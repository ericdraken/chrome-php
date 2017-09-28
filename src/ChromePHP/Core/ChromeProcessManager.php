<?php
/**
 * ChromePHP - ChromeProcessManager.php
 * Created by: Eric Draken
 * Date: 2017/9/11
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Core;


use Draken\ChromePHP\Commands\ChromeCommands;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Draken\ChromePHP\Queue\NullPromiseProcess;
use Draken\ChromePHP\Queue\ProcessManager;
use Draken\ChromePHP\Queue\ProcessorCounter;
use Draken\ChromePHP\Utils\Paths;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Symfony\Component\Process\Exception\ProcessFailedException as SymfonyProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Class ChromeProcessManager
 *
 * @package Draken\ChromePHP\Core
 */
class ChromeProcessManager extends ProcessManager
{
	/** Retry delay in seconds */
	const CHROME_CONNECT_RETRY_DELAY_FLOAT = 0.5;

	/** The number of errors (e.g. remote debugger connect errors) to tolerate*/
	const CHROME_MAX_NUM_ERRORS_BEFORE_TERMINATION = 4;

	/**
	 * How many processor terminations to tolerate before refusing
	 * to spin up any more processors
	 */
	const MAX_TERMINATIONS_BEFORE_DECREASING_PARALLELIZATION_LIMIT = 2;

	/** @var int */
	private $chromeTerminationsCount = 0;

	/** @var int */
	private $parallelizationLimit;

	/** @var ChromeProcessors */
	private $chromeProcessors;

	/** @var Promise */
	private $queuePromise;

	/** @var \Exception */
	private $queueException;

	/**
	 * ChromeProcessManager constructor.
	 *
	 * @param int $startingChromePort
	 * @param null $parallelizationLimit
	 * @param string $chromeBinaryPath
	 */
	public function __construct( int $startingChromePort = 9222, $parallelizationLimit = null, $chromeBinaryPath = '' )
	{
		// Create a new Chrome processors manager
		// but set the binary path later
		$this->chromeProcessors = new ChromeProcessors( $startingChromePort, $chromeBinaryPath );

		// Get the number of processors available
		$physicalLimit = (new ProcessorCounter())->getCpuCount();

		$limit = !is_null($parallelizationLimit) ? $parallelizationLimit : $physicalLimit;

		if (!is_numeric($limit)) {
			throw new InvalidArgumentException(sprintf('Parallelization limit must be numeric, type "%s" given', gettype($limit)));
		}

		$limit = intval($limit, 10);
		if ($limit < 1) {
			throw new InvalidArgumentException('Parallelization limit must be greater than 0');
		}

		// Log a limit warning
		if ( $limit > $physicalLimit ) {
			self::logger()->warning("The requested parallelization limit ($limit) is higher than the CPU limit ($physicalLimit). Chrome processes may suffer.");
		}

		$this->parallelizationLimit = $limit;

		// Create the process queue and set the processor limit
		parent::__construct( $this->parallelizationLimit );

		// Pass into the queue a completion logic handler
		// This way the handler can remain private
		$this->setQueueCompletionHook( function( NodeProcess $process ) {
			return $this->beforeClearCompletedProcess( $process );
		} );

		// Set the local Puppeteer-supplied Chromium binary if no path is supplied
		if ( empty( $chromeBinaryPath ) ) {
			$this->chromeProcessors->setChromeBinaryPath( Paths::getLocalChromiumBinaryPath() );
		}

		// Store an internal promise to be resolved after run()
		$queue = $this->processQueue;
		$queuePromise = new Promise( function () use ( &$queuePromise, &$queue ) {
			/** @var Promise $queuePromise */

			// Resolve the promise
			$completedProcesses = $queue->getCompletedProcesses();
			$successfulProcesses = $queue->getSuccessfulProcesses();
			if ( ! $this->queueException && count( $completedProcesses ) === count( $successfulProcesses ) )
			{
				// Fulfill the promise as all the processes have successfully completed
				$queuePromise->resolve( $queue );
			}
			else if ( ! $this->queueException )
			{
				// Reject this promise
				$queuePromise->reject( [
					$queue,
					new RuntimeException( "Process queue was not entirely successful", 1 )
				] );
			}
			else
			{
				// Reject this promise due to an exception
				$queuePromise->reject( [ $queue, $this->queueException ] );
			}
		} );
		$this->queuePromise = $queuePromise;
	}

	/**
	 * Forward the 'then' method of a Promise to the internal Promise
	 *
	 * @param callable|null $onFulfilled
	 * @param callable|null $onRejected
	 *
	 * @return Promise|PromiseInterface
	 */
	public function then(
		callable $onFulfilled = null,
		callable $onRejected = null
	): Promise {
		return $this->queuePromise->then( $onFulfilled, $onRejected );
	}

	/**
	 * Forward the 'otherwise' method of a Promise to the internal Promise
	 *
	 * @param callable $onRejected
	 *
	 * @return Promise|PromiseInterface
	 */
	public function otherwise( callable $onRejected ): Promise {
		return $this->queuePromise->otherwise( $onRejected );
	}

	/**
	 * If this method returns TRUE, then the process will be
	 * restarted afresh and its promise handler will not be called.
	 * Use this to restart processes that are waiting for some system
	 * resource to come available, like Chrome remote debugger. It should
	 * also update the Chrome process state when a process has ended
	 *
	 * @param NodeProcess $process
	 *
	 * @return bool
	 */
	private function beforeClearCompletedProcess( NodeProcess $process )
	{
		assert( $process->isTerminated() );

		// No need to restart this process
		if ( $process->isSuccessful() ) {
			return false;
		}

		// Decide if this process should be tried again
		// This method may kill a failed Chrome process
		return $this->isRecoverableError( $process );
	}

	/**
	 * Returns true if the process should be retired, false otherwise
	 * @param NodeProcess $process
	 *
	 * @return bool
	 */
	private function isRecoverableError( NodeProcess $process )
	{
		// TODO: Cannot find module '/tmp/chrome-php.tcYYvvo/59c17caaeab9b.js' should just fail

		// TODO: Handle "Error: unexpected server response (500)"

		// TODO: Inspect the error, and collaborate with NodeProcess to ask
		// the completed process if it wants to run again, or just fail

		$err = $process->getErrorOutput();

		if (
			// Chrome either hasn't loaded yet, is too busy, went away, or
			// something is suddenly blocking the port
			strpos( $err, 'ECONNREFUSED' ) !== false ||

			// Unable to lock onto a Chrome page or tab
			// REF: chrome-remote-interface/lib/chrome.js:40
			strpos( $err, 'No inspectable targets' ) !== false ||

			// The WebSocket connection to Chrome closed unexpectedly
			// REF: ws/lib/WebSocket.js:356
		    strpos( $err, 'WebSocket.send' !== false ) ||

			// Chrome socket or session closed
			// REF: node_modules/puppeteer/lib/Connection.js
			strpos( $err, 'closed' !== false )
		) {
			$this->handleRecoverableConnectionError( $process );
			return true;    // Try the process again later
		}

		$lastException = $process->getLastException();

		if (
			// The process timed out
			$lastException &&
			$lastException instanceof ProcessTimedOutException &&
			$lastException->isIdleTimeout()
		) {
			$this->handleRecoverableConnectionError( $process );
			return true;    // Try the process again later
		}

		return false;
	}

	/**
	 * @param NodeProcess $process
	 */
	private function handleRecoverableConnectionError( NodeProcess $process )
	{
		$port = $process->getAssignedPort();
		assert( ! empty( $port ) );

		// 1) Has the processor been purposefully killed?
		if ( ! $this->chromeProcessors->isPortViable( $port ) )
		{
			// Try the process again on a different processor
			return;
		}

		// 2) Has the assigned processor gone away?
		// Chrome will sometimes segfault if it runs out of memory,
		// or it will stutter if it runs out of temp disk space
		if ( ! ChromeCommands::isChromeRunning( $port ) )
		{
			// The processor has gone away, but the system hasn't dealt with it yet
			LoggableBase::logger()->error( "Chrome on port $port has gone away" );

			// Deal with the failed processor
			$this->chromeProcessors->quitProcessorOnPort( $port, false );
			$this->chromeTerminationsCount++;

			// Try the process again on a different processor
			return;
		}

		// 3) This processor is still running, but just refused the connection?
		$info = $this->chromeProcessors->getActiveProcessorInfoOnPort( $port );
		assert( $info );

		// Increase the number of processor exceptions
		$info->addException( $process->getLastException() ?: new SymfonyProcessFailedException( $process ) );

		// 3a) Should we keep trying to connect?
		if ( $info->getNumExceptions() < self::CHROME_MAX_NUM_ERRORS_BEFORE_TERMINATION )
		{
			LoggableBase::logger()->warning( "Couldn't connect to Chrome debugger on port $port. Trying again." );
		}

		// 3b) This processor has failed too many times. It will be terminated
		else
		{
			LoggableBase::logger()->error( "Unable to connect to Chrome remote debugger [port $port] after several tries. Giving up." );

			// This processor is possibly faulty, so kill it immediately,
			$this->chromeProcessors->quitProcessorOnPort( $port, false );
			$this->chromeTerminationsCount++;
		}
	}

	/**
	 * If there are too many processor terminations, then decrease the parallelization limit
	 * to use one less processor to complete the queue
	 */
	private function updateParallelizationLimit()
	{
		if ( $this->chromeTerminationsCount >= self::MAX_TERMINATIONS_BEFORE_DECREASING_PARALLELIZATION_LIMIT )
		{
			// Reset this counter
			$this->chromeTerminationsCount = 0;

			// Decrease the parallelization limit
			$this->parallelizationLimit--;

			self::logger()->critical("Processor terminations have exceeded tolerance. Decreasing parallelization limit to ({$this->parallelizationLimit}).");

			// Not possible to set the limit below zero,
			// but the other controls will know know when
			// the limit has gone below zero
			if ( $this->parallelizationLimit > 0 ) {
				$this->getProcessQueue()->updateLimit( $this->parallelizationLimit );
			}
		}
	}

	/**
	 * Add another processors if there is room to do so
	 * and it is needed
	 */
	private function spinUpProcessor()
	{
		$numChromeProcesses = $this->chromeProcessors->numActiveChromeProcessors();
		if (
			// Are more processors needed?
			$this->processQueue->count() > $numChromeProcesses &&

			// Is another processor available?
			$numChromeProcesses < $this->parallelizationLimit
		) {
			$this->chromeProcessors->spinUpNewChromeProcessor();
		}
	}

	/**
	 * Add a non-blocking delay to the Node process if
	 * the processor has experienced connection issues before
	 *
	 * @param NodeProcess $process
	 */
	private function throttleProcessor( NodeProcess $process )
	{
		// Insert a start delay of this process if the processor is having connectivity trouble
		$info = $this->chromeProcessors->getActiveProcessorInfoOnPort( $process->getAssignedPort() );
		assert( $info );
		if ( ($exceptionsCount = $info->getNumExceptions()) > 0 )
		{
			// TODO: Don't throttle forever

			// This will not block the main loop, just the forked process
			// Also, increase the delay proportional to the number of
			// connection problems this processor has experienced
			$process->setInitialDelay( self::CHROME_CONNECT_RETRY_DELAY_FLOAT * $exceptionsCount );
		}
		else
		{
			// This may be a new processor, so remove any previous delay
			$process->setInitialDelay(0);
		}
	}

	/**
	 * @param NodeProcess $process
	 */
	private function startProcess( NodeProcess $process )
	{
		self::logger()->debug("EXEC: {$process->getCommandLine()}");

		$process->start();
	}

	/**
	 * @param \Closure|null $tick
	 *
	 * @throws RuntimeException
	 */
	public function run(\Closure $tick = null)
	{
		try
		{
			$processQueue = $this->processQueue;

			/** @var NodeProcess $next */
			foreach ($processQueue() as $next)
			{
				// This will flush Chrome console buffers to the start() callback
				// for real-time output monitoring and to reset the idle timeout
				$this->chromeProcessors->flushBuffers();

				// Run idle timeout checks on the Chrome processors, quitting frozen
				// processors if their idle timeouts have expired
				$this->chromeTerminationsCount += $this->chromeProcessors->handleTimeouts();

				// Skip needless processing
				if ( $next instanceof NullPromiseProcess )
				{
					$tick && $tick();
					continue;
				}

				// Decrease the parallelization limit if there
				// are too many processor terminations
				$this->updateParallelizationLimit();

				// Spin up another Chrome process if needed and allowed
				$this->spinUpProcessor();

				if ( $this->chromeProcessors->numActiveChromeProcessors() === 0 ) {
					throw new RuntimeException("Ran out of processors to finish the queue. All went away.");
				}

				// Free up processors
				$this->chromeProcessors->unassignTerminatedProcesses();

				// Assign this process to the next free Chrome processor
				// If none are available yet, then try the process again later
				if ( ! $this->chromeProcessors->assignProcessToNextFreeProcessor( $next ) )
				{
					$this->processQueue->postpone( $next );

					$tick && $tick();
					continue;
				}

				// Throttle the processor if is having trouble
				$this->throttleProcessor( $next );

				// Start the process
				$this->startProcess( $next );

				// Tick
				$tick && $tick();
			}

			assert( count( $this->processQueue->getPending() ) === 0 && count( $this->processQueue->getRunning() ) === 0 );
		}
		catch ( \Exception $e ) {
			// Reject the promise due to this exception
			$this->queueException = $e;
		}
		finally {
			// Resolve the promise immediately
			$this->queuePromise->wait( false );

			// Quit Chrome processors explicitly because
			// at this point all the Node processes should be finished
			$this->chromeProcessors->quitAllChromeProcessors( true );
		}
	}
}
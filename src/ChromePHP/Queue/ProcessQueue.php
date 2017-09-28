<?php
/**
 * ChromePHP - ProcessQueue.php
 * Created by: Eric Draken
 * Date: 2017/9/10
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Queue;

use Draken\ChromePHP\Core\LoggableBase;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Class ProcessQueue
 *
 */
class ProcessQueue extends LoggableBase implements \Countable
{
	/**
	 * 1ms sleep time is reasonable because on
	 * each tick there is a timeout check on each
	 * running process
	 */
	const SLEEP_MICRO_SECONDS = 1000;

	/** @var int */
	private $limit = -1;

	/** @var PromiseProcess[] */
	private $queue = [];

	/** @var PromiseProcess[] */
	private $completedProcesses = [];

	/** @var Callable */
	private $completionHook;

	/**
	 * ProcessQueue constructor
	 *
	 * @param int $limit
	 */
	public function __construct( $limit = null )
	{
		$limit = !is_null($limit) ? $limit : (new ProcessorCounter())->getCpuCount();
		$this->updateLimit( $limit );
	}

	/**
	 * Update the simultaneous queue processing limit
	 * @param int $limit
	 */
	public function updateLimit( int $limit )
	{
		if ( ! is_numeric( $limit ) ) {
			throw new InvalidArgumentException( sprintf( 'Limit must be numeric, type "%s" given',
				gettype( $limit ) ) );
		}

		if ( $limit < 1 ) {
			throw new InvalidArgumentException( 'Process limit must be greater than 0' );
		}

		$limit = intval( $limit, 10 );

		if ( $this->limit > 0 ) {
			self::logger()->alert( "Changing process limit to $limit" );
		}

		$this->limit = $limit;
	}

	/**
	 * Return current queue count
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->queue );
	}

	/**
	 * @return PromiseProcess[]
	 */
	public function getCompletedProcesses(): array {
		return $this->completedProcesses;
	}

	/**
	 * @return PromiseProcess[]
	 */
	public function getSuccessfulProcesses(): array
	{
		return array_filter($this->completedProcesses, function(PromiseProcess $process) {
			return $process->isSuccessful();
		});
	}

	/**
	 * @return PromiseProcess[]
	 */
	public function getFailedProcesses(): array
	{
		return array_filter($this->completedProcesses, function(PromiseProcess $process) {
			return ! $process->isSuccessful();
		});
	}

	/**
	 * If this method returns TRUE, then the process will be
	 * restarted afresh and its promise handler will not be called.
	 * Use this to restart processes that are waiting for some system
	 * resource to come available, like Chrome remote debugger
	 * @param Callable $completionHook
	 */
	public function setCompletionHook( Callable $completionHook ) {
		$this->completionHook = $completionHook;
	}

	/**
	 * Add new process to the queue. If the process already
	 * exists in the queue, it will be moved to the end
	 *
	 * @param PromiseProcess $process
	 *
	 * @return ProcessQueue $this
	 */
	public function add( PromiseProcess $process ): self
	{
		// Prevent adding the same process multiple times
		$this->remove( $process );

		$this->queue[] = $process;
		return $this;
	}

	/**
	 * Remove all instances of this process from the queue.
	 * This should not be called by the public. Only resolveCompleted()
	 * should be responsible for removing processes from the queue
	 *
	 * @param PromiseProcess $process
	 *
	 * @return ProcessQueue
	 */
	private function remove( PromiseProcess $process ): self
	{
		// Nice way to remove multiple identical processes
		$this->queue = array_filter( $this->queue, function( $test ) use ( &$process ) {
			return $test !== $process;
		} );
		return $this;
	}

	/**
	 * Postpone a Process by removing it at its current point in the queue,
	 * if is in the queue at all, and re-add it to the end of the queue
	 * @param PromiseProcess $process
	 *
	 * @return ProcessQueue
	 */
	public function postpone( PromiseProcess $process ): self {
		// Simply add it back because in the add() method a remove()
		// is called to prevent multiple same processes from being added
		return $this->add( $process );
	}

	/**
	 * Return pending processes
	 *
	 * @return array
	 */
	public function getPending(): array
	{
		return array_filter($this->queue, function(PromiseProcess $process) {
			return ! $process->isStarted();
		});
	}

	/**
	 * Return running processes
	 *
	 * @return array
	 */
	public function getRunning(): array
	{
		return array_filter($this->queue, function(PromiseProcess $process) {
			return $process->isRunning();
		});
	}

	/**
	 * Return completed processes
	 *
	 * @return array
	 */
	public function getCompleted(): array
	{
		return array_filter($this->queue, function(PromiseProcess $process) {
			return $process->isTerminated();
		});
	}

	/**
	 * Resolve completed processes from the queue,
	 * or re-enqueue them if they should be
	 *
	 * @return void
	 */
	public function resolveCompleted()
	{
		$completed = $this->getCompleted();

		/** @var PromiseProcess $process */
		foreach($completed as $process)
		{
			// Remove the terminated process
			$this->remove( $process );

			try
			{
				// If the completionHook function returns TRUE, then re-enqueue
				// this process to try it again instead of clearing it. This
				// is a chance to try again on a recoverable error
				if ( $this->completionHook && call_user_func( $this->completionHook, $process ) )
				{
					// Add a clean process to try again
					$this->add( $process->cloneCleanProcess() );

					continue;
				}
			}
			catch ( \Exception $e )
			{
				// If an exception is thrown in the user function,
				// treat the result as 'false'
				self::logger()->error( "completionHook function threw an error: {$e->getMessage()}" );
			}

			// Save this process to the completed process array
			$this->completedProcesses[] = $process;

			/** @var PromiseProcess $process */
			$promise = $process->getPromise();

			// Unwrap and resolve immediately
			$promise->wait( false );
		};
	}

	/**
	 * Set the last exception caught, reject the promise,
	 * and remove the process from the queue
	 *
	 * @param PromiseProcess  $process
	 * @param \Exception|null $exception
	 */
	public function setLastException(PromiseProcess $process, \Exception $exception = null)
	{
		// Immediately reject the promise after setting the exception
		if ( $process instanceof PromiseProcess )
		{
			// Either set the received exception, or create a new exception
			// with the stderr of the Process
			$process->setLastException(
				$exception instanceof \Exception ?
					$exception :
					new \RuntimeException( $process->getErrorOutput() )
			);
		}
	}

	/**
	 * Check the timeouts of all the Processes that
	 * currently running. If they have timed out, then
	 * try to resolve their promises
	 */
	public function checkTimeouts()
	{
		/** @var PromiseProcess $process */
		foreach ( $this->getRunning() as $process )
		{
			try
			{
				// If the process has timed out, an exception will be thrown
				$process->checkTimeout();
			}
			catch ( ProcessTimedOutException $e )
			{
				self::logger()->warning("Process [{$process->getUniqueId()}] has timed out");

				// Set the last exception of this process
				$this->setLastException( $process, $e );
			}
		}
	}

	/**
	 * Run the queue
	 *
	 * @return \Generator
	 */
	public function __invoke()
	{
		// While there are pending processes
		while ( count( $this->queue ) )
		{
			usleep(self::SLEEP_MICRO_SECONDS);

			// Check for processes that have timed out
			$this->checkTimeouts();

			// Resolve completed process promises
			$this->resolveCompleted();

			$pending = $this->getPending();
			$pendingCount = count( $pending );
			$runningCount = count( $this->getRunning() );

			if ( $pendingCount && $runningCount < $this->limit ) {
				yield current( $pending );
			} else {
				yield new NullPromiseProcess();
			}
		}

		// Resolve any missed completed process promises
		$this->resolveCompleted();
	}
}

<?php
/**
 * ChromePHP - ChromeProcessors.php
 * Created by: Eric Draken
 * Date: 2017/9/13
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Core;


use Draken\ChromePHP\Commands\ChromeCommands;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class ChromeProcessors extends LoggableBase
{
	/** @var int */
	private $nextPort;

	/** @var int */
	private $numActiveChromeProcessors = 0;

	/**
	 * Processors running now
	 * @var ChromeProcessorInfo[] $activeChromeProcessors
	 */
	private $activeChromeProcessors = [];

	/**
	 * Processors that have gone away
	 * @var ChromeProcessorInfo[] $killedChromeProcessors
	 */
	private $killedChromeProcessors = [];

	/** @var string */
	private $chromeBinaryPath = '';

	/**
	 * ChromeProcessors constructor.
	 *
	 * @param int $startingPort
	 * @param string $chromeBinaryPath
	 */
	public function __construct( $startingPort = 9222, $chromeBinaryPath = '' )
	{
		// Verify and save port
		$port = intval($startingPort, 10);
		if ($port <= 0) {
			throw new InvalidArgumentException("Starting port must be greater than 0. Got $startingPort");
		}

		$this->nextPort = $startingPort;
		$this->setChromeBinaryPath( $chromeBinaryPath );
	}

	/**
	 * Set the Chrome|Chromium binary path to be used for the Chrome processors
	 * @param string $chromeBinaryPath
	 */
	public function setChromeBinaryPath( string $chromeBinaryPath ) {
		$this->chromeBinaryPath = $chromeBinaryPath;
	}

	/**
	 * Start a new Chrome process at the next incremented port
	 * starting at the starting port supplied in the constructor
	 */
	public function spinUpNewChromeProcessor()
	{
		// Start a chrome processes at the base port
		// and increment the port as new processes are added
		$chromeProcess = new ChromeProcess( $this->chromeBinaryPath );

		// TODO: Where will we set the UA?

		$port = $this->nextPort++;
		self::logger()->info("Starting Chrome process on port $port");
		$chromeProcess->launchHeadlessChrome( $port );

		// Add this processor to the pool
		$this->numActiveChromeProcessors++;
		$this->activeChromeProcessors[ $port ] = new ChromeProcessorInfo( $chromeProcess );

		assert( $this->numActiveChromeProcessors == count( $this->activeChromeProcessors ) );
	}

	/**
	 * @return int
	 */
	public function numActiveChromeProcessors(): int
	{
		assert( $this->numActiveChromeProcessors == count( $this->activeChromeProcessors ) );
		return $this->numActiveChromeProcessors;
	}

	/**
	 * If a processor has been killed, then this
	 * port is not viable now or in the future
	 * @param int $port
	 *
	 * @return bool
	 */
	public function isPortViable( int $port ): bool {
		return ! isset( $this->killedChromeProcessors[ strval($port) ] );
	}

	/**
	 * @param int $port
	 *
	 * @return ChromeProcessorInfo
	 */
	public function getActiveProcessorInfoOnPort( int $port ): ChromeProcessorInfo
	{
		$port = strval($port);
		if ( ! isset( $this->activeChromeProcessors[$port] ) ) {
			throw new RuntimeException("There is no active processor on port $port");
		}

		return $this->activeChromeProcessors[$port];
	}

	/**
	 * Flush the console output of every active processor for real-time
	 * display as well as to reset the idle timeout
	 */
	public function flushBuffers()
	{
		foreach ( $this->activeChromeProcessors as $port => $info )
		{
			// Flush console output for real-time display as well
			// as to reset the idle timeout
			$info && $info->getChromeInstance()->flushBuffers();
		}
	}

	/**
	 * Handle the timeouts of all the Chrome Processes that
	 * are currently running. If they have timed out, close them gracefully.
	 * Return the number of processors that have timeout out and been closed
	 *
	 * @return int
	 */
	public function handleTimeouts(): int
	{
		$failures = 0;

		foreach ( $this->activeChromeProcessors as $port => $info )
		{
			try
			{
				// If the process has timed out, an exception will be thrown
				$info && $info->getChromeInstance()->checkTimeout();
			}
			catch ( ProcessTimedOutException $e )
			{
				self::logger()->warning("Chrome processor [port: {$info->getPort()}] has timed out. Quitting.");

				// Kill any process that is running on that processor as well
				$assignedProcess = $info->getAssignedProcess();
				if ( $assignedProcess && $assignedProcess->isRunning() )
				{
					self::logger()->warning("Forcing process [{$assignedProcess->getUniqueId()}] to stop due to processor timeout");
					$assignedProcess->stop(0, 9 );

					$assignedProcess->setLastException( new RuntimeException("Process killed due to Chrome idle timeout") );
				}

				// Kill the processor that has timed out
				$this->quitProcessorOnPort( $info->getPort(), false );
				$failures++;
			}
		}

		return $failures;
	}

	/**
	 * Stop a processor on a given port
	 * @param int  $port
	 * @param bool $graceful
	 */
	public function quitProcessorOnPort( int $port, $graceful = true )
	{
		$port = strval($port);
		if ( ! isset( $this->activeChromeProcessors[$port] ) )
		{
			if ( ! isset( $this->killedChromeProcessors[$port] ) ) {
				throw new RuntimeException("There is no active or stopped processor on port $port");
			}

			self::logger()->warning("Processor on port $port already stopped");
			return;
		}

		self::logger()->info("Quitting processor on port $port" );

		// Quit Chrome if it is still running
		/** @var ChromeProcess $processor */
		$processorInfo = $this->activeChromeProcessors[ $port ];
		if ( $processorInfo->getChromeInstance() ) {
			$processorInfo->getChromeInstance()->quitAndCleanup( $graceful );
		} else {
			self::logger()->warning("Chrome instance not found in an info object");
			ChromeCommands::quitChromeInstanceGracefully( $port );
		}

		// Add an entry to the killed processors list
		$this->killedChromeProcessors[ $port ] = $processorInfo;

		// Remove the entry from the active processors list
		unset( $this->activeChromeProcessors[ $port ] );
		$this->numActiveChromeProcessors--;

		assert( $this->numActiveChromeProcessors == count( $this->activeChromeProcessors ) );
	}

	/**
	 * Quit all Chrome processes
	 * @param bool $graceful
	 */
	public function quitAllChromeProcessors( $graceful = true )
	{
		// Quit Chrome processes explicitly
		foreach ( $this->activeChromeProcessors as $port => $processInfo )
		{
			self::logger()->info("Quitting Chrome process on port $port");
			$processInfo->getChromeInstance()->quitAndCleanup( $graceful );

			// Add an entry to the killed processors list
			$this->killedChromeProcessors[ $port ] = new ChromeProcessorInfo( $processInfo->getChromeInstance() );
		}

		// Reset the array and count of active processors
		$this->numActiveChromeProcessors = 0;
		$this->activeChromeProcessors    = [];
	}

	/**
	 * Check if there is a free processor available
	 * @return bool
	 */
	public function hasFreeChromeProcessor() {
		return !!$this->findFreeChromeProcessorInfo();
	}

	/**
	 * Find and return a free Chrome process if there is one,
	 * or return false if none are found
	 * @return bool|ChromeProcessorInfo
	 */
	private function findFreeChromeProcessorInfo()
	{
		if ( $this->numActiveChromeProcessors() <= 0 ) {
			self::logger()->warning("There are no processors running");
			return false;
		}

		foreach ( $this->activeChromeProcessors as $port => $info )
		{
			// No process is assigned to this processor
			if ( ! $info->hasAssignedProcess() ) {
				return $info;
			}
		}

		return false;
	}

	/**
	 * Unassign all terminated processes
	 */
	public function unassignTerminatedProcesses()
	{
		foreach ( $this->activeChromeProcessors as $port => $info )
		{
			// Unassign terminated processes
			if ( $info->hasAssignedProcess() && $info->getAssignedProcess()->isTerminated() ) {
				$info->unassignProcess();
			}
		}
	}

	/**
	 * @param NodeProcess $process
	 *
	 * @return bool
	 */
	public function assignProcessToNextFreeProcessor( NodeProcess $process )
	{
		$freeProcessorInfo = $this->findFreeChromeProcessorInfo();
		if ( ! $freeProcessorInfo ) {
			self::logger()->warning("There are no free processors. Processors: $this");
			return false;
		}

		$freePort = $freeProcessorInfo->getPort();
		assert( is_int( $freePort ) );

		// Assign the port
		$process->setAssignedPort( $freePort );

		// Assign the WS endpoint URL as well
		$process->setAssignedWsEndpointUrl( $freeProcessorInfo->getChromeInstance()->getWsEndpointUrl() );

		// Assign the working folder
		$process->setAssignedWorkingFolder( $freeProcessorInfo->getChromeInstance()->getTempFolder() );

		// Assign the process
		$freeProcessorInfo->assignProcess( $process );
		return true;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		$out1 = [];
		foreach ( $this->activeChromeProcessors as $port => $info )
		{
			$assignedProcess = $info->hasAssignedProcess() ? $info->getAssignedProcess() : false;

			$out1[] = [
				"port" => $port,
				"exceptions" => $info->getNumExceptions(),
				"assignedProcessUid" => $assignedProcess ? $assignedProcess->getUniqueId() : false,
				"assignedProcessPid" => $assignedProcess ? $assignedProcess->getPid() : false,
				"assignedProcessCmd" => $assignedProcess ? $assignedProcess->getCommandLine() : false
			];
		}

		$out2 = [];
		foreach ( $this->killedChromeProcessors as $port => $info )
		{
			$assignedProcess = $info->hasAssignedProcess() ? $info->getAssignedProcess() : false;

			$out2[] = [
				"port" => $port,
				"exceptions" => $info->getNumExceptions(),
				"assignedProcessUid" => $assignedProcess ? $assignedProcess->getUniqueId() : false,
				"assignedProcessPid" => $assignedProcess ? $assignedProcess->getPid() : false,
				"assignedProcessCmd" => $assignedProcess ? $assignedProcess->getCommandLine() : false
			];
		}

		return (string)print_r( ['active' => $out1, 'killed' => $out2], true );
	}
}
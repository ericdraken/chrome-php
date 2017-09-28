<?php
/**
 * ChromePHP - ChromeProcess.php
 * Created by: Eric Draken
 * Date: 2017/9/8
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Core;

use Draken\ChromePHP\Commands\ChromeCommands;
use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Draken\ChromePHP\Utils\ChromeLogFilter;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Draken\ChromePHP\Utils\Utils;

final class ChromeProcess extends LoggableBase
{
	/** Number of tries to check if child processes are still running */
	const NUM_TRIES_TO_POLL_CHILD_PROCESSES_STILL_RUNNING = 4;

	/** The delay in seconds to start the exponential backoff when quitting Chrome */
	const EXPONENTIAL_BACKOFF_START_DELAY_SECONDS = 1;

	/** Chrome will be killed unless it has console output within every N seconds */
	const CHROME_IDLE_TIMEOUT = 120;

	/** The WS endpoint must be found within this number of seconds */
	const CHROME_WS_ENDPOINT_SEARCH_TIMEOUT = 5;

	/** @var Process */
	private $chromeProcess;

	/** @var string */
	private $tempFolder;

	/** @var int */
	private $port = 0;

	/** @var string */
	private $wsEndpointUrl = false;

	/** @var string */
	private $chromeBinaryPath = '';

	/** @var array */
	private $envVars = [];

	/** @var string */
	private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36';

	/**
	 * ChromeProcess constructor.
	 * Register a shutdown function to properly quit Chrome
	 * and remove the temp folders. This will happen even if
	 * an exception is thrown
	 *
	 * @param string $chromeBinaryPath
	 */
	public function __construct( $chromeBinaryPath = '' )
	{
		// Verify we are not the root user
		if ( posix_getuid() == 0 ) {
			throw new RuntimeException("Do not launch Chrome as root because it is not sandboxed.");
		}

		$this->setChromeBinaryPath( $chromeBinaryPath );

		// Register shutdown function to quit Chrome and clean up
		// Register an anonymous function to avoid
		// "Warning: (Registered shutdown functions) Unable to call"
		// messages during PHPUnit
		register_shutdown_function( function () {
			$this->quitAndCleanup();
		} );
	}

	/**
	 * @param array $envVars
	 */
	public function setEnvVars( array $envVars ) {
		$this->envVars = $envVars;
	}

	/**
	 * @return int
	 */
	public function getPort(): int {
		return $this->port;
	}

	/**
	 * @return string
	 */
	public function getWsEndpointUrl(): string {
		return $this->wsEndpointUrl ?: '';
	}

	/**
	 * @return string
	 */
	public function getTempFolder(): string {
		return $this->tempFolder;
	}

	/**
	 * @return bool
	 */
	public function isChromeRunning() {
		return $this->chromeProcess && $this->chromeProcess->isRunning();
	}

	/**
	 * @param string $chromeBinaryPath
	 */
	public function setChromeBinaryPath( string $chromeBinaryPath )
	{
		if ( ! empty( $chromeBinaryPath ) && ! file_exists( $chromeBinaryPath ) ) {
			throw new InvalidArgumentException( "Supplied Chrome binary path doesn't exist. Got: '$chromeBinaryPath'" );
		}

		$this->chromeBinaryPath = $chromeBinaryPath;
	}

	/**
	 * Set the default user agent
	 *
	 * @param $str
	 */
	public function setDefaultUserAgent($str)
	{
		if ( $this->chromeProcess && $this->chromeProcess->isRunning() )
		{
			throw new RuntimeException("Unable to change the user agent of a running Chrome process");
		}

		$this::logger()->info("Setting user agent to '$str'");

		$this->userAgent = $str;
	}

	/**
	 * Flush console output for real-time display as well
	 * as to reset the idle timeout. Any start() methods
	 * on this process should then receive buffer output
	 */
	public function flushBuffers()
	{
		// This call will trigger an updateStatus() which
		// will trigger a readPipes() which will flush the buffers.
		// Neat trick, hey?
		$this->chromeProcess && $this->chromeProcess->isRunning();
	}

	/**
	 * Launch a new instance of headless Chrome on the given port.
	 * You can either explicitly quit Chrome, which will also remove
	 * temp files, or this will happen automatically during GC
	 *
	 * @param int $port
	 */
	public function launchHeadlessChrome( $port = 9222 )
	{
		// Check if Chrome is running already
		if ( $this->isChromeRunning() ) {
			throw new RuntimeException("Chrome process already launched. Quit it first.");
		}

		$this::logger()->info("Launching Chrome on port $port");

		// Verify port
		$port = intval($port, 10);
		if ($port <= 0) {
			throw new InvalidArgumentException("Port must be greater than 0. Got $port");
		}

		$this->port = $port;
		self::logger()->info("Using port $port");

		// If there is a Chrome instance on this port,
		// quit it gracefully, then forcibly if needed
		ChromeCommands::quitChromeInstanceGracefully($port);

		// Tmp folder
		$this->tempFolder = Utils::makeUnixTmpDir();

		self::logger()->info("Using temp folder at {$this->tempFolder}");

		// Logs
		$out = $this->tempFolder . DIRECTORY_SEPARATOR . 'chrome-out.log';
		$err = $this->tempFolder . DIRECTORY_SEPARATOR . 'chrome-err.log';

		// Set or find a Chrome binary
		$this->chromeBinaryPath = ChromeCommands::findChromeBinPath( $this->envVars, $this->chromeBinaryPath );

		if ( ! file_exists( $this->chromeBinaryPath ) ) {
			throw new RuntimeException( "No Chrome binary supplied, and none can be found" );
		}

		$cmd = self::buildHeadlessChromeCmd(
			$port,
			$this->userAgent,
			$this->tempFolder,
			$this->chromeBinaryPath
		);

		self::logger()->info("Exec [ $cmd ]");

		// Launch the command
		$this->chromeProcess = new Process( $cmd, null, $this->envVars );

		// Set temporary WS endpoint search timeout
		$this->chromeProcess->setTimeout( self::CHROME_WS_ENDPOINT_SEARCH_TIMEOUT );

		// Run Chrome process asynchronously and log its output
		$this->chromeProcess->start( function ($type, $buffer) use (&$err, &$out) {

			// Save log to log files as well
			file_put_contents( Process::ERR === $type ? $err : $out, $buffer.PHP_EOL , FILE_APPEND | LOCK_EX);

			// Filter the log message from its context
			// and apply the level automatically
			ChromeLogFilter::logConsoleOutput( $buffer );

			// Search for the WS debugger endpoint in the console output
			if ( ! $this->wsEndpointUrl ) {
				if ( $this->wsEndpointUrl = ChromeLogFilter::extractWsEndpoint( $buffer ) ) {   // false if not found
					self::logger()->debug( "Found DevTools WS endpoint at '{$this->wsEndpointUrl}'" );
				}
			}
		} );

		// Wait until the WS endpoint is found, or throw an exception
		while ( ! $this->wsEndpointUrl && $this->chromeProcess->isRunning() )
		{
			try {
				// Check if the idle timeout is reached
				$this->chromeProcess->checkTimeout();
			} catch ( ProcessTimedOutException $e ) {
				throw new RuntimeException('Unable to get DevTools WS endpoint from Chrome console log');
			}

			// 1/100th of the timeout in seconds
			usleep(self::CHROME_WS_ENDPOINT_SEARCH_TIMEOUT * 10000 );
		}

		// Reset Chrome process timeouts
		$this->chromeProcess->setTimeout(0);
		$this->chromeProcess->setIdleTimeout( self::CHROME_IDLE_TIMEOUT );
	}

	/**
	 * Forward the 'getTimeout' method of Process to the internal process
	 *
	 * @return float|null
	 */
	public function getTimeout() {
		return $this->chromeProcess ? $this->chromeProcess->getTimeout() : null;
	}

	/**
	 * Forward the 'checkTimeout' method of Process to the internal process
	 *
	 * @throws ProcessTimedOutException
	 */
	public function checkTimeout() {
		$this->chromeProcess && $this->chromeProcess->checkTimeout();
	}

	/**
	 * Quit Chrome, the launching process and cleanup the temp folder.
	 * Try to wait until all child processes are finished before quitting Chrome.
	 * If $graceful is not true, then skip the child process check
	 *
	 * @param bool $graceful
	 */
	public function quitAndCleanup( $graceful = true )
	{
		if ( $graceful && ! empty( $this->chromeBinaryPath ) )
		{
			$tries = self::NUM_TRIES_TO_POLL_CHILD_PROCESSES_STILL_RUNNING;
			$pollInterval = self::EXPONENTIAL_BACKOFF_START_DELAY_SECONDS;

			do {
				$cmd = sprintf( LinuxCommands::countRunningChildProcessesCmd,
					getmypid(),
					basename( $this->chromeBinaryPath )
				);

				self::logger()->info( "Exec [ $cmd ]" );

				$process = new Process( $cmd );
				$process->run();

				// If there was an error, for some reason, then log it and pretend the
				// child process count is zero to exit the check loop
				if ( ! $process->isSuccessful() ) {
					self::logger()
					    ->error( "Child process count command failed [$cmd]. Error: "
					             . $process->getErrorOutput() );
				}

				// Get the number of running child processes yet to finish
				$count = $process->isSuccessful()
					? intval( $process->getOutput(), 10 ) : 0;

				if ( $count ) {
					self::logger()
					    ->info( "There are still $count child process(es) running. "
					            . ( $tries - 1 )
					            . " tries remaining. Trying again in {$pollInterval} seconds." );

					sleep( $pollInterval );

					// Exponential backoff
					$pollInterval *= 2;
				}
			} while ( $count > 0 && -- $tries > 0 );

			if ( $count > 0 ) {
				self::logger()
				    ->warning( "There are still $count child process(es) running, but Chrome will be terminated" );
			}
		}

		// Quit Chrome

		self::logger()->info("Quitting Chrome");

		$pid = ChromeCommands::getPidOfChromeBoundToPort($this->port);

		// Gracefully quit Chrome by sending the lowest signal to Chrome
		if ( ! $pid )
		{
			self::logger()->notice("Chrome not running on {$this->port}.");
		}
		else if ( ! ChromeCommands::killChromeProcesses(2, $pid) )
		{
			self::logger()->error("Unable to quit Chrome PID $pid");
		}

		// We tried to quit Chrome gracefully, but if it is still running,
		// then send a stronger signal through the process
		if ( $this->chromeProcess && $this->chromeProcess->isRunning() )
		{
			self::logger()->info("Stopping process " . $this->chromeProcess->getPid());

			// Just in case Chrome isn't responding to the stronger signal,
			// send the strongest signal after a short delay
			$this->chromeProcess->stop(5, 9);
		}

		// Remove the temp folder
		if ( is_writable( $this->tempFolder ) )
		{
			self::logger()->info("Rmrf'ing temp folder {$this->tempFolder}");

			// Cleanup temp folder
			$res = Utils::rmrfDir( $this->tempFolder );
			if (!!$res)
			{
				// There was an error rmrf'ing
				self::logger()->error("Unable to rm -rf: Result '$res'");
			}
		}
		else
		{
			self::logger()->info("Temp folder {$this->tempFolder} already removed");
		}
	}

	/**
	 * Construct the headless Chrome command
	 *
	 * @param int $port
	 * @param string $useragent
	 * @param string $tmpdir
	 * @param string $binpath
	 *
	 * @return string
	 */
	private function buildHeadlessChromeCmd($port, $useragent, $tmpdir, $binpath = ''): string
	{
		// Remove NOWDOC newlines
		$headlessChromeCmd =
			preg_replace(
				'/[\\n\\r]+/',
				'',
				LinuxCommands::headlessChromeCmd
			);

		// Construct the command
		$cmd = vsprintf( implode(' ', $headlessChromeCmd), array_map('escapeshellarg',[
			$binpath,   // chrome binary path, null if search with 'which'
			$port,      // '--remote-debugging-port=%u',
			$tmpdir,    // '--profile-directory=%s',
			$tmpdir,    // '--user-data-dir=%s',
			$tmpdir,    // '--homedir=%s',
			$useragent  // '--user-agent=%s'
		]) );

		return $cmd;
	}
}
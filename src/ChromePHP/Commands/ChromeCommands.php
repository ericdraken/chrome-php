<?php
/**
 * ChromePHP - ChromeCommands.php
 * Created by: Eric Draken
 * Date: 2017/9/10
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Commands;


use Draken\ChromePHP\Core\LoggableBase;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Class ChromeCommands
 *
 * @package Draken\ChromePHP\Core
 */
class ChromeCommands extends LoggableBase
{
	/**
	 * Return any found Chrome binary, or the supplied binary
	 *
	 * @param string $binpath
	 *
	 * @param array $env
	 *
	 * @return string
	 */
	public static function findChromeBinPath( array $env = [], string $binpath = '' )
	{
		if ( ! file_exists( $binpath ) ) {
			$binpath = '';
		}

		// Remove NOWDOC newlines
		$cmd = sprintf(
			preg_replace(
				'/[\\n\\r]+/',
				'',
				LinuxCommands::chromeBinaryCmd
			),
			escapeshellarg( $binpath )
		);
		$process = new Process( $cmd, null, $env );
		$process->run();
		return trim( $process->getOutput() );
	}

	/**
	 * Return the version of the supplied Chrome binary,
	 * else the version the system recognizes, else false
	 * The system Chrome version is found by $(which google-chrome)
	 *
	 * @param string $binpath
	 *
	 * @param array $env
	 *
	 * @return bool|string
	 */
	public static function getInstalledChromeVersion( array $env = [], string $binpath = '' )
	{
		if ( !empty($binpath) && !is_executable($binpath) )
		{
			throw new InvalidArgumentException("'$binpath' is not found or not executable. Cannot get Chrome version");
		}

		$binpath = self::findChromeBinPath( $env, $binpath );
		$cmd = "$binpath --version";

		self::logger()->info("Exec [ $cmd ]");

		// Run the check
		$process = new Process( $cmd );
		$process->run();

		// On failure, throw exception
		if ( ! empty( $process->getErrorOutput() ) )
		{
			throw new ProcessFailedException( $process );
		}

		// Extract the version if found
		$res = $process->getOutput();
		return $res === "0" ? false : $res;
	}

	/**
	 * Build the command to create an SSH tunnel to the Chrome debug port
	 * @param int $exposedPort
	 * @param int $debugPort
	 * @param int $sshPort
	 * @return string
	 */
	public static function createSSHTunnelToChrome( int $exposedPort = 9223, int $debugPort = 9222, int $sshPort = 22 )
	{
		$cmd = vsprintf(LinuxCommands::sshTunnelCmd, [$exposedPort, $debugPort, $sshPort]);

		throw new RuntimeException("-- Not implemented yet --");
	}

	/**
	 * Check if Chrome is running on the bound port
	 * @param int $port
	 *
	 * @return bool
	 */
	public static function isChromeRunning( int $port )
	{
		return !!self::getPidOfChromeBoundToPort($port);
	}

	/**
	 * Check if the desired port is already bound
	 * @param int $port
	 * @return bool
	 */
	public static function isPortBound( int $port )
	{
		// Verify port
		$port = intval($port, 10);
		if ($port <= 0) {
			throw new InvalidArgumentException("Port must be greater than 0. Got $port");
		}

		$cmd = sprintf(LinuxCommands::checkIfPortBoundCmd, $port);

		self::logger()->info("Exec [ $cmd ]");

		// Run the check
		$process = new Process( $cmd );
		$process->run();

		// On failure, throw exception
		if ( ! empty( $process->getErrorOutput() ) )
		{
			throw new ProcessFailedException( $process );
		}

		// Extract the PID if found
		$res = $process->getOutput();
		return $process->isSuccessful() && ! empty( $res );
	}

	/**
	 * Check if the desired port is bound to Chrome properly by
	 * returning the PID of the found bound process
	 * @param int $port
	 * @return int|false Return the PID or false if not found
	 */
	public static function getPidOfChromeBoundToPort( int $port )
	{
		// Verify port
		$port = intval($port, 10);
		if ($port <= 0) {
			return false;
		}

		$cmd = sprintf( LinuxCommands::checkIfChromePortBoundCmd, $port );

		self::logger()->info("Exec [ $cmd ]");

		// Run the check
		$process = new Process( $cmd );
		$process->run();

		// On failure, throw exception
		if ( ! empty( $process->getErrorOutput() ) )
		{
			throw new ProcessFailedException( $process );
		}

		// Extract the PID if found
		$res = $process->getOutput();
		if ( $process->isSuccessful() && ! empty( $res ) )
		{
			$re = '/(^.?[0-9]+)/';
			preg_match( $re, $res, $matches, 0, 0 );
			if ( count( $matches ) > 0 )
			{
				return trim( $matches[0] );
			}
			throw new RuntimeException( "Couldn't extract the PID. Got '$res'" );
		}

		return false;
	}

	/**
	 * Return how many Chrome processes are running
	 * @return bool
	 * @throws \RuntimeException
	 */
	public static function getNumChromeProcessesRunning()
	{
		$cmd = LinuxCommands::countRunningChromeProcessesCmd;

		self::logger()->info("Exec [ $cmd ]");

		// Run the check
		$process = new Process( $cmd );
		$process->run();

		// On failure, throw exception
		if ( ! empty( $process->getErrorOutput() ) )
		{
			throw new ProcessFailedException( $process );
		}

		// Extract the count
		$res = $process->getOutput();
		if ( $process->isSuccessful() && ! empty( $res ) )
		{
			return intval($res, 10);
		}

		return 0;
	}

	/**
	 * Send a signal to all headless Chrome instances that are
	 * connected to a remote debugging port. Only use this if
	 * if you are not able to gracefully close a Chrome instance
	 * that you have a PID for.
	 *
	 * Signals:
	 *   2 - quit
	 *   9 - kill (really kill, and cannot be ignored)
	 *  15 - terminate
	 *
	 * @see https://unix.stackexchange.com/questions/317492/list-of-kill-signals
	 *
	 * @param int $signal
	 * @param int $pid
	 *
	 * @return bool
	 */
	public static function killChromeProcesses( int $signal = 2, int $pid = 0 )
	{
		// Validate signal
		switch ($signal)
		{
			case 2:
			case 9:
			case 15:
				break;

			default: {
				throw new RuntimeException("Signal must be either 2, 9 or 15. Got '$signal'");
			}
		}

		if ($pid > 0) {
			// A single Chrome process
			$cmd = sprintf( LinuxCommands::killSingleProcessCmd, $signal, $pid );
		} else {
			// All Chrome processes
			$cmd = sprintf( LinuxCommands::killChromeProcessesCmd, $signal );
		}

		self::logger()->info("Exec [ $cmd ]");

		// Run the kill command
		$process = new Process( $cmd );
		$process->run();

		// On failure, throw exception
		if ( ! empty( $process->getErrorOutput() ) )
		{
			throw new ProcessFailedException( $process );
		}

		return $process->isSuccessful();
	}

	/**
	 * Quit Chrome on a given port gracefully by first asking it to quit,
	 * then forcing it to terminate if it won't quit nicely
	 *
	 * @param int $port
	 */
	public static function quitChromeInstanceGracefully( int $port )
	{
		// Verify port
		$port = intval($port, 10);
		if ($port <= 0) {
			throw new InvalidArgumentException("Port must be greater than 0. Got $port");
		}

		self::logger()->info("Checking for Chrome on port $port");

		// Quit any running Chrome processes on this port
		if ( self::isChromeRunning($port) )
		{
			// Graceful quit
			$pid = self::getPidOfChromeBoundToPort($port);
			self::killChromeProcesses(2, $pid);

			self::logger()->info("Sent graceful signal to Chrome $pid");

			// Delay
			sleep(2);

			// Check again
			if ( self::isChromeRunning($port) )
			{
				self::logger()->info("Sent forceful signal to Chrome $pid");

				// Force termination of Chrome
				self::killChromeProcesses(9, $pid);
			}
		}
	}

	/**
	 * Show the Chrome process tree(s) belonging to
	 * headless chrome instances that are bound to ports
	 */
	public static function showChromeProcessTree()
	{
		$cmd = LinuxCommands::showRunningChromeTreeCmd;

		self::logger()->info("Exec [ $cmd ]");

		$process = new Process( $cmd );
		$process->run();

		// On failure, throw exception
		if ( ! empty( $process->getErrorOutput() ) )
		{
			throw new ProcessFailedException( $process );
		}

		$res = $process->getOutput();
		if ( $process->isSuccessful() && ! empty( $res ) )
		{
			echo "<pre>Chrome process tree:".PHP_EOL."$res</pre>";
			return;
		}

		echo "<pre>Chrome not running</pre>";
	}
}
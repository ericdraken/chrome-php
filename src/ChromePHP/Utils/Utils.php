<?php
/**
 * ChromePHP - Utils.php
 * Created by: Eric Draken
 * Date: 2017/9/8
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Utils;

use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\LoggableBase;
use Draken\ChromePHP\Exceptions\ProcessFailedException;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Symfony\Component\Process\Process;

class Utils extends LoggableBase {

	/**
	 * Create a new temp folder
	 * @return string
	 */
	public static function makeUnixTmpDir()
	{
		// Verify we are not the root user
		if ( posix_getuid() == 0 ) {
			throw new RuntimeException("Will not perform mkdir as the root user.");
		}

		$cmd = LinuxCommands::makeTmpDirCmd;

		self::logger()->info("Exec [ $cmd ]");

		// Run the command
		$process = new Process( $cmd );

		try
		{
			$process->mustRun();

			// Extract the path of the temp folder
			return trim($process->getOutput());
		}
		catch (ProcessFailedException $e)
		{
			// Signal the temp file couldn't be created
			throw new ProcessFailedException( $process );
		}
	}

	/**
	 * Remove a dir as long as the user is not root.
	 * Return false on no failure, or an error message on failure
	 * @param $path
	 *
	 * @return bool|string
	 */
	public static function rmrfDir($path)
	{
		// Verify we are not the root user
		if ( posix_getuid() == 0 ) {
			throw new RuntimeException("Will not perform rm -rf as the root user.");
		}

		$realpath = realpath($path);

		// Verify the path exists
		if ( empty($realpath) || !is_dir($realpath) ) {
			throw new InvalidArgumentException("'$path' isn't a folder or doesn't exist");
		}

		// Verify the path is removable
		if ( !is_writable($realpath) ) {
			throw new InvalidArgumentException("'$path' isn't writable and thus not removable");
		}

		// Verify the path is not too short
		if ( strlen($realpath) < 5 ) {
			throw new InvalidArgumentException("'$path' is shorter than 5 characters. Unsafe to remove");
		}

		// Verify the path has at least two slashes e.g. /tmp/
		if ( substr_count($realpath, DIRECTORY_SEPARATOR) < 2 ) {
			throw new InvalidArgumentException("'$path' must be higher than root folder");
		}

		$cmd = sprintf( LinuxCommands::rmrfDirCmd, escapeshellarg($realpath) );

		self::logger()->info("Exec [ $cmd ]");

		// Run the command
		$process = new Process( $cmd );

		$process->run();
		return empty( $process->getErrorOutput() ) && $process->isSuccessful() ? false : $process->getErrorOutput();
	}

	/**
	 * Search for an array of strings
	 * @param string $haystack
	 * @param array|string $needles
	 * @param int $offset
	 *
	 * @return bool
	 *
	 * @see https://stackoverflow.com/a/9220624/1938889
	 */
	public static function strposa( $haystack, $needles, $offset = 0 )
	{
		if ( ! is_array( $needles ) )
		{
			$needles = [ $needles ];
		}
		foreach ( $needles as $query )
		{
			if ( strpos( $haystack, $query, $offset ) !== false )
			{
				return true;
			} // stop on first true result
		}

		return false;
	}
}
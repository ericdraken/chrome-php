<?php
/**
 * ChromePHP - Paths.php
 * Created by: Eric Draken
 * Date: 2017/9/18
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Utils;

use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\LoggableBase;
use Symfony\Component\Process\Process;

class Paths extends LoggableBase
{
	/**
	 * Get the node_modules path
	 * @return bool|string
	 */
	public static function getNodeModulesPath() {
		return realpath(__DIR__ . '/../../../node_modules');
	}

	/**
	 * Get the NodeJS scripts path
	 * @return string|false
	 */
	public static function getNodeScriptsPath() {
		return realpath(__DIR__ . '/../scripts');
	}

	/**
	 * Return the local Chromium binary path as downloaded by
	 * npm Puppeteer, or an empty string if it is not present
	 * @return string
	 */
	public static function getLocalChromiumBinaryPath()
	{
		// Get the local Puppeteer-supplied Chromium binary
		/** @var Process $process */
		$process = new Process(
			LinuxCommands::nodeCmd .
			' ' .
			escapeshellarg( self::getNodeScriptsPath() . '/chromiumpath.js' ) .
		    ' ' .
		    escapeshellarg( '--modulesPath='.self::getNodeModulesPath() ),
			null, null, null, 5
		);

		$process->run();

		if ( $process->isSuccessful() ) {
			$chromeBinaryPath = trim( $process->getOutput() );
			self::logger()->debug("Found Puppeteer Chromium at '{$chromeBinaryPath}'");
		} else {
			$chromeBinaryPath = '';
			self::logger()->error("Unable to find Puppeteer Chromium path. Error: " . $process->getErrorOutput() );
		}

		return $chromeBinaryPath;
	}

	/**
	 * Get the DevTools WS endpoint of a running Chrome instance
	 * by supplying its port. A connection will be made to Chrome
	 * to get the WS url, and then it will be closed.
	 *
	 * @param int $port
	 *
	 * @return bool|string
	 */
	public static function getWsEndpointOnPort( int $port )
	{
		// Get the WS endpoint of this processor for Puppeteer
		/** @var Process $process */
		$process = new Process(
			LinuxCommands::nodeCmd .
			' ' .
			escapeshellarg( self::getNodeScriptsPath() . '/endpoint.js' ) .
			' ' .
			escapeshellarg( "--port=$port" ),
			null, null, null, 5
		);

		$process->run();

		if ( $process->isSuccessful() ) {
			return trim( $process->getOutput() );
		} else {
			self::logger()->error( 'Unable to get WS endpoint. Response: ' . $process->getErrorOutput() );
			return false;
		}
	}
}
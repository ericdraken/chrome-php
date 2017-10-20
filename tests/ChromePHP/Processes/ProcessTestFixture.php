<?php
/**
 * ChromePHP - ProcessTestFixture.php
 * Created by: Eric Draken
 * Date: 2017/10/18
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Processes;

use Draken\ChromePHP\Commands\LinuxCommands;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProcessTestFixture extends TestCase
{
	protected static $defaultPort = 9222;

	protected static $testServerPort = 8888;

	protected static $server;

	/**
	 * Kill all running Chrome instances
	 */
	public static function setUpBeforeClass()
	{
		// Kill any chrome servers
		exec( sprintf( LinuxCommands::killChromeProcessesCmd, 9 ) );

		// Kill the test server or anything on that port
		// REF: https://stackoverflow.com/a/9169237/1938889
		exec( sprintf( 'fuser -k -n tcp %u 2>&1', self::$testServerPort ) );

		// Helper
		self::$server = 'http://127.0.0.1:' . self::$testServerPort;

		// Start a node server
		$server = new Process( sprintf(
			LinuxCommands::nodeCmd . ' %s %u &',
			__DIR__ . '/../server/server.js',
			self::$testServerPort
		) );
		$server->start();
	}

	public static function tearDownAfterClass()
	{
		// Kill the test server
		exec( sprintf( 'fuser -k -n tcp %u 2>&1', self::$testServerPort ) );
	}

	/**
	 * Explicitly quit Chrome even though it should
	 * happen automatically
	 */
	public function tearDown()
	{
		exec( sprintf( LinuxCommands::killChromeProcessesCmd, 2 ) );
	}

	protected function dataUri( $html = '' )
	{
		return 'data:text/html;charset=utf-8;base64,' . base64_encode( $html );
	}
}
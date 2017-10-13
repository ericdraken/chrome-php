<?php
/**
 * ChromePHP - HarProcessTest.php
 * Created by: Eric Draken
 * Date: 2017/10/12
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Processes;

use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Processes\HarProcess;
use Draken\ChromePHP\Processes\Response\HarInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class HarProcessTest extends TestCase
{
	private static $defaultPort = 9222;

	private static $testServerPort = 8888;

	private static $server;

	/**
	 * Kill all running Chrome instance.
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

	public function testHar()
	{
		$process = new HarProcess( self::$server . "/image.html" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( HarProcess $successfulProcess ) use ( &$obj )
			{
				$out = $successfulProcess->getErrorOutput();
				$obj = $successfulProcess->getHarInfo();

			}, function ( HarProcess $failedProcess ) use ( &$procFailed, &$out )
			{
				$out = $failedProcess->getErrorOutput();
				$procFailed = true;
			} );

		$manager->then( null, function () use ( &$queueFailed )
		{
			$queueFailed = true;
		} );

		// Start processing
		$manager->run();

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		$this->assertInstanceOf( HarInfo::class, $obj );

		// Test specific attributes in the HAR
		$this->assertEquals( 200, $obj->getStatus() );

		$har = $obj->getHarObj();
		$this->assertCount( 2, $har->log->entries );

	}
}

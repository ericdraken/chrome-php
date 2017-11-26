<?php
/**
 * ChromePHP - VersionJSTest.php
 * Created by: Eric Draken
 * Date: 2017/9/24
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\scripts;

use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Utils\Paths;
use PHPUnit\Framework\TestCase;

class VersionJSTest extends TestCase
{
	private static $defaultPort = 9222;

	/**
	 * Kill all running Chrome instance.
	 */
	public static function setUpBeforeClass()
	{
		exec( sprintf( LinuxCommands::killChromeProcessesCmd, 'KILL' ) );
	}

	/**
	 * Explicitly quit Chrome even though it should
	 * happen automatically
	 */
	public function tearDown()
	{
		exec( sprintf( LinuxCommands::killChromeProcessesCmd, 'INT' ) );
	}

	/**
	 * Test version.js returns the headless Chrome version
	 * e.g. HeadlessChrome/62.0.3198.0
	 */
	public function testVersionJS()
	{
		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Node process from a string
		$process = new NodeProcess( Paths::getNodeScriptsPath() . '/version.js', [], 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( NodeProcess $successfulProcess ) use ( &$out )
			{
				$out = $successfulProcess->getOutput();
			}, function ( NodeProcess $failedProcess ) use ( &$procFailed, &$out )
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

		// Tests
		$this->assertStringStartsWith( 'HeadlessChrome', $out );
		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );
	}
}
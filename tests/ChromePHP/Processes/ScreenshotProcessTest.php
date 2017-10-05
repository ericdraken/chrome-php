<?php
/**
 * ChromePHP - ScreenshotProcessTest.php
 * Created by: Eric Draken
 * Date: 2017/10/3
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes;

use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Emulations\Emulation;
use Draken\ChromePHP\Processes\Response\RenderedHTTPPageInfo;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionObject;
use Symfony\Component\Process\Process;

class ScreenshotProcessTest extends TestCase
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

	/**
	 * The emulations array is properly set
	 */
	public function testConstructorSavedEmulations()
	{
		$url = self::$server;
		$e = new Emulation(100, 200, 2.5, 'agent', true, true, true );
		$f = new Emulation(300, 400, 1.5, 'agent2', true, true, true );

		$process = new ScreenshotProcess( $url, [ $e, $f ] );

		$args = $this->getObjectAttribute( $process, 'userScriptArgs' );

		$this->assertContains( "--url=$url", $args );
		$this->assertContains( "--emulation=$e", $args );
		$this->assertContains( "--emulations=[$e,$f]", $args );
	}

	/**
	 * Only one --emulations should be present
	 */
	public function testConstructorOverwriteEmulation()
	{
		$url = self::$server;
		$e = new Emulation(100, 200, 2.5, 'agent', true, true, true );

		$process = new ScreenshotProcess( $url, [ $e ], [
			'--emulations=[]'
		] );

		$args = $this->getObjectAttribute( $process, 'userScriptArgs' );

		$this->assertCount( 4, $args );
		$this->assertContains( "--emulations=[$e]", $args );
		$this->assertNotContains( "--emulations=[]", $args );
	}

	/**
	 * Test that the vmcode can access the emulations and return a
	 * predictable response in the results object
	 */
	public function testVMCodeGetEmulations()
	{
		$url = self::$server;
		$e = new Emulation(100, 200, 2.5, 'agent', true, true, true );
		$f = new Emulation(300, 400, 1.5, 'agent2', true, true, true );

		$process = new ScreenshotProcess( $url, [ $e, $f ] );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		// Mock VM screenshot script
		$vmcode = <<<JS
			(async () => {
			    const emulations = argv.emulations;
		    	vmcodeResults.push( emulations );
			})();
JS;

		$args = $this->getObjectAttribute( $process, 'userScriptArgs' );

		// Filter out the existing vmcode param
		$args = array_filter( $args, function ( $arg ) {
			return stripos( $arg, '--vmcode=' ) !== 0;
		} );

		// Add the new vmcode
		$args[] = "--vmcode=$vmcode";

		// Override the vm code in the test object
		$refObject = new ReflectionObject( $process );
		$refProperty = $refObject->getParentClass()->getParentClass()->getProperty( 'userScriptArgs' );
		$refProperty->setAccessible( true );
		$refProperty->setValue( $process, $args );

		$argsTest = $this->getObjectAttribute( $process, 'userScriptArgs' );
		$this->assertContains( "--vmcode=$vmcode", $argsTest );

		// Use the base fitness check on the returned results object
		$refMethod = new ReflectionMethod( $process, 'setupPromiseProxyResolver');
		$refMethod->setAccessible( true );
		$refMethod->invoke( $process );

		// Chrome
		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj, &$out )
			{
				$out = $successfulProcess->getErrorOutput();
				$obj = $successfulProcess->getRenderedPageInfoObj();
			}, function ( NodeProcess $failedProcess ) use ( &$procFailed, &$out )
			{
				$out = $failedProcess->getErrorOutput();
				$procFailed = true;
			} );

		$manager->then( null, function () use ( &$queueFailed ) 	{
			$queueFailed = true;
		} );

		// Start processing
		$manager->run();

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		/** @var RenderedHTTPPageInfo $obj */
		$this->assertNotEmpty( $obj->getVmcodeResults() );
		$this->assertEquals( "[$e,$f]", $obj->getVmcodeResults()[0] );
	}

	/**
	 * Provide test data for the test below
	 * @return array
	 */
	public function scaleGenerator()
	{
		return [
			'x1.0' => [1.0, '150x150x1.png'],
			'x2.0' => [2.0, '150x150x2.png'],
			'x2.5' => [2.5, '150x150x2.5.png']
		];
	}

	/**
	 * Test that a single screenshot of scale 2.0 can be taken
	 *
	 * @dataProvider scaleGenerator
	 *
	 * @param float $scale
	 * @param string $filename
	 */
	public function testTakeScaledScreenshots( float $scale, string $filename )
	{
		$url = self::$server . '/image.jpg';
		$e = new Emulation(150, 150, $scale, 'UserAgent', false, false, false );

		$process = new ScreenshotProcess( $url, [$e] );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		// Chrome
		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj, &$out, &$exists, &$filesize )
			{
				$out = $successfulProcess->getErrorOutput();
				$obj = $successfulProcess->getRenderedPageInfoObj();

				/** @var RenderedHTTPPageInfo $obj */
				$exists = file_exists( $obj->getVmcodeResults()[0]->{'filepath'} );
				$filesize = filesize( $obj->getVmcodeResults()[0]->{'filepath'} );

			}, function ( NodeProcess $failedProcess ) use ( &$procFailed, &$out )
			{
				$out = $failedProcess->getErrorOutput();
				$procFailed = true;
			} );

		$manager->then( null, function () use ( &$queueFailed ) 	{
			$queueFailed = true;
		} );

		// Start processing
		$manager->run();

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		/** @var RenderedHTTPPageInfo $obj */
		// Check the object
		$vmRes = $obj->getVmcodeResults()[0];
		$this->assertInstanceOf( \stdClass::class, $vmRes );
		$this->assertStringEndsWith( $filename, $vmRes->{'filepath'} );
		$this->assertTrue( $exists );
		$this->assertGreaterThan( 4096, $filesize );
	}

	/**
	 * Test that a single fullpage screenshot can be taken
	 */
	public function testTakeFullpageScreenshot()
	{
		$url = self::$server . '/image.html';
		$e = new Emulation(150, 20, 1, 'UserAgent', false, false, false );

		// Specify a full page emulation is desired
		$e->setFullPage(true);

		$process = new ScreenshotProcess( $url, [$e] );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		// Chrome
		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj, &$out, &$exists, &$filesize )
			{
				$out = $successfulProcess->getErrorOutput();
				$obj = $successfulProcess->getRenderedPageInfoObj();

				/** @var RenderedHTTPPageInfo $obj */
				$exists = file_exists( $obj->getVmcodeResults()[0]->{'filepath'} );
				$filesize = filesize( $obj->getVmcodeResults()[0]->{'filepath'} );

			}, function ( NodeProcess $failedProcess ) use ( &$procFailed, &$out )
			{
				$out = $failedProcess->getErrorOutput();
				$procFailed = true;
			} );

		$manager->then( null, function () use ( &$queueFailed ) 	{
			$queueFailed = true;
		} );

		// Start processing
		$manager->run();

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		/** @var RenderedHTTPPageInfo $obj */
		// Check the object
		$vmRes = $obj->getVmcodeResults()[0];
		$this->assertInstanceOf( \stdClass::class, $vmRes );
		$this->assertContains( '150x15', $vmRes->{'filepath'} );
		$this->assertStringEndsWith( 'x1.png', $vmRes->{'filepath'} );
		$this->assertTrue( $exists );
		$this->assertGreaterThan( 4096, $filesize );
	}

	/**
	 * Test that a single fullpage screenshot can be taken
	 * past 16384 pixels with a scale factor of 1
	 */
	public function testTakeFullpageScreenshotOver16384px()
	{
		$url = self::$server . '/18000height.html';
		$e = new Emulation(1024, 768, 1, 'UserAgent', false, false, false );

		// Specify a full page emulation is desired
		$e->setFullPage(true);

		$process = new ScreenshotProcess( $url, [$e] );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		// Chrome
		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj, &$out, &$exists, &$filesize )
			{
				$out = $successfulProcess->getErrorOutput();
				$obj = $successfulProcess->getRenderedPageInfoObj();

				/** @var RenderedHTTPPageInfo $obj */
				$exists = file_exists( $obj->getVmcodeResults()[0]->{'filepath'} );
				$filesize = filesize( $obj->getVmcodeResults()[0]->{'filepath'} );

			}, function ( NodeProcess $failedProcess ) use ( &$procFailed, &$out )
			{
				$out = $failedProcess->getErrorOutput();
				$procFailed = true;
			} );

		$manager->then( null, function () use ( &$queueFailed ) 	{
			$queueFailed = true;
		} );

		// Start processing
		$manager->run();

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		/** @var RenderedHTTPPageInfo $obj */
		// Check the object
		$vmRes = $obj->getVmcodeResults()[0];
		$this->assertInstanceOf( \stdClass::class, $vmRes );
		$this->assertContains( '1024x1', $vmRes->{'filepath'} );
		$this->assertTrue( $exists );
		$this->assertGreaterThan( 4096, $filesize );
	}
}

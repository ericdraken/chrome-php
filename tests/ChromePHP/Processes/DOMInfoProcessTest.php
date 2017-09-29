<?php
/**
 * ChromePHP - DOMInfoProcessTest.php
 * Created by: Eric Draken
 * Date: 2017/9/28
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes;

use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DOMInfoProcessTest extends TestCase
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

	protected function dataUri( $html = '' )
	{
		return 'data:text/html;charset=utf-8;base64,' . base64_encode( $html );
	}

	/**
	 * Test no exceptions are thrown on basic constructor
	 */
	public function testConstructor()
	{
		new DOMInfoProcess( self::$server."/index.html" );
		$this->addToAssertionCount(1);
	}

	/**
	 * Expected exception on an empty URL
	 */
	public function testConstructorEmptyUrl()
	{
		$this->expectException( InvalidArgumentException::class );
		new DOMInfoProcess( '' );
	}

	/**
	 * The URL is properly stored
	 */
	public function testConstructorSavedUrl()
	{
		$url = 'http://example.com';

		$process = new DOMInfoProcess( $url );

		$args = $this->getObjectAttribute( $process, 'userScriptArgs' );

		$this->assertContains( "--url=$url", $args );
	}

	/**
	 * Only one URL should be present
	 */
	public function testConstructorOverwriteUrl()
	{
		$url = 'http://example.com';

		$process = new DOMInfoProcess( $url, [
			'--url=http://yahoo.com'
		] );

		$args = $this->getObjectAttribute( $process, 'userScriptArgs' );

		$this->assertCount( 1, $args );
		$this->assertContains( "--url=$url", $args );
	}

	/**
	 * Test that we get an info object back
	 */
	public function testInfoObjectReturned()
	{
		$process = new DOMInfoProcess( self::$server."/index.html" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( DOMInfoProcess $successfulProcess ) use ( &$obj )
			{
				$obj = $successfulProcess->getDomInfoObj();

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

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		$this->assertInstanceOf( DOMInfo::class, $obj );
	}

	/**
	 * Test that we get an info object back
	 */
	public function testRenderedHtml()
	{
		$process = new DOMInfoProcess( self::$server."/render.html" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( DOMInfoProcess $successfulProcess ) use ( &$obj )
			{
				$obj = $successfulProcess->getDomInfoObj();

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

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		/** @var DOMInfo $obj */
		$this->assertNotContains( 'rendered', $obj->rawHtml );
		$this->assertContains( 'rendered', $obj->renderedHtml );
	}

	/**
	 * Test that the process was not successful on a 404 error
	 */
	public function test404Request()
	{
		$process = new DOMInfoProcess( self::$server."/notextist" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( DOMInfoProcess $successfulProcess ) use ( &$obj, &$procSucceeded )
			{
				$procSucceeded = true;
			}, function ( DOMInfoProcess $failedProcess ) use ( &$obj, &$procFailed, &$out )
			{
				$obj = $failedProcess->getDomInfoObj();
				$out = $failedProcess->getErrorOutput();
				$procFailed = true;
			} );

		$manager->then( null, function () use ( &$queueFailed )
		{
			$queueFailed = true;
		} );

		// Start processing
		$manager->run();

		$procSucceeded && $this->fail( "Process should not have succeeded" );
		! $procFailed && $this->fail( "Process should have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		$this->assertInstanceOf( DOMInfo::class, $obj );

		/** @var DOMInfo $obj */
		$this->assertEquals( 404, $obj->lastResponse->status );
	}

	/**
	 * Test that the process was not successful on an image request
	 */
	public function testImageRequest()
	{
		$process = new DOMInfoProcess( self::$server."/image.jpg" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( DOMInfoProcess $successfulProcess ) use ( &$obj )
			{
				$obj = $successfulProcess->getDomInfoObj();

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

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		$this->assertInstanceOf( DOMInfo::class, $obj );

		/** @var DOMInfo $obj */
		$this->assertEquals( 200, $obj->lastResponse->status );
		$this->assertTrue( $obj->ok );
		$this->assertEmpty( $obj->rawHtml );
		$this->assertEmpty( $obj->renderedHtml );
		$this->assertEquals( 'image/jpeg', $obj->lastResponse->responseHeaders->{'content-type'} );
	}

	/**
	 * Test that we get all the console messages
	 */
	public function testConsoleLogs()
	{
		$process = new DOMInfoProcess( self::$server."/console.html" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( DOMInfoProcess $successfulProcess ) use ( &$obj )
			{
				$obj = $successfulProcess->getDomInfoObj();

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

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		/** @var DOMInfo $obj */
		// Console logs check
		$this->assertStringStartsWith( 'DEBUG:', $obj->consoleLogs[0] );
		$this->assertStringStartsWith( 'INFO:', $obj->consoleLogs[1] );
		$this->assertStringStartsWith( 'WARN:', $obj->consoleLogs[2] );
		$this->assertStringStartsWith( 'ERROR:', $obj->consoleLogs[3] );
		$this->assertStringStartsWith( 'LOG:', $obj->consoleLogs[4] );
	}

	/**
	 * Test that a redirect chain is followed and recorded
	 */
	public function testRedirectChain()
	{
		$process = new DOMInfoProcess( self::$server."/307/" );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( DOMInfoProcess $successfulProcess ) use ( &$obj )
			{
				$obj = $successfulProcess->getDomInfoObj();

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

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		/** @var DOMInfo $obj */
		$this->assertCount( 3, $obj->redirectChain );
		$this->assertEquals( 200, $obj->lastResponse->status );
		$this->assertCount( 0, $obj->failed );
		$this->assertCount( 1, $obj->requests );
	}
}

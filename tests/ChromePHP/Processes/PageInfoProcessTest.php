<?php
/**
 * ChromePHP - PageInfoProcessTest.php
 * Created by: Eric Draken
 * Date: 2017/9/28
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes;

use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Processes\HTTP\RenderedHTTPPageInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PageInfoProcessTest extends TestCase
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
		new PageInfoProcess( self::$server . "/index.html" );
		$this->addToAssertionCount(1);
	}

	/**
	 * Expected exception on an empty URL
	 */
	public function testConstructorEmptyUrl()
	{
		$this->expectException( InvalidArgumentException::class );
		new PageInfoProcess( '' );
	}

	/**
	 * The URL is properly stored
	 */
	public function testConstructorSavedUrl()
	{
		$url = 'http://example.com';

		$process = new PageInfoProcess( $url );

		$args = $this->getObjectAttribute( $process, 'userScriptArgs' );

		$this->assertContains( "--url=$url", $args );
	}

	/**
	 * Only one URL should be present
	 */
	public function testConstructorOverwriteUrl()
	{
		$url = 'http://example.com';

		$process = new PageInfoProcess( $url, [
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
		$process = new PageInfoProcess( self::$server . "/index.html" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj )
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

		$this->assertInstanceOf( RenderedHTTPPageInfo::class, $obj );
	}

	/**
	 * Test that we get an info object back
	 */
	public function testgetRenderedHtml()
	{
		$process = new PageInfoProcess( self::$server . "/render.html" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj )
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

		/** @var RenderedHTTPPageInfo $obj */
		$this->assertNotContains( 'rendered', $obj->getRawHtml() );
		$this->assertContains( 'rendered', $obj->getRenderedHtml() );
	}

	/**
	 * Test that the process was not successful on a 404 error
	 */
	public function test404Request()
	{
		$process = new PageInfoProcess( self::$server . "/notextist" );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj, &$procSucceeded )
			{
				$procSucceeded = true;
			}, function ( PageInfoProcess $failedProcess ) use ( &$obj, &$procFailed, &$out )
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

		$this->assertInstanceOf( RenderedHTTPPageInfo::class, $obj );

		/** @var RenderedHTTPPageInfo $obj */
		$this->assertEquals( 404, $obj->getLastResponse()->getStatus() );
	}

	/**
	 * Test that the process was not successful on a bad domain
	 */
	public function testBadDomainRequest()
	{
		$process = new PageInfoProcess( 'http://notgoingtowork.edu' );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj, &$procSucceeded )
			{
				$procSucceeded = true;
			}, function ( PageInfoProcess $failedProcess ) use ( &$obj, &$procFailed, &$out )
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

		$this->assertInstanceOf( RenderedHTTPPageInfo::class, $obj );

		/** @var RenderedHTTPPageInfo $obj */
		$this->assertEquals( 0, $obj->getStatus() );
		$this->assertNotEmpty( $obj->getLastResponse() );
		$this->assertEquals( 0, $obj->getLastResponse()->getStatus() );
	}

	/**
	 * Test that the process was not successful on an image request
	 */
	public function testImageRequest()
	{
		$process = new PageInfoProcess( self::$server . "/image.jpg" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj )
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

		$this->assertInstanceOf( RenderedHTTPPageInfo::class, $obj );

		/** @var RenderedHTTPPageInfo $obj */
		$this->assertEquals( 200, $obj->getLastResponse()->getStatus() );
		$this->assertTrue( $obj->isOk() );
		$this->assertEmpty( $obj->getRawHtml() );
		$this->assertEmpty( $obj->getRenderedHtml() );
		$this->assertEquals( 'image/jpeg', $obj->getLastResponse()->getResponseHeaders()->{'content-type'} );
	}

	/**
	 * Test that we get all the console messages
	 */
	public function testConsoleLogs()
	{
		$process = new PageInfoProcess( self::$server . "/console.html" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj )
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

		/** @var RenderedHTTPPageInfo $obj */
		// Console logs check
		$this->assertStringStartsWith( 'DEBUG:', $obj->getConsoleLogs()[0] );
		$this->assertStringStartsWith( 'INFO:', $obj->getConsoleLogs()[1] );
		$this->assertStringStartsWith( 'WARN:', $obj->getConsoleLogs()[2] );
		$this->assertStringStartsWith( 'ERROR:', $obj->getConsoleLogs()[3] );
		$this->assertStringStartsWith( 'LOG:', $obj->getConsoleLogs()[4] );
	}

	/**
	 * Test that we get all the console messages
	 */
	public function testScriptErrors()
	{
		$process = new PageInfoProcess( self::$server . "/errors.html" );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj )
			{
				$out = $successfulProcess->getErrorOutput();
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

		/** @var RenderedHTTPPageInfo $obj */
		$this->assertCount( 2, $obj->getErrors() );
		$this->assertContains( 'FunctionNotFound', $obj->getErrors()[0] );
		$this->assertContains( 'MethodNotFound', $obj->getErrors()[1] );
	}

	/**
	 * Test that a meta redirect is followed and recorded
	 */
	public function testMetaRedirect()
	{
		$process = new PageInfoProcess( self::$server . "/meta-redirect-1.html" );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj )
			{
				$out = $successfulProcess->getErrorOutput();
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

		/** @var RenderedHTTPPageInfo $obj */
		$this->assertCount( 1, $obj->getRedirectChain() );
		$this->assertEquals( 200, $obj->getLastResponse()->getStatus() );
		$this->assertCount( 0, $obj->getFailed() );
		$this->assertCount( 2, $obj->getRequests() );
		$this->assertNotEquals( $obj->getRequestUrl(), $obj->getLastResponse()->getUrl() );
	}

	/**
	 * Test that a JS redirect is followed and recorded
	 */
	public function testJSRedirect()
	{
		$process = new PageInfoProcess( self::$server . "/javascript-redirect-1.html" );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj )
			{
				$out = $successfulProcess->getErrorOutput();
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

		/** @var RenderedHTTPPageInfo $obj */
		$this->assertCount( 1, $obj->getRedirectChain() );
		$this->assertEquals( 200, $obj->getLastResponse()->getStatus() );
		$this->assertCount( 0, $obj->getFailed() );
		$this->assertCount( 2, $obj->getRequests() );
		$this->assertNotEquals( $obj->getRequestUrl(), $obj->getLastResponse()->getUrl() );
	}

	/**
	 * Test that a redirect chain is followed and recorded
	 */
	public function testMixedRedirectChain()
	{
		$process = new PageInfoProcess( self::$server . "/302-2/" );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj )
			{
				$out = $successfulProcess->getErrorOutput();
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

		/** @var RenderedHTTPPageInfo $obj */
		$this->assertCount( 5, $obj->getRedirectChain() );
		$this->assertEquals( 200, $obj->getLastResponse()->getStatus() );
		$this->assertCount( 0, $obj->getFailed() );
		$this->assertCount( 2, $obj->getRequests() );
	}
}

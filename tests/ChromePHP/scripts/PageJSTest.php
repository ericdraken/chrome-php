<?php
/**
 * ChromePHP - PageJSTest.phpp
 * Created by: Eric Draken
 * Date: 2017/9/27
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\scripts;

use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Utils\Paths;
use PHPUnit\Framework\TestCase;

class PageJSTest extends TestCase
{
	private static $defaultPort = 9222;

	/**
	 * Kill all running Chrome instance.
	 */
	public static function setUpBeforeClass()
	{
		exec( sprintf( LinuxCommands::killChromeProcessesCmd, 9 ) );
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
	 * Test that a JSON string can be saved, retrieved and inflated
	 */
	public function testDomJS()
	{
		$testUrl = $this->dataUri( '<!DOCTYPE html><html><head><title></title></head><body></body></html>' );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Node process from a string
		$process = new NodeProcess( Paths::getNodeScriptsPath() . '/page.js', [
			'--url='.$testUrl
		], 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( NodeProcess $successfulProcess ) use ( &$out, &$contents )
			{
				$out = $successfulProcess->getOutput();

				$contents = @file_get_contents( trim( $out ) );

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

		// Filename
		$jsonFile = trim( $out );
		$this->assertStringEndsWith( '.json', $jsonFile );

		// Contents
		$this->assertNotEmpty( $contents );

		// JSON check
		$obj = json_decode( $contents );
		$this->assertNotNull( $obj );
	}

	/**
	 * Verify the expected info object contents
	 */
	public function testDomJSInfo()
	{
		$testBody = '<!DOCTYPE html><html><head><title>title</title></head><body><script>setTimeout(function(){document.body.innerText = \'javascript\'},10)</script></body></html>';

		$renderedBody = '<!DOCTYPE html><html><head><title>title</title></head><body>javascript</body></html>';

		$testUrl = $this->dataUri( $testBody );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Node process from a string
		$process = new NodeProcess( Paths::getNodeScriptsPath() . '/page.js', [
			'--url='.$testUrl
		], 1, false, false );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'error'
		]);

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( NodeProcess $successfulProcess ) use ( &$out, &$contents )
			{
				$out = $successfulProcess->getOutput();

				$contents = @file_get_contents( trim( $out ) );

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

		// Inflate
		$obj = json_decode( $contents );

		// URL check
		$this->assertEquals( $testUrl, $obj->requestUrl );

		// Raw HTML check
		$this->assertEquals( $testBody, $obj->rawHtml );

		// Rendered HTML check
		$this->assertEquals( $renderedBody, $obj->renderedHtml );
	}

	/**
	 * Verify the expected info object console logs
	 */
	public function testDomJSInfoConsoleLogs()
	{
		$testBody = <<<'HTML'
<!DOCTYPE html><html><head></head><body><script>console.debug('debug');console.info('info');console.warn('warn');console.error('error');console.log('log');</script></body></html>
HTML;

		$testUrl = $this->dataUri( $testBody );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Node process from a string
		$process = new NodeProcess( Paths::getNodeScriptsPath() . '/page.js', [
			'--url='.$testUrl
		], 1, false, false );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( NodeProcess $successfulProcess ) use ( &$out, &$contents )
			{
				$out = $successfulProcess->getOutput();
				$contents = @file_get_contents( trim( $out ) );

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

		// Inflate
		$obj = json_decode( $contents );
		$this->assertNotNull( $obj );

		// Console logs check
		$this->assertStringStartsWith( 'DEBUG:', $obj->consoleLogs[0] );
		$this->assertStringStartsWith( 'INFO:', $obj->consoleLogs[1] );
		$this->assertStringStartsWith( 'WARN:', $obj->consoleLogs[2] );
		$this->assertStringStartsWith( 'ERROR:', $obj->consoleLogs[3] );
		$this->assertStringStartsWith( 'LOG:', $obj->consoleLogs[4] );
	}

	/**
	 * Verify a not-found error was detected
	 */
	public function testDomJSInfoConsoleLogsNotFoundError()
	{
		$testBody = <<<'HTML'
<!DOCTYPE html><html><head></head><body><img src="http://127.0.0.1/notexists" /></body></html>
HTML;

		$testUrl = $this->dataUri( $testBody );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Node process from a string
		$process = new NodeProcess( Paths::getNodeScriptsPath() . '/page.js', [
			'--url='.$testUrl
		], 1, false, false );

		// Enable debugging
		$process->setEnv([
			'LOG_LEVEL' => 'debug'
		]);

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( NodeProcess $successfulProcess ) use ( &$out, &$contents )
			{
				$out = $successfulProcess->getOutput();
				$contents = @file_get_contents( trim( $out ) );

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

		// Inflate
		$obj = json_decode( $contents );
		$this->assertNotNull( $obj );

		// Requests
		$this->assertCount( 2, $obj->requests);

		// Failures
		$this->assertNotEmpty( $obj->failed );
		$this->assertEquals( 404, $obj->failed[0]->status );
		$this->assertEquals( 'GET', $obj->failed[0]->method );
	}
}
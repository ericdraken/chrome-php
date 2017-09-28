<?php
/**
 * ChromePHP - ChromeProcessTest.php
 * Created by: Eric Draken
 * Date: 2017/9/22
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Core;

use Draken\ChromePHP\Commands\ChromeCommands;
use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\ChromeProcess;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Utils\Paths;
use PHPUnit\Framework\TestCase;

class ChromeProcessTest extends TestCase
{
	/** @var int */
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

	/**
	 * Supply a bad chrome binary path
	 */
	public function testBadConstruct()
	{
		$this->expectException( InvalidArgumentException::class );
		new ChromeProcess( 'notexists' );
	}

	/**
	 * Supply no chrome binary path
	 */
	public function testConstruct()
	{
		$proc = new ChromeProcess();

		$this->assertFalse( $proc->isChromeRunning() );

		$this->assertNull( $proc->getTimeout() );
	}

	/**
	 * Test setting the user agent string
	 */
	public function testSetUA()
	{
		$proc = new ChromeProcess();

		$proc->setDefaultUserAgent( 'test' );

		$this->assertAttributeEquals( 'test', 'userAgent', $proc );
	}

	// TODO: Test UA is really used

	/**
	 * Test the Chrome cmd is assembled correctly
	 */
	public function testBuildChromeCmd()
	{
		// Access private method
		$reflection = new \ReflectionClass( ChromeProcess::class );
		$method = $reflection->getMethod( 'buildHeadlessChromeCmd' );
		$method->setAccessible(true);

		$proc = new ChromeProcess();

		// private function buildHeadlessChromeCmd($port, $useragent, $tmpdir, $binpath = ''): string
		$res = $method->invokeArgs($proc, [
			1234,
			'Bob',
			'docs',
			'bindir'
		]);

		// Port
		$this->assertContains( '--remote-debugging-port=\'1234\'', $res );

		// UA
		$this->assertContains( '--user-agent=\'Bob\'', $res );

		// User dir
		$this->assertContains( '--user-data-dir=\'docs\'', $res );

		// Binary
		$this->assertStringStartsWith( '\'bindir\'', $res );
	}

	/**
	 * Chrome should launch using the internal Chrome binary
	 * @depends testBuildChromeCmd
	 */
	public function testCanStartSuppliedChrome()
	{
		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( self::$defaultPort );

		$this->assertTrue( $chromeInstance->isChromeRunning() );
		$this->assertTrue( ChromeCommands::isChromeRunning( self::$defaultPort ) );
	}

	/**
	 * Chrome should launch using the internal Chrome binary
	 * @depends testBuildChromeCmd
	 */
	public function testCanStartSuppliedChrome2()
	{
		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess();
		$chromeInstance->setChromeBinaryPath( $localChromePath );
		$chromeInstance->launchHeadlessChrome( self::$defaultPort );

		$this->assertTrue( $chromeInstance->isChromeRunning() );
		$this->assertTrue( ChromeCommands::isChromeRunning( self::$defaultPort ) );
	}

	/**
	 * Chrome should launch using the "system" Chrome binary
	 * @depends testCanStartSuppliedChrome
	 */
	public function testCanStartSystemChrome()
	{
		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess();

		$env = [
			"PATH" => dirname( realpath( $localChromePath ) ) . ':' . getenv( 'PATH' )
		];
		$chromeInstance->setEnvVars( $env );

		$chromeInstance->launchHeadlessChrome( self::$defaultPort );

		$this->assertTrue( $chromeInstance->isChromeRunning() );
		$this->assertTrue( ChromeCommands::isChromeRunning( self::$defaultPort ) );
	}

	/**
	 * Test setting a non-existent Chrome binary
	 */
	public function testSupplyBadChromeBinary()
	{
		$chromeInstance = new ChromeProcess();

		$this->expectException( InvalidArgumentException::class );
		$chromeInstance->setChromeBinaryPath( 'notexists' );
	}

	/**
	 * Test setting a non-existent Chrome binary
	 */
	public function testSupplyBadChromeBinary2()
	{
		$this->expectException( InvalidArgumentException::class );
		new ChromeProcess( 'notexists' );
	}
}

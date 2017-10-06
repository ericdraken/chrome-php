<?php
/**
 * ChromePHP - ChromeCommandsTest.php
 * Created by: Eric Draken
 * Date: 2017/9/20
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Commands;

use Draken\ChromePHP\Commands\ChromeCommands;
use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\ChromeProcess;
use Draken\ChromePHP\Utils\Paths;
use PHPUnit\Framework\TestCase;

class ChromeCommandsTest extends TestCase
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
	 * There should be no process bound to this port
	 */
	public function testCannotGetPidOfChromeBoundToPort()
	{
		$this->assertFalse( ChromeCommands::getPidOfChromeBoundToPort( self::$defaultPort ) );
	}

	/**
	 * No processes should be running
	 * @depends testCannotGetPidOfChromeBoundToPort
	 */
	public function testGetZeroChromeProcessesRunning()
	{
		$this->assertEquals( 0, ChromeCommands::getNumChromeProcessesRunning() );
	}

	/**
	 * This port should be available normally
	 * @depends testGetZeroChromeProcessesRunning
	 */
	public function testPortIsNotBound()
	{
		$this->assertFalse( ChromeCommands::isPortBound( self::$defaultPort ) );
	}

	/**
	 * Test that we can find the supplied path
	 */
	public function testFindSuppliedChromeBinPath()
	{
		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$path = ChromeCommands::findChromeBinPath( [], $localChromePath );
		$this->assertEquals( $localChromePath, $path );
	}

	/**
	 * Test that we can find the PATH-set path
	 * @depends testFindSuppliedChromeBinPath
	 */
	public function testFindEnvPathChromeBinPath()
	{
		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$env =[
			"PATH" => dirname(realpath($localChromePath)) . ':' . getenv('PATH')
		];

		$path = ChromeCommands::findChromeBinPath( $env );
		$this->assertEquals( $localChromePath, $path );
	}

	/**
	 * Get the supplied local Chrome version
	 */
	public function testGetSuppliedChromeVersion()
	{
		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$ver = ChromeCommands::getInstalledChromeVersion( [], $localChromePath );

		$this->assertNotEmpty( $ver );
		$this->assertStringStartsWith( 'Chrom', $ver, "A correct Chrome version starts with a 'Chrom'. Got: $ver" );
	}

	/**
	 * Get the system Chrome version
	 */
	public function testGetInstalledChromeVersion()
	{
		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$env =[
			"PATH" => dirname(realpath($localChromePath)) . ':' . getenv('PATH')
		];

		$ver = ChromeCommands::getInstalledChromeVersion( $env );

		$this->assertNotEmpty( $ver );
		$this->assertStringStartsWith( 'Chrom', $ver, "A correct Chrome version starts with a 'Chrom'. Got: $ver" );
	}

	/**
	 * @depends testGetInstalledChromeVersion
	 */
	public function testCanStartSuppliedChrome()
	{
		$port = self::$defaultPort;

		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( $port );
		$this->assertTrue( $chromeInstance->isChromeRunning() );
	}

	/**
	 * @depends testCanStartSuppliedChrome
	 */
	public function testIsChromeRunning()
	{
		$port = self::$defaultPort + 1;

		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( $port );

		$this->assertTrue( ChromeCommands::isChromeRunning( $port ) );
	}

	/**
	 * @depends testCanStartSuppliedChrome
	 */
	public function testIsPortBound()
	{
		$port = self::$defaultPort + 2;

		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( $port );

		$this->assertTrue( ChromeCommands::isPortBound( $port ) );
	}

	/**
	 * @depends testCanStartSuppliedChrome
	 */
	public function testGetPidOfChromeBoundToPort()
	{
		$port = self::$defaultPort + 3;

		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( $port );

		$this->assertGreaterThan( 0, ChromeCommands::getPidOfChromeBoundToPort( $port ) );
	}

	/**
	 * @depends testCanStartSuppliedChrome
	 */
	public function testGetNumChromeProcessesRunning()
	{
		$port = self::$defaultPort + 4;

		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( $port );

		$this->assertEquals( 1, ChromeCommands::getNumChromeProcessesRunning() );
	}

	/**
	 * @depends testCanStartSuppliedChrome
	 */
	public function testShowChromeProcessTree()
	{
		$port = self::$defaultPort + 5;

		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( $port );

		ob_start();
		ChromeCommands::showChromeProcessTree();
		$out = ob_get_contents();
		ob_end_clean();

		$this->assertContains( ' chrome', $out );
	}

	/**
	 * @depends testCanStartSuppliedChrome
	 */
	public function killChromeProcessesByPID()
	{
		$port = self::$defaultPort + 6;

		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( $port );

		$this->assertTrue( $chromeInstance->isChromeRunning() );

		$pid = ChromeCommands::getPidOfChromeBoundToPort( $port );
		$this->assertGreaterThan( 0, $pid );

		ChromeCommands::killChromeProcesses( 2, $pid );

		// Short delay for the close
		sleep(1);

		$this->assertFalse( $chromeInstance->isChromeRunning() );
		$this->assertFalse( ChromeCommands::isPortBound( $port ) );
		$this->assertEquals( 0, ChromeCommands::getPidOfChromeBoundToPort( $port ) );

		// There should now only be 1 Chrome running
		$this->assertEquals( 1, ChromeCommands::getNumChromeProcessesRunning() );
	}

	/**
	 * @depends testCanStartSuppliedChrome
	 */
	public function testQuitChromeInstanceGracefully()
	{
		$port = self::$defaultPort + 10;

		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( $port );

		$this->assertTrue( $chromeInstance->isChromeRunning() );

		$pid = ChromeCommands::getPidOfChromeBoundToPort( $port );
		$this->assertGreaterThan( 0, $pid );

		ChromeCommands::quitChromeInstanceGracefully( $port );

		$this->assertFalse( $chromeInstance->isChromeRunning() );
		$this->assertFalse( ChromeCommands::isPortBound( $port ) );
		$this->assertEquals( 0, ChromeCommands::getPidOfChromeBoundToPort( $port ) );

		// There should only be 0 Chromes running
		$this->assertEquals( 0, ChromeCommands::getNumChromeProcessesRunning() );
	}
}

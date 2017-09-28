<?php
/**
 * ChromePHP - PathsTest.php
 * Created by: Eric Draken
 * Date: 2017/9/20
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Utils;

use Draken\ChromePHP\Commands\ChromeCommands;
use Draken\ChromePHP\Core\ChromeProcess;
use Draken\ChromePHP\Utils\Paths;
use PHPUnit\Framework\TestCase;

class PathsTest extends TestCase
{
	/**
	 * Kill all running Chrome instance
	 */
	public static function setUpBeforeClass()
	{
		ChromeCommands::killChromeProcesses(2 );
	}

	/**
	 * Explicitly quit Chrome even though it should
	 * happen automatically
	 */
	public static function tearDownAfterClass()
	{
		ChromeCommands::killChromeProcesses(2);
	}

	/**
	 * Test the modules path exists
	 */
	public function testGetNodeModulesPath()
	{
		$path = Paths::getNodeModulesPath();
		$this->assertDirectoryExists( $path );

		// Contains Puppeteer
		$this->assertDirectoryExists( $path . DIRECTORY_SEPARATOR . 'puppeteer' );
	}

	/**
	 * Test the package scripts path exists
	 */
	public function testGetNodeScriptsPath()
	{
		$path = Paths::getNodeScriptsPath();
		$this->assertDirectoryExists( $path );

		// Contains chromiumpath.js
		$this->assertFileExists( $path . DIRECTORY_SEPARATOR . 'chromiumpath.js' );
	}

	/**
	 * @depends testGetNodeModulesPath
	 * @depends testGetNodeScriptsPath
	 */
	public function testGetLocalChromiumBinaryPath()
	{
		$path = Paths::getLocalChromiumBinaryPath();
		$this->assertFileExists( $path );
		$this->assertFileIsReadable( $path );
	}

	/**
	 * @depends testGetLocalChromiumBinaryPath
	 */
	public function testGetWsEndpointOnPort()
	{
		$port = 9222;

		$localChromePath = Paths::getLocalChromiumBinaryPath();
		$this->assertNotEmpty( $localChromePath );

		$chromeInstance = new ChromeProcess( $localChromePath );
		$chromeInstance->launchHeadlessChrome( $port );

		$this->assertTrue( $chromeInstance->isChromeRunning() );

		$ws = Paths::getWsEndpointOnPort( $port );
		$this->assertNotEmpty( $ws );
		$this->assertNotFalse( $ws );
		$this->assertStringStartsWith( 'ws:', $ws );

		$chromeInstance->quitAndCleanup( false );
	}

	/**
	 * No endpoint should be found. The method should return false
	 */
	public function testNoWsEndpointOnPort9223()
	{
		$ws = Paths::getWsEndpointOnPort( 9223 );
		$this->assertFalse( $ws );
	}

	/**
	 * No endpoint should be found. The method should return false
	 */
	public function testNoWsEndpointOnPort80()
	{
		$ws = Paths::getWsEndpointOnPort( 80 );
		$this->assertFalse( $ws );
	}
}

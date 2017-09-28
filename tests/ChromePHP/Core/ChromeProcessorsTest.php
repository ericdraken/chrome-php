<?php
/**
 * ChromePHP - ChromeProcessorsTest.php
 * Created by: Eric Draken
 * Date: 2017/9/22
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Core;

use Draken\ChromePHP\Commands\ChromeCommands;
use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Core\ChromeProcessors;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Utils\Paths;
use PHPUnit\Framework\TestCase;

class ChromeProcessorsTest extends TestCase
{
	/**
	 * Close all Chrome processes before running a test
	 */
	protected function setUp()
	{
		exec( sprintf( LinuxCommands::killChromeProcessesCmd, 9 ) );
	}

	/**
	 * Close all Chrome processes after running a test
	 */
	protected function tearDown()
	{
		exec( sprintf( LinuxCommands::killChromeProcessesCmd, 2 ) );
	}

	/**
	 * Baseline test
	 */
	public function testConstructor()
	{
		$processors = new ChromeProcessors( 9222 );

		$this->assertAttributeEquals( 9222, 'nextPort', $processors );
		$this->assertAttributeEquals( 0, 'numActiveChromeProcessors', $processors );
		$this->assertAttributeEquals( '', 'chromeBinaryPath', $processors );
		$this->assertEquals( 0, $processors->numActiveChromeProcessors() );
		$this->assertTrue( $processors->isPortViable( 9222 ) );
	}

	/**
	 * Port must be greater than zero
	 */
	public function testZeroPort()
	{
		$this->expectException( InvalidArgumentException::class );
		new ChromeProcessors(0);
	}

	/**
	 * We must be able to spin up a Chrome process using the internal Chromium binary
	 */
	public function testSpinUpProcessor()
	{
		$port = 9222;

		$binary = Paths::getLocalChromiumBinaryPath();

		$processors = new ChromeProcessors( $port, $binary );

		// Ensure there is no processor running on this port
		ChromeCommands::quitChromeInstanceGracefully( $port );

		$processors->spinUpNewChromeProcessor();

		$this->assertAttributeEquals( 1, 'numActiveChromeProcessors', $processors );

		$processorsArray = $this->getObjectAttribute( $processors, 'activeChromeProcessors' );
		$this->assertCount( 1, $processorsArray );

		$this->assertTrue( ChromeCommands::isChromeRunning( $port ) );

		$this->assertEquals( 1, ChromeCommands::getNumChromeProcessesRunning() );

		$this->assertTrue( $processors->hasFreeChromeProcessor() );
	}
}

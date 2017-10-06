<?php
/**
 * ChromePHP - NodeProcessTest.php
 * Created by: Eric Draken
 * Date: 2017/9/21
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Core;

use Draken\ChromePHP\Commands\NodeCommands;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class NodeProcessTest extends TestCase
{
	/**
	 * Ports must be greater than zero
	 */
	public function testInvalidPort()
	{
		$process = new NodeProcess();

		$this->expectException( InvalidArgumentException::class );
		$process->setAssignedPort(0);
	}

	/**
	 * Ports must be greater than zero
	 */
	public function testValidPort()
	{
		$process = new NodeProcess();

		$process->setAssignedPort( 1234 );

		$this->assertAttributeEquals( 1234, 'assignedPort', $process );
	}

	/**
	 * Valid endpoints start with 'ws:'
	 */
	public function testInvalidEndpoint()
	{
		$process = new NodeProcess();

		$this->expectException( InvalidArgumentException::class );
		$process->setAssignedWsEndpointUrl( '' );
	}

	/**
	 * Valid endpoints start with 'ws:'
	 */
	public function testInvalidEndpoint2()
	{
		$process = new NodeProcess();

		$this->expectException( InvalidArgumentException::class );
		$process->setAssignedWsEndpointUrl( 'notreal' );
	}

	public function testSetInitialDelay()
	{
		$process = new NodeProcess();
		$process->setInitialDelay( 10 );

		$this->assertAttributeEquals( 10, 'initialDelay', $process );
	}

	/**
	 * Test running a NodeProcess with no script or string
	 */
	public function testRunEmpty()
	{
		$process = new NodeProcess();

		$this->expectException( RuntimeException::class );
		$process->run();
	}

	/**
	 * Test running a NodeProcess with no script or string
	 */
	public function testRunEmptyString()
	{
		$process = new NodeProcess('');

		$this->expectException( RuntimeException::class );
		$process->run();
	}

	/**
	 * Test running a NodeProcess with a script that doesn't exist
	 */
	public function testNotExistsScript()
	{
		$this->expectException( InvalidArgumentException::class );
		new NodeProcess('doesnotexists.js');
	}

	/**
	 * Test two objects have the same promise after clone
	 */
	public function testSamePromiseAfterClone()
	{
		$pp1 = new NodeProcess( '' );
		$pp2 = $pp1->cloneCleanProcess();

		$this->assertEquals( $pp1->getPromise(), $pp2->getPromise() );
	}

	/**
	 * Test two objects have the same timeout after clone
	 */
	public function testSameTimeoutAfterClone()
	{
		$pp1 = new NodeProcess( '', [], 100, false );
		$pp2 = $pp1->cloneCleanProcess();

		$this->assertEquals( $pp1->getTimeout(), $pp2->getTimeout() );
	}

	/**
	 * Test two objects have different unique ids after clone
	 */
	public function testUniqueIdsAfterClone()
	{
		$pp1 = new NodeProcess();
		$pp2 = $pp1->cloneCleanProcess();

		$this->assertNotEquals( $pp1->getUniqueId(), $pp2->getUniqueId() );
	}

	/**
	 * Test the last exception is null after a clone
	 */
	public function testNoLastExceptionAfterClone()
	{
		$pp1 = new NodeProcess('');
		$pp1->setLastException( new \Exception("test") );

		$pp2 = $pp1->cloneCleanProcess();

		$this->assertEmpty( $pp2->getLastException() );
		$this->assertNotEmpty( $pp1->getLastException() );
		$this->assertNotEquals( $pp1->getLastException(), $pp2->getLastException() );
	}

	/**
	 * Ensure Node is installed
	 */
	public function testNodeInstalled()
	{
		$this->assertNotFalse( NodeCommands::getInstalledNodeVersion() );
	}

	/**
	 * Test running a NodeProcess with a simple script
	 * without setting any Chrome connection details
	 * @depends testNodeInstalled
	 */
	public function testRunSimpleScriptNoChromeConnectionInfo()
	{
		$process = new NodeProcess('console.log("test")');

		$this->expectException( RuntimeException::class );
		$process->run();
	}

	/**
	 * Test running a NodeProcess with a simple script
	 * and with fake Chrome connections details
	 * @depends testNodeInstalled
	 */
	public function testRunSimpleScriptFile()
	{
		// Write a script to disk
		$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . '.js';
		$code = 'console.log("test")';
		file_put_contents( $tmp, $code );

		try
		{
			$process = new NodeProcess( $tmp );

			$process->setAssignedPort( 1234 );
			$process->run();

			$this->assertContains( 'test', $process->getOutput() );
		}
		finally
		{
			unlink( $tmp );
		}
	}

	/**
	 * Test running a NodeProcess with a simple script
	 * and with fake Chrome connections details
	 * @depends testRunSimpleScriptFile
	 * @depends testNodeInstalled
	 */
	public function testRunSimpleScript()
	{
		$process = new NodeProcess( 'console.log("test")' );

		$process->setAssignedPort( 1234 );
		$process->run();

		$this->assertContains( 'test', $process->getOutput() );

		// Check the temp file was deleted
		$tempScriptPath = $this->getObjectAttribute( $process, 'tempScriptPath' );

		$this->assertNotEmpty( $tempScriptPath );

		// Remove the temp file
		$process->cleanup();

		$this->assertFileNotExists( $tempScriptPath );
	}

	/**
	 * Test that the command is built properly
	 */
	public function testBuildCommand()
	{
		// Write a script to disk
		$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . '.js';
		$code = 'console.log("test")';
		file_put_contents( $tmp, $code );

		try
		{
			$process = new NodeProcess( $tmp, ['--arg=test'], 10, true );

			$process->setAssignedPort( 1234 );

			$process->setAssignedWsEndpointUrl( 'ws://test' );

			$process->setInitialDelay( 5.01 );

			$work = sys_get_temp_dir();
			$process->setAssignedWorkingFolder( $work );


			// This will build the command
			$process->start();


			$cmd = $process->getCommandLine();
			$this->assertNotEmpty( $cmd );

			// Port
			$this->assertContains( '--chrome-port=1234', $cmd );

			// Endpoint
			$this->assertContains( '--chrome-wsep=ws://test', $cmd );

			// Delay
			$this->assertStringStartsWith( 'sleep 5.01;', $cmd );

			// Inspector
			$this->assertContains( '--inspect-brk=', $cmd );

			// User arguments
			$this->assertContains( '--arg=test', $cmd );

			// Working folder
			$this->assertContains( '--chrome-temp='.$work, $cmd );

			// Script
			$this->assertContains( $tmp, $cmd );
		}
		finally
		{
			// The process must be killed because inspector mode waits a long time
			$process->stop(0, 9);

			unlink( $tmp );
		}
	}

	/**
	 * Test that a timeout set in the child class is
	 * reflected in the parent base class
	 */
	public function testTimeoutSetProperly()
	{
		$timeout = 0.1;

		$process = new NodeProcess( 'console.log(1)', [], $timeout, false );
		$process->setAssignedPort(1234);    // Dummy port

		$this->assertFalse( $this->getObjectAttribute( $process, 'inspectMode' ) );

		$process->start();

		// Check that the timeout is accurate
		$this->assertEquals( $timeout, $this->getObjectAttribute( $process, 'timeout' ) );
	}

	/**
	 * Test that a timeout set in the child class is
	 * reflected in the parent base class
	 */
	public function testInspectModeTimeoutSetProperly()
	{
		$timeout = 10;  // This should be overridden by inspect mode

		$process = new NodeProcess( 'console.log(1)', [], $timeout, true );
		$process->setAssignedPort(1234);    // Dummy port

		$this->assertTrue( $this->getObjectAttribute( $process, 'inspectMode' ) );

		$process->start();

		// Check that the timeout is accurate
		$this->assertEquals( 0, $this->getObjectAttribute( $process, 'timeout' ) );
	}

	/**
	 * Test an exception is thrown on a process timeout
	 */
	public function testTimeout()
	{
		$process = new NodeProcess( <<<'JS'
function sleep(ms) {
    var unixtime_ms = new Date().getTime();
    while(new Date().getTime() < unixtime_ms + ms) {}
}
sleep(5000);	// Node script will try to run for 5 seconds
JS
, [], 0.1, false ); // But the timeout is 0.1 seconds

		$process->setAssignedPort(1234);    // Dummy port

		$this->assertFalse( $this->getObjectAttribute( $process, 'inspectMode' ) );

		$this->expectException( ProcessTimedOutException::class );
		$process->run();
	}
}

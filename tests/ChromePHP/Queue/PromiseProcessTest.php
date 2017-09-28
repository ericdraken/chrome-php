<?php
/**
 * ChromePHP - PromiseProcessTest.php
 * Created by: Eric Draken
 * Date: 2017/9/20
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Queue;

use Draken\ChromePHP\Queue\PromiseProcess;
use PHPUnit\Framework\TestCase;

class PromiseProcessTest extends TestCase
{
	/**
	 * Test basic PromiseProcess construction
	 */
	public function testConstructor()
	{
		$pp = new PromiseProcess( 'return 0', null, null, null, 10 );

		$pp->run();

		$this->assertTrue( $pp->isTerminated() );
		$this->assertFalse( $pp->isRunning() );
		$this->assertTrue( $pp->isSuccessful() );
		$this->assertTrue( $pp->isStarted() );

		$this->assertEquals( 10, $pp->getTimeout() );
	}

	/**
	 * Test two objects have different unique ids
	 *
	 * @depends testConstructor
	 */
	public function testUniqueIds()
	{
		$pp1 = new PromiseProcess( '' );
		$pp2 = new PromiseProcess( '' );

		$this->assertNotEquals( $pp1->getUniqueId(), $pp2->getUniqueId() );
	}

	/**
	 * Test two objects have the same promise after clone
	 * @depends testConstructor
	 */
	public function testSamePromiseAfterClone()
	{
		$pp1 = new PromiseProcess( '' );
		$pp2 = $pp1->cloneCleanProcess();

		$this->assertEquals( $pp1->getPromise(), $pp2->getPromise() );
	}

	/**
	 * Test two objects have the same timeout after clone
	 * @depends testConstructor
	 */
	public function testSameTimeoutAfterClone()
	{
		$pp1 = new PromiseProcess( '', null, null, null, 99 );
		$pp2 = $pp1->cloneCleanProcess();

		$this->assertEquals( $pp1->getTimeout(), $pp2->getTimeout() );
	}

	/**
	 * Test two objects have the same cmd after clone
	 * @depends testConstructor
	 */
	public function testSameCmdAfterClone()
	{
		$pp1 = new PromiseProcess( 'echo 1' );
		$pp2 = $pp1->cloneCleanProcess();

		$this->assertEquals( $pp1->getCommandLine(), $pp2->getCommandLine() );
	}

	/**
	 * Test two objects have different unique ids after clone
	 * @depends testConstructor
	 */
	public function testUniqueIdsAfterClone()
	{
		$pp1 = new PromiseProcess('');
		$pp2 = $pp1->cloneCleanProcess();

		$this->assertNotEquals( $pp1->getUniqueId(), $pp2->getUniqueId() );
	}

	/**
	 * Test the last exception is null after a clone
	 * @depends testConstructor
	 */
	public function testNoLastExceptionAfterClone()
	{
		$pp1 = new PromiseProcess('');
		$pp1->setLastException( new \Exception("test") );

		$pp2 = $pp1->cloneCleanProcess();

		$this->assertEmpty( $pp2->getLastException() );
		$this->assertNotEmpty( $pp1->getLastException() );
		$this->assertNotEquals( $pp1->getLastException(), $pp2->getLastException() );
	}

	/**
	 * Shouldn't be able to clone a running process
	 * @depends testConstructor
	 */
	public function testCloneRunningProcess()
	{
		$pp1 = new PromiseProcess('echo A; sleep 0.01; echo B');
		$pp1->run( function( $type, $buffer ) use ( &$pp1 ) {
			// Received console output while running
			try
			{
				$pp1->cloneCleanProcess();
				$this->fail( "Shouldn't be able to clone a running process" );
			} catch ( \Exception $e ) {
				$this->addToAssertionCount(1);
			}
		} );
	}
}

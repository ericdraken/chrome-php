<?php
/**
 * ChromePHP - ProcessQueueTest.php
 * Created by: Eric Draken
 * Date: 2017/9/21
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Queue;

use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Queue\NullPromiseProcess;
use Draken\ChromePHP\Queue\ProcessorCounter;
use Draken\ChromePHP\Queue\ProcessQueue;
use Draken\ChromePHP\Queue\PromiseProcess;
use PHPUnit\Framework\TestCase;

class ProcessQueueTest extends TestCase
{
	/**
	 * Constructor
	 */
	public function testConstruct()
	{
		$queue = new ProcessQueue();
		$counter = new ProcessorCounter();

		$this->assertInstanceOf( \Countable::class, $queue );
		$this->assertAttributeEquals( $counter->getCpuCount(), 'limit', $queue );
	}

	/**
	 * Set the limit
	 */
	public function testConstructWithArgs()
	{
		$queue = new ProcessQueue(4);

		$this->assertInstanceOf(\Countable::class, $queue);
		$this->assertAttributeEquals(4, 'limit', $queue);
	}

	/**
	 * Set the limit as a string
	 */
	public function testConstructWithNumericString()
	{
		$queue = new ProcessQueue('4');

		$this->assertInstanceOf(\Countable::class, $queue);
		$this->assertAttributeEquals(4, 'limit', $queue);
	}

	/**
	 * Shouldn't be able to set the limit below 1
	 * @depends testConstruct
	 */
	public function testInvalidLimit()
	{
		$this->expectException(InvalidArgumentException::class);
		new ProcessQueue(0);
	}

	/**
	 * Shouldn't be able to set the limit below 1
	 * @depends testConstruct
	 */
	public function testUpdateInvalidLimit()
	{
		$queue = new ProcessQueue(1);

		$this->expectException(InvalidArgumentException::class);
		$queue->updateLimit(0);
	}

	/**
	 * One pending process in the queue
	 * @depends testConstruct
	 */
	public function testAdd()
	{
		$queue = new ProcessQueue(1);

		$process = new PromiseProcess('return 0');
		$queue->add( $process );

		$this->assertEquals( 1, $queue->count() );
		$this->assertCount( 1, $queue->getPending() );
		$this->assertCount( 0, $queue->getRunning() );
		$this->assertCount( 0, $queue->getCompleted() );
		$this->assertCount( 0, $queue->getCompletedProcesses() );
	}

	/**
	 * Two pending processes in the queue
	 * @depends testAdd
	 */
	public function testAdd2()
	{
		$queue = new ProcessQueue(1);

		$process1 = new PromiseProcess('return 0');
		$process2 = new PromiseProcess('return 0');

		$queue->add( $process1 );
		$queue->add( $process2 );

		$this->assertEquals( 2, $queue->count() );
		$this->assertCount( 2, $queue->getPending() );
		$this->assertCount( 0, $queue->getRunning() );
		$this->assertCount( 0, $queue->getCompleted() );
		$this->assertCount( 0, $queue->getCompletedProcesses() );
	}

	/**
	 * Add the same process object several times. This should
	 * result in only one being in the queue
	 * @depends testAdd
	 */
	public function testAddMultipleSameProcess()
	{
		$queue = new ProcessQueue(1);

		$process1 = new PromiseProcess('return 0');

		$queue->add( $process1 );
		$queue->add( $process1 );
		$queue->add( $process1 );

		$this->assertEquals( 1, $queue->count() );
	}

	/**
	 * Postpone a process
	 * @depends testConstruct
	 */
	public function testPostpone()
	{
		$queue = new ProcessQueue(1);

		$process1 = new PromiseProcess('return 0');
		$process2 = new PromiseProcess('return 1');

		$queue->add( $process1 );
		$queue->add( $process2 );

		$this->assertEquals( 2, $queue->count() );

		$queue->postpone( $process1 );

		$this->assertEquals( 2, $queue->count() );
	}

	/**
	 * Get pending processes
	 * @depends testAdd
	 */
	public function testGetPending()
	{
		$queue = new ProcessQueue();
		$process = new PromiseProcess('return 0');

		$queue->add($process);

		$pending = $queue->getPending();

		$this->assertContainsOnly(PromiseProcess::class, $pending);
		$this->assertInstanceOf(PromiseProcess::class, current( $pending ) );
		$this->assertSame($process, current( $pending ) );
	}

	/**
	 * Get running processes
	 * @depends testAdd
	 */
	public function testGetRunning()
	{
		$queue = new ProcessQueue();
		$process = new PromiseProcess('sleep 0.01');

		$queue->add($process);

		$this->assertEmpty($queue->getRunning());

		$process->start();

		$running = $queue->getRunning();

		$this->assertContainsOnly(PromiseProcess::class, $running);
		$this->assertInstanceOf(PromiseProcess::class, current( $running ) );
		$this->assertSame($process, current( $running ) );
	}

	/**
	 * Resolve any promises after a run
	 * @depends testAdd
	 */
	public function testResolveCompleted()
	{
		$queue = new ProcessQueue();
		$process = new PromiseProcess('return 0');

		$queue->add($process);
		$process->run();
		$queue->resolveCompleted();

		$this->assertEmpty( $queue->getPending() );
		$this->assertEmpty( $queue->getRunning() );
		$this->assertEmpty( $queue->getCompleted() );
		$this->assertEquals( 0, $queue->count() );

		$this->assertCount( 1, $queue->getCompletedProcesses() );
		$this->assertTrue( $process->isTerminated() );
	}

	/**
	 * Confirm a promise is resolved after a good run
	 * @depends testResolveCompleted
	 */
	public function testResolveCompletedWithPromiseResolve()
	{
		$queue = new ProcessQueue();
		$process = new PromiseProcess( 'return 0' );
		$promise = $process->getPromise();

		$isResolved = false;
		$promise->then( function () use ( &$isResolved )
		{
			$isResolved = true;
		} );

		$this->assertFalse( $isResolved );

		$queue->add( $process );
		$process->run();
		$queue->resolveCompleted();

		$this->assertEmpty( $queue );
		$this->assertCount( 0, $queue );
		$this->assertTrue( $isResolved );
		$this->assertTrue( $process->isTerminated() );
	}

	/**
	 * Confirm a promise is rejected after a bad run
	 * @depends testResolveCompleted
	 */
	public function testResolveCompletedWithPromiseReject()
	{
		$queue = new ProcessQueue();
		$process = new PromiseProcess( 'return 1' );
		$promise = $process->getPromise();

		$isResolved = false;
		$promise->then( null, function () use ( &$isResolved )
		{
			$isResolved = true;
		} );

		$this->assertFalse( $isResolved );

		$queue->add( $process );
		$process->run();
		$queue->resolveCompleted();

		$this->assertEmpty( $queue );
		$this->assertCount( 0, $queue );
		$this->assertTrue( $isResolved );
		$this->assertTrue( $process->isTerminated() );
	}

	/** @depends testAdd */
	public function testInvoke()
	{
		$queue = new ProcessQueue();
		$process = new PromiseProcess('return 0');

		$queue->add( $process );

		/** @var PromiseProcess $pending */
		foreach ( $queue() as $pending )
		{
			if ( $pending instanceof NullPromiseProcess ) {
				break;
			}

			$this->assertInstanceOf(PromiseProcess::class, $pending );
			$this->assertFalse( $pending->isStarted() );

			$pending->run();
		}

		$this->assertCount( 0, $queue->getPending() );
		$this->assertCount( 0, $queue->getCompleted() );
		$this->assertCount( 0, $queue );
		$this->assertTrue( $process->isTerminated() );
	}

	/**
	 * Test that a completion hook returns false
	 */
	public function testCompletionHookReturnsFalse()
	{
		$queue = new ProcessQueue(1);

		$ranHook = false;
		$queue->setCompletionHook( function() use ( &$ranHook ) {

			$ranHook = true;

			return false;
		} );

		$queue->add( new PromiseProcess('return 0') );

		/** @var PromiseProcess $process */
		$process = $queue()->current();
		$process->run();

		$queue->resolveCompleted();

		$this->assertTrue($ranHook);
	}

	/**
	 * Test that a completion hook returns true which
	 * will try a cloned process again
	 */
	public function testCompletionHookReturnsTrue()
	{
		$queue = new ProcessQueue(1);

		$ranHook = false;
		$queue->setCompletionHook( function() use ( &$ranHook ) {

			$ranHook = true;

			return true;
		} );

		$queue->add( new PromiseProcess('return 0') );

		/** @var PromiseProcess $process */
		$process = $queue()->current();
		$process->run();

		$queue->resolveCompleted();

		$this->assertTrue($ranHook);
		$this->assertCount(1, $queue);
	}

	/**
	 * Test that a completion hook throws an exception
	 * and is treated like it returns false
	 */
	public function testCompletionHookThrowsException()
	{
		$queue = new ProcessQueue(1);

		$ranHook = false;
		$queue->setCompletionHook( function() use ( &$ranHook ) {

			$ranHook = true;
			throw new \Exception('never seen exception');

		} );

		$queue->add( new PromiseProcess('return 0') );

		/** @var PromiseProcess $process */
		$process = $queue()->current();
		$process->run();

		$queue->resolveCompleted();

		$this->assertTrue($ranHook);
		$this->assertCount(0, $queue);
	}

	/**
	 * Two pending processes in the queue
	 * @depends testInvoke
	 */
	public function testRanQueueCounts()
	{
		$queue = new ProcessQueue(1);

		$process1 = new PromiseProcess('return 0');
		$process2 = new PromiseProcess('return 1');

		$queue->add( $process1 );
		$queue->add( $process2 );

		// Baseline counts

		$this->assertEquals( 2, $queue->count() );
		$this->assertCount( 2, $queue->getPending() );
		$this->assertCount( 0, $queue->getRunning() );

		// Get one Process

		/** @var PromiseProcess $proc */
		$proc = $queue()->current();
		$proc->run();

		// Get updated counts after a run

		$this->assertEquals( 2, $queue->count() );
		$this->assertCount( 1, $queue->getPending() );
		$this->assertCount( 0, $queue->getRunning() );
		$this->assertCount( 0, $queue->getCompletedProcesses() );
		$this->assertCount( 0, $queue->getFailedProcesses() );

		// The queue only gets updated after the next process is fetched
		$proc2 = $queue()->current();
		$proc2->run();

		$this->assertEquals( 1, $queue->count() );
		$this->assertCount( 0, $queue->getPending() );
		$this->assertCount( 0, $queue->getRunning() );
		$this->assertCount( 1, $queue->getCompletedProcesses() );
		$this->assertCount( 1, $queue->getSuccessfulProcesses() );
		$this->assertCount( 0, $queue->getFailedProcesses() );

		$nullproc = $queue()->current();
		$this->assertInstanceOf( NullPromiseProcess::class, $nullproc );

		$this->assertEquals( 0, $queue->count() );
		$this->assertCount( 0, $queue->getPending() );
		$this->assertCount( 0, $queue->getRunning() );
		$this->assertCount( 2, $queue->getCompletedProcesses() );
		$this->assertCount( 1, $queue->getSuccessfulProcesses() );
		$this->assertCount( 1, $queue->getFailedProcesses() );
	}
}

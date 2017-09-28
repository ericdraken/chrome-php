<?php
/**
 * ChromePHP - ProcessManagerTest.php
 * Created by: Eric Draken
 * Date: 2017/9/21
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Queue;

use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Queue\ProcessManager;
use Draken\ChromePHP\Queue\ProcessQueue;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProcessManagerTest extends TestCase
{
	/**
	 * Test the constructor
	 */
	public function testConstruct()
	{
		$processManager = new ProcessManager();

		$this->assertAttributeInstanceOf(ProcessQueue::class, 'processQueue', $processManager);
	}

	/**
	 * Test setting the parallelization limit
	 * @depends testConstruct
	 */
	public function testConstructWithLimit()
	{
		$processManager = new ProcessManager(4);

		$this->assertAttributeEquals( 4, 'limit', $processManager->getProcessQueue() );
	}

	/**
	 * Test setting the parallelization limit too low
	 * @depends testConstruct
	 */
	public function testConstructWithZeroLimit()
	{
		$this->expectException( InvalidArgumentException::class );
		new ProcessManager(0);
	}

	/**
	 * Test enqueuing a Process
	 */
	public function testEnqueue()
	{
		$processManager = new ProcessManager();
		$promise = $processManager->enqueue( new Process( 'return 0' ) );

		$this->assertEquals( 1, $processManager->count() );
		$this->assertInstanceOf( PromiseInterface::class, $promise );
	}

	/**
	 * Test shouldn't enqueue a running Process
	 */
	public function testEnqueueAlreadyRunning()
	{
		$processManager = new ProcessManager();

		$process = new Process( 'sleep 0.01' );

		$process->start();

		$this->expectException( InvalidArgumentException::class );
		$processManager->enqueue( $process );
	}

	/**
	 * Test shouldn't enqueue a terminated Process
	 */
	public function testEnqueueTerminated()
	{
		$processManager = new ProcessManager();

		$process = new Process( 'return 0' );

		$process->run();

		$this->expectException( InvalidArgumentException::class );
		$processManager->enqueue( $process );
	}

	/**
	 * Test a resolved promise
	 */
	public function testEnqueueResolvePromise()
	{
		$processManager = new ProcessManager();
		$promise = $processManager->enqueue( new Process( 'return 0' ) );

		$promise->then( function () use ( &$fulfilled, &$exception )
		{
			$fulfilled = true;

			try {
				$this->assertInstanceOf( Process::class, func_get_arg( 0 ) );
			} catch ( \Exception $e ) {
				$exception = $e;
			}

		}, null );

		$processManager->run();

		$this->assertTrue( $fulfilled );
		!!$exception && $this->fail( $exception->getMessage() );
	}

	/**
	 * Test a rejected promise
	 */
	public function testEnqueueRejectPromise()
	{
		$processManager = new ProcessManager();
		$promise = $processManager->enqueue( new Process( 'return 1' ) );

		$promise->then( null, function () use ( &$rejected, &$exception )
		{
			$rejected = true;

			try {
				$this->assertInstanceOf( Process::class, func_get_arg( 0 ) );
			} catch ( \Exception $e ) {
				$exception = $e;
			}
		} );

		$processManager->run();

		$this->assertTrue( $rejected );
		!!$exception && $this->fail( $exception->getMessage() );
	}

	/**
	 * Test the run() with a Promise that should be fulfilled
	 *
	 * @depends testEnqueue
	 */
	public function testRunFulfill()
	{
		$processManager = new ProcessManager();
		$promise = $processManager->enqueue( new Process( 'return 0' ) );

		$promise->then( function ( $process ) use ( &$promise, &$exception )
		{
			try
			{
				/** @var Process $process */
				$this->assertInstanceOf( Process::class, $process );

				$this->assertInstanceOf( PromiseInterface::class, $promise );
				$this->assertTrue( $process->isTerminated() );
				$this->assertTrue( $process->isStarted() );
				$this->assertFalse( $process->isRunning() );
				$this->assertEquals( PromiseInterface::FULFILLED, $promise->getState() );

			} catch ( \Exception $e ) {
				$exception = $e;
			}

		}, null );

		$processManager->run();

		!!$exception && $this->fail( $exception->getMessage() );
	}

	/**
	 * Test the run() with a Promise that should be rejected
	 *
	 * @depends testEnqueue
	 */
	public function testRunReject()
	{
		$processManager = new ProcessManager();
		$promise = $processManager->enqueue( new Process( 'return 1' ) );

		$promise->then( null, function ( $process ) use ( &$promise, &$exception )
		{
			try
			{
				/** @var Process $process */
				$this->assertInstanceOf( Process::class, $process );

				$this->assertInstanceOf( PromiseInterface::class, $promise );
				$this->assertTrue( $process->isTerminated() );
				$this->assertTrue( $process->isStarted() );
				$this->assertFalse( $process->isRunning() );
				$this->assertEquals( PromiseInterface::REJECTED, $promise->getState() );
			} catch ( \Exception $e ) {
				$exception = $e;
			}

		} );

		$processManager->run();

		!!$exception && $this->fail( $exception->getMessage() );
	}

	/**
	 * Test tick callback
	 * @depends testEnqueue
	 */
	public function testRunWithTick()
	{
		$processManager = new ProcessManager();
		$processManager->enqueue( new Process('return 0') );

		$ticks = 0;
		$processManager->run(function() use (&$ticks) {
			$ticks++;
		});

		$this->assertGreaterThan(0, $ticks);
	}

	/**
	 * Test setting a completionHook
	 *
	 * @depends testEnqueue
	 */
	public function testCompletionHook()
	{
		$processManager = new ProcessManager();


		$promise = $processManager->enqueue( new Process( 'return 0' ) );

		$promise->then( function ( $process ) use ( &$promise, &$exception )
		{
			try
			{
				/** @var Process $process */
				$this->assertInstanceOf( Process::class, $process );

				$this->assertInstanceOf( PromiseInterface::class, $promise );
				$this->assertTrue( $process->isTerminated() );
				$this->assertTrue( $process->isStarted() );
				$this->assertFalse( $process->isRunning() );
				$this->assertEquals( PromiseInterface::FULFILLED, $promise->getState() );

			} catch ( \Exception $e ) {
				$exception = $e;
			}

		}, null );

		$processManager->run();

		!!$exception && $this->fail( $exception->getMessage() );
	}
}

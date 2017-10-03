<?php
/**
 * ChromePHP - ChromeProcessManagerTest.php
 * Created by: Eric Draken
 * Date: 2017/9/22
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Core;

use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Core\ChromeProcessors;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Draken\ChromePHP\Queue\ProcessorCounter;
use DrakenTest\ChromePHP\LoggerHelper;
use DrakenTest\ChromePHP\Mocks\NodeProcessMock;
use GuzzleHttp\Promise\Promise;
use PHPUnit\Framework\TestCase;

/**
 * Prevent setting the class alias for all test suites
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ChromeProcessManagerTest extends TestCase
{
	private static $defaultPort = 9222;

	/**
	 * Swap ChromeProcess with ChromeProcessMock
	 * so Chrome isn't really launched for each test.
	 * Tests must be run in separate processes, and
	 * global serialization must be disabled
	 */
	public function setUp()
	{
		/** @noinspection PhpUndefinedClassInspection */
		class_alias(
			'DrakenTest\ChromePHP\Mocks\ChromeProcessMock',
			'Draken\ChromePHP\Core\Chrome'.'Process',
			true
		);
	}

	/**
	 * Test the constructor
	 */
	public function testConstruct()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 2, '' );

		$this->assertAttributeEquals( 2, 'parallelizationLimit', $cmp );

		$this->assertAttributeInstanceOf( Promise::class, 'queuePromise', $cmp );

		$this->assertAttributeInstanceOf( ChromeProcessors::class, 'chromeProcessors', $cmp );
	}

	/**
	 * @depends testConstruct
	 */
	public function testConstructNoLimit()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort );

		$physicalLimit = (new ProcessorCounter())->getCpuCount();

		$this->assertAttributeEquals( $physicalLimit, 'parallelizationLimit', $cmp );
	}

	/**
	 * @depends testConstruct
	 */
	public function testConstructZeroLimit()
	{
		$this->expectException( InvalidArgumentException::class );
		new ChromeProcessManager( self::$defaultPort, 0 );
	}

	/**
	 * @depends testConstruct
	 */
	public function testConstructZeroPort()
	{
		$this->expectException( InvalidArgumentException::class );
		new ChromeProcessManager( 0 );
	}

	/**
	 * @depends testConstruct
	 */
	public function testConstructHighLimitWarning()
	{
		$fp = LoggerHelper::setMemoryBaseLogger();

		new ChromeProcessManager( self::$defaultPort, 100 );

		rewind($fp);

		$this->assertContains( 'higher than the', stream_get_contents($fp) );

		LoggerHelper::removeBaseLogger();
	}

	/**
	 * An empty run should fulfill the manager's promise
	 * @depends testConstruct
	 */
	public function testEmptyRun()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 1 );

		$cmp->then( function () use ( &$fulfilled )
		{
			$fulfilled = true;
		}, function () use ( &$rejected )
		{
			$rejected = true;
		} );

		$cmp->run();

		$this->assertTrue( $fulfilled );
		$this->assertNull( $rejected );
	}

	/**
	 * @depends testEmptyRun
	 */
	public function testOneRun()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 1 );

		$process = new NodeProcessMock( 'echo test; return 0' );

		$cmp->enqueue($process);

		$ticks = 0;
		$cmp->run( function () use ( &$ticks ) {
			$ticks++;
		} );

		$this->assertEquals( 2, $ticks);
	}

	/**
	 * @depends testOneRun
	 */
	public function testTwoRuns()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 1 );

		$process = new NodeProcessMock( 'echo test; return 0' );
		$process2 = new NodeProcessMock( 'echo test; return 0' );

		$cmp->enqueue($process);
		$cmp->enqueue($process2);

		$ticks = 0;
		$cmp->run( function () use ( &$ticks ) {
			$ticks++;
		} );

		$this->assertEquals( 3, $ticks);
	}

	/**
	 * @depends testOneRun
	 */
	public function testFulfilledRun()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 1 );

		$process = new NodeProcessMock( 'echo test; return 0' );
		$cmp->enqueue($process);

		$cmp->then( function () use ( &$fulfilled )
		{
			$fulfilled = true;
		}, function () use ( &$rejected )
		{
			$rejected = true;
		} );

		$cmp->run();

		$this->assertTrue( $fulfilled );
		$this->assertNull( $rejected );
	}

	/**
	 * @depends testOneRun
	 */
	public function testRejectedRun()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 1 );

		$process = new NodeProcessMock( 'echo boo; return 1' );
		$cmp->enqueue($process);

		$cmp->then( function () use ( &$fulfilled )
		{
			$fulfilled = true;
		}, function () use ( &$rejected )
		{
			$rejected = true;
		} );

		$cmp->run();

		$this->assertNull( $fulfilled );
		$this->assertTrue( $rejected );
	}

	/**
	 * @depends testOneRun
	 */
	public function testExceptionRun()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 1 );

		// An exception will be thrown on start() with an empty command line
		$process = new NodeProcessMock( '' );
		$cmp->enqueue($process);

		$cmp->then( function () use ( &$fulfilled )
		{
			$fulfilled = true;
		}, function () use ( &$rejected )
		{
			$rejected = true;
		} );

		$cmp->run();

		$this->assertNull( $fulfilled );
		$this->assertTrue( $rejected );
	}

	/**
	 * @depends testOneRun
	 */
	public function testFailedProcessRun()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 1 );

		// Return 'test' and exit code 1
		$process = new NodeProcessMock( 'echo test; return 1' );
		$cmp->enqueue($process);

		$cmp->then( function () use ( &$fulfilled ) {
			$fulfilled = true;
		}, function ( array &$arr ) use ( &$rejected, &$exception ) {
			$rejected = true;

			list( $queue, $ex ) = $arr;

			try {
				$this->assertCount( 1, $queue->getCompletedProcesses() );
				$this->assertCount( 0, $queue->getSuccessfulProcesses() );
				$this->assertCount( 1, $queue->getFailedProcesses() );
				$this->assertInstanceOf( RuntimeException::class, $ex );
			} catch ( \Exception $e ) {
				$exception = $e;
			}
		} );

		$cmp->run();

		$this->assertNull( $fulfilled );
		$this->assertTrue( $rejected );
		!!$exception && $this->fail( $exception->getMessage() );
	}

	/**
	 * Test one successful process and one failed process
	 * @depends testOneRun
	 */
	public function testMixedProcessRun()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 1 );

		// Return 'A' and exit code 1
		$process = new NodeProcessMock( '>&2 echo A; return 1' );
		$cmp->enqueue($process);

		// Return 'B' and exit code 0
		$process = new NodeProcessMock( 'echo B; return 0' );
		$cmp->enqueue($process);

		$cmp->then( function () use ( &$fulfilled ) {
			$fulfilled = true;
		}, function ( array &$arr ) use ( &$rejected, &$exception ) {
			$rejected = true;

			list( $queue, $ex ) = $arr;

			try
			{
				$this->assertCount( 2, $queue->getCompletedProcesses() );
				$this->assertCount( 1, $queue->getSuccessfulProcesses() );
				$this->assertCount( 1, $queue->getFailedProcesses() );
				$this->assertInstanceOf( RuntimeException::class, $ex );

				$proc1 = current( $queue->getSuccessfulProcesses() );
				$this->assertEquals( 'B', trim( $proc1->getOutput() ) );

				$proc2 = current( $queue->getFailedProcesses() );
				$this->assertEquals( 'A', trim( $proc2->getErrorOutput() ) );

			} catch ( \Exception $e ) {
				$exception = $e;
			}
		} );

		$cmp->run();

		$this->assertNull( $fulfilled );
		$this->assertTrue( $rejected );
		!!$exception && $this->fail( $exception->getMessage() );
	}

	/**
	 * Test two processors run in parallel
	 * @depends testOneRun
	 */
	public function testTwoProcessors()
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, 2 );

		$timeDiff = 100;
		$process = new NodeProcessMock( 'sleep 0.1; return 0' );
		$process->getPromise()->then( function() use ( &$timeDiff ) {
			// Start timer
			$timeDiff = microtime(true);
		} );

		$process2 = new NodeProcessMock( 'sleep 0.1; return 0' );
		$process->getPromise()->then( function() use ( &$timeDiff ) {
			// Stop timer
			$timeDiff = microtime(true) - $timeDiff;
		} );

		$cmp->enqueue( $process );
		$cmp->enqueue( $process2 );

		$cmp->run();

		// The time difference between two process that
		// run in parallel should be near zero. If they run
		// in series then expected time diff would be 0.1
		$this->assertLessThan( 0.01, $timeDiff );
	}

	/**
	 * Test ten processors run in parallel
	 * @depends testTwoProcessors
	 */
	public function testTenProcessors()
	{
		// Simulate spinning up 10 processors
		$cmp = new ChromeProcessManager( self::$defaultPort, 10 );

		$timeDiff = 100;

		for ( $i = 0; $i < 9; $i++ )
		{
			$cmp->enqueue( new NodeProcessMock( 'sleep 0.1; return 0' ) );
		}

		$process10 = new NodeProcessMock( 'sleep 0.1; return 0' );
		$process10->getPromise()->then( function() use ( &$timeDiff ) {
			// Stop timer
			$timeDiff = microtime(true) - $timeDiff;
		} );
		$cmp->enqueue( $process10 );

		// Start timer
		$timeDiff = microtime(true);

		$cmp->run();

		// The time difference between 10 process that
		// run in parallel should be near 0.1. If they run
		// in series then expected time diff would be 1.0
		$this->assertLessThan( 0.2, $timeDiff );
	}

	/**
	 * Test four processors run in parallel with four processors
	 * @depends testTwoProcessors
	 */
	public function testTwelveByFourProcessors()
	{
		// Simulate spinning up 12 processors
		$cmp = new ChromeProcessManager( self::$defaultPort, 4 );

		$timeDiff = 100;

		for ( $i = 0; $i < 11; $i++ )
		{
			$cmp->enqueue( new NodeProcessMock( 'sleep 0.1; return 0' ) );
		}

		$process12 = new NodeProcessMock( 'sleep 0.1; return 0' );
		$process12->getPromise()->then( function() use ( &$timeDiff ) {
			// Stop timer
			$timeDiff = microtime(true) - $timeDiff;
		} );
		$cmp->enqueue( $process12 );

		// Start timer
		$timeDiff = microtime(true);

		$cmp->run();

		// The time difference between three groups of four
		// should be 0.3. In series, it would be closer to 1.2
		$this->assertLessThan( 0.4, $timeDiff );
	}

	/**
	 * Provide limits and expected ticks
	 * @return array
	 */
	public function connectionRefusedErrorLimitsProvider(): array
	{
		$maxErrors = ChromeProcessManager::MAX_TERMINATIONS_BEFORE_DECREASING_PARALLELIZATION_LIMIT;

		return [
			"1 processor" => [1, ( 1 * $maxErrors ) ],
			"2 processors" => [2, ( 2 * $maxErrors ) ],
			"3 processors" => [3, ( 3 * $maxErrors ) ]
		];
	}

	/**
	 * Chrome always starts, but
	 * a connection is always refused, and
	 * Chrome is "killed" right away.
	 * Test that the manager doesn't infinitely spin up new processors
	 *
	 * @param int $limit
	 * @param int $ticksExpected
	 *
	 * @dataProvider connectionRefusedErrorLimitsProvider
	 */
	public function testConnectionRefusedError( int $limit, int $ticksExpected )
	{
		$cmp = new ChromeProcessManager( self::$defaultPort, $limit );

		$process = new NodeProcessMock( '>&2 echo "ECONNREFUSED"; return 1' );
		$cmp->enqueue($process);

		$cmp->then( function () use ( &$fulfilled ) {
			$fulfilled = true;
		}, function ( array &$arr ) use ( &$rejected, &$exception ) {
			$rejected = true;

			/** @var \Exception $exception */
			list( $queue, $ex ) = $arr;

			try
			{
				$this->assertNotEmpty( $ex );
				$this->assertContains( 'Ran out of processors', $ex->getMessage() );
			} catch ( \Exception $e ) {
				$exception = $e;
			}
		} );

		$ticks = 0;
		$cmp->run( function () use ( &$ticks ) {
			$ticks++;
		} );

		$this->assertNull( $fulfilled );
		$this->assertTrue( $rejected );
		$this->assertGreaterThanOrEqual( $ticksExpected, $ticks);
		!!$exception && $this->fail( $exception->getMessage() );
	}

	/**
	 * Provide limits and expected ticks
	 * @return array
	 */
	public function connectionRefusedErrorChromeStillRunningDataProvider(): array
	{
		$errorsBeforeTermination = ChromeProcessManager::CHROME_MAX_NUM_ERRORS_BEFORE_TERMINATION;
		$maxErrors = ChromeProcessManager::MAX_TERMINATIONS_BEFORE_DECREASING_PARALLELIZATION_LIMIT;

		return [
			"1 processor" => [1, ( 1 * $maxErrors * $errorsBeforeTermination ) ],
			"2 processors" => [2, ( 2 * $maxErrors * $errorsBeforeTermination ) ],
			"4 processors" => [4, ( 4 * $maxErrors * $errorsBeforeTermination ) ]
		];
	}

	/**
	 * Chrome always starts and runs, but
	 * a connection is always refused
	 * Test that the manager does not infinitely postpone processes
	 *
	 * @param int $limit
	 * @param int $ticksExpected
	 *
	 * @dataProvider connectionRefusedErrorChromeStillRunningDataProvider
	 */
	public function testConnectionRefusedErrorChromeStillRunning( int $limit, int $ticksExpected )
	{
		class_alias(
			'DrakenTest\ChromePHP\Mocks\ChromeCommandsMock',
			'Draken\ChromePHP\Commands\Chrome'.'Commands',
			true
		);

		$cmp = new ChromeProcessManager( self::$defaultPort, $limit );

		$process = new NodeProcessMock( '>&2 echo "ECONNREFUSED"; return 1' );
		$cmp->enqueue($process);

		$cmp->then( function () use ( &$fulfilled ) {
			$fulfilled = true;
		}, function ( array &$arr ) use ( &$rejected, &$exception ) {
			$rejected = true;

			/** @var \Exception $exception */
			list( $queue, $ex ) = $arr;

			try
			{
				$this->assertNotEmpty( $ex );
				$this->assertContains( 'Ran out of processors', $ex->getMessage() );
			} catch ( \Exception $e ) {
				$exception = $e;
			}
		} );

		$ticks = 0;
		$cmp->run( function () use ( &$ticks ) {
			$ticks++;
		} );

		$this->assertNull( $fulfilled );
		$this->assertTrue( $rejected );
		$this->assertGreaterThanOrEqual( $ticksExpected, $ticks );
		!!$exception && $this->fail( $exception->getMessage() );
	}
}

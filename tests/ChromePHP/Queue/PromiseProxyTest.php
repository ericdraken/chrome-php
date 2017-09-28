<?php
/**
 * ChromePHP - PromiseProxyTest.php
 * Created by: Eric Draken
 * Date: 2017/9/20
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Queue;

use Draken\ChromePHP\Queue\PromiseProxy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PromiseProxyTest extends TestCase
{
	/**
	 * Test the simplest operation of the ProxyPromise
	 */
	public function testResolvePromiseProxy()
	{
		$promise = new PromiseProxy();

		$process = new Process("return 0");

		$promise->setProcess( $process );

		$process->run();

		$promise->wait( false );

		$this->assertTrue( $process->isTerminated() );
		$this->assertTrue( $process->isSuccessful() );
	}

	/**
	 * Test the a promise rejection
	 */
	public function testRejectPromiseProxy()
	{
		$promise = new PromiseProxy();

		$process = new Process("return 1");

		$promise->setProcess( $process );

		$process->run();
		$promise->wait( false );

		$this->assertTrue( $process->isTerminated() );
		$this->assertFalse( $process->isSuccessful() );
	}

	/**
	 * Test no process set
	 */
	public function testBadPromiseProxy()
	{
		$promise = new PromiseProxy();

		try {
			$promise->wait( false );

			$this->fail( "Supposed to throw an exception" );

		} catch ( \Exception $e ) {
			$this->addToAssertionCount(1);
		}

	}
}

<?php
/**
 * ChromePHP - ChromeRunnerTest.php
 * Created by: Eric Draken
 * Date: 2017/10/19
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Processes;

use Draken\ChromePHP\Processes\PageInfoProcess;

class ChromeRunnerTest extends ProcessTestFixture
{
	/**
	 * Test a simple single run works
	 */
	public function testSimpleRun()
	{
		$process = new PageInfoProcess( self::$server );

		$didRun = false;
		$process->runInChrome( 9222, function ( PageInfoProcess $process ) use ( &$didRun ) {
			$didRun = true;
		} );

		$this->assertTrue( $didRun );
		$this->assertEquals( 200, $process->getRenderedPageInfoObj()->getStatus() );
	}

	/**
	 * Nested runs should fail
	 */
	public function testNestedRun()
	{
		$process = new PageInfoProcess( self::$server );

		$process->runInChrome( 9222, function ( PageInfoProcess $process ) use ( &$didRun ) {
			$this->expectException( \Exception::class );
			$process->runInChrome( 9222 );
			$this->fail( "An exception should have been raised" );
		} );
	}

	/**
	 * Nested runs should fail
	 */
	public function testNestedRun2()
	{
		$process = new PageInfoProcess( self::$server );

		$this->expectException( \Exception::class );
		$process->runInChrome( 9222, function ( PageInfoProcess $process ) use ( &$didRun ) {
			$process->runInChrome( 9222 );
			$this->fail( "An exception should have been raised" );
		} );
	}
}
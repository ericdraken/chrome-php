<?php
/**
 * ChromePHP - HarProcessTest.php
 * Created by: Eric Draken
 * Date: 2017/10/12
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Processes;

use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Processes\HarProcess;
use Draken\ChromePHP\Processes\Response\HarInfo;

class HarProcessTest extends ProcessTestFixture
{
	public function testHar()
	{
		$process = new HarProcess( self::$server . "/image.html" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( HarProcess $successfulProcess ) use ( &$obj )
			{
				$out = $successfulProcess->getErrorOutput();
				$obj = $successfulProcess->getHarInfo();

			}, function ( HarProcess $failedProcess ) use ( &$procFailed, &$out )
			{
				$out = $failedProcess->getErrorOutput();
				$procFailed = true;
			} );

		$manager->then( null, function () use ( &$queueFailed )
		{
			$queueFailed = true;
		} );

		// Start processing
		$manager->run();

		$procFailed && $this->fail( "Process should not have failed" );
		$queueFailed && $this->fail( "Queue should not have failed" );

		$this->assertInstanceOf( HarInfo::class, $obj );

		// Test specific attributes in the HAR
		$this->assertEquals( 200, $obj->getStatus() );

		$har = $obj->getHarObj();
		$this->assertCount( 2, $har->log->entries );

	}
}

<?php
/**
 * ChromePHP - ProcessFailedExceptionTest.php
 * Created by: Eric Draken
 * Date: 2017/9/21
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Exceptions;

use Draken\ChromePHP\Exceptions\ProcessFailedException;
use DrakenTest\ChromePHP\LoggerHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProcessFailedExceptionTest extends TestCase
{
	/**
	 * Test the logger is working
	 */
	public function testLogger()
	{
		$fp = LoggerHelper::setMemoryBaseLogger();

		// Echo a string to stderr and set the exit code 1
		$process = new Process('echo "test failure" 1>&2; return 1');
		$process->run();

		new ProcessFailedException( $process );

		rewind($fp);
		$out = stream_get_contents($fp);

		$this->assertContains( 'test failure', $out );
	}
}

<?php
/**
 * ChromePHP - InvalidArgumentException.php
 * Created by: Eric Draken
 * Date: 2017/9/21
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Exceptions;

use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use DrakenTest\ChromePHP\LoggerHelper;
use PHPUnit\Framework\TestCase;

class InvalidArgumentExceptionTest extends TestCase
{
	/**
	 * Test the logger is working
	 */
	public function testLogger()
	{
		$fp = LoggerHelper::setMemoryBaseLogger();

		new InvalidArgumentException("test failure");

		rewind($fp);

		$this->assertContains( 'test failure', stream_get_contents($fp) );
	}
}
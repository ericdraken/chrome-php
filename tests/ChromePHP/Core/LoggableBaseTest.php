<?php
/**
 * ChromePHP - LoggableBaseTest.php
 * Created by: Eric Draken
 * Date: 2017/9/21
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Core;

use Draken\ChromePHP\Core\LoggableBase;
use DrakenTest\ChromePHP\LoggerHelper;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LoggableBaseTest extends TestCase
{
	/**
	 * Start each test with no logger set
	 */
	protected function setUp()
	{
		LoggableBase::setBaseLogger( new NullLogger() );
	}

	/**
	 * Start each test with no logger set
	 */
	protected function tearDown()
	{
		LoggableBase::setBaseLogger( new NullLogger() );
	}

	/**
	 * Test the supplied logger is accessible
	 */
	public function testSetBaseLogger()
	{
		$logger = new Logger('test');

		LoggableBase::setBaseLogger( $logger );

		$this->assertEquals( $logger, LoggableBase::logger() );
	}

	/**
	 * Test that a message can be logged
	 */
	public function testLogMessage()
	{
		$fp = LoggerHelper::setMemoryBaseLogger();

		LoggableBase::logger()->error( 'test message' );

		rewind($fp);

		$this->assertContains( 'test message', stream_get_contents($fp) );
	}
}

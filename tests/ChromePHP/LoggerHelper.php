<?php
/**
 * ChromePHP - LoggerHelper.php
 * Created by: Eric Draken
 * Date: 2017/9/20
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP;

use Draken\ChromePHP\Core\LoggableBase;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

class LoggerHelper
{
	/**
	 * Set the base logger to log to the console. This is
	 * only useful for CLI script debugging and unit testing
	 *
	 * @param int $level
	 */
	public static function setStdoutBaseLogger( int $level = Logger::DEBUG )
	{
		// Default TZ
		if ( ! date_default_timezone_get() && ! ini_get('date.timezone') ) {
			Logger::setTimezone( new \DateTimeZone( 'America/Vancouver' ) );
		}

		$logger = new Logger( __FILE__ );
		$formatter = new LineFormatter( "[%datetime%][%level_name%] %message% %context% %extra%\n", '', true, true );
		$handler = new StreamHandler( 'php://stdout', $level );
		$handler->setFormatter( $formatter );
		$logger->pushHandler( $handler );
		LoggableBase::setBaseLogger( $logger );
	}

	/**
	 * Set the base logger to a stream and return a resource reference
	 * that you can rewind($fp) to get the contents
	 * @param int $level
	 *
	 * @return resource
	 */
	public static function setMemoryBaseLogger( int $level = Logger::DEBUG )
	{
		$fp = fopen("php://temp/maxmemory:4096", 'r+');

		// Log to a stream
		$logger = new Logger(__FILE__);
		$handler = new StreamHandler($fp, $level );
		$formatter = new LineFormatter("[%datetime%][%level_name%] %message%", '', true, true);
		$handler->setFormatter( $formatter );
		$logger->pushHandler( $handler );
		LoggableBase::setBaseLogger($logger);

		return $fp;
	}

	/**
	 * Remove the Stdout logger by replacing it with a null logger
	 */
	public static function removeBaseLogger()
	{
		LoggableBase::setBaseLogger( new NullLogger() );
	}
}
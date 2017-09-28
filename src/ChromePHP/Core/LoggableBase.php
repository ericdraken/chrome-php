<?php
/**
 * ChromePHP - LoggableBase.php
 * Created by: Eric Draken
 * Date: 2017/9/12
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class LoggableBase
 */
abstract class LoggableBase
{
	/**
	 * @var bool Enforce the assignment of a logger
	 */
	private static $enforceLogger = false;

	/**
	 * Hold a static reference to the Base logger
	 * @var LoggerInterface
	 */
	private static $logger;

	/**
	 * Return an instance to $logger which may be a NullLogger if the
	 * setBaseLogger() has not be called at least once
	 *
	 * @return LoggerInterface
	 * @throws \RuntimeException
	 */
	public static function logger()
	{
		if ( ! isset( self::$logger ) )
		{
			if ( self::$enforceLogger )
			{
				// Throw an error to ensue that a logger is set
				throw new \RuntimeException( "No Logger set" );
			}
			else
			{
				// Set a null logger that does nothing
				self::$logger = new NullLogger();
			}
		}

		if ( ! ( self::$logger instanceof LoggerInterface ) )
		{
			throw new \RuntimeException( "Base logger set, but is not of type Logger. Got: " . gettype( self::$logger ) );
		}

		// Return the global logger instance
		return self::$logger;
	}

	/**
	 * Set a static logger for all inherited components to use
	 *
	 * @param LoggerInterface $logger
	 */
	public static function setBaseLogger( LoggerInterface $logger )
	{
		self::$logger = $logger;
	}
}
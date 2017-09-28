<?php
/**
 * ChromePHP - LoggerHelper.php
 * Created by: Eric Draken
 * Date: 2017/9/8
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Utils;


use Draken\ChromePHP\Core\LoggableBase;

class ChromeLogFilter extends LoggableBase
{
	public static function logConsoleOutput( $msg )
	{
		// [0909/065847.723026:VERBOSE1:pulse_stubs.cc(683)] ...
		$re = '/\[([^\:]+)\:([^\:]+)\:([^\]]+)\]([^\[]+)/im';

		preg_match_all($re, $msg, $matches, PREG_SET_ORDER, 0);

		foreach ( $matches as $match )
		{
			// Message
			$line = trim(preg_replace( '/[\n\r]+/', ' ', $match[3] . ': ' . $match[4] ) );

			// Severity
			$severity = strtoupper(preg_replace('/[^a-zA-Z]+/', '', $match[2]));

			switch ( $severity )
			{
				case 'VERBOSE':
					self::logger()->debug($line);
					break;

				case 'INFO':
					self::logger()->info($line);
					break;

				case 'NOTICE':
				default:
					self::logger()->notice($line);
					break;

				case 'WARN':
				case 'WARNING':
					self::logger()->warning($line);
					break;

				case 'ERR':
				case 'ERROR':
					self::logger()->error($line);
					break;
			}
		}
	}

	/**
	 * Extract out the WS endpoint from a log message, if present
	 * @param $msg
	 *
	 * @return bool
	 */
	public static function extractWsEndpoint( $msg )
	{
		// REF: node_modules\puppeteer\lib\Launcher.js:161
		// line.match(/^DevTools listening on (ws:\/\/.*)$/);
		if ( preg_match('/^DevTools listening on (ws:\/\/.*)$/m', $msg, $match) ) {
			return $match[1];
		}

		return false;
	}
}
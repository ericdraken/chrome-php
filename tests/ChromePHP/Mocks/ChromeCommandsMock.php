<?php
/**
 * ChromePHP - ChromeCommandsMock.php
 * Created by: Eric Draken
 * Date: 2017/9/24
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Mocks;

class ChromeCommandsMock
{
	public static $numChromeProcessesRunning = 1;

	public static function isChromeRunning( int $port )
	{
		// Always running on this port
		return true;
	}

	public static function quitChromeInstanceGracefully( int $port ) {

	}

	public static function killChromeProcesses( int $signal = 2, int $pid = 0 ) {

	}

	public static function getNumChromeProcessesRunning() {
		return self::$numChromeProcessesRunning;
	}

	public static function getPidOfChromeBoundToPort( int $port ) {
		return -1;
	}

	public static function showChromeProcessTree() {

	}
}
<?php
/**
 * ChromePHP - NodeCommands.php
 * Created by: Eric Draken
 * Date: 2017/9/20
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Commands;

use Draken\ChromePHP\Core\LoggableBase;
use Draken\ChromePHP\Exceptions\ProcessFailedException;
use Symfony\Component\Process\Process;

class NodeCommands extends LoggableBase
{
	/**
	 * Return the installed NodeJS version
	 * @return string|false
	 */
	public static function getInstalledNodeVersion()
	{
		$cmd = LinuxCommands::getNodeVersionCmd;

		self::logger()->info("Exec [ $cmd ]");

		// Run the check
		$process = new Process( $cmd );
		$process->run();

		// On failure, throw exception
		if ( ! empty( $process->getErrorOutput() ) )
		{
			throw new ProcessFailedException( $process );
		}

		// Return the version if found
		$res = $process->getOutput();
		return ( $process->isSuccessful() && ! empty( $res ) ) ? $res : false;
	}
}
<?php
/**
 * ChromePHP - ChromeProcessMock.php
 * Created by: Eric Draken
 * Date: 2017/9/23
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Mocks;

class ChromeProcessMock
{
	// Default
	private $port = 9222;

	public function __construct( $chromeBinaryPath = '' )
	{

	}

	/**
	 * @param array $envVars
	 */
	public function setEnvVars( array $envVars ) {

	}

	/**
	 * @return int
	 */
	public function getPort(): int {
		return $this->port;
	}

	/**
	 * @return string
	 */
	public function getWsEndpointUrl(): string {
		return "ws://localhost:{$this->port}/notrealendpoint";
	}

	/**
	 * @return string
	 */
	public function getTempFolder(): string {
		return sys_get_temp_dir();
	}

	/**
	 * @return bool
	 */
	public function isChromeRunning() {
		return true;
	}

	/**
	 * @param string $chromeBinaryPath
	 */
	public function setChromeBinaryPath( string $chromeBinaryPath )
	{

	}

	/**
	 * @param $str
	 */
	public function setDefaultUserAgent($str)
	{

	}

	public function flushBuffers()
	{

	}

	/**
	 * @param int $port
	 */
	public function launchHeadlessChrome( $port = 9222 ) {
		$this->port = $port;
	}

	/**
	 * @return float|null
	 */
	public function getTimeout() {

	}

	/**
	 * @throws ProcessTimedOutException
	 */
	public function checkTimeout() {

	}

	/**
	 * @param bool $graceful
	 */
	public function quitAndCleanup( $graceful = true )
	{

	}
}
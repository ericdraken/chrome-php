<?php
/**
 * ChromePHP - ChromeRunnerTraits.php
 * Created by: Eric Draken
 * Date: 2017/10/18
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes\Traits;

use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Exceptions\RuntimeException;

trait ChromeRunnerTraits
{
	/**
	 * @param int $startingChromePort
	 * @param callable $callback
	 * @param callable $tickFunction
	 * @param string $chromeBinaryPath
	 */
	public function runInChrome(
		int $startingChromePort = 9222,
		callable $callback = null,
		callable $tickFunction = null,
		string $chromeBinaryPath = '' )
	{
		// Prevent reusing this process after having ran previously
		if ( $this->isStarted() ) {
			throw new RuntimeException( "Unable to reuse this process" );
		}

		// Chrome process manager with a 2-browser limit
		$manager = new ChromeProcessManager($startingChromePort, 1, $chromeBinaryPath );

		/** @var NodeProcess $this */
		$manager->enqueue( $this );

		// The Chrome process manager returns a promise
		$manager->then( function (...$args) use ( &$callback, &$err )
		{
			// Fulfill
			try {
				if ( is_callable( $callback ) ) {
					call_user_func( $callback, $this );
				}
			} catch ( \Exception $e ) {
				$err = $e;
			}
		}, function (...$args) use ( &$callback, &$err )
		{
			// Reject
			try {
				if ( is_callable( $callback ) ) {
					call_user_func( $callback, $this );
				}
			} catch ( \Exception $e ) {
				$err = $e;
			}
		} );

		// Start processing, and close Chrome
		// before throwing any errors below
		$manager->run( $tickFunction );

		// Throw the error outside the promise handlers
		if ( $err ) {
			throw $err;
		}
	}
}
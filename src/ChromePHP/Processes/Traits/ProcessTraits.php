<?php
/**
 * ChromePHP - ProcessArgs.php
 * Created by: Eric Draken
 * Date: 2017/10/12
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes\Traits;

use Draken\ChromePHP\Core\LoggableBase;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Emulations\Devices\DefaultDesktop;
use Draken\ChromePHP\Emulations\Emulation;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Draken\ChromePHP\Queue\PromiseProxy;
use GuzzleHttp\Promise\Promise;

trait ProcessTraits
{
	protected function processArgs( string $url, array &$args = [], Emulation &$emulation = null )
	{
		if ( empty( $url ) ) {
			throw new InvalidArgumentException( "Supplied URL is empty" );
		}

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new InvalidArgumentException( "Invalid URL. Got: $url" );
		}

		// Register the device emulation or a default desktop emulation
		$this->emulation = is_null( $emulation ) ? new DefaultDesktop() : $emulation;

		// Filter out any reserved params supplied by the user
		$args = array_filter( $args, function ( $arg ) {
			return
				stripos( $arg, '--url=' ) !== 0 &&
				stripos( $arg, '--timeout=' ) !== 0 &&
				stripos( $arg, '--emulation=' ) !== 0;
		} );
	}

	/**
	 * Create a new PromiseProxy using a
	 * modified wait function to that of the
	 * default PromiseProxy
	 *
	 * @param NodeProcess $process
	 * @param Promise $promise The promise to get back
	 * @param callable|null $successCheckCallback
	 */
	protected function setupInternalPromiseProxyResolver( NodeProcess $process, Promise &$promise, callable $successCheckCallback = null )
	{
		// Set up the wait function
		$promise = new PromiseProxy( function () use ( $process, &$promise, &$successCheckCallback )
		{
			if ($this->isStarted()) {
				$this->wait();
			}

			if ( $this->isSuccessful() )
			{
				try
				{
					// Test for another property to determine if really successful.
					// Throw an exception here to reject the promise
					if ( is_callable( $successCheckCallback ) ) {
						call_user_func( $successCheckCallback, $process );
					}

					// If no exception was thrown, then fulfill this promise
					$promise->resolve( $process );
				}
				catch ( \Exception $exception )
				{
					// Something went wrong parsing the response
					$this->setLastException( $exception );
					$promise->reject( $process );
				}
			}
			else
			{
				// Something went wrong in the process
				LoggableBase::logger()->error( $process->getLastException() && $process->getLastException()->getMessage() );
				LoggableBase::logger()->error( "NodeJS debug logs:" . PHP_EOL . $this->getErrorOutput() );

				$promise->reject( $process );
			}
		} );

		// Assign the process to this proxy
		$promise->setProcess( $process );
	}

	/**
	 * Retrieve the NodeJS temp file JSON data and
	 * inflate it back into an object
	 *
	 * @return \stdClass
	 */
	protected function tempFileJsonToObj(): \stdClass
	{
		// Winston debug logs
		LoggableBase::logger()->info( "NodeJS debug logs:" . PHP_EOL . $this->getErrorOutput() );

		$jsonFile = trim( $this->getOutput() );

		// Verify the file
		if ( ! file_exists( $jsonFile ) ) {
			throw new RuntimeException("Couldn't open the JSON file: " . substr( $jsonFile, 0, 50));
		}
		$contents = file_get_contents( $jsonFile );

		if ( ! $obj = json_decode( $contents, false ) ) {
			throw new RuntimeException("Couldn't parse the JSON contents: " . substr( $contents, 0, 50) . '...');
		}

		return $obj;
	}
}
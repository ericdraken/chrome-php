<?php
/**
 * ChromePHP - PageInfoProcess.php
 * Created by: Eric Draken
 * Date: 2017/9/26
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes;

use Draken\ChromePHP\Core\LoggableBase;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Emulations\Devices\DefaultDesktop;
use Draken\ChromePHP\Emulations\Emulation;
use Draken\ChromePHP\Exceptions\HttpResponseException;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Draken\ChromePHP\Processes\HTTP\RenderedHTTPPageInfo;
use Draken\ChromePHP\Queue\PromiseProxy;
use Draken\ChromePHP\Utils\Paths;

class PageInfoProcess extends NodeProcess
{
	/** @var RenderedHTTPPageInfo */
	protected $domInfoObj;

	/** @var Emulation */
	protected $emulation;

	/**
	 * PageInfoProcess constructor.
	 *
	 * @param string $url
	 * @param array $args
	 * @param Emulation|null $emulation
	 * @param int $timeout
	 */
	public function __construct( string $url, array $args = [], Emulation $emulation = null, $timeout = 10 )
	{
		if ( empty( $url ) ) {
			throw new InvalidArgumentException( "Supplied URL is empty" );
		}

		// Register the device emulation or a default desktop emulation
		$this->emulation = is_null( $emulation ) ? new DefaultDesktop() : $emulation;

		// Filter out any reserved params supplied by the user
		$args = array_filter( $args, function ( $arg ) {
			return
				stripos( $arg, '--url=' ) !== 0 &&
				stripos( $arg, '--emulation=' ) !== 0;
		} );

		// Merge the args array with the mandatory arguments,
		// but always use the params supplied below
		parent::__construct(Paths::getNodeScriptsPath() . '/page.js', array_merge($args, [
			'--url='.$url,
			'--emulation='.$this->emulation
		]), $timeout, false, false);

		// Set an empty object as the result
		$this->domInfoObj = new RenderedHTTPPageInfo();

		// Setup a new wait function
		$this->setupPromiseProxyResolver();
	}

	/**
	 * @return RenderedHTTPPageInfo
	 */
	public function getDomInfoObj(): RenderedHTTPPageInfo
	{
		return $this->domInfoObj;
	}

	/**
	 * Create a new PromiseProxy using a
	 * modified wait function to that of the
	 * default PromiseProxy
	 */
	private function setupPromiseProxyResolver()
	{
		// Set up the wait function
		$this->promise = new PromiseProxy( function ()
		{
			if ($this->isStarted()) {
				$this->wait();
			}

			if ( $this->isSuccessful() )
			{
				try
				{
					$this->domInfoObj = $this->processNodeResults();

					// Was the initial request successful?
					if ( $this->domInfoObj->isOk() ) {
						$this->promise->resolve( $this );
					} else {
						// The response was not ok
						$this->setLastException( new HttpResponseException( "Didn't get a 2XX response" ) );
						$this->promise->reject( $this );
					}
				}
				catch ( \Exception $exception )
				{
					// Something went wrong parsing the response
					$this->setLastException( $exception );
					$this->promise->reject( $this );
				}
			}
			else
			{
				// Something went wrong in the process
				LoggableBase::logger()->error( $this->getLastException() && $this->getLastException()->getMessage() );
				LoggableBase::logger()->error( "NodeJS debug logs:" . PHP_EOL . $this->getErrorOutput() );

				$this->promise->reject( $this );
			}
		} );

		// Assign the process to this proxy
		$this->promise->setProcess( $this );
	}

	/**
	 * Retrieve the NodeJS temp file JSON data and
	 * inflate it back into an object
	 *
	 * @return RenderedHTTPPageInfo
	 */
	private function processNodeResults(): RenderedHTTPPageInfo
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

		return new RenderedHTTPPageInfo( $obj );
	}
}
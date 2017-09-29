<?php
/**
 * ChromePHP - DOMInfoProcess.php
 * Created by: Eric Draken
 * Date: 2017/9/26
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes;

use Draken\ChromePHP\Core\LoggableBase;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Exceptions\HttpResponseException;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Draken\ChromePHP\Queue\PromiseProxy;
use Draken\ChromePHP\Utils\Paths;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\RejectionException;
use Prophecy\Promise\CallbackPromise;

class DOMInfoProcess extends NodeProcess
{
	/** @var DOMInfo */
	private $domInfoObj;

	/**
	 * DOMInfoProcess constructor.
	 *
	 * @param string $url
	 * @param array $args
	 * @param int $timeout
	 */
	public function __construct( string $url, array $args = [], $timeout = 10 )
	{
		if ( empty( $url ) ) {
			throw new InvalidArgumentException( "Supplied URL is empty" );
		}

		// Filter out any URL params supplied by the user
		$args = array_filter( $args, function ( $arg ) {
			return stripos( $arg, '--url=' ) !== 0;
		} );

		// Merge the args array with the mandatory argument,
		// but always use the --url param supplied below
		parent::__construct(Paths::getNodeScriptsPath() . '/dom.js', array_merge($args, [
			'--url='.$url
		]), $timeout, false, false);

		// Set an empty object as the result
		$this->domInfoObj = new DOMInfo();

		// Setup a new wait function
		$this->setupPromiseProxyResolver();
	}

	/**
	 * @return DOMInfo
	 */
	public function getDomInfoObj(): DOMInfo
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
					if ( $this->domInfoObj->ok ) {
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
	 * @return DOMInfo
	 */
	private function processNodeResults(): DOMInfo
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

		return new DOMInfo( $obj );
	}
}

class DOMInfo
{
	/** @var bool */
	public $ok = false;

	/** @var int */
	public $status = 0;

	/** @var string */
	public $requestUrl = "";

	/** @var ResponseInfo */
	public $lastResponse;

	/** @var ResponseInfo[] */
	public $redirectChain = [];

	/** @var string */
	public $rawHtml = "";

	/** @var string */
	public $renderedHtml = "";

	/** @var array */
	public $consoleLogs = [];

	/** @var array */
	public $requests = [];

	/** @var ResponseInfo[] */
	public $failed = [];

	/** @var string[] */
	public $errors = [];

	/**
	 * @param \stdClass $data
	 */
	public function __construct( \stdClass $data = null )
	{
		if ( is_null( $data ) )
		{
			return $this;
		}

		foreach ( $data as $key => $value )
		{
			// Response to initial request
			if ( is_object( $value ) ) {
				$this->{$key} = new ResponseInfo( $value );
			}

			// Error messages are plain strings
			else if ( is_array( $value ) && count( $value ) && is_string( $value[0] ) )
			{
				foreach ( $value as $msg ) {
					$this->{$key}[] = $msg;
				}
				continue;
			}

			// Failure objects and redirect objects
			else if ( is_array( $value ) && count( $value ) && is_object( $value[0] )  )
			{
				foreach ( $value as $obj ) {
					$this->{$key}[] = new ResponseInfo( $obj );
				}
				continue;
			}

			// Primitives
			$this->{$key} = $value;
		}

		return $this;
	}
}

class ResponseInfo
{
	/** @var string */
	public $url = "";

	/** @var int */
	public $status = 0;

	/** @var string */
	public $type = "";

	/** @var string */
	public $method = "";

	/** @var \stdClass */
	public $requestHeaders;

	/** @var \stdClass */
	public $responseHeaders;

	/**
	 * @param \stdClass $data
	 */
	public function __construct( \stdClass $data = null )
	{
		if ( is_null( $data ) )
		{
			return $this;
		}

		foreach ( $data as $key => $value )
		{
			$this->{$key} = $value;
		}

		return $this;
	}
}
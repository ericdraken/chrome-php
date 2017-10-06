<?php
/**
 * ChromePHP - RenderedHTTPPageInfo.php
 * Created by: Eric Draken
 * Date: 2017/9/30
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes\Response;

class RenderedHTTPPageInfo
{
	/** @var bool */
	protected $ok = false;

	/** @var int */
	protected $status = 0;

	/** @var string */
	protected $requestUrl = "";

	/** @var HTTPResponseInfo */
	protected $lastResponse;

	/** @var HTTPResponseInfo[] */
	protected $redirectChain = [];

	/** @var string */
	protected $rawHtml = "";

	/** @var string */
	protected $renderedHtml = "";

	/** @var array */
	protected $consoleLogs = [];

	/** @var mixed */
	protected $vmcodeResults;

	/** @var array */
	protected $requests = [];

	/** @var HTTPResponseInfo[] */
	protected $failed = [];

	/** @var string[] */
	protected $pageErrors = [];

	/** @var int */
	protected $loadTime = -1;

	/**
	 * @return bool
	 */
	public function isOk(): bool
	{
		return $this->ok;
	}

	/**
	 * @return int
	 */
	public function getStatus(): int
	{
		return $this->status;
	}

	/**
	 * @return string
	 */
	public function getRequestUrl(): string
	{
		return $this->requestUrl;
	}

	/**
	 * @return HTTPResponseInfo
	 */
	public function getLastResponse(): HTTPResponseInfo
	{
		return $this->lastResponse;
	}

	/**
	 * @return HTTPResponseInfo[]
	 */
	public function getRedirectChain(): array
	{
		return $this->redirectChain;
	}

	/**
	 * @return string
	 */
	public function getRawHtml(): string
	{
		return $this->rawHtml;
	}

	/**
	 * @return string
	 */
	public function getRenderedHtml(): string
	{
		return $this->renderedHtml;
	}

	/**
	 * @return array
	 */
	public function getConsoleLogs(): array
	{
		return $this->consoleLogs;
	}

	/**
	 * @return mixed
	 */
	public function getVmcodeResults()
	{
		return $this->vmcodeResults;
	}

	/**
	 * @return array
	 */
	public function getRequests(): array
	{
		return $this->requests;
	}

	/**
	 * @return HTTPResponseInfo[]
	 */
	public function getFailed(): array
	{
		return $this->failed;
	}

	/**
	 * @return \string[]
	 */
	public function getPageErrors(): array
	{
		return $this->pageErrors;
	}

	/**
	 * @return int
	 */
	public function getLoadTime(): int
	{
		return $this->loadTime;
	}

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
			// Special property to include as is
			if ( $key === 'vmcodeResults' ) {
				$this->{$key} = $value;
				continue;
			}

			// Ensure lastResponse is an object
			if ( $key === 'lastResponse' && ! is_object( $value ) ) {
				$value = new \stdClass();
			}

			// Response to initial request
			if ( is_object( $value ) ) {
				$this->{$key} = new HTTPResponseInfo( $value );
				continue;
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
					$this->{$key}[] = new HTTPResponseInfo( $obj );
				}
				continue;
			}

			// Primitives
			$this->{$key} = $value;
		}

		return $this;
	}
}
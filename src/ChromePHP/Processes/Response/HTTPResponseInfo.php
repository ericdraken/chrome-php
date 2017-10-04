<?php
/**
 * ChromePHP - HTTPResponseInfo.php
 * Created by: Eric Draken
 * Date: 2017/9/30
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes\Response;

class HTTPResponseInfo
{
	/** @var string */
	protected $url = "";

	/** @var int */
	protected $status = 0;

	/** @var string */
	protected $type = "";

	/** @var string */
	protected $method = "";

	/** @var \stdClass */
	protected $requestHeaders;

	/** @var \stdClass */
	protected $responseHeaders;

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
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
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * @return \stdClass
	 */
	public function getRequestHeaders(): \stdClass
	{
		return $this->requestHeaders;
	}

	/**
	 * @return \stdClass
	 */
	public function getResponseHeaders(): \stdClass
	{
		return $this->responseHeaders;
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
			$this->{$key} = $value;
		}

		return $this;
	}
}
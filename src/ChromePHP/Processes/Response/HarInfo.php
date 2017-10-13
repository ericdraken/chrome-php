<?php
/**
 * ChromePHP - HarInfo.php
 * Created by: Eric Draken
 * Date: 2017/10/12
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes\Response;

use Draken\ChromePHP\Exceptions\RuntimeException;

class HarInfo
{
	/** @var \stdClass */
	private $harObj;

	/** @var string */
	private $harJson = '{}';

	/** @var int */
	private $status = 0;

	/** @var string */
	private $url = '';

	/** @var string */
	private $redirectURL = '';

	/**
	 * HarInfo constructor.
	 *
	 * @param \stdClass|null $obj
	 */
	public function __construct( \stdClass $obj = null )
	{
		if ( is_null( $obj ) )
		{
			return $this;
		}

		if ( ! property_exists( $obj, 'log' ) ) {
			throw new RuntimeException( "Har property 'log' missing from object" );
		}

		// Get HAR components
		$log = $obj->log;
		$entries = $log->entries;

		// Main entry
		$mainEntry = $entries[0];
		$mainRequest = $mainEntry->request;
		$mainResponse = $mainEntry->response;

		$this->status = $mainResponse->status;
		$this->url = $mainRequest->url;
		$this->redirectURL = $mainResponse->redirectURL;

		// Follow any redirects
		for ( $i = 1; $i < count( $entries ); $i++ )
		{
			$response = $entries[$i]->response;
			$this->status = $response->status;

			// Save the redirect URL
			if ( $this->status >= 300 && $this->status <= 399 ) {
				$this->redirectURL = $response->redirectURL;
			}

			// Follow only redirects
			if ( $this->status <= 300 || $this->status > 399 ) {
				break;
			}
		}

		// Save the HAR object
		$this->harObj = $obj;

		// JSON string
		$this->harJson = json_encode( $this->harObj );
	}

	/**
	 * @return \stdClass
	 */
	public function getHarObj(): \stdClass
	{
		return $this->harObj;
	}

	/**
	 * @return string
	 */
	public function getHarJson(): string
	{
		return $this->harJson;
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
	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * @return string
	 */
	public function getRedirectURL(): string
	{
		return $this->redirectURL;
	}

	/**
	 * @return bool
	 */
	public function isDidRedirect(): bool
	{
		return strlen( $this->redirectURL ) > 0;
	}

	/**
	 * @return bool
	 */
	public function isOk(): bool
	{
		return $this->status >= 200 && $this->status <= 299;
	}
}
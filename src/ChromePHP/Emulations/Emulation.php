<?php
/**
 * ChromePHP - Emulation.php
 * Created by: Eric Draken
 * Date: 2017/10/2
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Emulations;

use Draken\ChromePHP\Exceptions\InvalidArgumentException;

class Emulation
{
	/** @var \stdClass */
	protected $viewport;

	/** @var string */
	protected $userAgent = '';

	/** @var bool */
	protected $fullPage = false;

	/**
	 * Emulation constructor
	 *
	 * @param int $width
	 * @param int $height
	 * @param float $deviceScaleFactor
	 * @param string $userAgent
	 * @param bool $isMobile
	 * @param bool $hasTouch
	 * @param bool $isLandscape
	 * @param bool $fullPage
	 */
	public function __construct(
		int $width,
		int $height,
		float $deviceScaleFactor = 1.0,
		string $userAgent = '',
		bool $isMobile = false,
		bool $hasTouch = false,
		bool $isLandscape = false,
		bool $fullPage = false
	) {
		if ( $width < 1 || $width > 4096 ) {
			throw new InvalidArgumentException( "Width of $width is invalid" );
		}

		if ( $height < 1 || $height > 16384 ) {
			throw new InvalidArgumentException( "Height of $height is invalid" );
		}

		if ( $deviceScaleFactor < 1.0 || $deviceScaleFactor > 3.0 ) {
			throw new InvalidArgumentException( "Please supply a scale between 0 and 3. Got $deviceScaleFactor" );
		}

		$this->viewport = new \stdClass();
		$vp = &$this->viewport;

		$vp->width = $width;
		$vp->height = $height;
		$vp->deviceScaleFactor = $deviceScaleFactor;
		$vp->isMobile = $isMobile;
		$vp->hasTouch = $hasTouch;
		$vp->isLandscape = $isLandscape;

		$this->userAgent = $userAgent ?: '';
		$this->fullPage = $fullPage;
	}

	/**
	 * Convert the emulation information into a JSON
	 * string to be consumed by Puppeteer
	 * @return string
	 */
	public function __toString()
	{
		$obj = new \stdClass();
		$obj->viewport = $this->viewport;
		$obj->userAgent = $this->userAgent;
		$obj->fullPage = $this->fullPage;

		return json_encode( $obj );
	}

	/**
	 * @return int
	 */
	public function getWidth(): int
	{
		return $this->viewport->width;
	}

	/**
	 * @return int
	 */
	public function getHeight(): int
	{
		return $this->viewport->height;
	}

	/**
	 * @return float
	 */
	public function getDeviceScaleFactor(): float
	{
		return $this->viewport->deviceScaleFactor;
	}

	/**
	 * @return bool
	 */
	public function isMobile(): bool
	{
		return $this->viewport->isMobile;
	}

	/**
	 * @return bool
	 */
	public function hasTouch(): bool
	{
		return $this->viewport->hasTouch;
	}

	/**
	 * @return bool
	 */
	public function isLandscape(): bool
	{
		return $this->viewport->isLandscape;
	}

	/**
	 * @return string
	 */
	public function getUserAgent(): string
	{
		return $this->userAgent;
	}

	/**
	 * @return bool
	 */
	public function isFullPage(): bool
	{
		return $this->fullPage;
	}

	/**
	 * This parameter is only used when taking screenshots
	 * @param bool $fullPage
	 */
	public function setFullPage( bool $fullPage )
	{
		$this->fullPage = $fullPage;
	}
}
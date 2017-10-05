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
	/** @var int */
	protected $width;

	/** @var int */
	protected $height;

	/** @var double */
	protected $scaleFactor;

	/** @var bool */
	protected $isMobile = false;

	/** @var bool */
	protected $hasTouch = false;

	/** @var bool */
	protected $isLandscape = false;

	/** @var string */
	protected $userAgent = '';

	/** @var bool */
	protected $fullPage = false;

	/**
	 * Emulation constructor
	 *
	 * @param int $width
	 * @param int $height
	 * @param float $scaleFactor
	 * @param string $userAgent
	 * @param bool $isMobile
	 * @param bool $hasTouch
	 * @param bool $isLandscape
	 * @param bool $fullPage
	 */
	public function __construct(
		int $width,
		int $height,
		float $scaleFactor = 1.0,
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

		if ( $scaleFactor < 1.0 || $scaleFactor > 3.0 ) {
			throw new InvalidArgumentException( "Please supply a scale between 0 and 3. Got $scaleFactor" );
		}

		$this->width = $width;
		$this->height = $height;
		$this->scaleFactor = $scaleFactor;
		$this->isMobile = $isMobile;
		$this->hasTouch = $hasTouch;
		$this->isLandscape = $isLandscape;
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

		// Viewport
		$v = new \stdClass();
		$v->width = $this->width;
		$v->height = $this->height;
		$v->deviceScaleFactor = $this->scaleFactor;
		$v->isMobile = $this->isMobile;
		$v->hasTouch = $this->hasTouch;
		$v->isLandscape = $this->isLandscape;

		// Assemble the object consumed by Puppeteer
		$obj->viewport = $v;
		$obj->userAgent = $this->userAgent;

		// Extra information
		$obj->fullPage = $this->fullPage;

		return json_encode( $obj );
	}

	/**
	 * @return int
	 */
	public function getWidth(): int
	{
		return $this->width;
	}

	/**
	 * @return int
	 */
	public function getHeight(): int
	{
		return $this->height;
	}

	/**
	 * @return double
	 */
	public function getScaleFactor(): double
	{
		return $this->scaleFactor;
	}

	/**
	 * @return bool
	 */
	public function isMobile(): bool
	{
		return $this->isMobile;
	}

	/**
	 * @return bool
	 */
	public function hasTouch(): bool
	{
		return $this->hasTouch;
	}

	/**
	 * @return bool
	 */
	public function isLandscape(): bool
	{
		return $this->isLandscape;
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
<?php
/**
 * ChromePHP - ScreenshotInfo.php
 * Created by: Eric Draken
 * Date: 2017/10/5
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes\Response;

use Draken\ChromePHP\Emulations\Emulation;
use Draken\ChromePHP\Utils\Casts;

class ScreenshotInfo
{
	/** @var string */
	protected $url = '';

	/** @var Emulation */
	protected $emulation;

	/** @var string */
	protected $filepath = '';

	/** @var int */
	protected $width = 0;

	/** @var int */
	protected $height = 0;

	/** @var float */
	protected $scale = 0.0;

	/** @var bool */
	protected $fullPage = false;

	/** @var int */
	protected $filesize = 0;

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
			// Inflate the Emulation object
			if ( $key === 'emulation' )
			{
				$this->{$key} = Casts::create( Emulation::class, $value );
				continue;
			}

			$this->{$key} = $value;
		}

		return $this;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * @return Emulation
	 */
	public function getEmulation(): Emulation
	{
		return $this->emulation;
	}

	/**
	 * @return string
	 */
	public function getFilepath(): string
	{
		return $this->filepath;
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
	 * @return float
	 */
	public function getScale(): float
	{
		return $this->scale;
	}

	/**
	 * @return bool
	 */
	public function isFullPage(): bool
	{
		return $this->fullPage;
	}

	/**
	 * @return int
	 */
	public function getFilesize(): int
	{
		return $this->filesize;
	}
}
<?php
/**
 * ChromePHP - DefaultDesktop.php
 * Created by: Eric Draken
 * Date: 2017/10/2
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Emulations\Common;

use Draken\ChromePHP\Emulations\Emulation;

class DefaultDesktop extends Emulation
{
	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct() {
		parent::__construct(1280, 800, 1.0);
	}
}
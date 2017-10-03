<?php
/**
 * ChromePHP - DefaultDesktop.php
 * Created by: Eric Draken
 * Date: 2017/10/2
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Emulations\Devices;

use Draken\ChromePHP\Emulations\Emulation;

/**
 * Class DefaultDesktop
 * Use a screen resolution of 1366x768 with the default UA of the system
 * @see
 *
 * @package Draken\ChromePHP\Emulations\Devices
 */
class DefaultDesktop extends Emulation
{
	public function __construct() {
		parent::__construct(1366, 768, 1.0, '', false, false, false);
	}
}
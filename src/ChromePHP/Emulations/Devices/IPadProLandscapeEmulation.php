<?php
/**
 * ChromePHP - IPadProLandscapeEmulation.php
 * Created by: Eric Draken
 * Date: 2017/10/3
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Emulations\Devices;

use Draken\ChromePHP\Emulations\Emulation;

class IPadProLandscapeEmulation extends Emulation
{
	public function __construct()
	{
		$ua = 'Mozilla/5.0 (iPad; CPU OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1';
		parent::__construct(1366, 1024, 2.0, $ua, true, true, true);
	}
}
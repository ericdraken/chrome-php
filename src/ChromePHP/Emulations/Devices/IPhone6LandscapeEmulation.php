<?php
/**
 * ChromePHP - IPhone6LandscapeEmulation.php
 * Created by: Eric Draken
 * Date: 2017/10/3
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Emulations\Devices;

use Draken\ChromePHP\Emulations\Emulation;

class IPhone6LandscapeEmulation extends Emulation
{
	public function __construct()
	{
		$ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1';
		parent::__construct(667, 375, 2.0, $ua, true, true, true);
	}
}
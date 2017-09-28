<?php
/**
 * ChromePHP - ChromeLogFilterTest.php
 * Created by: Eric Draken
 * Date: 2017/9/20
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Utils;

use Draken\ChromePHP\Utils\Paths;
use PHPUnit\Framework\TestCase;

class ChromeLogFilterTest extends TestCase
{
	/**
	 * Check that the same regex pattern is consistently
	 * present in updates to the Puppeteer node module. This
	 * regex is the basis of how the DevTools WS endpoint is found
	 */
	public function testDevToolsWSRegex()
	{
		// REF: node_modules\puppeteer\lib\Launcher.js:161
		// line.match(/^DevTools listening on (ws:\/\/.*)$/);
		$target = Paths::getNodeModulesPath() . '/puppeteer/lib/Launcher.js';
		$this->assertFileExists( $target );

		$src = file_get_contents( $target );
		$this->assertContains( '/^DevTools listening on (ws:\/\/.*)$/', $src );
	}
}

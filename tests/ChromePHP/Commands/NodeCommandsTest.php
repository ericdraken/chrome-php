<?php
/**
 * ChromePHP - NodeCommandsTest.php
 * Created by: Eric Draken
 * Date: 2017/9/20
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Commands;

use Draken\ChromePHP\Commands\NodeCommands;
use PHPUnit\Framework\TestCase;

class NodeCommandsTest extends TestCase
{
	public function testGetInstalledNodeVersion()
	{
		$ver = NodeCommands::getInstalledNodeVersion();

		$this->assertNotEmpty( $ver );
		$this->assertStringStartsWith( 'v', $ver, "A correct Node version starts with a 'v'. Got: $ver" );
	}
}

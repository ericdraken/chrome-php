<?php
/**
 * ChromePHP - EmulationTest.php
 * Created by: Eric Draken
 * Date: 2017/10/2
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Emulations;

use PHPUnit\Framework\TestCase;

class EmulationTest extends TestCase
{
	public function testEmulation()
	{
		$e = new Emulation(100, 200, 2.5, 'agent', true, true, true );

		$json = $e->__toString();
		$this->assertNotEmpty( $json );

		$obj = json_decode( $json, false );
		$this->assertNotNull( $obj );

		$this->assertObjectHasAttribute( 'viewport', $obj );
		$this->assertObjectHasAttribute( 'userAgent', $obj );

		$this->assertEquals( 100, $obj->viewport->width );
		$this->assertEquals( 200, $obj->viewport->height );
		$this->assertEquals( 2.5, $obj->viewport->deviceScaleFactor );
		$this->assertTrue( $obj->viewport->isMobile );
		$this->assertTrue( $obj->viewport->hasTouch );
		$this->assertTrue( $obj->viewport->isLandscape );
		$this->assertEquals( 'agent', $obj->userAgent );
	}
}

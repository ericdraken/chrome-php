<?php
/**
 * ChromePHP - ChromeProcessorInfoTest.php
 * Created by: Eric Draken
 * Date: 2017/9/22
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Core;

use Draken\ChromePHP\Core\ChromeProcess;
use Draken\ChromePHP\Core\ChromeProcessorInfo;
use Draken\ChromePHP\Core\NodeProcess;
use PHPUnit\Framework\TestCase;

class ChromeProcessorInfoTest extends TestCase
{
	/**
	 * Set the Chrome process
	 */
	public function testConstruct()
	{
		$proc = new ChromeProcess();

		$info = new ChromeProcessorInfo( $proc );

		$this->assertEquals( $proc, $info->getChromeInstance() );
		$this->assertAttributeEquals( $proc, 'chromeInstance', $info );
		$this->assertCount( 0, $info->getExceptions() );
		$this->assertEquals( 0, $info->getNumExceptions() );
	}

	/**
	 * @depends testConstruct
	 */
	public function testAddNodeProcess()
	{
		$proc = new ChromeProcess();
		$node = new NodeProcess();

		$info = new ChromeProcessorInfo( $proc );
		$info->assignProcess( $node );

		$this->assertEquals( $node, $info->getAssignedProcess() );
		$this->assertAttributeEquals( $node, 'assignedProcess', $info );
	}

	/**
	 * If no port is set yet, then return 0
	 * @depends testConstruct
	 */
	public function testGetPortUnstartedChromeProcess()
	{
		$proc = new ChromeProcess();

		$info = new ChromeProcessorInfo( $proc );

		$this->assertEquals( 0, $info->getPort() );
	}

	/**
	 * @depends testConstruct
	 */
	public function testAddException()
	{
		$info = new ChromeProcessorInfo( new ChromeProcess() );
		$ex = new \Exception('test');

		$info->addException( $ex );

		$this->assertEquals( $ex, current( $info->getExceptions() ) );
		$this->assertCount( 1, $info->getExceptions() );
	}

	/**
	 * @depends testConstruct
	 */
	public function testHasNoAssignedProcess()
	{
		$proc = new ChromeProcess();

		$info = new ChromeProcessorInfo( $proc );

		$this->assertFalse( $info->hasAssignedProcess() );
	}

	/**
	 * @depends testConstruct
	 */
	public function testHasAssignedProcess()
	{
		$proc = new ChromeProcess();
		$node = new NodeProcess();

		$info = new ChromeProcessorInfo( $proc );
		$info->assignProcess( $node );

		$this->assertTrue( $info->hasAssignedProcess() );
	}

	/**
	 * @depends testConstruct
	 */
	public function testHasProcess()
	{
		$proc = new ChromeProcess();
		$node = new NodeProcess();

		$info = new ChromeProcessorInfo( $proc );
		$info->assignProcess( $node );

		$this->assertTrue( $info->hasProcess( $node ) );
	}

	/**
	 * @depends testConstruct
	 */
	public function testNotHasProcess()
	{
		$proc = new ChromeProcess();
		$node = new NodeProcess();
		$node2 = new NodeProcess();

		$info = new ChromeProcessorInfo( $proc );
		$info->assignProcess( $node );

		$this->assertFalse( $info->hasProcess( $node2 ) );
	}

	/**
	 * @depends testConstruct
	 */
	public function testUnassignProcess()
	{
		$proc = new ChromeProcess();
		$node = new NodeProcess();

		$info = new ChromeProcessorInfo( $proc );
		$info->assignProcess( $node );

		$this->assertTrue( $info->hasProcess( $node ) );

		$info->unassignProcess();

		$this->assertFalse( $info->hasProcess( $node ) );
	}
}

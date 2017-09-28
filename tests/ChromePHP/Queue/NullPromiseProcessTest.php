<?php
/**
 * ChromePHP - NullPromiseProcessTest.php
 * Created by: Eric Draken
 * Date: 2017/9/21
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Queue;

use Draken\ChromePHP\Queue\NullPromiseProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class NullPromiseProcessTest extends TestCase
{
	public function testConstruct()
	{
		$process = new NullPromiseProcess();

		$this->assertInstanceOf(Process::class, $process);
	}

	public function testStart()
	{
		$process = new NullPromiseProcess();

		$this->assertNull($process->start());
	}

	public function testRun()
	{
		$process = new NullPromiseProcess();

		$this->assertEquals(0, $process->run());
	}

	public function testMustRun()
	{
		$process = new NullPromiseProcess();

		$this->assertEquals(0, $process->mustRun());
	}

	public function testWait()
	{
		$process = new NullPromiseProcess();

		$this->assertEmpty(0, $process->wait());
	}

	public function testGetPromise()
	{
		$process = new NullPromiseProcess();

		$this->assertNull($process->getPromise());
	}

	public function testGetUniqueId()
	{
		$process = new NullPromiseProcess();

		$this->assertEmpty(0, $process->getUniqueId());
	}
}

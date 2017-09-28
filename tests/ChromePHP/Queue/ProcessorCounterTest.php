<?php
/**
 * ChromePHP - ProcessorCounterTest.php
 * Created by: Eric Draken
 * Date: 2017/9/21
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Queue;

use Draken\ChromePHP\Queue\ProcessorCounter;
use PHPUnit\Framework\TestCase;

class ProcessorCounterTest extends TestCase
{
	public function testGetCpuCount()
	{
		$processorCounter = new ProcessorCounter();
		$processorCount = $processorCounter->getCpuCount();

		$this->assertInternalType('integer', $processorCount);
		$this->assertGreaterThan(0, $processorCount);
	}

	public function testToString()
	{
		$processorCounter = new ProcessorCounter();
		$processorCount = (string) $processorCounter;

		$this->assertInternalType('string', $processorCount);
		$this->assertGreaterThan(0, $processorCount);
	}
}

<?php
/**
 * ChromePHP - UtilsTest.php
 * Created by: Eric Draken
 * Date: 2017/9/20
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Utils;

use Draken\ChromePHP\Utils\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
	/**
	 * Test the creation of temp folders used by Chrome and NodeJS
	 */
	public function testMakeUnixTmpDir()
	{
		$dir = Utils::makeUnixTmpDir();
		$this->assertDirectoryExists( $dir );
		$this->assertDirectoryIsWritable( $dir );

		rmdir( $dir );
		$this->assertDirectoryNotExists( $dir );
	}

	/**
	 * Make a temp dir, create a file, then rmrf the dir
	 * @depends testMakeUnixTmpDir
	 */
	public function testRmrfDir()
	{
		$dir = Utils::makeUnixTmpDir();
		$this->assertDirectoryIsWritable( $dir );

		$touched = $dir . DIRECTORY_SEPARATOR . 'touched';

		touch( $touched );
		$this->assertFileExists( $touched );

		$this->assertFalse( Utils::rmrfDir( $dir ) );

		$this->assertFileNotExists( $touched );
		$this->assertFileNotExists( $dir );
	}

	/**
	 * General test battery on strposa()
	 */
	public function testStrposa()
	{
		$sub = "ABCDEF";

		$this->assertTrue( Utils::strposa( $sub, ['A'] ) );

		$this->assertTrue( Utils::strposa( $sub, ['F'] ) );

		$this->assertTrue( Utils::strposa( $sub, ['CD'] ) );

		$this->assertTrue( Utils::strposa( $sub, 'B' ) );

		$this->assertFalse( Utils::strposa( $sub, ['G'] ) );

		$this->assertFalse( Utils::strposa( $sub, [] ) );

		$this->assertFalse( Utils::strposa( $sub, null ) );

	}
}

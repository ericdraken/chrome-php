<?php
/**
 * ChromePHP - PageInfoProcessTLSTest.php
 * Created by: Eric Draken
 * Date: 2017/10/18
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Processes;

use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Processes\PageInfoProcess;
use Draken\ChromePHP\Processes\Response\RenderedHTTPPageInfo;

class PageInfoProcessTLSTest extends ProcessTestFixture
{
	/**
	 * @return array
	 */
	public function badSSLGenerator()
	{
		return [
			'sha256' => [ 'sha256.badssl.com', true ],
			'expired' => [ 'expired.badssl.com', false ],
			'wrong host' => [ 'wrong.host.badssl.com', false ],
			'self-signed' => [ 'self-signed.badssl.com', false ],
			'untrusted root' => [ 'untrusted-root.badssl.com', false ],
			'incomplete chain' => [ 'incomplete-chain.badssl.com', false ],
			'dh480' => [ 'dh480.badssl.com', false ],
			'dh512' => [ 'dh512.badssl.com', false ],
			'dh1024' => [ 'dh1024.badssl.com', false ],
			'dh2048' => [ 'dh2048.badssl.com', false ],

			// Failing tests
			//'revoked' => [ 'revoked.badssl.com', false ],
			//'pinning' => [ 'pinning-test.badssl.com', false ],
		];
	}

	/**
	 * @dataProvider badSSLGenerator
	 *
	 * @param string $domain
	 * @param bool $shouldPass
	 */
	public function testTLSCerts( string $domain, bool $shouldPass )
	{
		$process = new PageInfoProcess( "https://$domain/" );

		$manager = new ChromeProcessManager( self::$defaultPort, 1 );

		// Enqueue the job
		$manager
			->enqueue( $process )
			->then( function ( PageInfoProcess $successfulProcess ) use ( &$obj, &$success, &$out )
			{
				$obj = $successfulProcess->getRenderedPageInfoObj();
				$out = $successfulProcess->getErrorOutput();
				$success = true;

			}, function ( PageInfoProcess $failedProcess ) use ( &$obj, &$success, &$out )
			{
				$obj = $failedProcess->getRenderedPageInfoObj();
				$out = $failedProcess->getErrorOutput();
				$success = false;
			} );

		$manager->then( null, function () use ( &$queueFailed )
		{
			$queueFailed = true;
		} );

		// Start processing
		$manager->run();

		$queueFailed && $this->fail( "Queue should not have failed" );
		$this->assertInstanceOf( RenderedHTTPPageInfo::class, $obj );

		// Confirm expected TLS results
		/** @var RenderedHTTPPageInfo $obj */
		$this->assertEquals( $shouldPass, $success, $out );
	}
}

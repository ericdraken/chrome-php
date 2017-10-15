<?php
/**
 * ChromePHP - HarProcess.php
 * Created by: Eric Draken
 * Date: 2017/10/12
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes;

use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Emulations\Emulation;
use Draken\ChromePHP\Exceptions\HttpResponseException;
use Draken\ChromePHP\Processes\Response\HarInfo;
use Draken\ChromePHP\Processes\Traits\ProcessTraits;
use Draken\ChromePHP\Utils\Paths;

class HarProcess extends NodeProcess
{
	use ProcessTraits;

	/** @var HarInfo */
	protected $harInfo;

	/** @var Emulation */
	protected $emulation;

	/** @var string */
	protected $url;

	/**
	 * PageInfoProcess constructor.
	 *
	 * @param string $url
	 * @param array $args
	 * @param Emulation|null $emulation
	 * @param int $timeout
	 */
	public function __construct( string $url, array $args = [], Emulation $emulation = null, $timeout = 10 )
	{
		// Validate and set args
		$this->processArgs( $url, $args, $emulation );

		// Merge the args array with the mandatory arguments,
		// but always use the params supplied below
		parent::__construct(Paths::getNodeScriptsPath() . '/har.js', array_merge($args, [
			'--url='.$url,
			'--emulation='.$this->emulation
		]), $timeout, false, false);

		// Set an empty object as the result
		$this->harInfo = new HarInfo();

		// Setup a new wait function
		$this->setupPromiseProxyResolver();
	}

	/**
	 * Create a new PromiseProxy using a
	 * modified wait function to that of the
	 * default PromiseProxy
	 *
	 * @param callable|null $successCheckCallback
	 */
	protected function setupPromiseProxyResolver( callable $successCheckCallback = null )
	{
		$this->setupInternalPromiseProxyResolver(
			$this,
			$this->promise,
			function( HarProcess $process ) use ( &$successCheckCallback ) {

				$obj = $this->tempFileJsonToObj();
				$process->harInfo = new HarInfo( $obj );

				// Throw any network related error
				if ( strpos( $process->harInfo->getLastError(), 'ERR_' ) !== false ) {
					throw new HttpResponseException( $this->harInfo->getLastError() );
				}

				// Was the initial request successful?
				if ( $process->harInfo->isOk() )
				{
					// Test for another property to determine if really successful.
					// Throw an exception here to reject the promise
					if ( is_callable( $successCheckCallback ) ) {
						call_user_func( $successCheckCallback, $process->harInfo );
					}
				} else {
					// The response was not ok.
					// Throw an exception to reject the promise
					throw new HttpResponseException( "Didn't get a 2XX response. Got {$process->harInfo->getStatus()}" );
				}
			} );
	}

	/**
	 * @return HarInfo
	 */
	public function getHarInfo(): HarInfo
	{
		return $this->harInfo;
	}

	/**
	 * @return Emulation
	 */
	public function getEmulation(): Emulation
	{
		return $this->emulation;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}
}
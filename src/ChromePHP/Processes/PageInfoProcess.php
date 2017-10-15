<?php
/**
 * ChromePHP - PageInfoProcess.php
 * Created by: Eric Draken
 * Date: 2017/9/26
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes;

use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Emulations\Emulation;
use Draken\ChromePHP\Exceptions\HttpResponseException;
use Draken\ChromePHP\Processes\Response\RenderedHTTPPageInfo;
use Draken\ChromePHP\Processes\Traits\ProcessTraits;
use Draken\ChromePHP\Utils\Paths;

class PageInfoProcess extends NodeProcess
{
	use ProcessTraits;

	/** @var RenderedHTTPPageInfo */
	protected $renderedPageInfoObj;

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
		parent::__construct(Paths::getNodeScriptsPath() . '/page.js', array_merge($args, [
			'--url='.$url,
			'--emulation='.$this->emulation
		]), $timeout, false, false);

		// Set an empty object as the result
		$this->renderedPageInfoObj = new RenderedHTTPPageInfo();

		// Setup a new wait function
		$this->setupPromiseProxyResolver();
	}

	/**
	 * @return RenderedHTTPPageInfo
	 */
	public function getRenderedPageInfoObj(): RenderedHTTPPageInfo
	{
		return $this->renderedPageInfoObj;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
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
			function( PageInfoProcess $process ) use ( &$successCheckCallback ) {

				$obj = $this->tempFileJsonToObj();
				$this->renderedPageInfoObj = new RenderedHTTPPageInfo( $obj );

				// Check for a cert error
				if ( $this->renderedPageInfoObj->hasCertError() ) {
					throw new HttpResponseException( $this->renderedPageInfoObj->getLastCertError() );
				}

				// Was the initial request successful?
				if ( $process->renderedPageInfoObj->isOk() )
				{
					// Test for another property to determine if really successful.
					// Throw an exception here to reject the promise
					if ( is_callable( $successCheckCallback ) ) {
						call_user_func( $successCheckCallback, $process->renderedPageInfoObj );
					}
				} else {
					// The response was not ok.
					// Throw an exception to reject the promise
					throw new HttpResponseException( "Didn't get a 2XX response. Got {$process->renderedPageInfoObj->getStatus()}" );
				}
		} );
	}
}
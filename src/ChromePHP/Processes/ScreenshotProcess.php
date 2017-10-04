<?php
/**
 * ChromePHP - ScreenshotProcess.php
 * Created by: Eric Draken
 * Date: 2017/10/2
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Processes;

use Draken\ChromePHP\Emulations\Emulation;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Draken\ChromePHP\Processes\Response\RenderedHTTPPageInfo;
use Draken\ChromePHP\Utils\Paths;

class ScreenshotProcess extends PageInfoProcess
{
	/** @var Emulation[]  */
	private $emulations = [];

	/**
	 * ScreenshotProcess constructor.
	 *
	 * @param string $url
	 * @param array $args
	 * @param Emulation[] $emulations
	 * @param int $timeout
	 */
	public function __construct( string $url, array $args = [], array $emulations, $timeout = 10 )
	{
		// Set the device emulations to screenshot. Several may be supplied
		if ( ! count( $emulations ) ) {
			throw new InvalidArgumentException( "Emulations array is empty. Supply at least one page emulation" );
		}
		$this->emulations = $emulations;
		assert( $emulations[0] instanceof Emulation );

		// Filter out any reserved params supplied by the user
		$args = array_filter( $args, function ( $arg ) {
			return
				stripos( $arg, '--emulations=' ) !== 0 &&
				stripos( $arg, '--vmcode=' ) !== 0;
		} );

		// Convert the emulations array to JSON this way
		// to use the toString method which already returns JSON.
		$emuJson = '['.implode(',', $emulations ).']';

		// Merge the args array with the mandatory arguments
		parent::__construct($url, array_merge($args, [
			'--emulations='.$emuJson,
			'--vmcode='.file_get_contents( Paths::getNodeScriptsPath() . '/screenshot.vm.js' )
		]), $emulations[0], $timeout);

		// Setup a new wait function with an additional success check function
		$this->setupPromiseProxyResolver( [ $this, 'checkScreenshotFitness' ] );
	}

	/**
	 * @param RenderedHTTPPageInfo $renderedPageInfoObj
	 */
	protected function checkScreenshotFitness( RenderedHTTPPageInfo $renderedPageInfoObj )
	{
		$results = $renderedPageInfoObj->getVmcodeResults();
		if ( ! is_array ( $results ) || ! count( $results ) ) {
			throw new RuntimeException( 'No screenshots were saved' );
		}

		foreach ( $results as $result )
		{
			// Confirm required properties
			if ( ! property_exists( $result, 'filepath' ) ) {
				throw new RuntimeException( 'Missing filepath property from results object' );
			}

			if ( ! property_exists( $result, 'emulation' ) ) {
				throw new RuntimeException( 'Missing emulation object from results object' );
			}

			// Confirm temp screenshot exists
			if ( ! file_exists( $result->{'filepath'} ) ) {
				throw new RuntimeException( "Supposedly saved screenshot {$result->{'filepath'}} doesn't exist" );
			}

			// Confirm dimensions
			list($width, $height) = getimagesize( $result->{'filepath'} );
			$expectedWidth = $result->{'emulation'}->{'viewport'}->{'width'};
			if ( $width !== $expectedWidth ) {
				throw new RuntimeException( "Expected width doesn't match actual width: $expectedWidth vs $width" );
			}

			$expectedHeight = $result->{'emulation'}->{'viewport'}->{'height'};
			if ( $height !== $expectedHeight ) {
				throw new RuntimeException( "Expected height doesn't match actual height: $expectedHeight vs $height" );
			}
		}
	}
}
<?php
/**
 * ChromePHP - screenshot.php
 * Created by: Eric Draken
 * Date: 2017/10/11
 * Copyright (c) 2017
 */

use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Emulations\Devices\IPhone6Emulation;
use Draken\ChromePHP\Processes\ScreenshotProcess;
use Draken\ChromePHP\Queue\ProcessQueue;

require_once __DIR__ . '/../vendor/autoload.php';

// Chrome process manager with a 2-browser limit
$manager = new ChromeProcessManager(9222, 2 );

// An emulation or list of emulations must be supplied
// in order to set the viewport and scaling
$emulation = new IPhone6Emulation();

// Specialized NodeJS process to visit a website
// and take a screenshot of it
$process = new ScreenshotProcess('https://github.com', [ $emulation ] );

// Enqueue the process
$promise = $manager->enqueue( $process );

// Each process returns a promise, and here
// is where further processing can be done, for example,
// more processes can be added to the queue here
$promise->then( function ( ScreenshotProcess $process ) use ( &$buffer ) {

	// Display logs
	$logs = $process->getErrorOutput();
	print_r( $logs );

	// 2XX response
	$screenshots = $process->getScreenshots();
	$screenshotInfo = current( $screenshots );
	$tempFile = $screenshotInfo->getFilepath();
	$buffer = file_get_contents( $tempFile );

}, function ( ScreenshotProcess $failedProcess ) {

	// 4XX - 5XX response or timeout
	var_dump( $failedProcess->getLastException() );

} );

// The Chrome process manager returns a promise
$manager->then( function ( ProcessQueue $queue ) use ( &$buffer )
{
	// All processes succeeded so display the screenshot
	if ( !empty( $buffer ) )
	{
		header('Content-Type: image/png');
		echo $buffer;
	}


}, function ( array $results ) {

	// Some processes failed
	echo PHP_EOL . "Error: " . $results[1]->getMessage() . PHP_EOL;

} );

// Start processing
$manager->run( function() {
	// This is a 'tick' callback which can be
	// used to display progress
} );

// At this point, all the Chrome processors will shut down, and their
// temporary folders will be deleted. All screenshots in the temp folders
// will also be deleted as well. Make sure any screenshots to be saved
// have been copied to a permanent location in one of the above promise callbacks

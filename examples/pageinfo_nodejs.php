<?php
/**
 * ChromePHP - pageinfo_nodejs.php
 * Created by: Eric Draken
 * Date: 2017/10/11
 * Copyright (c) 2017
 */

use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Processes\PageInfoProcess;
use Draken\ChromePHP\Queue\ProcessQueue;

require_once __DIR__ . '/../vendor/autoload.php';

// Chrome process manager with a 2-browser limit
$manager = new ChromeProcessManager(9222, 2 );

// Additional NodeJS code to run after the page info has been collected.
// From here, automated browser testing can take place, for example.
// This example executes a page method to retrieve the document title
$code = <<<'JS'

(async () => { 
    let title = await page.evaluate( 'document.title' );
    console.log(title);
})();

JS;

// Specialized NodeJS process to visit a website
// and return detailed information about it. By adding '--vmcode'
// as an argument, this code will run in the context of
// the same browser page
// Set '--ignorecerterrors=1' to ignore TLS certificate errors
$process = new PageInfoProcess('https://github.com', [
	'--vmcode=' . $code,
	'--ignorecerterrors=0'
]);

// Enable logging to see a trace of all network
// and page activity
$process->setEnv([
	'LOG_LEVEL' => 'debug'
]);

// Enqueue the process
$promise = $manager->enqueue( $process );

// Each process returns a promise, and here
// is where further processing can be done, for example,
// more processes can be added to the queue here
$promise->then( function ( PageInfoProcess $process ) {

	// 2XX response
	// NOTE: All debugging and console output happens
	// on stderr because the stdout is reserved for
	// returning the page info JSON results file path
	print_r( $process->getErrorOutput() );

}, function ( PageInfoProcess $failedProcess ) {

	// 4XX - 5XX response or timeout
	var_dump( $failedProcess->getLastException() );

} );

// The Chrome process manager returns a promise
$manager->then( function ( ProcessQueue $queue )
{
	// All processes succeeded
	echo PHP_EOL . "Finished, no errors";

}, function ( array $results ) {

	// Some processes failed
	echo PHP_EOL . "Error: " . $results[1]->getMessage() . PHP_EOL;

} );

// Start processing
$manager->run( function() {
	// This is a 'tick' callback which can be
	// used to display progress
	echo '.';
} );

// At this point, all the Chrome processors will shut down, and their
// temporary folders will be deleted. Chrome processes and temp folders
// will stop and be deleted automatically on process failure as well

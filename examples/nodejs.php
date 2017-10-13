<?php
/**
 * ChromePHP - nodejs.php
 * Created by: Eric Draken
 * Date: 2017/10/11
 * Copyright (c) 2017
 */

use Draken\ChromePHP\Core\ChromeProcessManager;
use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Queue\ProcessQueue;

require_once __DIR__ . '/../vendor/autoload.php';

// Chrome process manager with a 2-browser limit
$manager = new ChromeProcessManager(9222, 2 );

// Create a node process from a string. A JavaScript file will be created
// and executed in the context of a browser page. Console operations will be
// buffered to the process output (stdout --> getOutput(), stderr --> getErrorOutput() ).
// There will be some globals available as well. See /src/ChromePHP/Core/NodeProcess.php:215
// fot all the available globals. If a file path is passed in as the first argument, then
// no globals are prepared beforehand
$process = new NodeProcess( <<<'JS'

// Testing
console.log( 'Chrome port: ' + chrome.port );
console.log( 'WebSocket DevTools endpoint: ' + chrome.wsep );
console.log( 'Temp path: ' + chrome.temp );

JS
);

// Enqueue the process
$promise = $manager->enqueue( $process );

// Each process returns a promise, and here
// is where further processing can be done, for example,
// more processes can be added to the queue here
$promise->then( function ( NodeProcess $process ) {

	print_r( $logs );

	// 2XX response
	var_dump( $process->getOutput() );

}, function ( NodeProcess $failedProcess ) {

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

<?php
/**
 * ChromePHP - pageinfo_quick.php
 * Created by: Eric Draken
 * Date: 2017/10/19
 * Copyright (c) 2017
 */

use Draken\ChromePHP\Processes\PageInfoProcess;

require_once __DIR__ . '/../vendor/autoload.php';

// Quick way to launch a Chrome instance and run the process
( new PageInfoProcess( 'https://github.com' ) )->runInChrome( 9222, function ( PageInfoProcess $process ) {
	echo $process->getRenderedPageInfoObj()->getStatus();
} );
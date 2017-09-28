<?php
/**
 * ChromePHP - bootstrap.php
 * Created by: Eric Draken
 * Date: 2017/9/21
 * Copyright (c) 2017
 */

/**
 * PHPUnit Bootstrap
 *
 * Registers the Composer autoloader
 */
call_user_func( function ()
{
	if ( ! is_file( $autoloadFile = realpath( __DIR__ . '/../vendor/autoload.php' ) ) )
	{
		throw new \RuntimeException( 'Did not find vendor/autoload.php. Did you run "composer install --dev"?' );
	}

	/** @noinspection PhpIncludeInspection */
	require_once $autoloadFile;
} );

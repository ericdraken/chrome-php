<?php
/**
 * ChromePHP - ProcessFailedException.php
 * Created by: Eric Draken
 * Date: 2017/9/8
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Exceptions;

use Draken\ChromePHP\Core\LoggableBase;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException as SymfonyProcessFailedException;

class ProcessFailedException extends SymfonyProcessFailedException
{
	public function __construct( Process $process )
	{
		parent::__construct( $process );

		// Log the error message
		LoggableBase::logger()->error( $this->getMessage() );
	}
}
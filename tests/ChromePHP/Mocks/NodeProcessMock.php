<?php
/**
 * ChromePHP - NodeProcessMock.php
 * Created by: Eric Draken
 * Date: 2017/9/22
 * Copyright (c) 2017
 */

namespace DrakenTest\ChromePHP\Mocks;

use Draken\ChromePHP\Core\NodeProcess;
use Draken\ChromePHP\Queue\PromiseProcess;

class NodeProcessMock extends NodeProcess
{
	/** @noinspection PhpMissingParentConstructorInspection
	 * @param string $command
	 * @param array $args
	 * @param int $timeout
	 * @param bool $inspect
	 */
	public function __construct( $command = 'echo test; return 0', array $args = [], $timeout = 300, $inspect = false )
	{
		// Skip the NodeProcess constructor
		return PromiseProcess::__construct( $command, null, null, null, $timeout );
	}

	public function run( $callback = null )
	{
		if ( empty( $this->getCommandLine() ) ) {
			throw new \RuntimeException( "Command line is empty" );
		}

		// Skip the NodeProcess command building code
		return PromiseProcess::run( $callback );
	}

	public function start( callable $callback = null )
	{
		if ( empty( $this->getCommandLine() ) ) {
			throw new \RuntimeException( "Command line is empty" );
		}

		// Skip the NodeProcess command building code
		return PromiseProcess::start( $callback );
	}

	public function mustRun( callable $callback = null )
	{
		if ( empty( $this->getCommandLine() ) ) {
			throw new \RuntimeException( "Command line is empty" );
		}

		// Skip the NodeProcess command building code
		return PromiseProcess::mustRun( $callback );
	}
}
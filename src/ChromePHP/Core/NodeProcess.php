<?php
/**
 * ChromePHP - NodeProcess.php
 * Created by: Eric Draken
 * Date: 2017/9/15
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Core;


use Draken\ChromePHP\Commands\LinuxCommands;
use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use Draken\ChromePHP\Exceptions\RuntimeException;
use Draken\ChromePHP\Queue\PromiseProcess;
use Draken\ChromePHP\Utils\Paths;
use Draken\ChromePHP\Utils\Utils;
use GuzzleHttp\Promise\PromiseInterface;

class NodeProcess extends PromiseProcess
{
	/** @var int */
	private $assignedPort = 0;

	/** @var int */
	private $initialDelay = 0;

	/** @var string */
	private $assignedWsEndpointUrl = '';

	/** @var string */
	private $assignedWorkingFolder = '';

	/** @var string */
	private $nodeScriptPath = '';

	/** @var string */
	private $tempScriptPath = '';

	/** @var string */
	private $nodeScriptString = '';

	/** @var int */
	private $timeout = 0;

	/** @var bool */
	private $inspectMode = false;

	/** @var bool */
	private $pipeStderrToStdout = false;

	/** @var array */
	private $userScriptArgs = [];

	/** @var array */
	private $cmdParams = [
		'inspect' => '--inspect-brk=0.0.0.0:9229',
		'port' => '--chrome-port=',
		'host' => '--chrome-host=',
		'wsep' => '--chrome-wsep=',
		'temp' => '--chrome-temp=',
	];

	/**
	 * NodeProcess constructor.
	 * Create a special PromiseProcess that takes either a NodeJS script path,
	 * or the script contents itself, and returns a Process that can
	 * connect to a running Chrome instance
	 *
	 * @param string $script
	 * @param array $args
	 * @param int $timeout
	 * @param bool $inspect
	 * @param bool $pipeStderrToStdout
	 */
	public function __construct(
		string $script = null,
		array $args = [],
		int $timeout = 300,
		bool $inspect = false,
		bool $pipeStderrToStdout = false
	) {
		if ( $timeout < 0 ) {
			$timeout = 0;
		}

		if ( $script )
		{
			// Check if the $script is a path or the script to execute
			if ( Utils::strposa( $script, [PHP_EOL, '(', '{', '[', ';'] ) ) {
				// This is probably a script string
				$this->setNodeScriptString( $script );
			} else if ( file_exists( $script ) ) {
				// This is a file path
				$this->nodeScriptPath = $script;
			} else {
				throw new InvalidArgumentException( "Unknown script argument. File not found ($script), and it doesn't look like script contents" );
			}
		}

		$this->inspectMode = $inspect;
		$this->userScriptArgs = $args;
		$this->timeout = $timeout;
		$this->pipeStderrToStdout = $pipeStderrToStdout;

		// Register shutdown function to cleanup any temp files.
		// Register an anonymous function to avoid
		// "Warning: (Registered shutdown functions) Unable to call"
		// messages during PHPUnit tests
		register_shutdown_function( function () {
			$this->cleanup();
		} );

		parent::__construct( '' );
	}

	/**
	 * Do not call this directly
	 * @param array|string $commandline
	 *
	 * @return \Symfony\Component\Process\Process|void
	 * @throws RuntimeException
	 */
	public function setCommandLine( $commandline )
	{
		throw new RuntimeException( "Do not call setCommandLine() directly" );
	}

	/**
	 * @return int
	 */
	public function getAssignedPort(): int {
		return $this->assignedPort;
	}

	/**
	 * Assign a port to this Node process by way of
	 * setting the '--port=' parameter in the command
	 *
	 * @param int $assignedPort
	 */
	public function setAssignedPort( int $assignedPort )
	{
		// Verify port
		$port = intval($assignedPort, 10);
		if ($port <= 0) {
			throw new InvalidArgumentException("Assigned port must be greater than 0. Got $assignedPort");
		}

		$this->assignedPort = $assignedPort;
	}

	/**
	 * @param string $assignedWsEndpointUrl
	 */
	public function setAssignedWsEndpointUrl( string $assignedWsEndpointUrl )
	{
		// Verify endpoint
		$url = filter_var( $assignedWsEndpointUrl, FILTER_VALIDATE_URL );
		if ( ! $url || stripos( $assignedWsEndpointUrl, 'ws' ) !== 0 ) {
			throw new InvalidArgumentException( "WS endpoint URL is invalid. Got: '$assignedWsEndpointUrl'" );
		}

		$this->assignedWsEndpointUrl = $assignedWsEndpointUrl;
	}

	/**
	 * @return string
	 */
	public function getAssignedWsEndpointUrl(): string {
		return $this->assignedWsEndpointUrl;
	}

	/**
	 * @param string $assignedWorkingFolder
	 */
	public function setAssignedWorkingFolder( string $assignedWorkingFolder )
	{
		// Check existence
		if ( empty( $assignedWorkingFolder ) || ! is_dir( $assignedWorkingFolder ) ) {
			throw new InvalidArgumentException("Working folder doesn't exist: '$assignedWorkingFolder'");
		}

		$this->assignedWorkingFolder = realpath( $assignedWorkingFolder );
	}

	/**
	 * @return string
	 */
	public function getAssignedWorkingFolder(): string {
		return $this->assignedWorkingFolder;
	}

	/**
	 * Set the raw Node script to run. No additional variables
	 * will be available. It is up to the developer to extract
	 * additional params from the argv property
	 * @param string $nodeScriptString
	 */
	public function setRawNodeScriptString( string $nodeScriptString )	{
		$this->nodeScriptString = $nodeScriptString;
	}

	/**
	 * Set a low-level Node script to run. A few globals will be available to
	 * manually connect to a running Chrome instance with.
	 *
	 * Access them in the Node script like so:
	 *
	 * e.g. let endpoint = chrome.wsep;
	 *
	 * @param string $nodeScriptString
	 */
	public function setNodeScriptString( string $nodeScriptString )
	{
		$nodeModulesPath = Paths::getNodeModulesPath();
		$nodeScriptsPath = Paths::getNodeScriptsPath();
		$header = <<<EOT
'use strict';
(function() {
    let argv = require('$nodeModulesPath/minimist')(process.argv.slice(2));
    global.chrome = {
        wsep : argv['chrome-wsep'] || false,
        port : argv['chrome-port'] || 9222,
        host : argv['chrome-host'] || 'localhost',
        temp : argv['chrome-temp'] || '/tmp'
    };
    global.modules_path = '$nodeModulesPath';
    global.scripts_path = '$nodeScriptsPath';
})();

EOT;
		$this->nodeScriptString = $header . $nodeScriptString;
	}

	/**
	 * @return string
	 */
	public function getNodeScriptString(): string {
		return $this->nodeScriptString;
	}

	/**
	 * Set ENV variables for this process,
	 * and add some defaults like TZ
	 * @param array $env
	 *
	 * @return \Symfony\Component\Process\Process
	 */
	public function setEnv( array $env )
	{
		// Add default ENV variables
		$envDefaults = [
			'TZ' => date_default_timezone_get()
		];

		$env = array_merge( $envDefaults, $env );

		return parent::setEnv( $env );
	}

	/**
	 * Prepend a sleep command at the front of the process
	 * or replace an existing sleep command with a new one.
	 *
	 * @param float $delay_float_seconds
	 */
	public function setInitialDelay(float $delay_float_seconds)
	{
		if ( $this->isRunning() ) {
			throw new RuntimeException("Unable to delay a running process");
		}

		if ( $delay_float_seconds < 0 ) {
			$delay_float_seconds = 0;
		}

		if ( $delay_float_seconds > 60 ) {
			throw new InvalidArgumentException("Initial delay too long. Max allowed is 60s. Got {$delay_float_seconds}s");
		}

		$this->initialDelay = $delay_float_seconds;
	}

	/**
	 * Return a new NodeProcess from an unstarted state with the same
	 * promise handler, port and delay, but with a different unique id
	 * @return NodeProcess
	 */
	public function cloneCleanProcess()
	{
		if ($this->isRunning()) {
			throw new \RuntimeException('Process is already running');
		}
		$process = clone $this;

		// Assign another unique ID
		$process->uniqueId = uniqid();

		// Clear the last exception
		$process->lastException = null;

		// Update the process assigned to this promise proxy
		$this->promise->setProcess( $process );

		assert( $process->assignedPort > 0 );
		assert( $process->promise instanceof PromiseInterface );
		assert( $process->getTimeout() === $this->getTimeout() );

		return $process;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setTimeout( $timeout ) {
		$this->timeout = $timeout;
	}

	/**
	 * {@inheritdoc}
	 */
	public function start( callable $callback = null )
	{
		$this->checkSettings();
		$this->buildNodeCommand();
		return parent::start( $callback );
	}

	/**
	 * {@inheritdoc}
	 */
	public function run( $callback = null )
	{
		$this->checkSettings();
		$this->buildNodeCommand();
		return parent::run( $callback );
	}

	/**
	 * {@inheritdoc}
	 */
	public function mustRun( callable $callback = null )
	{
		$this->checkSettings();
		$this->buildNodeCommand();
		return parent::mustRun( $callback );
	}

	/**
	 * Check if this Node has a script and Chrome connection params set
	 * @throws RuntimeException
	 */
	private function checkSettings()
	{
		// Need a Chrome endpoint to connect to
		if ( empty( $this->assignedPort ) && empty( $this->assignedWsEndpointUrl ) ) {
			throw new RuntimeException("No Chrome connection details provided");
		}

		// Need something to execute
		if ( empty( $this->nodeScriptString ) && empty( $this->nodeScriptPath ) ) {
			throw new RuntimeException("No script path or string supplied. Nothing to do");
		}

		// Timeouts
		if ( $this->inspectMode ) {
			// No timeouts in inspector mode
			parent::setTimeout(0);
		} else {
			// Set the user-supplied process timeout
			parent::setTimeout( $this->timeout );
		}
	}

	/**
	 * Build and set the command to run in the Process
	 */
	private function buildNodeCommand()
	{
		// Hold command parts
		$cmdParts = [];

		// Inspector flag
		if ( $this->inspectMode ) {
			$cmdParts[] = $this->cmdParams['inspect'];
		}

		// Script path
		if ( $this->nodeScriptPath ) {
			$cmdParts[] = $this->nodeScriptPath;
		} else if ( ! empty( $this->nodeScriptString ) ) {
			$cmdParts[] = $this->nodeScriptToFile();
		} else {
			throw new RuntimeException("No script path or script string supplied");
		}

		// Add in user args
		foreach ( $this->userScriptArgs as $arg ) {
			$cmdParts[] = $arg;
		}

		// Chrome port
		if ( $this->assignedPort ) {
			$cmdParts[] = $this->cmdParams['port'] . $this->assignedPort;
		}

		// TODO: Implement this?
//		// Chrome host
//		if ( $this->assignedHost ) {
//			$cmdParts[] = $this->cmdParams['host'] . $this->assignedHost;
//		}

		// Add the working folder
		if ( $this->assignedWorkingFolder ) {
			$cmdParts[] = $this->cmdParams['temp'] . $this->assignedWorkingFolder;
		}

		// Add the WS endpoint URL
		if ( $this->assignedWsEndpointUrl ) {
			$cmdParts[] = $this->cmdParams['wsep'] . $this->assignedWsEndpointUrl;
		}

		// Create the command this way instead of using the
		// process builder because the nodeCmd must not be escaped
		$cmd = LinuxCommands::nodeCmd . ' ';
		$cmd .= implode(' ', array_map('escapeshellarg', $cmdParts ) );

		// Send all errors to stdout
		if ( $this->pipeStderrToStdout ) {
            $cmd .= ' 2>&1';
        }

		// Initial delay
		if ( $this->initialDelay > 0 ) {
			$cmd = "sleep {$this->initialDelay}; $cmd";
		}

		parent::setCommandLine( $cmd );
	}

	/**
	 * Convert the supplied node script into a JS file
	 * with a name tied to the uid of the process
	 */
	private function nodeScriptToFile()
	{
		// Just in case
		if ( empty( $tmp = $this->assignedWorkingFolder ) )
		{
			$tmp = sys_get_temp_dir() . '/nodejs';

			if ( ! file_exists( $tmp ) ) {
				mkdir( $tmp, 0777, true );
			}
		}

		$scriptPath = $tmp . DIRECTORY_SEPARATOR . $this->getUniqueId() . '.js';
		if ( ! file_exists( $scriptPath ) )
		{
			// Save the script
			file_put_contents( $scriptPath, $this->nodeScriptString );

			if( ! file_exists( $scriptPath ) ) {
				throw new RuntimeException( "Unable to save the script to '$scriptPath'" );
			}
		}

		// Keep a reference to the temp script file
		// so it can be deleted later
		$this->tempScriptPath = $scriptPath;

		return $scriptPath;
	}

	/**
	 * Unlink the temp script file if it was created
	 */
	public function cleanup()
	{
		if ( ! empty( $this->tempScriptPath ) && file_exists( $this->tempScriptPath ) )
		{
			unlink( $this->tempScriptPath );
		}
	}
}
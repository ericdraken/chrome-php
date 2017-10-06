<?php
/**
 * ChromePHP - PromiseProcess.php
 * Created by: Eric Draken
 * Date: 2017/9/10
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Queue;


use Draken\ChromePHP\Exceptions\RuntimeException;
use GuzzleHttp\Promise\Promise;
use Symfony\Component\Process\Process;

class PromiseProcess extends Process
{
	/** @var Promise */
	protected $promise;

	/** @var string */
	protected $uniqueId;

	/** @var \Exception */
	protected $lastException;

	/**
	 * PromiseProcess constructor.
	 * Create a new Process and create a new Promise along with it.
	 *
	 * @param array|string $commandline
	 * @param null         $cwd
	 * @param null         $env
	 * @param null         $input
	 * @param int|float    $timeout
	 * @param array|null   $options
	 */
	public function __construct(
		$commandline,
		$cwd = null,
		$env = null,
		$input = null,
		$timeout = 60,
		array $options = null
	) {
		parent::__construct( $commandline, $cwd, $env, $input, $timeout, $options );

		// Create a proxied promise that can
		// refer to the state of the process assigned to it
		// to reject or resolve the promise
		$this->promise = new PromiseProxy();
		$this->promise->setProcess( $this );

		// Unique ID of the process, started or not
		$this->uniqueId = uniqid();
	}

	/**
	 * Return a new PromiseProcess from an unstarted state with the same
	 * promise handler but a different unique id
	 * @return PromiseProcess
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

		return $process;
	}

	/**
	 * @return Promise
	 */
	public function getPromise() {
		return $this->promise;
	}

	/**
	 * @return string
	 */
	public function getUniqueId(): string {
		return $this->uniqueId;
	}

	/**
	 * @param \Exception $lastException
	 */
	public function setLastException( \Exception $lastException ) {
		$this->lastException = $lastException;
	}

	/**
	 * @return \Exception|null
	 */
	public function getLastException() {
		return $this->lastException;
	}
}
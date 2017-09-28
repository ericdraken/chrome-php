<?php
/**
 * ChromePHP - ChromeProcessorInfo.php
 * Created by: Eric Draken
 * Date: 2017/9/13
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Core;


class ChromeProcessorInfo
{
	/** @var ChromeProcess */
	private $chromeInstance;

	/** @var \Exception[]  */
	private $exceptions = [];

	/** @var NodeProcess */
	private $assignedProcess = false;

	/**
	 * ChromeProcessorInfo constructor.
	 *
	 * @param ChromeProcess $process
	 */
	function __construct( ChromeProcess $process ) {
		$this->chromeInstance = $process;
	}

	/**
	 * @return bool|int
	 */
	public function getPort() {
		return $this->chromeInstance->getPort();
	}

	/**
	 * @return ChromeProcess
	 */
	public function getChromeInstance(): ChromeProcess {
		return $this->chromeInstance;
	}

	/**
	 * @param ChromeProcess $chromeInstance
	 */
	public function setChromeInstance( ChromeProcess $chromeInstance ) {
		$this->chromeInstance = $chromeInstance;
	}

	/**
	 * @return \Exception[]
	 */
	public function getExceptions(): array {
		return $this->exceptions;
	}

	/**
	 * @return int
	 */
	public function getNumExceptions(): int {
		return count( $this->exceptions );
	}

	/**
	 * @param \Exception $exception
	 */
	public function addException( \Exception $exception ) {
		$this->exceptions[] = $exception;
	}

	/**
	 * @return bool
	 */
	public function hasAssignedProcess(): bool {
		return !!$this->assignedProcess;
	}

	/**
	 * @param NodeProcess $process
	 *
	 * @return bool
	 */
	public function hasProcess( NodeProcess $process ): bool {
		return $this->hasAssignedProcess() &&
		       $this->getAssignedProcess() === $process;
	}

	/**
	 * @return NodeProcess
	 */
	public function getAssignedProcess(): NodeProcess {
		return $this->assignedProcess;
	}

	/**
	 * @param NodeProcess $assignedProcess
	 */
	public function assignProcess( NodeProcess $assignedProcess ) {
		$this->assignedProcess = $assignedProcess;
	}

	/**
	 * Unassign the process if it was assigned
	 */
	public function unassignProcess() {
		$this->assignedProcess = false;
	}
}
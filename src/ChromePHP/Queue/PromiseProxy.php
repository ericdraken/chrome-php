<?php
/**
 * ChromePHP - PromiseProxy.php
 * Created by: Eric Draken
 * Date: 2017/9/16
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Queue;


use Draken\ChromePHP\Exceptions\RuntimeException;
use GuzzleHttp\Promise\Promise;
use Symfony\Component\Process\Process;

/**
 * Class PromiseProxy
 * When a PromiseProcess is cloned for a fresh restart, say
 * to try the process again, it should hold on to the original
 * promise. The promise wait function must be set at instantiation
 * time, so a normal that wait function would always be tied to
 * the original process. By using a proxy, the process can be updated
 *
 * @package Draken\ChromePHP\Queue
 */
class PromiseProxy extends Promise
{
	/** @var Process */
	private $process;

	/**
	 * PromiseProxy constructor.
	 *
	 * @param callable|null $waitFn
	 * @param callable|null $cancelFn
	 */
	public function __construct(
		callable $waitFn = null,
		callable $cancelFn = null
	) {

		if ( ! is_callable( $waitFn ) ) {
			$waitFn = [$this, 'waitFn'];
		}

		parent::__construct( $waitFn, $cancelFn );
	}

	/**
	 * Wait function to synchronously resolve promises
	 */
	protected function waitFn()
	{
		if ( ! $this->process instanceof Process ) {
			throw new RuntimeException("A Process must be assigned to this Promise");
		}

		if ($this->process->isStarted()) {
			$this->process->wait();
		}

		$this->process->isSuccessful()
			? $this->resolve( $this->process )    // Fulfill
			: $this->reject( $this->process );    // Reject
	}

	/**
	 * @param Process $process
	 */
	public function setProcess( Process $process ) {
		$this->process = $process;
	}
}
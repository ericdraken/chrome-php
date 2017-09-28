<?php
/**
 * ChromePHP - NullPromiseProcess.php
 * Created by: Eric Draken
 * Date: 2017/9/10
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Queue;

use GuzzleHttp\Promise\Promise;
use Symfony\Component\Process\Process;

/**
 * Class NullPromiseProcess
 *
 * @author Edward Pfremmer <epfremme@nerdery.com>
 */
class NullPromiseProcess extends Process
{
	/**
	 * {@inheritdoc}
	 */
	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct()
	{
		// do nothing
	}

	/**
	 * {@inheritdoc}
	 */
	public function start(Callable $callback = null)
	{
		// do nothing
	}

	/**
	 * {@inheritdoc}
	 */
	public function run($callback = null)
	{
		return 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function mustRun( callable $callback = null )
	{
		return 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function wait(Callable $callback = null)
	{
		return 0;
	}

	/**
	 * @return Promise
	 */
	public function getPromise() {
		return null;
	}

	/**
	 * @return string
	 */
	public function getUniqueId(): string {
		return 0;
	}
}

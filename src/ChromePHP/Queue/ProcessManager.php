<?php
/**
 * ChromePHP - ProcessManager.php
 * Created by: Eric Draken
 * Date: 2017/9/10
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Queue;

use Draken\ChromePHP\Exceptions\InvalidArgumentException;
use GuzzleHttp\Promise\Promise;
use Symfony\Component\Process\Process;
use Draken\ChromePHP\Core\LoggableBase;

/**
 * Class ProcessManager
 *
 */
class ProcessManager extends LoggableBase
{
    /** @var ProcessQueue */
    protected $processQueue;

    /**
     * ProcessFactory constructor
     *
     * @param null $parallelizationLimit
     */
    public function __construct($parallelizationLimit = null)
    {
        $this->processQueue = new ProcessQueue($parallelizationLimit);
    }

	/**
	 * Return the raw process queue
	 *
	 * @return ProcessQueue
	 */
	public function getProcessQueue(): ProcessQueue {
		return $this->processQueue;
	}

	/**
	 * Get the number of Processes still in the queue
	 *
	 * @return int
	 */
	public function count(): int {
		return $this->getProcessQueue()->count();
	}

	/**
	 * If this method returns TRUE, then the process will be
	 * restarted afresh and its promise handler will not be called.
	 * Use this to restart processes that are waiting for some system
	 * resource to come available, like Chrome remote debugger.
	 *
	 * This should be called by an inherited class
	 *
	 * @param Callable $completionHook
	 */
	protected function setQueueCompletionHook( Callable $completionHook ) {
		$this->processQueue->setCompletionHook( $completionHook );
	}

	/**
	 * @param Process $process
	 *
	 * @return Promise
	 */
    public function enqueue(Process $process): Promise
    {
    	if ( ! $process instanceof Process ) {
    		throw new InvalidArgumentException("Argument is not a type of Process. Got " . gettype($process) );
	    }

    	if ( $process->isRunning() ) {
    		throw new InvalidArgumentException("Cannot enqueue a running process");
	    }

	    if ( $process->isTerminated() ) {
		    throw new InvalidArgumentException("Cannot enqueue a terminated process");
	    }

	    if ( ! $process instanceof PromiseProcess )
	    {
		    $pp = new PromiseProcess(
			    $process->getCommandLine(),
			    $process->getWorkingDirectory(),
			    $process->getEnv(),
			    $process->getInput(),
			    $process->getTimeout()
		    );

		    // Remove the original
		    unset( $process );

		    $this->processQueue->add($pp);
		    return $pp->getPromise();
	    }

	    $this->processQueue->add($process);
	    return $process->getPromise();
    }

	/**
	 * @param \Closure|null $tick
	 */
    public function run(\Closure $tick = null)
    {
        $queue = $this->processQueue;

        /** @var PromiseProcess $next */
        foreach ($queue() as $next)
        {
            $next->start();
            $tick && $tick();
        }
    }
}

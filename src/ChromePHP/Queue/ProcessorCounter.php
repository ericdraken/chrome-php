<?php
/**
 * ChromePHP - ProcessorCounter.php
 * Created by: Eric Draken
 * Date: 2017/9/10
 * Copyright (c) 2017
 * Original author: Edward Pfremmer <epfremme@nerdery.com>
 */

namespace Draken\ChromePHP\Queue;

/**
 * Class ProcessorCounter
 */
class ProcessorCounter
{
    const DEFAULT_CPU_COUNT = 4;

    /**
     * @var int
     */
    private $cpuCount;

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getCpuCount();
    }

    /**
     * @return int
     */
    public function getCpuCount()
    {
        if ($this->cpuCount) {
            return $this->cpuCount;
        }

        if ($this->hasProcInfo()) {
            return $this->getProcCount();
        }

        if ($this->isWindows()) {
            return $this->getWinCount();
        }

        return $this->getSysctlCount();
    }

    /**
     * @return bool
     */
    private function hasProcInfo()
    {
        return is_file('/proc/cpuinfo');
    }

    /**
     * @return bool
     */
    private function isWindows()
    {
        return 'WIN' == strtoupper(substr(PHP_OS, 0, 3));
    }

    /**
     * @return int
     */
    private function getSysctlCount()
    {
        $process = @popen('sysctl -a', 'rb');

        if (false !== $process) {
            $output = stream_get_contents($process);

            preg_match('/hw.ncpu: (\d+)/', $output, $matches);
            pclose($process);
        }

        return !empty($matches) ? intval($matches[1][0]) : self::DEFAULT_CPU_COUNT;
    }

    /**
     * @return int
     */
    private function getProcCount()
    {
        $cpuinfo = file_get_contents('/proc/cpuinfo');

        preg_match_all('/^processor/m', $cpuinfo, $matches);

        return !empty($matches[0]) ? count($matches[0]) : self::DEFAULT_CPU_COUNT;
    }

    /**
     * @return int
     */
    private function getWinCount()
    {
        $process = @popen('wmic cpu get NumberOfCores', 'rb');

        if (false !== $process) {
            fgets($process);
            $count = intval(fgets($process));
            pclose($process);
        }

        return !empty($count) ? $count : self::DEFAULT_CPU_COUNT;
    }
}

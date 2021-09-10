<?php

namespace WindBridges\ProcessMessaging;


use Closure;
use Exception;
use Generator;
use InvalidArgumentException;

class ProcessPool
{
    protected ?Closure $generator = null;
    protected ?Generator $iterator = null;
    protected int $concurrency = 1;
    protected array $activeProcesses = [];
    protected bool $isRunning = false;
    protected bool $mustStop = false;
    protected float $polling = 0.3;

    protected ?Closure $onProcessStarted = null;
    protected ?Closure $onProcessFinished = null;

    public function __construct(Closure $generator = null)
    {
        $generator && $this->setGenerator($generator);
    }

    function setGenerator(?Closure $generator)
    {
        $this->generator = $generator;
    }

    function getGenerator(): Closure
    {
        return $this->generator ?: function () { };
    }

    /**
     * Concurrency can be adjusted during runtime
     *
     * @param int $concurrency
     */
    public function setConcurrency(int $concurrency): void
    {
        if ($concurrency < 1) {
            throw new InvalidArgumentException('Concurrency limit must be greater than 0');
        }

        $this->concurrency = $concurrency;
    }

    function isRunning(): bool
    {
        return $this->isRunning;
    }

    function run()
    {
        $this->start();
        $this->wait();
    }

    function start()
    {
        $this->iterator = null;
        $this->isRunning = true;
        $this->startInstances();
    }

    function wait()
    {
        $pollingMs = $this->polling * 1000000;

        while (true) {
            $this->tick();

            if ($this->isRunning()) {
                usleep($pollingMs);
            } else {
                break;
            }
        }
    }

    /**
     * Disables launch of new processes.
     * You should call wait() to wait while currently running processes are completed.
     * Use stop() to immediately kill all running processes.
     */
    function finish()
    {
        $this->mustStop = true;
    }

    /**
     * Kills all processes. You should call wait() to wait while all stopped.
     *
     * @param float $timeout
     * @param int|null $signal
     */
    function stop(float $timeout = 10, int $signal = null)
    {
        foreach ($this->activeProcesses as $process) {
            if ($process->isRunning()) {
                $process->stop($timeout, $signal);
            }
        }
    }

    function getProcessCount(): int
    {
        $count = 0;

        if ($this->isRunning) {
            // We don't use $concurrency here because it can be altered during runtime
            $maxId = max(array_keys($this->activeProcesses));

            for ($i = 0; $i <= $maxId; $i++) {

                $process = $this->activeProcesses[$i] ?? null;

                if ($process) {
                    if (!$process->isTerminated()) {
                        $count++;
                    } else {
                        if ($this->onProcessFinished) {
                            // Callback can restart process and return new one
                            $_process = call_user_func($this->onProcessFinished, $process);

                            if ($_process) {
                                if ($_process instanceof Process && $_process->isRunning()) {
                                    // If restarted, then update process list
                                    $this->activeProcesses[$i] = $_process;
                                    $count++;
                                    continue;
                                } else {
                                    throw new Exception("onProcessFinished() handler must return Process instance or null");
                                }
                            }
                        }

                        unset($this->activeProcesses[$i]);
                    }
                }
            }
        }

        return $count;
    }

    function tick()
    {
        if ($this->isRunning) {
            $count = $this->getProcessCount();
            $started = $this->startInstances();

            if (!$started && !$count) {
                $this->isRunning = false;
            }
        }
    }

    function onProcessStarted(Closure $handler = null)
    {
        $this->onProcessStarted = $handler;
    }

    /**
     * Handler is called when process is shut down (both success and error).
     * Status of process can be checked by $process->isSuccessful().
     * To restart failed process use:
     *      return $process->restart();
     * It is important to return result of restart() from the handler, since it
     * returns new Process object.
     *
     * @param Closure|null $handler function(Process $process)
     */
    function onProcessFinished(Closure $handler = null)
    {
        $this->onProcessFinished = $handler;
    }

    private function startInstances(): bool
    {
        if ($this->mustStop) {
            return false;
        }

        for ($i = 0; $i < $this->concurrency; $i++) {
            if (!($this->activeProcesses[$i] ?? null)) {
                $process = $this->getProcess();

                if ($process) {
                    $process->start();
                    $this->activeProcesses[$i] = $process;
                    $this->onProcessStarted && call_user_func($this->onProcessStarted, $process);
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    private function getProcess(): ?Process
    {
        if (!$this->iterator) {
            $this->iterator = $this->getGenerator()();
        } else {
            $this->iterator->next();
        }

        if ($this->iterator->valid()) {
            $process = $this->iterator->current();

            if (!$process instanceof Process) {
                throw new InvalidArgumentException("Generator must return object of " . Process::class);
            }

            return $process;
        } else {
            return null;
        }
    }
}
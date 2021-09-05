<?php

namespace WindBridges\ProcessMessaging;


use Closure;
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
        $this->iterator = $this->getGenerator()();
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

        if($this->isRunning) {
            // Don't use $concurrency here because it can be altered during runtime
            $maxId = max(array_keys($this->activeProcesses));

            for ($i = 0; $i <= $maxId; $i++) {
                $process = $this->activeProcesses[$i] ?? null;

                if ($process) {
                    if (!$process->isTerminated()) {
                        $count++;
                    } else {
                        unset($this->activeProcesses[$i]);
                    }
                }
            }
        }

        return $count;
    }

    function tick()
    {
        if($this->isRunning) {
            $count = $this->getProcessCount();

            if (!$this->startInstances() && !$count) {
                $this->isRunning = false;
            }
        }
    }

    private function startInstances(): bool
    {
        if ($this->mustStop || !$this->iterator->valid()) {
            return false;
        }

        for ($i = 0; $i < $this->concurrency; $i++) {
            if (!($this->activeProcesses[$i] ?? null) && $this->iterator->valid()) {
                /** @var Process $process */
                $process = $this->iterator->current();
                $this->iterator->next();

                if (!$process instanceof Process) {
                    throw new InvalidArgumentException("Generator must return object of " . Process::class);
                }

                $process->start();
                $this->activeProcesses[$i] = $process;
            }
        }

        return true;
    }
}
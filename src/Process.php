<?php

namespace WindBridges\ProcessMessaging;


use Exception;

class Process extends \Symfony\Component\Process\Process
{
    protected $onMessage;
    protected $onOutput;
    protected $onException;

    public function onMessage(callable $handler)
    {
        $this->onMessage = $handler;
    }

    public function onOutput(callable $handler)
    {
        $this->onOutput = $handler;
    }

    public function onException(callable $handler)
    {
        $this->onException = $handler;
    }

    public function start(callable $callback = null, array $env = [])
    {
        return parent::start(function ($type, $buffer) use($callback) {
            if (trim($buffer)) {
                $lines = explode("\n", $buffer);

                foreach ($lines as $line) {
                    $line = trim($line);

                    if ($line) {
                        $message = $this->unserializeMessage($line);

                        if($type == Process::ERR) {
                            $this->onException && call_user_func($this->onException, $message->getObject());
                        } else {
                            if ($message->getType() == Message::TYPE_ECHO && $this->onOutput) {
                                call_user_func($this->onOutput, $message->getObject());
                            } elseif ($message->getType() == Message::TYPE_MESSAGE && $this->onMessage) {
                                call_user_func($this->onMessage, $message->getObject());
                            }
                        }
                    }
                }
            }
        }, $env);
    }

    protected function unserializeMessage(string $line): Message
    {
        $decoded = base64_decode($line);
        $message = unserialize($decoded);

        if (!$message instanceof Message) {
            throw new Exception('Error unserializing process message');
        }

        return $message;
    }
}
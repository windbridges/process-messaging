<?php

namespace WindBridges\ProcessMessaging;


use Exception;
use Throwable;
use Webmozart\Assert\Assert;
use WindBridges\Parser\Event\EchoEvent;

class Process extends \Symfony\Component\Process\Process
{
    protected $tag;
    protected $onMessage;
    protected $onOutput;
    protected $onException;
    /** @var SerializerInterface */
    private $serializer;

    public function __construct($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60, array $options = [])
    {
        $this->serializer = new Serializer();
        $this->onException = function (Throwable $ex) {
            throw $ex;
        };
        parent::__construct($commandline, $cwd, $env, $input, $timeout);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function setTag(string $tag)
    {
        $this->tag = $tag;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function onMessage(callable $handler)
    {
        $this->onMessage = $handler;
    }

    public function onEcho(callable $handler)
    {
        $this->onOutput = $handler;
    }

    public function onException(callable $handler)
    {
        $this->onException = $handler;
    }

    public function start(callable $callback = null, array $env = [])
    {
        parent::start(function ($type, $buffer) use($callback) {
            if (trim($buffer)) {
                $lines = explode("\n", $buffer);

                foreach ($lines as $line) {
                    $line = trim($line);

                    if ($line) {
                        $message = $this->unserializeMessage($line, $lines);

                        if($type == Process::ERR) {
                            $this->onException && call_user_func($this->onException, $message->getObject());
                        } else {
                            $msgType = $message->getType();

                            if ($msgType == Message::TYPE_ECHO && $this->onOutput) {
                                call_user_func($this->onOutput, $message->getObject());
                            } elseif ($msgType == Message::TYPE_MESSAGE && $this->onMessage) {
                                if ($message->getObject() instanceof EchoEvent) {
                                    dump($message->getObject()); // ?
                                }
                                call_user_func($this->onMessage, $message->getObject());
                            } elseif ($msgType == Message::TYPE_EXCEPTION) {
                                call_user_func($this->onException, $message->getObject());
                            } else {
                                throw new Exception('Process received unknown message type: ' . $msgType);
                            }
                        }
                    }
                }
            }

            $callback && $callback($type, $buffer);
        }, $env);
    }

    protected function unserializeMessage(string $line, array $allLines): Message
    {
        try {
            $message = $this->serializer->unserialize($line);
        } catch (Exception $exception) {
            $serializerClass = get_class($this->serializer);
            throw new Exception("Error decoding process message in '{$this->tag}' with {$serializerClass}: {$exception->getMessage()}\nBuffer contents: " . join("\n", $allLines));
        }

        Assert::isInstanceOf($message, Message::class);

        return $message;
    }
}
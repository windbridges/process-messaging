<?php

namespace WindBridges\ProcessMessaging;

use Throwable;

class ProcessMessaging
{
    static protected $echoHandlerInstalled = false;
    static protected $exceptionHandlerInstalled = false;
    static protected $serializer;

    static function handleOutput()
    {
        self::handleEcho();
        self::handleExceptions();
    }

    static function send($object)
    {
        self::sendMessage(Message::TYPE_MESSAGE, $object);
    }

    static function sendException(Throwable $exception)
    {
        $serializableException = $exception instanceof SerializableException
            ? $exception : new SerializableException($exception);
        self::sendMessage(Message::TYPE_EXCEPTION, $serializableException, STDERR);
    }

    static function getSerializer(): SerializerInterface
    {
        if (!self::$serializer) {
            self::$serializer = new Serializer();
        }

        return self::$serializer;
    }

    static function setSerializer(SerializerInterface $serializer)
    {
        self::$serializer = $serializer;
    }

    static protected function sendMessage(string $type, $object, $output = STDOUT)
    {
        $message = new Message($type, $object);
        $serialized = self::getSerializer()->serialize($message);
        fwrite($output, $serialized . "\n");
    }

    static protected function handleEcho()
    {
        if(!self::$echoHandlerInstalled) {
            ob_start(function ($buffer) {
                if(strlen($buffer)) {
                    self::sendMessage(Message::TYPE_ECHO, $buffer);
                }
            }, 1);

            self::$echoHandlerInstalled = true;
        }
    }

    static protected function handleExceptions()
    {
        if(!self::$exceptionHandlerInstalled) {
            set_exception_handler(function (Throwable $exception) {
                self::sendException($exception);
            });

            self::$exceptionHandlerInstalled = true;
        }
    }

}
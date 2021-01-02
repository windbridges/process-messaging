<?php

namespace WindBridges\ProcessMessaging;

use Throwable;

class ProcessMessaging
{
    static protected $echoHandlerInstalled = false;
    static protected $ExceptionHandlerInstalled = false;

    static function handleOutput()
    {
        self::handleEcho();
        self::handleExceptions();
    }

    static function send($object)
    {
        self::sendMessage(Message::TYPE_MESSAGE, $object);
    }

    static protected function sendMessage(string $type, $object, $output = STDOUT)
    {
        $message = new Message($type, $object);
        $serialized = serialize($message);
        $encoded = base64_encode($serialized);
        fwrite($output, $encoded . "\n");
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
        if(!self::$ExceptionHandlerInstalled) {
            set_exception_handler(function (Throwable $exception) {
                self::sendMessage(Message::TYPE_EXCEPTION, $exception, STDERR);
            });

            self::$ExceptionHandlerInstalled = true;
        }
    }

}
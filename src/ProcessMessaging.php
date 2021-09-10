<?php

namespace WindBridges\ProcessMessaging;

use ErrorException;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;
use Throwable;

class ProcessMessaging
{
    static protected bool $handleMessages = false;
    static protected bool $echoHandlerInstalled = false;
    static protected bool $exceptionHandlerInstalled = false;
    static protected ?SerializerInterface $serializer = null;

    static function handleAll()
    {
        self::handleMessages();
        self::handleOutput();
    }

    static function handleOutput()
    {
        self::handleEcho();
        self::handleExceptions();
    }

    static function handleMessages()
    {
        self::$handleMessages = true;
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

        exit(1);
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

        if (self::$handleMessages) {
            $serialized = self::getSerializer()->serialize($message);
            fwrite($output, $serialized . "\n");
        } else {
            $data = print_r($message->export(), true);
            fwrite($output, $data . "\n");
        }
    }

    static protected function handleEcho()
    {
        if (!self::$echoHandlerInstalled) {
            $handler = function ($buffer) {
                if (strlen($buffer)) {
                    self::sendMessage(Message::TYPE_ECHO, $buffer);
                }
            };

            ob_start($handler, 1);

            if (class_exists(VarDumper::class)) {
                VarDumper::setHandler(function ($var) use ($handler) {
                    $cloner = new VarCloner();
                    $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
                    $dumper = new CliDumper();
                    $output = '';
                    $dumper->dump($cloner->cloneVar($var), function ($line, $depth) use (&$output, $handler) {
                        if ($depth >= 0) {
                            $output .= str_repeat('  ', $depth) . $line . "\n";
                        }
                    });
                    $handler($output);
                });
            }

            self::$echoHandlerInstalled = true;
        }
    }

    static protected function handleExceptions()
    {
        if (!self::$exceptionHandlerInstalled) {
            set_exception_handler(function (Throwable $exception) {
                self::sendException($exception);
            });

            set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext) {
                $exception = new ErrorException($errstr, 0, $errno, $errfile, $errline);
                throw new SerializableException($exception);
            });

            self::$exceptionHandlerInstalled = true;
        }
    }
}
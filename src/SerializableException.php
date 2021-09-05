<?php

namespace WindBridges\ProcessMessaging;


use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Serializable;
use Throwable;

class SerializableException extends Exception implements Serializable
{
    protected $class;
    protected $message;
    protected $file;
    protected $line;
    protected $code;
    public $trace;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(Throwable $exception)
    {
        $this->class = get_class($exception);
        $this->message = sprintf('(%s) %s', $this->class, $exception->getMessage());
        $this->file = $exception->getFile();
        $this->line = $exception->getLine();
        $this->code = $exception->getCode();

        $trace = [];

        foreach ($exception->getTrace() as $item) {
            if ($item['args'] ?? null) {
                if (is_object($item)) {
                    $item['args'] = get_class($item);
                } elseif (is_array($item)) {
                    $item['args'] = [];
                } else {
                    $item['args'] = gettype($item);
                }
            }

            $trace[] = $item;
        }

        $this->trace = $trace;
    }

    public function export(): array
    {
        return [
            'class' => $this->class,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'code' => $this->code,
            'trace' => $this->trace,
        ];
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function serialize(): string
    {
        $trace = [];

        foreach ($this->trace as $item) {
            if ($item['args'] ?? null) {
                if (is_object($item)) {
                    $item['args'] = get_class($item);
                } elseif (is_array($item)) {
                    $item['args'] = [];
                } else {
                    $item['args'] = gettype($item);
                }
            }

            $trace[] = $item;
        }

        return serialize([
            'class' => $this->class,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'code' => $this->code,
            'trace' => $trace
        ]);
    }

    public function unserialize($serialized)
    {
        $serialized = unserialize($serialized);
        $this->class = $serialized['class'];
        $this->message = $serialized['message'];
        $this->file = $serialized['file'];
        $this->line = $serialized['line'];
        $this->code = $serialized['code'];
        $this->trace = $serialized['trace'];

        // Do some magic to allow Exception's final methods return serialized values
        $obj = (new ReflectionObject($this))->getParentClass();
        $this->overrideProperty($obj, 'message', $this->message);
        $this->overrideProperty($obj, 'file', $this->file);
        $this->overrideProperty($obj, 'line', $this->line);
        $this->overrideProperty($obj, 'code', $this->code);
        $this->overrideProperty($obj, 'trace', $this->trace);
    }

    private function overrideProperty(ReflectionClass $object, string $prop, $value)
    {
        $prop = $object->getProperty($prop);
        $prop->setAccessible(true);
        $prop->setValue($this, $value);
    }
}
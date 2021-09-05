<?php
namespace WindBridges\ProcessMessaging;

use Exception;
use Serializable;

class Message implements Serializable
{
    const TYPE_ECHO = 'echo';
    const TYPE_MESSAGE = 'message';
    const TYPE_EXCEPTION = 'exception';

    private $type;
    private $object;

    public function __construct(string $type, $object)
    {
        $this->type = $type;
        $this->object = $object;
    }

    public function export(): array
    {
        return [
            'type' => $this->type,
            'object' => $this->object,
        ];
    }

    public function serialize()
    {
        return serialize([
            $this->type,
            $this->object
        ]);
    }

    public function unserialize($serialized)
    {
        $errorReporting = error_reporting(error_reporting() ^ E_NOTICE);
        $data = unserialize($serialized);
        error_reporting($errorReporting);

        if (!$data && $err = error_get_last()) {
            throw new Exception($err['message']);
        }

        $this->type = $data[0];
        $this->object = $data[1];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getObject()
    {
        return $this->object;
    }


}
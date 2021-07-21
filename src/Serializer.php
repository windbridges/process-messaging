<?php

namespace WindBridges\ProcessMessaging;


use Exception;
use Webmozart\Assert\Assert;
use WindBridges\Parser\Event\Bot\BotStartedEvent;

class Serializer implements SerializerInterface
{

    function serialize(Message $message): string
    {
        $serialized = serialize($message);
        return base64_encode($serialized);
    }

    function unserialize(string $serialized): Message
    {
        error_clear_last();
        $prevErrLevel = error_reporting(error_reporting() ^ E_NOTICE);
        $decoded = base64_decode($serialized);

        if ($err = error_get_last()) {
            throw new Exception($err['message'] . ' (decode error)');
        }

        /** @var Message $message */
        $message = unserialize($decoded);
        error_reporting($prevErrLevel);

        if ($err = error_get_last()) {
            $error = error_get_last()['message'];
            throw new Exception($error . ' (unserialize error)');
        }

        Assert::isInstanceOf($message, Message::class);

        return $message;
    }
}
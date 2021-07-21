<?php

namespace WindBridges\ProcessMessaging;


interface SerializerInterface
{
    function serialize(Message $message): string;

    function unserialize(string $serialized): Message;
}
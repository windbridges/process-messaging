<?php

use WindBridges\ProcessMessaging\Message;
use WindBridges\ProcessMessaging\Process;
use PHPUnit\Framework\TestCase;

class ProcessWithMessagingTest extends TestCase
{
    function testEcho()
    {
        $cmd = ['php', __DIR__ . '/scripts/echo1.php'];

        $proc = new Process($cmd);

        $proc->onOutput(function (string $buffer) {
            if ($buffer == 'Echo message text') {
                $this->addToAssertionCount(1);
            }
        });

        $proc->run();
    }

    function testMultilineEcho()
    {
        $cmd = ['php', __DIR__ . '/scripts/echo2.php'];

        $proc = new Process($cmd);
        $output = [];

        $proc->onOutput(function (string $buffer) use (&$output) {
            $output[] = $buffer;
        });

        $proc->run();

        $this->assertEquals([
            "Echo message text line 1\n",
            "Echo message text line 2\n",
            "Echo message text line 3\n",
        ], $output);
    }

    function testCustomMessage()
    {
        $cmd = ['php', __DIR__ . '/scripts/echo3.php'];

        $proc = new Process($cmd);

        $proc->onMessage(function ($object) {
            $this->assertEquals([1,2,3], $object);
        });

        $proc->run();
    }

    function testExceptionMessage()
    {
        $cmd = ['php', __DIR__ . '/scripts/echo4.php'];

        $proc = new Process($cmd);

        $proc->onException(function (Throwable $exception)  {
            $this->assertEquals('Test exception', $exception->getMessage());
        });

        $proc->run();
    }

    function testSerializeMessage()
    {
        $msg = new Message(Message::TYPE_MESSAGE, [1, 2, 3]);
        $serialized = serialize($msg);
        /** @var Message $msg */
        $msg = unserialize($serialized);
        $this->assertEquals(Message::TYPE_MESSAGE, $msg->getType());
        $this->assertEquals([1,2,3], $msg->getObject());
    }
}

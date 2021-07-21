### Installation
```
composer require windbridges/process-messaging
```

### About

This package helps to communicate with child processes. Create child processes with Symfony Process (https://github.com/symfony/process) and catch message objects back instead of parsing plain output or using files/sockets/db as a mediator.

- Catch messages from every `echo` of child process
- Catch custom messages sent with `ProcessMessaging::send`
- Catch exceptions thrown from child script

### Usage

##### Handling echo

After calling `ProcessMessaging::handleOutput()`, every echo output serialized into a message which can be unserialized in the parent PHP-process. 

```php
// child.php

use WindBridges\ProcessMessaging\ProcessMessaging;

require "vendor/autoload.php";

ProcessMessaging::handleOutput();

echo 'Echo message text';
```

```php
// parent.php
use WindBridges\ProcessMessaging\Process;

require "vendor/autoload.php";

$proc = new Process(['php', 'child.php']);

$proc->onEcho(function (string $buffer) {
    // $buffer contains 'Echo message text' here 
    echo "Output from parent: $buffer\n";
});

$proc->run();

```

**Note!** If you didn't call `ProcessMessaging::handleOutput()` before any output sent, it will break the execution because parent process will try to unserialize the plain output.  

##### Sending custom messages

The main feature of the package is to send custom messages. They can contain scalar data, arrays, objects or anything except resources and other things that cannot be serialized. 

```php
// child.php

use WindBridges\ProcessMessaging\ProcessMessaging;

require "vendor/autoload.php";

class MyObject
{
    protected $a;
}

ProcessMessaging::send('A string');
ProcessMessaging::send([1, 2, 3]);
ProcessMessaging::send(new MyObject());
// ...
```

```php
// parent.php
use WindBridges\ProcessMessaging\Process;

require "vendor/autoload.php";

$proc = new Process(['php', 'child.php']);

$proc->onMessage(function ($object) {
    // Get your $object here 
    var_dump($object);
});

$proc->run();
```

##### Handling exceptions

Exceptions are automatically wrapped into `SerializableException` class to prevent serialization of the stack trace. The trace is stored as a result of `traceAsString()` method.

```php
// child.php

use WindBridges\ProcessMessaging\ProcessMessaging;

require "vendor/autoload.php";

ProcessMessaging::handleOutput();

throw new Exception('Test exception');
```

```php
// parent.php

use WindBridges\ProcessMessaging\Process;
use WindBridges\ProcessMessaging\SerializableException;

require "vendor/autoload.php";

$proc = new Process(['php', 'child.php']);

$proc->onException(function (SerializableException $exception)  {
    echo $exception->getMessage();
});

$proc->run();

```
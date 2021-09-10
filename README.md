### Installation

```
composer require windbridges/process-messaging
```

### About

This package helps to communicate with child processes. Child process writes serialized messages to the STDOUT
using `ProcessMessaging::send()`, and parent process catches these messages using `WindBridges\ProcessMessaging\Process`
which is a wrapper around Symfony Process (https://github.com/symfony/process). `ProcessMessaging` is also catches
any `echo` and supports Symfony's `VarDumper` output (as well as `dump`/`dd` output).

- Catch messages from every `echo` of child process
- Catch custom messages sent with `ProcessMessaging::send`
- Catch exceptions thrown from child script

### Usage

##### Handling echo

After calling `ProcessMessaging::handleAll()`, every echo output serialized into a message which can be unserialized
in the parent PHP-process.

```php
// child.php

use WindBridges\ProcessMessaging\ProcessMessaging;

require "vendor/autoload.php";

ProcessMessaging::handleAll();

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

**Note!** If you didn't call `ProcessMessaging::handleAll()` before any output sent, it will break the execution
because parent process will try to unserialize the plain output. However, it can be useful to run child script manually during the development. For example:

```php
// child.php

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use WindBridges\ProcessMessaging\ProcessMessaging;

require "vendor/autoload.php";

// Let's set PROCESS_MESSAGING env variable when creating process to understand if we launched child from parent or manually
if (getenv('PROCESS_MESSAGING')) {
    // Child is launched from parent process,
    // so call handleAll() to serialize messages
    ProcessMessaging::handleAll();
    // And read input data from STDIN
    $data = json_decode(fgets(STDIN), true);
} else {
    // If we are here, then we launched the child manually,
    // so don't call handleAll(), and read input from options
    // (or any other way you want)  
    $input = new ArgvInput($argv, new InputDefinition([
        new InputArgument('n1', InputArgument::REQUIRED)
    ]));

    $data = [
        'n1' => $input->getArgument('n1')
    ];
}
```

##### Sending custom messages

The main feature of the package is to send custom messages. They can contain scalar data, arrays, objects or anything
except resources and other things that cannot be serialized.

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

Exceptions are automatically wrapped into `SerializableException` class to prevent serialization of the stack trace. The
trace is stored as a result of `traceAsString()` method.

```php
// child.php

use WindBridges\ProcessMessaging\ProcessMessaging;

require "vendor/autoload.php";

ProcessMessaging::handleAll();

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

#### Process Pool

Using Process Pool we can run multiple processes with the limited concurrency:

```php
// parent.php

use WindBridges\ProcessMessaging\Process;
use WindBridges\ProcessMessaging\ProcessPool;

require "vendor/autoload.php";

$process = new Process(
    ['php', 'child.php'], // commandline
    null,
    ['CHILD_PROC' => true], // tell to the child that it launched from parent 
    null,
    86400 // process timeout
); 

$process->setTag('Child');

$data = [1, 2, 3, 4, 5];

$pool = new ProcessPool(function () use($process, $data) {
    // spawn separate process for each data item
    foreach($data as $n) {
        $process = clone $process;
        $process->setInput(json_encode(['n' => $n]));
        yield $process;    
    }
});

$pool->setConcurrency(5);
$pool->run();
```

```php
// child.php

require "vendor/autoload.php";


```
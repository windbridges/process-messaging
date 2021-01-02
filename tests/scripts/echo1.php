<?php

use WindBridges\ProcessMessaging\ProcessMessaging;

require __DIR__ . "/../../vendor/autoload.php";

ProcessMessaging::handleOutput();

echo 'Echo message text';

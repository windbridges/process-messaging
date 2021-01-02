<?php

use WindBridges\ProcessMessaging\ProcessMessaging;

require __DIR__ . "/../../vendor/autoload.php";

ProcessMessaging::handleOutput();

throw new Exception('Test exception');
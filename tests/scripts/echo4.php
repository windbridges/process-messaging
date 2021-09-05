<?php

use WindBridges\ProcessMessaging\ProcessMessaging;

require __DIR__ . "/../../vendor/autoload.php";

ProcessMessaging::handleAll();

throw new Exception('Test exception');
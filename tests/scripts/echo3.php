<?php

use WindBridges\ProcessMessaging\ProcessMessaging;

require __DIR__ . "/../../vendor/autoload.php";

ProcessMessaging::send([1, 2, 3]);
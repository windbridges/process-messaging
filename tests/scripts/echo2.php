<?php

use WindBridges\ProcessMessaging\ProcessMessaging;

require __DIR__ . "/../../vendor/autoload.php";

ProcessMessaging::handleOutput();

echo "Echo message text line 1\n";
echo "Echo message text line 2\n";
echo "Echo message text line 3\n";

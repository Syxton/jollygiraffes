<?php
require 'vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

//echo "Public Key:\n" . $keys['publicKey'] . "\n\n";
//echo "Private Key:\n" . $keys['privateKey'] . "\n\n";

// Save them
//file_put_contents('vapid.json', json_encode($keys, JSON_PRETTY_PRINT));
//echo "Saved to vapid.json\n";
<?php
/**
 * Author: Benedikt Bauer
 * Date: 18.11.2014
 * Time: 22:06
 */

require 'vendor/autoload.php';

$ironmq = new IronMQ();
$ironmq->ssl_verifypeer = false;
$messages = $ironmq->getMessages('archivedPrice', 999, 1);
$printable_messages = [];
foreach ($messages as $message) {
    $printable_messages []= json_decode($message->body);
}

var_dump($printable_messages);
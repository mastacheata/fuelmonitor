<?php
/**
 * Author: Benedikt Bauer
 * Date: 02.12.2014
 * Time: 23:31
 */

require 'vendor/autoload.php';

chdir(__DIR__);

$fm = new \Xenzilla\FuelMonitor\TankenTankenMonitor();
$fm->setCache();
$fm->setPreferences();
if ($fm->fetchPrices()) {
    $fm->notifyUsers();
}
//$fm->fetchPrices();
//$fm->notifyUsers();

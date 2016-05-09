<?php
/**
 * Author: Benedikt Bauer
 * Date: 02.12.2014
 * Time: 23:31
 */

require 'vendor/autoload.php';

$fm = new \Xenzilla\FuelMonitor\VollerTankMonitor();
$fm->setCache();
$fm->setPreferences();
if ($fm->fetchPrices()) {
    $fm->notifyUsers();
}
//$fm->fetchPrices();
//$fm->notifyUsers();

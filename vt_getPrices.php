<?php
/**
 * Author: Benedikt Bauer
 * Date: 02.12.2014
 * Time: 23:31
 */

require 'vendor/autoload.php';

file_put_contents(dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/log/cron.log', date('Y-m-d H:i:s').' Script executed'."\n", FILE_APPEND);

$fm = new \Xenzilla\FuelMonitor\VollerTankMonitor();
$fm->setCache();
$fm->setPreferences();
if ($fm->fetchPrices()) {
    $fm->notifyUsers();
}
//$fm->fetchPrices();
//$fm->notifyUsers();

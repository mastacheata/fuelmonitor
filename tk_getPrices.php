<?php
/**
 * Author: Benedikt Bauer
 * Date: 06.06.2016
 * Time: 23:35
 */

require 'vendor/autoload.php';

chdir(__DIR__);

$fm = new \Xenzilla\FuelMonitor\TankerKoenigMonitor();
$fm->setCache();
$fm->setPreferences();
if ($fm->fetchPrices()) {
    $fm->notifyUsers();
}

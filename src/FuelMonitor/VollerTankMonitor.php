<?php
/**
 * Author: Benedikt Bauer
 * Date: 10.01.2015
 * Time: 00:11
 */

namespace Xenzilla\FuelMonitor;

use SebastianBergmann\Exporter\Exception;

class VollerTankMonitor extends FuelMonitor {
    public function fetchPrices()
    {
        $notification = "";

        $client = new \GuzzleHttp\Client();
        $requests = [];
        foreach ($this->fuelTypes as $fuelName => $fuelId) {
            $request = $client->createRequest('POST', $this->baseURL, ['body' => [$this->location + ['fueltype' => $fuelId]]]);
            $client->send($request);
            $response = $client->send($request);
            $json = $response->json();
            $currentFuel = [];
            $comparePrice = 0.000;

            foreach ($json['full_result_set'] as $station) {
                if (array_key_exists($station['uid'], $this->idMap)) {
                    $currentFuel[$this->idMap[$station['uid']]] = floatval($station['price']);
                } elseif (array_key_exists('_'.$station['uid'], $this->idMap)) {
                    $comparePrice = floatval($station['price']);
                }
            }
            if (min($currentFuel) < $comparePrice) {
                $notification .= $fuelName." : ".min($currentFuel)." (".array_search(min($currentFuel), $currentFuel).") < ".$comparePrice."\n";
            }
            else {
                $notification .= $fuelName." : ".min($currentFuel)." (".array_search(min($currentFuel), $currentFuel).") > ".$comparePrice."\n";
            }
        }
        var_dump($notification);
    }
} 
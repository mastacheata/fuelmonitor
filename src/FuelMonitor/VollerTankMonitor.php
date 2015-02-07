<?php
/**
 * Author: Benedikt Bauer
 * Date: 10.01.2015
 * Time: 00:11
 */

namespace Xenzilla\FuelMonitor;

use GuzzleHttp\Client;

class VollerTankMonitor extends FuelMonitor {
    public function fetchPrices() {
        $client = new Client();

        foreach ($this->fuelTypes as $fuelName => $fuelId) {
            $request = $client->createRequest('POST', $this->baseURL, ['body' => [$this->location + ['fueltype' => $fuelId]]]);
            $response = $client->send($request);
            $json = $response->json();
            $comparePrice = 0.000;
            $currentFuel = [];

            foreach ($json['full_result_set'] as $station) {
                if (array_key_exists($station['uid'], $this->idMap)) {
                    $currentFuel[$this->idMap[$station['uid']]] = floatval($station['price']);
                } elseif (array_key_exists('_'.$station['uid'], $this->idMap)) {
                    $comparePrice = floatval($station['price']) + 0.020;
                }
            }

            $this->newPrices->$fuelName = $currentFuel;
            $this->comparePrices->$fuelName = $comparePrice;
            $this->minPrices[$fuelName] = $this->findCheapest($fuelName);
        }

        if (empty(array_filter($this->minPrices))) {
            $this->logger->addInfo('Prices unchanged', $this->minPrices);
            return false;
        }
        else {
            return true;
        }
    }
} 
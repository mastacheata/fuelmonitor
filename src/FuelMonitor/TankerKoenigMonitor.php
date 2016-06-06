<?php
/**
 * Created by PhpStorm.
 * User: benedikt
 * Date: 06.06.2016
 * Time: 22:46
 */

namespace Xenzilla\FuelMonitor;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

class TankerKoenigMonitor extends FuelMonitor
{
    protected $preferencesFile = 'preferences-tk.json';

    public function fetchPrices()
    {
        $container = [];
        $history = Middleware::history($container);

        $stack = HandlerStack::create();
        // Add the history middleware to the handler stack.
        $stack->push($history);

        $client = new Client(['handler' => $stack]);

        foreach ($this->fuelTypes as $fuelName => $fuelId) {
            $response = $client->request('GET', $this->baseURL, ['query' => array_merge($this->location, $this->extraParams, ['type' => $fuelId]), 'verify' => false]);

            $json = json_decode($response->getBody(), true);
            $comparePrice = 0.000;
            $currentFuel = [];

            if ($json['status'] !== 'ok') {
                $lastRequest = end($container);
                throw new BadResponseException($json['message'], $lastRequest->request, $lastRequest->response);
            }

            foreach ($json['stations'] as $station) {
                $station['id'] = strtoupper($station['id']);
                if (array_key_exists($station['id'], $this->idMap)) {
                    $currentFuel[$this->idMap[$station['id']]] = floatval($station['price']);
                } elseif (array_key_exists('_'.$station['id'], $this->idMap)) {
                    $comparePrice = floatval($station['price']) + 0.020;
                }
            }

            $this->newPrices->$fuelName = $currentFuel;
            $this->comparePrices->$fuelName = $comparePrice;
            $this->minPrices[$fuelName] = $this->findCheapest($fuelName);
        }

        if (empty(array_filter($this->minPrices))) {
            $this->logger->addInfo('Prices unchanged', ['newPrices' => (array) $this->newPrices, 'comparePrices' => (array) $this->comparePrices]);
            return false;
        }
        else {
            return true;
        }
    }
}
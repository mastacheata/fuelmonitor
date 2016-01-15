<?php
/**
 * Author: Benedikt Bauer
 * Date: 10.01.2015
 * Time: 00:11
 */

namespace Xenzilla\FuelMonitor;

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class CleverTankenMonitor extends FuelMonitor {

    protected $preferencesFile = 'preferences-ct.json';

    public function fetchPrices() {
        $pricesArray = [];
        $empty = true;
        $client = new Client();

        foreach ($this->fuelTypes as $fuelName => $fuelId) {
            $crawler = $client->request('GET', $this->baseURL.'?spritsorte=' . $fuelId.'&r='.$this->location['radius'].'&ort='.$this->location['ort']);

            $pricesArray[$fuelName] = [];
            $currentFuel = &$pricesArray[$fuelName];

            $crawler->filter('#main-content-fuel-station-list a .price-entry')->each(function (Crawler $node) use (&$currentFuel) {
                // TODO make home station configurable and optional
                if ($node->attr('id') != 'tankstelle-46554')
                {
                    $priceValue = '';
                    $priceNode = $node->filter('.price');
                    if ($priceNode->count() > 0) {
                        $priceValue = $priceNode->text();
                    }
                    $currentFuel[$node->attr('id')] = $priceValue;
                }
            });

            if (empty($currentFuel)) {
                $httpStatus = $client->getResponse()->getStatus();
                if ($httpStatus == 200) {
                    // Just an empty response
                    // TODO Log the actual error response
                    // => $crawler->filter('#main-center-column ul > li')->text();
                } else {
                    // TODO Log critical error
                    throw new \Exception('URLs changed');
                }
            } else {
                $empty = false;
            }
        }

        if (!$empty) {
/*            $this->cache->put('rawPrice', json_encode($pricesArray));
            $this->queue->postMessage('archivedPrice', json_encode(['timestamp' => date('Y-m-d H:i:s')] + $pricesArray), ['delay' => 300, 'expires_in' => 172800]);*/
            print_r($pricesArray);
        }
    }
} 

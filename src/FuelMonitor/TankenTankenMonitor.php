<?php
/**
 * Author: Benedikt Bauer
 * Date: 10.01.2015
 * Time: 00:11
 */

namespace Xenzilla\FuelMonitor;

use Symfony\Component\DomCrawler\Crawler;

class TankenTankenMonitor extends FuelMonitor {
    public function fetchPrices()
    {
        $pricesArray = [];
        $empty = true;

        foreach ($this->fuelTypes as $fuelName => $fuelId) {
            $crawler = $this->client->request('GET', str_replace('{fueltype}', $fuelId, $this->baseURL));

            $pricesArray[$fuelName] = [];
            $currentFuel = &$pricesArray[$fuelName];
            $crawler->filter('div[data-partial="resultlist"] > a')->each(function (Crawler $node) use (&$currentFuel) {
                // TODO make home station configurable and optional
                if (substr($node->attr('href'), 38) != 'EB6F6809-0745-4533-B253-50E761076D02') {
                    $priceValue = '';
                    $priceNode = $node->filter('div.price');

                    if ($priceNode->count() > 0) {
                        $priceValue = $priceNode->text();
                    }
                    $currentFuel[substr($node->attr('href'), 38)] = $priceValue;
                }
            });

            if (empty($currentFuel)) {
                $httpStatus = $this->client->getResponse()->getStatus();
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
            $this->cache->put('rawPrice', json_encode($pricesArray));
            $this->queue->postMessage('archivedPrice', json_encode(['timestamp' => date('Y-m-d H:i:s')] + $pricesArray), ['delay' => 300, 'expires_in' => 172800]);
            print_r($pricesArray);
        }
    }
} 
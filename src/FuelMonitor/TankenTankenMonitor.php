<?php
/**
 * Author: Benedikt Bauer
 * Date: 10.01.2015
 * Time: 00:11
 */

namespace Xenzilla\FuelMonitor;

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class TankenTankenMonitor extends FuelMonitor {
    public function fetchPrices() {
        $client = new Client();

        foreach ($this->fuelTypes as $fuelName => $fuelId) {
            $crawler = $client->request('GET', str_replace(array_merge(array_keys($this->location), ['{fueltype}']), array_merge(array_values($this->location), [$fuelId]), $this->baseURL));
            $comparePrice = 0.000;
            $currentFuel = [];

            $crawler->filter('div[data-partial="resultlist"] > a')->each(function (Crawler $node) use (&$currentFuel, &$comparePrice) {
                // TODO make home station configurable and optional
                $stationId = substr($node->attr('href'), 38);
                $priceValue = '';
                $priceNode = $node->filter('div.price');

                if ($priceNode->count() > 0) {
                    $priceValue = $priceNode->text();
                }
                else {
                    $this->logger->addError('Missing Price', ['station' => $this->idMap[$stationId], '']);
                }

                if (array_key_exists($stationId, $this->idMap)) {
                    $currentFuel[$this->idMap[$stationId]] = floatval($priceValue);
                }
                elseif (array_key_exists('_'.$stationId, $this->idMap)) {
                    $comparePrice = floatval($priceValue) + 0.020;
                }
            });

            if (empty($currentFuel)) {
                $httpStatus = $client->getResponse()->getStatus();
                if ($httpStatus == 200) {
                    // Empty response or the HTML structure changed
                    $this->logger->addAlert('HTML Structure changed', ['pricesblock' => $crawler->filter('#main-center-column ul > li')->text(), 'fullHTML' => $crawler->html()]);
                } else {
                    throw new \Exception('URLs changed');
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
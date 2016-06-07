<?php
/**
 * Created by PhpStorm.
 * User: benedikt
 * Date: 06.06.2016
 * Time: 22:46
 */

/**
 * This class consumes the API of Tankerkoenig.de (c) by Martin Kurz
 * creativecommons.tankerkoenig.de API is licensed under a
 * Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this
 * work. If not, see <http://creativecommons.org/licenses/by/4.0/>.
 * Full license text is included in the LICENSE file at the root of this repository and available free of charge at https://creativecommons.org/licenses/by/4.0/legalcode
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
        $response = $client->request('GET', $this->baseURL, ['query' => array_merge($this->location, $this->extraParams, ['type' => 'all']), 'verify' => false]);

        $json = json_decode($response->getBody(), true);
        if ($json['status'] !== 'ok') {
            $lastRequest = end($container);
            throw new BadResponseException($json['message'], $lastRequest->request, $lastRequest->response);
        }
        
        foreach ($this->fuelTypes as $fuelName => $fuelId) {
            $comparePrice = 0.000;
            $currentFuel = [];

            foreach ($json['stations'] as $station) {
                $station['id'] = strtoupper($station['id']);
                if (array_key_exists($station['id'], $this->idMap)) {
                    $currentFuel[$this->idMap[$station['id']]] = floatval($station[$fuelId]);
                } elseif (array_key_exists('_'.$station['id'], $this->idMap)) {
                    $comparePrice = floatval($station[$fuelId]) + 0.020;
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
<?php
/**
 * Author: Benedikt Bauer
 * Date: 20.11.2014
 * Time: 21:41
 */

namespace Xenzilla\FuelMonitor;

use GuzzleHttp\Client;
use IronMQ;
use IronCache;

abstract class FuelMonitor {

    /**
     * Message queue
     * @var IronMQ
     */
    protected $queue;

    /**
     * Message cache
     * @var IronCache
     */
    protected $cache;

    /**
     * HTML parser client
     * @var \Goutte\Client
     */
    protected $client;

    /**
     * Base URL for MTS website / API
     * @var string
     */
    protected $baseURL;

    /**
     * Array of FuelTypes
     * Keys are the names (i.e. Super_E10)
     * Values are the numerical codes (i.e. 5)
     * @var array
     */
    protected  $fuelTypes;

    /**
     * Array of location parameters for filtering results
     * @var array
     */
    protected $location;

    /**
     * Array of unique identifier to gas station name
     * Keys are the identifiers
     * Values are the names
     * @var array
     */
    protected $idMap;

    /**
     * Pushover API token and application name
     * @var array
     */
    private $pushoverDefaultParameters;

    /**
     * Array of changed prices to notify users of
     * @var array
     */
    private $minPrices;

    public $oldPrices = array();
    public $newPrices = array();

    public function __construct() {
        date_default_timezone_set('Europe/Berlin');
    }

    public function setQueue($queue) {
        $this->queue = $queue;
    }

    public function setCache($cache) {
        $this->cache = $cache;
    }

    public function setClient($client) {
        $this->client = $client;
    }

    public function setPreferences() {
        // Set a few sensible defaults
        $base_url = '';
        $fuel_types = [];
        $location = [];
        $id_map = [];
        $pushoverDefaultParameters = [];
        extract(json_decode(file_get_contents('preferences.json'), true), EXTR_IF_EXISTS);
        $this->baseURL = $base_url;
        $this->fuelTypes = $fuel_types;
        $this->pushoverDefaultParameters = $pushoverDefaultParameters;
        $this->idMap = $id_map;
        $this->location = $location;
    }

    public function setPrices($payload) {
        $this->newPrices = is_string($payload) ? json_decode($payload) : $payload;
        $this->oldPrices = json_decode($this->cache->get('oldPrice'));
    }

    public function setMinPrice($prices) {
        $this->minPrices = json_decode($prices, true);
    }



    public function findCheapest($fuelType) {
        if ($this->oldPrices->$fuelType != $this->newPrices->$fuelType) {
            $minOld = min(array_filter((array) $this->oldPrices->$fuelType));
            $minNew = min(array_filter((array) $this->newPrices->$fuelType));

            if ($minNew != $minOld) {
                echo $fuelType.': '.$minOld.' != '.$minNew."\n";
                return $fuelType.': '.$minNew;
            }  else {
                return null;
            }
        }
    }

    public function pushMessage($json) {
        $this->queue->postMessage('changedPrice', json_encode($json));
    }

    public function notifyUsers() {
        // TODO replace test_users by real users (here AND in sendPrices.worker)
        $users = json_decode(file_get_contents('test_users.json'));

        $client = new Client(['base_url' => 'https://api.pushover.net/1/']);
        foreach ($users as $user)
        {
            $userParameters = ['user' => $user->apikey];

            if (!empty($user->types)) {
                $userPrices = array_intersect_key($this->minPrices, array_flip($user->types));
                $userParameters['message'] = implode("\n", $userPrices);
                if (count($userPrices) === 1) {
                    $userParameters['url'] = $this->baseURL.'&spritsorte='.$this->fuelTypes[$user->types[0]];
                }
            } else {
                $userParameters['message'] = implode("\n", $this->minPrices);
            }

            $parameters = array_merge($this->pushoverDefaultParameters, $userParameters);
            // TODO catch Exception thrown by Guzzle if the request is errorneous
            $response = $client->post('messages.json', [
                'body' => $parameters,
            ]);

            echo $userParameters['message']."\n";

            if ($response->getStatusCode() === 200) {
                echo "Messages remaining: ".$response->getHeader('X-Limit-App-Remaining')."\n";
                echo "Will reset on ".date('Y-m-d H:i:s', $response->getHeader('X-Limit-App-Reset'));
            }
        }
    }
} 
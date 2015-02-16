<?php
/**
 * Author: Benedikt Bauer
 * Date: 20.11.2014
 * Time: 21:41
 */

namespace Xenzilla\FuelMonitor;

use GuzzleHttp\Client;
use Monolog\ErrorHandler;
use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RavenHandler;
use Raven_Client;
use Predis\Client as Redis;

abstract class FuelMonitor {

    /**
     * Message cache
     * @var Redis
     */
    protected $cache;

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
     * Array of changed prices to notify users of
     * @var array
     */
    protected $minPrices = array();

    /**
     * Prices to compare against for each fueltype
     * @var \stdClass
     */
    protected $comparePrices;

    /**
     * All new prices fetched from the WebService
     * @var \stdClass
     */
    protected $newPrices;

    /**
     * Monolog logging interface
     * @var \Monolog\Logger
     */
    protected $logger;

    /**
     * Pushover API token and application name
     * @var array
     */
    private $pushoverDefaultParameters;

    public function __construct() {
        date_default_timezone_set('Europe/Berlin');
        $this->logger = new Logger('sentry');
        ErrorHandler::register($this->logger);
        $this->logger->pushHandler(new ErrorLogHandler(), Logger::ERROR);
        $this->newPrices = new \stdClass();
        $this->comparePrices = new \stdClass();
    }

    public function setCache($cache = null) {
        if ($cache !== null) {
            $this->cache = $cache;
        }
        else {
            $this->cache = new Redis();
        }
    }

    public function setPreferences() {
        // Set a few sensible defaults
        $base_url = '';
        $fuel_types = [];
        $location = [];
        $id_map = [];
        $logging = [];
        $pushoverDefaultParameters = [];

        extract(json_decode(file_get_contents('preferences.json', true), true), EXTR_IF_EXISTS);

        $this->baseURL = $base_url;
        $this->fuelTypes = $fuel_types;
        $this->pushoverDefaultParameters = $pushoverDefaultParameters;
        $this->idMap = $id_map;
        $this->location = $location;

        $this->logger = new Logger('fuelmon-mts');

        if (!empty($logging)) {
            $level = Logger::ERROR;
            switch (strtolower($logging['level'])) {
                case 'debug':
                    $level = Logger::DEBUG;
                    break;
                case 'info':
                    $level = Logger::INFO;
                    break;
                case 'notice':
                    $level = Logger::NOTICE;
                    break;
                case 'warning':
                    $level = Logger::WARNING;
                    break;
                case 'error':
                    $level = Logger::ERROR;
                    break;
                case 'critical':
                    $level = Logger::CRITICAL;
                    break;
                case 'alert':
                    $level = Logger::ALERT;
                    break;
                case 'emergency':
                    $level = Logger::EMERGENCY;
                    break;
            }

            if (array_key_exists('raven', $logging)) {
                $raven = new Raven_Client($logging['raven']);
                $this->logger->pushHandler(new RavenHandler($raven, $level, $logging['bubble']));
            }
        }
    }

    protected function setCachedPrices($payload) {
        $this->cache->set('fuelmon_cachedPrices', json_encode($payload));
    }

    protected function getCachedPrices() {
        return json_decode($this->cache->get('fuelmon_cachedPrices'), true);
    }

    public function setPrices($payload) {
        $this->newPrices = is_string($payload) ? json_decode($payload) : $payload;
        $this->comparePrices = json_decode($this->cache->get('oldPrice'));
    }

    public function setMinPrice($prices) {
        $this->minPrices = json_decode($prices, true);
    }

    public function findCheapest($fuelType) {
        $comparePrice = $this->comparePrices->$fuelType;
        $minNew = min(array_filter($this->newPrices->$fuelType));
        $minNewStation = array_keys($this->newPrices->$fuelType, $minNew);

        if ($minNew != $comparePrice) {
            return array($minNewStation[0] => $minNew);
        }
        else {
            return null;
        }
    }

    public function notifyUsers() {
        $cachedPrices = $this->getCachedPrices();

        if ($cachedPrices != $this->minPrices) {
            $newCachedPrices = array();

            if (empty($cachedPrices)) {
                $this->logger->addError('Cache empty');
            }
            else {
                foreach($cachedPrices as $fuelType => $cachedStationPrice) {
                    if (array_values($cachedStationPrice) != array_values($this->minPrices[$fuelType])) {
                        $newCachedPrices[$fuelType] = $this->minPrices[$fuelType];
                    }
                }
            }

            if (!empty(array_filter($newCachedPrices)) || empty($cachedPrices)) {
                $this->setCachedPrices($newCachedPrices);
            }
            else {
                $this->logger->addInfo('Duplicate message', ['minPrices' => $this->minPrices, 'cachedPrices' => $cachedPrices, 'comparePrices' => (array) $this->comparePrices]);
                return;
            }
        }
        else {
            $this->logger->addInfo('Duplicate message', ['minPrices' => $this->minPrices, 'cachedPrices' => $cachedPrices, 'comparePrices' => (array) $this->comparePrices]);
            return;
        }

        $users = json_decode(file_get_contents('users.json', true));

        $client = new Client(['base_url' => 'https://api.pushover.net/1/']);
        foreach ($users as $user)
        {
            $userParameters = ['user' => $user->apikey];

            if (!empty($user->types)) {
                $userPrices = array_intersect_key(array_filter($this->minPrices), array_flip($user->types));
                array_walk($userPrices, function (&$stationPrice, $fuelType) {$stationPrice = $fuelType.': '.$stationPrice[key($stationPrice)].' ('.key($stationPrice).')';});
                $userParameters['message'] = implode("\n", $userPrices);
                if (count($userPrices) === 1) {
                    $userParameters['url'] = $this->baseURL.'&spritsorte='.$this->fuelTypes[$user->types[0]];
                }
            } else {
                $userPrices = array_filter($this->minPrices);
                array_walk($userPrices, function (&$stationPrice, $fuelType) {$stationPrice = $fuelType.': '.$stationPrice[key($stationPrice)].' ('.key($stationPrice).')';});
                $userParameters['message'] = implode("\n", $userPrices);
            }

            $parameters = array_merge($this->pushoverDefaultParameters, $userParameters);
            $response = $client->post('messages.json', [
                'body' => $parameters,
            ]);

            if ($response->getStatusCode() === 200) {
                $this->logger->addDebug('Message Push successful', $userParameters);
                $this->logger->addInfo('Pushover Limits', ['count' => $response->getHeader('X-Limit-App-Remaining'), 'reset' => date('Y-m-d H:i:s', $response->getHeader('X-Limit-App-Reset'))]);
            }
        }
    }

    abstract public function fetchPrices();
} 
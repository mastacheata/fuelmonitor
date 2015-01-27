<?php
/**
 * Author: Benedikt Bauer
 * Date: 20.11.2014
 * Time: 22:27
 */

namespace {
    $mockGetPayload = false;
}

namespace Xenzilla\FuelMonitor {

    function getPayload() {
        global $mockGetPayload;
        if (isset($mockGetPayload) && $mockGetPayload === true) {
            return json_decode(file_get_contents('C:\Users\extuser\Dropbox\devel\unversioned\fuelmon-mts\newPrices.json'));
        } else {
            return \getPayload();
        }
    }

    class PriceComparatorTest extends \PHPUnit_Framework_TestCase {

        public function setUp() {
            global $mockGetPayload;
            $mockGetPayload = false;
        }

        public function testInitializesVariables() {
            $ironMQ = $this->getMock('IronMQ');
            $ironMQ->method('getMessage')->willReturn(json_decode(file_get_contents('C:\Users\extuser\Dropbox\devel\unversioned\fuelmon-mts\oldPrices.json')));

        }
    }
}



 
<?php
/**
 * Author: Benedikt Bauer
 * Date: 20.11.2014
 * Time: 22:27
 */

namespace Xenzilla\FuelMonitor {

    class PriceComparatorTest extends \PHPUnit_Framework_TestCase {

        public function setUp() {
            global $mockGetPayload;
            $mockGetPayload = false;
        }

        public function testInitializesVariables() {
            $this->assertTrue(true);
        }
    }
}



 
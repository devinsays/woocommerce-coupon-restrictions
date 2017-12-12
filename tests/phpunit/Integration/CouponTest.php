<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use DevPress\WooCommerce\CouponRestrictions\Test\Framework\MockSession;
use WC_Helper_Coupon;
use WC_Helper_Product;

class CouponTest extends \WP_UnitTestCase {
	protected $coupon;

	public function setUp() {
		parent::setUp();
		$this->coupon = WC_Helper_Coupon::create_coupon();
		WC()->session = new MockSession();
	}

	public function test_unit_tests() {
		$tests = true;
		$this->assertTrue( $tests );
	}
}

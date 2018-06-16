<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

class Onboarding_Setup_Test extends WP_UnitTestCase {

	public function setUp() {
	}

	/**
	 * Checks that option version is set correctly.
	 */
	public function test_option_version() {
		$plugin = WC_Coupon_Restrictions();
		$options = get_option( 'woocommerce-coupon-restrictions', false );
		$this->assertEquals( $plugin->version, $options['version'] );
	}

}

<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use WP_UnitTestCase;

class Onboarding_Setup_Test extends WP_UnitTestCase {

	/**
	 * Checks that option version is set correctly.
	 */
	public function test_option_version() {
		$plugin = WC_Coupon_Restrictions();
		$option = get_option( 'woocommerce-coupon-restrictions', false );
		$this->assertEquals( $plugin->version, $option['version'] );
	}

}

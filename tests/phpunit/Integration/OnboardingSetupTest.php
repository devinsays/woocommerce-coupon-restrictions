<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

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

	/**
	 * Checks that onboarding transient is set.
	 */
	public function test_onboarding_transient_set() {
		WC_Coupon_Restrictions();
		$transient = get_transient( 'woocommerce-coupon-restrictions-activated' );
		$this->assertEquals( 1, $transient );
	}

	/**
	 * Checks upgrade routine from <= 1.6.2 to current.
	 */
	public function test_upgrade_routine() {
		$option['version'] = '1.6.2';
		update_option( 'woocommerce-coupon-restrictions', $option );

		// This will kick off the upgrade routine.
		$plugin = WC_Coupon_Restrictions();
		$plugin->init();

		$query_type = get_option( 'coupon_restrictions_customer_query', 'account' );
		$this->assertEquals( $query_type, 'accounts-orders' );

		delete_option( 'woocommerce-coupon-restrictions' );
	}
}

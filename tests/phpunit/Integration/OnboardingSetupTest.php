<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Coupon_Restrictions_Table;

class Onboarding_Setup_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// The upgrade routine runs dbDelta, which decides between CREATE and
		// ALTER by checking SHOW TABLES. That cannot see the temporary tables
		// the test framework would otherwise substitute.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// The schema changes only run for admin and WP CLI requests.
		set_current_screen( 'dashboard' );
	}

	/**
	 * Checks that option version is set correctly.
	 */
	public function test_option_version() {
		$plugin = WC_Coupon_Restrictions();
		$plugin->upgrade_routine();

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
	}

	public function tear_down() {
		WC_Coupon_Restrictions_Table::delete_table();
		delete_option( 'woocommerce-coupon-restrictions' );
		delete_transient( 'woocommerce-coupon-restrictions-updating' );

		set_current_screen( 'front' );

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		parent::tear_down();
	}
}

<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Coupon_Restrictions_Table;

class UpgradeRoutineTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// The test framework rewrites CREATE/DROP TABLE to TEMPORARY table
		// queries, but dbDelta decides between CREATE and ALTER by checking
		// SHOW TABLES, which cannot see temporary tables. Use real DDL for
		// these tests so the upgrade path behaves like production.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		WC_Coupon_Restrictions_Table::delete_table();
		delete_option( 'woocommerce-coupon-restrictions' );
	}

	/**
	 * The verification table should be created on install/upgrade so enhanced
	 * usage limits work for coupons created via REST API, CLI or import
	 * (not just the admin coupon save screen).
	 */
	public function test_upgrade_routine_creates_verification_table() {
		\WC_Coupon_Restrictions()->upgrade_routine();

		$this->assertTrue( WC_Coupon_Restrictions_Table::table_exists() );
	}

	/**
	 * The upgrade routine should also update the schema for existing installs
	 * when the plugin version changes.
	 */
	public function test_upgrade_routine_updates_schema_on_version_change() {
		global $wpdb;

		// Simulate an existing install with the old (index-less) schema.
		$table_name      = WC_Coupon_Restrictions_Table::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$wpdb->query(
			"CREATE TABLE {$table_name} (
			record_id mediumint(9) NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL,
			order_id bigint(20) UNSIGNED NOT NULL,
			coupon_code varchar(255) NOT NULL,
			email varchar(255) NOT NULL,
			ip varchar(15) NOT NULL,
			shipping_address varchar(255) NOT NULL,
			UNIQUE KEY record_id (record_id)
			) {$charset_collate};"
		);
		update_option( 'woocommerce-coupon-restrictions', array( 'version' => '2.0.0' ) );

		\WC_Coupon_Restrictions()->upgrade_routine();

		$indexes   = $wpdb->get_results( "SHOW INDEX FROM {$table_name}" );
		$key_names = wp_list_pluck( $indexes, 'Key_name' );
		$this->assertContains( 'coupon_ip', $key_names );

		// The ip column should now accommodate IPv6 addresses.
		$column = $wpdb->get_row( "SHOW COLUMNS FROM {$table_name} LIKE 'ip'" );
		$this->assertSame( 'varchar(45)', strtolower( $column->Type ) );
	}

	public function tear_down() {
		WC_Coupon_Restrictions_Table::delete_table();
		delete_option( 'woocommerce-coupon-restrictions' );

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		parent::tear_down();
	}
}

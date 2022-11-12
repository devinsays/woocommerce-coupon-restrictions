<?php
/**
 * WooCommerce Coupon Restrictions - Onboarding.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    1.5.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_Onboarding {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Gets the base file for plugin.
		$base = WC_Coupon_Restrictions::plugin_base();

		// Adds links for plugin on the plugin admin screen.
		add_filter( 'plugin_action_links_' . $base, array( $this, 'plugin_action_links' ) );

		// Transient is set in WC_Coupon_Restrictions->upgrade_routine()
		// when plugin is activated for the first time.
		if ( get_transient( 'woocommerce-coupon-restrictions-activated' ) ) {
			// Loads the notice script and dismiss notice script.
			add_action( 'admin_enqueue_scripts', array( $this, 'init_install_notice' ) );

			// Deletes the transient via query string (when user clicks to start onboarding).
			add_action( 'init', array( $this, 'dismiss_notice_via_query' ) );

			// Deletes the transient via ajax (when user dismisses notice).
			add_action( 'wp_ajax_wc_customer_coupons_dismiss_notice', array( $this, 'dismiss_notice_via_ajax' ) );
		}

		// Initialize the pointers for onboarding flow.
		add_action( 'admin_enqueue_scripts', array( $this, 'init_pointers_for_screen' ) );
	}

	/**
	 * Loads everything required to display and dismiss the install notice.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function init_install_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Display the onboarding notice.
		add_action( 'admin_notices', array( $this, 'install_notice' ) );

		// Loads jQuery if not already available.
		wp_enqueue_script( 'jquery-core' );

		// Inline script deletes the transient when notice is dismissed.
		$notice_dismiss_script = $this->install_notice_dismiss();
		wp_add_inline_script( 'jquery-core', $notice_dismiss_script );
	}

	/**
	 * Plugin action links.
	 *
	 * @since 1.5.0
	 *
	 * @param  array $links List of existing plugin action links.
	 * @return array List of modified plugin action links.
	 */
	public function plugin_action_links( $links ) {
		// URL for coupon screen onboarding
		$coupon_url  = admin_url( 'post-new.php?post_type=shop_coupon&woocommerce-coupon-restriction-pointers=1' );
		$setting_url = admin_url( 'admin.php?page=wc-settings' );

		$custom = array(
			'<a href="' . esc_url( $coupon_url ) . '">' . esc_html__( 'New Coupon', 'woocommerce-coupon-restrictions' ) . '</a>',
			'<a href="' . esc_url( $setting_url ) . '">' . esc_html__( 'Settings', 'woocommerce-coupon-restrictions' ) . '</a>',
			'<a href="https://devpress.com/products/woocommerce-coupon-restrictions/">' . esc_html__( 'Docs', 'woocommerce-coupon-restrictions' ) . '</a>',
		);
		$links  = array_merge( $custom, $links );
		return $links;
	}

	/**
	 * Deletes the admin notice transient if query string is present.
	 *
	 * @since 1.5.0
	 */
	public function dismiss_notice_via_query() {
		if (
			current_user_can( 'manage_options' ) &&
			isset( $_GET['woocommerce-coupon-restriction-pointers'] )
		) {
			delete_transient( 'woocommerce-coupon-restrictions-activated' );
		}
	}

	/**
	 * Deletes the admin notice transient via ajax.
	 *
	 * @since 1.5.0
	 */
	public function dismiss_notice_via_ajax() {
		if ( ! check_ajax_referer( 'wc_customer_coupons_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		$notice = delete_transient( 'woocommerce-coupon-restrictions-activated' );
		if ( $notice ) {
			wp_send_json_success();
			exit;
		}

		wp_send_json_error();
		exit;
	}

	/**
	 * Displays a welcome notice.
	 *
	 * @since 1.5.0
	 */
	public function install_notice() {
		if ( current_user_can( 'manage_options' ) ) {
			$url = admin_url( 'post-new.php?post_type=shop_coupon&woocommerce-coupon-restriction-pointers=1' );
			?>
			<div class="updated notice is-dismissible woocommerce-message" data-woocommerce-coupon-restrictions="true" style="border-left-color: #cc99c2">
				<p>
					<?php esc_html_e( 'WooCommerce Coupon Restrictions plugin activated.', 'woocommerce-coupon-restrictions' ); ?>
					<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'See how it works.', 'woocommerce-coupon-restrictions' ); ?></a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Allows admin notice to be dismissed via ajax.
	 *
	 * @access public
	 * @since  1.5.0
	 * @return void
	 */
	public function install_notice_dismiss() {
		return "
			( function ( window, $ ) {
				'use strict';
				$( document ).ready( function() {
					$( '.notice' ).on( 'click', '.notice-dismiss', function( event ) {
						var notice = event.delegateTarget.getAttribute( 'data-woocommerce-coupon-restrictions' );
						if ( ! notice ) {
							return;
						}
						$.ajax( {
							method: 'post',
							data: {
								nonce: '" . wp_create_nonce( 'wc_customer_coupons_nonce' ) . "',
								action: 'wc_customer_coupons_dismiss_notice'
							},
							url: ajaxurl
						} );
					} );
				} );
			} )( window, jQuery );
		";
	}

	/**
	 * Init pointers for screen.
	 *
	 * @since 1.5.0
	 */
	public function init_pointers_for_screen() {
		if (
			! isset( $_GET['woocommerce-coupon-restriction-pointers'] ) ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		$screen = get_current_screen();
		if ( 'shop_coupon' === $screen->id ) {
			$this->display_pointers( $this->get_pointers() );
		}
	}

	/**
	 * Defines all the pointers.
	 *
	 * @since 1.5.0
	 */
	public function get_pointers() {
		return array(
			'coupon-restrictions-panel' => array(
				'target'       => '#woocommerce-coupon-data .usage_restriction_options',
				'next'         => 'customer-restriction-type',
				'next_trigger' => array(
					'target' => '#title',
					'event'  => 'input',
				),
				'options'      => array(
					'content'  => '<h3>' . esc_html__( 'Usage Restrictions', 'woocommerce-coupon-restrictions' ) . '</h3>' .
					'<p>' . esc_html__( 'Coupon restrictions can be found in this panel.', 'woocommerce-coupon-restrictions' ) . '</p>',
					'position' => array(
						'edge'  => 'top',
						'align' => 'left',
					),
				),
			),
			'customer-restriction-type' => array(
				'target'       => '#usage_restriction_coupon_data .customer_restriction_type_field .woocommerce-help-tip',
				'next'         => 'usage-limit',
				'next_trigger' => array(
					'target' => '#title',
					'event'  => 'input',
				),
				'options'      => array(
					'content'  => '<h3>' . esc_html__( 'Customer Restrictions', 'woocommerce-coupon-restrictions' ) . '</h3>' .
					'<p>' . esc_html__( 'You now have the option to restrict coupons to new customers or existing customers.', 'woocommerce-coupon-restrictions' ) . '</p>' .
					'<p>' . esc_html__( 'Customers are considered "new" until they complete a purchase.', 'woocommerce-coupon-restrictions' ) . '</p>',
					'position' => array(
						'edge'  => 'left',
						'align' => 'left',
					),
				),
			),
			'usage-limit'               => array(
				'target'       => '#usage_limit_coupon_data .usage_limit_per_user_field .woocommerce-help-tip',
				'next'         => 'role-restriction',
				'next_trigger' => array(
					'target' => '#title',
					'event'  => 'input',
				),
				'options'      => array(
					'content'  => '<h3>' . esc_html__( 'Limit User Tip', 'woocommerce-coupon-restrictions' ) . '</h3>' .
					'<p>' . esc_html__( 'If you are using a new customer restriction, you may also want to limit the coupon to 1 use.', 'woocommerce-coupon-restrictions' ) . '</p>' .
					'<p>' . esc_html__( 'Payments can take a few minutes to process, and it is possible for a customer to place multiple orders in that time if a coupon does not have a 1 use limit.', 'woocommerce-coupon-restrictions' ) . '</p>',
					'position' => array(
						'edge'  => 'left',
						'align' => 'left',
					),
				),
			),
			'role-restriction'          => array(
				'target'       => '#usage_restriction_coupon_data .role_restriction_only_field',
				'next'         => 'location-restrictions',
				'next_trigger' => array(
					'target' => '#title',
					'event'  => 'input',
				),
				'options'      => array(
					'content'  => '<h3>' . esc_html__( 'Role Restrictions', 'woocommerce-coupon-restrictions' ) . '</h3>' .
					'<p>' . esc_html__( 'Coupons can be restricted to specific user roles. Customer must have an account for the coupon to apply.', 'woocommerce-coupon-restrictions' ) . '</p>',
					'position' => array(
						'edge'  => 'right',
						'align' => 'right',
					),
				),
			),
			'location-restrictions'     => array(
				'target'       => '#usage_restriction_coupon_data .location_restrictions_field',
				'next'         => 'multiple-restictions',
				'next_trigger' => array(
					'target' => '#title',
					'event'  => 'input',
				),
				'options'      => array(
					'content'  => '<h3>' . esc_html__( 'Location Restrictions', 'woocommerce-coupon-restrictions' ) . '</h3>' .
					'<p>' . esc_html__( 'Checking this box displays options for country and/or zip code restrictions.', 'woocommerce-coupon-restrictions' ) . '</p>',
					'position' => array(
						'edge'  => 'right',
						'align' => 'right',
					),
				),
			),
			'multiple-restictions'      => array(
				'target'  => '#coupon_options .usage_restriction_options',
				'options' => array(
					'content'  => '<h3>' . esc_html__( 'Multiple Restrictions', 'woocommerce-coupon-restrictions' ) . '</h3>' .
					'<p>' . esc_html__( 'If multiple coupon restrictions are set, the customer must meet all restrictions.', 'woocommerce-coupon-restrictions' ) . '</p>',
					'position' => array(
						'edge'  => 'left',
						'align' => 'left',
					),
				),
			),
		);
	}

	/**
	 * Displays the pointers.
	 * Follow WooCommerce core pattern:
	 * https://github.com/woocommerce/woocommerce/blob/master/includes/admin/class-wc-admin-pointers.php
	 *
	 * @param array $pointers
	 *
	 * @since 1.5.0
	 */
	public function display_pointers( $pointers ) {
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		$file = esc_url( WC_Coupon_Restrictions::plugin_asset_path() . '/assets/onboarding.js' );
		wp_register_script(
			'wccr-onboarding-pointers',
			$file,
			array( 'wp-pointer' ),
			'1.8.6',
			true
		);

		wp_localize_script(
			'wccr-onboarding-pointers',
			'WCCR_POINTERS',
			array(
				'pointers' => $pointers,
				'close'    => esc_html__( 'Dismiss', 'woocommerce-coupon-restrictions' ),
				'next'     => esc_html__( 'Next', 'woocommerce-coupon-restrictions' ),
				'enjoy'    => esc_html__( 'Enjoy!', 'woocommerce-coupon-restrictions' ),
			)
		);

		wp_enqueue_script( 'wccr-onboarding-pointers' );
	}

}

<?php
/**
 * WooCommerce Coupon Restrictions - Onboarding.
 *
 * @class    WC_Coupon_Restrictions_Onboarding
 * @author   DevPress
 * @package  WooCommerce Coupon Restrictions
 * @license  GPL-2.0+
 * @since    1.5.0
 */

if ( ! defined('ABSPATH') ) {
	exit; // Exit if accessed directly.
}

class WC_Coupon_Restrictions_Onboarding {

	/**
	* Construct the class.
	*/
	public function __construct() {

		// Gets the base file for plugin.
		$base = WC_Coupon_Restrictions::plugin_base();

		// Adds links for plugin on the plugin admin screen.
		add_filter( 'plugin_action_links_' . $base, array( $this, 'plugin_action_links' ) );

		$options = get_option( 'woocommerce-coupon-restrictions', false );
		if ( false === $options ) {
			$this->onboard_routine();
		}

		if ( get_transient( 'woocommerce-coupon-restrictions-activated' ) ) :

			// Displays the onboarding notice.
			add_action( 'admin_notices', array( $this, 'admin_installed_notice' ) );

			// Inline script deletes the transient when notice is dismissed.
			add_action( 'admin_footer', array( $this, 'admin_notice_dismiss' ), 100 );

			// Deletes the transient via query string (when user clicks to start onboarding).
			add_action( 'init', array( $this, 'dismiss_notice_via_query' ) );

			// Deletes the transient via ajax (when user dismisses notice).
			add_action( 'wp_ajax_wc_customer_coupons_dismiss_notice', array( $this, 'dismiss_notice_via_ajax' ) );

		endif;

		// Initialize the pointers for onboarding flow.
		add_action( 'admin_enqueue_scripts', array( $this, 'init_pointers_for_screen' ) );

	}

	/**
	 * Plugin action links.
	 *
	 * @since 1.5.0
	 *
	 * @param  array $links List of existing plugin action links.
	 * @return array List of modified plugin action links.
	 */
	public static function plugin_action_links( $links ) {

		// URL for coupon screen onboarding
		$url = admin_url( 'post-new.php?post_type=shop_coupon&woocommerce-coupon-restriction-pointers=1' );

		$custom = array(
			'<a href="' . esc_url( $url ) . '">' . __( 'New Coupon', 'woocommerce-coupon-restrictions' ) . '</a>',
			'<a href="https://devpress.com/products/woocommerce-coupon-restrictions/">' . __( 'Docs', 'woocommerce-coupon-restrictions' ) . '</a>'
		);
		$links = array_merge( $custom, $links );
		return $links;
	}

	/**
	 * Deletes the admin notice transient if query string is present.
	 *
	 * @since 1.5.0
	 */
	public static function dismiss_notice_via_query() {
		if (
			current_user_can( 'manage_options' ) &&
			isset( $_GET['woocommerce-coupon-restriction-pointers'] ) )
		{
			delete_transient( 'woocommerce-coupon-restrictions-activated' );
		}
	}

	/**
	 * Deletes the admin notice transient via ajax.
	 *
	 * @since 1.5.0
	 */
	public static function dismiss_notice_via_ajax() {

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
	 * Sets a transient that triggers our onboarding routine.
	 *
	 * @access public
	 * @since  1.5.0
	 * @return void
	 */
	public static function onboard_routine() {
		set_transient( 'woocommerce-coupon-restrictions-activated', true, 60 * 60 * 24 * 7 );
	}

	/**
	 * Displays a welcome notice.
	 *
	 * @since 1.5.0
	 */
	public static function admin_installed_notice() {
		if ( current_user_can( 'manage_options' ) ) :
			$url = admin_url( 'post-new.php?post_type=shop_coupon&woocommerce-coupon-restriction-pointers=1' );
			?>
			<div class="updated notice is-dismissible woocommerce-message" data-woocommerce-coupon-restrictions="true" style="border-left-color: #cc99c2">
				<p>
					<?php _e( 'WooCommerce Coupon Restrictions plugin activated.', 'woocommerce-coupon-restrictions' ); ?>
					<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'See how it works.', 'woocommerce-coupon-restrictions' ); ?></a>
				</p>
			</div>
			<?php
		endif;
	}

	/**
	 * Allows admin notice to be dismissed via ajax.
	 *
	 * @access public
	 * @since  1.5.0
	 * @return void
	 */
	public static function admin_notice_dismiss() {
		if ( current_user_can( 'manage_options' ) ) :
			// Loads jQuery if not already available
			wp_enqueue_script( 'jquery' );
			?>
			<script>
			( function ( window, $ ) {
				'use strict';

				$( '.notice' ).on( 'click', '.notice-dismiss', function( event ) {
					var notice = event.delegateTarget.getAttribute( 'data-woocommerce-coupon-restrictions' );

					if ( ! notice ) {
						return;
					}

					$.ajax( {
						method: 'post',
						data: {
							nonce: '<?php echo wp_create_nonce( 'wc_customer_coupons_nonce' ); ?>',
							action: 'wc_customer_coupons_dismiss_notice'
						},
						url: ajaxurl
					} );
				} );
			} )( window, jQuery );
			</script>
		<?php
		endif;
	}

	/**
	 * Init pointers for screen.
	 *
	 * @since 1.5.0
	 */
	public static function init_pointers_for_screen() {

		if (
			! isset( $_GET['woocommerce-coupon-restriction-pointers'] ) ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		$screen = get_current_screen();
		if ( 'shop_coupon' === $screen->id ) {
			$pointers = $this->get_pointers();
			$this->display_pointers( $pointers );
		}
	}

	/**
	 * Defines all the pointers.
	 *
	 * @since 1.5.0
	 */
	public static function get_pointers() {
		$pointers = array(
			'pointers' => array(
				'coupon-restrictions-panel' => array(
					'target' => '#woocommerce-coupon-data .usage_restriction_options',
					'next' => 'customer-restriction-type',
					'next_trigger' => array(
						'target' => '#title',
						'event'  => 'input',
					),
					'options' => array(
						'content'  => '<h3>' . esc_html__( 'Usage Restrictions', 'woocommerce-coupon-restrictions' ) . '</h3>' .
						'<p>' . esc_html__( 'The new coupon restrictions can be found in this panel.', 'woocommerce-coupon-restrictions' ) . '</p>',
						'position' => array(
							'edge'  => 'top',
							'align' => 'left',
						)
					)
				),
				'customer-restriction-type' => array(
					'target' => '#usage_restriction_coupon_data .customer_restriction_type_field .woocommerce-help-tip',
					'next' => 'usage-limit',
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
						)
					)
				),
				'usage-limit' => array(
					'target' => '#usage_limit_coupon_data .usage_limit_per_user_field .woocommerce-help-tip',
					'next' => 'location-restrictions',
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
						)
					)
				),
				'location-restrictions' => array(
					'target' => '#usage_restriction_coupon_data .location_restrictions_field',
					'next' => 'multiple-restictions',
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
						)
					)
				),
				'multiple-restictions' => array(
					'target' => '#coupon_options .usage_restriction_options',
					'options'      => array(
						'content'  => '<h3>' . esc_html__( 'Multiple Restrictions', 'woocommerce-coupon-restrictions' ) . '</h3>' .
						'<p>' . esc_html__( 'If multiple coupon restrictions are set, the customer must meet all restrictions.', 'woocommerce-coupon-restrictions' ) . '</p>',
						'position' => array(
							'edge'  => 'left',
							'align' => 'left',
						)
					)
				)
			)
		);

		return $pointers;
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
	public static function display_pointers( $pointers ) {
		$pointers = wp_json_encode( $pointers );
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		wc_enqueue_js(
			"jQuery( function( $ ) {
				var wccr_pointers = {$pointers};
				setTimeout( init_wccr_pointers, 800 );
				function init_wccr_pointers() {
					$.each( wccr_pointers.pointers, function( i ) {
						pre_show_wccr_pointer( i );
						show_wc_pointer( i );
						return false;
					});
				}
				function show_wc_pointer( id ) {
					var pointer = wccr_pointers.pointers[ id ];
					var options = $.extend( pointer.options, {
						pointerClass: 'wp-pointer wc-pointer',
						close: function() {
							pre_show_wccr_pointer( pointer.next );
							if ( pointer.next ) {
								show_wc_pointer( pointer.next );
							}
						},
						buttons: function( event, t ) {
							var close   = '" . esc_js( __( 'Dismiss', 'woocommerce-customer-coupons' ) ) . "',
								next    = '" . esc_js( __( 'Next', 'woocommerce-customer-coupons' ) ) . "',
								enjoy    = '" . esc_js( __( 'Enjoy!', 'woocommerce-customer-coupons' ) ) . "',
								btn_close  = $( '<a class=\"close\" href=\"#\">' + close + '</a>' ),
								btn_next = $( '<a class=\"button button-primary\" href=\"#\">' + next + '</a>' ),
								btn_complete = $( '<a class=\"button button-primary\" href=\"#\">' + enjoy + '</a>' ),
								wrapper = $( '<div class=\"wc-pointer-buttons\" />' );
							btn_close.bind( 'click.pointer', function(e) {
								e.preventDefault();
								t.element.pointer('destroy');
							});
							btn_next.bind( 'click.pointer', function(e) {
								e.preventDefault();
								t.element.pointer('close');
							});
							btn_complete.bind( 'click.pointer', function(e) {
								e.preventDefault();
								t.element.pointer('close');
							});

							wrapper.append( btn_close );

							if ('multiple-restictions' !== id) {
								wrapper.append( btn_next );
							} else {
								wrapper.append( btn_complete );
							}
							return wrapper;
						},
					} );
					var this_pointer = $( pointer.target ).pointer( options );
					$('html, body').animate({ scrollTop: $( pointer.target ).offset().top - 200 });
					this_pointer.pointer( 'open' );
					if ( pointer.next_trigger ) {
						$( pointer.next_trigger.target ).on( pointer.next_trigger.event, function() {
							setTimeout( function() { this_pointer.pointer( 'close' ); }, 400 );
						});
					}
				}
				function pre_show_wccr_pointer( pointer ) {
					if ( 'coupon-restrictions-panel' === pointer ) {
						$('#woocommerce-coupon-data .usage_restriction_tab a').trigger('click');
					}
					if ( 'usage-limit' === pointer ) {
						$('#woocommerce-coupon-data .usage_limit_tab a').trigger('click');
					}
					if ( 'location-restrictions' === pointer ) {
						$('#woocommerce-coupon-data .usage_restriction_tab a').trigger('click');
						$('#usage_restriction_coupon_data .checkbox').trigger('click');
					}
				}
			});"
		);
	}

}

new WC_Coupon_Restrictions_Onboarding();

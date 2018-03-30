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
	* Initialize the class.
	*/
	public static function init() {

		// Sets a transient on activation to determine if activation notices should be displayed.
		register_activation_hook( plugin_basename( __FILE__ ), __CLASS__ . '::activation_hook' );

		// Deletes the transient based on url query string.
		add_action( 'init', __CLASS__ . '::query_string_dismiss_admin_notice' );

		// Displays a notice on activation.
		add_action( 'admin_notices', __CLASS__ . '::admin_installed_notice' );

		// Initialize the pointers for onboarding flow.
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::init_pointers_for_screen' );

	}

	/**
	 * Displays a welcome message. Called when the extension is activated.
	 *
	 * @since 1.5.0
	 */
	public static function activation_hook() {
		// After the plugin is activated a transient variable is set for 10 minutes.
		// This variable determines whether onboarding notice should be displayed.
		set_transient( 'woocommerce-coupon-restrictions-activated', true, 10 * 60 );
	}

	/**
	 * Deletes the admin notice transient if query string is present.
	 *
	 * @since 1.5.0
	 */
	public static function query_string_dismiss_admin_notice() {
		if ( isset( $_GET['woocommerce-coupon-restriction-pointers'] ) ) {
			delete_transient( 'woocommerce-coupon-restrictions-activated' );
		}
	}

	/**
	 * Displays a welcome message. Called when the extension is activated.
	 *
	 * @since 1.5.0
	 */
	public static function admin_installed_notice() {

		if ( true ) :
		// if ( get_transient( 'woocommerce-coupon-restrictions-activated' ) ) :
			$url = 'post-new.php?post_type=shop_coupon&woocommerce-coupon-restriction-pointers=1';
			?>
			<div class="updated notice is-dismissible woocommerce-message" style="border-left-color: #cc99c2">
				<p>
					<?php _e( 'WooCommerce Coupon Restrictions plugin activated.', 'woocommerce-coupon-restrictions' ); ?>
					<a href="<?php echo admin_url( $url ); ?>"><?php esc_html_e( 'See How it Works', 'woocommerce-coupon-restrictions' ); ?></a>
				</p>
			</div>
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
			$pointers = self::get_pointers();
			self::display_pointers( $pointers );
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
					'next' => 'content',
					'next_trigger' => array(
						'target' => '#title',
						'event'  => 'input',
					),
					'options'      => array(
						'content'  => '<h3>' . esc_html__( 'Usage Restrictions', 'woocommerce-coupon-restrictions' ) . '</h3>' .
						'<p>' . esc_html__( 'The new coupon restrictions can be found in this panel.', 'woocommerce-coupon-restrictions' ) . '</p>',
						'position' => array(
							'edge'  => 'top',
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
				jQuery('#woocommerce-coupon-data .usage_restriction_tab a').trigger('click');

				var wc_pointers = {$pointers};
				setTimeout( init_wc_pointers, 800 );
				function init_wc_pointers() {
					console.log('Pointers!');
					console.log(wc_pointers);
					$.each( wc_pointers.pointers, function( i ) {
						console.log(i);
						show_wc_pointer( i );
						return false;
					});
				}
				function show_wc_pointer( id ) {
					var pointer = wc_pointers.pointers[ id ];
					var options = $.extend( pointer.options, {
						pointerClass: 'wp-pointer wc-pointer',
						close: function() {
							if ( pointer.next ) {
								show_wc_pointer( pointer.next );
							}
						},
						buttons: function( event, t ) {
							var close   = '" . esc_js( __( 'Dismiss', 'woocommerce' ) ) . "',
								next    = '" . esc_js( __( 'Next', 'woocommerce' ) ) . "',
								button  = $( '<a class=\"close\" href=\"#\">' + close + '</a>' ),
								button2 = $( '<a class=\"button button-primary\" href=\"#\">' + next + '</a>' ),
								wrapper = $( '<div class=\"wc-pointer-buttons\" />' );
							button.bind( 'click.pointer', function(e) {
								e.preventDefault();
								t.element.pointer('destroy');
							});
							button2.bind( 'click.pointer', function(e) {
								e.preventDefault();
								t.element.pointer('close');
							});
							wrapper.append( button );
							wrapper.append( button2 );
							return wrapper;
						},
					} );
					var this_pointer = $( pointer.target ).pointer( options );
					this_pointer.pointer( 'open' );
					if ( pointer.next_trigger ) {
						$( pointer.next_trigger.target ).on( pointer.next_trigger.event, function() {
							setTimeout( function() { this_pointer.pointer( 'close' ); }, 400 );
						});
					}
				}
			});"
		);
	}

}

WC_Coupon_Restrictions_Onboarding::init();

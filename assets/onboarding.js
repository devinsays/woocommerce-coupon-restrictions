jQuery( function( $ ) {
	setTimeout( init_wccr_pointers, 800 );
	function init_wccr_pointers() {
		$.each( WCCR_POINTERS.pointers, function( i ) {
			pre_show_wccr_pointer( i );
			show_wc_pointer( i );
			return false;
		});
	}
	function show_wc_pointer( id ) {
		var pointer = WCCR_POINTERS.pointers[ id ];
		var options = $.extend( pointer.options, {
			pointerClass: 'wp-pointer wc-pointer',
			close: function() {
				pre_show_wccr_pointer( pointer.next );
				if ( pointer.next ) {
					show_wc_pointer( pointer.next );
				}
			},
			buttons: function( event, t ) {
				var btn_close  = $( '<a class=\"close\" href=\"#\">' + WCCR_POINTERS.close + '</a>' ),
					btn_next = $( '<a class=\"button button-primary\" href=\"#\">' + WCCR_POINTERS.next + '</a>' ),
					btn_complete = $( '<a class=\"button button-primary\" href=\"#\">' + WCCR_POINTERS.enjoy + '</a>' ),
					wrapper = $( '<div class=\"wc-pointer-buttons\" />' );
				btn_close.bind( 'click.pointer', function(e) {
					e.preventDefault();
					t.element.pointer('destroy');

					// Updates the URL so pointers won't show on page refresh.
					var url = window.location.href;
					url = url.replace('&woocommerce-coupon-restriction-pointers=1', '');
					window.history.pushState(null, null, url);
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
		if ( 'role-restriction' === pointer ) {
			$('#woocommerce-coupon-data .usage_restriction_tab a').trigger('click');
		}
		if ( 'location-restrictions' === pointer ) {
			$('#usage_restriction_coupon_data .checkbox').trigger('click');
		}
	}
});

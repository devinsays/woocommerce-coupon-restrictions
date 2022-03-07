jQuery( function( $ ) {
	// Displays the pointers once the coupon tabs load.
	const observer = new MutationObserver((mutations, obs) => {
		const tabs = document.getElementsByClassName('coupon_data_tabs')[0];
		if (tabs) {
			initPointers();
			obs.disconnect();
			return;
		}
	});

	observer.observe(document, {
		childList: true,
		subtree: true
	});

	function initPointers() {
		$.each( WCCR_POINTERS.pointers, function( i ) {
			revealTab( i );
			showPointer( i );
			return false;
		});
	}

	function showPointer( id ) {
		var pointer = WCCR_POINTERS.pointers[ id ];
		var options = $.extend( pointer.options, {
			pointerClass: 'wp-pointer wc-pointer',
			close: function() {
				revealTab( pointer.next );
				if ( pointer.next ) {
					showPointer( pointer.next );
				}
			},
			buttons: function( event, t ) {
				const btnClose  = $( `<a class="close" href="#">${WCCR_POINTERS.close}</a>` );
				const btnNext = $( `<a class="button button-primary" href="#">${WCCR_POINTERS.next}</a>` );
				const btnComplete = $( `<a class="button button-primary" href="#">${WCCR_POINTERS.enjoy}</a>` );

				let wrapper = $( `<div class="wc-pointer-buttons" />` );

				btnClose.bind( 'click.pointer', function(e) {
					e.preventDefault();
					t.element.pointer('destroy');

					// Updates the URL so pointers won't show on page refresh.
					var url = window.location.href;
					url = url.replace('&woocommerce-coupon-restriction-pointers=1', '');
					window.history.pushState(null, null, url);
				});

				btnNext.bind( 'click.pointer', function(e) {
					e.preventDefault();
					t.element.pointer('close');
				});

				btnComplete.bind( 'click.pointer', function(e) {
					e.preventDefault();
					t.element.pointer('close');
				});

				wrapper.append( btnClose );

				if ('multiple-restictions' !== id) {
					wrapper.append( btnNext );
				} else {
					wrapper.append( btnComplete );
				}

				return wrapper;
			},
		});

		const thisPointer = $( pointer.target ).pointer( options );
		$('html, body').animate({ scrollTop: $( pointer.target ).offset().top - 200 });
		thisPointer.pointer( 'open' );

		if ( pointer.next_trigger ) {
			$( pointer.next_trigger.target ).on( pointer.next_trigger.event, function() {
				setTimeout( function() { thisPointer.pointer( 'close' ); }, 400 );
			});
		}
	}

	function revealTab( pointer ) {
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

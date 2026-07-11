/**
 * KDNA Checkout front end. Stage 2: layout reflow.
 *
 * Wraps the native WooCommerce checkout form into two regions,
 * .kdna-checkout__main (customer details and anything third parties
 * add to the form) and .kdna-checkout__summary (order review heading,
 * order review table and payment), then flags the widget as ready so
 * the two-column grid CSS engages.
 *
 * Nodes are only moved, never cloned or rebuilt, so all WooCommerce
 * checkout behaviour (AJAX fragments replacing the review table and
 * payment box) keeps working inside the summary card. Without this
 * script the native single-column checkout still works (fail-safe).
 *
 * @package KDNA_Checkout
 */
( function () {
	'use strict';

	/**
	 * Reflow one KDNA Checkout widget instance.
	 *
	 * @param {HTMLElement} root The .kdna-checkout wrapper.
	 */
	function reflow( root ) {
		if ( root.classList.contains( 'kdna-checkout--ready' ) || root.classList.contains( 'kdna-checkout--editor' ) ) {
			return;
		}

		var form = root.querySelector( 'form.woocommerce-checkout' );
		if ( ! form ) {
			return; // Empty cart, order-received screen or editor: nothing to reflow.
		}

		var heading = form.querySelector( '#order_review_heading' );
		var review = form.querySelector( '#order_review' );
		if ( ! review ) {
			return;
		}

		var main = document.createElement( 'div' );
		main.className = 'kdna-checkout__main';

		var summary = document.createElement( 'div' );
		summary.className = 'kdna-checkout__summary';

		// Everything that is not the order summary moves to the main region,
		// preserving source order so third-party additions keep their place.
		Array.prototype.slice.call( form.children ).forEach( function ( node ) {
			if ( node === heading || node === review ) {
				return;
			}
			main.appendChild( node );
		} );

		if ( heading ) {
			summary.appendChild( heading );
		}
		summary.appendChild( review );

		form.appendChild( main );
		form.appendChild( summary );

		root.classList.add( 'kdna-checkout--ready' );
		root.dispatchEvent( new CustomEvent( 'kdna:checkout-ready', { bubbles: true } ) );
	}

	function boot() {
		Array.prototype.slice.call( document.querySelectorAll( '.kdna-checkout' ) ).forEach( reflow );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );

/**
 * Stage 3: pay button icon.
 *
 * The widget renders the chosen icon into an inert <template> so the
 * icon markup survives WooCommerce rebuilding the Place Order button
 * during AJAX fragment refreshes. This module clones the template into
 * the button on load and again after every updated_checkout event.
 */
( function () {
	'use strict';

	/**
	 * Inject the icon into the pay button of one widget instance.
	 *
	 * @param {HTMLElement} root The .kdna-checkout wrapper.
	 */
	function applyPayIcon( root ) {
		var template = root.querySelector( 'template.kdna-checkout__pay-icon-tpl' );
		if ( ! template || ! template.content ) {
			return;
		}

		var button = root.querySelector( '#place_order' );
		if ( ! button || button.querySelector( '.kdna-checkout__pay-icon' ) ) {
			return; // No button yet, or the icon is already in place.
		}

		var position = 'after' === template.getAttribute( 'data-position' ) ? 'after' : 'before';

		var icon = document.createElement( 'span' );
		icon.className = 'kdna-checkout__pay-icon kdna-checkout__pay-icon--' + position;
		icon.setAttribute( 'aria-hidden', 'true' );
		icon.appendChild( template.content.cloneNode( true ) );

		if ( 'after' === position ) {
			button.appendChild( icon );
		} else {
			button.insertBefore( icon, button.firstChild );
		}
	}

	function applyAll() {
		Array.prototype.slice.call( document.querySelectorAll( '.kdna-checkout--pay-icon' ) ).forEach( applyPayIcon );
	}

	function boot() {
		applyAll();

		// WooCommerce replaces the payment box (button included) via
		// jQuery-driven fragments, so re-apply after each refresh.
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'updated_checkout', applyAll );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );

/**
 * Stage 4: cart strip live updates.
 *
 * Quantity edits and removals post to the plugin's AJAX endpoint, the
 * returned strip HTML replaces the old strip, and WooCommerce's own
 * update_checkout event refreshes the order summary totals. Events are
 * delegated to the document because the strip node is replaced on
 * every update.
 */
( function () {
	'use strict';

	var qtyTimer = null;

	function findStrip( el ) {
		return el.closest ? el.closest( '.kdna-checkout-strip' ) : null;
	}

	function inSkeleton( strip ) {
		return strip.classList.contains( 'kdna-checkout-strip--skeleton' );
	}

	function request( strip, cartItemKey, quantity ) {
		var cfg = window.kdnaCheckoutStrip;
		if ( ! cfg || ! window.fetch || inSkeleton( strip ) ) {
			return;
		}

		strip.classList.add( 'kdna-checkout-strip--busy' );

		var data = new window.FormData();
		data.append( 'action', 'kdna_checkout_strip_update' );
		data.append( 'nonce', cfg.nonce );
		data.append( 'cart_item_key', cartItemKey );
		data.append( 'quantity', String( quantity ) );
		data.append( 'controls', strip.getAttribute( 'data-controls' ) || 'full' );
		data.append( 'sticky_desktop', strip.getAttribute( 'data-sticky-desktop' ) || '' );
		data.append( 'sticky_mobile', strip.getAttribute( 'data-sticky-mobile' ) || '' );
		data.append( 'subtotal_label', strip.getAttribute( 'data-subtotal-label' ) || '' );
		data.append( 'edit_label', strip.getAttribute( 'data-edit-label' ) || '' );
		data.append( 'done_label', strip.getAttribute( 'data-done-label' ) || '' );

		window.fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success || ! result.data || ! result.data.strip_html ) {
					strip.classList.remove( 'kdna-checkout-strip--busy' );
					return;
				}

				var wasEditing = strip.classList.contains( 'kdna-checkout-strip--editing' );
				var holder     = document.createElement( 'div' );
				holder.innerHTML = result.data.strip_html;

				var fresh = holder.firstElementChild;
				if ( fresh && strip.parentNode ) {
					if ( wasEditing && ! result.data.cart_empty ) {
						fresh.classList.add( 'kdna-checkout-strip--editing' );
						var link = fresh.querySelector( '.kdna-checkout-strip__edit-link' );
						if ( link ) {
							link.textContent = fresh.getAttribute( 'data-done-label' ) || link.textContent;
							link.setAttribute( 'aria-expanded', 'true' );
						}
					}
					strip.parentNode.replaceChild( fresh, strip );
					fresh.dispatchEvent( new CustomEvent( 'kdna:strip-updated', { bubbles: true } ) );
				}

				// Refresh the order summary through WooCommerce itself.
				if ( window.jQuery ) {
					window.jQuery( document.body ).trigger( 'update_checkout' );
				}
			} )
			.catch( function () {
				strip.classList.remove( 'kdna-checkout-strip--busy' );
			} );
	}

	document.addEventListener( 'change', function ( event ) {
		var input = event.target;
		if ( ! input.classList || ! input.classList.contains( 'kdna-checkout-strip__qty' ) ) {
			return;
		}

		var strip = findStrip( input );
		var tile  = input.closest( '.kdna-checkout-strip__tile' );
		if ( ! strip || ! tile || ! tile.getAttribute( 'data-key' ) ) {
			return;
		}

		var quantity = parseInt( input.value, 10 );
		if ( isNaN( quantity ) || quantity < 0 ) {
			quantity    = 1;
			input.value = '1';
		}

		window.clearTimeout( qtyTimer );
		qtyTimer = window.setTimeout( function () {
			request( strip, tile.getAttribute( 'data-key' ), quantity );
		}, 350 );
	} );

	document.addEventListener( 'click', function ( event ) {
		if ( ! event.target || ! event.target.closest ) {
			return;
		}

		var removeButton = event.target.closest( '.kdna-checkout-strip__remove' );
		if ( removeButton ) {
			event.preventDefault();
			var removeStrip = findStrip( removeButton );
			var removeTile  = removeButton.closest( '.kdna-checkout-strip__tile' );
			if ( removeStrip && removeTile && removeTile.getAttribute( 'data-key' ) ) {
				request( removeStrip, removeTile.getAttribute( 'data-key' ), 0 );
			}
			return;
		}

		var editLink = event.target.closest( '.kdna-checkout-strip__edit-link' );
		if ( editLink ) {
			event.preventDefault();
			var editStrip = findStrip( editLink );
			if ( ! editStrip ) {
				return;
			}
			var editing = editStrip.classList.toggle( 'kdna-checkout-strip--editing' );
			editLink.setAttribute( 'aria-expanded', editing ? 'true' : 'false' );
			editLink.textContent = editing
				? ( editStrip.getAttribute( 'data-done-label' ) || editLink.textContent )
				: ( editStrip.getAttribute( 'data-edit-label' ) || editLink.textContent );
		}
	} );
}() );

/**
 * Stage 5: express payment row.
 *
 * The gateways render their express buttons wherever they normally do
 * (usually inside the checkout form). This module relocates the known
 * express wrappers into the row at the top, hides the gateways' own
 * separators, and shows the row only while at least one relocated
 * button is actually visible. Gateways keep full ownership of their
 * buttons; nodes are moved, never rebuilt, so their JS keeps working.
 */
( function () {
	'use strict';

	function parseList( root, attribute ) {
		try {
			var parsed = JSON.parse( root.getAttribute( attribute ) || '[]' );
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( error ) {
			return [];
		}
	}

	function isVisible( el ) {
		if ( el.hidden ) {
			return false;
		}
		if ( window.getComputedStyle && 'none' === window.getComputedStyle( el ).display ) {
			return false;
		}
		return true;
	}

	function setUp( row ) {
		if ( row.classList.contains( 'kdna-checkout-express--skeleton' ) ) {
			return; // Editor skeleton: static, never scans for gateways.
		}

		var root      = row.closest( '.kdna-checkout' ) || document;
		var buttons   = row.querySelector( '.kdna-checkout-express__buttons' );
		var selectors = parseList( row, 'data-selectors' );
		var hide      = parseList( row, 'data-hide' );

		if ( ! buttons ) {
			return;
		}

		function collect() {
			selectors.forEach( function ( selector ) {
				var found;
				try {
					found = root.querySelectorAll( selector );
				} catch ( error ) {
					return; // Ignore an invalid selector from the filter.
				}
				Array.prototype.slice.call( found ).forEach( function ( el ) {
					if ( ! buttons.contains( el ) ) {
						buttons.appendChild( el );
					}
				} );
			} );

			hide.forEach( function ( selector ) {
				var found;
				try {
					found = root.querySelectorAll( selector );
				} catch ( error ) {
					return;
				}
				Array.prototype.slice.call( found ).forEach( function ( el ) {
					el.classList.add( 'kdna-checkout-express-hide' );
				} );
			} );
		}

		function updateVisibility() {
			var children = Array.prototype.slice.call( buttons.children );
			var anyVisible = children.some( isVisible );
			row.classList.toggle( 'kdna-checkout-express--active', anyVisible );
		}

		function scan() {
			collect();
			updateVisibility();
		}

		scan();

		// Gateways initialise asynchronously: some insert their wrapper
		// late, most reveal it only once the payment sheet is confirmed
		// available. Watch briefly, then settle.
		var scheduled = null;
		var observer  = null;

		function scheduleScan() {
			if ( scheduled ) {
				return;
			}
			scheduled = window.setTimeout( function () {
				scheduled = null;
				scan();
			}, 100 );
		}

		if ( window.MutationObserver && root !== document ) {
			observer = new MutationObserver( scheduleScan );
			observer.observe( root, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: [ 'style', 'class', 'hidden' ],
			} );
			window.setTimeout( function () {
				observer.disconnect();
			}, 15000 );
		}

		[ 500, 1500, 3000 ].forEach( function ( delay ) {
			window.setTimeout( scan, delay );
		} );

		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'updated_checkout', scan );
		}
	}

	function boot() {
		Array.prototype.slice.call( document.querySelectorAll( '.kdna-checkout-express' ) ).forEach( setUp );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );

/**
 * Stage 6: lightweight inline field validation.
 *
 * Errors show per field as the shopper leaves each one, instead of
 * only on submit. WooCommerce's own checkout script keeps handling
 * submit-time validation; this layer only adds early, per-field
 * feedback and never blocks anything (fail-safe). Active only when
 * the widget wrapper carries the --validate modifier.
 */
( function () {
	'use strict';

	var EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	var PHONE_PATTERN = /^[+0-9()\-\s.]{7,20}$/;
	var POSTCODES = {
		GB: /^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/,
		AU: /^\d{4}$/,
		NZ: /^\d{4}$/,
		US: /^\d{5}(-\d{4})?$/,
		CA: /^[A-Z]\d[A-Z]\s*\d[A-Z]\d$/
	};

	function messages() {
		return window.kdnaCheckoutFields || {};
	}

	function fieldCountry( input ) {
		var form = input.closest( 'form' );
		if ( ! form ) {
			return '';
		}
		var prefix  = 0 === ( input.id || '' ).indexOf( 'shipping_' ) ? 'shipping' : 'billing';
		var country = form.querySelector( '#' + prefix + '_country' );
		return country ? String( country.value || '' ).toUpperCase() : '';
	}

	function validationMessage( row, input ) {
		var value = String( input.value || '' ).trim();
		var texts = messages();

		if ( row.classList.contains( 'validate-required' ) && '' === value ) {
			return texts.required || 'This field is required.';
		}
		if ( '' === value ) {
			return '';
		}
		if ( ( row.classList.contains( 'validate-email' ) || 'email' === input.type ) && ! EMAIL_PATTERN.test( value ) ) {
			return texts.email || 'Please enter a valid email address.';
		}
		if ( row.classList.contains( 'validate-postcode' ) ) {
			var pattern = POSTCODES[ fieldCountry( input ) ];
			if ( pattern && ! pattern.test( value.toUpperCase() ) ) {
				return texts.postcode || 'Please enter a valid postcode.';
			}
		}
		if ( row.classList.contains( 'validate-phone' ) && ! PHONE_PATTERN.test( value ) ) {
			return texts.phone || 'Please enter a valid phone number.';
		}
		return '';
	}

	function setFieldState( row, input, message ) {
		var existing = row.querySelector( '.kdna-checkout-field-error' );
		if ( existing ) {
			existing.parentNode.removeChild( existing );
		}

		if ( message ) {
			var error = document.createElement( 'span' );
			error.className = 'kdna-checkout-field-error';
			error.textContent = message;
			row.appendChild( error );

			row.classList.add( 'woocommerce-invalid' );
			row.classList.remove( 'woocommerce-validated' );
			input.setAttribute( 'aria-invalid', 'true' );
		} else {
			row.classList.remove( 'woocommerce-invalid', 'woocommerce-invalid-required-field' );
			row.classList.add( 'woocommerce-validated' );
			input.removeAttribute( 'aria-invalid' );
		}
	}

	function fieldTarget( eventTarget ) {
		if ( ! eventTarget || ! eventTarget.closest || ! eventTarget.matches ) {
			return null;
		}
		if ( ! eventTarget.matches( 'input, textarea, select' ) || 'checkbox' === eventTarget.type || 'radio' === eventTarget.type ) {
			return null;
		}
		if ( ! eventTarget.closest( '.kdna-checkout--validate form.woocommerce-checkout' ) ) {
			return null;
		}
		var row = eventTarget.closest( '.form-row' );
		return row ? { row: row, input: eventTarget } : null;
	}

	document.addEventListener( 'focusout', function ( event ) {
		var target = fieldTarget( event.target );
		if ( target ) {
			setFieldState( target.row, target.input, validationMessage( target.row, target.input ) );
		}
	} );

	// Clear an existing inline error the moment the field becomes valid.
	document.addEventListener( 'input', function ( event ) {
		var target = fieldTarget( event.target );
		if ( target && target.row.querySelector( '.kdna-checkout-field-error' ) ) {
			var message = validationMessage( target.row, target.input );
			if ( ! message ) {
				setFieldState( target.row, target.input, '' );
			}
		}
	} );
}() );

/**
 * Stage 7: order bump toggle.
 *
 * Ticking the bump checkbox adds the offered product (at its
 * discounted price) via AJAX with no reload; unticking removes it.
 * Totals refresh through WooCommerce's own update_checkout event,
 * whose fragment re-render also restores the checkbox state from the
 * cart, so the box always reflects reality. Events are delegated to
 * the document because the payment box is replaced on every refresh.
 */
( function () {
	'use strict';

	document.addEventListener( 'change', function ( event ) {
		var checkbox = event.target;
		if ( ! checkbox.classList || ! checkbox.classList.contains( 'kdna-checkout-bump__checkbox' ) ) {
			return;
		}

		var box = checkbox.closest ? checkbox.closest( '.kdna-checkout-bump' ) : null;
		if ( ! box || box.classList.contains( 'kdna-checkout-bump--skeleton' ) ) {
			return;
		}

		var cfg = window.kdnaCheckoutBump;
		if ( ! cfg || ! window.fetch ) {
			return;
		}

		var ticked = checkbox.checked;

		box.classList.add( 'kdna-checkout-bump--busy' );
		checkbox.disabled = true;

		var data = new window.FormData();
		data.append( 'action', 'kdna_checkout_bump_toggle' );
		data.append( 'nonce', cfg.nonce );
		data.append( 'bump_id', box.getAttribute( 'data-bump' ) || '0' );
		data.append( 'ticked', ticked ? '1' : '0' );

		window.fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					// Fail-safe: revert to the previous state.
					checkbox.checked = ! ticked;
					box.classList.remove( 'kdna-checkout-bump--busy' );
					checkbox.disabled = false;
					return;
				}

				box.dispatchEvent( new CustomEvent( 'kdna:bump-toggled', {
					bubbles: true,
					detail: { inCart: !! result.data.in_cart },
				} ) );

				// The fragment refresh re-renders the bump box with the
				// server-confirmed state and updated totals.
				if ( window.jQuery ) {
					window.jQuery( document.body ).trigger( 'update_checkout' );
				} else {
					box.classList.remove( 'kdna-checkout-bump--busy' );
					checkbox.disabled = false;
				}
			} )
			.catch( function () {
				checkbox.checked = ! ticked;
				box.classList.remove( 'kdna-checkout-bump--busy' );
				checkbox.disabled = false;
			} );
	} );
}() );

/**
 * Stage 8: trust block positioning.
 *
 * The trust block renders after the checkout form, outside every AJAX
 * fragment, so it can never be wiped by a totals refresh. This module
 * then moves it into the position chosen in Elementor (summary card or
 * below the customer details) once the Stage 2 reflow has built those
 * regions. "Full width below the checkout" stays where it rendered.
 */
( function () {
	'use strict';

	function place( root ) {
		var trust = root.querySelector( '.kdna-checkout-trust[data-position]' );
		if ( ! trust ) {
			return;
		}

		var position = trust.getAttribute( 'data-position' );
		var target   = null;

		if ( 'summary' === position ) {
			target = root.querySelector( '.kdna-checkout__summary' );
		} else if ( 'main' === position ) {
			target = root.querySelector( '.kdna-checkout__main' );
		}

		if ( target && trust.parentNode !== target ) {
			target.appendChild( trust );
		}
	}

	// Roots that reflow after this module loads.
	document.addEventListener( 'kdna:checkout-ready', function ( event ) {
		if ( event.target && event.target.classList && event.target.classList.contains( 'kdna-checkout' ) ) {
			place( event.target );
		}
	} );

	// Roots that were already reflowed (script order within this file).
	function boot() {
		Array.prototype.slice.call( document.querySelectorAll( '.kdna-checkout--ready' ) ).forEach( place );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );

/**
 * Stage 9: Google Places address autocomplete.
 *
 * The Google script only loads when the feature is enabled in
 * Settings > KDNA Checkout and a key is stored, and only on pages
 * containing the checkout widget. It calls kdnaCheckoutPlacesInit
 * when ready; this module then attaches autocomplete to the billing
 * and shipping address fields and maps the selected place back onto
 * the WooCommerce fields. Without Google (feature off, key missing or
 * blocked) nothing here runs and the standard fields stand untouched.
 */
( function () {
	'use strict';

	function component( components, type, useShortName ) {
		for ( var i = 0; i < components.length; i++ ) {
			if ( components[ i ].types && -1 !== components[ i ].types.indexOf( type ) ) {
				return useShortName ? components[ i ].short_name : components[ i ].long_name;
			}
		}
		return '';
	}

	function setField( form, id, value ) {
		var field = form.querySelector( '#' + id );
		if ( ! field ) {
			return;
		}
		if ( window.jQuery ) {
			// jQuery keeps select2 (country/state boxes) in sync.
			window.jQuery( field ).val( value ).trigger( 'change' );
		} else {
			field.value = value;
			field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}

	function applyPlace( form, prefix, place ) {
		var components = place && place.address_components ? place.address_components : null;
		if ( ! components ) {
			return; // Fail-safe: nothing selected, nothing changed.
		}

		var streetNumber = component( components, 'street_number', false );
		var route        = component( components, 'route', false );
		var subpremise   = component( components, 'subpremise', false );
		var city         = component( components, 'locality', false )
			|| component( components, 'postal_town', false )
			|| component( components, 'sublocality_level_1', false );
		var state        = component( components, 'administrative_area_level_1', true );
		var postcode     = component( components, 'postal_code', false );
		var country      = component( components, 'country', true );

		var addressLine = ( ( streetNumber ? streetNumber + ' ' : '' ) + route ).trim();

		// Country first: WooCommerce rebuilds the state field when the
		// country changes, so the state is set on a later tick.
		if ( country ) {
			setField( form, prefix + '_country', country );
		}
		if ( addressLine ) {
			setField( form, prefix + '_address_1', addressLine );
		}
		if ( subpremise ) {
			setField( form, prefix + '_address_2', subpremise );
		}
		if ( city ) {
			setField( form, prefix + '_city', city );
		}
		if ( postcode ) {
			setField( form, prefix + '_postcode', postcode );
		}

		if ( state ) {
			[ 50, 400 ].forEach( function ( delay ) {
				window.setTimeout( function () {
					setField( form, prefix + '_state', state );
				}, delay );
			} );
		}

		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'update_checkout' );
		}

		form.dispatchEvent( new CustomEvent( 'kdna:address-autocompleted', {
			bubbles: true,
			detail: { prefix: prefix },
		} ) );
	}

	function bind( form, prefix ) {
		var input = form.querySelector( '#' + prefix + '_address_1' );
		if ( ! input || input.getAttribute( 'data-kdna-places-bound' ) ) {
			return;
		}

		var autocomplete = new window.google.maps.places.Autocomplete( input, {
			types: [ 'address' ],
			fields: [ 'address_components' ],
		} );

		autocomplete.addListener( 'place_changed', function () {
			applyPlace( form, prefix, autocomplete.getPlace() );
		} );

		input.setAttribute( 'data-kdna-places-bound', '1' );

		// Stop Enter submitting the checkout while the suggestion list is open.
		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				var pac = document.querySelector( '.pac-container' );
				if ( pac && null !== pac.offsetParent ) {
					event.preventDefault();
				}
			}
		} );
	}

	function init() {
		if ( ! window.google || ! window.google.maps || ! window.google.maps.places ) {
			return; // Fail-safe: Google absent, standard fields stand.
		}

		Array.prototype.slice.call( document.querySelectorAll( '.kdna-checkout form.woocommerce-checkout' ) ).forEach( function ( form ) {
			bind( form, 'billing' );
			bind( form, 'shipping' );
		} );
	}

	// Google's loading=async callback.
	window.kdnaCheckoutPlacesInit = init;

	// Fallback when the script was already loaded (for example cached
	// without the async callback firing after us).
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );

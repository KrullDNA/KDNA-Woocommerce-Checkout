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

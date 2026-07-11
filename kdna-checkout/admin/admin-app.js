/**
 * KDNA Checkout admin app.
 *
 * Registers Alpine.js components for the Settings > KDNA Checkout screen.
 * This file executes before Alpine.js loads (Alpine depends on it), so the
 * components are registered on the alpine:init event.
 *
 * @package KDNA_Checkout
 */
( function () {
	'use strict';

	document.addEventListener( 'alpine:init', function () {
		window.Alpine.data( 'kdnaCheckoutAdmin', function () {
			return {
				ready: false,

				init: function () {
					this.ready = true;
					document.dispatchEvent(
						new CustomEvent( 'kdna:admin-ready', { bubbles: true } )
					);
				}
			};
		} );
	} );
}() );

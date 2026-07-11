# KDNA Checkout, current build status

Companion to `KDNA-Checkout-Brief.docx` (section 8). Updated at the end of every build session so the next session knows where things stand.

| Stage | Deliverable | Status |
| --- | --- | --- |
| Stage 1 | Foundation & data layer | **Complete** (v0.1.0, 2026-07-11) |
| Stage 2 | Elementor checkout widget (structure) | **Complete** (v0.2.0, 2026-07-11) |
| Stage 3 | Checkout styling controls | **Complete** (v0.3.0, 2026-07-11) |
| Stage 4 | Cart strip (mini-cart) | **Complete** (v0.4.0, 2026-07-11) |
| Stage 5 | Express payment row + styling | **Complete** (v0.5.0, 2026-07-11) |
| Stage 6 | Field optimisation & guest checkout | **Complete** (v0.6.0, 2026-07-11) |
| Stage 7 | Order bumps | Not started |
| Stage 8 | Trust signals block | Not started |
| Stage 9 | Google Places address autocomplete | Not started |
| Stage 10 | Abandoned-cart capture (data layer) | Not started |
| Stage 11 | Recovery email sequence (admin-built + branded template) | Not started |
| Stage 12 | Polish, compatibility & packaging | Not started |

## Stage 6 session notes

- `includes/class-kdna-checkout-fields.php` owns field behaviour. The widget hands its settings over via `KDNA_Checkout_Fields::set_config()` right before the shortcode runs, and the config is also persisted to the `kdna_checkout_fields_config` option (autoload off, written only when changed, never from the editor) because WooCommerce re-applies `woocommerce_checkout_fields` during AJAX order processing where the widget never runs. Same config both times keeps render and validation consistent.
- Guest checkout: `woocommerce_checkout_registration_required` is filtered to false whenever the config is active, so an account is never forced; `woocommerce_checkout_registration_enabled` reflects the widget's "create an account" switch (default on), which renders WooCommerce's native optional checkbox near the end of the details form.
- Field editor: an Elementor repeater (drag to reorder) with per-row field select, show/hide switch and custom label. Priorities are assigned from row order ((index+1)*10) and mirrored onto the matching shipping fields so both sections stay consistent. Order notes are handled in the order section.
- Fail-safes baked into `sanitise_config`: unknown keys dropped, duplicates collapsed, email forced visible (order, customer record and Stage 10 capture depend on it), and any standard field missing from the list is appended as shown, so deleting a repeater row can never lose data. A hidden country field submits the store base country via `woocommerce_checkout_posted_data`; hidden fields are removed from the same filtered list WooCommerce validates against, so no orphaned "required" errors.
- Combined name: last-name fields removed, first-name relabelled (custom label respected, default "Full name") and made full-width; on submit the value is split back into first/last (last word becomes the last name) so order data stays complete.
- Placeholders as labels: each field label becomes its placeholder and the label gets `screen-reader-text` (scoped CSS fallback included), keeping accessibility intact.
- Inline validation: a delegated JS module (active only when the wrapper has `--validate`, default on) validates on blur and clears live on input: required, email format, country-aware postcode patterns (GB/AU/NZ/US/CA), and phone shape. It appends a `.kdna-checkout-field-error` message, toggles WooCommerce's own invalid/validated row classes and sets `aria-invalid`; messages are localised. It never blocks submission, WooCommerce still owns submit-time validation.
- Style additions in the Input Fields section: validation error colour (message + invalid border via CSS variable) and error message typography.
- Verified by a Stage 6 smoke test (sanitisation rules, hide/reorder/relabel with shipping mirroring, combine + split, placeholders, hidden-country default, registration filters, persistence, repeater defaults, editor never writing options) plus a jsdom validation test and the full Stage 2-5 regression suite.

## Stage 5 session notes

- `includes/class-kdna-checkout-express.php` renders the row container: a buttons area (with a `kdna_checkout_express_buttons` action inside for direct server-side rendering) and an optional divider with editable, escaped text. No payment logic anywhere; gateways keep full ownership of their buttons.
- Relocation, not re-rendering: a new JS module moves the known gateway express wrappers (Stripe express checkout element and legacy payment request wrapper, WooPayments, PayPal Payments express placements, Afterpay/Zip) into the row and hides the gateways' own "OR" separators. The selector lists are filterable (`kdna_checkout_express_selectors`, `kdna_checkout_express_hide_selectors`) and travel to the JS via data attributes.
- Fail-safe visibility: the row is `display:none` until the JS confirms at least one relocated button is actually visible (gateways reveal their wrappers only after e.g. `canMakePayment` resolves). A MutationObserver scoped to the widget (auto-disconnects after 15 s), retry scans at 0.5/1.5/3 s and the `updated_checkout` event handle late initialisation, and the row deactivates again if every button hides, so an inactive gateway leaves no gap and no error.
- Render order inside the wrapper: cart strip, express row, checkout form.
- Content controls: show/hide row (default shown), show/hide divider, divider text (default "or pay with card below"). Style sections: Express Payment Row (background, full border group, separate radius, box-shadow, padding, margin, gap between buttons, button minimum width) and Express Divider (line colour, line thickness, text typography and colour, gap around the text, spacing above), all condition-gated and `{{WRAPPER}}`-scoped.
- The editor placeholder shows a static skeleton row (two grey buttons plus the divider) marked `--skeleton` so it never scans for gateways; divider styling previews live in the editor.
- Verified by a Stage 5 smoke test (render/escaping/fallback text, filter round-trip, no premature `--active`, widget order strip→express→form, toggles, skeleton) and a jsdom test (relocation, separator hiding, activate-on-reveal, late-insert collection, deactivate-when-hidden, Stage 2 reflow coexistence), plus the full Stage 2-4 regression suite.

## Stage 4 session notes

- `includes/class-kdna-checkout-cart-strip.php` owns the strip: `render()` builds the markup (tiles with image, escaped name, quantity input capped for sold-individually products, remove button, subtotal via `get_cart_subtotal()`), and `ajax_update` (action `kdna_checkout_strip_update`, nonce-checked, works for guests via `wp_ajax_nopriv`) sets a quantity (0 removes), recalculates totals and returns fresh strip HTML. Unknown modes fall back to `full`; unknown cart keys 404.
- The strip renders at the very top of the widget wrapper, above where the Stage 5 express row will go. Content controls: show/hide (default shown), Item controls select (Full default / Subtle / Edit toggle / Locked), separate desktop and mobile sticky switches, and label text for Subtotal / Edit / Done.
- Mode behaviour is CSS-driven off `--controls-<mode>` classes: Subtle dims the remove icon until hover, Edit toggle hides the controls (showing a static quantity) until the Edit link adds `--editing`, Locked always shows the static quantity. Render args round-trip through data attributes so AJAX refreshes keep the exact same mode, sticky flags and labels; the JS preserves the editing state across refreshes.
- JS (appended module in `assets/js/kdna-checkout.js`): document-delegated change/click handlers (the strip node is replaced on every update), 350 ms debounce on quantity edits, busy state during requests, `update_checkout` triggered on `document.body` so WooCommerce refreshes the order summary itself, and a `kdna:strip-updated` event. Editor skeletons (`--skeleton`) never hit the endpoint.
- Six new Style sections (all condition-gated on the strip being shown): Container (background, border group, radius, shadow, padding, margin, tile gap, sticky offset, empty-state text), Tiles (size, background, border group, radius, shadow, padding, image radius, name typography/colour), Quantity Field (typography, colours, border group, radius, width, padding, focus border/background), Remove Button (icon size, padding, border group, radius, Normal/Hover tabs), Edit Link (typography, border group, radius, padding, Normal/Hover tabs), Subtotal (label and amount typography/colour, alignment, spacing). Selector hygiene sweep passes.
- The editor placeholder now includes a static skeleton strip using the real classes, so all strip style controls give live feedback in the editor.
- Nonce + AJAX data attach to the existing `kdna-checkout` script handle via `wp_localize_script`, so nothing loads where the widget is absent.
- Verified by a Stage 4 smoke test (render modes, escaping, sold-individually cap, empty state, AJAX set/remove/404 paths, widget integration order, skeleton, localisation) plus jsdom strip behaviour tests and the full Stage 2/3 regression suite.

## Stage 3 session notes

- The full Style tab lives in `elementor/widgets/class-widget-checkout.php`, organised into one private method per section: Columns & Spacing, Headings, Field Labels, Input Fields, Order Summary Card, Order Summary Text & Totals, Pay Button.
- Every selector is emitted through `{{WRAPPER}}` (Elementor resolves it to the widget instance ID), so two checkout widgets on one site style independently; `.elementor-widget-container` is never referenced, verified by an automated selector sweep.
- Layout controls (summary column width, column gap, row gap, sticky top offset, pay icon gap/size) set the Stage 2 `--kdna-checkout-*` CSS variables on the wrapper; everything else uses direct instance-scoped selectors, which always out-rank the plugin base CSS.
- Full styling-coverage convention applied: inputs, summary card and pay button each expose background, the complete border group, separate border-radius, box-shadow, padding and margin. Inputs get a distinct Focus tab (text, background, border colour, box-shadow); the pay button gets a Hover tab whose styles also apply on keyboard focus, plus a transition-duration control.
- Input selectors cover text inputs, textareas, selects and WooCommerce select2 boxes (including select2 focus/open states); placeholder colour is separate.
- Pay button icon: an ICONS control renders into an inert `<template>` in the wrapper; a new module in `assets/js/kdna-checkout.js` clones it into `#place_order` on load and re-applies it after every `updated_checkout` fragment refresh (WooCommerce rebuilds the button). Position, spacing and size controls included.
- Key box controls also target the editor placeholder skeleton (fields, summary card, button) so restyling gives live feedback in the editor without running a live checkout.
- Responsive controls: summary width, all gaps and spacing, paddings, margins and radii.
- Content tab untouched; no express buttons. Stage 1/2 files untouched except additive blocks in the shared assets and the version constant (0.3.0).
- Verified by stubbed-Elementor smoke tests (all sections/controls present, selector hygiene sweep, focus/hover distinctness, icon template render paths, editor placeholder) and jsdom DOM tests (icon injection, idempotency, re-apply after simulated fragment refresh, Stage 2 reflow regression).

## Stage 2 session notes

- `elementor/class-kdna-checkout-elementor.php` registers the "KDNA" category and the widgets; both Elementor hooks are bound at file-load time (never inside an `elementor/loaded` callback). If Elementor is absent the hooks never fire, so nothing breaks.
- `elementor/widgets/class-widget-checkout.php` is the KDNA Checkout widget. Atomic architecture: `has_widget_inner_wrapper()` returns false when `e_optimized_markup` is active, render output is a single wrapper div, no CSS touches `.elementor-widget-container`.
- Front end renders `[woocommerce_checkout]` (classic shortcode checkout). `assets/js/kdna-checkout.js` moves the form children into `.kdna-checkout__main` and `.kdna-checkout__summary` regions (nodes moved, never rebuilt, so WooCommerce AJAX fragments keep working), then adds the `--ready` modifier that engages the grid CSS. No JS = native single-column checkout, fail-safe.
- `assets/css/kdna-checkout.css`: two-column grid (`minmax(0,1fr)` + 400px summary column via `--kdna-checkout-*` variables), sticky summary card on desktop (toggleable), single column under 768px with an above/below control (`order: -1`). Editor placeholder skeleton styles included.
- `includes/class-kdna-checkout-assets.php` registers (not enqueues) the `kdna-checkout` style/script handles; the widget declares them via `get_style_depends()`/`get_script_depends()` so Elementor enqueues them only on pages containing the widget.
- Content tab controls: sticky summary switcher (default on) and mobile summary position select (default below). Full Style tab is Stage 3.
- Stage 1 files untouched except the designated integration points: the core loader gained require lines and the assets component, and the version constant moved to 0.2.0.
- Verified by stubbed PHP smoke test (category/widget registration, inner-wrapper flip, editor placeholder vs front-end shortcode render, modifier classes) and a jsdom DOM test of the reflow (region wrapping, source order, nonce retention, idempotency, editor instance untouched).

## Stage 1 session notes

- Plugin lives in `kdna-checkout/` within this repository.
- Main file: headers, constants, HPOS compatibility declaration, WooCommerce-active guard (admin notice, plugin pauses), includes loader via the `KDNA_Checkout` singleton.
- Activation creates `{$wpdb->prefix}kdna_checkout_carts` (id, cart_token, email, cart_snapshot JSON, cart_total, currency, status, created_at, updated_at) via dbDelta, and stores `kdna_checkout_version` / `kdna_checkout_db_version` options.
- Deactivation is non-destructive and fires `kdna_checkout_deactivated` so later stages can hook their clean-up in without modifying Stage 1 files.
- CPTs `kdna_recovery_email` and `kdna_order_bump` registered, admin-only (`public` false, `show_ui` true, `show_in_menu` false, no REST, no rewrite).
- Settings > KDNA Checkout page shell renders a "Coming soon" placeholder; Alpine.js 3.14.9 is bundled locally at `admin/vendor/alpine.min.js` and enqueued (deferred) only on that screen, with components registered on `alpine:init` in `admin/admin-app.js`.
- `uninstall.php` drops the table, deletes both CPTs' posts (with meta) and removes every `kdna_checkout_*` option; multisite-aware.
- No checkout widget, no recovery logic, per the stage scope.

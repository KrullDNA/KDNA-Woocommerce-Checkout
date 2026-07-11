# KDNA Checkout, current build status

Companion to `KDNA-Checkout-Brief.docx` (section 8). Updated at the end of every build session so the next session knows where things stand.

| Stage | Deliverable | Status |
| --- | --- | --- |
| Stage 1 | Foundation & data layer | **Complete** (v0.1.0, 2026-07-11) |
| Stage 2 | Elementor checkout widget (structure) | **Complete** (v0.2.0, 2026-07-11) |
| Stage 3 | Checkout styling controls | **Complete** (v0.3.0, 2026-07-11) |
| Stage 4 | Cart strip (mini-cart) | Not started |
| Stage 5 | Express payment row + styling | Not started |
| Stage 6 | Field optimisation & guest checkout | Not started |
| Stage 7 | Order bumps | Not started |
| Stage 8 | Trust signals block | Not started |
| Stage 9 | Google Places address autocomplete | Not started |
| Stage 10 | Abandoned-cart capture (data layer) | Not started |
| Stage 11 | Recovery email sequence (admin-built + branded template) | Not started |
| Stage 12 | Polish, compatibility & packaging | Not started |

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

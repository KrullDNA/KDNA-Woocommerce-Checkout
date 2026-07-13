<?php
/**
 * Branded recovery email template.
 *
 * Table-based, inline-styled HTML for maximum email-client support.
 * Receives an $email array from KDNA_Checkout_Emails::render_email():
 *   subject, body_html, logo_url, brand_colour, button_colour,
 *   footer_text, store_name.
 *
 * The body_html has already had its merge tags replaced and been run
 * through wp_kses_post; it is echoed as-is here.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$email = isset( $email ) && is_array( $email ) ? $email : array();

$subject       = isset( $email['subject'] ) ? $email['subject'] : '';
$body_html     = isset( $email['body_html'] ) ? $email['body_html'] : '';
$logo_url      = isset( $email['logo_url'] ) ? $email['logo_url'] : '';
$brand_colour  = isset( $email['brand_colour'] ) ? $email['brand_colour'] : '#2271b1';
$button_colour = isset( $email['button_colour'] ) ? $email['button_colour'] : '#2271b1';
$footer_text     = isset( $email['footer_text'] ) ? $email['footer_text'] : '';
$store_name      = isset( $email['store_name'] ) ? $email['store_name'] : get_bloginfo( 'name' );
$unsubscribe_url = isset( $email['unsubscribe_url'] ) ? $email['unsubscribe_url'] : '';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="color-scheme" content="light" />
	<title><?php echo esc_html( $subject ); ?></title>
	<style type="text/css">
		.kdna-email-button:hover { opacity: 0.9; }
		@media only screen and (max-width: 600px) {
			.kdna-email-container { width: 100% !important; }
			.kdna-email-pad { padding-left: 20px !important; padding-right: 20px !important; }
		}
	</style>
</head>
<body style="margin:0;padding:0;background-color:#f2f4f6;-webkit-text-size-adjust:100%;">
	<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
		<?php echo esc_html( $subject ); ?>
	</div>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background-color:#f2f4f6;">
		<tr>
			<td align="center" style="padding:24px 12px;">
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" class="kdna-email-container" style="width:600px;max-width:600px;border-collapse:collapse;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
					<!-- Header -->
					<tr>
						<td align="center" style="background-color:<?php echo esc_attr( $brand_colour ); ?>;padding:24px;">
							<?php if ( '' !== $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $store_name ); ?>" style="max-height:48px;max-width:220px;display:block;border:0;" />
							<?php else : ?>
								<span style="color:#ffffff;font-size:22px;font-weight:bold;font-family:Arial,Helvetica,sans-serif;"><?php echo esc_html( $store_name ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<!-- Body -->
					<tr>
						<td class="kdna-email-pad" style="padding:32px 40px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#333333;">
							<?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Merged and wp_kses_post-sanitised upstream. ?>
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td class="kdna-email-pad" style="padding:20px 40px 32px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#8a8a8a;border-top:1px solid #eeeeee;">
							<?php if ( '' !== $footer_text ) : ?>
								<p style="margin:0 0 8px;"><?php echo esc_html( $footer_text ); ?></p>
							<?php endif; ?>
							<p style="margin:0;">
								<?php
								printf(
									/* translators: %s: store name. */
									esc_html__( 'You are receiving this because you started a checkout at %s.', 'kdna-checkout' ),
									esc_html( $store_name )
								);
								?>
								<?php if ( '' !== $unsubscribe_url ) : ?>
									<a href="<?php echo esc_url( $unsubscribe_url ); ?>" style="color:#8a8a8a;text-decoration:underline;"><?php echo esc_html__( 'Unsubscribe', 'kdna-checkout' ); ?></a>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>

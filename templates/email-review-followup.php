<?php
/**
 * Email template: Review Follow-up (Email 2).
 *
 * Variable available: $body (string) — the email body text with placeholders already replaced.
 *
 * @package Review_Automation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div style="font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #333;">
	<?php echo wp_kses_post( wpautop( $body ) ); ?>
</div>

<?php
/**
 * Mailer class for Review Automation plugin.
 *
 * Handles all email sending operations including placeholder replacement,
 * template loading, and WooCommerce mailer integration.
 *
 * @package Review_Automation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RA_Mailer
 */
class RA_Mailer {

	/**
	 * Retrieve plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return ( new RA_Settings() )->get_settings();
	}

	/**
	 * Replace all supported placeholders in the given text string.
	 *
	 * @param string   $text  Text containing placeholders.
	 * @param WC_Order $order WooCommerce order object.
	 * @return string Text with placeholders replaced.
	 */
	public static function replace_placeholders( $text, $order ) {
		$settings = self::get_settings();

		// Build anchor tags for each review link.
		$link_1 = ! empty( $settings['review_link_1_url'] )
			? '<a href="' . esc_url( $settings['review_link_1_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $settings['review_link_1_label'] ) . '</a>'
			: esc_html( $settings['review_link_1_label'] );

		$link_2 = ! empty( $settings['review_link_2_url'] )
			? '<a href="' . esc_url( $settings['review_link_2_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $settings['review_link_2_label'] ) . '</a>'
			: esc_html( $settings['review_link_2_label'] );

		$link_3 = ! empty( $settings['review_link_3_url'] )
			? '<a href="' . esc_url( $settings['review_link_3_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $settings['review_link_3_label'] ) . '</a>'
			: esc_html( $settings['review_link_3_label'] );

		$order_date = $order->get_date_created()
			? $order->get_date_created()->date_i18n( get_option( 'date_format' ) )
			: '';

		$replacements = array(
			'{customer_name}' => esc_html( $order->get_billing_first_name() ),
			'{order_id}'      => esc_html( $order->get_order_number() ),
			'{order_date}'    => esc_html( $order_date ),
			'{review_link_1}' => $link_1,
			'{review_link_2}' => $link_2,
			'{review_link_3}' => $link_3,
			'{shop_name}'     => esc_html( get_bloginfo( 'name' ) ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
	}

	/**
	 * Load an email template file and return its rendered HTML.
	 *
	 * @param string $template_file Absolute path to the template file.
	 * @param string $body          The email body text to pass to the template.
	 * @return string Rendered HTML output from the template.
	 */
	private static function load_template( $template_file, $body ) {
		if ( ! file_exists( $template_file ) ) {
			return '<div>' . wp_kses_post( wpautop( $body ) ) . '</div>';
		}

		ob_start();
		include $template_file;
		return ob_get_clean();
	}

	/**
	 * Send the initial review request email (Email 1).
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return bool True on success, false on failure.
	 */
	public static function send_review_request( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_get_logger()->error(
				sprintf( 'send_review_request: Order #%d not found.', $order_id ),
				array( 'source' => 'review-automation' )
			);
			return false;
		}

		$settings = self::get_settings();

		if ( empty( $settings['email1_enabled'] ) ) {
			return false;
		}

		$to      = $order->get_billing_email();
		$subject = self::replace_placeholders( $settings['email1_subject'], $order );
		$body    = self::replace_placeholders( $settings['email1_body'], $order );

		$template_file = RA_PATH . 'templates/email-review-request.php';
		$html_content  = self::load_template( $template_file, $body );

		// Wrap in WooCommerce email shell.
		$wrapped = WC()->mailer()->wrap_message( $subject, $html_content );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$result = WC()->mailer()->send( $to, $subject, $wrapped, $headers, array() );

		if ( $result ) {
			wc_get_logger()->info(
				sprintf( 'Review request email sent for order #%d to %s.', $order_id, $to ),
				array( 'source' => 'review-automation' )
			);
		} else {
			wc_get_logger()->error(
				sprintf( 'Failed to send review request email for order #%d to %s.', $order_id, $to ),
				array( 'source' => 'review-automation' )
			);
		}

		return (bool) $result;
	}

	/**
	 * Send the follow-up review email (Email 2).
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return bool True on success, false on failure.
	 */
	public static function send_review_followup( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_get_logger()->error(
				sprintf( 'send_review_followup: Order #%d not found.', $order_id ),
				array( 'source' => 'review-automation' )
			);
			return false;
		}

		$settings = self::get_settings();

		if ( empty( $settings['email2_enabled'] ) ) {
			return false;
		}

		$to      = $order->get_billing_email();
		$subject = self::replace_placeholders( $settings['email2_subject'], $order );
		$body    = self::replace_placeholders( $settings['email2_body'], $order );

		$template_file = RA_PATH . 'templates/email-review-followup.php';
		$html_content  = self::load_template( $template_file, $body );

		// Wrap in WooCommerce email shell.
		$wrapped = WC()->mailer()->wrap_message( $subject, $html_content );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$result = WC()->mailer()->send( $to, $subject, $wrapped, $headers, array() );

		if ( $result ) {
			wc_get_logger()->info(
				sprintf( 'Review follow-up email sent for order #%d to %s.', $order_id, $to ),
				array( 'source' => 'review-automation' )
			);
		} else {
			wc_get_logger()->error(
				sprintf( 'Failed to send review follow-up email for order #%d to %s.', $order_id, $to ),
				array( 'source' => 'review-automation' )
			);
		}

		return (bool) $result;
	}

	/**
	 * Send a test email using dummy order data.
	 *
	 * @param string $to_email Recipient email address.
	 * @return bool True on success, false on failure.
	 */
	public static function send_test_email( $to_email ) {
		$settings = self::get_settings();

		$current_date = date_i18n( get_option( 'date_format' ) );

		// Build anchor tags for review links (same logic as replace_placeholders).
		$link_1 = ! empty( $settings['review_link_1_url'] )
			? '<a href="' . esc_url( $settings['review_link_1_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $settings['review_link_1_label'] ) . '</a>'
			: esc_html( $settings['review_link_1_label'] );

		$link_2 = ! empty( $settings['review_link_2_url'] )
			? '<a href="' . esc_url( $settings['review_link_2_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $settings['review_link_2_label'] ) . '</a>'
			: esc_html( $settings['review_link_2_label'] );

		$link_3 = ! empty( $settings['review_link_3_url'] )
			? '<a href="' . esc_url( $settings['review_link_3_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $settings['review_link_3_label'] ) . '</a>'
			: esc_html( $settings['review_link_3_label'] );

		$replacements = array(
			'{customer_name}' => 'Test Customer',
			'{order_id}'      => '12345',
			'{order_date}'    => $current_date,
			'{review_link_1}' => $link_1,
			'{review_link_2}' => $link_2,
			'{review_link_3}' => $link_3,
			'{shop_name}'     => esc_html( get_bloginfo( 'name' ) ),
		);

		$subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['email1_subject'] );
		$body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['email1_body'] );

		$template_file = RA_PATH . 'templates/email-review-request.php';

		if ( file_exists( $template_file ) ) {
			ob_start();
			include $template_file;
			$html_content = ob_get_clean();
		} else {
			$html_content = '<div>' . wp_kses_post( wpautop( $body ) ) . '</div>';
		}

		// Wrap in WooCommerce email shell.
		$wrapped = WC()->mailer()->wrap_message( $subject, $html_content );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$result = WC()->mailer()->send( $to_email, '[TEST] ' . $subject, $wrapped, $headers, array() );

		wc_get_logger()->info(
			sprintf(
				'Test email %s to %s.',
				$result ? 'sent successfully' : 'failed to send',
				$to_email
			),
			array( 'source' => 'review-automation' )
		);

		return (bool) $result;
	}
}

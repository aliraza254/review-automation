<?php
/**
 * Plugin Name: Review Automation
 * Plugin URI:  https://github.com/aliraza254/
 * Description: Automatically sends review request emails to customers after their order is marked as Delivered by TrackShip.
 * Version:     1.0.0
 * Author:      Muhammad Ali Raza
 * Text Domain: review-automation
 * Domain Path: /languages
 *
 * @package Review_Automation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if WooCommerce is active. If not, show an admin notice and bail.
 */
add_action( 'plugins_loaded', 'ra_init' );

function ra_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ra_wc_missing_notice' );
		return;
	}

	// Define constants.
	define( 'RA_VERSION', '1.0.0' );
	define( 'RA_PATH', plugin_dir_path( __FILE__ ) );
	define( 'RA_URL', plugin_dir_url( __FILE__ ) );

	// Include core classes.
	require_once RA_PATH . 'includes/class-settings.php';
	require_once RA_PATH . 'includes/class-mailer.php';

	// Instantiate the settings class (registers admin menu & settings).
	new RA_Settings();

	// Log ALL order status changes to find the correct delivered slug.
	add_action( 'woocommerce_order_status_changed', 'ra_log_status_change', 10, 4 );

	// Hook into WooCommerce delivered status (provided by TrackShip).
	add_action( 'woocommerce_order_status_delivered', 'ra_on_delivered', 10, 2 );

	// Cron action hooks.
	add_action( 'ra_send_review_request', 'ra_send_review_request' );
	add_action( 'ra_send_review_followup', 'ra_send_review_followup' );

	// Hidden test cron trigger (admin-only URL param).
	add_action( 'init', 'ra_maybe_run_test_cron' );
}

/**
 * Admin notice when WooCommerce is not active.
 */
function ra_wc_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: WooCommerce plugin name */
					__( '<strong>Review Automation</strong> requires %s to be installed and active.', 'review-automation' ),
					'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Log every order status change to help identify the correct delivered slug.
 *
 * @param int    $order_id   Order ID.
 * @param string $from       Previous status slug (without wc- prefix).
 * @param string $to         New status slug (without wc- prefix).
 * @param object $order      Order object.
 */
function ra_log_status_change( $order_id, $from, $to, $order ) {
	wc_get_logger()->info(
		sprintf( 'Order #%d status changed: %s → %s', $order_id, $from, $to ),
		array( 'source' => 'review-automation' )
	);
}

/**
 * Fired when an order status changes to "delivered".
 *
 * @param int      $order_id Order ID.
 * @param WC_Order $order    Order object.
 */
function ra_on_delivered( $order_id, $order ) {
	$logger = wc_get_logger();
	$logger->info( sprintf( 'ra_on_delivered triggered for order #%d.', $order_id ), array( 'source' => 'review-automation' ) );

	$settings = ( new RA_Settings() )->get_settings();

	// Bail if the plugin is disabled.
	if ( empty( $settings['enabled'] ) ) {
		$logger->info( sprintf( 'Order #%d: plugin disabled, skipping.', $order_id ), array( 'source' => 'review-automation' ) );
		return;
	}

	// Store delivered timestamp (only once).
	if ( ! $order->get_meta( '_ra_delivered_at' ) ) {
		$order->update_meta_data( '_ra_delivered_at', current_time( 'timestamp' ) );
		$order->save();
	}

	// Get configured delays.
	$email1_delay = isset( $settings['email1_delay'] ) ? absint( $settings['email1_delay'] ) : 1;
	$email2_delay = isset( $settings['email2_delay'] ) ? absint( $settings['email2_delay'] ) : 7;

	// Handle Email 1 (review request).
	if ( ! empty( $settings['email1_enabled'] ) ) {
		if ( '1' !== $order->get_meta( '_ra_review_request_sent' ) ) {
			if ( 0 === $email1_delay ) {
				// Send immediately
				RA_Mailer::send_review_request( $order_id );
				$order->update_meta_data( '_ra_review_request_sent', '1' );
				$order->save();
				$logger->info( sprintf( 'Order #%d: email 1 sent immediately.', $order_id ), array( 'source' => 'review-automation' ) );
			} else {
				// Schedule
				if ( ! wp_next_scheduled( 'ra_send_review_request', array( $order_id ) ) ) {
					wp_schedule_single_event( time() + $email1_delay * DAY_IN_SECONDS, 'ra_send_review_request', array( $order_id ) );
					$logger->info( sprintf( 'Order #%d: email 1 scheduled for %d days.', $order_id, $email1_delay ), array( 'source' => 'review-automation' ) );
				} else {
					$logger->info( sprintf( 'Order #%d: email 1 already scheduled, skipping.', $order_id ), array( 'source' => 'review-automation' ) );
				}
			}
		} else {
			$logger->info( sprintf( 'Order #%d: email 1 already sent, skipping.', $order_id ), array( 'source' => 'review-automation' ) );
		}
	} else {
		$logger->info( sprintf( 'Order #%d: email 1 disabled, skipping.', $order_id ), array( 'source' => 'review-automation' ) );
	}

	// Schedule Email 2 (follow-up).
	if ( ! empty( $settings['email2_enabled'] ) ) {
		if ( '1' !== $order->get_meta( '_ra_review_followup_sent' ) ) {
			if ( ! wp_next_scheduled( 'ra_send_review_followup', array( $order_id ) ) ) {
				wp_schedule_single_event( time() + $email2_delay * DAY_IN_SECONDS, 'ra_send_review_followup', array( $order_id ) );
				$logger->info( sprintf( 'Order #%d: email 2 scheduled for %d days.', $order_id, $email2_delay ), array( 'source' => 'review-automation' ) );
			} else {
				$logger->info( sprintf( 'Order #%d: email 2 already scheduled, skipping.', $order_id ), array( 'source' => 'review-automation' ) );
			}
		} else {
			$logger->info( sprintf( 'Order #%d: email 2 already sent, skipping.', $order_id ), array( 'source' => 'review-automation' ) );
		}
	} else {
		$logger->info( sprintf( 'Order #%d: email 2 disabled, skipping.', $order_id ), array( 'source' => 'review-automation' ) );
	}
}

/**
 * Cron callback: send the initial review request email.
 *
 * @param int $order_id Order ID.
 */
function ra_send_review_request( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order || ! $order->get_billing_email() ) {
		wc_get_logger()->warning(
			sprintf( 'Review request skipped — order #%d not found or no billing email.', $order_id ),
			array( 'source' => 'review-automation' )
		);
		return;
	}

	// Bail if already sent.
	if ( '1' === $order->get_meta( '_ra_review_request_sent' ) ) {
		return;
	}

	$settings = ( new RA_Settings() )->get_settings();

	// Bail if plugin or email 1 is disabled.
	if ( empty( $settings['enabled'] ) || empty( $settings['email1_enabled'] ) ) {
		return;
	}

	RA_Mailer::send_review_request( $order_id );

	$order->update_meta_data( '_ra_review_request_sent', '1' );
	$order->save();
}

/**
 * Cron callback: send the follow-up review email.
 *
 * @param int $order_id Order ID.
 */
function ra_send_review_followup( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order || ! $order->get_billing_email() ) {
		wc_get_logger()->warning(
			sprintf( 'Review follow-up skipped — order #%d not found or no billing email.', $order_id ),
			array( 'source' => 'review-automation' )
		);
		return;
	}

	// Bail if already sent.
	if ( '1' === $order->get_meta( '_ra_review_followup_sent' ) ) {
		return;
	}

	$settings = ( new RA_Settings() )->get_settings();

	// Bail if plugin or email 2 is disabled.
	if ( empty( $settings['enabled'] ) || empty( $settings['email2_enabled'] ) ) {
		return;
	}

	// Check if the customer has already left a review for any product in the order.
	$billing_email = $order->get_billing_email();
	$items         = $order->get_items();

	foreach ( $items as $item ) {
		$product_id = $item->get_product_id();
		$reviews    = get_comments(
			array(
				'post_id'      => $product_id,
				'author_email' => $billing_email,
				'status'       => 'approve',
				'type'         => 'review',
			)
		);

		if ( ! empty( $reviews ) ) {
			wc_get_logger()->info(
				sprintf(
					'Review follow-up skipped for order #%d — customer already left a review for product #%d.',
					$order_id,
					$product_id
				),
				array( 'source' => 'review-automation' )
			);
			return;
		}
	}

	RA_Mailer::send_review_followup( $order_id );

	$order->update_meta_data( '_ra_review_followup_sent', '1' );
	$order->save();
}

/**
 * Hidden admin test trigger.
 * Usage: /wp-admin/?ra_test_cron=1&order_id=123
 */
function ra_maybe_run_test_cron() {
	if (
		! isset( $_GET['ra_test_cron'] ) ||
		! isset( $_GET['order_id'] ) ||
		! current_user_can( 'manage_options' )
	) {
		return;
	}

	$order_id = absint( $_GET['order_id'] );

	if ( ! $order_id ) {
		return;
	}

	// Bypass sent-flags: send directly via the Mailer class.
	RA_Mailer::send_review_request( $order_id );
	RA_Mailer::send_review_followup( $order_id );

	wp_die(
		esc_html__( 'Test emails sent. Check your inbox and WooCommerce logs.', 'review-automation' ),
		esc_html__( 'Review Automation Test', 'review-automation' ),
		array( 'response' => 200 )
	);
}

/**
 * Plugin activation — placeholder for future use.
 */
register_activation_hook( __FILE__, 'ra_activate' );
function ra_activate() {
	// Nothing required on activation at this time.
}

/**
 * Plugin deactivation — clear all scheduled cron events.
 */
register_deactivation_hook( __FILE__, 'ra_deactivate' );
function ra_deactivate() {
	wp_clear_scheduled_hook( 'ra_send_review_request' );
	wp_clear_scheduled_hook( 'ra_send_review_followup' );
}

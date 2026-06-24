<?php
/**
 * Settings class for Review Automation plugin.
 *
 * Registers the WooCommerce submenu page and handles all plugin settings.
 *
 * @package Review_Automation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RA_Settings
 */
class RA_Settings {

	/**
	 * Option name used to store settings in the database.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'ra_review_automation_settings';

	/**
	 * Constructor — wire up WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_ra_test_email', array( $this, 'send_test_email' ) );
	}

	/**
	 * Add a submenu page under WooCommerce.
	 */
	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Review Automation', 'review-automation' ),
			__( 'Review Automation', 'review-automation' ),
			'manage_woocommerce',
			'review-automation',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the settings group.
	 */
	public function register_settings() {
		register_setting(
			'ra_review_automation_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Return default settings values.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			'enabled'              => 1,
			'delivery_status_slug' => 'delivered',
			'review_link_1_label'  => 'Google',
			'review_link_1_url'    => 'https://maps.app.goo.gl/Yf3FxdxDdUpwPHdn9',
			'review_link_2_label'  => 'FeedbackCompany',
			'review_link_2_url'    => 'https://www.feedbackcompany.com/nl-nl/reviews/tapijten-kelim',
			'review_link_3_label'  => 'Trustpilot',
			'review_link_3_url'    => 'https://nl.trustpilot.com/review/tapijtenkelim.nl',
			'email1_enabled'       => 1,
			'email1_delay'         => 1,
			'email1_subject'       => "How was your order? We'd love your feedback!",
			'email1_body'          => "Hi {customer_name},\n\nYour order #{order_id} has been delivered. We hope you love it!\n\nWe'd really appreciate it if you could take a moment to leave us a review. It helps us a lot!\n\nLeave a review here:\n- Google: {review_link_1}\n- FeedbackCompany: {review_link_2}\n- Trustpilot: {review_link_3}\n\nThank you so much!\n{shop_name}",
			'email2_enabled'       => 1,
			'email2_delay'         => 7,
			'email2_subject'       => 'Still time to share your experience!',
			'email2_body'          => "Hi {customer_name},\n\nWe noticed you haven't had a chance to leave a review for order #{order_id} yet.\n\nWe'd still love to hear what you think! It only takes a minute.\n\nLeave a review here:\n- Google: {review_link_1}\n- FeedbackCompany: {review_link_2}\n- Trustpilot: {review_link_3}\n\nThanks again for shopping with us!\n{shop_name}",
		);
	}

	/**
	 * Return merged settings (saved values merged with defaults).
	 *
	 * @return array
	 */
	public function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $saved, $this->get_defaults() );
	}

	/**
	 * Render the admin settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'review-automation' ) );
		}

		$settings = $this->get_settings();

		// Show saved/error notices.
		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
			add_settings_error(
				'ra_messages',
				'ra_saved',
				__( 'Settings saved successfully.', 'review-automation' ),
				'updated'
			);
		}
		if ( isset( $_GET['test_email_sent'] ) ) {
			if ( '1' === $_GET['test_email_sent'] ) {
				add_settings_error(
					'ra_messages',
					'ra_test_sent',
					__( 'Test email sent successfully.', 'review-automation' ),
					'updated'
				);
			} else {
				add_settings_error(
					'ra_messages',
					'ra_test_failed',
					__( 'Test email could not be sent. Please check your mail configuration.', 'review-automation' ),
					'error'
				);
			}
		}

		settings_errors( 'ra_messages' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Review Automation Settings', 'review-automation' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'ra_review_automation_group' ); ?>

				<!-- ===================== GENERAL ===================== -->
				<h2><?php esc_html_e( 'General', 'review-automation' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ra_enabled"><?php esc_html_e( 'Enable Plugin', 'review-automation' ); ?></label>
						</th>
						<td>
							<input
								type="checkbox"
								id="ra_enabled"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]"
								value="1"
								<?php checked( 1, ! empty( $settings['enabled'] ) ); ?>
							/>
							<label for="ra_enabled"><?php esc_html_e( 'Enable the review automation emails', 'review-automation' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ra_delivery_status_slug"><?php esc_html_e( 'Delivery Status Slug', 'review-automation' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="ra_delivery_status_slug"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delivery_status_slug]"
								value="<?php echo esc_attr( $settings['delivery_status_slug'] ); ?>"
								class="regular-text"
							/>
							<p class="description">
								<?php esc_html_e( 'The order status slug that triggers the emails (e.g. "delivered" set by TrackShip).', 'review-automation' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<!-- ===================== REVIEW LINKS ===================== -->
				<h2><?php esc_html_e( 'Review Links', 'review-automation' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					for ( $i = 1; $i <= 3; $i++ ) :
						$label_key = "review_link_{$i}_label";
						$url_key   = "review_link_{$i}_url";
						?>
					<tr>
						<th scope="row">
							<?php
							/* translators: %d: review link number */
							echo esc_html( sprintf( __( 'Review Link %d', 'review-automation' ), $i ) );
							?>
						</th>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $label_key ); ?>]"
								value="<?php echo esc_attr( $settings[ $label_key ] ); ?>"
								placeholder="<?php esc_attr_e( 'Label', 'review-automation' ); ?>"
								class="regular-text"
								style="margin-bottom:4px;"
							/>
							<br />
							<input
								type="url"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $url_key ); ?>]"
								value="<?php echo esc_attr( $settings[ $url_key ] ); ?>"
								placeholder="https://"
								class="large-text"
							/>
							<p class="description">
								<?php
								/* translators: %d: placeholder number */
								echo esc_html( sprintf( __( 'Used as {review_link_%d} in email templates.', 'review-automation' ), $i ) );
								?>
							</p>
						</td>
					</tr>
					<?php endfor; ?>
				</table>

				<!-- ===================== EMAIL 1 ===================== -->
				<h2><?php esc_html_e( 'Email 1 — Review Request', 'review-automation' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ra_email1_enabled"><?php esc_html_e( 'Enable', 'review-automation' ); ?></label>
						</th>
						<td>
							<input
								type="checkbox"
								id="ra_email1_enabled"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email1_enabled]"
								value="1"
								<?php checked( 1, ! empty( $settings['email1_enabled'] ) ); ?>
							/>
							<label for="ra_email1_enabled"><?php esc_html_e( 'Send the initial review request email after delivery', 'review-automation' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ra_email1_delay"><?php esc_html_e( 'Send Delay (Days)', 'review-automation' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="ra_email1_delay"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email1_delay]"
								value="<?php echo esc_attr( isset( $settings['email1_delay'] ) ? $settings['email1_delay'] : 1 ); ?>"
								class="small-text"
								min="0"
								step="1"
							/>
							<p class="description">
								<?php esc_html_e( 'Number of days to wait after delivery before sending the first email (use 0 to send immediately).', 'review-automation' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ra_email1_subject"><?php esc_html_e( 'Subject', 'review-automation' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="ra_email1_subject"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email1_subject]"
								value="<?php echo esc_attr( $settings['email1_subject'] ); ?>"
								class="large-text"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ra_email1_body"><?php esc_html_e( 'Body', 'review-automation' ); ?></label>
						</th>
						<td>
							<p class="description" style="margin-bottom:8px;">
								<strong><?php esc_html_e( 'Available placeholders:', 'review-automation' ); ?></strong><br />
								<code>{customer_name}</code> — <?php esc_html_e( "Customer's first name", 'review-automation' ); ?><br />
								<code>{order_id}</code> — <?php esc_html_e( 'Order number', 'review-automation' ); ?><br />
								<code>{order_date}</code> — <?php esc_html_e( 'Order date', 'review-automation' ); ?><br />
								<code>{review_link_1}</code>, <code>{review_link_2}</code>, <code>{review_link_3}</code> — <?php esc_html_e( 'Review platform links (clickable anchors)', 'review-automation' ); ?><br />
								<code>{shop_name}</code> — <?php esc_html_e( 'Your site name', 'review-automation' ); ?>
							</p>
							<textarea
								id="ra_email1_body"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email1_body]"
								rows="12"
								class="large-text"
							><?php echo esc_textarea( $settings['email1_body'] ); ?></textarea>
						</td>
					</tr>
				</table>

				<!-- ===================== EMAIL 2 ===================== -->
				<h2><?php esc_html_e( 'Email 2 — Follow-up', 'review-automation' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ra_email2_enabled"><?php esc_html_e( 'Enable', 'review-automation' ); ?></label>
						</th>
						<td>
							<input
								type="checkbox"
								id="ra_email2_enabled"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email2_enabled]"
								value="1"
								<?php checked( 1, ! empty( $settings['email2_enabled'] ) ); ?>
							/>
							<label for="ra_email2_enabled"><?php esc_html_e( 'Send a follow-up email after delivery if no review has been left', 'review-automation' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ra_email2_delay"><?php esc_html_e( 'Send Delay (Days)', 'review-automation' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="ra_email2_delay"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email2_delay]"
								value="<?php echo esc_attr( isset( $settings['email2_delay'] ) ? $settings['email2_delay'] : 7 ); ?>"
								class="small-text"
								min="1"
								step="1"
							/>
							<p class="description">
								<?php esc_html_e( 'Number of days to wait after delivery before sending the follow-up email (must be at least 1).', 'review-automation' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ra_email2_subject"><?php esc_html_e( 'Subject', 'review-automation' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="ra_email2_subject"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email2_subject]"
								value="<?php echo esc_attr( $settings['email2_subject'] ); ?>"
								class="large-text"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ra_email2_body"><?php esc_html_e( 'Body', 'review-automation' ); ?></label>
						</th>
						<td>
							<p class="description" style="margin-bottom:8px;">
								<strong><?php esc_html_e( 'Available placeholders:', 'review-automation' ); ?></strong><br />
								<code>{customer_name}</code>, <code>{order_id}</code>, <code>{order_date}</code>,
								<code>{review_link_1}</code>, <code>{review_link_2}</code>, <code>{review_link_3}</code>,
								<code>{shop_name}</code>
							</p>
							<textarea
								id="ra_email2_body"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email2_body]"
								rows="12"
								class="large-text"
							><?php echo esc_textarea( $settings['email2_body'] ); ?></textarea>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'review-automation' ) ); ?>
			</form>

			<!-- ===================== TEST EMAIL ===================== -->
			<hr />
			<h2><?php esc_html_e( 'Send Test Email', 'review-automation' ); ?></h2>
			<p><?php esc_html_e( 'Send a test version of Email 1 (Review Request) to verify your configuration.', 'review-automation' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ra_test_email" />
				<?php wp_nonce_field( 'ra_test_email_nonce', 'ra_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ra_test_email"><?php esc_html_e( 'Send to Email', 'review-automation' ); ?></label>
						</th>
						<td>
							<input
								type="email"
								id="ra_test_email"
								name="test_email"
								value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
								class="regular-text"
								required
							/>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Send Test Email', 'review-automation' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the test email form POST.
	 */
	public function send_test_email() {
		// Verify nonce.
		if (
			! isset( $_POST['ra_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ra_nonce'] ) ), 'ra_test_email_nonce' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'review-automation' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'review-automation' ) );
		}

		$to_email = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : get_option( 'admin_email' );

		if ( ! is_email( $to_email ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'            => 'review-automation',
						'test_email_sent' => '0',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$result = RA_Mailer::send_test_email( $to_email );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'review-automation',
					'test_email_sent' => $result ? '1' : '0',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw input from the form.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['enabled']              = ! empty( $input['enabled'] ) ? 1 : 0;
		$sanitized['delivery_status_slug'] = isset( $input['delivery_status_slug'] ) ? sanitize_text_field( wp_unslash( $input['delivery_status_slug'] ) ) : 'delivered';

		// Review links.
		for ( $i = 1; $i <= 3; $i++ ) {
			$label_key = "review_link_{$i}_label";
			$url_key   = "review_link_{$i}_url";

			$sanitized[ $label_key ] = isset( $input[ $label_key ] ) ? sanitize_text_field( wp_unslash( $input[ $label_key ] ) ) : '';
			$sanitized[ $url_key ]   = isset( $input[ $url_key ] ) ? esc_url_raw( wp_unslash( $input[ $url_key ] ) ) : '';
		}

		// Email 1.
		$sanitized['email1_enabled'] = ! empty( $input['email1_enabled'] ) ? 1 : 0;
		$sanitized['email1_delay']   = isset( $input['email1_delay'] ) ? absint( $input['email1_delay'] ) : 1;
		$sanitized['email1_subject'] = isset( $input['email1_subject'] ) ? sanitize_text_field( wp_unslash( $input['email1_subject'] ) ) : '';
		$sanitized['email1_body']    = isset( $input['email1_body'] ) ? wp_kses_post( wp_unslash( $input['email1_body'] ) ) : '';

		// Email 2.
		$sanitized['email2_enabled'] = ! empty( $input['email2_enabled'] ) ? 1 : 0;
		$sanitized['email2_delay']   = isset( $input['email2_delay'] ) ? max( 1, absint( $input['email2_delay'] ) ) : 7;
		$sanitized['email2_subject'] = isset( $input['email2_subject'] ) ? sanitize_text_field( wp_unslash( $input['email2_subject'] ) ) : '';
		$sanitized['email2_body']    = isset( $input['email2_body'] ) ? wp_kses_post( wp_unslash( $input['email2_body'] ) ) : '';

		return $sanitized;
	}
}

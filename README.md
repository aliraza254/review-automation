# Review Automation

**Plugin Name:** Review Automation  
**Version:** 1.0.0  
**Author:** Stackians  
**Plugin URI:** https://stackians.com/

## Description

Automatically sends review request emails to customers after their WooCommerce order is marked as **Delivered** by TrackShip. Supports two configurable email stages and checks for existing reviews before sending the follow-up.

---

## Requirements

- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- TrackShip plugin (provides the `wc-delivered` order status)
- A working email/SMTP configuration on your WordPress site

---

## Installation

1. Upload the `review-automation` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins > Installed Plugins** in the WordPress admin.
3. Navigate to **WooCommerce > Review Automation** to configure the plugin.

---

## Configuration

Go to **WooCommerce > Review Automation** and configure the following sections:

### General
- **Enable Plugin** — Master switch to turn all review automation on or off.
- **Delivery Status Slug** — The order status slug that triggers the emails. Default: `delivered` (set by TrackShip).

### Review Links
Configure up to three review platform links (label + URL). These are inserted as clickable anchor tags using the `{review_link_1}`, `{review_link_2}`, and `{review_link_3}` placeholders.

Default platforms pre-configured:
- **Google** — `https://maps.app.goo.gl/Yf3FxdxDdUpwPHdn9`
- **FeedbackCompany** — `https://www.feedbackcompany.com/nl-nl/reviews/tapijten-kelim`
- **Trustpilot** — `https://nl.trustpilot.com/review/tapijtenkelim.nl`

### Email 1 — Review Request
- Sent **1 day** after the order status changes to Delivered.
- Configure: Enable/Disable, Subject line, Body text.

### Email 2 — Follow-up
- Sent **2 days** after the order status changes to Delivered.
- Only sent if the customer has **not** already left a product review.
- Configure: Enable/Disable, Subject line, Body text.

---

## How the Trigger Works

```
TrackShip marks order as Delivered
        ↓
woocommerce_order_status_delivered hook fires
        ↓
Plugin stores delivery timestamp on the order
        ↓
Two WP-Cron events are scheduled:
  - ra_send_review_request  (+1 day)
  - ra_send_review_followup (+2 days)
        ↓
Cron fires → checks flags → sends email via WC Mailer
```

The plugin uses WP-Cron for scheduling. For reliable cron execution in production it is strongly recommended to disable the default WP-Cron and set up a real server cron job:

```bash
# Example: run every 5 minutes
*/5 * * * * php /var/www/html/wp-cron.php
```

Or add to `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

---

## Available Placeholders

Use these placeholders in the **Subject** and **Body** fields of both email templates:

| Placeholder       | Replaced with                                      |
|-------------------|----------------------------------------------------|
| `{customer_name}` | Customer's billing first name                      |
| `{order_id}`      | WooCommerce order number                           |
| `{order_date}`    | Order creation date (formatted per site settings)  |
| `{review_link_1}` | Clickable anchor link for Review Link 1            |
| `{review_link_2}` | Clickable anchor link for Review Link 2            |
| `{review_link_3}` | Clickable anchor link for Review Link 3            |
| `{shop_name}`     | Your site name (from WordPress General Settings)   |

---

## Testing

### Method 1: Test Email Button (Admin)

1. Go to **WooCommerce > Review Automation**.
2. Scroll to the **Send Test Email** section at the bottom.
3. Enter your email address and click **Send Test Email**.
4. This sends a test version of Email 1 using dummy data (`Test Customer`, order `#12345`).

### Method 2: Admin URL Parameter (Bypasses Sent Flags)

To trigger both emails directly for a real order, visit the following URL while logged in as an administrator:

```
https://yoursite.com/wp-admin/?ra_test_cron=1&order_id=YOUR_ORDER_ID
```

Replace `YOUR_ORDER_ID` with a real WooCommerce order ID. This calls both `send_review_request` and `send_review_followup` directly via the `RA_Mailer` class, bypassing the "already sent" meta flag checks entirely. The page will display a confirmation message when complete.

### Checking Logs

All email activity is logged via WooCommerce's logging system. To view:

1. Go to **WooCommerce > Status > Logs**.
2. Select the log source `review-automation` from the dropdown.

---

## Deactivation Behavior

When the plugin is deactivated:
- All pending scheduled WP-Cron events for `ra_send_review_request` and `ra_send_review_followup` are cleared.
- No order data or settings are deleted (settings are preserved for reactivation).

To fully remove plugin data, delete the plugin and manually remove the `ra_review_automation_settings` option from the `wp_options` table if needed.

---

## Order Meta Keys

The plugin stores the following meta data on orders:

| Meta Key                            | Value | Description                              |
|-------------------------------------|-------|------------------------------------------|
| `_ra_delivered_at`           | Unix timestamp | When the order was marked delivered |
| `_ra_review_request_sent`    | `1`   | Set after Email 1 is sent               |
| `_ra_review_followup_sent`   | `1`   | Set after Email 2 is sent               |

---

## Support

For support, visit [https://tapijtenkelim.nl](https://tapijtenkelim.nl).

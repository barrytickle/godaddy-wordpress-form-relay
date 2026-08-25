# Tango Form Wire

Form Relay connects an ordinary HTML form to WordPress and delivers each submission by email. It is intended for forms hosted on the same site and does not require an external form service or API key by default. Optional Cloudflare Turnstile protection can be enabled for sites that want an additional browser-level spam check.

Valid frontend submissions are also saved in **Form Relay > Submissions**. The WordPress-style list shows the Form, submitted name and email, delivery status, and submission time. Each record opens into a detail view, and administrators can filter or delete saved submissions. Successful and failed mail attempts are both retained so an email outage does not discard an enquiry.

Email can be delivered through the normal WordPress mail configuration, a GoDaddy/cPanel local SMTP preset, or a custom SMTP server. This allows Form Relay to work with an existing SMTP plugin as well as directly configured hosting mail.

## Basic setup

1. Install and activate the plugin.
2. Open **Form Relay** in WordPress and create a form.
3. Set the recipient, sender details, email subject and templates.
4. Copy the generated form attribute into the opening HTML `<form>` tag:

   ```html
   <form data-form-relay="YOUR_FORM_ID">
   ```

The sender email is split into two settings. **Sender Email** is only the part before the `@`, such as `wordpress` or `enquiries`. **Sender Domain** is the domain after it. Together they produce an address such as `wordpress@example.com`.

Use a real mailbox or an address that your hosting account is authorised to send from. The visitor's submitted email address is used as the Reply-To address, not the From address. This helps prevent SPF and DMARC failures.

## Email troubleshooting

If the form displays its thank-you message but no email arrives, work through the checks below. A successful form response means the hosting mail server accepted the message from the plugin; it does not guarantee that the recipient's provider ultimately delivered it.

### 1. Check the form settings

- Confirm the form is enabled and accepting submissions.
- Check that **Recipient Email** contains the inbox where submissions should arrive.
- Check the complete sender address shown by **Sender Email** and **Sender Domain**.
- Make sure that sender address belongs to a domain configured on the hosting account.
- Check the recipient's spam, junk and quarantine folders.

Using a dummy visitor address such as `test@test.com` should not prevent the message from being sent. It only affects the Reply-To header. For a proper test, however, use an address you control so replies and validation are predictable.

### 2. Confirm that the sender mailbox exists

On cPanel hosting, open **Email Accounts** and check that the complete sender address exists, for example `wordpress@example.com`.

The recipient and sender have different purposes:

- **Recipient Email** is where form submissions are delivered.
- **Sender Email** and **Sender Domain** form the address the hosting server sends from.

They do not need to be the same address.

### 3. Check cPanel Email Routing

If mail for the sender domain is hosted on the same cPanel server, open **Email Routing** and make sure the domain is configured as **Local Mail Exchanger**.

If it is incorrectly set to **Remote Mail Exchanger**, cPanel may try to route locally hosted mail somewhere else. Only use the remote setting when the domain's mail is genuinely handled by another provider.

### 4. Check DNS and email authentication

In cPanel, open **Email Deliverability** and review the sender domain. Correct any reported SPF or DKIM problems. If the domain uses DMARC, make sure its policy is compatible with the address and server being used.

Also check that the domain's MX records point to the service that actually handles its email. DNS changes can take time to propagate.

### 5. Use Track Delivery

Open **Track Delivery** in cPanel and search for the recipient address.

- A successful entry means cPanel handed the message onwards.
- A deferred or failed entry should include the remote server's reason.
- A bounce received by the sender mailbox usually contains the most useful SMTP error.
- No entry at all suggests the message never reached Exim, the search filters are too narrow, or outbound mail is being blocked before normal delivery tracking.

When contacting the host, include the full SMTP error and its timestamp. That is considerably more useful than saying only that the email did not arrive.

### 6. Look for an account-level outbound block

Errors such as the following are imposed by the hosting provider and cannot be fixed by changing the form template:

```text
552 5.2.0 Access to this mail system has been blocked due to spam activity
```

If this happens:

1. Stop repeated test submissions.
2. Scan the WordPress installation and hosting account for malicious or unfamiliar PHP files.
3. Verify WordPress core checksums and inspect plugins, themes, administrator accounts and cron jobs.
4. Change any credentials that may have been exposed and enable two-factor authentication.
5. Ask the hosting provider to review and remove the outbound mail restriction after the account has been cleaned.

Changing the sender address will not bypass an account-level block. The host must lift it.

### 7. Test local and external delivery separately

If possible, temporarily send one test to a mailbox hosted on the same cPanel account and another to an external provider such as Gmail.

- Local delivery working while external delivery fails usually points to outbound routing, authentication, reputation or a hosting restriction.
- Both tests failing suggests a local mailbox, routing or SMTP configuration problem.

Avoid submitting dozens of identical tests in quick succession. That can resemble spam and makes the delivery logs harder to read.

## Templates and responses

Email templates support placeholders including `{{form_name}}`, `{{site_name}}`, `{{page_title}}`, `{{page_url}}`, `{{submitted_at}}` and `{{fields}}`. Field row templates support `{{field_key}}`, `{{field_label}}` and `{{field_value}}`.

The form editor includes a preview button that renders the current template with dummy submission data without sending an email.

Forms can display an inline thank-you message or redirect to a selected WordPress page. Error templates support `{{error_message}}`, `{{error_code}}` and `{{form_name}}`. Inline responses are inserted at the end of the form and can be given custom CSS classes.

## Stored submission data

Submissions are stored in a dedicated database table for the current WordPress site and are visible only to administrators with the `manage_options` capability. Records remain until they are deleted from **Form Relay > Submissions**. Because submitted fields may contain personal data, site owners should include them in their privacy and retention policy.

## Spam protection

Every form uses the built-in same-origin and nonce checks, honeypot, IP rate limiting, duplicate detection and payload limits. These protections require no account or external service.

Cloudflare Turnstile can be enabled as an optional additional layer:

1. Create a Managed Turnstile widget in the [Cloudflare dashboard](https://dash.cloudflare.com/?to=/:account/turnstile).
2. Open **Form Relay > Cloudflare Turnstile**, enable the integration and paste the Site Key and Secret Key.
3. Edit a Form and enable Turnstile in its **Cloudflare Turnstile** block.
4. Choose **Before the submit button** or **Manual placement**, then save.

For better secret handling, define the key in `wp-config.php` instead of storing it in the WordPress database:

```php
define( 'TANGO_FORM_WIRE_TURNSTILE_SECRET', 'your-secret-key' );
```

The default location inserts the widget immediately before the first submit button. For Manual placement, add an empty marker anywhere inside the form:

```html
<div data-form-relay-captcha></div>
```

Turnstile is loaded only on pages containing a Form configured to require it. The browser connects to Cloudflare to obtain a verification token, and WordPress sends that token to Cloudflare's Siteverify API. Form field contents are not sent to Cloudflare by Tango Form Wire. See the [Turnstile documentation](https://developers.cloudflare.com/turnstile/), [Cloudflare Privacy Policy](https://www.cloudflare.com/privacypolicy/) and [Cloudflare Website and Online Services Terms](https://www.cloudflare.com/website-terms/).

## Developer hooks

Filters: `form_relay_submission_data`, `form_relay_email_subject`, `form_relay_email_html`, `form_relay_from_email`, `form_relay_smtp_host`, `form_relay_smtp_port`, `form_relay_error_code`, `form_relay_error_message`, `form_relay_duplicate_window`, `form_relay_max_fields`, `form_relay_max_field_name_length`, `form_relay_max_field_value_length`, `form_relay_max_payload_size`.

Actions: `form_relay_before_send`, `form_relay_after_send`.

## Changelog

### 1.10.0

- Added optional Cloudflare Turnstile protection while keeping the built-in no-account spam defences active.
- Added a dedicated settings page with guided setup and secure global credential storage.
- Added per-form enablement with automatic or manual widget placement.
- Added mandatory server-side verification, friendly errors, token resets and rate limiting for failed challenges.

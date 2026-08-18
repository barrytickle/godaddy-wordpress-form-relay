=== Form Relay ===
Contributors: form-relay-contributors
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.8.0
License: GPLv2 or later

Handle same-site HTML form submissions through wp_mail().

== Installation ==
Activate Form Relay and open Form Relay in wp-admin. Use the familiar Forms list and Add New Form screen to create or edit a form, configure delivery, and copy its immutable Form ID.

Add data-form-relay="FORM_ID" to an existing form on the same WordPress site. Form Relay's frontend script detects it, submits it using a WordPress nonce, and displays the configured success or safe public error message. No custom JavaScript, PHP, response element, API key, or external relay configuration is needed.

Each Form has independent templates, success and error messages, behaviour, ignored fields, enabled status, and IP-based rate limits. Protection includes same-site checks, nonce validation, a honeypot, duplicate-submission detection, payload limits, sanitisation, and escaping.

Responses can show an inline Thank You Message or redirect to a selected WordPress Thank You Page. Inline thank you and error elements are inserted as the final child inside the form and accept optional custom CSS classes. Error Message templates support {{error_message}}, {{error_code}} and {{form_name}}; Thank You Messages support {{form_name}}.

The Main Email Template supports {{form_name}}, {{site_name}}, {{page_title}}, {{page_url}}, {{submitted_at}} and {{fields}}. The {{fields}} Placeholder is the concatenated output of the Field Row Template repeated for every non-ignored Submitted Field. The Field Row Template supports {{field_key}}, {{field_label}} and {{field_value}}. Full examples and quick-insert controls are available in wp-admin.

The Form editor includes an Email Preview modal. It renders the current unsaved templates and subject with generic dummy data without sending an email.

Outgoing messages use the configured Sender Name, Sender Email and Sender Domain. The Sender Email is the local part before the @, while Sender Domain defaults to the current WordPress site domain. The default Sender Name is {{site_name}} Enquiries and supports {{site_name}} and {{form_name}}. A valid submitted email address is used as Reply-To, preferring the configured Reply-To Field and otherwise detecting the first valid email Submitted Field. The visitor's address is never used as From, which protects SPF and DMARC deliverability.

Email delivery can use the WordPress default mail configuration, a GoDaddy/cPanel local SMTP preset at 127.0.0.1:25, or a custom SMTP server. New installations default to WordPress mail so existing SMTP plugins and hosting configuration continue to work. Installations upgrading from an earlier Form Relay release retain the local SMTP behaviour. Custom SMTP supports port, encryption and optional authentication; the password can be stored in WordPress or defined as FORM_RELAY_SMTP_PASSWORD in wp-config.php. The form_relay_smtp_host and form_relay_smtp_port filters can override the relay address.

While a frontend submission is being sent, Form Relay inserts an accessible inline loading indicator as the final content inside the form. It is removed when the configured success or error response is ready.

== Developer hooks ==
Filters: form_relay_submission_data, form_relay_email_subject, form_relay_email_html, form_relay_from_email, form_relay_smtp_host, form_relay_smtp_port, form_relay_error_code, form_relay_error_message, form_relay_duplicate_window, form_relay_max_fields, form_relay_max_field_name_length, form_relay_max_field_value_length, form_relay_max_payload_size.
Actions: form_relay_before_send, form_relay_after_send.

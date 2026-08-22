=== Tango Form Wire ===
Contributors: barrytickle, tangodevelopment
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.1
License: GPLv2 or later

Connect developer-built HTML forms to secure WordPress email delivery without replacing your markup.

== Description ==

Tango Form Wire is built for theme and site developers who already have their own form markup. Add one generated data attribute to an existing same-site HTML form, then configure its recipient, email templates, responses, delivery method, and abuse protection in WordPress.

The plugin does not generate or replace form HTML. It enhances the form you have already built and inserts loading, success, and error feedback inside that form.

Features include:

* Multiple independently configured forms.
* HTML email and field-row templates with documented placeholders.
* Inline thank-you messages or redirects to a WordPress page.
* Custom response classes for theme integration.
* WordPress default mail, GoDaddy/cPanel local SMTP, and custom SMTP delivery.
* Same-site and nonce checks, a honeypot, payload limits, rate limiting, and duplicate protection.
* Email previews with dummy data and an optional metadata-only diagnostic log.
* A WordPress-style Submissions screen that retains successful and failed delivery attempts.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install its ZIP through Plugins > Add New > Upload Plugin.
2. Activate Tango Form Wire.
3. Open Form Relay in wp-admin and create or edit a form.
4. Copy its Form Attribute into the opening tag of your existing HTML form.
5. Configure the recipient, responses, templates, and email delivery method, then save.

Add data-form-relay="FORM_ID" to an existing form on the same WordPress site. Form Relay's frontend script detects it, submits it using a WordPress nonce, and displays the configured success or safe public error message. No custom JavaScript, PHP, response element, API key, or external relay configuration is needed.

Each Form has independent templates, success and error messages, behaviour, ignored fields, enabled status, and IP-based rate limits. Protection includes same-site checks, nonce validation, a honeypot, duplicate-submission detection, payload limits, sanitisation, and escaping.

Responses can show an inline Thank You Message or redirect to a selected WordPress Thank You Page. Inline thank you and error elements are inserted as the final child inside the form and accept optional custom CSS classes. Error Message templates support {{error_message}}, {{error_code}} and {{form_name}}; Thank You Messages support {{form_name}}.

The Main Email Template supports {{form_name}}, {{site_name}}, {{page_title}}, {{page_url}}, {{submitted_at}} and {{fields}}. The {{fields}} Placeholder is the concatenated output of the Field Row Template repeated for every non-ignored Submitted Field. The Field Row Template supports {{field_key}}, {{field_label}} and {{field_value}}. Full examples and quick-insert controls are available in wp-admin.

The Form editor includes an Email Preview modal. It renders the current unsaved templates and subject with generic dummy data without sending an email.

Outgoing messages use the configured Sender Name, Sender Email and Sender Domain. The Sender Email is the local part before the @, while Sender Domain defaults to the current WordPress site domain. The default Sender Name is {{site_name}} Enquiries and supports {{site_name}} and {{form_name}}. A valid submitted email address is used as Reply-To, preferring the configured Reply-To Field and otherwise detecting the first valid email Submitted Field. The visitor's address is never used as From, which protects SPF and DMARC deliverability.

Email delivery can use the WordPress default mail configuration, a GoDaddy/cPanel local SMTP preset at 127.0.0.1:25, or a custom SMTP server. New installations default to WordPress mail so existing SMTP plugins and hosting configuration continue to work. Installations upgrading from an earlier Form Relay release retain the local SMTP behaviour. Custom SMTP supports port, encryption and optional authentication; the password can be stored in WordPress or defined as FORM_RELAY_SMTP_PASSWORD in wp-config.php. The form_relay_smtp_host and form_relay_smtp_port filters can override the relay address.

While a frontend submission is being sent, Form Relay inserts an accessible inline loading indicator as the final content inside the form. It is removed when the configured success or error response is ready.

Each valid frontend submission is stored in the current site's dedicated Form Relay submissions table after email delivery is attempted. Open Form Relay > Submissions to filter by Form or delivery status, inspect submitted fields, and delete individual or multiple records. Failed deliveries are stored so an enquiry is not lost when email delivery is unavailable.

== Developer hooks ==
Filters: form_relay_submission_data, form_relay_email_subject, form_relay_email_html, form_relay_from_email, form_relay_smtp_host, form_relay_smtp_port, form_relay_error_code, form_relay_error_message, form_relay_duplicate_window, form_relay_max_fields, form_relay_max_field_name_length, form_relay_max_field_value_length, form_relay_max_payload_size.
Actions: form_relay_before_send, form_relay_after_send.

== Frequently Asked Questions ==

= Does this plugin create the form markup? =

No. It is designed for developers who have already built a form in a theme or template. The plugin connects that existing form to WordPress by means of a `data-form-relay` attribute.

= Can I use my existing SMTP plugin? =

Yes. Select WordPress Default as the delivery method and Tango Form Wire will use the normal WordPress mail configuration, including a compatible SMTP plugin.

= Does it support multisite? =

Yes. Settings are stored per site, so each site in a network can have its own forms and delivery configuration.

= Does metadata logging store submitted message content? =

No. The optional diagnostic log stores metadata such as time, form, outcome, and error code. It does not store submitted field values.

= Are submitted fields stored in WordPress? =

Yes. Valid frontend submissions are stored in a dedicated per-site database table and are visible only to administrators with the `manage_options` capability. They remain until an administrator deletes them from Form Relay > Submissions. Site owners should account for this personal data in their privacy and retention policy.

== Screenshots ==

1. The Forms list in wp-admin, showing all configured forms.
2. Editing a form's email templates and delivery settings.
3. A form's configuration fields for recipient and behaviour.

== Changelog ==

= 1.9.1 =

* Renamed the plugin to Tango Form Wire and updated the readme contributors list.

= 1.9.0 =

* Added a Submissions submenu with a WordPress-style list, filtering, pagination, bulk deletion, and submission detail views.
* Store both successful and failed email delivery attempts in a dedicated per-site database table.

= 1.8.0 =

* Added selectable WordPress, local cPanel, and custom SMTP delivery methods.
* Added delivery diagnostics and WordPress.org submission metadata.
* Improved sender configuration, form responses, email previewing, and frontend loading feedback.

# Tango Form Wire: context for coding assistants

This file records the important product and engineering decisions behind Tango Form Wire (formerly published as "Developer Form Relay"). Read it before changing the plugin. It is context, not a substitute for inspecting the current code and diff.

## Product purpose

Tango Form Wire is a lightweight WordPress plugin for developers who have already coded their own HTML form. It deliberately does not provide a drag-and-drop form builder or generate frontend form markup.

A theme developer adds one immutable attribute to an existing same-site form:

```html
<form data-form-relay="FORM_ID">
```

The plugin intercepts the submission, validates and sanitises its fields, sends a templated HTML email, then either displays an inline response inside the form or redirects to a selected thank-you page.

The intended public positioning is: **bring your own HTML; let WordPress handle secure submission and delivery**.

## Repository and release conventions

- The public plugin display name is **Tango Form Wire** (slug and text domain `tango-form-wire`). The shorter **Form Relay** label remains in wp-admin, and internal hooks, classes, constants and the database table keep the `form_relay`/`Form_Relay` prefix by design — see "WordPress.org readiness" below for why only the public name/slug/text domain changed.
- The current version is declared in `tango-form-wire.php` and must match `Stable tag` in `readme.txt`.
- Development uses GitHub and `main`. WordPress.org's plugin directory is a separate SVN repo (`https://plugins.svn.wordpress.org/tango-form-wire/`) — GitHub commits do not automatically publish there.
- `bin/release-to-svn.sh` automates syncing the git repo's shipped files into an SVN working copy at `.svn-wc/` (gitignored), cutting a `tags/<version>` snapshot, and committing. It refuses to re-tag a version that's already published, and refuses to run if the plugin header version and readme `Stable tag` don't match. Run `--no-commit` to stage without publishing. It does not touch `assets/` (icon, banner, screenshots) — those live only in the SVN working copy and are managed by hand, since they aren't tracked in git. The script must be run interactively by the user in their own terminal, never by an assistant on their behalf, since `svn commit` needs their WordPress.org password (cached afterwards via macOS Keychain, since this environment's `svn` build links `Security.framework`).
- Build installable archives with a single top-level plugin directory named `tango-form-wire/`, main file `tango-form-wire.php` (the slug is now granted on WordPress.org). While the slug was still pending, the archive briefly had to stay on the old `developer-form-relay/`/`developer-form-relay.php` naming — renaming it early caused their upload processor to report "no Plugin Name" even though the in-file header parsed fine.
- Do not commit credentials, private keys, real recipient addresses, production domains, SMTP passwords or incident logs.
- Public defaults and documentation must use generic names, domains and email addresses.
- Do not deploy to a live site or push changes unless the user explicitly asks.

## Code map

- `tango-form-wire.php`: plugin header, version constants and bootstrap.
- `includes/class-form-relay.php`: defaults, migrations, stored settings and frontend asset configuration.
- `includes/class-form-relay-rest.php`: public REST submission endpoint, nonce/origin checks, rate limiting, honeypot and response handling.
- `includes/class-form-relay-service.php`: submission normalisation, mail headers and transport selection.
- `includes/class-form-relay-renderer.php`: placeholder expansion and safe HTML email rendering.
- `includes/class-form-relay-submissions.php`: per-site submission table installation, persistence, queries and deletion.
- `admin/class-form-relay-admin.php`: Forms list, post-style editor, settings persistence, test email and preview markup.
- `admin/js/admin.js`: admin interactions and client-side email preview.
- `assets/form-relay.js`: frontend interception, payload creation, loader and success/error behaviour.
- `readme.txt`: WordPress.org-format documentation.
- `README.md`: human-facing GitHub documentation and mail troubleshooting.

The codebase currently favours compact PHP. Preserve the existing style unless a broader formatting change is intentional and agreed.

## Behaviour that must be preserved

- Form IDs are immutable when a form is renamed.
- The integration label is **Form Attribute**, not Form ID.
- A disabled form remains configured but rejects frontend submissions.
- Frontend success/error elements and the loading indicator are inserted using `beforeend` inside the form.
- Successful submissions can either show a message or redirect to a WordPress page, never both.
- Success and error elements support administrator-supplied CSS class names.
- The email preview uses current unsaved templates, generic dummy contact data and the real WordPress site name. It must not send an email.
- `{{fields}}` represents the concatenated rendered field rows, not a single submitted value.
- Visitor email addresses are used only for Reply-To. Never use a visitor-controlled address as From.
- Sender Name defaults to `{{site_name}} Enquiries`.
- Sender Email is the local part and Sender Domain is the domain. Together they create the From address.
- Public responses must never reveal PHPMailer, SMTP or other internal server errors.
- Optional logs contain delivery metadata only, not submitted form contents.
- Valid frontend submissions are stored after email delivery is attempted, including failed delivery attempts, and are visible only to administrators.
- The plugin must continue to work on WordPress multisite. Settings are stored per site through normal WordPress options.

## Mail delivery architecture

Version 1.8 introduced three global delivery methods:

1. `wordpress`: normal `wp_mail()` behaviour. This is the default for fresh installations and respects hosting configuration and SMTP plugins.
2. `local`: GoDaddy/cPanel-compatible local SMTP at `127.0.0.1:25`, without authentication or encryption.
3. `custom`: administrator-provided SMTP host, port, encryption and optional authentication.

Installations upgrading from a version before 1.8 must migrate to `local`, because local SMTP was previously hard-coded. Do not change that migration casually or existing sites may stop sending mail.

Custom SMTP passwords may be saved in the WordPress option, but the preferred production configuration is the `FORM_RELAY_SMTP_PASSWORD` constant in `wp-config.php`. Password values must never be rendered back into the admin page or logs.

PHPMailer hooks are attached only for the duration of Form Relay's individual `wp_mail()` call and must always be removed in a `finally` block. Form Relay must not globally alter unrelated WordPress email.

## Security model

The public endpoint is intentionally available to logged-out visitors. Its protection is layered:

- WordPress REST nonce verification
- same-origin scheme, host and port check
- immutable configured form ID
- enabled/disabled status
- honeypot field
- IP-based rate limiting
- duplicate-submission fingerprints
- maximum payload, field count, name length, value length and nesting depth
- recursive sanitisation
- context-appropriate escaping during email rendering

Nonces are not authentication, particularly for logged-out users. Do not remove the other controls or describe the nonce as sufficient access control.

All admin mutations require `manage_options` and `check_admin_referer( 'form_relay_admin' )`.

Continue to follow WordPress's rules:

- validate against explicit allow-lists where possible;
- unslash request data before sanitising it;
- sanitise before storage or use;
- escape late according to the output context;
- use `wp_kses()`/`wp_kses_post()` only where limited HTML is intended;
- never expose internal mail errors to frontend visitors.

## WordPress.org readiness

The version 1.9 submission bundle completed the official Plugin Check plugin's General, Plugin Repo, Security, Performance and Accessibility categories with zero findings on 19 August 2026. The bundle deliberately excludes this file, `README.md` and other repository-only material.

On 22 August 2026 the WordPress.org Plugins Team pended that submission over two issues: the display name/slug ("Form Relay" was flagged as too close to existing third-party form-relay services, e.g. formrelay.com and formrelay.app) and a contributors/ownership mismatch (readme listed `barrytickle` as sole contributor while the submitting account is `tangodevelopment`, both owned by the same person). In response, the plugin was renamed to **Tango Form Wire** (slug/text domain `tango-form-wire`), and `tangodevelopment` was added to the readme `Contributors` list alongside `barrytickle`. This was a deliberately minimal rename: internal hook names, class names, constants and the database table keep the `form_relay` prefix; only the public plugin name, slug, text domain, and the `add_menu_page()` page title (not the wp-admin menu label) changed.

The current public name is **Tango Form Wire** and the expected slug/text domain is `tango-form-wire`. The plugin header, final escaping boundaries and WordPress.org `readme.txt` have been updated for resubmission.

Future release work should still:

- keep all new user-facing strings translation-ready with the `tango-form-wire` text domain;
- preserve stored submissions on uninstall unless a future, clearly documented data-removal option is intentionally added;
- consider moving REST nonce/origin validation into a dedicated `permission_callback` if the response contract can be preserved;
- add directory artwork and screenshots after approval and final branding.

Do not claim that the plugin is WordPress.org-approved or fully compliant until the official review has passed.

## Validation before handing off changes

At minimum, run:

```sh
for file in tango-form-wire.php includes/*.php admin/*.php; do php -l "$file" || exit 1; done
node --check admin/js/admin.js
node --check assets/form-relay.js
git diff --check
```

For mail changes, test each affected delivery method separately. A true return value from `wp_mail()` or PHPMailer means the configured server accepted the message; it does not guarantee final inbox delivery. Check the receiving inbox and the hosting provider's delivery logs when diagnosing deliverability.

For frontend changes, verify successful submission, validation failure, server failure, duplicate detection, rate limiting, loader cleanup, button re-enabling, inline responses and thank-you redirects.

For admin changes, verify saving, reload persistence, conditional panels, test email, preview rendering and multisite behaviour.

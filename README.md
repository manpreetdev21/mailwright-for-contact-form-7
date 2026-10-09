# Mailwright for Contact Form 7

Reusable, brandable email templates for Contact Form 7 — design once, assign to any form, and keep every submission.

| | |
|---|---|
| **Version** | 2.0.0 |
| **Requires** | WordPress 6.5+, PHP 8.1+, Contact Form 7 5.8+ |
| **Tested up to** | WordPress 7.1, Contact Form 7 6.2 |
| **License** | GPL-2.0-or-later |

---

## What it does

Contact Form 7 keeps doing what it does best — rendering, validating and submitting forms, and sending the mail. This plugin manages what those emails **look like**, and keeps a record of what people sent.

> **Your Contact Form 7 settings are never overwritten.** Templates are applied at runtime, so a form's own Mail configuration stays exactly as you left it and takes over again the moment you detach a template.

## Features

### Templates

- Reusable HTML and plain-text email templates
- **Visual block builder** — drag Heading, Text, Form Fields, Button, Image, Divider and Spacer blocks into the email and reorder them
- Hand-written HTML can be converted to blocks, and any template can still be edited as source on the HTML tab
- Separate admin-notification and customer-confirmation templates per form
- Preview with realistic sample data, plus test sends
- Duplicate, search, filter, bulk actions, JSON import and export
- Nine starter templates, installable at any time from **Tools → Demo Templates**

### Tags

- Every mail-tag on a form is detected through Contact Form 7's own API
- Click-to-insert tags with friendly names, search and a recently-used list
- A warning when a template uses a tag the form does not have — nothing is ever removed for you
- A notice when a form gains new fields the template is not using yet
- All of Contact Form 7 special mail-tags, including the `[_user_*]` set for logged-in visitors
- File upload fields are detected, and the uploaded file is attached to the email

### Submissions

- Every accepted submission is saved to its own database table, with the email result (**sent** / **not sent**) recorded alongside it
- Pick a form to get one column per field; **Details** expands the whole record in place
- Uploaded files are copied out of Contact Form 7 before it deletes them, then served through a capability-checked download link. The store itself is closed to the web
- Search across stored answers, filter by email result, sort, paginate, delete individually or in bulk
- **Export to CSV** — exports everything the current filters match, not just the page on screen, with one column per field

### Branding

- One place for logo, colours, footer text, address and social links, used by every template
- Branding values that are left empty leave no gap in the email — the paragraph, and the row around it, are dropped
- A warning when the logo is hosted somewhere only your own machine can reach, such as `localhost`

## Installation

1. Install and activate **Contact Form 7**.
2. Copy this plugin into `wp-content/plugins/` and activate it.
3. Open **Mailwright** in the admin menu.

The nine starter templates are installed the first time an editor opens the admin with Contact Form 7 active. You can add any that are missing later from **Tools → Demo Templates**.

## How it works

| Concern | Owner |
|---|---|
| Rendering, validation, spam checks, sending | Contact Form 7 |
| What the email looks like | This plugin |
| Where the mail goes | Your SMTP plugin, unchanged |

Templates are injected through the `wpcf7_contact_form_properties` filter when a form is used — they are never written into Contact Form 7's database. The plugin also stands down on Contact Form 7's own admin screens, so editing the Mail tab there behaves exactly as it always did.

## Uploaded files

Contact Form 7 deletes its copy of an upload as soon as the request ends, so each submission keeps its own copy under `wp-content/uploads/mwright-submissions/`.

- Each submission gets an unguessable folder
- The directory is closed to the web with an `.htaccess` deny rule and an `index.html`
- The only route to a file is an authenticated, nonce-checked admin download
- File types WordPress does not allow are recorded by name but never written to disk
- Deleting a submission deletes its files

## Development

Run the end-to-end smoke test against a real WordPress install with Contact Form 7 active:

```bash
php tests/smoke-test.php
```

It creates a throwaway form, template and submission, exercises the real code paths, and cleans up after itself.

## FAQ

**Does this change my Contact Form 7 mail settings?**
No. Templates are injected when a form is used, not written into Contact Form 7's database. Detaching a template restores your original settings instantly, because they were never touched.

**What if I edit the Mail tab while a template is assigned?**
Nothing breaks. The plugin steps aside on Contact Form 7's own screens, so saving there edits your Contact Form 7 settings as usual. A notice tells you a template is currently in charge of what actually gets sent.

**Will my SMTP plugin still work?**
Yes. Contact Form 7 still sends the mail through WordPress, so any SMTP plugin keeps working. This plugin never stores or displays mail credentials.

**Why is my logo missing from the email I received?**
Almost always because the logo URL points at a host the recipient cannot reach — `localhost` and `*.local` are the usual culprits. It renders in the admin preview because your browser is on that machine. Global Branding warns you when it spots one.

**What happens to my data if I delete the plugin?**
Templates, submissions and stored files all stay. They are only removed if you tick **Delete all plugin data** in Settings → Advanced first.

## Changelog

### 2.0.0
- Renamed the plugin to **Mailwright for Contact Form 7**. Existing templates, submissions, branding and settings migrate automatically on the first admin page load.
- Added the `Requires Plugins` header, so WordPress 6.5+ checks for Contact Form 7 before activation.
- The missing-dependency notice now appears only on this plugin's screens and the plugins list.

### 1.4.2
- Clean pass on the WordPress.org Plugin Check: no errors, and the variable-prefix warnings in the admin screens are gone too.
- Uninstall now removes the upload folder through `WP_Filesystem` rather than calling `rmdir()` directly.
- `php bin/build-zip.php` builds the distributable zip from `.distignore`, so development and hidden files are never shipped.

### 1.4.1
- Checked against Contact Form 7 6.2. Nothing this plugin relies on has changed: the properties filter, the submission API, the mail property keys and the rule that leaves a complete HTML document unwrapped all behave as before.
- Added the special mail-tags CF7 supports that the sidebar was not offering: `[_post_id]`, `[_post_name]`, `[_post_author_email]` and the seven `[_user_*]` tags, each with sample data for Preview.
- New compatibility checks in the smoke test, so a future CF7 release that moves one of these shows up immediately.

### 1.4.0
- New **Export CSV** button on the Submissions screen. It exports every row the current form, email-result and search filters match, with one column per field and the uploaded file names.
- Cells that open with `=`, `+`, `-` or `@` are written as plain text, so an exported answer cannot run as a formula when the file is opened in Excel or Google Sheets.

### 1.3.1
- Fix: a file upload field showed Contact Form 7's internal digest as if it were the visitor's answer. Upload fields are now recorded only as files, and older rows hide the digest too.
- **Submitted Data** in the list is now a **Details** button that expands the whole submission in place, instead of a summary that ran off the edge of the table.
- The submission screen and the expanded row share one renderer, so they always agree.

### 1.3.0
- Refreshed the whole admin interface: a new colour system, rounded cards, pill filters and badges, icon tiles on the dashboard, and consistent form controls. No screen changed what it does.
- WordPress's own buttons, search boxes and bulk-action menus inside these screens now match the rest of the plugin.
- Fix: a warning shown inside a field rendered inline and overlapped the controls around it.
- Every grey used for text now clears the 4.5:1 contrast minimum.

### 1.2.2
- Fix: a branding value left empty no longer leaves a gap in the email. The paragraph it would have filled is dropped, and so is the whole row when that is all the row held.
- Branding now warns when the logo is hosted somewhere only this machine can reach, such as `localhost` — it looks right in the preview but arrives blank in the inbox.

### 1.2.1
- Fix: demo templates were never installed when both plugins were activated together, or when Contact Form 7 was installed after this plugin. They are now added on the first admin page load once Contact Form 7 is active.
- New **Install demo templates** button under Tools. It only adds starters that are missing, so it is safe to run again.

### 1.2.0
- New **Submissions** screen: every accepted Contact Form 7 submission is saved to its own database table, with the email result recorded alongside it.
- Pick a form to see one column per field; open a submission for the full answers, uploaded files and IP address.
- Uploaded files are copied out of Contact Form 7 before it deletes them, and downloaded through a capability-checked link; the store itself is closed to the web.
- Works with every Contact Form 7 form, including ones added later.

### 1.1.0
- Visual block builder for HTML templates: drag Heading, Text, Form Fields, Button, Image, Divider and Spacer blocks into the email and reorder them.
- Hand-written templates can be converted to blocks from the Visual tab.
- The HTML tab is unchanged, so any template can still be edited as source.

### 1.0.0
- Initial release.

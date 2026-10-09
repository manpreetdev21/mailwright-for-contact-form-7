=== Mailwright for Contact Form 7 ===
Contributors: manpreetdev21
Tags: contact form 7, email template, html email, email designer, submissions
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reusable, brandable email templates for Contact Form 7. Design once, assign to any form, and keep every submission.

== Description ==

Mailwright for Contact Form 7 is a template layer for Contact Form 7. Contact Form 7 keeps doing what it does best — rendering, validating and submitting forms, and sending the mail. This plugin manages what those emails look like, and keeps a record of what people sent.

**Your Contact Form 7 settings are never overwritten.** Templates are applied at runtime, so your form's own Mail configuration stays exactly as you left it and takes over again the moment you detach a template.

= Templates =

* Reusable HTML and plain-text email templates
* Visual block builder — drag Heading, Text, Form Fields, Button, Image, Divider and Spacer blocks into the email and reorder them
* Hand-written HTML can be converted to blocks, and any template can still be edited as source on the HTML tab
* Separate admin-notification and customer-confirmation templates per form
* Preview with realistic sample data, plus test sends
* Duplicate, search, filter, bulk actions, JSON import and export
* Nine starter templates, installable at any time from Tools

= Tags =

* Every mail-tag on a form is detected through Contact Form 7's own API
* Click-to-insert tags with friendly names, search and a recently used list
* All of Contact Form 7's special mail-tags, including the [_user_*] set for logged-in visitors
* A warning when a template uses a tag the form does not have — nothing is ever removed for you
* A notice when a form gains new fields the template is not using yet
* File upload fields are detected, and the uploaded file is attached to the email

= Submissions =

* Every accepted submission is saved to its own database table, with the email result recorded alongside it
* Pick a form to get one column per field; Details expands the whole record in place
* Uploaded files are copied out of Contact Form 7 before it deletes them, then served through a capability-checked download link. The store itself is closed to the web
* Search across stored answers, filter by email result, sort, paginate, delete individually or in bulk
* Export to CSV — exports everything the current filters match, not just the page on screen

= Branding =

* One place for logo, colours, footer text, address and social links, used by every template
* Branding values that are left empty leave no gap in the email
* A warning when the logo is hosted somewhere only your own machine can reach, such as localhost

== Installation ==

1. Install and activate Contact Form 7.
2. Upload this plugin to `/wp-content/plugins/` and activate it.
3. Go to **Mailwright** in the admin menu.

The nine starter templates are installed the first time an editor opens the admin with Contact Form 7 active. You can add any that are missing later from Tools.

== Frequently Asked Questions ==

= Does this change my Contact Form 7 mail settings? =

No. Templates are injected when a form is used, not written into Contact Form 7's database. Detaching a template restores your original settings instantly, because they were never touched.

= What happens if I edit the Mail tab in Contact Form 7 while a template is assigned? =

Nothing breaks. The plugin steps aside on Contact Form 7's own screens, so saving there edits your Contact Form 7 settings as usual. A notice tells you a template is currently in charge of what actually gets sent.

= Will my SMTP plugin still work? =

Yes. Contact Form 7 still sends the mail through WordPress, so any SMTP plugin keeps working. This plugin never stores or displays mail credentials.

= Why is my logo missing from the email I received? =

Almost always because the logo URL points at a host the recipient cannot reach — localhost and .local addresses are the usual culprits. It renders in the admin preview because your browser is on that machine. Global Branding warns you when it spots one.

= What happens to my data if I delete the plugin? =

Templates, submissions and stored files all stay. They are only removed if you tick "Delete all plugin data" in Settings → Advanced first.

== Screenshots ==

1. The template editor, with the visual block builder and the available mail-tags.
2. The submissions log, with one column per form field.
3. Global branding, shared by every template.

== Changelog ==

= 2.0.0 =
* Renamed the plugin to Mailwright for Contact Form 7. Existing templates, submissions, branding and settings are migrated automatically on the first admin page load.
* Added the Requires Plugins header, so WordPress 6.5+ checks for Contact Form 7 before activation.
* The missing-dependency notice now appears only on this plugin's screens and the plugins list.

= 1.4.2 =
* Clean pass on the WordPress.org Plugin Check: no errors, and the variable-prefix warnings in the admin screens are gone too.
* Uninstall now removes the upload folder through WP_Filesystem rather than calling rmdir() directly.
* The distributed zip is built from .distignore, so development files and hidden files are never shipped.

= 1.4.1 =
* Checked against Contact Form 7 6.2. Nothing this plugin relies on has changed.
* Added the special mail-tags CF7 supports that the sidebar was not offering: [_post_id], [_post_name], [_post_author_email] and the seven [_user_*] tags, each with sample data for Preview.
* New compatibility checks in the smoke test, so a future CF7 release that moves one of these shows up immediately.

= 1.4.0 =
* New Export CSV button on the Submissions screen. It exports every row the current form, email-result and search filters match, with one column per field and the uploaded file names.
* Cells that open with =, +, - or @ are written as plain text, so an exported answer cannot run as a formula when the file is opened in a spreadsheet.

= 1.3.1 =
* Fix: a file upload field showed Contact Form 7's internal digest as if it were the visitor's answer. Upload fields are now recorded only as files.
* Submitted Data in the list is now a Details button that expands the whole submission in place.

= 1.3.0 =
* Refreshed the whole admin interface: a new colour system, rounded cards, pill filters and badges, icon tiles on the dashboard, and consistent form controls.
* WordPress's own buttons, search boxes and bulk-action menus inside these screens now match the rest of the plugin.
* Fix: a warning shown inside a field rendered inline and overlapped the controls around it.
* Every grey used for text now clears the 4.5:1 contrast minimum.

= 1.2.2 =
* Fix: a branding value left empty no longer leaves a gap in the email.
* Branding now warns when the logo is hosted somewhere only this machine can reach.

= 1.2.1 =
* Fix: demo templates were never installed when both plugins were activated together, or when Contact Form 7 was installed after this plugin.
* New "Install demo templates" button under Tools.

= 1.2.0 =
* New Submissions screen: every accepted Contact Form 7 submission is saved to its own database table, with the email result recorded alongside it.
* Uploaded files are copied out of Contact Form 7 before it deletes them, and downloaded through a capability-checked link.

= 1.1.0 =
* Visual block builder for HTML templates.
* Hand-written templates can be converted to blocks from the Visual tab.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.4.1 =
Compatibility pass for Contact Form 7 6.2, plus the special mail-tags that were missing from the editor sidebar.

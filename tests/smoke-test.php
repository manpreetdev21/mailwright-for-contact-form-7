<?php
/**
 * End-to-end smoke test.
 *
 * Runs against the real WordPress install and the real Contact Form 7, because
 * the whole plugin hangs off CF7's own APIs — mocking them would only test the
 * mock. Creates a throwaway form and template, then cleans both up.
 *
 * Usage:  php tests/smoke-test.php
 *
 * @package Mailwright_For_Contact_Form_7
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 'Run this from the command line.' );
}

$root = dirname( __DIR__, 4 );

// Needed so the "stand down on CF7 admin screens" guard can be exercised.
define( 'WP_ADMIN', true );

require_once $root . '/wp-load.php';

$failures = 0;
$checks   = 0;

/**
 * Asserts a condition and reports it.
 *
 * @param string $label     What is being checked.
 * @param bool   $condition Result.
 * @param string $detail    Extra context shown on failure.
 */
function mwright_check( $label, $condition, $detail = '' ) {
	global $failures, $checks;

	++$checks;

	if ( $condition ) {
		echo "  PASS  $label\n";
		return;
	}

	++$failures;
	echo "  FAIL  $label" . ( $detail ? "\n        $detail" : '' ) . "\n";
}

echo "\nMailwright for Contact Form 7 — smoke test\n";
echo str_repeat( '-', 60 ) . "\n";

mwright_check( 'Contact Form 7 is active', MWRIGHT_Plugin::cf7_supported() );
mwright_check( 'Template post type registered', post_type_exists( 'mwright_template' ) );

/* -------------------------------------------------------------------------
 * Fixtures
 * ---------------------------------------------------------------------- */

$form = WPCF7_ContactForm::get_template( array( 'title' => 'MWRIGHT Smoke Form' ) );

$form->set_properties(
	array(
		'form' => "[text* your-name]\n[email* your-email]\n[tel your-phone]\n[textarea your-message]\n[file* your-resume]\n[file docs]",
		'mail' => array(
			'subject'            => 'ORIGINAL SUBJECT',
			'sender'             => 'Original <original@example.com>',
			'recipient'          => 'original-recipient@example.com',
			'body'               => 'ORIGINAL BODY',
			'additional_headers' => 'Reply-To: [your-email]',
			'attachments'        => '',
			'use_html'           => 0,
			'exclude_blank'      => 0,
		),
	)
);

$form_id = $form->save();

mwright_check( 'Test contact form created', $form_id > 0 );

$template_id = MWRIGHT_Template_Post_Type::save(
	array(
		'name'          => 'MWRIGHT Smoke Template',
		'type'          => 'html',
		'status'        => 'publish',
		'subject'       => 'New enquiry from [your-name]',
		'preview_text'  => 'Someone contacted you',
		'body'          => '<!doctype html><html><body><table><tr><td>Hello [your-name] at [mwright_company_name], reply to [your-email]. [company]</td></tr></table></body></html>',
		'headers'       => 'Reply-To: [your-email]',
		'attachments'   => "[your-resume]
../../wp-config.php
[9bad]
[docs]
[your-resume]",
		'exclude_blank' => 1,
	)
);

mwright_check( 'Template created', ! is_wp_error( $template_id ), is_wp_error( $template_id ) ? $template_id->get_error_message() : '' );

$stored = MWRIGHT_Template_Post_Type::get( $template_id );

/* -------------------------------------------------------------------------
 * Sanitising
 * ---------------------------------------------------------------------- */

mwright_check( 'Doctype survives sanitising', str_starts_with( strtolower( ltrim( $stored['body'] ) ), '<!doctype' ), $stored['body'] );
mwright_check( 'Email-safe HTML survives sanitising', str_contains( $stored['body'], '<table>' ) );
mwright_check( 'CF7 tags are preserved verbatim', str_contains( $stored['body'], '[your-name]' ) );

mwright_check(
	'Attachment spec keeps file tags and drops the rest',
	"[your-resume]\n[docs]" === $stored['attachments'],
	str_replace( "\n", ' | ', $stored['attachments'] )
);

mwright_check(
	'Attachment spec never accepts a bare file path',
	! str_contains( $stored['attachments'], 'wp-config' ),
	$stored['attachments']
);

mwright_check(
	'File flag derived from the attachment spec',
	1 === (int) get_post_meta( $template_id, '_mwright_has_files', true )
		&& MWRIGHT_Template_Post_Type::count_with_files() > 0
);

$xss = MWRIGHT_Template_Post_Type::sanitize_body( '<p onclick="evil()">hi</p><script>alert(1)</script><iframe src="x"></iframe>', 'html' );

mwright_check( 'Scripts stripped from bodies', ! str_contains( $xss, '<script' ) && ! str_contains( $xss, '<iframe' ) && ! str_contains( $xss, 'onclick' ), $xss );

// The visual builder stores what it needs in data attributes, so kses has to
// let them through or every saved layout comes back unreadable.
$blocks = MWRIGHT_Template_Post_Type::sanitize_body(
	'<table><tr><td data-mwright-block="text" data-align="center" style="padding:8px;">hi</td></tr></table>',
	'html'
);

mwright_check(
	'Builder block markers survive sanitising',
	str_contains( $blocks, 'data-mwright-block="text"' ) && str_contains( $blocks, 'data-align="center"' ),
	$blocks
);

/* -------------------------------------------------------------------------
 * Tag detection and validation
 * ---------------------------------------------------------------------- */

$mail_tags = MWRIGHT_CF7_Bridge::mail_tags( $form_id );

mwright_check(
	'CF7 mail-tags detected',
	array_diff( array( 'your-name', 'your-email', 'your-phone', 'your-message' ), $mail_tags ) === array(),
	implode( ', ', $mail_tags )
);

$file_types = MWRIGHT_CF7_Bridge::file_tag_types();

mwright_check(
	'File tag types come from CF7 own feature flag',
	array_diff( array( 'file', 'file*' ), $file_types ) === array(),
	implode( ', ', $file_types )
);

$file_fields = MWRIGHT_CF7_Bridge::file_fields( $form_id );

mwright_check(
	'File upload fields detected',
	array_diff( array( 'your-resume', 'docs' ), $file_fields ) === array() && 2 === count( $file_fields ),
	implode( ', ', $file_fields )
);

mwright_check(
	'Ordinary fields are not flagged as uploads',
	! array_intersect( array( 'your-name', 'your-email', 'your-message' ), $file_fields )
);

$bad_attach = MWRIGHT_CF7_Bridge::invalid_attachments( "[your-resume]\n[your-message]", $form_id );

mwright_check(
	'Attachment naming a non-file field is flagged',
	array( 'your-message' ) === $bad_attach,
	implode( ', ', $bad_attach )
);

$unknown = MWRIGHT_CF7_Bridge::unknown_tags( $stored['body'], $form_id );

mwright_check( 'Unknown tag [company] flagged', in_array( 'company', $unknown, true ), implode( ', ', $unknown ) );
mwright_check( 'Known and branding tags not flagged', ! array_intersect( array( 'your-name', 'your-email', 'mwright_company_name' ), $unknown ) );

$unused = MWRIGHT_CF7_Bridge::unused_tags( $stored['body'], $form_id );

mwright_check( 'Unused form tags reported', in_array( 'your-message', $unused, true ) && ! in_array( 'your-name', $unused, true ), implode( ', ', $unused ) );

mwright_check( 'Friendly labels derived', 'Email' === MWRIGHT_CF7_Bridge::friendly_label( 'your-email' ) );

/* -------------------------------------------------------------------------
 * Branding and sample rendering
 * ---------------------------------------------------------------------- */

$branded = MWRIGHT_Branding::replace( '[mwright_company_name] / [mwright_year]', true );

mwright_check( 'Branding tags resolved', ! str_contains( $branded, '[mwright_' ), $branded );

$sample = MWRIGHT_Renderer::sample_render_data( $stored, $form_id );

mwright_check( 'Sample render replaces form tags', ! str_contains( $sample['body'], '[your-name]' ) && str_contains( $sample['body'], 'John Smith' ) );
mwright_check( 'Sample render types email fields', str_contains( MWRIGHT_Renderer::sample_values( $form_id )['your-email'], '@' ) );
mwright_check( 'Preheader injected into HTML body', str_contains( $sample['body'], 'Someone contacted you' ) );

/* -------------------------------------------------------------------------
 * Assignment and runtime injection — the core guarantee
 * ---------------------------------------------------------------------- */

$original_meta = get_post_meta( $form_id, '_mail', true );

mwright_check( 'Assignment stored', true === MWRIGHT_CF7_Bridge::assign( $form_id, 'admin', $template_id ) );

$live = WPCF7_ContactForm::get_instance( $form_id );
$mail = $live->prop( 'mail' );

mwright_check( 'Template body injected into CF7', str_contains( $mail['body'], 'Hello [your-name]' ), substr( $mail['body'], 0, 80 ) );
mwright_check( 'Template subject injected', 'New enquiry from [your-name]' === $mail['subject'], $mail['subject'] );
mwright_check( 'HTML mode enabled for HTML templates', 1 === (int) $mail['use_html'] );
mwright_check( 'Exclude-blank carried across', 1 === (int) $mail['exclude_blank'] );
mwright_check( 'Recipient falls back to the form when the template is silent', 'original-recipient@example.com' === $mail['recipient'], $mail['recipient'] );
mwright_check( 'Template headers applied', str_contains( $mail['additional_headers'], 'Reply-To' ) );

mwright_check(
	'Attachment spec reaches CF7 mail property',
	str_contains( $mail['attachments'], '[your-resume]' )
		&& str_contains( $mail['attachments'], '[docs]' ),
	$mail['attachments']
);

/* An empty spec must leave whatever Contact Form 7 already had. */
update_post_meta( $template_id, '_mwright_attachments', '' );

$fallback = MWRIGHT_Renderer::to_mail_array(
	$template_id,
	array( 'attachments' => '[form-own-file]' ),
	'admin'
);

mwright_check(
	'Empty attachment spec falls back to the form own value',
	'[form-own-file]' === $fallback['attachments'],
	$fallback['attachments']
);

update_post_meta( $template_id, '_mwright_attachments', "[your-resume]\n[docs]" );

mwright_check(
	'CF7 database row is untouched',
	get_post_meta( $form_id, '_mail', true ) === $original_meta && 'ORIGINAL BODY' === $original_meta['body'],
	'CF7 mail meta changed — this must never happen'
);

/* Customer slot must switch CF7's mail_2 on. */
MWRIGHT_CF7_Bridge::assign( $form_id, 'customer', $template_id );

$live2  = WPCF7_ContactForm::get_instance( $form_id );
$mail_2 = $live2->prop( 'mail_2' );

mwright_check( 'Customer email (mail_2) activated', ! empty( $mail_2['active'] ) );
mwright_check( 'Customer recipient defaults to the visitor', str_contains( $mail_2['recipient'], '[your-email]' ), $mail_2['recipient'] );

/* -------------------------------------------------------------------------
 * The guard that stops CF7's own save() persisting our template
 * ---------------------------------------------------------------------- */

$_REQUEST['page'] = 'wpcf7';

$guarded = MWRIGHT_CF7_Bridge::filter_properties(
	array( 'mail' => $original_meta, 'mail_2' => array() ),
	WPCF7_ContactForm::get_instance( $form_id )
);

mwright_check(
	'Filter stands down on CF7 edit screens',
	'ORIGINAL BODY' === $guarded['mail']['body'],
	'CF7 admin save would overwrite the original mail config'
);

unset( $_REQUEST['page'] );

/* -------------------------------------------------------------------------
 * Inactive templates must not take over a live form
 * ---------------------------------------------------------------------- */

wp_update_post( array( 'ID' => $template_id, 'post_status' => 'private' ) );

mwright_check( 'Inactive template is not applied', null === MWRIGHT_Renderer::to_mail_array( $template_id, $original_meta, 'admin' ) );

wp_update_post( array( 'ID' => $template_id, 'post_status' => 'publish' ) );

/* -------------------------------------------------------------------------
 * Duplication, deletion guard and detach
 * ---------------------------------------------------------------------- */

$copy_id = MWRIGHT_Template_Post_Type::duplicate( $template_id );
$copy    = MWRIGHT_Template_Post_Type::get( $copy_id );

mwright_check( 'Duplicate copies the body', $copy && $copy['body'] === $stored['body'] );
mwright_check( 'Duplicate lands as a draft', $copy && 'draft' === $copy['status'] );
mwright_check( 'Duplicate copies the attachment spec', $copy && $copy['attachments'] === $stored['attachments'] );

mwright_check( 'Assigned template is reported as in use', count( MWRIGHT_CF7_Bridge::forms_using( $template_id ) ) === 1 );

MWRIGHT_CF7_Bridge::detach( $form_id, 'admin' );
MWRIGHT_CF7_Bridge::detach( $form_id, 'customer' );

$restored = WPCF7_ContactForm::get_instance( $form_id )->prop( 'mail' );

mwright_check( 'Detach restores CF7 own settings', 'ORIGINAL BODY' === $restored['body'], $restored['body'] );
mwright_check( 'Assignment removed', array() === MWRIGHT_CF7_Bridge::for_form( $form_id ) );

/* Deleting a template must prune its assignments. */
MWRIGHT_CF7_Bridge::assign( $form_id, 'admin', $copy_id );
wp_update_post( array( 'ID' => $copy_id, 'post_status' => 'publish' ) );
MWRIGHT_CF7_Bridge::assign( $form_id, 'admin', $copy_id );
wp_delete_post( $copy_id, true );

mwright_check( 'Deleting a template prunes its assignments', array() === MWRIGHT_CF7_Bridge::for_form( $form_id ) );

/* -------------------------------------------------------------------------
 * Screen option and branding round-trip
 * ---------------------------------------------------------------------- */

mwright_check(
	'Templates-per-page screen option is saved',
	35 === apply_filters( 'set_screen_option_mwright_per_page', false, 'mwright_per_page', '35' )
);

mwright_check(
	'Absurd per-page values are clamped',
	200 === apply_filters( 'set_screen_option_mwright_per_page', false, 'mwright_per_page', '99999' )
);

$branding_before = MWRIGHT_Branding::get();

MWRIGHT_Branding::save( array_merge( $branding_before, array( 'company_name' => 'Imported Co', 'primary_color' => '#abcdef' ) ) );

$branding_after = MWRIGHT_Branding::get();

mwright_check( 'Branding import restores text values', 'Imported Co' === $branding_after['company_name'] );
mwright_check( 'Branding import restores colours', '#abcdef' === $branding_after['primary_color'] );

MWRIGHT_Branding::save( array_merge( $branding_before, array( 'primary_color' => 'not-a-colour' ) ) );

mwright_check( 'Invalid colour falls back to the default', '#2271b1' === MWRIGHT_Branding::get()['primary_color'] );

MWRIGHT_Branding::save( $branding_before );

/* -------------------------------------------------------------------------
 * Submissions log
 * ---------------------------------------------------------------------- */

MWRIGHT_Submissions::install();

global $wpdb;

mwright_check(
	'Submissions table exists',
	MWRIGHT_Submissions::table() === $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', MWRIGHT_Submissions::table() )
	)
);

$entry_before = MWRIGHT_Submissions::count();

$wpdb->insert(
	MWRIGHT_Submissions::table(),
	array(
		'form_id'      => $form_id,
		'form_title'   => 'MWRIGHT Smoke Form',
		'status'       => 'sent',
		'fields'       => wp_json_encode(
			array(
				'your-name'    => 'John Smith',
				'your-message' => "Line one\nLine two",
				'gone-field'   => array( 'a', 'b' ),
			)
		),
		'files'        => wp_json_encode( array( 'your-file' => array( 'cv.pdf' ) ) ),
		'remote_ip'    => '203.0.113.42',
		'submitted_at' => current_time( 'mysql' ),
	)
);

$entry_id = (int) $wpdb->insert_id;
$stored   = MWRIGHT_Submissions::get( $entry_id );

mwright_check( 'Submission stored and read back', $stored && 'John Smith' === $stored['fields']['your-name'], wp_json_encode( $stored ) );
mwright_check( 'Multi-value answers survive the round trip', $stored && array( 'a', 'b' ) === $stored['fields']['gone-field'] );
mwright_check( 'Uploaded file names are kept', $stored && array( 'cv.pdf' ) === $stored['files']['your-file'] );

$listed = MWRIGHT_Submissions::query( array( 'form_id' => $form_id ) );

mwright_check( 'Submission listed for its form', 1 === $listed['total'] );

mwright_check(
	'Search matches stored answers',
	1 === MWRIGHT_Submissions::query( array( 'search' => 'John Smith' ) )['total']
);

mwright_check(
	'Search ignores answers that are not there',
	0 === MWRIGHT_Submissions::query( array( 'search' => 'nobody-by-that-name' ) )['total']
);

$columns = MWRIGHT_Submissions::field_columns( $form_id, $listed['items'] );

mwright_check(
	'Columns cover the form fields and any extras in the data',
	isset( $columns['your-name'], $columns['your-message'], $columns['gone-field'] ),
	implode( ', ', array_keys( $columns ) )
);

mwright_check( 'Forms with submissions are listed', isset( MWRIGHT_Submissions::forms()[ $form_id ] ) );

MWRIGHT_Submissions::delete( array( $entry_id ) );

mwright_check(
	'Submission deleted',
	null === MWRIGHT_Submissions::get( $entry_id ) && $entry_before === MWRIGHT_Submissions::count()
);

/*
 * The capture hook itself, driven through Contact Form 7's own submit() so
 * the test exercises the real path. wp_mail is short-circuited: this checks
 * that a submission is logged, not that the server can send email.
 *
 * Its own form, because the fixture above requires a file upload that a
 * command-line submission cannot provide.
 */
$live = WPCF7_ContactForm::get_template( array( 'title' => 'MWRIGHT Capture Form' ) );

$live->set_properties(
	array( 'form' => "[text* your-name]\n[email* your-email]\n[textarea your-message]" )
);

$live_id = $live->save();

// A command-line post has no browser fingerprint, which CF7 reads as spam.
add_filter( 'wpcf7_skip_spam_check', '__return_true' );
add_filter( 'pre_wp_mail', '__return_true' );

$_POST = array(
	'_wpcf7'          => $live_id,
	'_wpcf7_version'  => WPCF7_VERSION,
	'_wpcf7_locale'   => 'en_US',
	'_wpcf7_unit_tag' => 'wpcf7-f' . $live_id . '-o1',
	'your-name'       => 'Jane Tester',
	'your-email'      => 'jane@example.com',
	'your-message'    => "First line\nSecond line",
);

$submit_result = WPCF7_ContactForm::get_instance( $live_id )->submit();

$_POST = array();

remove_filter( 'pre_wp_mail', '__return_true' );
remove_filter( 'wpcf7_skip_spam_check', '__return_true' );

$captured = MWRIGHT_Submissions::query( array( 'form_id' => $live_id ) );
$logged   = $captured['items'][0] ?? null;

mwright_check(
	'A real submission is captured',
	1 === $captured['total'],
	'CF7 returned: ' . wp_json_encode( $submit_result )
);

mwright_check(
	'Captured answers match what was posted',
	$logged && 'Jane Tester' === ( $logged['fields']['your-name'] ?? '' )
		&& "First line\nSecond line" === ( $logged['fields']['your-message'] ?? '' ),
	wp_json_encode( $logged['fields'] ?? array() )
);

mwright_check( 'The email result is recorded', $logged && 'sent' === $logged['status'] );

mwright_check(
	'Contact Form 7 internals are not stored as answers',
	$logged && ! array_filter( array_keys( $logged['fields'] ), static fn( $key ) => str_starts_with( $key, '_' ) ),
	implode( ', ', array_keys( $logged['fields'] ?? array() ) )
);

MWRIGHT_Submissions::delete( array( $logged['id'] ?? 0 ) );
wp_delete_post( $live_id, true );

/*
 * Uploaded files: Contact Form 7 deletes its own copy when the request ends,
 * so the copy that matters is ours.
 */
$source_dir  = wp_upload_dir()['basedir'] . '/mwright-test-source';
$source_file = $source_dir . '/notes.txt';
$blocked     = $source_dir . '/payload.php';

wp_mkdir_p( $source_dir );
file_put_contents( $source_file, 'attached file body' );
file_put_contents( $blocked, '<?php // should never be stored' );

$stored = MWRIGHT_Submissions::store_files(
	array(
		'your-doc'  => array( $source_file ),
		'your-code' => array( $blocked ),
	)
);

$kept_path = MWRIGHT_Submissions::file_path( $stored['your-doc'][0]['path'] ?? '' );

mwright_check( 'Uploaded file is copied somewhere permanent', '' !== $kept_path, wp_json_encode( $stored ) );

mwright_check(
	'The stored copy holds the original bytes',
	$kept_path && 'attached file body' === file_get_contents( $kept_path )
);

mwright_check(
	'The original name is kept for display',
	'notes.txt' === ( $stored['your-doc'][0]['name'] ?? '' )
);

mwright_check(
	'A file type WordPress will not allow is recorded but never stored',
	'' === ( $stored['your-code'][0]['path'] ?? 'x' )
		&& 'type' === ( $stored['your-code'][0]['error'] ?? '' ),
	wp_json_encode( $stored['your-code'] ?? array() )
);

mwright_check(
	'The upload folder is closed to the web',
	file_exists( MWRIGHT_Submissions::upload_dir() . '/.htaccess' )
		&& file_exists( MWRIGHT_Submissions::upload_dir() . '/index.html' )
);

mwright_check(
	'A path climbing out of the upload folder is refused',
	'' === MWRIGHT_Submissions::file_path( '../../../wp-config.php' )
);

$wpdb->insert(
	MWRIGHT_Submissions::table(),
	array(
		'form_id'      => $form_id,
		'form_title'   => 'MWRIGHT Smoke Form',
		'status'       => 'sent',
		'fields'       => wp_json_encode( array( 'your-name' => 'Ada Upload' ) ),
		'files'        => wp_json_encode( $stored ),
		'remote_ip'    => '203.0.113.7',
		'submitted_at' => current_time( 'mysql' ),
	)
);

$file_entry = MWRIGHT_Submissions::get( (int) $wpdb->insert_id );
$listed_file = MWRIGHT_Submissions::files( $file_entry )['your-doc'][0] ?? array();

mwright_check(
	'Stored files read back with a download index',
	isset( $listed_file['index'] ) && 'notes.txt' === $listed_file['name']
);

MWRIGHT_Submissions::delete( array( $file_entry['id'] ) );

mwright_check( 'Deleting a submission removes its files', ! file_exists( $kept_path ) );

wp_delete_file( $source_file );
wp_delete_file( $blocked );

/* -------------------------------------------------------------------------
 * Demo templates
 * ---------------------------------------------------------------------- */

require_once MWRIGHT_DIR . 'includes/starter-templates.php';

$starter_names = wp_list_pluck( mwright_starter_templates(), 'name' );
$all_templates = static function () {
	return get_posts(
		array(
			'post_type'      => MWRIGHT_Template_Post_Type::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
};
$missing_names = static function () use ( $starter_names ) {
	return array_values( array_filter( $starter_names, static fn( $name ) => ! mwright_template_name_taken( $name ) ) );
};

$first_run  = mwright_install_starter_templates();
$second_run = mwright_install_starter_templates();

mwright_check( 'Every demo template exists after installing', array() === $missing_names(), implode( ', ', $missing_names() ) );
mwright_check( 'Installing demos again adds nothing', array() === $second_run, wp_json_encode( $second_run ) );

// Leave the site with exactly the templates it had.
foreach ( $first_run as $demo_id ) {
	wp_delete_post( $demo_id, true );
}

/*
 * The fresh-install bug: activating both plugins together ran activation
 * before Contact Form 7 loaded, so the demos were never seeded. The first
 * admin page load has to catch up.
 */
mwright_check(
	'Demo seeding runs on admin load, not only on activation',
	false !== has_action( 'admin_init', array( 'MWRIGHT_Plugin', 'maybe_seed' ) )
);

$seeded_flag = get_option( 'mwright_seeded' );
$before_seed = $all_templates();
$admin_ids   = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );

delete_option( 'mwright_seeded' );
wp_set_current_user( (int) ( $admin_ids[0] ?? 0 ) );

MWRIGHT_Plugin::maybe_seed();

mwright_check( 'An unseeded site gets its demo templates', array() === $missing_names() && (bool) get_option( 'mwright_seeded' ) );

$after_first_seed = $all_templates();

MWRIGHT_Plugin::maybe_seed();

mwright_check( 'Seeding only happens once', $after_first_seed === $all_templates() );

wp_set_current_user( 0 );

foreach ( array_diff( $after_first_seed, $before_seed ) as $demo_id ) {
	wp_delete_post( $demo_id, true );
}

if ( false === $seeded_flag ) {
	delete_option( 'mwright_seeded' );
} else {
	update_option( 'mwright_seeded', $seeded_flag );
}

/* -------------------------------------------------------------------------
 * Branding gaps and logo reachability
 * ---------------------------------------------------------------------- */

$gap_branding = MWRIGHT_Branding::get();

MWRIGHT_Branding::save(
	array_merge(
		$gap_branding,
		array(
			'address'          => '',
			'social_facebook'  => '',
			'social_twitter'   => '',
			'social_linkedin'  => '',
			'social_instagram' => '',
			'footer_text'      => 'Footer line',
		)
	)
);

$gap_body = MWRIGHT_Branding::replace(
	'<table>' .
	'<tr><td style="padding:8px 32px;"><p style="margin:0 0 12px;">[mwright_address]</p></td></tr>' .
	'<tr><td style="padding:8px 32px;"><p style="margin:0 0 12px;">[mwright_social_links]</p></td></tr>' .
	'<tr><td style="padding:8px 32px;"><p style="margin:0 0 12px;">[mwright_footer_text]</p></td></tr>' .
	'<tr><td style="height:24px;line-height:24px;font-size:0;">&nbsp;</td></tr>' .
	'</table>',
	true
);

mwright_check( 'An empty branding value leaves no empty paragraph', ! str_contains( $gap_body, '<p style="margin:0 0 12px;"></p>' ), $gap_body );
mwright_check( 'The padded row around it goes too', 1 === substr_count( $gap_body, 'padding:8px 32px' ), $gap_body );
mwright_check( 'The row that still has content stays', str_contains( $gap_body, 'Footer line' ) );
mwright_check( 'A deliberate spacer row is left alone', str_contains( $gap_body, 'height:24px' ), $gap_body );

$text_body = MWRIGHT_Branding::replace( "Address: [mwright_address]\nFooter: [mwright_footer_text]", false );

mwright_check( 'Plain-text bodies are not pruned', str_contains( $text_body, 'Address: ' ) );

MWRIGHT_Branding::save( $gap_branding );

foreach ( array( 'http://localhost/logo.png', 'http://127.0.0.1/logo.png', 'http://192.168.1.10/logo.png', 'https://mysite.local/logo.png' ) as $local_url ) {
	mwright_check( 'Unreachable logo host flagged: ' . wp_parse_url( $local_url, PHP_URL_HOST ), MWRIGHT_Branding::is_private_host( $local_url ) );
}

mwright_check( 'A public logo host is not flagged', ! MWRIGHT_Branding::is_private_host( 'https://example.com/logo.png' ) );
mwright_check( 'An empty logo is not flagged', ! MWRIGHT_Branding::is_private_host( '' ) );

/*
 * Contact Form 7 puts its own digest of an upload in the posted data. It must
 * never reach the screen as if the visitor had typed it.
 */
$digest_entry = array(
	'id'     => 0,
	'fields' => array(
		'your-name' => 'Jane Tester',
		'file-145'  => '78410ba7cfde1bc7950b88ac10d19f218be541fb13a4cdeb9d1041191b9270de',
	),
	'files'  => array( 'file-145' => array( array( 'name' => 'screenshot.png', 'path' => '', 'size' => 10 ) ) ),
);

$digest_answers = MWRIGHT_Submissions::answers( $digest_entry );

mwright_check(
	'An upload field is not shown as a typed answer',
	array( 'your-name' => 'Jane Tester' ) === $digest_answers,
	wp_json_encode( $digest_answers )
);

mwright_check(
	'The upload is still listed as a file',
	'screenshot.png' === ( MWRIGHT_Submissions::files( $digest_entry )['file-145'][0]['name'] ?? '' )
);

/*
 * CSV export. A cell that opens with a formula character is a real attack on
 * whoever opens the file in Excel, so it must never survive as one.
 */
foreach ( array( '=1+1', '+1', '-1', '@SUM(A1)', "\tcmd", "\rcmd" ) as $risky ) {
	mwright_check(
		'A formula cell is neutralised: ' . trim( $risky ),
		str_starts_with( MWRIGHT_Submissions::csv_cell( $risky ), "'" ),
		MWRIGHT_Submissions::csv_cell( $risky )
	);
}

mwright_check( 'An ordinary answer is left alone', 'Jane Tester' === MWRIGHT_Submissions::csv_cell( 'Jane Tester' ) );
mwright_check( 'An email address is left alone', 'jane@example.com' === MWRIGHT_Submissions::csv_cell( 'jane@example.com' ) );

/*
 * Contact Form 7 compatibility. These are the pieces of CF7's API this plugin
 * leans on; if an upgrade moves one, this is where it shows up first.
 */
mwright_check( 'CF7 is new enough', version_compare( WPCF7_VERSION, MWRIGHT_Plugin::MIN_CF7, '>=' ), 'CF7 ' . WPCF7_VERSION );

foreach ( array( 'scan_form_tags', 'collect_mail_tags', 'prop', 'set_properties', 'title', 'id' ) as $method ) {
	mwright_check( 'WPCF7_ContactForm::' . $method . '() exists', method_exists( 'WPCF7_ContactForm', $method ) );
}

foreach ( array( 'get_posted_data', 'uploaded_files', 'get_meta' ) as $method ) {
	mwright_check( 'WPCF7_Submission::' . $method . '() exists', method_exists( 'WPCF7_Submission', $method ) );
}

mwright_check( 'wpcf7_is_name() exists', function_exists( 'wpcf7_is_name' ) );
mwright_check( 'File tag types still discoverable', array() !== MWRIGHT_CF7_Bridge::file_tag_types(), implode( ', ', MWRIGHT_CF7_Bridge::file_tag_types() ) );

// A full HTML document must not be wrapped a second time by CF7.
mwright_check(
	'CF7 still leaves a complete HTML document alone',
	1 === preg_match( '%<html[>\s].*</html>%is', '<!doctype html><html><body>x</body></html>' )
		&& 0 === preg_match( '%<html[>\s].*</html>%is', '<p>partial</p>' )
);

// Every tag offered in the sidebar needs a sample value, or Preview shows the raw tag.
$sample_specials = MWRIGHT_Renderer::sample_values( $form_id );

foreach ( array_keys( MWRIGHT_CF7_Bridge::special_tags() ) as $special ) {
	if ( str_ends_with( $special, '_' ) ) {
		continue; // A prefix, not a tag of its own.
	}

	mwright_check( 'Preview has a sample for [' . $special . ']', isset( $sample_specials[ $special ] ) );
}

/* -------------------------------------------------------------------------
 * Packaging — what WordPress.org checks on upload
 * ---------------------------------------------------------------------- */

$readme_path = MWRIGHT_DIR . 'readme.txt';

mwright_check( 'readme.txt exists', file_exists( $readme_path ) );

$readme_text = file_exists( $readme_path ) ? file_get_contents( $readme_path ) : '';

mwright_check(
	'readme.txt declares a GPL licence',
	(bool) preg_match( '/^License:\s*GPLv2 or later/mi', $readme_text )
		&& (bool) preg_match( '#^License URI:\s*https?://(www\.)?gnu\.org/#mi', $readme_text )
);

preg_match( '/^Stable tag:\s*(\S+)/mi', $readme_text, $mwright_stable );

mwright_check(
	'Stable tag matches the plugin version',
	( $mwright_stable[1] ?? '' ) === MWRIGHT_VERSION,
	( $mwright_stable[1] ?? 'missing' ) . ' vs ' . MWRIGHT_VERSION
);

mwright_check(
	'Changelog covers this version',
	str_contains( $readme_text, '= ' . MWRIGHT_VERSION . ' =' )
);

$license_text = file_exists( MWRIGHT_DIR . 'LICENSE' ) ? file_get_contents( MWRIGHT_DIR . 'LICENSE' ) : '';

mwright_check( 'LICENSE file ships with the plugin', '' !== $license_text );

mwright_check(
	'LICENSE carries the full GPL version 2 text',
	str_contains( $license_text, 'GNU GENERAL PUBLIC LICENSE' )
		&& str_contains( $license_text, 'Version 2, June 1991' )
		&& str_contains( $license_text, 'TERMS AND CONDITIONS' )
);

/* -------------------------------------------------------------------------
 * Clean up
 * ---------------------------------------------------------------------- */

wp_delete_post( $template_id, true );
wp_delete_post( $form_id, true );

mwright_check( 'Deleting a form prunes its assignments', array() === MWRIGHT_CF7_Bridge::for_form( $form_id ) );

echo str_repeat( '-', 60 ) . "\n";
printf( "%d checks, %d failures\n\n", $checks, $failures );

exit( $failures ? 1 : 0 );

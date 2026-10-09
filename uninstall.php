<?php
/**
 * Uninstall routine.
 *
 * Templates are only destroyed when the administrator explicitly opted in
 * under Settings → Advanced. The default is to leave everything alone.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$mwright_settings = (array) get_option( 'mwright_settings', array() );

if ( empty( $mwright_settings['delete_on_uninstall'] ) ) {
	return;
}

$mwright_templates = get_posts(
	array(
		'post_type'      => 'mwright_template',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $mwright_templates as $mwright_id ) {
	wp_delete_post( $mwright_id, true );
}

// The submissions log lives in its own table.
global $wpdb;

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- own table, uninstall only.
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mwright_submissions' );

// Uploaded files kept alongside the submissions.
$mwright_uploads = wp_upload_dir();
$mwright_dir     = untrailingslashit( $mwright_uploads['basedir'] ) . '/mwright-submissions';

if ( is_dir( $mwright_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	global $wp_filesystem;

	if ( WP_Filesystem() && $wp_filesystem ) {
		$wp_filesystem->delete( $mwright_dir, true );
	}
}

foreach ( array( 'mwright_settings', 'mwright_branding', 'mwright_assignments', 'mwright_log', 'mwright_seeded', 'mwright_db_version' ) as $mwright_option ) {
	delete_option( $mwright_option );
}

// Per-user screen option for the templates list.
delete_metadata( 'user', 0, 'mwright_per_page', '', true );
delete_metadata( 'user', 0, 'mwright_entries_per_page', '', true );

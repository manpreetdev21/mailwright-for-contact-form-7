<?php
/**
 * Stores every accepted Contact Form 7 submission in its own table.
 *
 * One row per submission, with the posted fields kept as JSON so any form —
 * present or future — is recorded without a schema change. Contact Form 7
 * still owns validation, spam checks and sending; we only listen.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

class MWRIGHT_Submissions {

	/** Table name, without the site prefix. */
	const TABLE = 'mwright_submissions';

	/** Option holding the installed schema version. */
	const DB_OPTION = 'mwright_db_version';

	/** Bump to make dbDelta run again. */
	const DB_VERSION = '1';

	/**
	 * Hooks the capture listener.
	 */
	public static function init() {
		add_action( 'wpcf7_submit', array( __CLASS__, 'capture' ), 10, 2 );

		// The table is created on activation; this catches upgrades.
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		}
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Creates or updates the table when the schema version moves.
	 */
	public static function maybe_install() {
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Creates the table.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// dbDelta is whitespace-sensitive: two spaces before each key list.
		dbDelta(
			"CREATE TABLE $table (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				form_id bigint(20) unsigned NOT NULL DEFAULT 0,
				form_title varchar(255) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT '',
				fields longtext NOT NULL,
				files text NOT NULL,
				remote_ip varchar(45) NOT NULL DEFAULT '',
				submitted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY form_id (form_id),
				KEY submitted_at (submitted_at)
			) $collate;"
		);

		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/* ---------------------------------------------------------------------
	 * Capture
	 * ------------------------------------------------------------------ */

	/**
	 * Records a submission once Contact Form 7 has finished with it.
	 *
	 * Only submissions that passed validation and spam checks are stored, so
	 * the log holds real enquiries rather than every bot attempt.
	 *
	 * @param WPCF7_ContactForm $contact_form Submitted form.
	 * @param array             $result       CF7's submission result.
	 */
	public static function capture( $contact_form, $result ) {
		$status = is_array( $result ) ? ( $result['status'] ?? '' ) : '';

		if ( ! in_array( $status, array( 'mail_sent', 'mail_failed' ), true ) ) {
			return;
		}

		if ( ! $contact_form instanceof WPCF7_ContactForm || ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();

		if ( ! $submission ) {
			return;
		}

		$uploads = (array) $submission->uploaded_files();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- our own table.
		$wpdb->insert(
			self::table(),
			array(
				'form_id'      => $contact_form->id(),
				'form_title'   => $contact_form->title(),
				'status'       => 'mail_sent' === $status ? 'sent' : 'failed',
				'fields'       => (string) wp_json_encode( self::posted_fields( $submission, $uploads ) ),
				'files'        => (string) wp_json_encode( self::store_files( $uploads ) ),
				'remote_ip'    => (string) $submission->get_meta( 'remote_ip' ),
				'submitted_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * The visitor's own answers, without Contact Form 7's internal fields.
	 *
	 * @param WPCF7_Submission $submission Live submission.
	 * @param array            $uploads    Upload fields, which are recorded separately.
	 * @return array Field name => value.
	 */
	private static function posted_fields( $submission, $uploads = array() ) {
		$fields = array();

		foreach ( (array) $submission->get_posted_data() as $name => $value ) {
			// _wpcf7, _wpcf7_unit_tag and friends are plumbing, not answers.
			if ( ! is_string( $name ) || str_starts_with( $name, '_' ) ) {
				continue;
			}

			/*
			 * Contact Form 7 puts its own digest of the file in the posted
			 * data for an upload field. It is not an answer anyone can read,
			 * and the file itself is kept in the files column.
			 */
			if ( array_key_exists( $name, $uploads ) ) {
				continue;
			}

			$fields[ $name ] = is_array( $value )
				? array_map( array( __CLASS__, 'clean' ), $value )
				: self::clean( $value );
		}

		return $fields;
	}

	/**
	 * Copies the visitor's uploads somewhere permanent.
	 *
	 * Contact Form 7 deletes its own copy as soon as the request ends, so the
	 * file has to be taken now or not at all. Each submission gets its own
	 * unguessable folder, and the whole directory is closed to the web — the
	 * only way back to a file is the capability-checked download link.
	 *
	 * @param array $uploads Field name => uploaded file paths, as CF7 reports them.
	 * @return array Field name => list of stored files.
	 */
	public static function store_files( $uploads ) {
		$stored = array();
		$folder = '';

		foreach ( (array) $uploads as $name => $paths ) {
			foreach ( array_filter( (array) $paths ) as $source ) {
				if ( ! is_readable( $source ) ) {
					continue;
				}

				$original = wp_basename( $source );

				// WordPress already decides which file types are safe to keep.
				if ( ! wp_check_filetype( $original )['type'] ) {
					$stored[ $name ][] = array(
						'name'  => $original,
						'path'  => '',
						'size'  => (int) filesize( $source ),
						'error' => 'type',
					);
					continue;
				}

				if ( '' === $folder ) {
					$folder = self::new_folder();
				}

				if ( ! $folder ) {
					continue;
				}

				$target = trailingslashit( self::upload_dir() . '/' . $folder ) .
					wp_unique_filename( self::upload_dir() . '/' . $folder, sanitize_file_name( $original ) );

				if ( ! copy( $source, $target ) ) {
					continue;
				}

				$stored[ $name ][] = array(
					'name' => $original,
					'path' => $folder . '/' . wp_basename( $target ),
					'size' => (int) filesize( $target ),
				);
			}
		}

		return $stored;
	}

	/**
	 * Root directory for stored uploads.
	 *
	 * @return string Absolute path, without a trailing slash.
	 */
	public static function upload_dir() {
		$uploads = wp_upload_dir();

		return untrailingslashit( $uploads['basedir'] ) . '/mwright-submissions';
	}

	/**
	 * Creates a fresh per-submission folder and makes sure the root is closed
	 * to direct web requests.
	 *
	 * @return string Path relative to the root, or an empty string on failure.
	 */
	private static function new_folder() {
		$root = self::upload_dir();

		if ( ! wp_mkdir_p( $root ) ) {
			return '';
		}

		// Belt and braces: the server should refuse these files outright, and
		// the folder name should be unguessable if it ever does not.
		if ( ! file_exists( $root . '/.htaccess' ) ) {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions -- plain guard file, no filesystem credentials involved.
				$root . '/.htaccess',
				"# Uploaded form files are served through the admin only.\n" .
				"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
				"<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
			);
		}

		if ( ! file_exists( $root . '/index.html' ) ) {
			file_put_contents( $root . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- empty directory-listing guard.
		}

		$folder = wp_date( 'Y/m' ) . '/' . wp_generate_password( 20, false );

		return wp_mkdir_p( $root . '/' . $folder ) ? $folder : '';
	}

	/**
	 * Absolute path of a stored file, or an empty string when it is missing or
	 * points outside our directory.
	 *
	 * @param string $relative Stored relative path.
	 * @return string
	 */
	public static function file_path( $relative ) {
		$relative = (string) $relative;

		if ( '' === $relative ) {
			return '';
		}

		$root = realpath( self::upload_dir() );
		$path = realpath( self::upload_dir() . '/' . $relative );

		if ( ! $root || ! $path || ! is_file( $path ) ) {
			return '';
		}

		// A stored path must never climb out of the uploads folder.
		return str_starts_with( $path, $root ) ? $path : '';
	}

	/**
	 * The files on a submission, in one shape whatever version stored them.
	 *
	 * @param array $entry Submission.
	 * @return array Field name => list of [ name, path, size, error, index ].
	 */
	public static function files( $entry ) {
		$out = array();

		foreach ( (array) ( $entry['files'] ?? array() ) as $field => $items ) {
			foreach ( (array) $items as $index => $item ) {
				$out[ $field ][] = array(
					'index' => (int) $index,
					// Before file storage existed, only the name was kept.
					'name'  => is_array( $item ) ? (string) ( $item['name'] ?? '' ) : (string) $item,
					'path'  => is_array( $item ) ? (string) ( $item['path'] ?? '' ) : '',
					'size'  => is_array( $item ) ? (int) ( $item['size'] ?? 0 ) : 0,
					'error' => is_array( $item ) ? (string) ( $item['error'] ?? '' ) : '',
				);
			}
		}

		return $out;
	}

	/**
	 * The readable answers on a submission: everything except the fields
	 * that hold an upload, which are shown as files instead.
	 *
	 * Older rows kept Contact Form 7 own file digest as the value, so this
	 * filters them out on the way to the screen as well.
	 *
	 * @param array $entry Submission.
	 * @return array Field name => value.
	 */
	public static function answers( $entry ) {
		$files = (array) ( $entry['files'] ?? array() );

		return array_diff_key( (array) ( $entry['fields'] ?? array() ), $files );
	}

	/**
	 * Download link for one stored file.
	 *
	 * @param int    $entry_id Submission ID.
	 * @param string $field    Field name.
	 * @param int    $index    Position within that field.
	 * @return string
	 */
	public static function download_url( $entry_id, $field, $index ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'mwright_download',
					'entry'  => (int) $entry_id,
					'field'  => rawurlencode( $field ),
					'index'  => (int) $index,
				),
				admin_url( 'admin-post.php' )
			),
			'mwright_download_' . (int) $entry_id
		);
	}

	/**
	 * Removes the stored files of one submission, and the folder holding them.
	 *
	 * @param array $entry Submission.
	 */
	private static function delete_files( $entry ) {
		$folders = array();

		foreach ( self::files( $entry ) as $items ) {
			foreach ( $items as $file ) {
				$path = self::file_path( $file['path'] );

				if ( ! $path ) {
					continue;
				}

				wp_delete_file( $path );
				$folders[ dirname( $path ) ] = true;
			}
		}

		// Only ever removes folders we made, and only once they are empty.
		foreach ( array_keys( $folders ) as $folder ) {
			if ( is_dir( $folder ) && ! glob( $folder . '/*' ) ) {
				rmdir( $folder ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- own empty directory.
			}
		}
	}

	/**
	 * Normalises one posted value for storage.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function clean( $value ) {
		return sanitize_textarea_field( (string) $value );
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * Lists submissions.
	 *
	 * @param array $args form_id, status, search, per_page, page, orderby, order.
	 * @return array [ items, total ].
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'form_id'  => 0,
				'status'   => '',
				'search'   => '',
				'per_page' => 20,
				'page'     => 1,
				'orderby'  => 'submitted_at',
				'order'    => 'DESC',
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['form_id'] ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}

		if ( in_array( $args['status'], array( 'sent', 'failed' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( '' !== trim( (string) $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(fields LIKE %s OR form_title LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$clause  = implode( ' AND ', $where );
		$orderby = in_array( $args['orderby'], array( 'submitted_at', 'form_title', 'status', 'id' ), true )
			? $args['orderby']
			: 'submitted_at';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, (int) $args['page'] - 1 ) * $per_page;
		$table    = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name and ordering come from whitelists above.
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $clause";
		$list_sql  = "SELECT * FROM $table WHERE $clause ORDER BY $orderby $order LIMIT %d OFFSET %d";

		$total = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
			: $wpdb->get_var( $count_sql ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return array(
			'items' => array_map( array( __CLASS__, 'shape' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * One submission.
	 *
	 * @param int $id Submission ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own table, ID is prepared.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );

		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Turns a database row into a plain array with the JSON decoded.
	 *
	 * @param object $row Database row.
	 * @return array
	 */
	private static function shape( $row ) {
		return array(
			'id'           => (int) $row->id,
			'form_id'      => (int) $row->form_id,
			'form_title'   => (string) $row->form_title,
			'status'       => (string) $row->status,
			'fields'       => (array) json_decode( (string) $row->fields, true ),
			'files'        => (array) json_decode( (string) $row->files, true ),
			'remote_ip'    => (string) $row->remote_ip,
			'submitted_at' => (string) $row->submitted_at,
		);
	}

	/**
	 * Deletes submissions.
	 *
	 * @param array $ids Submission IDs.
	 * @return int Rows removed.
	 */
	public static function delete( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', (array) $ids ) );

		if ( ! $ids ) {
			return 0;
		}

		// Take the files with the row, or the uploads folder grows forever.
		foreach ( $ids as $id ) {
			$entry = self::get( $id );

			if ( $entry ) {
				self::delete_files( $entry );
			}
		}

		$table        = self::table();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own table; the IN list is %d placeholders built from absint-ed IDs.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id IN ($placeholders)", $ids ) );
	}

	/**
	 * Forms that have submissions, most recent activity first.
	 *
	 * Titles come from the log itself, so a deleted form still reads properly.
	 *
	 * @return array Form ID => [ title, count ].
	 */
	public static function forms() {
		global $wpdb;

		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own table, no user input.
		$rows = $wpdb->get_results(
			"SELECT form_id, MAX(form_title) AS form_title, COUNT(*) AS total, MAX(submitted_at) AS last_at
			 FROM $table GROUP BY form_id ORDER BY last_at DESC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$forms = array();

		foreach ( (array) $rows as $row ) {
			$forms[ (int) $row->form_id ] = array(
				'title' => (string) $row->form_title,
				'count' => (int) $row->total,
			);
		}

		return $forms;
	}

	/**
	 * How many submissions are stored.
	 *
	 * @param int $form_id Restrict to one form.
	 * @return int
	 */
	public static function count( $form_id = 0 ) {
		global $wpdb;

		$table = self::table();

		if ( $form_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own table, ID is prepared.
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE form_id = %d", (int) $form_id ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- own table, no user input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	}

	/**
	 * Column names to show for a form: the form's own fields first, then any
	 * extra keys found in the rows on screen (fields since removed).
	 *
	 * @param int   $form_id Form ID.
	 * @param array $items   Submissions being displayed.
	 * @return array Field name => label.
	 */
	public static function field_columns( $form_id, $items ) {
		$columns = array();

		foreach ( MWRIGHT_CF7_Bridge::form_tags( $form_id ) as $tag ) {
			$columns[ $tag['name'] ] = $tag['label'];
		}

		foreach ( $items as $item ) {
			foreach ( array_keys( $item['fields'] ) as $name ) {
				if ( ! isset( $columns[ $name ] ) ) {
					$columns[ $name ] = MWRIGHT_CF7_Bridge::friendly_label( $name );
				}
			}
		}

		return $columns;
	}

	/**
	 * One CSV cell, made safe for spreadsheets.
	 *
	 * Excel and Google Sheets treat a cell opening with =, +, - or @ as a
	 * formula, which turns an exported answer into something that runs. A
	 * leading apostrophe keeps it plain text.
	 *
	 * @param mixed $cell Cell value.
	 * @return string
	 */
	public static function csv_cell( $cell ) {
		$cell = (string) $cell;

		return preg_match( '/^[=+\-@\t\r]/', $cell ) ? "'" . $cell : $cell;
	}

	/**
	 * A stored value as one readable line.
	 *
	 * @param mixed $value Field value.
	 * @return string
	 */
	public static function flatten( $value ) {
		return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
	}
}

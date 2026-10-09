<?php
/**
 * Bootstrap, environment guards and shared settings access.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

class MWRIGHT_Plugin {

	/** Oldest Contact Form 7 we integrate with. */
	const MIN_CF7 = '5.8';

	/** Option holding the plugin settings array. */
	const SETTINGS = 'mwright_settings';

	/**
	 * Loads the plugin once WordPress and Contact Form 7 are available.
	 */
	public static function boot() {
		/*
		 * Translations load themselves. Since WordPress 4.6 the Text Domain and
		 * Domain Path headers are enough, and calling load_plugin_textdomain()
		 * here would only run earlier than the strings are needed.
		 */
		if ( ! self::cf7_supported() ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_requirement_notice' ) );
			return;
		}

		require_once MWRIGHT_DIR . 'includes/class-template-post-type.php';
		require_once MWRIGHT_DIR . 'includes/class-branding.php';
		require_once MWRIGHT_DIR . 'includes/class-renderer.php';
		require_once MWRIGHT_DIR . 'includes/class-cf7-bridge.php';
		require_once MWRIGHT_DIR . 'includes/class-submissions.php';

		// Post types belong on `init`: $wp_rewrite does not exist yet on plugins_loaded.
		add_action( 'init', array( 'MWRIGHT_Template_Post_Type', 'init' ) );

		MWRIGHT_CF7_Bridge::init();
		MWRIGHT_Submissions::init();

		if ( is_admin() ) {
			require_once MWRIGHT_DIR . 'admin/class-admin.php';
			require_once MWRIGHT_DIR . 'admin/class-ajax.php';

			MWRIGHT_Admin::init();
			MWRIGHT_Ajax::init();

			add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ), 5 );
			add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ) );
		}
	}

	/**
	 * Seeds starter templates and default settings on first activation.
	 */
	public static function activate() {
		if ( ! self::cf7_supported() ) {
			return; // Nothing to seed yet; boot() will nag in the admin.
		}

		require_once MWRIGHT_DIR . 'includes/class-template-post-type.php';
		MWRIGHT_Template_Post_Type::init();

		require_once MWRIGHT_DIR . 'includes/class-submissions.php';
		MWRIGHT_Submissions::install();

		add_option( self::SETTINGS, self::default_settings() );

		self::maybe_seed();
	}

	/**
	 * Moves data written under the plugin's former name.
	 *
	 * Version 2.0.0 renamed the plugin, and with it the post type, meta keys,
	 * options and submissions table. A site that stored anything under the old
	 * names is carried across once, then the flag stops this running again.
	 */
	public static function maybe_migrate() {
		if ( get_option( 'mwright_migrated' ) ) {
			return;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- one-off rename of our own rows, core tables only.
		$wpdb->query(
			$wpdb->prepare( "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s", 'mwright_template', 'cf7etm_template' )
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = REPLACE( meta_key, %s, %s ) WHERE meta_key LIKE %s",
				'_cf7etm_',
				'_mwright_',
				$wpdb->esc_like( '_cf7etm_' ) . '%'
			)
		);

		foreach ( array( 'settings', 'branding', 'assignments', 'log', 'seeded', 'db_version' ) as $name ) {
			$old = get_option( 'cf7etm_' . $name );

			if ( false !== $old && false === get_option( 'mwright_' . $name ) ) {
				update_option( 'mwright_' . $name, $old, false );
			}

			delete_option( 'cf7etm_' . $name );
		}

		foreach ( array( 'per_page', 'entries_per_page' ) as $name ) {
			$wpdb->query(
				$wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s", 'mwright_' . $name, 'cf7etm_' . $name )
			);
		}

		$old_table = $wpdb->prefix . 'cf7etm_submissions';
		$new_table = $wpdb->prefix . 'mwright_submissions';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) ) === $old_table
			&& $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) ) !== $new_table ) {
			$wpdb->query( "RENAME TABLE `$old_table` TO `$new_table`" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$uploads = wp_upload_dir();
		$old_dir = untrailingslashit( $uploads['basedir'] ) . '/cf7etm-submissions';
		$new_dir = untrailingslashit( $uploads['basedir'] ) . '/mwright-submissions';

		if ( is_dir( $old_dir ) && ! is_dir( $new_dir ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			global $wp_filesystem;

			if ( WP_Filesystem() && $wp_filesystem ) {
				$wp_filesystem->move( $old_dir, $new_dir );
			}
		}

		update_option( 'mwright_migrated', MWRIGHT_VERSION, false );
	}

	/**
	 * Installs the starter templates once, as soon as Contact Form 7 is there.
	 *
	 * Activation alone cannot do this. Bulk-activating both plugins runs this
	 * plugin's hook before Contact Form 7 has loaded, and installing Contact
	 * Form 7 afterwards never re-runs activation — so it also runs on the first
	 * admin page an editor opens.
	 */
	public static function maybe_seed() {
		if ( get_option( 'mwright_seeded' ) || ! current_user_can( self::cap() ) ) {
			return;
		}

		require_once MWRIGHT_DIR . 'includes/starter-templates.php';
		mwright_install_starter_templates();
		update_option( 'mwright_seeded', MWRIGHT_VERSION );
	}

	/**
	 * Whether a supported Contact Form 7 is active.
	 *
	 * @return bool
	 */
	public static function cf7_supported() {
		return defined( 'WPCF7_VERSION' )
			&& class_exists( 'WPCF7_ContactForm' )
			&& version_compare( WPCF7_VERSION, self::MIN_CF7, '>=' );
	}

	/**
	 * Admin notice shown when Contact Form 7 is missing or too old.
	 */
	public static function render_requirement_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		/*
		 * Only where it is useful: this plugin's own screens and the plugins
		 * list. A dependency warning on every admin page is noise.
		 */
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';

		if ( 'plugins' !== $id && ! str_contains( $id, 'mwright' ) ) {
			return;
		}

		$message = defined( 'WPCF7_VERSION' )
			? sprintf(
				/* translators: 1: required CF7 version, 2: installed CF7 version */
				__( 'Mailwright for Contact Form 7 needs Contact Form 7 %1$s or newer. Version %2$s is installed.', 'mailwright-for-contact-form-7' ),
				self::MIN_CF7,
				WPCF7_VERSION
			)
			: __( 'Mailwright for Contact Form 7 needs the Contact Form 7 plugin to be installed and active.', 'mailwright-for-contact-form-7' );

		wp_admin_notice( esc_html( $message ), array( 'type' => 'warning' ) );
	}

	/**
	 * Capability required to manage templates. Reuses Contact Form 7's own
	 * capability so existing form editors get access without extra setup.
	 *
	 * @return string
	 */
	public static function cap() {
		return 'wpcf7_edit_contact_forms';
	}

	/**
	 * Dies with a friendly message when the current user lacks access.
	 */
	public static function require_cap() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die(
				esc_html__( 'You do not have permission to manage email templates.', 'mailwright-for-contact-form-7' ),
				403
			);
		}
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'default_type'       => 'html',
			'default_sender'     => '',
			'test_recipient'     => '',
			'debug'              => 0,
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * All settings, merged over defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		return wp_parse_args( (array) get_option( self::SETTINGS, array() ), self::default_settings() );
	}

	/**
	 * A single setting value.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Fallback when unset.
	 * @return mixed
	 */
	public static function setting( $key, $default = '' ) {
		$settings = self::settings();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Writes a line to the debug log when debug mode is enabled.
	 * Email bodies are never logged.
	 *
	 * @param string $message Context line.
	 */
	public static function log( $message ) {
		if ( ! self::setting( 'debug' ) ) {
			return;
		}

		$log = (array) get_option( 'mwright_log', array() );

		$log[] = array(
			'time'    => time(),
			'message' => (string) $message,
		);

		// Keep the log bounded; this is a diagnostic aid, not an archive.
		update_option( 'mwright_log', array_slice( $log, -200 ), false );
	}

	/**
	 * Admin URL for one of the plugin screens.
	 *
	 * @param string $page Page slug suffix, e.g. 'templates'.
	 * @param array  $args Extra query arguments.
	 * @return string
	 */
	public static function url( $page = '', $args = array() ) {
		$slug = 'mwright' . ( $page ? '-' . $page : '' );
		return add_query_arg( array( 'page' => $slug ) + $args, admin_url( 'admin.php' ) );
	}
}

<?php
/**
 * Admin menus, screens, assets and the non-AJAX form handlers.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

class MWRIGHT_Admin {

	/** Our page hook suffixes, filled in by register_menu(). */
	private static $hooks = array();

	/**
	 * Hooks the admin layer.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		add_action( 'admin_post_mwright_save_branding', array( __CLASS__, 'handle_save_branding' ) );
		add_action( 'admin_post_mwright_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_mwright_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_mwright_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_mwright_install_demos', array( __CLASS__, 'handle_install_demos' ) );
		add_action( 'admin_post_mwright_export_entries', array( __CLASS__, 'handle_export_entries' ) );
		add_action( 'admin_post_mwright_clear_log', array( __CLASS__, 'handle_clear_log' ) );
		add_action( 'admin_post_mwright_download', array( __CLASS__, 'handle_download' ) );

		// Without this, core discards the "Templates per page" screen option.
		add_filter(
			'set_screen_option_mwright_per_page',
			static function ( $status, $option, $value ) {
				return max( 1, min( 200, absint( $value ) ) );
			},
			10,
			3
		);

		add_filter(
			'set_screen_option_mwright_entries_per_page',
			static function ( $status, $option, $value ) {
				return max( 1, min( 200, absint( $value ) ) );
			},
			10,
			3
		);
	}

	/**
	 * Registers the top-level menu and its submenus.
	 */
	public static function register_menu() {
		$cap = MWRIGHT_Plugin::cap();

		self::$hooks['dashboard'] = add_menu_page(
			__( 'Mailwright for Contact Form 7', 'mailwright-for-contact-form-7' ),
			__( 'Mailwright', 'mailwright-for-contact-form-7' ),
			$cap,
			'mwright',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-email-alt',
			30
		);

		$submenus = array(
			'mwright'                => array( __( 'Dashboard', 'mailwright-for-contact-form-7' ), 'render_dashboard' ),
			'mwright-templates'      => array( __( 'Email Templates', 'mailwright-for-contact-form-7' ), 'render_templates' ),
			'mwright-template-edit'  => array( __( 'Add New', 'mailwright-for-contact-form-7' ), 'render_editor' ),
			'mwright-contact-forms'  => array( __( 'Contact Forms', 'mailwright-for-contact-form-7' ), 'render_contact_forms' ),
			'mwright-assignments'    => array( __( 'Assignments', 'mailwright-for-contact-form-7' ), 'render_assignments' ),
			'mwright-branding'       => array( __( 'Global Branding', 'mailwright-for-contact-form-7' ), 'render_branding' ),
			'mwright-settings'       => array( __( 'Settings', 'mailwright-for-contact-form-7' ), 'render_settings' ),
			'mwright-tools'          => array( __( 'Tools', 'mailwright-for-contact-form-7' ), 'render_tools' ),
			'mwright-submissions'    => array( __( 'Submissions', 'mailwright-for-contact-form-7' ), 'render_submissions' ),
		);

		foreach ( $submenus as $slug => $config ) {
			list( $title, $callback ) = $config;

			$hook = add_submenu_page(
				'mwright',
				$title,
				$title,
				$cap,
				$slug,
				array( __CLASS__, $callback )
			);

			self::$hooks[ $slug ] = $hook;
		}

		add_action( 'load-' . self::$hooks['mwright-templates'], array( __CLASS__, 'load_templates_screen' ) );
		add_action( 'load-' . self::$hooks['mwright-submissions'], array( __CLASS__, 'load_submissions_screen' ) );
	}

	/**
	 * Sets up per-page screen options for the templates list.
	 */
	public static function load_templates_screen() {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Templates per page', 'mailwright-for-contact-form-7' ),
				'default' => 20,
				'option'  => 'mwright_per_page',
			)
		);
	}

	/**
	 * Per-page option for the submissions list, plus the single-row delete
	 * link (a GET action, so it is handled before anything is printed).
	 */
	public static function load_submissions_screen() {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Submissions per page', 'mailwright-for-contact-form-7' ),
				'default' => 20,
				'option'  => 'mwright_entries_per_page',
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked below.
		if ( 'delete' !== sanitize_key( wp_unslash( $_GET['entry_action'] ?? '' ) ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked below.
		$id = absint( $_GET['entry'] ?? 0 );

		self::verify( 'mwright_delete_entry_' . $id );

		$entry = MWRIGHT_Submissions::get( $id );

		MWRIGHT_Submissions::delete( array( $id ) );

		wp_safe_redirect(
			MWRIGHT_Plugin::url(
				'submissions',
				array(
					'form'          => $entry ? $entry['form_id'] : 0,
					'mwright_notice' => 'entry_deleted',
				)
			)
		);
		exit;
	}

	/**
	 * Whether the current screen belongs to this plugin.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	private static function is_plugin_screen( $hook ) {
		return in_array( $hook, self::$hooks, true );
	}

	/**
	 * The blocks the visual builder offers, in palette order.
	 *
	 * @return array Type => label and dashicon.
	 */
	public static function block_types() {
		return array(
			'heading' => array( 'label' => __( 'Heading', 'mailwright-for-contact-form-7' ), 'icon' => 'heading' ),
			'text'    => array( 'label' => __( 'Text', 'mailwright-for-contact-form-7' ), 'icon' => 'editor-paragraph' ),
			'fields'  => array( 'label' => __( 'Form Fields', 'mailwright-for-contact-form-7' ), 'icon' => 'list-view' ),
			'button'  => array( 'label' => __( 'Button', 'mailwright-for-contact-form-7' ), 'icon' => 'button' ),
			'image'   => array( 'label' => __( 'Image', 'mailwright-for-contact-form-7' ), 'icon' => 'format-image' ),
			'divider' => array( 'label' => __( 'Divider', 'mailwright-for-contact-form-7' ), 'icon' => 'minus' ),
			'spacer'  => array( 'label' => __( 'Spacer', 'mailwright-for-contact-form-7' ), 'icon' => 'editor-expand' ),
		);
	}

	/**
	 * Strings the visual builder needs in the browser.
	 *
	 * @return array
	 */
	private static function builder_i18n() {
		return array(
			'text'          => __( 'Text', 'mailwright-for-contact-form-7' ),
			'textHelp'      => __( 'Leave a blank line between paragraphs.', 'mailwright-for-contact-form-7' ),
			'level'         => __( 'Size', 'mailwright-for-contact-form-7' ),
			'align'         => __( 'Alignment', 'mailwright-for-contact-form-7' ),
			'left'          => __( 'Left', 'mailwright-for-contact-form-7' ),
			'center'        => __( 'Centre', 'mailwright-for-contact-form-7' ),
			'right'         => __( 'Right', 'mailwright-for-contact-form-7' ),
			'background'    => __( 'Background', 'mailwright-for-contact-form-7' ),
			'textColour'    => __( 'Text colour', 'mailwright-for-contact-form-7' ),
			'buttonColour'  => __( 'Button colour', 'mailwright-for-contact-form-7' ),
			'lineColour'    => __( 'Line colour', 'mailwright-for-contact-form-7' ),
			'url'           => __( 'Link URL', 'mailwright-for-contact-form-7' ),
			'imageUrl'      => __( 'Image URL', 'mailwright-for-contact-form-7' ),
			'altText'       => __( 'Alt text', 'mailwright-for-contact-form-7' ),
			'width'         => __( 'Width (px)', 'mailwright-for-contact-form-7' ),
			'height'        => __( 'Height (px)', 'mailwright-for-contact-form-7' ),
			'fieldRows'     => __( 'Rows', 'mailwright-for-contact-form-7' ),
			'rowLabel'      => __( 'Label', 'mailwright-for-contact-form-7' ),
			'rowTag'        => __( '[your-name]', 'mailwright-for-contact-form-7' ),
			'addRow'        => __( 'Add row', 'mailwright-for-contact-form-7' ),
			'addFormFields' => __( 'Add all form fields', 'mailwright-for-contact-form-7' ),
			'removeRow'     => __( 'Remove row', 'mailwright-for-contact-form-7' ),
			'chooseImage'   => __( 'Choose image', 'mailwright-for-contact-form-7' ),
			'moveUp'        => __( 'Move up', 'mailwright-for-contact-form-7' ),
			'moveDown'      => __( 'Move down', 'mailwright-for-contact-form-7' ),
			'duplicate'     => __( 'Duplicate block', 'mailwright-for-contact-form-7' ),
			'remove'        => __( 'Remove block', 'mailwright-for-contact-form-7' ),
			'headingSample' => __( 'Heading', 'mailwright-for-contact-form-7' ),
			'buttonSample'  => __( 'Click here', 'mailwright-for-contact-form-7' ),
		);
	}

	/**
	 * Loads CSS and JS on this plugin's screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue( $hook ) {
		if ( ! self::is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'mwright-admin',
			MWRIGHT_URL . 'assets/css/admin.css',
			array(),
			MWRIGHT_VERSION
		);

		$deps = array();

		if ( self::$hooks['mwright-branding'] === $hook ) {
			// wp.media has to be loaded before admin.js runs, or the
			// "Choose image" button binds nothing.
			wp_enqueue_media();
			$deps[] = 'media-editor';
		}

		wp_enqueue_script(
			'mwright-admin',
			MWRIGHT_URL . 'assets/js/admin.js',
			$deps,
			MWRIGHT_VERSION,
			true
		);

		wp_localize_script(
			'mwright-admin',
			'mwright',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mwright_admin' ),
				'i18n'    => array(
					'saving'       => __( 'Saving…', 'mailwright-for-contact-form-7' ),
					'saved'        => __( 'Template saved.', 'mailwright-for-contact-form-7' ),
					'error'        => __( 'Something went wrong. Please try again.', 'mailwright-for-contact-form-7' ),
					'confirm'      => __( 'Are you sure?', 'mailwright-for-contact-form-7' ),
					'cancel'       => __( 'Cancel', 'mailwright-for-contact-form-7' ),
					'deleteTitle'  => __( 'Delete template', 'mailwright-for-contact-form-7' ),
					'deleteBody'   => __( 'Are you sure you want to delete this template? This cannot be undone.', 'mailwright-for-contact-form-7' ),
					'deleteButton' => __( 'Delete template', 'mailwright-for-contact-form-7' ),
					'unsaved'      => __( 'You have unsaved changes.', 'mailwright-for-contact-form-7' ),
					'copied'       => __( 'Tag copied to clipboard.', 'mailwright-for-contact-form-7' ),
					'testSent'     => __( 'Test email sent.', 'mailwright-for-contact-form-7' ),
					'testPrompt'   => __( 'Send the test email to:', 'mailwright-for-contact-form-7' ),
					'sendTest'     => __( 'Send test', 'mailwright-for-contact-form-7' ),
					'previewTitle' => __( 'Email preview', 'mailwright-for-contact-form-7' ),
					'close'        => __( 'Close', 'mailwright-for-contact-form-7' ),
					'save'         => __( 'Save', 'mailwright-for-contact-form-7' ),
					'subject'      => __( 'Subject:', 'mailwright-for-contact-form-7' ),
					'remove'       => __( 'Remove', 'mailwright-for-contact-form-7' ),
					'keep'         => __( 'Keep', 'mailwright-for-contact-form-7' ),
					'replaceWith'  => __( 'Replace with…', 'mailwright-for-contact-form-7' ),
					'noTags'       => __( 'This form has no fields that can be used in an email.', 'mailwright-for-contact-form-7' ),
					/* translators: %s: comma-separated list of mail tags */
					'badAttach'    => __( 'Not a file upload field on the selected form: %s. Nothing will be attached.', 'mailwright-for-contact-form-7' ),
					'newTagsOne'   => __( '1 form tag is available but not used in this template.', 'mailwright-for-contact-form-7' ),
					/* translators: %d: number of unused form tags */
					'newTagsMany'  => __( '%d form tags are available but not used in this template.', 'mailwright-for-contact-form-7' ),
				),
			)
		);

		if ( self::$hooks['mwright-template-edit'] === $hook ) {
			// Core already ships CodeMirror; no third-party editor needed.
			$settings = wp_enqueue_code_editor(
				array(
					'type'       => 'text/html',
					'codemirror' => array(
						'lineNumbers' => true,
						'lineWrapping' => true,
					),
				)
			);

			// The builder picks images out of the media library.
			wp_enqueue_media();

			wp_enqueue_script(
				'mwright-editor',
				MWRIGHT_URL . 'assets/js/editor.js',
				array( 'mwright-admin' ),
				MWRIGHT_VERSION,
				true
			);

			wp_enqueue_script(
				'mwright-builder',
				MWRIGHT_URL . 'assets/js/builder.js',
				array( 'mwright-editor', 'media-editor', 'jquery-ui-sortable', 'jquery-ui-draggable' ),
				MWRIGHT_VERSION,
				true
			);

			wp_localize_script(
				'mwright-editor',
				'mwrightEditor',
				array(
					// False when the user turned syntax highlighting off; the
					// editor then falls back to a plain textarea.
					'codeEditor' => $settings ? $settings : false,
					'blocks'     => wp_list_pluck( self::block_types(), 'label' ),
					'i18n'       => self::builder_i18n(),
				)
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Screens
	 * ------------------------------------------------------------------ */

	/**
	 * Renders one of the view files.
	 *
	 * @param string $view View file base name.
	 * @param array  $data Variables extracted into the view.
	 */
	private static function view( $view, $data = array() ) {
		MWRIGHT_Plugin::require_cap();

		$file = MWRIGHT_DIR . 'admin/views/' . $view . '.php';

		if ( ! file_exists( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled, internal view data.
		extract( $data );

		require $file;
	}

	/** Dashboard screen. */
	public static function render_dashboard() {
		self::view( 'dashboard' );
	}

	/** Templates list screen. */
	public static function render_templates() {
		self::view( 'templates' );
	}

	/** Template editor screen. */
	public static function render_editor() {
		self::view( 'editor' );
	}

	/** Contact forms overview screen. */
	public static function render_contact_forms() {
		self::view( 'contact-forms' );
	}

	/** Assignments screen. */
	public static function render_assignments() {
		self::view( 'assignments' );
	}

	/** Global branding screen. */
	public static function render_branding() {
		self::view( 'branding' );
	}

	/** Settings screen. */
	public static function render_settings() {
		self::view( 'settings' );
	}

	/** Tools screen. */
	public static function render_tools() {
		self::view( 'tools' );
	}

	/**
	 * Streams one stored upload to an administrator.
	 *
	 * The files live outside the web root's reach on purpose, so this is the
	 * only way to them, and it costs a capability check and a nonce.
	 */
	public static function handle_download() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified on the next line.
		$entry_id = absint( $_GET['entry'] ?? 0 );

		self::verify( 'mwright_download_' . $entry_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$field = sanitize_text_field( wp_unslash( $_GET['field'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$index = absint( $_GET['index'] ?? 0 );

		$entry = MWRIGHT_Submissions::get( $entry_id );
		$files = $entry ? MWRIGHT_Submissions::files( $entry ) : array();
		$file  = $files[ $field ][ $index ] ?? null;
		$path  = $file ? MWRIGHT_Submissions::file_path( $file['path'] ) : '';

		if ( ! $path ) {
			wp_die(
				esc_html__( 'That file is no longer available.', 'mailwright-for-contact-form-7' ),
				404
			);
		}

		nocache_headers();

		header( 'Content-Type: ' . ( wp_check_filetype( $path )['type'] ?: 'application/octet-stream' ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header(
			'Content-Disposition: attachment; filename="' . sanitize_file_name( $file['name'] ) . '"'
		);
		// The browser must not sniff a different type out of the bytes.
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- streaming a local file, not fetching a URL.
		exit;
	}

	/** Renders the submissions log. */
	public static function render_submissions() {
		self::view( 'submissions' );
	}

	/**
	 * Shared page header markup.
	 *
	 * @param string $title   Screen title.
	 * @param string $actions Optional HTML for the right-hand actions.
	 */
	public static function header( $title, $actions = '' ) {
		printf(
			'<div class="mwright-header"><div><h1 class="mwright-header__title">%s</h1></div><div class="mwright-header__actions">%s</div></div>',
			esc_html( $title ),
			wp_kses_post( $actions )
		);
	}

	/**
	 * Prints an admin notice queued through the redirect URL.
	 */
	public static function flash() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message key.
		$notice = isset( $_GET['mwright_notice'] ) ? sanitize_key( wp_unslash( $_GET['mwright_notice'] ) ) : '';

		if ( ! $notice ) {
			return;
		}

		$messages = array(
			'branding_saved'  => array( 'success', __( 'Branding saved.', 'mailwright-for-contact-form-7' ) ),
			'settings_saved'  => array( 'success', __( 'Settings saved.', 'mailwright-for-contact-form-7' ) ),
			'imported'        => array( 'success', __( 'Templates imported.', 'mailwright-for-contact-form-7' ) ),
			'import_failed'   => array( 'error', __( 'That file could not be imported. Please upload a valid export file.', 'mailwright-for-contact-form-7' ) ),
			'log_cleared'     => array( 'success', __( 'Debug log cleared.', 'mailwright-for-contact-form-7' ) ),
			'template_saved'  => array( 'success', __( 'Template saved.', 'mailwright-for-contact-form-7' ) ),
			'entry_deleted'   => array( 'success', __( 'Submission deleted.', 'mailwright-for-contact-form-7' ) ),
			'demos_installed' => array( 'success', __( 'Demo templates installed.', 'mailwright-for-contact-form-7' ) ),
			'demos_present'   => array( 'info', __( 'All demo templates are already installed. Nothing was added.', 'mailwright-for-contact-form-7' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		list( $type, $message ) = $messages[ $notice ];

		printf(
			'<div class="mwright-alert mwright-alert--%s" role="status">%s</div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/* ---------------------------------------------------------------------
	 * Form handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Verifies nonce and capability for an admin-post request.
	 *
	 * @param string $action Nonce action.
	 */
	private static function verify( $action ) {
		MWRIGHT_Plugin::require_cap();
		check_admin_referer( $action );
	}

	/**
	 * Redirects back to a plugin screen with a notice.
	 *
	 * @param string $page   Page slug suffix.
	 * @param string $notice Notice key.
	 */
	private static function redirect( $page, $notice ) {
		wp_safe_redirect( MWRIGHT_Plugin::url( $page, array( 'mwright_notice' => $notice ) ) );
		exit;
	}

	/** Saves the global branding form. */
	public static function handle_save_branding() {
		self::verify( 'mwright_save_branding' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- each field is sanitized in MWRIGHT_Branding::save(); nonce checked in self::verify() above.
		MWRIGHT_Branding::save( wp_unslash( (array) ( $_POST['branding'] ?? array() ) ) );

		self::redirect( 'branding', 'branding_saved' );
	}

	/** Saves the settings form. */
	public static function handle_save_settings() {
		self::verify( 'mwright_save_settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- sanitized field by field below; nonce checked in self::verify() above.
		$input = wp_unslash( (array) ( $_POST['settings'] ?? array() ) );

		$clean = array(
			'default_type'        => ( isset( $input['default_type'] ) && 'text' === $input['default_type'] ) ? 'text' : 'html',
			'default_sender'      => sanitize_text_field( $input['default_sender'] ?? '' ),
			'test_recipient'      => sanitize_email( $input['test_recipient'] ?? '' ),
			'debug'               => empty( $input['debug'] ) ? 0 : 1,
			'delete_on_uninstall' => empty( $input['delete_on_uninstall'] ) ? 0 : 1,
		);

		update_option( MWRIGHT_Plugin::SETTINGS, $clean );

		self::redirect( 'settings', 'settings_saved' );
	}

	/**
	 * Streams the submissions currently being viewed as a CSV file.
	 *
	 * The same form, email-result and search filters the screen is using are
	 * applied, so what downloads is what was on screen — not just the page
	 * that happened to be open.
	 */
	public static function handle_export_entries() {
		self::verify( 'mwright_export_entries' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$form_id = absint( $_REQUEST['form'] ?? 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$status = sanitize_key( wp_unslash( $_REQUEST['status'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) );

		$filters = array(
			'form_id'  => $form_id,
			'status'   => $status,
			'search'   => $search,
			'per_page' => 200,
		);

		/*
		 * Two passes over the rows, 200 at a time. The first works out which
		 * columns this export needs; the second writes them. Batching keeps a
		 * long log from being held in memory all at once.
		 */
		$answers = array();
		$files   = array();

		// A chosen form contributes its own field order first.
		foreach ( MWRIGHT_CF7_Bridge::form_tags( $form_id ) as $tag ) {
			if ( $tag['is_file'] ) {
				$files[ $tag['name'] ] = $tag['label'];
			} else {
				$answers[ $tag['name'] ] = $tag['label'];
			}
		}

		foreach ( self::entry_batches( $filters ) as $batch ) {
			foreach ( $batch as $entry ) {
				foreach ( array_keys( MWRIGHT_Submissions::answers( $entry ) ) as $name ) {
					$answers[ $name ] = $answers[ $name ] ?? MWRIGHT_CF7_Bridge::friendly_label( $name );
				}

				foreach ( array_keys( MWRIGHT_Submissions::files( $entry ) ) as $name ) {
					$files[ $name ] = $files[ $name ] ?? MWRIGHT_CF7_Bridge::friendly_label( $name );
				}
			}
		}

		$name = $form_id ? sanitize_title( MWRIGHT_Submissions::forms()[ $form_id ]['title'] ?? (string) $form_id ) : 'all-forms';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mwright-submissions-' . $name . '-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' );

		// Without the byte order mark Excel reads UTF-8 as its own codepage.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- writing to the response, not the filesystem.

		self::csv_row(
			$out,
			array_merge(
				array(
					__( 'Submitted', 'mailwright-for-contact-form-7' ),
					__( 'Form', 'mailwright-for-contact-form-7' ),
					__( 'Email result', 'mailwright-for-contact-form-7' ),
					__( 'IP address', 'mailwright-for-contact-form-7' ),
				),
				array_values( $answers ),
				array_values( $files )
			)
		);

		foreach ( self::entry_batches( $filters ) as $batch ) {
			foreach ( $batch as $entry ) {
				$row = array(
					$entry['submitted_at'],
					$entry['form_title'],
					'sent' === $entry['status']
						? __( 'Sent', 'mailwright-for-contact-form-7' )
						: __( 'Not sent', 'mailwright-for-contact-form-7' ),
					$entry['remote_ip'],
				);

				$entry_answers = MWRIGHT_Submissions::answers( $entry );

				foreach ( array_keys( $answers ) as $field ) {
					$row[] = isset( $entry_answers[ $field ] )
						? MWRIGHT_Submissions::flatten( $entry_answers[ $field ] )
						: '';
				}

				$entry_files = MWRIGHT_Submissions::files( $entry );

				foreach ( array_keys( $files ) as $field ) {
					$row[] = isset( $entry_files[ $field ] )
						? implode( ', ', wp_list_pluck( $entry_files[ $field ], 'name' ) )
						: '';
				}

				self::csv_row( $out, $row );
			}
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- closing the response stream.
		exit;
	}

	/**
	 * Yields matching submissions a page at a time.
	 *
	 * @param array $filters Query arguments, including per_page.
	 * @return Generator
	 */
	private static function entry_batches( $filters ) {
		$page = 1;

		do {
			$results = MWRIGHT_Submissions::query( array( 'page' => $page ) + $filters );

			if ( $results['items'] ) {
				yield $results['items'];
			}

			$seen = $page * $filters['per_page'];
			++$page;
		} while ( $seen < $results['total'] );
	}

	/**
	 * Writes one CSV line.
	 *
	 * A cell that opens with =, +, - or @ is treated as a formula by Excel and
	 * Google Sheets, so it is quoted into being plain text first.
	 *
	 * @param resource $handle Output stream.
	 * @param array    $row    Cells.
	 */
	private static function csv_row( $handle, $row ) {
		fputcsv( $handle, array_map( array( 'MWRIGHT_Submissions', 'csv_cell' ), $row ), ',', '"', '' );
	}

	/** Installs whichever starter templates are missing. */
	public static function handle_install_demos() {
		self::verify( 'mwright_install_demos' );

		require_once MWRIGHT_DIR . 'includes/starter-templates.php';

		self::redirect( 'tools', mwright_install_starter_templates() ? 'demos_installed' : 'demos_present' );
	}

	/** Streams a JSON export of all templates. */
	public static function handle_export() {
		self::verify( 'mwright_export' );

		$templates = array();

		foreach ( array_keys( MWRIGHT_Template_Post_Type::options() ) as $id ) {
			$template = MWRIGHT_Template_Post_Type::get( $id );

			if ( ! $template ) {
				continue;
			}

			unset( $template['id'], $template['author'], $template['modified'], $template['form_context'] );

			$templates[] = $template;
		}

		$payload = array(
			'format'    => 'mwright',
			'version'   => MWRIGHT_VERSION,
			'exported'  => gmdate( 'c' ),
			'branding'  => MWRIGHT_Branding::get(),
			'templates' => $templates,
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mwright-email-templates-' . gmdate( 'Y-m-d' ) . '.json' );
		header( 'X-Content-Type-Options: nosniff' );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/** Imports templates from an uploaded JSON file. */
	public static function handle_import() {
		self::verify( 'mwright_import' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in self::verify(); is_uploaded_file() is the validation that matters for a temp path.
		if ( empty( $_FILES['import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			self::redirect( 'tools', 'import_failed' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- server-side temp path from $_FILES; nonce checked in self::verify() above.
		$raw = file_get_contents( $_FILES['import_file']['tmp_name'] );

		$data = json_decode( (string) $raw, true );

		if ( ! is_array( $data ) || 'mwright' !== ( $data['format'] ?? '' ) || empty( $data['templates'] ) || ! is_array( $data['templates'] ) ) {
			self::redirect( 'tools', 'import_failed' );
		}

		// Branding is exported too, but it is global: only restore on request.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in self::verify() above.
		if ( ! empty( $_POST['import_branding'] ) && ! empty( $data['branding'] ) && is_array( $data['branding'] ) ) {
			MWRIGHT_Branding::save( $data['branding'] );
		}

		$imported = 0;

		foreach ( $data['templates'] as $template ) {
			if ( ! is_array( $template ) || empty( $template['name'] ) ) {
				continue;
			}

			// Never trust an ID from a file: always insert a new template.
			$template['id'] = 0;

			// Imported templates land as drafts so nothing goes live unreviewed.
			$template['status'] = 'draft';

			if ( ! is_wp_error( MWRIGHT_Template_Post_Type::save( $template ) ) ) {
				++$imported;
			}
		}

		self::redirect( 'tools', $imported ? 'imported' : 'import_failed' );
	}

	/** Empties the debug log. */
	public static function handle_clear_log() {
		self::verify( 'mwright_clear_log' );

		delete_option( 'mwright_log' );

		self::redirect( 'tools', 'log_cleared' );
	}
}

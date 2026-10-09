<?php
/**
 * The Contact Form 7 integration layer.
 *
 * Templates are injected into CF7 at runtime through the
 * `wpcf7_contact_form_properties` filter. Nothing is ever written into CF7's
 * own `_mail` / `_mail_2` post meta, which means:
 *
 *  - applying a template is instantly reversible,
 *  - an administrator's hand-written CF7 mail config is never destroyed,
 *  - detaching restores CF7's original settings with no migration.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

class MWRIGHT_CF7_Bridge {

	const OPTION = 'mwright_assignments';

	/** Template slot => CF7 mail property. */
	const SLOTS = array(
		'admin'    => 'mail',
		'customer' => 'mail_2',
	);

	/**
	 * Hooks the integration.
	 */
	public static function init() {
		add_filter( 'wpcf7_contact_form_properties', array( __CLASS__, 'filter_properties' ), 10, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'prune_assignments' ), 10, 2 );
		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'log_sent' ) );
		add_action( 'wpcf7_mail_failed', array( __CLASS__, 'log_failed' ) );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_managed_notice' ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Runtime injection
	 * ------------------------------------------------------------------ */

	/**
	 * Swaps CF7's mail properties for the rendered templates.
	 *
	 * @param array             $properties   Contact form properties.
	 * @param WPCF7_ContactForm $contact_form The form being set up.
	 * @return array
	 */
	public static function filter_properties( $properties, $contact_form ) {
		if ( ! $contact_form instanceof WPCF7_ContactForm ) {
			return $properties;
		}

		$form_id = (int) $contact_form->id();

		if ( ! $form_id ) {
			return $properties;
		}

		/*
		 * WPCF7_ContactForm::save() persists get_properties() straight to post
		 * meta. If our template were live while an administrator saved the CF7
		 * Mail tab, their original mail config would be overwritten for good.
		 * So we stand down on every request that can save a form.
		 */
		if ( self::is_form_editing_request() ) {
			return $properties;
		}

		$assigned = self::for_form( $form_id );

		if ( ! $assigned ) {
			return $properties;
		}

		foreach ( self::SLOTS as $slot => $prop ) {
			$template_id = (int) ( $assigned[ $slot ] ?? 0 );

			if ( ! $template_id ) {
				continue;
			}

			$mail = MWRIGHT_Renderer::to_mail_array(
				$template_id,
				(array) ( $properties[ $prop ] ?? array() ),
				$slot,
				$contact_form
			);

			if ( $mail ) {
				$properties[ $prop ] = $mail;
			}
		}

		return $properties;
	}

	/**
	 * Whether the current request is one that can save a CF7 form.
	 *
	 * Form submissions (REST .../feedback) are deliberately NOT matched here —
	 * that is exactly when the template must be applied.
	 *
	 * @return bool
	 */
	private static function is_form_editing_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only guard, no state changes.
		if ( is_admin() && isset( $_REQUEST['page'] ) && 'wpcf7' === $_REQUEST['page'] ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';

			if ( preg_match( '#^/?contact-form-7/v1/contact-forms(/\d+)?/?$#', (string) $route ) ) {
				return true;
			}
		}

		return false;
	}

	/* ---------------------------------------------------------------------
	 * Assignments
	 * ------------------------------------------------------------------ */

	/**
	 * The whole assignment map: form id => [ slot => template id ].
	 *
	 * Stored as a single autoloaded option, so answering "is this form
	 * managed?" on a submission costs no extra query.
	 *
	 * @return array
	 */
	public static function assignments() {
		$map   = (array) get_option( self::OPTION, array() );
		$clean = array();

		foreach ( $map as $form_id => $slots ) {
			$form_id = absint( $form_id );

			if ( ! $form_id || ! is_array( $slots ) ) {
				continue;
			}

			foreach ( self::SLOTS as $slot => $unused ) {
				$template_id = absint( $slots[ $slot ] ?? 0 );

				if ( $template_id ) {
					$clean[ $form_id ][ $slot ] = $template_id;
				}
			}
		}

		return $clean;
	}

	/**
	 * Assignments for one form.
	 *
	 * @param int $form_id CF7 form ID.
	 * @return array
	 */
	public static function for_form( $form_id ) {
		$map = self::assignments();
		return $map[ (int) $form_id ] ?? array();
	}

	/**
	 * Forms a template is assigned to.
	 *
	 * @param int $template_id Template ID.
	 * @return array List of form IDs.
	 */
	public static function forms_using( $template_id ) {
		$template_id = (int) $template_id;
		$out         = array();

		foreach ( self::assignments() as $form_id => $slots ) {
			if ( in_array( $template_id, array_map( 'intval', $slots ), true ) ) {
				$out[] = $form_id;
			}
		}

		return $out;
	}

	/**
	 * Template IDs currently assigned to any form.
	 *
	 * @return array
	 */
	public static function assigned_template_ids() {
		$ids = array();

		foreach ( self::assignments() as $slots ) {
			$ids = array_merge( $ids, array_map( 'intval', array_values( $slots ) ) );
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Assigns a template to a form slot.
	 *
	 * @param int    $form_id     CF7 form ID.
	 * @param string $slot        'admin' or 'customer'.
	 * @param int    $template_id Template ID.
	 * @return true|WP_Error
	 */
	public static function assign( $form_id, $slot, $template_id ) {
		$form_id     = absint( $form_id );
		$template_id = absint( $template_id );

		if ( ! isset( self::SLOTS[ $slot ] ) ) {
			return new WP_Error( 'mwright_bad_slot', __( 'Unknown email slot.', 'mailwright-for-contact-form-7' ) );
		}

		if ( ! self::form( $form_id ) ) {
			return new WP_Error( 'mwright_no_form', __( 'That contact form no longer exists.', 'mailwright-for-contact-form-7' ) );
		}

		if ( ! MWRIGHT_Template_Post_Type::get( $template_id ) ) {
			return new WP_Error( 'mwright_no_template', __( 'That template no longer exists.', 'mailwright-for-contact-form-7' ) );
		}

		$map = self::assignments();

		$map[ $form_id ][ $slot ] = $template_id;

		update_option( self::OPTION, $map );

		MWRIGHT_Plugin::log(
			sprintf( 'Template #%d assigned to form #%d (%s email).', $template_id, $form_id, $slot )
		);

		return true;
	}

	/**
	 * Removes a template from a form slot, restoring CF7's own settings.
	 *
	 * @param int    $form_id CF7 form ID.
	 * @param string $slot    'admin' or 'customer'.
	 * @return true
	 */
	public static function detach( $form_id, $slot ) {
		$form_id = absint( $form_id );
		$map     = self::assignments();

		unset( $map[ $form_id ][ $slot ] );

		if ( empty( $map[ $form_id ] ) ) {
			unset( $map[ $form_id ] );
		}

		update_option( self::OPTION, $map );

		MWRIGHT_Plugin::log( sprintf( 'Template detached from form #%d (%s email).', $form_id, $slot ) );

		return true;
	}

	/**
	 * Drops assignments when a template or a contact form is deleted.
	 *
	 * @param int     $post_id Deleted post ID.
	 * @param WP_Post $post    Deleted post object.
	 */
	public static function prune_assignments( $post_id, $post = null ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$is_template = MWRIGHT_Template_Post_Type::POST_TYPE === $post->post_type;
		$is_form     = 'wpcf7_contact_form' === $post->post_type;

		if ( ! $is_template && ! $is_form ) {
			return;
		}

		$map     = self::assignments();
		$changed = false;

		if ( $is_form && isset( $map[ $post_id ] ) ) {
			unset( $map[ $post_id ] );
			$changed = true;
		}

		if ( $is_template ) {
			foreach ( $map as $form_id => $slots ) {
				foreach ( $slots as $slot => $template_id ) {
					if ( (int) $template_id === (int) $post_id ) {
						unset( $map[ $form_id ][ $slot ] );
						$changed = true;
					}
				}

				if ( empty( $map[ $form_id ] ) ) {
					unset( $map[ $form_id ] );
				}
			}
		}

		if ( $changed ) {
			update_option( self::OPTION, $map );
		}
	}

	/* ---------------------------------------------------------------------
	 * Reading Contact Form 7
	 * ------------------------------------------------------------------ */

	/**
	 * All contact forms as id => title.
	 *
	 * @return array
	 */
	public static function forms() {
		$forms = WPCF7_ContactForm::find(
			array(
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$out = array();

		foreach ( $forms as $form ) {
			$out[ (int) $form->id() ] = $form->title();
		}

		return $out;
	}

	/**
	 * Loads a single contact form.
	 *
	 * @param int $form_id CF7 form ID.
	 * @return WPCF7_ContactForm|null
	 */
	public static function form( $form_id ) {
		$form = wpcf7_contact_form( absint( $form_id ) );
		return $form instanceof WPCF7_ContactForm ? $form : null;
	}

	/**
	 * Mail-tags available on a form, straight from CF7's own API.
	 *
	 * @param int $form_id CF7 form ID.
	 * @return array List of tag names, without brackets.
	 */
	public static function mail_tags( $form_id ) {
		$form = self::form( $form_id );
		return $form ? (array) $form->collect_mail_tags() : array();
	}

	/**
	 * Form tags with the extra detail the editor sidebar needs.
	 *
	 * @param int $form_id CF7 form ID.
	 * @return array List of [ name, label, type, required, values ].
	 */
	public static function form_tags( $form_id ) {
		$form = self::form( $form_id );

		if ( ! $form ) {
			return array();
		}

		$available = $form->collect_mail_tags();
		$details   = array();

		foreach ( $form->scan_form_tags() as $tag ) {
			if ( ! $tag->name || ! in_array( $tag->name, $available, true ) || isset( $details[ $tag->name ] ) ) {
				continue;
			}

			$details[ $tag->name ] = array(
				'name'     => $tag->name,
				'label'    => self::friendly_label( $tag->name ),
				'type'     => $tag->basetype,
				'is_file'  => in_array( $tag->type, self::file_tag_types(), true ),
				'required' => (bool) $tag->is_required(),
				'values'   => array_values( (array) $tag->values ),
			);
		}

		// Keep any mail-tag CF7 reports but we could not match to a form tag.
		foreach ( $available as $name ) {
			if ( ! isset( $details[ $name ] ) ) {
				$details[ $name ] = array(
					'name'     => $name,
					'label'    => self::friendly_label( $name ),
					'type'     => 'text',
					'is_file'  => false,
					'required' => false,
					'values'   => array(),
				);
			}
		}

		return array_values( $details );
	}

	/**
	 * Form-tag types that produce an uploaded file.
	 *
	 * Asks Contact Form 7 which types declare its own `file-uploading`
	 * feature rather than hard-coding 'file', so upload fields added by
	 * add-ons are detected too. Returns both the plain and required forms,
	 * e.g. 'file' and 'file*'.
	 *
	 * @return array List of tag type names.
	 */
	public static function file_tag_types() {
		static $types = null;

		if ( null === $types ) {
			$types = WPCF7_FormTagsManager::get_instance()->collect_tag_types( 'file-uploading' );
		}

		return $types;
	}

	/**
	 * File-upload field names on a form.
	 *
	 * @param int $form_id CF7 form ID.
	 * @return array List of field names, without brackets.
	 */
	public static function file_fields( $form_id ) {
		$names = array();

		foreach ( self::form_tags( $form_id ) as $tag ) {
			if ( ! empty( $tag['is_file'] ) ) {
				$names[] = $tag['name'];
			}
		}

		return $names;
	}

	/**
	 * Turns "your-email" into "Email" for display. The real CF7 tag is always
	 * what gets inserted into the template.
	 *
	 * @param string $name Tag name.
	 * @return string
	 */
	public static function friendly_label( $name ) {
		$label = preg_replace( '/^(your|the)[-_]/i', '', (string) $name );
		$label = str_replace( array( '-', '_' ), ' ', $label );

		return ucwords( trim( $label ) );
	}

	/**
	 * CF7's special mail-tags, as enumerated in includes/special-mail-tags.php.
	 *
	 * @return array Tag name => label.
	 */
	public static function special_tags() {
		return array(
			'_site_title'         => __( 'Site Title', 'mailwright-for-contact-form-7' ),
			'_site_description'   => __( 'Site Tagline', 'mailwright-for-contact-form-7' ),
			'_site_url'           => __( 'Site URL', 'mailwright-for-contact-form-7' ),
			'_site_domain'        => __( 'Site Domain', 'mailwright-for-contact-form-7' ),
			'_site_admin_email'   => __( 'Site Admin Email', 'mailwright-for-contact-form-7' ),
			'_contact_form_title' => __( 'Form Title', 'mailwright-for-contact-form-7' ),
			'_date'               => __( 'Submission Date', 'mailwright-for-contact-form-7' ),
			'_time'               => __( 'Submission Time', 'mailwright-for-contact-form-7' ),
			'_url'                => __( 'Page URL', 'mailwright-for-contact-form-7' ),
			'_remote_ip'          => __( 'Visitor IP', 'mailwright-for-contact-form-7' ),
			'_user_agent'         => __( 'Browser', 'mailwright-for-contact-form-7' ),
			'_post_title'         => __( 'Post Title', 'mailwright-for-contact-form-7' ),
			'_post_url'           => __( 'Post URL', 'mailwright-for-contact-form-7' ),
			'_post_author'        => __( 'Post Author', 'mailwright-for-contact-form-7' ),
			'_post_author_email'  => __( 'Post Author Email', 'mailwright-for-contact-form-7' ),
			'_post_id'            => __( 'Post ID', 'mailwright-for-contact-form-7' ),
			'_post_name'          => __( 'Post Slug', 'mailwright-for-contact-form-7' ),
			'_invalid_fields'     => __( 'Invalid Field Count', 'mailwright-for-contact-form-7' ),
			// Contact Form 7 fills these in only when the visitor is logged in.
			'_user_login'         => __( 'Username', 'mailwright-for-contact-form-7' ),
			'_user_email'         => __( 'User Email', 'mailwright-for-contact-form-7' ),
			'_user_display_name'  => __( 'User Display Name', 'mailwright-for-contact-form-7' ),
			'_user_first_name'    => __( 'User First Name', 'mailwright-for-contact-form-7' ),
			'_user_last_name'     => __( 'User Last Name', 'mailwright-for-contact-form-7' ),
			'_user_nickname'      => __( 'User Nickname', 'mailwright-for-contact-form-7' ),
			'_user_url'           => __( 'User Website', 'mailwright-for-contact-form-7' ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Validation
	 * ------------------------------------------------------------------ */

	/**
	 * Finds tags used in a template that the chosen form cannot supply.
	 *
	 * @param string $text    Subject and body concatenated.
	 * @param int    $form_id CF7 form ID.
	 * @return array Unknown tag names.
	 */
	public static function unknown_tags( $text, $form_id ) {
		if ( ! preg_match_all( '/\[([a-z_][0-9a-z:._-]*)\]/i', (string) $text, $matches ) ) {
			return array();
		}

		$known = array_merge(
			self::mail_tags( $form_id ),
			array_keys( self::special_tags() ),
			array_keys( MWRIGHT_Branding::tags() )
		);

		$unknown = array();

		foreach ( array_unique( $matches[1] ) as $tag ) {
			// CF7 supports [tag] and the raw-value form [_raw_tag].
			$base = preg_replace( '/^_raw_/', '', $tag );

			if ( in_array( $tag, $known, true ) || in_array( $base, $known, true ) ) {
				continue;
			}

			// Special tags come in families, e.g. [_post_meta_xxx].
			if ( str_starts_with( $tag, '_post_' ) || str_starts_with( $tag, '_user_' ) ) {
				continue;
			}

			$unknown[] = $tag;
		}

		return $unknown;
	}

	/**
	 * Attachment lines that do not name a file-upload field on the form.
	 *
	 * CF7 silently attaches nothing when the name is not an upload field, so
	 * this is the difference between a broken template and a visible warning.
	 *
	 * @param string $spec    Attachment lines.
	 * @param int    $form_id CF7 form ID.
	 * @return array Tag names that will not attach anything.
	 */
	public static function invalid_attachments( $spec, $form_id ) {
		if ( ! preg_match_all( '/\[([^\]]+)\]/', (string) $spec, $matches ) ) {
			return array();
		}

		$files = self::file_fields( $form_id );
		$bad   = array();

		foreach ( array_unique( $matches[1] ) as $tag ) {
			if ( ! in_array( $tag, $files, true ) ) {
				$bad[] = $tag;
			}
		}

		return $bad;
	}

	/**
	 * New form tags that the template is not using yet.
	 *
	 * @param string $text    Subject and body concatenated.
	 * @param int    $form_id CF7 form ID.
	 * @return array Tag names.
	 */
	public static function unused_tags( $text, $form_id ) {
		$unused = array();

		foreach ( self::mail_tags( $form_id ) as $tag ) {
			if ( ! str_contains( (string) $text, '[' . $tag . ']' ) ) {
				$unused[] = $tag;
			}
		}

		return $unused;
	}

	/* ---------------------------------------------------------------------
	 * Ownership notice on CF7's own screens
	 * ------------------------------------------------------------------ */

	/**
	 * Tells administrators, on CF7's edit screen, that mail is managed here.
	 */
	public static function render_managed_notice() {
		$screen = get_current_screen();

		if ( ! $screen || ! str_contains( (string) $screen->id, 'wpcf7' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the current form ID for display only.
		$form_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! $form_id || ! current_user_can( MWRIGHT_Plugin::cap() ) ) {
			return;
		}

		$assigned = self::for_form( $form_id );

		if ( ! $assigned ) {
			return;
		}

		$labels = array();

		foreach ( $assigned as $slot => $template_id ) {
			$template = MWRIGHT_Template_Post_Type::get( $template_id );

			if ( ! $template ) {
				continue;
			}

			$labels[] = sprintf(
				'<a href="%s"><strong>%s</strong></a> (%s)',
				esc_url( MWRIGHT_Plugin::url( 'template-edit', array( 'template' => $template_id ) ) ),
				esc_html( $template['name'] ),
				'admin' === $slot
					? esc_html__( 'admin email', 'mailwright-for-contact-form-7' )
					: esc_html__( 'customer email', 'mailwright-for-contact-form-7' )
			);
		}

		if ( ! $labels ) {
			return;
		}

		$message = sprintf(
			'<p><strong>%s</strong> %s</p><p>%s</p><p><a class="button" href="%s">%s</a></p>',
			esc_html__( 'Managed by Mailwright for Contact Form 7.', 'mailwright-for-contact-form-7' ),
			wp_kses_post( implode( ', ', $labels ) ),
			esc_html__( 'The Mail settings below are stored by Contact Form 7 but are not used while a template is assigned. Detach the template to hand control back to Contact Form 7 — your settings here are untouched.', 'mailwright-for-contact-form-7' ),
			esc_url( MWRIGHT_Plugin::url( 'assignments' ) ),
			esc_html__( 'Manage assignments', 'mailwright-for-contact-form-7' )
		);

		wp_admin_notice(
			$message,
			array(
				'type'               => 'info',
				'paragraph_wrap'     => false,
				'additional_classes' => array( 'mwright-managed-notice' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Logging
	 * ------------------------------------------------------------------ */

	/**
	 * Records a successful send for a managed form.
	 *
	 * @param WPCF7_ContactForm $contact_form Submitted form.
	 */
	public static function log_sent( $contact_form ) {
		self::log_result( $contact_form, 'Success' );
	}

	/**
	 * Records a failed send for a managed form.
	 *
	 * @param WPCF7_ContactForm $contact_form Submitted form.
	 */
	public static function log_failed( $contact_form ) {
		self::log_result( $contact_form, 'Failed' );
	}

	/**
	 * Shared logging body. Email content is never written to the log.
	 *
	 * @param WPCF7_ContactForm $contact_form Submitted form.
	 * @param string            $status       Outcome label.
	 */
	private static function log_result( $contact_form, $status ) {
		if ( ! $contact_form instanceof WPCF7_ContactForm ) {
			return;
		}

		$assigned = self::for_form( $contact_form->id() );

		if ( ! $assigned ) {
			return;
		}

		$names = array();

		foreach ( $assigned as $slot => $template_id ) {
			$template = MWRIGHT_Template_Post_Type::get( $template_id );

			if ( $template ) {
				$names[] = $slot . ': ' . $template['name'];
			}
		}

		MWRIGHT_Plugin::log(
			sprintf(
				'%s — form "%s" (#%d), templates [%s].',
				$status,
				$contact_form->title(),
				$contact_form->id(),
				implode( ', ', $names )
			)
		);
	}
}

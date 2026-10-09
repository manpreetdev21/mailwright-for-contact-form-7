<?php
/**
 * Every AJAX endpoint, behind one nonce and one capability check.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

class MWRIGHT_Ajax {

	/** Action name => handler method. */
	const ACTIONS = array(
		'save_template'      => 'save_template',
		'delete_template'    => 'delete_template',
		'duplicate_template' => 'duplicate_template',
		'preview'            => 'preview',
		'send_test'          => 'send_test',
		'assign'             => 'assign',
		'detach'             => 'detach',
		'form_tags'          => 'form_tags',
	);

	/**
	 * Registers the endpoints.
	 */
	public static function init() {
		foreach ( array_keys( self::ACTIONS ) as $action ) {
			add_action( 'wp_ajax_mwright_' . $action, array( __CLASS__, 'dispatch' ) );
		}
	}

	/**
	 * Validates the request, then hands off to the right handler.
	 */
	public static function dispatch() {
		check_ajax_referer( 'mwright_admin', 'nonce' );

		// A valid nonce proves intent, not permission. Check both.
		if ( ! current_user_can( MWRIGHT_Plugin::cap() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to do that.', 'mailwright-for-contact-form-7' ) ),
				403
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$action = str_replace( 'mwright_', '', sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) ) );

		if ( ! isset( self::ACTIONS[ $action ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown request.', 'mailwright-for-contact-form-7' ) ), 400 );
		}

		call_user_func( array( __CLASS__, self::ACTIONS[ $action ] ) );
	}

	/**
	 * Collects the template fields from the request.
	 *
	 * Sanitizing happens in MWRIGHT_Template_Post_Type::save() so that the
	 * editor, the importer and the starter templates all go through the
	 * same funnel.
	 *
	 * @return array
	 */
	private static function posted_template() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in dispatch(); sanitized on save.
		$raw = wp_unslash( (array) ( $_POST['template'] ?? array() ) );

		return array(
			'id'            => absint( $raw['id'] ?? 0 ),
			'name'          => (string) ( $raw['name'] ?? '' ),
			'type'          => 'text' === ( $raw['type'] ?? '' ) ? 'text' : 'html',
			'subject'       => (string) ( $raw['subject'] ?? '' ),
			'preview_text'  => (string) ( $raw['preview_text'] ?? '' ),
			'body'          => (string) ( $raw['body'] ?? '' ),
			'description'   => (string) ( $raw['description'] ?? '' ),
			'status'        => (string) ( $raw['status'] ?? 'publish' ),
			'recipient'     => (string) ( $raw['recipient'] ?? '' ),
			'sender'        => (string) ( $raw['sender'] ?? '' ),
			'headers'       => (string) ( $raw['headers'] ?? '' ),
			'attachments'   => (string) ( $raw['attachments'] ?? '' ),
			'category'      => (string) ( $raw['category'] ?? '' ),
			'exclude_blank' => empty( $raw['exclude_blank'] ) ? 0 : 1,
			'form_context'  => absint( $raw['form_context'] ?? 0 ),
		);
	}

	/**
	 * Reads a form ID from the request.
	 *
	 * @return int
	 */
	private static function posted_form_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- verified in dispatch().
		return absint( $_REQUEST['form_id'] ?? 0 );
	}

	/**
	 * Reads a template ID from the request.
	 *
	 * @return int
	 */
	private static function posted_template_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- verified in dispatch().
		return absint( $_REQUEST['template_id'] ?? 0 );
	}

	/* ---------------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------------ */

	/** Creates or updates a template. */
	public static function save_template() {
		$input = self::posted_template();
		$id    = MWRIGHT_Template_Post_Type::save( $input );

		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ) );
		}

		$saved = MWRIGHT_Template_Post_Type::get( $id );

		wp_send_json_success(
			array(
				'id'       => $id,
				'message'  => __( 'Template saved.', 'mailwright-for-contact-form-7' ),
				'status'   => $saved['status'],
				'label'    => MWRIGHT_Template_Post_Type::status_label( $saved['status'] ),
				'editUrl'  => MWRIGHT_Plugin::url( 'template-edit', array( 'template' => $id ) ),
				'warnings' => self::tag_report( $input['subject'] . ' ' . $input['body'], $input['form_context'], $input['attachments'] ),
			)
		);
	}

	/** Deletes a template, unless a form still uses it. */
	public static function delete_template() {
		$id = self::posted_template_id();

		if ( ! MWRIGHT_Template_Post_Type::get( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Template not found.', 'mailwright-for-contact-form-7' ) ) );
		}

		$in_use = MWRIGHT_CF7_Bridge::forms_using( $id );

		if ( $in_use ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: number of contact forms */
						_n(
							'This template is still assigned to %d contact form. Detach it first.',
							'This template is still assigned to %d contact forms. Detach them first.',
							count( $in_use ),
							'mailwright-for-contact-form-7'
						),
						count( $in_use )
					),
				)
			);
		}

		wp_trash_post( $id );

		wp_send_json_success( array( 'message' => __( 'Template deleted.', 'mailwright-for-contact-form-7' ) ) );
	}

	/** Duplicates a template. */
	public static function duplicate_template() {
		$new_id = MWRIGHT_Template_Post_Type::duplicate( self::posted_template_id() );

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'id'      => $new_id,
				'message' => __( 'Template duplicated.', 'mailwright-for-contact-form-7' ),
				'editUrl' => MWRIGHT_Plugin::url( 'template-edit', array( 'template' => $new_id ) ),
			)
		);
	}

	/**
	 * Renders the current editor content with sample data.
	 *
	 * The response is displayed inside a sandboxed iframe by the client, so a
	 * hostile template can never reach the admin DOM.
	 */
	public static function preview() {
		$input = self::posted_template();

		$rendered = MWRIGHT_Renderer::sample_render_data( $input, $input['form_context'] );

		wp_send_json_success(
			array(
				'subject' => $rendered['subject'],
				'body'    => $rendered['body'],
				'type'    => $rendered['type'],
			)
		);
	}

	/** Sends a test email using sample data. */
	public static function send_test() {
		$input = self::posted_template();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- verified in dispatch().
		$recipient = sanitize_email( wp_unslash( $_POST['recipient'] ?? '' ) );

		if ( ! $recipient ) {
			$recipient = MWRIGHT_Plugin::setting( 'test_recipient' );
		}

		if ( ! $recipient ) {
			$recipient = wp_get_current_user()->user_email;
		}

		$result = MWRIGHT_Renderer::send_test( $input, $input['form_context'], $recipient );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: email address */
					__( 'Test email sent to %s.', 'mailwright-for-contact-form-7' ),
					$recipient
				),
			)
		);
	}

	/** Assigns a template to a form slot. */
	public static function assign() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- verified in dispatch().
		$slot = sanitize_key( $_REQUEST['slot'] ?? '' );

		$result = MWRIGHT_CF7_Bridge::assign( self::posted_form_id(), $slot, self::posted_template_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Template assigned.', 'mailwright-for-contact-form-7' ) ) );
	}

	/** Detaches a template from a form slot. */
	public static function detach() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- verified in dispatch().
		$slot = sanitize_key( $_REQUEST['slot'] ?? '' );

		MWRIGHT_CF7_Bridge::detach( self::posted_form_id(), $slot );

		wp_send_json_success(
			array(
				'message' => __( 'Template detached. Contact Form 7 is back in control of this email.', 'mailwright-for-contact-form-7' ),
			)
		);
	}

	/** Returns the tag sidebar data for a form, plus a tag report. */
	public static function form_tags() {
		$form_id = self::posted_form_id();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in dispatch(); used only for tag matching.
		$content = (string) wp_unslash( $_POST['content'] ?? '' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in dispatch(); used only for tag matching.
		$attachments = (string) wp_unslash( $_POST['attachments'] ?? '' );

		wp_send_json_success(
			array(
				'tags'    => MWRIGHT_CF7_Bridge::form_tags( $form_id ),
				'report'  => self::tag_report( $content, $form_id, $attachments ),
			)
		);
	}

	/**
	 * Unknown and unused tags for a body of text.
	 *
	 * @param string $content     Subject and body.
	 * @param int    $form_id     CF7 form ID.
	 * @param string $attachments Attachment lines.
	 * @return array
	 */
	private static function tag_report( $content, $form_id, $attachments = '' ) {
		if ( ! $form_id ) {
			return array(
				'unknown'     => array(),
				'unused'      => array(),
				'attachments' => array(),
			);
		}

		return array(
			'unknown'     => MWRIGHT_CF7_Bridge::unknown_tags( $content, $form_id ),
			'unused'      => MWRIGHT_CF7_Bridge::unused_tags( $content, $form_id ),
			'attachments' => MWRIGHT_CF7_Bridge::invalid_attachments( $attachments, $form_id ),
		);
	}
}

<?php
/**
 * Turns a stored template into something Contact Form 7 can send, and into
 * sample-data renders for previewing and test emails.
 *
 * CF7 keeps doing the mail-tag replacement, the HTML wrapping and the sending.
 * We only ever hand it strings.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

class MWRIGHT_Renderer {

	/**
	 * Builds a CF7 mail property array from a template.
	 *
	 * Any field the template leaves empty falls through to Contact Form 7's
	 * own value, so assigning a template never silently drops a Reply-To or
	 * an attachment rule.
	 *
	 * @param int               $template_id Template ID.
	 * @param array             $existing    CF7's current mail property.
	 * @param string            $slot        'admin' or 'customer'.
	 * @param WPCF7_ContactForm $form        Optional form, for defaults.
	 * @return array|null Null when the template cannot be used.
	 */
	public static function to_mail_array( $template_id, $existing = array(), $slot = 'admin', $form = null ) {
		$template = MWRIGHT_Template_Post_Type::get( $template_id );

		// Only active templates are allowed to take over a live form.
		if ( ! $template || 'publish' !== $template['status'] ) {
			return null;
		}

		$is_html = 'html' === $template['type'];

		$body = self::prepare_body( $template );

		$subject = MWRIGHT_Branding::replace( (string) $template['subject'], false );

		if ( '' === trim( $subject ) ) {
			$subject = (string) ( $existing['subject'] ?? '' );
		}

		$recipient = trim( (string) $template['recipient'] );

		if ( '' === $recipient ) {
			$recipient = trim( (string) ( $existing['recipient'] ?? '' ) );
		}

		if ( '' === $recipient ) {
			$recipient = 'customer' === $slot ? '[your-email]' : get_option( 'admin_email' );
		}

		$sender = trim( (string) $template['sender'] );

		if ( '' === $sender ) {
			$sender = trim( (string) ( $existing['sender'] ?? '' ) );
		}

		if ( '' === $sender ) {
			$sender = trim( (string) MWRIGHT_Plugin::setting( 'default_sender' ) );
		}

		if ( '' === $sender ) {
			$sender = sprintf( '%s <%s>', get_bloginfo( 'name' ), self::default_from_address() );
		}

		/*
		 * Raw and unbranded on purpose: WPCF7_Mail::attachments() reads this
		 * property without tag replacement and matches [field] literally.
		 */
		$attachments = trim( (string) $template['attachments'] );

		if ( '' === $attachments ) {
			$attachments = (string) ( $existing['attachments'] ?? '' );
		}

		$headers = trim( (string) $template['headers'] );

		if ( '' === $headers ) {
			$headers = (string) ( $existing['additional_headers'] ?? '' );
		}

		return array(
			'subject'            => $subject,
			'sender'             => $sender,
			'recipient'          => $recipient,
			'body'               => $body,
			'additional_headers' => $headers,
			// Contact Form 7 still owns the files themselves; we only name fields.
			'attachments'        => $attachments,
			'use_html'           => $is_html ? 1 : 0,
			'exclude_blank'      => (int) $template['exclude_blank'],
			// The customer email is CF7's mail_2, which is off unless enabled.
			'active'             => 'customer' === $slot ? true : ( $existing['active'] ?? true ),
		);
	}

	/**
	 * Resolves branding tags and inserts the preheader.
	 *
	 * @param array $template Template data.
	 * @return string
	 */
	public static function prepare_body( $template ) {
		$is_html = 'html' === $template['type'];
		$body    = MWRIGHT_Branding::replace( (string) $template['body'], $is_html );

		$preview = trim( (string) $template['preview_text'] );

		if ( '' === $preview ) {
			return $body;
		}

		if ( ! $is_html ) {
			return $preview . "\n\n" . $body;
		}

		// Hidden preheader: what inboxes show next to the subject line.
		$preheader = sprintf(
			'<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">%s</div>',
			esc_html( $preview )
		);

		if ( preg_match( '/<body[^>]*>/i', $body ) ) {
			return preg_replace( '/(<body[^>]*>)/i', '$1' . $preheader, $body, 1 );
		}

		return $preheader . $body;
	}

	/**
	 * A safe default From address for this site.
	 *
	 * @return string
	 */
	private static function default_from_address() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = preg_replace( '/^www\./i', '', (string) $host );

		return 'wordpress@' . ( $host ? $host : 'localhost' );
	}

	/* ---------------------------------------------------------------------
	 * Sample rendering, for preview and test emails
	 * ------------------------------------------------------------------ */

	/**
	 * Realistic stand-in values for every tag a form offers.
	 *
	 * @param int $form_id CF7 form ID.
	 * @return array Tag name => sample value.
	 */
	public static function sample_values( $form_id ) {
		$values = array();

		foreach ( MWRIGHT_CF7_Bridge::form_tags( $form_id ) as $tag ) {
			$values[ $tag['name'] ] = self::sample_for_tag( $tag );
		}

		$form = MWRIGHT_CF7_Bridge::form( $form_id );

		$specials = array(
			'_site_title'         => get_bloginfo( 'name' ),
			'_site_description'   => get_bloginfo( 'description' ),
			'_site_url'           => home_url( '/' ),
			'_site_domain'        => wp_parse_url( home_url(), PHP_URL_HOST ),
			'_site_admin_email'   => get_option( 'admin_email' ),
			'_contact_form_title' => $form ? $form->title() : __( 'Contact Form', 'mailwright-for-contact-form-7' ),
			'_date'               => wp_date( get_option( 'date_format' ) ),
			'_time'               => wp_date( get_option( 'time_format' ) ),
			'_url'                => home_url( '/contact/' ),
			'_remote_ip'          => '203.0.113.42',
			'_user_agent'         => 'Mozilla/5.0 (Sample Browser)',
			'_post_title'         => __( 'Sample Page', 'mailwright-for-contact-form-7' ),
			'_post_url'           => home_url( '/contact/' ),
			'_post_author'        => __( 'Site Editor', 'mailwright-for-contact-form-7' ),
			'_post_author_email'  => get_option( 'admin_email' ),
			'_post_id'            => '42',
			'_post_name'          => 'sample-page',
			'_invalid_fields'     => '0',
			// Empty in a real email unless the visitor was logged in.
			'_user_login'         => 'jsmith',
			'_user_email'         => 'john@example.com',
			'_user_display_name'  => 'John Smith',
			'_user_first_name'    => 'John',
			'_user_last_name'     => 'Smith',
			'_user_nickname'      => 'jsmith',
			'_user_url'           => home_url( '/' ),
		);

		return array_merge( $specials, $values );
	}

	/**
	 * Picks a sample value based on the field type, then the field name.
	 *
	 * @param array $tag Tag detail from MWRIGHT_CF7_Bridge::form_tags().
	 * @return string
	 */
	private static function sample_for_tag( $tag ) {
		if ( ! empty( $tag['values'] ) ) {
			return (string) $tag['values'][0];
		}

		$by_type = match ( $tag['type'] ) {
			'email'                       => 'john@example.com',
			'tel'                         => '+1 555 123 456',
			'url'                         => 'https://example.com',
			'date'                        => wp_date( 'Y-m-d' ),
			'number', 'range'             => '42',
			'textarea'                    => __( 'This is a sample enquiry. Please treat it as test data.', 'mailwright-for-contact-form-7' ),
			'file'                        => 'document.pdf',
			'acceptance', 'checkbox'      => __( 'Yes', 'mailwright-for-contact-form-7' ),
			default                       => null,
		};

		if ( null !== $by_type ) {
			return $by_type;
		}

		$name = strtolower( $tag['name'] );

		return match ( true ) {
			str_contains( $name, 'name' )                                  => 'John Smith',
			str_contains( $name, 'phone' ) || str_contains( $name, 'tel' ) => '+1 555 123 456',
			str_contains( $name, 'company' )                               => 'Example Ltd',
			str_contains( $name, 'subject' )                               => __( 'Website enquiry', 'mailwright-for-contact-form-7' ),
			str_contains( $name, 'message' )                               => __( 'This is a sample enquiry.', 'mailwright-for-contact-form-7' ),
			default                                                        => __( 'Sample value', 'mailwright-for-contact-form-7' ),
		};
	}

	/**
	 * Renders a template with sample data.
	 *
	 * @param int $template_id Template ID.
	 * @param int $form_id     CF7 form ID used for the sample values.
	 * @return array|WP_Error [ subject, body, type ].
	 */
	public static function sample_render( $template_id, $form_id ) {
		$template = MWRIGHT_Template_Post_Type::get( $template_id );

		if ( ! $template ) {
			return new WP_Error( 'mwright_not_found', __( 'Template not found.', 'mailwright-for-contact-form-7' ) );
		}

		return self::sample_render_data( $template, $form_id );
	}

	/**
	 * Renders unsaved template data with sample values, so Preview reflects
	 * what is on screen rather than what was last saved.
	 *
	 * @param array $template Template data array.
	 * @param int   $form_id  CF7 form ID.
	 * @return array [ subject, body, type ].
	 */
	public static function sample_render_data( $template, $form_id ) {
		$is_html = 'html' === ( $template['type'] ?? 'html' );
		$samples = self::sample_values( $form_id );

		$search  = array();
		$replace = array();

		foreach ( $samples as $tag => $value ) {
			$search[]  = '[' . $tag . ']';
			$replace[] = $is_html ? esc_html( $value ) : $value;
		}

		$body    = str_replace( $search, $replace, self::prepare_body( $template ) );
		$subject = str_replace( $search, $replace, MWRIGHT_Branding::replace( (string) ( $template['subject'] ?? '' ), false ) );

		if ( $is_html && ! preg_match( '#<html[>\s]#i', $body ) ) {
			// Mirror what CF7's WPCF7_Mail::htmlize() would do when sending.
			$body = '<!doctype html><html><head><meta charset="utf-8"><title>'
				. esc_html( $subject ) . '</title></head><body>' . $body . '</body></html>';
		}

		return array(
			'subject' => $subject,
			'body'    => $body,
			'type'    => $is_html ? 'html' : 'text',
		);
	}

	/**
	 * Sends a test email using sample data.
	 *
	 * @param array  $template  Template data.
	 * @param int    $form_id   CF7 form ID for sample values.
	 * @param string $recipient Test recipient.
	 * @return true|WP_Error
	 */
	public static function send_test( $template, $form_id, $recipient ) {
		$recipient = sanitize_email( $recipient );

		if ( ! is_email( $recipient ) ) {
			return new WP_Error( 'mwright_bad_email', __( 'Please enter a valid email address.', 'mailwright-for-contact-form-7' ) );
		}

		// Cheap brake on using test sends as a mailer.
		if ( get_transient( 'mwright_test_throttle_' . get_current_user_id() ) ) {
			return new WP_Error(
				'mwright_throttled',
				__( 'Please wait a moment before sending another test email.', 'mailwright-for-contact-form-7' )
			);
		}

		$rendered = self::sample_render_data( $template, $form_id );
		$is_html  = 'html' === $rendered['type'];

		$headers = array( 'Content-Type: ' . ( $is_html ? 'text/html' : 'text/plain' ) . '; charset=UTF-8' );

		$subject = sprintf(
			/* translators: %s: rendered subject line */
			__( '[TEST] %s', 'mailwright-for-contact-form-7' ),
			$rendered['subject']
		);

		$sent = wp_mail( $recipient, $subject, $rendered['body'], $headers );

		set_transient( 'mwright_test_throttle_' . get_current_user_id(), 1, 60 );

		MWRIGHT_Plugin::log(
			sprintf(
				'Test email to %s — %s.',
				$recipient,
				$sent ? 'Success' : 'Failed'
			)
		);

		if ( ! $sent ) {
			return new WP_Error(
				'mwright_send_failed',
				__( 'WordPress could not send the test email. Check your site\'s email configuration or SMTP plugin.', 'mailwright-for-contact-form-7' )
			);
		}

		return true;
	}
}

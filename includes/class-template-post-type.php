<?php
/**
 * Template storage: a private custom post type plus registered meta.
 *
 * post_title   => template name
 * post_content => email body (post revisions come for free)
 * post_excerpt => description
 * post_status  => publish (active) | draft | private (inactive)
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

class MWRIGHT_Template_Post_Type {

	const POST_TYPE = 'mwright_template';

	/** Prefix every meta key shares. */
	const META_PREFIX = '_mwright_';

	/**
	 * Meta key => sanitize callback. Also drives save() and duplicate().
	 *
	 * @return array
	 */
	public static function meta_fields() {
		return array(
			'_mwright_type'          => 'sanitize_key',
			'_mwright_subject'       => 'sanitize_text_field',
			'_mwright_preview_text'  => 'sanitize_text_field',
			'_mwright_recipient'     => 'sanitize_text_field',
			'_mwright_sender'        => 'sanitize_text_field',
			'_mwright_headers'       => array( __CLASS__, 'sanitize_headers' ),
			'_mwright_attachments'   => array( __CLASS__, 'sanitize_attachments' ),
			'_mwright_exclude_blank' => 'absint',
			'_mwright_category'      => 'sanitize_text_field',
			'_mwright_form_context'  => 'absint',
		);
	}

	/**
	 * Registers the post type and its meta.
	 */
	public static function init() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Email Templates', 'mailwright-for-contact-form-7' ),
				'public'              => false,
				'show_ui'             => false, // We render our own screens.
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'revisions' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'delete_with_user'    => false,
			)
		);

		$auth = static function () {
			return current_user_can( MWRIGHT_Plugin::cap() );
		};

		$integers = array( '_mwright_exclude_blank', '_mwright_form_context' );

		foreach ( self::meta_fields() as $key => $sanitize ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'single'            => true,
					'type'              => in_array( $key, $integers, true ) ? 'integer' : 'string',
					'show_in_rest'      => false,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => $auth,
				)
			);
		}
	}

	/**
	 * Keeps only well-formed "Header: value" lines.
	 *
	 * @param string $value Raw additional headers.
	 * @return string
	 */
	public static function sanitize_headers( $value ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		$clean = array();

		foreach ( $lines as $line ) {
			$line = sanitize_text_field( $line );

			if ( '' !== trim( $line ) && preg_match( '/^[A-Za-z0-9-]+:\s*.+$/', $line ) ) {
				$clean[] = $line;
			}
		}

		return implode( "\n", $clean );
	}

	/**
	 * Keeps only mail-tag references, one per line.
	 *
	 * This value becomes Contact Form 7's `attachments` mail property, where a
	 * line reading [your-file] attaches whatever was uploaded to that field.
	 * CF7 also treats a bare line as a file path under wp-content; we do not
	 * expose that, so an administrator can never name an arbitrary path here.
	 *
	 * @param string $value Raw attachment lines.
	 * @return string
	 */
	public static function sanitize_attachments( $value ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		$clean = array();

		foreach ( $lines as $line ) {
			$line = trim( sanitize_text_field( $line ) );

			if ( ! preg_match( '/^\[([^\]]+)\]$/', $line, $matches ) ) {
				continue;
			}

			// Let Contact Form 7 decide what a valid field name looks like.
			if ( wpcf7_is_name( $matches[1] ) && ! in_array( $line, $clean, true ) ) {
				$clean[] = $line;
			}
		}

		return implode( "\n", $clean );
	}

	/**
	 * HTML that is safe to keep in an email body. Deliberately narrower than
	 * wp_kses_post (no iframes, no embeds, no scripts) but wider on the
	 * table and inline-style markup that email clients actually need.
	 *
	 * @return array
	 */
	public static function email_kses() {
		$global = array(
			// The visual builder marks each block with data-mwright-block.
			'data-*' => true,
			'style'  => true,
			'class'  => true,
			'id'     => true,
			'align'  => true,
			'dir'    => true,
			'lang'   => true,
		);

		$cell = array_merge(
			$global,
			array(
				'colspan' => true,
				'rowspan' => true,
				'width'   => true,
				'height'  => true,
				'valign'  => true,
				'bgcolor' => true,
			)
		);

		return array(
			'html'       => array( 'lang' => true, 'dir' => true, 'xmlns' => true ),
			'head'       => array(),
			'title'      => array(),
			'meta'       => array( 'charset' => true, 'name' => true, 'content' => true, 'http-equiv' => true ),
			'style'      => array( 'type' => true ),
			'body'       => $global,
			'table'      => array_merge( $cell, array( 'border' => true, 'cellpadding' => true, 'cellspacing' => true, 'role' => true ) ),
			'thead'      => $global,
			'tbody'      => $global,
			'tfoot'      => $global,
			'tr'         => $cell,
			'td'         => $cell,
			'th'         => $cell,
			'div'        => $global,
			'span'       => $global,
			'p'          => $global,
			'a'          => array_merge( $global, array( 'href' => true, 'title' => true, 'target' => true, 'rel' => true ) ),
			'img'        => array_merge( $global, array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'border' => true ) ),
			'h1'         => $global,
			'h2'         => $global,
			'h3'         => $global,
			'h4'         => $global,
			'h5'         => $global,
			'h6'         => $global,
			'ul'         => $global,
			'ol'         => $global,
			'li'         => $global,
			'strong'     => $global,
			'b'          => $global,
			'em'         => $global,
			'i'          => $global,
			'u'          => $global,
			'br'         => array(),
			'hr'         => $global,
			'blockquote' => $global,
			'small'      => $global,
			'center'     => $global,
			'pre'        => $global,
			'code'       => $global,
		);
	}

	/**
	 * Sanitizes an email body according to the template type.
	 *
	 * @param string $body Raw body.
	 * @param string $type 'html' or 'text'.
	 * @return string
	 */
	public static function sanitize_body( $body, $type = 'html' ) {
		if ( 'text' === $type ) {
			return sanitize_textarea_field( $body );
		}

		// Editors trusted with raw HTML keep their markup, as core does elsewhere.
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $body;
		}

		/*
		 * wp_kses() has no way to express a doctype, so it would silently drop
		 * one — and email clients need it. Lift it out, filter, put it back.
		 */
		$doctype = '';

		if ( preg_match( '/^\s*(<!doctype[^>]*>)/i', $body, $matches ) ) {
			// ltrim keeps this idempotent, so re-saving never grows the body.
			$doctype = $matches[1] . "\n";
			$body    = ltrim( substr( $body, strlen( $matches[0] ) ) );
		}

		return $doctype . wp_kses( $body, self::email_kses() );
	}

	/**
	 * Loads one template as a flat array.
	 *
	 * @param int $id Template post ID.
	 * @return array|null Null when the ID is not a template.
	 */
	public static function get( $id ) {
		$post = get_post( (int) $id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$data = array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'body'        => $post->post_content,
			'description' => $post->post_excerpt,
			'status'      => $post->post_status,
			'author'      => (int) $post->post_author,
			'modified'    => $post->post_modified,
		);

		foreach ( array_keys( self::meta_fields() ) as $key ) {
			$data[ substr( $key, strlen( self::META_PREFIX ) ) ] = get_post_meta( $post->ID, $key, true );
		}

		$data['type'] = 'text' === $data['type'] ? 'text' : 'html';

		return $data;
	}

	/**
	 * Creates or updates a template from untrusted input.
	 *
	 * @param array $input Raw field values.
	 * @return int|WP_Error Template ID.
	 */
	public static function save( $input ) {
		$id   = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		$type = ( isset( $input['type'] ) && 'text' === $input['type'] ) ? 'text' : 'html';
		$name = sanitize_text_field( $input['name'] ?? '' );

		if ( '' === trim( $name ) ) {
			return new WP_Error( 'mwright_no_name', __( 'Please give the template a name.', 'mailwright-for-contact-form-7' ) );
		}

		$status  = $input['status'] ?? 'publish';
		$allowed = array( 'publish', 'draft', 'private' );

		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'publish';
		}

		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => $name,
			'post_content' => self::sanitize_body( (string) ( $input['body'] ?? '' ), $type ),
			'post_excerpt' => sanitize_textarea_field( $input['description'] ?? '' ),
			'post_status'  => $status,
		);

		if ( $id && self::get( $id ) ) {
			$postarr['ID'] = $id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$id = (int) $result;

		$input['type'] = $type;

		foreach ( self::meta_fields() as $key => $sanitize ) {
			$field = substr( $key, strlen( self::META_PREFIX ) );

			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}

			update_post_meta( $id, $key, call_user_func( $sanitize, $input[ $field ] ) );
		}

		/*
		 * Derived flag, so "does this template attach files?" is a meta lookup
		 * for the list filters and the dashboard rather than a LIKE scan.
		 */
		$spec = trim( (string) get_post_meta( $id, '_mwright_attachments', true ) );

		update_post_meta( $id, '_mwright_has_files', '' === $spec ? 0 : 1 );

		return $id;
	}

	/**
	 * Copies a template, content and settings included.
	 *
	 * @param int $id Source template ID.
	 * @return int|WP_Error New template ID.
	 */
	public static function duplicate( $id ) {
		$source = self::get( $id );

		if ( ! $source ) {
			return new WP_Error( 'mwright_not_found', __( 'Template not found.', 'mailwright-for-contact-form-7' ) );
		}

		$copy = $source;

		$copy['id'] = 0;
		/* translators: %s: original template name */
		$copy['name']   = sprintf( __( '%s Copy', 'mailwright-for-contact-form-7' ), $source['name'] );
		$copy['status'] = 'draft'; // A copy should never go live by accident.

		return self::save( $copy );
	}

	/**
	 * Counts templates by status.
	 *
	 * @return array
	 */
	public static function counts() {
		$counts = (array) wp_count_posts( self::POST_TYPE );

		$out = array(
			'publish' => (int) ( $counts['publish'] ?? 0 ),
			'draft'   => (int) ( $counts['draft'] ?? 0 ),
			'private' => (int) ( $counts['private'] ?? 0 ),
		);

		$out['total'] = array_sum( $out );

		return $out;
	}

	/**
	 * Counts templates of a given type.
	 *
	 * @param string $type 'html' or 'text'.
	 * @return int
	 */
	public static function count_by_type( $type ) {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- an admin-only filter over a small, indexed set of templates.
				'meta_key'       => '_mwright_type',
				'meta_value'     => $type, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- an admin-only filter over a small, indexed set of templates.
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Counts templates that attach uploaded files.
	 *
	 * @return int
	 */
	public static function count_with_files() {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- an admin-only filter over a small, indexed set of templates.
				'meta_key'       => '_mwright_has_files',
				'meta_value'     => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- an admin-only filter over a small, indexed set of templates.
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * All templates as id => name, for select controls.
	 *
	 * @param bool $active_only Restrict to active templates.
	 * @return array
	 */
	public static function options( $active_only = false ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => $active_only ? array( 'publish' ) : array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$out = array();

		foreach ( $posts as $post ) {
			$out[ $post->ID ] = $post->post_title;
		}

		return $out;
	}

	/**
	 * Human-readable status label.
	 *
	 * @param string $status Post status.
	 * @return string
	 */
	public static function status_label( $status ) {
		return match ( $status ) {
			'publish' => __( 'Active', 'mailwright-for-contact-form-7' ),
			'draft'   => __( 'Draft', 'mailwright-for-contact-form-7' ),
			'private' => __( 'Inactive', 'mailwright-for-contact-form-7' ),
			default   => $status,
		};
	}
}

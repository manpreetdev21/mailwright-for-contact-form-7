<?php
/**
 * Global branding: one option, plus the [mwright_*] tags templates can use.
 *
 * These tags are resolved by us before Contact Form 7 ever sees the body,
 * so CF7 only has to deal with its own mail-tags.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

class MWRIGHT_Branding {

	const OPTION = 'mwright_branding';

	/**
	 * Branding defaults, seeded from the site itself so a fresh install
	 * already produces sensible emails.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'company_name'     => get_bloginfo( 'name' ),
			'logo'             => '',
			'website'          => home_url( '/' ),
			'primary_color'    => '#2271b1',
			'secondary_color'  => '#1d2327',
			'footer_text'      => sprintf(
				/* translators: %s: site name */
				__( 'You are receiving this email because you contacted %s.', 'mailwright-for-contact-form-7' ),
				get_bloginfo( 'name' )
			),
			'address'          => '',
			'social_facebook'  => '',
			'social_twitter'   => '',
			'social_linkedin'  => '',
			'social_instagram' => '',
		);
	}

	/**
	 * Current branding values.
	 *
	 * @return array
	 */
	public static function get() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * Sanitizes and stores branding values.
	 *
	 * @param array $input Raw input.
	 * @return array Stored values.
	 */
	public static function save( $input ) {
		$clean = array();

		foreach ( self::defaults() as $key => $default ) {
			$value = $input[ $key ] ?? '';

			$clean[ $key ] = match ( $key ) {
				'logo', 'website', 'social_facebook', 'social_twitter', 'social_linkedin', 'social_instagram' => esc_url_raw( $value ),
				'primary_color', 'secondary_color' => self::sanitize_color( $value, $default ),
				'footer_text', 'address' => sanitize_textarea_field( $value ),
				default => sanitize_text_field( $value ),
			};
		}

		update_option( self::OPTION, $clean );

		return $clean;
	}

	/**
	 * Accepts only a hex colour, otherwise keeps the default.
	 *
	 * @param string $value    Candidate colour.
	 * @param string $fallback Value to use when invalid.
	 * @return string
	 */
	public static function sanitize_color( $value, $fallback = '#2271b1' ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : $fallback;
	}

	/**
	 * Tag name => human label, for the editor sidebar.
	 *
	 * @return array
	 */
	public static function tags() {
		return array(
			'mwright_company_name'    => __( 'Company Name', 'mailwright-for-contact-form-7' ),
			'mwright_logo'            => __( 'Logo', 'mailwright-for-contact-form-7' ),
			'mwright_website'         => __( 'Website URL', 'mailwright-for-contact-form-7' ),
			'mwright_address'         => __( 'Company Address', 'mailwright-for-contact-form-7' ),
			'mwright_footer_text'     => __( 'Footer Text', 'mailwright-for-contact-form-7' ),
			'mwright_primary_color'   => __( 'Primary Colour', 'mailwright-for-contact-form-7' ),
			'mwright_secondary_color' => __( 'Secondary Colour', 'mailwright-for-contact-form-7' ),
			'mwright_social_links'    => __( 'Social Links', 'mailwright-for-contact-form-7' ),
			'mwright_year'            => __( 'Current Year', 'mailwright-for-contact-form-7' ),
		);
	}

	/**
	 * Replaces every [mwright_*] tag in a string.
	 *
	 * @param string $text Template text.
	 * @param bool   $html Whether the output is HTML.
	 * @return string
	 */
	public static function replace( $text, $html = true ) {
		$b = self::get();

		$logo = '';

		if ( $b['logo'] ) {
			$logo = $html
				? sprintf(
					'<img src="%s" alt="%s" style="max-width:180px;height:auto;border:0;" />',
					esc_url( $b['logo'] ),
					esc_attr( $b['company_name'] )
				)
				: $b['company_name'];
		} elseif ( $html ) {
			$logo = esc_html( $b['company_name'] );
		} else {
			$logo = $b['company_name'];
		}

		$map = array(
			'[mwright_company_name]'    => $html ? esc_html( $b['company_name'] ) : $b['company_name'],
			'[mwright_logo]'            => $logo,
			'[mwright_website]'         => esc_url( $b['website'] ),
			'[mwright_address]'         => $html ? nl2br( esc_html( $b['address'] ) ) : $b['address'],
			'[mwright_footer_text]'     => $html ? esc_html( $b['footer_text'] ) : $b['footer_text'],
			'[mwright_primary_color]'   => $b['primary_color'],
			'[mwright_secondary_color]' => $b['secondary_color'],
			'[mwright_social_links]'    => self::social_links( $b, $html ),
			'[mwright_year]'            => wp_date( 'Y' ),
		);

		$out = str_replace( array_keys( $map ), array_values( $map ), $text );

		return $html ? self::prune_empty( $out ) : $out;
	}

	/**
	 * Drops the wrappers a branding tag leaves behind when it has no value.
	 *
	 * An address or social links nobody filled in would otherwise still render
	 * as an empty paragraph — and, in a block template, as a whole padded row
	 * with its own background colour. Only the outgoing email is cleaned; the
	 * stored template keeps its structure so the editor still round-trips.
	 *
	 * @param string $html HTML body.
	 * @return string
	 */
	public static function prune_empty( $html ) {
		$patterns = array(
			// A paragraph left with nothing in it.
			'#<p\b[^>]*>\s*</p>#i',
			// A row whose every cell ended up empty. A spacer cell holding
			// &nbsp; is deliberate, so it does not match.
			'#<tr\b[^>]*>(?:\s*<t[dh]\b[^>]*>\s*</t[dh]>)+\s*</tr>#i',
		);

		do {
			$before = $html;
			$html   = preg_replace( $patterns, '', $html ) ?? $before;
		} while ( $html !== $before );

		return $html;
	}

	/**
	 * Whether a URL points somewhere only this machine or network can reach.
	 *
	 * An image served from localhost renders in the admin preview and stays
	 * blank in the email that lands in someone's inbox.
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	public static function is_private_host( $url ) {
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );

		if ( '' === $host ) {
			return false;
		}

		foreach ( array( '.local', '.test', '.localhost', '.internal' ) as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return true;
			}
		}

		if ( 'localhost' === $host ) {
			return true;
		}

		return filter_var( $host, FILTER_VALIDATE_IP )
			&& ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Builds the social links fragment.
	 *
	 * @param array $b    Branding values.
	 * @param bool  $html Whether to output HTML.
	 * @return string
	 */
	private static function social_links( $b, $html ) {
		$networks = array(
			'social_facebook'  => __( 'Facebook', 'mailwright-for-contact-form-7' ),
			'social_twitter'   => __( 'X', 'mailwright-for-contact-form-7' ),
			'social_linkedin'  => __( 'LinkedIn', 'mailwright-for-contact-form-7' ),
			'social_instagram' => __( 'Instagram', 'mailwright-for-contact-form-7' ),
		);

		$parts = array();

		foreach ( $networks as $key => $label ) {
			if ( empty( $b[ $key ] ) ) {
				continue;
			}

			$parts[] = $html
				? sprintf(
					'<a href="%s" style="color:%s;text-decoration:none;">%s</a>',
					esc_url( $b[ $key ] ),
					esc_attr( $b['primary_color'] ),
					esc_html( $label )
				)
				: $label . ': ' . $b[ $key ];
		}

		if ( ! $parts ) {
			return '';
		}

		return $html ? implode( ' &nbsp;·&nbsp; ', $parts ) : implode( "\n", $parts );
	}
}

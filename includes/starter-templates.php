<?php
/**
 * The nine starter templates seeded on first activation.
 *
 * Every HTML starter is a complete <html> document on purpose: CF7's
 * WPCF7_Mail::htmlize() only skips its own wrapper when the body already
 * matches <html>...</html>, so partial documents would end up double-wrapped.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps content in the shared, email-safe HTML shell.
 *
 * @param string $heading Panel heading.
 * @param string $inner   Inner HTML for the content cell.
 * @return string
 */
function mwright_html_shell( $heading, $inner ) {
	return '<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>' . $heading . '</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f1f1;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f1f1;padding:24px 12px;">
<tr>
<td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background-color:#ffffff;border:1px solid #e0e0e0;border-radius:6px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;color:#1d2327;">

<tr>
<td style="background-color:[mwright_primary_color];padding:24px 32px;" align="left">
[mwright_logo]
</td>
</tr>

<tr>
<td style="padding:32px;">
<h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;font-weight:600;color:[mwright_secondary_color];">' . $heading . '</h1>
' . $inner . '
</td>
</tr>

<tr>
<td style="padding:20px 32px;background-color:#fafafa;border-top:1px solid #e0e0e0;font-size:12px;line-height:1.6;color:#646970;">
<p style="margin:0 0 8px;">[mwright_footer_text]</p>
<p style="margin:0 0 8px;">[mwright_address]</p>
<p style="margin:0;">[mwright_social_links]</p>
<p style="margin:8px 0 0;">&copy; [mwright_year] [mwright_company_name] &nbsp;·&nbsp; <a href="[mwright_website]" style="color:[mwright_primary_color];text-decoration:none;">[mwright_website]</a></p>
</td>
</tr>

</table>
</td>
</tr>
</table>
</body>
</html>';
}

/**
 * Builds a two-column details table from label => tag pairs.
 *
 * @param array $rows Label => CF7 tag markup.
 * @return string
 */
function mwright_html_rows( $rows ) {
	$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.6;">';

	foreach ( $rows as $label => $tag ) {
		$html .= '
<tr>
<td width="140" valign="top" style="padding:10px 12px 10px 0;border-bottom:1px solid #f0f0f1;color:#646970;font-weight:600;">' . $label . '</td>
<td valign="top" style="padding:10px 0;border-bottom:1px solid #f0f0f1;color:#1d2327;">' . $tag . '</td>
</tr>';
	}

	return $html . '
</table>';
}

/**
 * The starter template definitions.
 *
 * @return array
 */
function mwright_starter_templates() {
	$paragraph = 'style="margin:0 0 16px;font-size:14px;line-height:1.7;"';
	$button    = static function ( $label, $href ) {
		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 16px;">
<tr><td align="center" bgcolor="[mwright_primary_color]" style="border-radius:4px;">
<a href="' . $href . '" style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;">' . $label . '</a>
</td></tr>
</table>';
	};

	return array(

		array(
			'name'         => __( 'Contact Form Notification', 'mailwright-for-contact-form-7' ),
			'type'         => 'html',
			'category'     => __( 'Admin', 'mailwright-for-contact-form-7' ),
			'subject'      => __( 'New enquiry from [your-name]', 'mailwright-for-contact-form-7' ),
			'preview_text' => __( 'A new message was submitted on your website.', 'mailwright-for-contact-form-7' ),
			'description'  => __( 'Admin notification listing every submitted field.', 'mailwright-for-contact-form-7' ),
			'headers'      => 'Reply-To: [your-email]',
			'body'         => mwright_html_shell(
				__( 'New Contact Enquiry', 'mailwright-for-contact-form-7' ),
				'<p ' . $paragraph . '>' . __( 'Someone has submitted the contact form on [mwright_company_name].', 'mailwright-for-contact-form-7' ) . '</p>'
				. mwright_html_rows(
					array(
						__( 'Name', 'mailwright-for-contact-form-7' )    => '[your-name]',
						__( 'Email', 'mailwright-for-contact-form-7' )   => '<a href="mailto:[your-email]" style="color:[mwright_primary_color];">[your-email]</a>',
						__( 'Phone', 'mailwright-for-contact-form-7' )   => '[your-phone]',
						__( 'Subject', 'mailwright-for-contact-form-7' ) => '[your-subject]',
						__( 'Message', 'mailwright-for-contact-form-7' ) => '[your-message]',
					)
				)
				. '<p style="margin:20px 0 0;font-size:12px;color:#646970;">' . __( 'Received [_date] at [_time] from [_remote_ip] on [_url]', 'mailwright-for-contact-form-7' ) . '</p>'
			),
		),

		array(
			'name'         => __( 'Customer Thank You', 'mailwright-for-contact-form-7' ),
			'type'         => 'html',
			'category'     => __( 'Customer', 'mailwright-for-contact-form-7' ),
			'recipient'    => '[your-email]',
			'subject'      => __( 'Thank you for contacting us', 'mailwright-for-contact-form-7' ),
			'preview_text' => __( 'We have received your message and will reply shortly.', 'mailwright-for-contact-form-7' ),
			'description'  => __( 'Confirmation sent to the person who filled in the form.', 'mailwright-for-contact-form-7' ),
			'body'         => mwright_html_shell(
				__( 'Thank you for getting in touch', 'mailwright-for-contact-form-7' ),
				'<p ' . $paragraph . '>' . __( 'Hello [your-name],', 'mailwright-for-contact-form-7' ) . '</p>'
				. '<p ' . $paragraph . '>' . __( 'Thank you for contacting [mwright_company_name]. We have received your message and a member of our team will reply as soon as possible.', 'mailwright-for-contact-form-7' ) . '</p>'
				. '<p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#646970;">' . __( 'Your message', 'mailwright-for-contact-form-7' ) . '</p>'
				. '<div style="padding:16px;background-color:#f6f7f7;border-left:3px solid [mwright_primary_color];font-size:14px;line-height:1.7;">[your-message]</div>'
				. '<p style="margin:16px 0 0;font-size:14px;line-height:1.7;">' . __( 'Kind regards,', 'mailwright-for-contact-form-7' ) . '<br />[mwright_company_name]</p>'
			),
		),

		array(
			'name'         => __( 'Quote Request', 'mailwright-for-contact-form-7' ),
			'type'         => 'html',
			'category'     => __( 'Admin', 'mailwright-for-contact-form-7' ),
			'subject'      => __( 'Quote request from [your-name]', 'mailwright-for-contact-form-7' ),
			'preview_text' => __( 'A new quote request is waiting for you.', 'mailwright-for-contact-form-7' ),
			'description'  => __( 'Admin notification for pricing and quote enquiries.', 'mailwright-for-contact-form-7' ),
			'headers'      => 'Reply-To: [your-email]',
			'body'         => mwright_html_shell(
				__( 'New Quote Request', 'mailwright-for-contact-form-7' ),
				'<p ' . $paragraph . '>' . __( 'A visitor has requested a quote.', 'mailwright-for-contact-form-7' ) . '</p>'
				. mwright_html_rows(
					array(
						__( 'Name', 'mailwright-for-contact-form-7' )    => '[your-name]',
						__( 'Company', 'mailwright-for-contact-form-7' ) => '[company]',
						__( 'Email', 'mailwright-for-contact-form-7' )   => '<a href="mailto:[your-email]" style="color:[mwright_primary_color];">[your-email]</a>',
						__( 'Phone', 'mailwright-for-contact-form-7' )   => '[your-phone]',
						__( 'Budget', 'mailwright-for-contact-form-7' )  => '[budget]',
						__( 'Details', 'mailwright-for-contact-form-7' ) => '[your-message]',
					)
				)
			),
		),

		array(
			'name'         => __( 'Booking Request', 'mailwright-for-contact-form-7' ),
			'type'         => 'html',
			'category'     => __( 'Admin', 'mailwright-for-contact-form-7' ),
			'subject'      => __( 'Booking request from [your-name]', 'mailwright-for-contact-form-7' ),
			'preview_text' => __( 'A new booking request has come in.', 'mailwright-for-contact-form-7' ),
			'description'  => __( 'Admin notification for appointment and booking forms.', 'mailwright-for-contact-form-7' ),
			'headers'      => 'Reply-To: [your-email]',
			'body'         => mwright_html_shell(
				__( 'New Booking Request', 'mailwright-for-contact-form-7' ),
				mwright_html_rows(
					array(
						__( 'Name', 'mailwright-for-contact-form-7' )     => '[your-name]',
						__( 'Email', 'mailwright-for-contact-form-7' )    => '<a href="mailto:[your-email]" style="color:[mwright_primary_color];">[your-email]</a>',
						__( 'Phone', 'mailwright-for-contact-form-7' )    => '[your-phone]',
						__( 'Date', 'mailwright-for-contact-form-7' )     => '[booking-date]',
						__( 'Time', 'mailwright-for-contact-form-7' )     => '[booking-time]',
						__( 'People', 'mailwright-for-contact-form-7' )   => '[guests]',
						__( 'Requests', 'mailwright-for-contact-form-7' ) => '[your-message]',
					)
				)
				. '<p style="margin:20px 0 0;font-size:12px;color:#646970;">' . __( 'Submitted [_date] at [_time].', 'mailwright-for-contact-form-7' ) . '</p>'
			),
		),

		array(
			'name'         => __( 'Support Request', 'mailwright-for-contact-form-7' ),
			'type'         => 'html',
			'category'     => __( 'Admin', 'mailwright-for-contact-form-7' ),
			'subject'      => __( 'Support request: [your-subject]', 'mailwright-for-contact-form-7' ),
			'preview_text' => __( 'A customer needs help.', 'mailwright-for-contact-form-7' ),
			'description'  => __( 'Admin notification for help desk and support forms.', 'mailwright-for-contact-form-7' ),
			'headers'      => 'Reply-To: [your-email]',
			'body'         => mwright_html_shell(
				__( 'New Support Request', 'mailwright-for-contact-form-7' ),
				mwright_html_rows(
					array(
						__( 'From', 'mailwright-for-contact-form-7' )     => '[your-name] &lt;[your-email]&gt;',
						__( 'Subject', 'mailwright-for-contact-form-7' )  => '[your-subject]',
						__( 'Priority', 'mailwright-for-contact-form-7' ) => '[priority]',
						__( 'Details', 'mailwright-for-contact-form-7' )  => '[your-message]',
					)
				)
				. $button( __( 'Reply to customer', 'mailwright-for-contact-form-7' ), 'mailto:[your-email]' )
			),
		),

		array(
			'name'         => __( 'Newsletter Signup', 'mailwright-for-contact-form-7' ),
			'type'         => 'html',
			'category'     => __( 'Customer', 'mailwright-for-contact-form-7' ),
			'recipient'    => '[your-email]',
			'subject'      => __( 'You are subscribed', 'mailwright-for-contact-form-7' ),
			'preview_text' => __( 'Welcome aboard — your subscription is confirmed.', 'mailwright-for-contact-form-7' ),
			'description'  => __( 'Confirmation for newsletter and mailing list signups.', 'mailwright-for-contact-form-7' ),
			'body'         => mwright_html_shell(
				__( 'Welcome aboard', 'mailwright-for-contact-form-7' ),
				'<p ' . $paragraph . '>' . __( 'Hello [your-name],', 'mailwright-for-contact-form-7' ) . '</p>'
				. '<p ' . $paragraph . '>' . __( 'Thanks for subscribing to updates from [mwright_company_name]. We will send you news now and then — never spam.', 'mailwright-for-contact-form-7' ) . '</p>'
				. $button( __( 'Visit our website', 'mailwright-for-contact-form-7' ), '[mwright_website]' )
			),
		),

		array(
			'name'         => __( 'File Upload Notification', 'mailwright-for-contact-form-7' ),
			'type'         => 'html',
			'category'     => __( 'Admin', 'mailwright-for-contact-form-7' ),
			'subject'      => __( 'New file from [your-name]', 'mailwright-for-contact-form-7' ),
			'preview_text' => __( 'A new submission arrived with an attachment.', 'mailwright-for-contact-form-7' ),
			'description'  => __( 'Admin notification for forms with an upload field. Change [your-file] to match your own field name.', 'mailwright-for-contact-form-7' ),
			'headers'      => 'Reply-To: [your-email]',
			'attachments'  => '[your-file]',
			'body'         => mwright_html_shell(
				__( 'New File Submission', 'mailwright-for-contact-form-7' ),
				'<p ' . $paragraph . '>' . __( 'Someone submitted a file through [mwright_company_name].', 'mailwright-for-contact-form-7' ) . '</p>'
				. mwright_html_rows(
					array(
						__( 'Name', 'mailwright-for-contact-form-7' )          => '[your-name]',
						__( 'Email', 'mailwright-for-contact-form-7' )         => '<a href="mailto:[your-email]" style="color:[mwright_primary_color];">[your-email]</a>',
						__( 'Message', 'mailwright-for-contact-form-7' )       => '[your-message]',
						__( 'Uploaded file', 'mailwright-for-contact-form-7' ) => '[your-file]',
					)
				)
				. '<p style="margin:20px 0 0;font-size:12px;color:#646970;">' . __( 'The file is attached to this email. If nothing is attached, the visitor did not upload one.', 'mailwright-for-contact-form-7' ) . '</p>'
			),
		),

		array(
			'name'         => __( 'Simple Notification', 'mailwright-for-contact-form-7' ),
			'type'         => 'text',
			'category'     => __( 'Admin', 'mailwright-for-contact-form-7' ),
			'subject'      => __( 'New enquiry from [your-name]', 'mailwright-for-contact-form-7' ),
			'description'  => __( 'Plain-text admin notification. Works in every mail client.', 'mailwright-for-contact-form-7' ),
			'headers'      => 'Reply-To: [your-email]',
			'body'         => __(
				'New Contact Enquiry
==================

Name:  [your-name]
Email: [your-email]
Phone: [your-phone]

Message:
[your-message]

--
Sent from [_site_title] ([_site_url])
Received [_date] at [_time] from [_remote_ip]',
				'mailwright-for-contact-form-7'
			),
		),

		array(
			'name'        => __( 'Blank Template', 'mailwright-for-contact-form-7' ),
			'type'        => 'html',
			'category'    => __( 'Starter', 'mailwright-for-contact-form-7' ),
			'status'      => 'draft',
			'subject'     => '',
			'description' => __( 'An empty branded shell to build your own layout in.', 'mailwright-for-contact-form-7' ),
			'body'        => mwright_html_shell(
				__( 'Heading', 'mailwright-for-contact-form-7' ),
				'<p style="margin:0 0 16px;font-size:14px;line-height:1.7;">' . __( 'Write your email here, and insert form tags from the sidebar.', 'mailwright-for-contact-form-7' ) . '</p>'
			),
		),
	);
}

/**
 * Inserts any starter templates that are not already on the site.
 *
 * Safe to run again: a starter whose name is already taken is left alone, so
 * the Tools button tops up missing demos without ever duplicating one.
 *
 * @return int[] IDs of the templates created.
 */
function mwright_install_starter_templates() {
	$created = array();

	foreach ( mwright_starter_templates() as $template ) {
		if ( mwright_template_name_taken( $template['name'] ) ) {
			continue;
		}

		$id = MWRIGHT_Template_Post_Type::save(
			wp_parse_args(
				$template,
				array(
					'status'        => 'publish',
					'recipient'     => '',
					'sender'        => '',
					'headers'       => '',
					'preview_text'  => '',
					'exclude_blank' => 1,
				)
			)
		);

		if ( ! is_wp_error( $id ) ) {
			$created[] = $id;
		}
	}

	return $created;
}

/**
 * Whether a live template already uses this name. Trashed ones do not count,
 * so a demo someone binned can be brought back.
 *
 * @param string $name Template name.
 * @return bool
 */
function mwright_template_name_taken( $name ) {
	return (bool) get_posts(
		array(
			'post_type'      => MWRIGHT_Template_Post_Type::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'title'          => $name,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
}

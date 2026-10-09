<?php
/**
 * Settings screen.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

$mwright_settings = MWRIGHT_Plugin::settings();
?>
<div class="wrap mwright">

	<?php
	MWRIGHT_Admin::header( __( 'Settings', 'mailwright-for-contact-form-7' ) );
	MWRIGHT_Admin::flash();
	?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="mwright_save_settings" />
		<?php wp_nonce_field( 'mwright_save_settings' ); ?>

		<div class="mwright-columns">

			<div class="mwright-card">
				<div class="mwright-card__head"><h2><?php esc_html_e( 'General', 'mailwright-for-contact-form-7' ); ?></h2></div>

				<p class="mwright-field">
					<label for="mwright-default-type"><?php esc_html_e( 'Default Email Format', 'mailwright-for-contact-form-7' ); ?></label>
					<select id="mwright-default-type" name="settings[default_type]">
						<option value="html" <?php selected( $mwright_settings['default_type'], 'html' ); ?>><?php esc_html_e( 'HTML', 'mailwright-for-contact-form-7' ); ?></option>
						<option value="text" <?php selected( $mwright_settings['default_type'], 'text' ); ?>><?php esc_html_e( 'Plain Text', 'mailwright-for-contact-form-7' ); ?></option>
					</select>
					<span class="mwright-help"><?php esc_html_e( 'Used when you create a new template.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>

				<p class="mwright-field">
					<label for="mwright-default-sender"><?php esc_html_e( 'Default Sender', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="text" id="mwright-default-sender" name="settings[default_sender]"
						value="<?php echo esc_attr( $mwright_settings['default_sender'] ); ?>"
						placeholder="<?php echo esc_attr( sprintf( '%s <%s>', get_bloginfo( 'name' ), get_option( 'admin_email' ) ) ); ?>" />
					<span class="mwright-help"><?php esc_html_e( 'Used when neither the template nor the form sets a From address.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>
			</div>

			<div class="mwright-card">
				<div class="mwright-card__head"><h2><?php esc_html_e( 'Email', 'mailwright-for-contact-form-7' ); ?></h2></div>

				<p class="mwright-field">
					<label for="mwright-test-recipient"><?php esc_html_e( 'Test Email Recipient', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="email" id="mwright-test-recipient" name="settings[test_recipient]"
						value="<?php echo esc_attr( $mwright_settings['test_recipient'] ); ?>"
						placeholder="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" />
					<span class="mwright-help"><?php esc_html_e( 'Where test emails go by default. Leave empty to use your own address.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>

				<p class="mwright-help">
					<?php esc_html_e( 'Templates are sent by Contact Form 7 using WordPress mail, so any SMTP plugin you already use keeps working. This plugin never stores or displays mail server credentials.', 'mailwright-for-contact-form-7' ); ?>
				</p>
			</div>

			<div class="mwright-card">
				<div class="mwright-card__head"><h2><?php esc_html_e( 'Files', 'mailwright-for-contact-form-7' ); ?></h2></div>

				<p class="mwright-help">
					<?php esc_html_e( 'Attachments are chosen per template, in the template editor. List one file tag per line and Contact Form 7 attaches whatever the visitor uploaded to that field.', 'mailwright-for-contact-form-7' ); ?>
				</p>

				<p class="mwright-help">
					<?php esc_html_e( 'File size limits, allowed file types and validation stay with Contact Form 7 and WordPress. There is nothing to configure here, and nothing here can loosen those limits.', 'mailwright-for-contact-form-7' ); ?>
				</p>

				<p class="mwright-help">
					<?php esc_html_e( 'Contact Form 7 removes an uploaded file shortly after sending, so each submission keeps its own copy under Submissions. Those copies are closed to the web and only reachable through a download link in the admin. A file path or download link is never put in an email.', 'mailwright-for-contact-form-7' ); ?>
				</p>
			</div>

			<div class="mwright-card">
				<div class="mwright-card__head"><h2><?php esc_html_e( 'Advanced', 'mailwright-for-contact-form-7' ); ?></h2></div>

				<p class="mwright-field mwright-field--check">
					<label for="mwright-debug">
						<input type="checkbox" id="mwright-debug" name="settings[debug]" value="1" <?php checked( (int) $mwright_settings['debug'], 1 ); ?> />
						<?php esc_html_e( 'Enable debug logging', 'mailwright-for-contact-form-7' ); ?>
					</label>
					<span class="mwright-help"><?php esc_html_e( 'Records which template was applied to which form, and whether sending succeeded. Email content is never logged.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>

				<p class="mwright-field mwright-field--check">
					<label for="mwright-delete-data">
						<input type="checkbox" id="mwright-delete-data" name="settings[delete_on_uninstall]" value="1"
							<?php checked( (int) $mwright_settings['delete_on_uninstall'], 1 ); ?> />
						<?php esc_html_e( 'Delete all plugin data when the plugin is uninstalled', 'mailwright-for-contact-form-7' ); ?>
					</label>
					<span class="mwright-help"><?php esc_html_e( 'Off by default. Your templates survive deactivating or deleting the plugin unless you turn this on.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>
			</div>

		</div>

		<p class="mwright-form-actions">
			<button type="submit" class="mwright-btn mwright-btn--primary"><?php esc_html_e( 'Save Settings', 'mailwright-for-contact-form-7' ); ?></button>
		</p>
	</form>
</div>

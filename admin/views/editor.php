<?php
/**
 * Template editor screen.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which template to edit.
$mwright_template_id = isset( $_GET['template'] ) ? absint( $_GET['template'] ) : 0;

$mwright_template = $mwright_template_id ? MWRIGHT_Template_Post_Type::get( $mwright_template_id ) : null;

if ( ! $mwright_template ) {
	$mwright_template = array(
		'id'            => 0,
		'name'          => '',
		'body'          => '',
		'description'   => '',
		'status'        => 'publish',
		'type'          => MWRIGHT_Plugin::setting( 'default_type', 'html' ),
		'subject'       => '',
		'preview_text'  => '',
		'recipient'     => '',
		'sender'        => '',
		'headers'       => '',
		'attachments'   => '',
		'exclude_blank' => 1,
		'category'      => '',
		'form_context'  => 0,
	);
}

$mwright_forms = MWRIGHT_CF7_Bridge::forms();

// Pick the most useful form to validate against: the stored one, then a form
// this template is already assigned to, then the first available form.
$mwright_form_context = (int) $mwright_template['form_context'];

if ( ! $mwright_form_context || ! isset( $mwright_forms[ $mwright_form_context ] ) ) {
	$mwright_using        = $mwright_template['id'] ? MWRIGHT_CF7_Bridge::forms_using( $mwright_template['id'] ) : array();
	$mwright_form_context = $mwright_using ? (int) $mwright_using[0] : (int) ( array_key_first( $mwright_forms ) ?? 0 );
}

$mwright_assigned_to = $mwright_template['id'] ? MWRIGHT_CF7_Bridge::forms_using( $mwright_template['id'] ) : array();

$mwright_status_modifier = match ( $mwright_template['status'] ) {
	'publish' => 'success',
	'private' => 'neutral',
	default   => 'warning',
};
?>
<div class="wrap mwright mwright-editor"
	data-template-id="<?php echo esc_attr( (string) $mwright_template['id'] ); ?>"
	data-form-id="<?php echo esc_attr( (string) $mwright_form_context ); ?>">

	<?php MWRIGHT_Admin::flash(); ?>

	<div class="mwright-editor__bar">
		<div class="mwright-editor__identity">
			<a class="mwright-editor__back" href="<?php echo esc_url( MWRIGHT_Plugin::url( 'templates' ) ); ?>"
				aria-label="<?php esc_attr_e( 'Back to templates', 'mailwright-for-contact-form-7' ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
			</a>
			<div>
				<label class="screen-reader-text" for="mwright-name"><?php esc_html_e( 'Template name', 'mailwright-for-contact-form-7' ); ?></label>
				<input type="text" id="mwright-name" class="mwright-editor__name" data-field="name"
					value="<?php echo esc_attr( $mwright_template['name'] ); ?>"
					placeholder="<?php esc_attr_e( 'Untitled template', 'mailwright-for-contact-form-7' ); ?>" />
				<div class="mwright-editor__meta">
					<span class="mwright-badge mwright-badge--<?php echo esc_attr( $mwright_status_modifier ); ?>" data-status-badge>
						<?php echo esc_html( MWRIGHT_Template_Post_Type::status_label( $mwright_template['status'] ) ); ?>
					</span>
					<span class="mwright-muted" data-dirty-flag hidden><?php esc_html_e( 'Unsaved changes', 'mailwright-for-contact-form-7' ); ?></span>
				</div>
			</div>
		</div>

		<div class="mwright-editor__actions">
			<button type="button" class="mwright-btn" data-action="preview">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'Preview', 'mailwright-for-contact-form-7' ); ?>
			</button>
			<button type="button" class="mwright-btn" data-action="send-test">
				<span class="dashicons dashicons-email" aria-hidden="true"></span>
				<?php esc_html_e( 'Send Test', 'mailwright-for-contact-form-7' ); ?>
			</button>
			<button type="button" class="mwright-btn mwright-btn--primary" data-action="save">
				<?php esc_html_e( 'Save', 'mailwright-for-contact-form-7' ); ?>
			</button>
		</div>
	</div>

	<div class="mwright-editor__layout">

		<!-- LEFT: available tags -->
		<aside class="mwright-panel mwright-tags" aria-label="<?php esc_attr_e( 'Available tags', 'mailwright-for-contact-form-7' ); ?>">
			<div class="mwright-panel__head">
				<h2><?php esc_html_e( 'Available Tags', 'mailwright-for-contact-form-7' ); ?></h2>
			</div>

			<div class="mwright-panel__body">
				<p class="mwright-field">
					<label for="mwright-form-context"><?php esc_html_e( 'Detect tags from', 'mailwright-for-contact-form-7' ); ?></label>
					<select id="mwright-form-context" data-field="form_context">
						<option value="0"><?php esc_html_e( '— Select a contact form —', 'mailwright-for-contact-form-7' ); ?></option>
						<?php foreach ( $mwright_forms as $id => $title ) : ?>
							<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $mwright_form_context, $id ); ?>>
								<?php echo esc_html( $title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="mwright-help"><?php esc_html_e( 'Used to detect tags and to check the template. It does not assign the template.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>

				<p class="mwright-field">
					<label class="screen-reader-text" for="mwright-tag-search"><?php esc_html_e( 'Search tags', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="search" id="mwright-tag-search" placeholder="<?php esc_attr_e( 'Search tags…', 'mailwright-for-contact-form-7' ); ?>" />
				</p>

				<div class="mwright-tags__recent" data-recent-tags hidden>
					<h3><?php esc_html_e( 'Recently used', 'mailwright-for-contact-form-7' ); ?></h3>
					<div class="mwright-tags__list" data-recent-list></div>
				</div>

				<div class="mwright-tags__group">
					<h3><?php esc_html_e( 'Form Fields', 'mailwright-for-contact-form-7' ); ?></h3>
					<div class="mwright-tags__list" data-form-tags>
						<p class="mwright-muted"><?php esc_html_e( 'Select a contact form to see its fields.', 'mailwright-for-contact-form-7' ); ?></p>
					</div>
				</div>

				<div class="mwright-tags__group" data-file-group hidden>
					<h3><?php esc_html_e( 'File Uploads', 'mailwright-for-contact-form-7' ); ?></h3>
					<div class="mwright-tags__list" data-file-tags></div>
					<p class="mwright-help"><?php esc_html_e( 'Contact Form 7 replaces a file tag with the uploaded file name.', 'mailwright-for-contact-form-7' ); ?></p>
				</div>

				<div class="mwright-tags__group">
					<h3><?php esc_html_e( 'System Tags', 'mailwright-for-contact-form-7' ); ?></h3>
					<div class="mwright-tags__list">
						<?php foreach ( MWRIGHT_CF7_Bridge::special_tags() as $mwright_tag => $mwright_label ) : ?>
							<?php require MWRIGHT_DIR . 'admin/views/partial-tag.php'; ?>
						<?php endforeach; ?>
					</div>
					<p class="mwright-help"><?php esc_html_e( 'Tags starting [_user_ are filled in only when the visitor is logged in; otherwise they come through empty.', 'mailwright-for-contact-form-7' ); ?></p>
				</div>

				<div class="mwright-tags__group">
					<h3><?php esc_html_e( 'Branding', 'mailwright-for-contact-form-7' ); ?></h3>
					<div class="mwright-tags__list">
						<?php foreach ( MWRIGHT_Branding::tags() as $mwright_tag => $mwright_label ) : ?>
							<?php require MWRIGHT_DIR . 'admin/views/partial-tag.php'; ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</aside>

		<!-- CENTRE: the email itself -->
		<main class="mwright-panel mwright-compose">
			<div class="mwright-panel__body">

				<div class="mwright-alert mwright-alert--warning" data-unknown-tags hidden>
					<p>
						<strong><?php esc_html_e( 'Warning', 'mailwright-for-contact-form-7' ); ?></strong>
						<?php esc_html_e( 'This template contains tags that are not available in the selected Contact Form 7 form.', 'mailwright-for-contact-form-7' ); ?>
					</p>
					<div class="mwright-tags__list" data-unknown-list></div>
					<p class="mwright-help"><?php esc_html_e( 'Nothing is removed automatically. Keep a tag if you plan to add the field, or remove it from the template.', 'mailwright-for-contact-form-7' ); ?></p>
				</div>

				<div class="mwright-alert mwright-alert--info" data-new-tags hidden>
					<p data-new-tags-message></p>
					<div class="mwright-tags__list" data-new-tags-list></div>
				</div>

				<p class="mwright-field">
					<label for="mwright-subject"><?php esc_html_e( 'Subject', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="text" id="mwright-subject" data-field="subject" data-insertable="1"
						value="<?php echo esc_attr( $mwright_template['subject'] ); ?>"
						placeholder="<?php esc_attr_e( 'New enquiry from [your-name]', 'mailwright-for-contact-form-7' ); ?>" />
				</p>

				<p class="mwright-field">
					<label for="mwright-preview-text"><?php esc_html_e( 'Preview Text', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="text" id="mwright-preview-text" data-field="preview_text" data-insertable="1"
						value="<?php echo esc_attr( $mwright_template['preview_text'] ); ?>" />
					<span class="mwright-help"><?php esc_html_e( 'The short line inboxes show next to the subject. Optional.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>

				<div class="mwright-modes" data-modes>
					<button type="button" class="mwright-mode is-active" data-mode="visual" aria-pressed="true">
						<span class="dashicons dashicons-layout" aria-hidden="true"></span>
						<?php esc_html_e( 'Visual Builder', 'mailwright-for-contact-form-7' ); ?>
					</button>
					<button type="button" class="mwright-mode" data-mode="html" aria-pressed="false">
						<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
						<?php esc_html_e( 'HTML', 'mailwright-for-contact-form-7' ); ?>
					</button>
				</div>

				<div class="mwright-builder" data-builder hidden>
					<div class="mwright-builder__palette" data-block-palette>
						<?php foreach ( MWRIGHT_Admin::block_types() as $mwright_block_type => $mwright_block ) : ?>
							<button type="button" class="mwright-blockbtn" data-add-block="<?php echo esc_attr( $mwright_block_type ); ?>">
								<span class="dashicons dashicons-<?php echo esc_attr( $mwright_block['icon'] ); ?>" aria-hidden="true"></span>
								<?php echo esc_html( $mwright_block['label'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>

					<div class="mwright-builder__main">
						<div class="mwright-alert mwright-alert--info" data-blocks-import hidden>
							<p><?php esc_html_e( 'This template is hand-written HTML. Converting rebuilds it as blocks from the content it can recognise — check the preview afterwards, and nothing is saved until you press Save.', 'mailwright-for-contact-form-7' ); ?></p>
							<button type="button" class="mwright-btn mwright-btn--small" data-action="convert-blocks">
								<?php esc_html_e( 'Convert to blocks', 'mailwright-for-contact-form-7' ); ?>
							</button>
						</div>

						<div class="mwright-builder__canvas" data-blocks-canvas></div>

						<p class="mwright-builder__empty" data-blocks-empty>
							<?php esc_html_e( 'Drag a block from the left, or click one to add it.', 'mailwright-for-contact-form-7' ); ?>
						</p>
					</div>
				</div>

				<div class="mwright-field mwright-field--grow" data-html-editor hidden>
					<label for="mwright-body"><?php esc_html_e( 'Email Body', 'mailwright-for-contact-form-7' ); ?></label>
					<textarea id="mwright-body" data-field="body" data-insertable="1" rows="24"
						spellcheck="false"><?php echo esc_textarea( $mwright_template['body'] ); ?></textarea>
					<span class="mwright-help"><?php esc_html_e( 'Click any tag on the left to insert it where your cursor is.', 'mailwright-for-contact-form-7' ); ?></span>
				</div>

			</div>
		</main>

		<!-- RIGHT: settings -->
		<aside class="mwright-panel mwright-settings-panel" aria-label="<?php esc_attr_e( 'Template settings', 'mailwright-for-contact-form-7' ); ?>">
			<div class="mwright-panel__head">
				<h2><?php esc_html_e( 'Settings', 'mailwright-for-contact-form-7' ); ?></h2>
			</div>

			<div class="mwright-panel__body">
				<p class="mwright-field">
					<label for="mwright-type"><?php esc_html_e( 'Template Type', 'mailwright-for-contact-form-7' ); ?></label>
					<select id="mwright-type" data-field="type">
						<option value="html" <?php selected( $mwright_template['type'], 'html' ); ?>><?php esc_html_e( 'HTML', 'mailwright-for-contact-form-7' ); ?></option>
						<option value="text" <?php selected( $mwright_template['type'], 'text' ); ?>><?php esc_html_e( 'Plain Text', 'mailwright-for-contact-form-7' ); ?></option>
					</select>
				</p>

				<p class="mwright-field">
					<label for="mwright-status"><?php esc_html_e( 'Status', 'mailwright-for-contact-form-7' ); ?></label>
					<select id="mwright-status" data-field="status">
						<option value="publish" <?php selected( $mwright_template['status'], 'publish' ); ?>><?php esc_html_e( 'Active', 'mailwright-for-contact-form-7' ); ?></option>
						<option value="draft" <?php selected( $mwright_template['status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'mailwright-for-contact-form-7' ); ?></option>
						<option value="private" <?php selected( $mwright_template['status'], 'private' ); ?>><?php esc_html_e( 'Inactive', 'mailwright-for-contact-form-7' ); ?></option>
					</select>
					<span class="mwright-help"><?php esc_html_e( 'Only active templates are used when a form is submitted.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>

				<p class="mwright-field">
					<label for="mwright-category"><?php esc_html_e( 'Category', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="text" id="mwright-category" data-field="category"
						value="<?php echo esc_attr( $mwright_template['category'] ); ?>"
						placeholder="<?php esc_attr_e( 'Admin, Customer…', 'mailwright-for-contact-form-7' ); ?>" />
				</p>

				<hr class="mwright-rule" />

				<p class="mwright-field">
					<label for="mwright-recipient"><?php esc_html_e( 'To', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="text" id="mwright-recipient" data-field="recipient" data-insertable="1"
						value="<?php echo esc_attr( $mwright_template['recipient'] ); ?>"
						placeholder="<?php esc_attr_e( 'Leave empty to keep the form’s own recipient', 'mailwright-for-contact-form-7' ); ?>" />
				</p>

				<p class="mwright-field">
					<label for="mwright-sender"><?php esc_html_e( 'From', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="text" id="mwright-sender" data-field="sender" data-insertable="1"
						value="<?php echo esc_attr( $mwright_template['sender'] ); ?>"
						placeholder="<?php esc_attr_e( 'Leave empty to keep the form’s own sender', 'mailwright-for-contact-form-7' ); ?>" />
				</p>

				<p class="mwright-field">
					<label for="mwright-headers"><?php esc_html_e( 'Additional Headers', 'mailwright-for-contact-form-7' ); ?></label>
					<textarea id="mwright-headers" data-field="headers" rows="3" data-insertable="1"
						placeholder="Reply-To: [your-email]"><?php echo esc_textarea( $mwright_template['headers'] ); ?></textarea>
					<span class="mwright-help"><?php esc_html_e( 'One header per line, for example Reply-To or Cc.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>

				<p class="mwright-field mwright-field--check">
					<label for="mwright-exclude-blank">
						<input type="checkbox" id="mwright-exclude-blank" data-field="exclude_blank"
							<?php checked( (int) $mwright_template['exclude_blank'], 1 ); ?> />
						<?php esc_html_e( 'Hide empty fields', 'mailwright-for-contact-form-7' ); ?>
					</label>
					<span class="mwright-help"><?php esc_html_e( 'Removes lines whose tags came back empty.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>

				<p class="mwright-field">
					<label for="mwright-attachments"><?php esc_html_e( 'Attachments', 'mailwright-for-contact-form-7' ); ?></label>
					<textarea id="mwright-attachments" data-field="attachments" rows="3" data-insertable="1"
						placeholder="[your-resume]"><?php echo esc_textarea( $mwright_template['attachments'] ); ?></textarea>
					<span class="mwright-help"><?php esc_html_e( 'One file tag per line. Whatever the visitor uploaded to that field is attached to this email. Leave empty to keep the form’s own attachment settings.', 'mailwright-for-contact-form-7' ); ?></span>
					<span class="mwright-alert mwright-alert--warning" data-attachment-warning hidden></span>
				</p>

				<hr class="mwright-rule" />

				<p class="mwright-field">
					<label for="mwright-description"><?php esc_html_e( 'Description', 'mailwright-for-contact-form-7' ); ?></label>
					<textarea id="mwright-description" data-field="description" rows="3"><?php echo esc_textarea( $mwright_template['description'] ); ?></textarea>
				</p>

				<div class="mwright-field">
					<span class="mwright-field__label"><?php esc_html_e( 'Assigned Forms', 'mailwright-for-contact-form-7' ); ?></span>
					<?php if ( $mwright_assigned_to ) : ?>
						<ul class="mwright-list-plain">
							<?php foreach ( $mwright_assigned_to as $mwright_form_id ) : ?>
								<li><?php echo esc_html( $mwright_forms[ $mwright_form_id ] ?? sprintf( '#%d', $mwright_form_id ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="mwright-muted"><?php esc_html_e( 'Not assigned to any form yet.', 'mailwright-for-contact-form-7' ); ?></p>
					<?php endif; ?>
					<a class="mwright-btn mwright-btn--small" href="<?php echo esc_url( MWRIGHT_Plugin::url( 'assignments' ) ); ?>">
						<?php esc_html_e( 'Manage assignments', 'mailwright-for-contact-form-7' ); ?>
					</a>
				</div>
			</div>
		</aside>

	</div>
</div>

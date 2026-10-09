<?php
/**
 * Assignments screen: which template each contact form uses.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

$mwright_forms       = MWRIGHT_CF7_Bridge::forms();
$mwright_assignments = MWRIGHT_CF7_Bridge::assignments();
$mwright_options     = MWRIGHT_Template_Post_Type::options( true );

// An assigned template that was later deactivated must still appear here, or
// the row shows "No template" while the form is still marked as managed.
foreach ( MWRIGHT_CF7_Bridge::assigned_template_ids() as $mwright_assigned_id ) {
	if ( isset( $mwright_options[ $mwright_assigned_id ] ) ) {
		continue;
	}

	$mwright_stale = MWRIGHT_Template_Post_Type::get( $mwright_assigned_id );

	if ( $mwright_stale ) {
		$mwright_options[ $mwright_assigned_id ] = sprintf(
			/* translators: 1: template name, 2: status label, e.g. Inactive */
			__( '%1$s (%2$s)', 'mailwright-for-contact-form-7' ),
			$mwright_stale['name'],
			MWRIGHT_Template_Post_Type::status_label( $mwright_stale['status'] )
		);
	}
}

// The templates list links here with ?template=N to assign that template.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preselection.
$mwright_preselect = isset( $_GET['template'] ) ? absint( $_GET['template'] ) : 0;

if ( $mwright_preselect && ! isset( $mwright_options[ $mwright_preselect ] ) ) {
	$mwright_preselect = 0;
}

$mwright_slots = array(
	'admin'    => __( 'Admin Email Template', 'mailwright-for-contact-form-7' ),
	'customer' => __( 'Customer Email Template', 'mailwright-for-contact-form-7' ),
);
?>
<div class="wrap mwright mwright-assignments">

	<?php
	MWRIGHT_Admin::header( __( 'Assignments', 'mailwright-for-contact-form-7' ) );
	MWRIGHT_Admin::flash();
	?>

	<div class="mwright-alert mwright-alert--info">
		<?php esc_html_e( 'Assigning a template does not change your Contact Form 7 mail settings. They stay exactly as they are and take over again the moment you detach.', 'mailwright-for-contact-form-7' ); ?>
	</div>

	<?php if ( $mwright_preselect ) : ?>
		<div class="mwright-alert mwright-alert--info">
			<?php
			printf(
				/* translators: %s: template name */
				esc_html__( '%s is pre-selected below. Pick the form and email it should handle, then press Apply Template.', 'mailwright-for-contact-form-7' ),
				'<strong>' . esc_html( $mwright_options[ $mwright_preselect ] ) . '</strong>'
			);
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! $mwright_forms ) : ?>

		<div class="mwright-empty">
			<span class="dashicons dashicons-feedback" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'No contact forms found.', 'mailwright-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'Create a form in Contact Form 7 first, then come back to assign a template to it.', 'mailwright-for-contact-form-7' ); ?></p>
			<a class="mwright-btn mwright-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpcf7-new' ) ); ?>">
				<?php esc_html_e( 'Create a contact form', 'mailwright-for-contact-form-7' ); ?>
			</a>
		</div>

	<?php elseif ( ! $mwright_options ) : ?>

		<div class="mwright-empty">
			<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'No active templates.', 'mailwright-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'Only active templates can be assigned to a form. Create one, or set an existing template to Active.', 'mailwright-for-contact-form-7' ); ?></p>
			<a class="mwright-btn mwright-btn--primary" href="<?php echo esc_url( MWRIGHT_Plugin::url( 'template-edit' ) ); ?>">
				<?php esc_html_e( 'Create Template', 'mailwright-for-contact-form-7' ); ?>
			</a>
		</div>

	<?php else : ?>

		<div class="mwright-card mwright-card--flush">
			<table class="mwright-table mwright-table--assignments">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Contact Form', 'mailwright-for-contact-form-7' ); ?></th>
						<?php foreach ( $mwright_slots as $mwright_label ) : ?>
							<th scope="col"><?php echo esc_html( $mwright_label ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $mwright_forms as $mwright_form_id => $title ) : ?>
						<?php $mwright_current = $mwright_assignments[ $mwright_form_id ] ?? array(); ?>
						<tr data-form-id="<?php echo esc_attr( (string) $mwright_form_id ); ?>">
							<td data-label="<?php esc_attr_e( 'Contact Form', 'mailwright-for-contact-form-7' ); ?>">
								<strong><?php echo esc_html( $title ); ?></strong>
								<?php if ( $mwright_current ) : ?>
									<span class="mwright-badge mwright-badge--success"><?php esc_html_e( 'Managed', 'mailwright-for-contact-form-7' ); ?></span>
								<?php endif; ?>
								<div class="mwright-muted">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcf7&post=' . $mwright_form_id . '&action=edit' ) ); ?>">
										<?php esc_html_e( 'Edit in Contact Form 7', 'mailwright-for-contact-form-7' ); ?>
									</a>
								</div>
							</td>

							<?php foreach ( $mwright_slots as $mwright_slot => $mwright_label ) : ?>
								<?php $mwright_assigned = (int) ( $mwright_current[ $mwright_slot ] ?? 0 ); ?>
								<td data-label="<?php echo esc_attr( $mwright_label ); ?>">
									<div class="mwright-assign" data-slot="<?php echo esc_attr( $mwright_slot ); ?>">
										<label class="screen-reader-text" for="mwright-select-<?php echo esc_attr( $mwright_form_id . '-' . $mwright_slot ); ?>">
											<?php
											printf(
												/* translators: 1: slot label, 2: form title */
												esc_html__( '%1$s for %2$s', 'mailwright-for-contact-form-7' ),
												esc_html( $mwright_label ),
												esc_html( $title )
											);
											?>
										</label>
										<select id="mwright-select-<?php echo esc_attr( $mwright_form_id . '-' . $mwright_slot ); ?>" data-template-select>
											<option value="0"><?php esc_html_e( '— No template —', 'mailwright-for-contact-form-7' ); ?></option>
											<?php
											// An empty admin slot takes the preselection from ?template=N.
											$mwright_chosen = $mwright_assigned;

											if ( ! $mwright_chosen && $mwright_preselect && 'admin' === $mwright_slot ) {
												$mwright_chosen = $mwright_preselect;
											}

											foreach ( $mwright_options as $id => $mwright_name ) :
												?>
												<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $mwright_chosen, $id ); ?>>
													<?php echo esc_html( $mwright_name ); ?>
												</option>
											<?php endforeach; ?>
										</select>

										<div class="mwright-assign__actions">
											<button type="button" class="mwright-btn mwright-btn--small mwright-btn--primary" data-action="apply">
												<?php esc_html_e( 'Apply Template', 'mailwright-for-contact-form-7' ); ?>
											</button>
											<button type="button" class="mwright-btn mwright-btn--small" data-action="detach" <?php disabled( ! $mwright_assigned ); ?>>
												<?php esc_html_e( 'Detach', 'mailwright-for-contact-form-7' ); ?>
											</button>
										</div>

										<?php if ( $mwright_assigned ) : ?>
											<p class="mwright-help">
												<a href="<?php echo esc_url( MWRIGHT_Plugin::url( 'template-edit', array( 'template' => $mwright_assigned ) ) ); ?>">
													<?php esc_html_e( 'Edit template', 'mailwright-for-contact-form-7' ); ?>
												</a>
											</p>
											<?php
											$mwright_assigned_template = MWRIGHT_Template_Post_Type::get( $mwright_assigned );

											// Only active templates take over a live form, so an
											// inactive one here means Contact Form 7 is still sending.
											if ( $mwright_assigned_template && 'publish' !== $mwright_assigned_template['status'] ) :
												?>
												<p class="mwright-alert mwright-alert--warning">
													<?php
													printf(
														/* translators: %s: status label, e.g. Draft */
														esc_html__( 'This template is %s, so Contact Form 7 is still sending this email. Set it to Active to use it.', 'mailwright-for-contact-form-7' ),
														esc_html( MWRIGHT_Template_Post_Type::status_label( $mwright_assigned_template['status'] ) )
													);
													?>
												</p>
												<?php
											endif;

											// Mailing the visitor's own upload back to them is
											// occasionally wanted and often a mistake. Warn, never block.
											if (
												'customer' === $mwright_slot
												&& $mwright_assigned_template
												&& '' !== trim( (string) $mwright_assigned_template['attachments'] )
											) :
												?>
												<p class="mwright-alert mwright-alert--warning">
													<?php esc_html_e( 'This template attaches the visitor’s uploaded files, and this email goes to the visitor.', 'mailwright-for-contact-form-7' ); ?>
												</p>
											<?php endif; ?>
										<?php elseif ( 'customer' === $mwright_slot ) : ?>
											<p class="mwright-help"><?php esc_html_e( 'Optional. Sends a confirmation to the visitor.', 'mailwright-for-contact-form-7' ); ?></p>
										<?php endif; ?>
									</div>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	<?php endif; ?>

</div>

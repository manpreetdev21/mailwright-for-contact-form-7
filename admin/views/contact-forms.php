<?php
/**
 * Contact Forms overview: what each form offers, and which templates it uses.
 *
 * Read-only. Everything here is detected from Contact Form 7's own API, so a
 * form that gains a field shows the change without anything being saved.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

$mwright_forms       = MWRIGHT_CF7_Bridge::forms();
$mwright_assignments = MWRIGHT_CF7_Bridge::assignments();

$mwright_slots = array(
	'admin'    => __( 'Admin Email', 'mailwright-for-contact-form-7' ),
	'customer' => __( 'Customer Email', 'mailwright-for-contact-form-7' ),
);
?>
<div class="wrap mwright">

	<?php
	MWRIGHT_Admin::header( __( 'Contact Forms', 'mailwright-for-contact-form-7' ) );
	MWRIGHT_Admin::flash();
	?>

	<?php if ( ! $mwright_forms ) : ?>

		<div class="mwright-empty">
			<span class="dashicons dashicons-feedback" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'No contact forms found.', 'mailwright-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'Create a form in Contact Form 7 and its fields will be detected here automatically.', 'mailwright-for-contact-form-7' ); ?></p>
			<a class="mwright-btn mwright-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpcf7-new' ) ); ?>">
				<?php esc_html_e( 'Create a contact form', 'mailwright-for-contact-form-7' ); ?>
			</a>
		</div>

	<?php else : ?>

		<div class="mwright-card mwright-card--flush">
			<table class="mwright-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Contact Form', 'mailwright-for-contact-form-7' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Fields', 'mailwright-for-contact-form-7' ); ?></th>
						<th scope="col"><?php esc_html_e( 'File Uploads', 'mailwright-for-contact-form-7' ); ?></th>
						<?php foreach ( $mwright_slots as $mwright_label ) : ?>
							<th scope="col"><?php echo esc_html( $mwright_label ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $mwright_forms as $mwright_form_id => $title ) :
						$mwright_tags    = MWRIGHT_CF7_Bridge::form_tags( $mwright_form_id );
						$mwright_files   = MWRIGHT_CF7_Bridge::file_fields( $mwright_form_id );
						$mwright_current = $mwright_assignments[ $mwright_form_id ] ?? array();
						?>
						<tr>
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

							<td data-label="<?php esc_attr_e( 'Fields', 'mailwright-for-contact-form-7' ); ?>">
								<?php if ( $mwright_tags ) : ?>
									<div class="mwright-muted">
										<?php
										$names = array();

										foreach ( $mwright_tags as $tag ) {
											if ( empty( $tag['is_file'] ) ) {
												$names[] = '[' . $tag['name'] . ']';
											}
										}

										echo esc_html( $names ? implode( ', ', $names ) : __( 'None', 'mailwright-for-contact-form-7' ) );
										?>
									</div>
								<?php else : ?>
									<span class="mwright-muted"><?php esc_html_e( 'None', 'mailwright-for-contact-form-7' ); ?></span>
								<?php endif; ?>
							</td>

							<td data-label="<?php esc_attr_e( 'File Uploads', 'mailwright-for-contact-form-7' ); ?>">
								<?php if ( $mwright_files ) : ?>
									<?php foreach ( $mwright_files as $mwright_name ) : ?>
										<span class="mwright-badge mwright-badge--info"><?php echo esc_html( '[' . $mwright_name . ']' ); ?></span>
									<?php endforeach; ?>
								<?php else : ?>
									<span class="mwright-muted"><?php esc_html_e( 'None', 'mailwright-for-contact-form-7' ); ?></span>
								<?php endif; ?>
							</td>

							<?php
							foreach ( $mwright_slots as $mwright_slot => $mwright_label ) :
								$mwright_assigned = (int) ( $mwright_current[ $mwright_slot ] ?? 0 );
								$mwright_template = $mwright_assigned ? MWRIGHT_Template_Post_Type::get( $mwright_assigned ) : null;
								?>
								<td data-label="<?php echo esc_attr( $mwright_label ); ?>">
									<?php if ( $mwright_template ) : ?>
										<a href="<?php echo esc_url( MWRIGHT_Plugin::url( 'template-edit', array( 'template' => $mwright_assigned ) ) ); ?>">
											<?php echo esc_html( $mwright_template['name'] ); ?>
										</a>
									<?php else : ?>
										<span class="mwright-muted"><?php esc_html_e( 'Contact Form 7 default', 'mailwright-for-contact-form-7' ); ?></span>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<p class="mwright-help">
			<a class="mwright-btn" href="<?php echo esc_url( MWRIGHT_Plugin::url( 'assignments' ) ); ?>">
				<?php esc_html_e( 'Manage assignments', 'mailwright-for-contact-form-7' ); ?>
			</a>
		</p>

	<?php endif; ?>

</div>

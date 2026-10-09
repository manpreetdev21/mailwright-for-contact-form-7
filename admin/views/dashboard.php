<?php
/**
 * Dashboard screen.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

$mwright_counts       = MWRIGHT_Template_Post_Type::counts();
$mwright_assigned_ids = MWRIGHT_CF7_Bridge::assigned_template_ids();
$mwright_forms        = MWRIGHT_CF7_Bridge::forms();
$mwright_assignments  = MWRIGHT_CF7_Bridge::assignments();

$mwright_cards = array(
	array(
		'icon'  => 'dashicons-email-alt',
		'count' => $mwright_counts['total'],
		'label' => __( 'Total Templates', 'mailwright-for-contact-form-7' ),
		'desc'  => __( 'Every template in your library.', 'mailwright-for-contact-form-7' ),
		'link'  => MWRIGHT_Plugin::url( 'templates' ),
		'cta'   => __( 'Manage templates', 'mailwright-for-contact-form-7' ),
	),
	array(
		'icon'  => 'dashicons-yes-alt',
		'count' => $mwright_counts['publish'],
		'label' => __( 'Active Templates', 'mailwright-for-contact-form-7' ),
		'desc'  => __( 'Ready to be assigned to a form.', 'mailwright-for-contact-form-7' ),
		'link'  => MWRIGHT_Plugin::url( 'templates', array( 'filter' => 'publish' ) ),
		'cta'   => __( 'View active', 'mailwright-for-contact-form-7' ),
	),
	array(
		'icon'  => 'dashicons-admin-links',
		'count' => count( $mwright_assignments ),
		'label' => __( 'Assigned Forms', 'mailwright-for-contact-form-7' ),
		'desc'  => __( 'Contact forms using a template.', 'mailwright-for-contact-form-7' ),
		'link'  => MWRIGHT_Plugin::url( 'assignments' ),
		'cta'   => __( 'View assignments', 'mailwright-for-contact-form-7' ),
	),
	array(
		'icon'  => 'dashicons-archive',
		'count' => max( 0, $mwright_counts['total'] - count( $mwright_assigned_ids ) ),
		'label' => __( 'Unused Templates', 'mailwright-for-contact-form-7' ),
		'desc'  => __( 'Not assigned to any form yet.', 'mailwright-for-contact-form-7' ),
		'link'  => MWRIGHT_Plugin::url( 'templates', array( 'filter' => 'unused' ) ),
		'cta'   => __( 'View unused', 'mailwright-for-contact-form-7' ),
	),
	array(
		'icon'  => 'dashicons-feedback',
		'count' => count( $mwright_forms ),
		'label' => __( 'Contact Forms', 'mailwright-for-contact-form-7' ),
		'desc'  => __( 'Forms detected in Contact Form 7.', 'mailwright-for-contact-form-7' ),
		'link'  => admin_url( 'admin.php?page=wpcf7' ),
		'cta'   => __( 'Open Contact Form 7', 'mailwright-for-contact-form-7' ),
	),
	array(
		'icon'  => 'dashicons-editor-code',
		'count' => MWRIGHT_Template_Post_Type::count_by_type( 'html' ),
		'label' => __( 'HTML Templates', 'mailwright-for-contact-form-7' ),
		'desc'  => __( 'Designed, branded email layouts.', 'mailwright-for-contact-form-7' ),
		'link'  => MWRIGHT_Plugin::url( 'templates', array( 'filter' => 'html' ) ),
		'cta'   => __( 'View HTML', 'mailwright-for-contact-form-7' ),
	),
	array(
		'icon'  => 'dashicons-editor-alignleft',
		'count' => MWRIGHT_Template_Post_Type::count_by_type( 'text' ),
		'label' => __( 'Plain Text Templates', 'mailwright-for-contact-form-7' ),
		'desc'  => __( 'Readable in every mail client.', 'mailwright-for-contact-form-7' ),
		'link'  => MWRIGHT_Plugin::url( 'templates', array( 'filter' => 'text' ) ),
		'cta'   => __( 'View plain text', 'mailwright-for-contact-form-7' ),
	),
	array(
		'icon'  => 'dashicons-paperclip',
		'count' => MWRIGHT_Template_Post_Type::count_with_files(),
		'label' => __( 'File Upload Templates', 'mailwright-for-contact-form-7' ),
		'desc'  => __( 'Templates that attach uploaded files.', 'mailwright-for-contact-form-7' ),
		'link'  => MWRIGHT_Plugin::url( 'templates', array( 'filter' => 'files' ) ),
		'cta'   => __( 'View file templates', 'mailwright-for-contact-form-7' ),
	),
);
?>
<div class="wrap mwright">

	<?php
	MWRIGHT_Admin::header(
		__( 'Dashboard', 'mailwright-for-contact-form-7' ),
		sprintf(
			'<a class="mwright-btn mwright-btn--primary" href="%s"><span class="dashicons dashicons-plus-alt2"></span>%s</a>',
			esc_url( MWRIGHT_Plugin::url( 'template-edit' ) ),
			esc_html__( 'Add New Template', 'mailwright-for-contact-form-7' )
		)
	);

	MWRIGHT_Admin::flash();
	?>

	<?php if ( 0 === $mwright_counts['total'] ) : ?>

		<div class="mwright-empty">
			<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'No templates yet.', 'mailwright-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'Create your first reusable Contact Form 7 email template.', 'mailwright-for-contact-form-7' ); ?></p>
			<a class="mwright-btn mwright-btn--primary" href="<?php echo esc_url( MWRIGHT_Plugin::url( 'template-edit' ) ); ?>">
				<?php esc_html_e( 'Create Template', 'mailwright-for-contact-form-7' ); ?>
			</a>
		</div>

	<?php else : ?>

		<div class="mwright-grid">
			<?php foreach ( $mwright_cards as $mwright_card ) : ?>
				<div class="mwright-card mwright-stat">
					<div class="mwright-stat__top">
						<span class="dashicons <?php echo esc_attr( $mwright_card['icon'] ); ?>" aria-hidden="true"></span>
						<span class="mwright-stat__count"><?php echo esc_html( number_format_i18n( $mwright_card['count'] ) ); ?></span>
					</div>
					<h2 class="mwright-stat__label"><?php echo esc_html( $mwright_card['label'] ); ?></h2>
					<p class="mwright-muted"><?php echo esc_html( $mwright_card['desc'] ); ?></p>
					<a class="mwright-stat__link" href="<?php echo esc_url( $mwright_card['link'] ); ?>">
						<?php echo esc_html( $mwright_card['cta'] ); ?> <span aria-hidden="true">&rarr;</span>
					</a>
				</div>
			<?php endforeach; ?>
		</div>

		<?php
		$mwright_recent = get_posts(
			array(
				'post_type'      => MWRIGHT_Template_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 5,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		?>

		<?php if ( $mwright_recent ) : ?>
			<div class="mwright-card mwright-card--flush">
				<div class="mwright-card__head">
					<h2><?php esc_html_e( 'Recently updated', 'mailwright-for-contact-form-7' ); ?></h2>
					<a href="<?php echo esc_url( MWRIGHT_Plugin::url( 'templates' ) ); ?>">
						<?php esc_html_e( 'View all', 'mailwright-for-contact-form-7' ); ?>
					</a>
				</div>
				<table class="mwright-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Template', 'mailwright-for-contact-form-7' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'mailwright-for-contact-form-7' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Updated', 'mailwright-for-contact-form-7' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $mwright_recent as $post ) : ?>
							<?php
							$mwright_modifier = match ( $post->post_status ) {
								'publish' => 'success',
								'private' => 'neutral',
								default   => 'warning',
							};
							?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Template', 'mailwright-for-contact-form-7' ); ?>">
									<a href="<?php echo esc_url( MWRIGHT_Plugin::url( 'template-edit', array( 'template' => $post->ID ) ) ); ?>">
										<?php echo esc_html( $post->post_title ); ?>
									</a>
								</td>
								<td data-label="<?php esc_attr_e( 'Status', 'mailwright-for-contact-form-7' ); ?>">
									<span class="mwright-badge mwright-badge--<?php echo esc_attr( $mwright_modifier ); ?>">
										<?php echo esc_html( MWRIGHT_Template_Post_Type::status_label( $post->post_status ) ); ?>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Updated', 'mailwright-for-contact-form-7' ); ?>">
									<?php
									$mwright_timestamp = get_post_timestamp( $post, 'modified' );

									echo esc_html(
										$mwright_timestamp
											? sprintf(
												/* translators: %s: human-readable time difference */
												__( '%s ago', 'mailwright-for-contact-form-7' ),
												human_time_diff( $mwright_timestamp )
											)
											: '—'
									);
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	<?php endif; ?>

	<?php if ( ! $mwright_forms ) : ?>
		<div class="mwright-alert mwright-alert--warning">
			<?php
			printf(
				/* translators: %s: link to Contact Form 7 */
				esc_html__( 'No contact forms found yet. %s to create one before assigning templates.', 'mailwright-for-contact-form-7' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wpcf7-new' ) ) . '">' . esc_html__( 'Open Contact Form 7', 'mailwright-for-contact-form-7' ) . '</a>'
			);
			?>
		</div>
	<?php endif; ?>

</div>

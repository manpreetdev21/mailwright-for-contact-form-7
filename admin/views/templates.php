<?php
/**
 * Templates list screen.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

require_once MWRIGHT_DIR . 'admin/class-templates-list-table.php';

$mwright_table = new MWRIGHT_Templates_List_Table();
$mwright_table->prepare_items();

$mwright_has_any = MWRIGHT_Template_Post_Type::counts()['total'] > 0;
?>
<div class="wrap mwright">

	<?php
	MWRIGHT_Admin::header(
		__( 'Email Templates', 'mailwright-for-contact-form-7' ),
		sprintf(
			'<a class="mwright-btn mwright-btn--primary" href="%s"><span class="dashicons dashicons-plus-alt2"></span>%s</a>',
			esc_url( MWRIGHT_Plugin::url( 'template-edit' ) ),
			esc_html__( 'Add New Template', 'mailwright-for-contact-form-7' )
		)
	);

	MWRIGHT_Admin::flash();
	?>

	<?php if ( ! $mwright_has_any ) : ?>

		<div class="mwright-empty">
			<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
			<h2><?php esc_html_e( 'No templates yet.', 'mailwright-for-contact-form-7' ); ?></h2>
			<p><?php esc_html_e( 'Create your first reusable Contact Form 7 email template.', 'mailwright-for-contact-form-7' ); ?></p>
			<a class="mwright-btn mwright-btn--primary" href="<?php echo esc_url( MWRIGHT_Plugin::url( 'template-edit' ) ); ?>">
				<?php esc_html_e( 'Create Template', 'mailwright-for-contact-form-7' ); ?>
			</a>
		</div>

	<?php else : ?>

		<div class="mwright-card mwright-card--flush mwright-list">
			<form method="get">
				<input type="hidden" name="page" value="mwright-templates" />
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, preserved across search.
				$mwright_filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';

				if ( $mwright_filter ) {
					printf( '<input type="hidden" name="filter" value="%s" />', esc_attr( $mwright_filter ) );
				}

				$mwright_table->views();
				$mwright_table->search_box( __( 'Search templates', 'mailwright-for-contact-form-7' ), 'mwright-search' );
				?>
			</form>

			<form method="post">
				<?php
				// WP_List_Table::display() emits the bulk-action nonce itself.
				$mwright_table->display();
				?>
			</form>

			<?php if ( ! $mwright_table->has_items() ) : ?>
				<div class="mwright-empty mwright-empty--inline">
					<h2><?php esc_html_e( 'Nothing matches that filter.', 'mailwright-for-contact-form-7' ); ?></h2>
					<p><?php esc_html_e( 'Try a different search term or clear the filter.', 'mailwright-for-contact-form-7' ); ?></p>
					<a class="mwright-btn" href="<?php echo esc_url( MWRIGHT_Plugin::url( 'templates' ) ); ?>">
						<?php esc_html_e( 'Clear filters', 'mailwright-for-contact-form-7' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

	<?php endif; ?>

</div>

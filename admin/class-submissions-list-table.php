<?php
/**
 * The submissions list table.
 *
 * With no form selected it lists every submission. Pick a form and the table
 * grows a column for each of that form's fields, so one screen shows the whole
 * enquiry rather than a summary.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MWRIGHT_Submissions_List_Table extends WP_List_Table {

	/** Field name => label for the selected form. */
	private $fields = array();

	/** Forms that have submissions. */
	private $forms = array();

	/**
	 * Sets up the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'mwright_entry',
				'plural'   => 'mwright_entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * The form being viewed, or 0 for all of them.
	 *
	 * @return int
	 */
	public function current_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		return isset( $_REQUEST['form'] ) ? absint( $_REQUEST['form'] ) : 0;
	}

	/**
	 * The status filter.
	 *
	 * @return string
	 */
	private function current_status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';

		return in_array( $status, array( 'sent', 'failed' ), true ) ? $status : '';
	}

	/**
	 * Columns. Field columns only appear once a form is chosen.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'           => '<input type="checkbox" />',
			'submitted_at' => __( 'Submitted', 'mailwright-for-contact-form-7' ),
		);

		if ( ! $this->current_form() ) {
			$columns['form_title'] = __( 'Form', 'mailwright-for-contact-form-7' );
		}

		$columns['status'] = __( 'Email', 'mailwright-for-contact-form-7' );

		foreach ( $this->fields as $name => $label ) {
			$columns[ 'field-' . $name ] = $label;
		}

		$columns['details'] = __( 'Submitted Data', 'mailwright-for-contact-form-7' );

		return $columns;
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'submitted_at' => array( 'submitted_at', true ),
			'form_title'   => array( 'form_title', false ),
			'status'       => array( 'status', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'mailwright-for-contact-form-7' ) );
	}

	/**
	 * One link per form that has submissions.
	 *
	 * @return array
	 */
	protected function get_views() {
		$current = $this->current_form();

		$views = array(
			'all' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( MWRIGHT_Plugin::url( 'submissions' ) ),
				$current ? '' : ' class="current" aria-current="page"',
				esc_html__( 'All Forms', 'mailwright-for-contact-form-7' ),
				MWRIGHT_Submissions::count()
			),
		);

		foreach ( $this->forms as $form_id => $form ) {
			$views[ 'form-' . $form_id ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( MWRIGHT_Plugin::url( 'submissions', array( 'form' => $form_id ) ) ),
				$current === $form_id ? ' class="current" aria-current="page"' : '',
				esc_html( $form['title'] ? $form['title'] : sprintf( '#%d', $form_id ) ),
				$form['count']
			);
		}

		return $views;
	}

	/**
	 * Status filter above the table.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$status  = $this->current_status();
		$options = array(
			''       => __( 'Any email result', 'mailwright-for-contact-form-7' ),
			'sent'   => __( 'Email sent', 'mailwright-for-contact-form-7' ),
			'failed' => __( 'Email failed', 'mailwright-for-contact-form-7' ),
		);

		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="mwright-status">' .
			esc_html__( 'Filter by email result', 'mailwright-for-contact-form-7' ) . '</label>';
		echo '<select name="status" id="mwright-status">';

		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $status, $value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
		submit_button( __( 'Filter', 'mailwright-for-contact-form-7' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Loads items for the current page.
	 */
	public function prepare_items() {
		$this->process_bulk_action();

		$this->forms = MWRIGHT_Submissions::forms();

		$per_page = $this->get_items_per_page( 'mwright_entries_per_page', 20 );
		$form_id  = $this->current_form();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only ordering.
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'submitted_at';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only ordering.
		$order = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';

		$results = MWRIGHT_Submissions::query(
			array(
				'form_id'  => $form_id,
				'status'   => $this->current_status(),
				'search'   => $search,
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
				'orderby'  => $orderby,
				'order'    => $order,
			)
		);

		$this->items = $results['items'];

		// Field columns only make sense when every row is the same form.
		$this->fields = $form_id ? MWRIGHT_Submissions::field_columns( $form_id, $this->items ) : array();

		$this->set_pagination_args(
			array(
				'total_items' => $results['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $results['total'] / max( 1, $per_page ) ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Handles the delete bulk action.
	 */
	public function process_bulk_action() {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );
		MWRIGHT_Plugin::require_cap();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked immediately above.
		MWRIGHT_Submissions::delete( array_map( 'absint', (array) wp_unslash( $_REQUEST['entry'] ?? array() ) ) );
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Submission.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="entry[]" value="%d" />', $item['id'] );
	}

	/**
	 * Date column, with the row actions.
	 *
	 * @param array $item Submission.
	 * @return string
	 */
	public function column_submitted_at( $item ) {
		$stamp = strtotime( $item['submitted_at'] );

		$view = MWRIGHT_Plugin::url( 'submissions', array( 'entry' => $item['id'] ) );

		$delete = wp_nonce_url(
			MWRIGHT_Plugin::url( 'submissions', array( 'entry_action' => 'delete', 'entry' => $item['id'] ) ),
			'mwright_delete_entry_' . $item['id']
		);

		$actions = array(
			'view'   => sprintf( '<a href="%s">%s</a>', esc_url( $view ), esc_html__( 'View', 'mailwright-for-contact-form-7' ) ),
			'delete' => sprintf(
				'<a href="%s" class="mwright-danger-link" data-mwright-confirm="%s">%s</a>',
				esc_url( $delete ),
				esc_attr__( 'Delete this submission? This cannot be undone.', 'mailwright-for-contact-form-7' ),
				esc_html__( 'Delete', 'mailwright-for-contact-form-7' )
			),
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $view ),
			esc_html( $stamp ? wp_date( 'Y-m-d H:i', $stamp ) : $item['submitted_at'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Form column.
	 *
	 * @param array $item Submission.
	 * @return string
	 */
	public function column_form_title( $item ) {
		$title = $item['form_title'] ? $item['form_title'] : sprintf( '#%d', $item['form_id'] );

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( MWRIGHT_Plugin::url( 'submissions', array( 'form' => $item['form_id'] ) ) ),
			esc_html( $title )
		);
	}

	/**
	 * Email result column.
	 *
	 * @param array $item Submission.
	 * @return string
	 */
	public function column_status( $item ) {
		$sent = 'sent' === $item['status'];

		return sprintf(
			'<span class="mwright-badge mwright-badge--%s">%s</span>',
			$sent ? 'success' : 'danger',
			$sent
				? esc_html__( 'Sent', 'mailwright-for-contact-form-7' )
				: esc_html__( 'Not sent', 'mailwright-for-contact-form-7' )
		);
	}

	/**
	 * Everything else: a field column, or the all-forms summary.
	 *
	 * @param array  $item   Submission.
	 * @param string $column Column key.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		if ( 'details' === $column ) {
			return sprintf(
				'<button type="button" class="mwright-btn mwright-btn--small" data-mwright-entry-toggle aria-expanded="false" aria-controls="mwright-entry-%1$d"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>%2$s</button>',
				$item['id'],
				esc_html__( 'Details', 'mailwright-for-contact-form-7' )
			);
		}

		if ( ! str_starts_with( $column, 'field-' ) ) {
			return '';
		}

		$name = substr( $column, 6 );

		$files = MWRIGHT_Submissions::files( $item );

		if ( isset( $files[ $name ] ) ) {
			return self::file_links( $item['id'], $name, $files[ $name ] );
		}

		if ( ! isset( $item['fields'][ $name ] ) ) {
			return '<span class="mwright-muted">&mdash;</span>';
		}

		$value = MWRIGHT_Submissions::flatten( $item['fields'][ $name ] );

		return '' === trim( $value )
			? '<span class="mwright-muted">&mdash;</span>'
			: esc_html( self::shorten( $value ) );
	}

	/**
	 * Paperclip links to the stored copies of what was uploaded.
	 *
	 * @param int    $entry_id Submission ID.
	 * @param string $field    Field name.
	 * @param array  $files    Files on that field.
	 * @return string
	 */
	private static function file_links( $entry_id, $field, $files ) {
		$links = array();

		foreach ( $files as $file ) {
			if ( '' === $file['path'] ) {
				$links[] = sprintf(
					'<span class="mwright-muted" title="%s">%s</span>',
					esc_attr__( 'This file type is not stored.', 'mailwright-for-contact-form-7' ),
					esc_html( $file['name'] )
				);
				continue;
			}

			$links[] = sprintf(
				'<a href="%s"><span class="dashicons dashicons-paperclip" aria-hidden="true"></span>%s</a>',
				esc_url( MWRIGHT_Submissions::download_url( $entry_id, $field, $file['index'] ) ),
				esc_html( $file['name'] )
			);
		}

		return implode( ', ', $links );
	}

	/**
	 * Each row is followed by a hidden one holding the whole submission, so
	 * Details opens it in place instead of sending anyone to another screen.
	 *
	 * @param array $item Submission.
	 */
	public function single_row( $item ) {
		echo '<tr>';
		$this->single_row_columns( $item );
		echo '</tr>';

		printf(
			'<tr class="mwright-entry__row" id="mwright-entry-%1$d" hidden><td colspan="%2$d">',
			(int) $item['id'],
			(int) $this->get_column_count()
		);

		$mwright_entry = $item;
		require MWRIGHT_DIR . 'admin/views/partial-entry-data.php';

		echo '</td></tr>';
	}

	/**
	 * Trims a long answer for the table; the full text is on the detail view.
	 *
	 * @param string $value Field value.
	 * @return string
	 */
	private static function shorten( $value ) {
		$value = trim( preg_replace( '/\s+/', ' ', $value ) );

		return mb_strlen( $value ) > 60 ? mb_substr( $value, 0, 60 ) . '…' : $value;
	}

	/**
	 * Message shown when nothing matches.
	 */
	public function no_items() {
		esc_html_e( 'No submissions yet.', 'mailwright-for-contact-form-7' );
	}
}

<?php
/**
 * WP_List_Table subclass for the External Link Manager dashboard.
 *
 * Provides a native WordPress admin table with sortable columns, search,
 * pagination, and bulk actions — no custom JS grid required.
 * Columns: URL, Domain, Source Post, Attributes, Status, Last Scanned.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WP_List_Table is not auto-loaded outside of list-table screens.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class ELM_List_Table
 */
class ELM_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'elm_link',
				'plural'   => 'elm_links',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Prepare the items for the table to process.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Process bulk actions or row actions before fetching data.
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'elm_links_per_page', 20 );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'detected_at';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc';
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
		$domain  = isset( $_REQUEST['domain'] ) ? sanitize_text_field( $_REQUEST['domain'] ) : '';

		$args = array(
			'per_page' => $per_page,
			'offset'   => $offset,
			'orderby'  => $orderby,
			'order'    => $order,
			'search'   => $search,
			'domain'   => $domain,
		);

		$this->items = ELM_Cache::get_dashboard_links( $args );
		$total_items = ELM_Cache::get_dashboard_count( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Define the columns that are going to be used in the table.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'url'         => __( 'Link Details', 'external-link-manager' ),
			'domain'      => __( 'Domain', 'external-link-manager' ),
			'post_id'     => __( 'Source Post', 'external-link-manager' ),
			'attributes'  => __( 'Attributes', 'external-link-manager' ),
			'is_excluded' => __( 'Excluded', 'external-link-manager' ),
			'detected_at' => __( 'Detected Date', 'external-link-manager' ),
		);
		return $columns;
	}

	/**
	 * Decide which columns to activate the sorting functionality on.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'url'         => array( 'url', false ),
			'domain'      => array( 'domain', false ),
			'post_id'     => array( 'post_id', false ),
			'detected_at' => array( 'detected_at', true ), // True means already sorted desc.
		);
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param object $item A singular item (one full row's worth of data).
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="link_ids[]" value="%s" />',
			absint( $item->id )
		);
	}

	/**
	 * Render the URL column with row actions.
	 *
	 * @param object $item
	 * @return string
	 */
	protected function column_url( $item ) {
		$url_display = '<strong><a href="' . esc_url( $item->url ) . '" target="_blank">' . esc_html( $item->url ) . '</a></strong>';
		if ( ! empty( $item->anchor_text ) ) {
			$url_display .= '<br/><span class="description">' . esc_html( $item->anchor_text ) . '</span>';
		}

		$actions = array();
		
		// Toggle Exclude Action
		$exclude_nonce = wp_create_nonce( 'elm_toggle_exclude_' . $item->id );
		$exclude_url   = sprintf( '?page=%s&action=%s&link_id=%s&_wpnonce=%s', esc_attr( $_REQUEST['page'] ), 'toggle_exclude', absint( $item->id ), $exclude_nonce );
		$exclude_label = ! empty( $item->is_excluded ) ? __( 'Include', 'external-link-manager' ) : __( 'Exclude', 'external-link-manager' );
		$actions['exclude'] = sprintf( '<a href="%s">%s</a>', esc_url( $exclude_url ), esc_html( $exclude_label ) );

		// Override Attributes Action
		$override_nonce = wp_create_nonce( 'elm_override_' . $item->id );
		$override_url   = sprintf( '?page=%s&action=%s&link_id=%s&_wpnonce=%s', esc_attr( $_REQUEST['page'] ), 'override_attributes', absint( $item->id ), $override_nonce );
		$actions['override'] = sprintf( '<a href="%s">%s</a>', esc_url( $override_url ), __( 'Override Attributes', 'external-link-manager' ) );

		return $url_display . $this->row_actions( $actions );
	}

	/**
	 * Render the Domain column.
	 *
	 * @param object $item
	 * @return string
	 */
	protected function column_domain( $item ) {
		$filter_url = sprintf( '?page=%s&domain=%s', esc_attr( $_REQUEST['page'] ), urlencode( $item->domain ) );
		return sprintf( '<a href="%s">%s</a>', esc_url( $filter_url ), esc_html( $item->domain ) );
	}

	/**
	 * Render the Source Post column.
	 *
	 * @param object $item
	 * @return string
	 */
	protected function column_post_id( $item ) {
		$title = get_the_title( $item->post_id );
		if ( ! $title ) {
			$title = __( '(No Title)', 'external-link-manager' );
		}
		
		$edit_link = get_edit_post_link( $item->post_id );
		if ( $edit_link ) {
			return sprintf( '<strong><a href="%s">%s</a></strong>', esc_url( $edit_link ), esc_html( $title ) );
		}
		
		return esc_html( $title );
	}

	/**
	 * Render the Attributes column.
	 *
	 * @param object $item
	 * @return string
	 */
	protected function column_attributes( $item ) {
		if ( ! empty( $item->attributes_json ) ) {
			return '<span class="dashicons dashicons-admin-customizer" title="' . esc_attr__( 'Overridden', 'external-link-manager' ) . '"></span> ' . __( 'Custom', 'external-link-manager' );
		}
		return '<span class="description">' . __( 'Global Settings', 'external-link-manager' ) . '</span>';
	}

	/**
	 * Render the Excluded column.
	 *
	 * @param object $item
	 * @return string
	 */
	protected function column_is_excluded( $item ) {
		if ( ! empty( $item->is_excluded ) ) {
			return '<span class="dashicons dashicons-hidden" style="color:#d63638;" title="' . esc_attr__( 'Excluded', 'external-link-manager' ) . '"></span>';
		}
		return '<span class="dashicons dashicons-visibility" style="color:#00a32a;" title="' . esc_attr__( 'Included', 'external-link-manager' ) . '"></span>';
	}

	/**
	 * Render the Detected Date column.
	 *
	 * @param object $item
	 * @return string
	 */
	protected function column_detected_at( $item ) {
		$date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( get_date_from_gmt( $item->detected_at ) ) );
		return esc_html( $date );
	}

	/**
	 * Fallback for unknown columns.
	 *
	 * @param object $item
	 * @param string $column_name
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return print_r( $item->$column_name, true );
	}

	/**
	 * Add filter dropdowns (Domain filter).
	 *
	 * @param string $which 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$domains = ELM_Cache::get_distinct_domains();
		if ( empty( $domains ) ) {
			return;
		}

		$current_domain = isset( $_REQUEST['domain'] ) ? sanitize_text_field( $_REQUEST['domain'] ) : '';
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="filter-by-domain"><?php esc_html_e( 'Filter by domain', 'external-link-manager' ); ?></label>
			<select name="domain" id="filter-by-domain">
				<option value=""><?php esc_html_e( 'All domains', 'external-link-manager' ); ?></option>
				<?php foreach ( $domains as $domain ) : ?>
					<option value="<?php echo esc_attr( $domain ); ?>" <?php selected( $current_domain, $domain ); ?>>
						<?php echo esc_html( $domain ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'external-link-manager' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk_exclude' => __( 'Exclude', 'external-link-manager' ),
			'bulk_include' => __( 'Include', 'external-link-manager' ),
		);
	}

	/**
	 * Handle bulk actions and row actions.
	 */
	public function process_bulk_action() {
		global $wpdb;

		// Row Actions
		if ( isset( $_REQUEST['action'] ) && 'toggle_exclude' === $_REQUEST['action'] && isset( $_REQUEST['link_id'] ) ) {
			$link_id = absint( $_REQUEST['link_id'] );
			
			if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'elm_toggle_exclude_' . $link_id ) && current_user_can( 'manage_options' ) ) {
				$table = ELM_DB::table_name();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$current = $wpdb->get_var( $wpdb->prepare( "SELECT is_excluded FROM `{$table}` WHERE id = %d", $link_id ) );
				
				if ( null !== $current ) {
					$new_val = $current ? 0 : 1;
					ELM_DB::update_link( $link_id, array( 'is_excluded' => $new_val ) );
					
					// Invalidate cache for the specific post
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM `{$table}` WHERE id = %d", $link_id ) );
					if ( $post_id ) {
						ELM_Cache::invalidate_post( $post_id );
					}
					
					// Redirect to avoid re-submission.
					$redirect_url = remove_query_arg( array( 'action', 'link_id', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) );
					wp_safe_redirect( $redirect_url );
					exit;
				}
			}
		}

		// Bulk Actions
		$action = $this->current_action();
		if ( ! $action || empty( $_REQUEST['link_ids'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$link_ids = array_map( 'absint', (array) $_REQUEST['link_ids'] );
		$table    = ELM_DB::table_name();

		if ( 'bulk_exclude' === $action || 'bulk_include' === $action ) {
			$is_excluded = 'bulk_exclude' === $action ? 1 : 0;
			
			foreach ( $link_ids as $link_id ) {
				ELM_DB::update_link( $link_id, array( 'is_excluded' => $is_excluded ) );
				
				// Invalidate cache for the specific post
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM `{$table}` WHERE id = %d", $link_id ) );
				if ( $post_id ) {
					ELM_Cache::invalidate_post( $post_id );
				}
			}

			// Redirect.
			$redirect_url = remove_query_arg( array( 'action', 'action2', 'link_ids', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}
}

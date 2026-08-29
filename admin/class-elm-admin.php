<?php
/**
 * Admin layer — menu registration and screen routing.
 *
 * Registers the top-level admin menu page and submenu items, conditionally
 * enqueues plugin assets only on plugin-owned screens (checked via
 * $hook_suffix), and routes requests to the correct view template.
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ELM_Admin
 */
class ELM_Admin {

	/**
	 * Plugin hook suffix for the main page.
	 *
	 * @var string
	 */
	private $plugin_screen_hook_suffix;

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register the administration menu.
	 */
	public function add_plugin_admin_menu() {
		// Top-level menu page.
		$this->plugin_screen_hook_suffix = add_menu_page(
			__( 'External Link Manager', 'external-link-manager' ),
			__( 'External Links', 'external-link-manager' ),
			'manage_options',
			'elm-dashboard',
			array( $this, 'display_plugin_dashboard_page' ),
			'dashicons-admin-links', // Native icon.
			81 // Position.
		);

		// Submenu: Dashboard (same slug as parent to rename first item).
		add_submenu_page(
			'elm-dashboard',
			__( 'Dashboard', 'external-link-manager' ),
			__( 'Dashboard', 'external-link-manager' ),
			'manage_options',
			'elm-dashboard',
			array( $this, 'display_plugin_dashboard_page' )
		);

		// Process list table actions before headers are sent.
		add_action( "load-{$this->plugin_screen_hook_suffix}", array( $this, 'process_list_table_actions' ) );

		// Submenu: Settings.
		add_submenu_page(
			'elm-dashboard',
			__( 'Settings', 'external-link-manager' ),
			__( 'Settings', 'external-link-manager' ),
			'manage_options',
			'elm-settings',
			array( $this, 'display_plugin_settings_page' )
		);
	}

	/**
	 * Process list table bulk and row actions early, before headers are sent.
	 */
	public function process_list_table_actions() {
		// Handle override form submission.
		if ( isset( $_POST['action'] ) && 'save_override' === $_POST['action'] && isset( $_POST['link_id'] ) ) {
			if ( wp_verify_nonce( $_POST['elm_save_override_nonce'], 'elm_save_override_' . intval( $_POST['link_id'] ) ) && current_user_can( 'manage_options' ) ) {
				$link_id = absint( $_POST['link_id'] );
				
				if ( isset( $_POST['reset_to_global'] ) ) {
					$attributes_json = null;
				} else {
					$override = array(
						'open_new_tab' => ! empty( $_POST['elm_override']['open_new_tab'] ),
						'nofollow'     => ! empty( $_POST['elm_override']['nofollow'] ),
						'sponsored'    => ! empty( $_POST['elm_override']['sponsored'] ),
						'ugc'          => ! empty( $_POST['elm_override']['ugc'] ),
						'noopener'     => ! empty( $_POST['elm_override']['noopener'] ),
						'noreferrer'   => ! empty( $_POST['elm_override']['noreferrer'] ),
						'custom_class' => isset( $_POST['elm_override']['custom_class'] ) ? sanitize_text_field( $_POST['elm_override']['custom_class'] ) : '',
					);
					$attributes_json = wp_json_encode( $override );
				}

				require_once ELM_PLUGIN_DIR . 'includes/class-elm-db.php';
				ELM_DB::update_link( $link_id, array( 'attributes_json' => $attributes_json ) );

				// Invalidate cache
				global $wpdb;
				$table = ELM_DB::table_name();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM `{$table}` WHERE id = %d", $link_id ) );
				if ( $post_id ) {
					require_once ELM_PLUGIN_DIR . 'includes/class-elm-cache.php';
					ELM_Cache::invalidate_post( $post_id );
				}

				$redirect_url = admin_url( 'admin.php?page=elm-dashboard' );
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}

		require_once ELM_PLUGIN_DIR . 'admin/class-elm-list-table.php';
		$list_table = new ELM_List_Table();
		$list_table->process_bulk_action();
	}

	/**
	 * Render the dashboard view.
	 */
	public function display_plugin_dashboard_page() {
		require_once ELM_PLUGIN_DIR . 'admin/class-elm-list-table.php';

		if ( isset( $_GET['action'] ) && 'override_attributes' === $_GET['action'] && ! empty( $_GET['link_id'] ) ) {
			require_once ELM_PLUGIN_DIR . 'admin/views/override.php';
			return;
		}

		require_once ELM_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the settings views based on active tab.
	 */
	public function display_plugin_settings_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'External Link Manager Settings', 'external-link-manager' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="?page=elm-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'external-link-manager' ); ?></a>
				<a href="?page=elm-settings&tab=exclusions" class="nav-tab <?php echo 'exclusions' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Exclusions', 'external-link-manager' ); ?></a>
				<a href="?page=elm-settings&tab=advanced" class="nav-tab <?php echo 'advanced' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Advanced', 'external-link-manager' ); ?></a>
			</h2>

			<?php
			if ( 'general' === $active_tab ) {
				require_once ELM_PLUGIN_DIR . 'admin/views/settings-general.php';
			} elseif ( 'exclusions' === $active_tab ) {
				require_once ELM_PLUGIN_DIR . 'admin/views/settings-exclusions.php';
			} elseif ( 'advanced' === $active_tab ) {
				require_once ELM_PLUGIN_DIR . 'admin/views/settings-advanced.php';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Register and enqueue admin styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_styles( $hook ) {
		// Only enqueue on our plugin pages.
		if ( false === strpos( $hook, 'elm-dashboard' ) && false === strpos( $hook, 'elm-settings' ) ) {
			return;
		}

		wp_enqueue_style(
			'elm-admin',
			ELM_PLUGIN_URL . 'admin/assets/admin.css',
			array(),
			ELM_VERSION,
			'all'
		);
	}

	/**
	 * Register and enqueue admin scripts.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		// Only enqueue on our plugin pages.
		if ( false === strpos( $hook, 'elm-dashboard' ) && false === strpos( $hook, 'elm-settings' ) ) {
			return;
		}

		wp_enqueue_script(
			'elm-admin',
			ELM_PLUGIN_URL . 'admin/assets/admin.js',
			array( 'jquery' ),
			ELM_VERSION,
			true
		);
	}
}

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
	 * Render the dashboard view.
	 */
	public function display_plugin_dashboard_page() {
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

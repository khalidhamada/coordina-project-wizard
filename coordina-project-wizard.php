<?php
/**
 * Plugin Name: Coordina Project Wizard
 * Plugin URI: https://github.com/khalidhamada/coordina-project-wizard
 * Description: Guided project setup add-on for Coordina.
 * Version: 0.2.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Khalid Hamada
 * Author URI: https://khalidhamada.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: coordina-project-wizard
 * Domain Path: /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COORDINA_PROJECT_WIZARD_VERSION', '0.2.0' );
define( 'COORDINA_PROJECT_WIZARD_FILE', __FILE__ );
define( 'COORDINA_PROJECT_WIZARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'COORDINA_PROJECT_WIZARD_URL', plugin_dir_url( __FILE__ ) );
define( 'COORDINA_PROJECT_WIZARD_CORE_PLUGIN', 'coordina/coordina.php' );

/**
 * Returns whether Coordina core is installed.
 */
function coordina_project_wizard_is_coordina_installed(): bool {
	return file_exists( WP_PLUGIN_DIR . '/' . COORDINA_PROJECT_WIZARD_CORE_PLUGIN );
}

/**
 * Returns whether Coordina core is active.
 */
function coordina_project_wizard_is_coordina_active(): bool {
	if ( ! coordina_project_wizard_is_coordina_installed() ) {
		return false;
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( is_plugin_active( COORDINA_PROJECT_WIZARD_CORE_PLUGIN ) ) {
		return true;
	}

	return is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( COORDINA_PROJECT_WIZARD_CORE_PLUGIN );
}

/**
 * Stops activation when Coordina core is missing or inactive.
 */
function coordina_project_wizard_activate(): void {
	if ( coordina_project_wizard_is_coordina_active() ) {
		return;
	}

	deactivate_plugins( plugin_basename( COORDINA_PROJECT_WIZARD_FILE ) );

	$coordina_project_wizard_message = coordina_project_wizard_is_coordina_installed()
		? __( 'Coordina Project Wizard requires the main Coordina plugin to be active before this add-on can be activated.', 'coordina-project-wizard' )
		: __( 'Coordina Project Wizard requires the main Coordina plugin to be installed and active before this add-on can be activated.', 'coordina-project-wizard' );

	wp_die(
		esc_html( $coordina_project_wizard_message ),
		esc_html__( 'Coordina dependency required', 'coordina-project-wizard' ),
		array(
			'back_link' => true,
		)
	);
}

/**
 * Shows a clear admin notice when Coordina core is unavailable.
 */
function coordina_project_wizard_missing_dependency_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$message = coordina_project_wizard_is_coordina_installed()
		? __( 'Coordina Project Wizard is inactive until the main Coordina plugin is activated.', 'coordina-project-wizard' )
		: __( 'Coordina Project Wizard is inactive until the main Coordina plugin is installed and activated.', 'coordina-project-wizard' );

	echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}

register_activation_hook( COORDINA_PROJECT_WIZARD_FILE, 'coordina_project_wizard_activate' );

$coordina_project_wizard_autoload = COORDINA_PROJECT_WIZARD_PATH . 'vendor/autoload.php';

if ( file_exists( $coordina_project_wizard_autoload ) ) {
	require_once $coordina_project_wizard_autoload;
} else {
	require_once COORDINA_PROJECT_WIZARD_PATH . 'src/Support/Autoloader.php';
	CoordinaProjectWizard\Support\Autoloader::register( COORDINA_PROJECT_WIZARD_PATH . 'src/' );
}

if ( ! coordina_project_wizard_is_coordina_active() ) {
	add_action( 'admin_notices', 'coordina_project_wizard_missing_dependency_notice' );
	return;
}

add_filter(
	'coordina/platform/providers',
	static function ( array $providers ): array {
		$providers[] = CoordinaProjectWizard\Providers\ProjectWizardServiceProvider::class;
		return array_values( array_unique( $providers ) );
	}
);

add_filter(
	'coordina/platform/entitlement-providers',
	static function ( array $providers ): array {
		$providers[] = 'project_wizard.entitlement_provider';
		return $providers;
	}
);
<?php
/**
 * Plugin Name: Coordina Project Wizard
 * Plugin URI: https://example.com/coordina-project-wizard
 * Description: Guided project setup add-on for Coordina.
 * Version: 0.2.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Khalid Hamada
 * Author URI: https://example.com
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

$coordina_project_wizard_autoload = COORDINA_PROJECT_WIZARD_PATH . 'vendor/autoload.php';

if ( file_exists( $coordina_project_wizard_autoload ) ) {
	require_once $coordina_project_wizard_autoload;
} else {
	require_once COORDINA_PROJECT_WIZARD_PATH . 'src/Support/Autoloader.php';
	CoordinaProjectWizard\Support\Autoloader::register( COORDINA_PROJECT_WIZARD_PATH . 'src/' );
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
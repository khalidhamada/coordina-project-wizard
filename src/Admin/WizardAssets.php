<?php
/**
 * Enqueues Project Wizard admin assets.
 */

declare(strict_types=1);

namespace CoordinaProjectWizard\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WizardAssets {
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'coordina' ) ) {
			return;
		}

		$page = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		$page = is_string( $page ) ? sanitize_key( wp_unslash( $page ) ) : '';

		if ( 'coordina-project-wizard' !== $page ) {
			return;
		}

		wp_enqueue_style(
			'coordina-project-wizard-admin',
			COORDINA_PROJECT_WIZARD_URL . 'assets/admin/project-wizard.css',
			array( 'coordina-admin-shell', 'coordina-admin-components' ),
			$this->asset_version( COORDINA_PROJECT_WIZARD_PATH . 'assets/admin/project-wizard.css' )
		);

		wp_enqueue_script(
			'coordina-project-wizard-admin',
			COORDINA_PROJECT_WIZARD_URL . 'assets/admin/project-wizard.js',
			array( 'coordina-admin-events', 'wp-i18n' ),
			$this->asset_version( COORDINA_PROJECT_WIZARD_PATH . 'assets/admin/project-wizard.js' ),
			true
		);

		wp_set_script_translations( 'coordina-project-wizard-admin', 'coordina-project-wizard', COORDINA_PROJECT_WIZARD_PATH . 'languages' );
	}

	private function asset_version( string $path ): string {
		return file_exists( $path ) ? (string) filemtime( $path ) : COORDINA_PROJECT_WIZARD_VERSION;
	}
}
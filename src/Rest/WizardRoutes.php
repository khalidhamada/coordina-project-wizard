<?php
/**
 * Registers Project Wizard REST routes.
 */

declare(strict_types=1);

namespace CoordinaProjectWizard\Rest;

use CoordinaProjectWizard\Domain\ProjectWizardService;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class WizardRoutes {
	private const NAMESPACE = 'coordina/v1';

	public static function register( ProjectWizardService $service ): void {
		register_rest_route(
			self::NAMESPACE,
			'/project-wizard/bootstrap',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $request ) use ( $service ): WP_REST_Response {
					unset( $request );
					return self::respond( $service->bootstrap() );
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'coordina_run_project_wizard' );
				},
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/project-wizard/settings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $request ) use ( $service ): WP_REST_Response {
					try {
						return self::respond( $service->update_settings( $request->get_json_params() ) );
					} catch ( Throwable $exception ) {
						return self::error( $exception->getMessage(), 400 );
					}
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'coordina_run_project_wizard' );
				},
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/project-wizard/projects',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $request ) use ( $service ): WP_REST_Response {
					try {
						return self::respond( $service->create_project( $request->get_json_params() ) );
					} catch ( Throwable $exception ) {
						return self::error( $exception->getMessage(), 400 );
					}
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'coordina_run_project_wizard' );
				},
			)
		);
	}

	private static function respond( array $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	private static function error( string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => $message,
			),
			$status
		);
	}
}
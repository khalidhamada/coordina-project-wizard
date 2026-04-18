<?php
/**
 * Lightweight fallback autoloader.
 */

declare(strict_types=1);

namespace CoordinaProjectWizard\Support;

final class Autoloader {
	/**
	 * @var string
	 */
	private static $base_path = '';

	public static function register( string $base_path ): void {
		self::$base_path = rtrim( $base_path, '/\\' ) . DIRECTORY_SEPARATOR;
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	private static function autoload( string $class ): void {
		$prefix = 'CoordinaProjectWizard\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = self::$base_path . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
<?php
/**
 * Project template catalog loader.
 */

declare(strict_types=1);

namespace CoordinaProjectWizard\Templates;

final class ProjectTemplateCatalog {
	/**
	 * @var string
	 */
	private $path;

	/**
	 * @var array<string, mixed>|null
	 */
	private $catalog;

	public function __construct( string $path ) {
		$this->path    = $path;
		$this->catalog = null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$catalog = $this->load();
		$items   = $catalog['templates'] ?? array();

		return is_array( $items ) ? array_values( $items ) : array();
	}

	/**
	 * @return array<int, string>
	 */
	public function keys(): array {
		return array_values(
			array_filter(
				array_map(
					static function ( array $template ): string {
						return \sanitize_key( (string) ( $template['key'] ?? '' ) );
					},
					$this->all()
				)
			)
		);
	}

	public function default_key(): string {
		$keys = $this->keys();

		return $keys[0] ?? '';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function find( string $key ): array {
		$key = \sanitize_key( $key );

		foreach ( $this->all() as $template ) {
			if ( \sanitize_key( (string) ( $template['key'] ?? '' ) ) === $key ) {
				return $template;
			}
		}

		$templates = $this->all();

		return $templates[0] ?? array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load(): array {
		if ( is_array( $this->catalog ) ) {
			return $this->catalog;
		}

		if ( ! file_exists( $this->path ) ) {
			$this->catalog = array(
				'version'   => 1,
				'templates' => array(),
			);

			return $this->catalog;
		}

		$json = file_get_contents( $this->path );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;

		$this->catalog = is_array( $data ) ? $data : array(
			'version'   => 1,
			'templates' => array(),
		);

		return $this->catalog;
	}
}
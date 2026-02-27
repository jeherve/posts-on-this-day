<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package jeherve/posts-on-this-day
 */

/**
 * Load the composer autoloader.
 */
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Define WordPress constants needed for tests.
 */
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

/**
 * Minimal WP_REST_Request stub for tests.
 *
 * Brain Monkey does not provide WordPress classes,
 * so we define a lightweight stand-in that satisfies
 * method signatures requiring \WP_REST_Request.
 */
if ( ! class_exists( 'WP_REST_Request' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	/**
	 * Minimal WP_REST_Request stub for testing.
	 */
	class WP_REST_Request {
		/**
		 * Request parameters.
		 *
		 * @var array
		 */
		private array $params = array();

		/**
		 * Constructor.
		 *
		 * @param array $params Optional initial parameters.
		 */
		public function __construct( array $params = array() ) {
			$this->params = $params;
		}

		/**
		 * Set a parameter.
		 *
		 * @param string $key   Parameter name.
		 * @param mixed  $value Parameter value.
		 */
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		/**
		 * Get a parameter value.
		 *
		 * @param string $key Parameter name.
		 *
		 * @return mixed|null The parameter value or null.
		 */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}
	}
}

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

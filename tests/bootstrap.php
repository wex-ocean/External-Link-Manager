<?php
/**
 * PHPUnit bootstrap for External Link Manager tests.
 *
 * Loads the WordPress test suite and the plugin itself so
 * WP_UnitTestCase is available for all test files.
 *
 * Usage (from plugin root):
 *   vendor/bin/phpunit --bootstrap tests/bootstrap.php
 *
 * @package ExternalLinkManager
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	// When running via phpunit, ABSPATH won't be defined yet.
	// The WP test bootstrap will define it; we skip the guard here.
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php. " .
		"Set the WP_TESTS_DIR environment variable to point to the WordPress test library.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Load the WordPress test bootstrap.
require_once $_tests_dir . '/includes/bootstrap.php';

// Load the plugin.
require_once dirname( __DIR__ ) . '/external-link-manager.php';

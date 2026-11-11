<?php
/**
 * Loads plugin classes from src/ by PSR-4 mapping.
 *
 * The plugin ships without vendor/, so this does not use the Composer autoloader.
 * Both the main plugin file and uninstall.php load it, because WordPress runs
 * uninstall.php without loading the main plugin file.
 *
 * @package WP_Buyer_Blacklist
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'WP_Buyer_Blacklist\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);

<?php
/**
 * Compares rendered markup with recorded snapshots.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Asserts that markup is byte-identical to a fixture in tests/integration/snapshots.
 */
trait HtmlSnapshots {

	/**
	 * Asserts markup matches its snapshot. Set UPDATE_SNAPSHOTS=1 to record it.
	 *
	 * @param string                $name         Snapshot file name without extension.
	 * @param string                $markup       Rendered markup.
	 * @param array<string, string> $placeholders Per-run values, such as nonces, mapped to stable placeholders.
	 * @return void
	 */
	private function assert_matches_html_snapshot( string $name, string $markup, array $placeholders = array() ): void {
		$markup = strtr( $markup, $placeholders );
		$file   = dirname( __DIR__ ) . '/snapshots/' . $name . '.html';

		if ( getenv( 'UPDATE_SNAPSHOTS' ) ) {
			file_put_contents( $file, $markup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes a local test fixture, not site data.
		}

		$this->assertFileExists( $file, "Snapshot {$name} is missing. Run the suite with UPDATE_SNAPSHOTS=1 to record it." );
		$this->assertSame( file_get_contents( $file ), $markup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local test fixture.
	}
}

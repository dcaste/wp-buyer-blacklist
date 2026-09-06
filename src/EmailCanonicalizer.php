<?php
/**
 * Email canonicalization.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

/**
 * Produces the Canonical value for email entries.
 */
final class EmailCanonicalizer {

	/**
	 * Canonicalizes an email address for matching.
	 *
	 * @param string $email Email address to canonicalize.
	 * @return string
	 */
	public static function canonicalize( string $email ): string {
		return strtolower( trim( $email ) );
	}
}

<?php
/**
 * Email Blacklist entry identity.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

use InvalidArgumentException;

/**
 * Holds the canonical identity used by an email Blacklist entry.
 */
final class EmailEntry {

	/**
	 * Canonical email value.
	 *
	 * @var string
	 */
	private string $canonical_value;

	/**
	 * Creates an email entry identity from a Buyer email address.
	 *
	 * @param string $email Buyer email address.
	 * @return self
	 * @throws InvalidArgumentException When the email is invalid.
	 */
	public static function from_email( string $email ): self {
		$canonical_value = EmailCanonicalizer::canonicalize( $email );

		if ( false === filter_var( $canonical_value, FILTER_VALIDATE_EMAIL ) ) {
			throw new InvalidArgumentException( 'The email address is invalid.' );
		}

		$entry                  = new self();
		$entry->canonical_value = $canonical_value;

		return $entry;
	}

	/**
	 * Gets the Canonical value.
	 *
	 * @return string
	 */
	public function canonical_value(): string {
		return $this->canonical_value;
	}

	/**
	 * Gets the database-safe identity key.
	 *
	 * @return string
	 */
	public function canonical_hash(): string {
		return hash( 'sha256', $this->canonical_value );
	}
}

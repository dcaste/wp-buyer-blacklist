<?php
/**
 * WooCommerce Customer ID Blacklist entry identity.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Entries;

use InvalidArgumentException;

/**
 * Holds the canonical identity used by a Customer-ID Blacklist entry.
 */
final class CustomerIdEntry {

	/**
	 * Canonical Customer ID.
	 *
	 * @var string
	 */
	private string $canonical_value;

	/**
	 * Creates an entry from a positive WooCommerce Customer ID.
	 *
	 * @param int $customer_id WooCommerce Customer ID.
	 * @return self
	 * @throws InvalidArgumentException When the identifier is not positive.
	 */
	public static function from_customer_id( int $customer_id ): self {
		if ( $customer_id <= 0 ) {
			throw new InvalidArgumentException( 'The Customer ID is invalid.' );
		}

		$entry                  = new self();
		$entry->canonical_value = (string) $customer_id;

		return $entry;
	}

	/**
	 * Gets the canonical Customer ID.
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

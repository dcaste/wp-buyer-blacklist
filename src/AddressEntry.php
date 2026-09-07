<?php
/**
 * Address Blacklist entry identity.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

use InvalidArgumentException;

/**
 * Holds the canonical identity used by an address Blacklist entry.
 */
final class AddressEntry {

	/**
	 * Match type.
	 *
	 * @var string
	 */
	private string $match_type;

	/**
	 * Canonical address fields.
	 *
	 * @var array<string, string>
	 */
	private array $canonical_value;

	/**
	 * Creates an address entry from complete Buyer address fields.
	 *
	 * @param string                $match_type Address Match type.
	 * @param array<string, string> $fields     Address fields.
	 * @param array<string, string> $countries  WooCommerce country codes and names.
	 * @return self
	 * @throws InvalidArgumentException When the address is incomplete or invalid.
	 */
	public static function from_fields( string $match_type, array $fields, array $countries ): self {
		if ( ! in_array( $match_type, array( 'billing_address', 'shipping_address' ), true ) ) {
			throw new InvalidArgumentException( 'The address Match type is invalid.' );
		}

		$canonical_value = array(
			'address_line_1' => self::canonicalize_field( $fields['address_line_1'] ?? '' ),
			'address_line_2' => self::canonicalize_field( $fields['address_line_2'] ?? '' ),
			'city'           => self::canonicalize_field( $fields['city'] ?? '' ),
			'state'          => self::canonicalize_field( $fields['state'] ?? '' ),
			'postcode'       => self::canonicalize_field( $fields['postcode'] ?? '' ),
			'country'        => strtoupper( self::canonicalize_field( $fields['country'] ?? '' ) ),
		);

		foreach ( array( 'address_line_1', 'city', 'state', 'postcode', 'country' ) as $required_field ) {
			if ( '' === $canonical_value[ $required_field ] ) {
				throw new InvalidArgumentException( 'The address is incomplete.' );
			}
		}
		if ( ! array_key_exists( $canonical_value['country'], $countries ) ) {
			throw new InvalidArgumentException( 'The country is not recognized by WooCommerce.' );
		}

		$entry                  = new self();
		$entry->match_type      = $match_type;
		$entry->canonical_value = $canonical_value;

		return $entry;
	}

	/**
	 * Gets the Match type.
	 *
	 * @return string
	 */
	public function match_type(): string {
		return $this->match_type;
	}

	/**
	 * Gets canonical address fields.
	 *
	 * @return array<string, string>
	 */
	public function canonical_value(): array {
		return $this->canonical_value;
	}

	/**
	 * Gets the database-safe identity key.
	 *
	 * @return string
	 */
	public function canonical_hash(): string {
		return hash( 'sha256', wp_json_encode( $this->canonical_value ) );
	}

	/**
	 * Normalizes one structured address field.
	 *
	 * @param string $value Field value.
	 * @return string
	 */
	private static function canonicalize_field( string $value ): string {
		return strtolower( (string) preg_replace( '/\s+/', ' ', trim( $value ) ) );
	}
}

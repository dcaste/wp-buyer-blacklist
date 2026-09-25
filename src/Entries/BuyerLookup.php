<?php
/**
 * Current registered Buyer for a Blacklist entry.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Entries;

use InvalidArgumentException;

/**
 * Finds the one registered Buyer a Blacklist entry identifies and reads their current details.
 */
final class BuyerLookup {

	/**
	 * WooCommerce customer address prefix for each address Match type.
	 */
	private const ADDRESS_PREFIXES = array(
		'billing_address'  => 'billing',
		'shipping_address' => 'shipping',
	);

	/**
	 * Finds the registered Buyer an entry identifies.
	 *
	 * @param array<string, mixed> $entry Entry with match_type and its canonical value.
	 * @return \WP_User|false The Buyer, or false when none or several match.
	 */
	public function find( array $entry ): \WP_User|false {
		return match ( $entry['match_type'] ) {
			'customer_id'                         => get_userdata( absint( $entry['value'] ) ),
			'email'                               => get_user_by( 'email', $entry['value'] ),
			'billing_address', 'shipping_address' => $this->only_customer_with_address( $entry['match_type'], (array) $entry['value'] ),
			default                               => false,
		};
	}

	/**
	 * Reads a registered Buyer's current name, email and addresses.
	 *
	 * @param \WP_User $user Registered Buyer.
	 * @return array<string, mixed> Buyer details.
	 */
	public function details( \WP_User $user ): array {
		$customer = new \WC_Customer( $user->ID );
		return array(
			'id'               => $user->ID,
			'name'             => trim( $user->first_name . ' ' . $user->last_name ),
			'email'            => $user->user_email,
			'billing_address'  => $this->address( $customer, 'billing_address' ),
			'shipping_address' => $this->address( $customer, 'shipping_address' ),
		);
	}

	/**
	 * Finds the one Customer whose current address of the entry's type matches it.
	 *
	 * @param string                $match_type      Address Match type.
	 * @param array<string, string> $canonical_value Entry address, already canonical.
	 * @return \WP_User|false The Customer, or false when none or several match.
	 */
	private function only_customer_with_address( string $match_type, array $canonical_value ): \WP_User|false {
		$countries = WC()->countries->get_countries();
		$matches   = array();
		// Postcode narrows the candidates. The database collation ignores its case; every field is compared canonically below.
		$candidates = get_users(
			array(
				'meta_key'   => self::ADDRESS_PREFIXES[ $match_type ] . '_postcode', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only runs when an operator opens one address entry.
				'meta_value' => $canonical_value['postcode'] ?? '', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Only runs when an operator opens one address entry.
			)
		);
		foreach ( $candidates as $candidate ) {
			try {
				$address = AddressEntry::from_fields( $match_type, $this->address( new \WC_Customer( $candidate->ID ), $match_type ), $countries );
			} catch ( InvalidArgumentException $exception ) {
				continue;
			}
			if ( $address->canonical_value() === $canonical_value ) {
				$matches[] = $candidate;
			}
		}

		return 1 === count( $matches ) ? $matches[0] : false;
	}

	/**
	 * Reads a customer's current address for an address Match type.
	 *
	 * @param \WC_Customer $customer   Customer.
	 * @param string       $match_type Address Match type.
	 * @return array<string, string> Address fields.
	 */
	private function address( \WC_Customer $customer, string $match_type ): array {
		$prefix = self::ADDRESS_PREFIXES[ $match_type ];
		return array(
			'address_line_1' => $customer->{"get_{$prefix}_address_1"}(),
			'address_line_2' => $customer->{"get_{$prefix}_address_2"}(),
			'city'           => $customer->{"get_{$prefix}_city"}(),
			'state'          => $customer->{"get_{$prefix}_state"}(),
			'postcode'       => $customer->{"get_{$prefix}_postcode"}(),
			'country'        => $customer->{"get_{$prefix}_country"}(),
		);
	}
}

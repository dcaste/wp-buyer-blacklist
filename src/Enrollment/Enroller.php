<?php
/**
 * Creates Blacklist entries from Customer and Order identities.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enrollment;

use InvalidArgumentException;
use WP_Buyer_Blacklist\EmailEntryRepository;
use WP_Buyer_Blacklist\Entries\AddressEntry;
use WP_Buyer_Blacklist\Entries\CustomerIdEntry;
use WP_Buyer_Blacklist\Entries\EmailEntry;

/**
 * Snapshots one identity from a WooCommerce Customer or Order into a Blacklist entry.
 */
final class Enroller {

	/**
	 * Entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Creates the enroller.
	 *
	 * @param EmailEntryRepository $entries Entry storage.
	 */
	public function __construct( EmailEntryRepository $entries ) {
		$this->entries = $entries;
	}

	/**
	 * Creates one immutable identity snapshot.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source identifier.
	 * @param string $match_type  Match type.
	 * @param string $reason      Operator reason.
	 * @return string Result status.
	 */
	public function create_entry( string $source_type, int $source_id, string $match_type, string $reason ): string {
		if ( '' === trim( $reason ) || ! in_array( $match_type, array( 'email', 'customer_id', 'billing_address', 'shipping_address' ), true ) ) {
			return 'unavailable';
		}
		$source = 'customer' === $source_type ? new \WC_Customer( $source_id ) : wc_get_order( $source_id );
		if ( ! $source instanceof \WC_Customer && ! $source instanceof \WC_Order ) {
			return 'unavailable';
		}
		try {
			if ( 'email' === $match_type ) {
				$entry = EmailEntry::from_email( $source instanceof \WC_Order ? $source->get_billing_email() : $source->get_email() );
				return $this->entries->create( $entry, $reason ) ? 'created' : 'duplicate';
			}
			if ( 'customer_id' === $match_type ) {
				$customer_id = $source instanceof \WC_Order ? $source->get_customer_id() : $source->get_id();
				return $this->entries->create_customer_id( CustomerIdEntry::from_customer_id( $customer_id ), $reason ) ? 'created' : 'duplicate';
			}
			$fields = $this->source_address( $source, $match_type );
			return $this->entries->create_address( AddressEntry::from_fields( $match_type, $fields, WC()->countries->get_countries() ), $reason ) ? 'created' : 'duplicate';
		} catch ( InvalidArgumentException $exception ) {
			return 'unavailable';
		}
	}

	/**
	 * Gets one source address in Blacklist field names.
	 *
	 * @param \WC_Customer|\WC_Order $source     Customer or Order source.
	 * @param string                 $match_type Address Match type.
	 * @return array<string, string>
	 */
	public function source_address( \WC_Customer|\WC_Order $source, string $match_type ): array {
		return array(
			'address_line_1' => 'billing_address' === $match_type ? $source->get_billing_address_1() : $source->get_shipping_address_1(),
			'address_line_2' => 'billing_address' === $match_type ? $source->get_billing_address_2() : $source->get_shipping_address_2(),
			'city'           => 'billing_address' === $match_type ? $source->get_billing_city() : $source->get_shipping_city(),
			'state'          => 'billing_address' === $match_type ? $source->get_billing_state() : $source->get_shipping_state(),
			'postcode'       => 'billing_address' === $match_type ? $source->get_billing_postcode() : $source->get_shipping_postcode(),
			'country'        => 'billing_address' === $match_type ? $source->get_billing_country() : $source->get_shipping_country(),
		);
	}

	/**
	 * Formats an Address for display.
	 *
	 * @param array<string, string> $address Address fields.
	 * @return string
	 */
	public function format_address( array $address ): string {
		return implode( ', ', array_filter( $address ) );
	}

	/**
	 * Checks whether an email can create an entry.
	 *
	 * @param string $email Buyer email address.
	 * @return bool
	 */
	public function is_valid_email( string $email ): bool {
		try {
			EmailEntry::from_email( $email );
			return true;
		} catch ( InvalidArgumentException $exception ) {
			return false;
		}
	}

	/**
	 * Checks whether an Address can create an entry.
	 *
	 * @param string                $match_type Address Match type.
	 * @param array<string, string> $address   Address fields.
	 * @return bool
	 */
	public function is_valid_address( string $match_type, array $address ): bool {
		try {
			AddressEntry::from_fields( $match_type, $address, WC()->countries->get_countries() );
			return true;
		} catch ( InvalidArgumentException $exception ) {
			return false;
		}
	}

	/**
	 * Finds active entries that match a WooCommerce Customer.
	 *
	 * @param int $customer_id WooCommerce Customer ID.
	 * @return list<array{id: string, is_active: string}> Matching entries.
	 */
	public function matching_customer_entries( int $customer_id ): array {
		$customer   = new \WC_Customer( $customer_id );
		$entries    = $this->entries->matching_entries( 'customer_id', hash( 'sha256', (string) $customer_id ) );
		$email_hash = '';

		try {
			$email_hash = EmailEntry::from_email( $customer->get_email() )->canonical_hash();
		} catch ( InvalidArgumentException $exception ) {
			$email_hash = '';
		}

		if ( '' !== $email_hash ) {
			$entries = array_merge( $entries, $this->entries->matching_entries( 'email', $email_hash ) );
		}

		foreach ( array( 'billing_address', 'shipping_address' ) as $match_type ) {
			$fields = $this->source_address( $customer, $match_type );
			try {
				$address = AddressEntry::from_fields( $match_type, $fields, WC()->countries->get_countries() );
				$entries = array_merge( $entries, $this->entries->matching_entries( $match_type, $address->canonical_hash() ) );
			} catch ( InvalidArgumentException $exception ) {
				continue;
			}
		}

		return $entries;
	}
}

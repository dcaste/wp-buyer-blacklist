<?php
/**
 * CSV Blacklist import validation.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

use InvalidArgumentException;

/**
 * Validates supported CSV rows before they are imported.
 */
final class CsvImport {

	/**
	 * Validates CSV content and identifies duplicate identities within it.
	 *
	 * @param string                $csv       CSV content.
	 * @param array<string, string> $countries WooCommerce country codes and names.
	 * @return array{accepted: list<array<string, mixed>>, duplicates: list<array{row: int, reason: string}>, rejected: list<array{row: int, reason: string}>}
	 * @throws InvalidArgumentException When the CSV is not valid UTF-8 or has no supported header.
	 */
	public static function validate( string $csv, array $countries ): array {
		if ( '' === $csv || 1 !== preg_match( '//u', $csv ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The REST response serializes this translated validation message.
			throw new InvalidArgumentException( __( 'The file must be a UTF-8 CSV file with a header row.', 'wp-buyer-blacklist' ) );
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- PHP's in-memory stream is required by fgetcsv and does not access the filesystem.
		$stream = fopen( 'php://temp', 'r+' );
		fwrite( $stream, $csv );
		rewind( $stream );
		$header = fgetcsv( $stream );
		if ( false === $header ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The REST response serializes this translated validation message.
			throw new InvalidArgumentException( __( 'The file must be a UTF-8 CSV file with a header row.', 'wp-buyer-blacklist' ) );
		}

		$headers = self::headers( $header );
		if ( ! isset( $headers['match_type'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The REST response serializes this translated validation message.
			throw new InvalidArgumentException( __( 'The CSV header must include match_type.', 'wp-buyer-blacklist' ) );
		}

		$result = array(
			'accepted'   => array(),
			'duplicates' => array(),
			'rejected'   => array(),
		);
		$seen   = array();
		$row    = 1;
		while ( true ) {
			$values = fgetcsv( $stream );
			if ( false === $values ) {
				break;
			}
			++$row;
			if ( self::is_empty_row( $values ) ) {
				continue;
			}
			if ( count( $values ) > count( $header ) ) {
				$result['rejected'][] = array(
					'row'    => $row,
					'reason' => __( 'The row has more fields than its header.', 'wp-buyer-blacklist' ),
				);
				continue;
			}

			$fields = self::fields( $headers, $values );
			try {
				$entry = self::entry( $fields, $countries );
			} catch ( InvalidArgumentException $exception ) {
				$result['rejected'][] = array(
					'row'    => $row,
					'reason' => $exception->getMessage(),
				);
				continue;
			}

			try {
				$entry['is_active'] = self::is_active( $fields['status'] );
			} catch ( InvalidArgumentException $exception ) {
				$result['rejected'][] = array(
					'row'    => $row,
					'reason' => $exception->getMessage(),
				);
				continue;
			}
			$key = $entry['match_type'] . ':' . $entry['canonical_hash'];
			if ( isset( $seen[ $key ] ) ) {
				$result['duplicates'][] = array(
					'row'    => $row,
					'reason' => __( 'Duplicate entry.', 'wp-buyer-blacklist' ),
				);
				continue;
			}
			$seen[ $key ]         = true;
			$entry['row']         = $row;
			$entry['reason']      = '' === trim( $fields['reason'] ) ? __( 'Imported blacklist entry', 'wp-buyer-blacklist' ) : $fields['reason'];
			$result['accepted'][] = $entry;
		}
		fclose( $stream );
		// phpcs:enable

		return $result;
	}

	/**
	 * Maps supported header names to positions.
	 *
	 * @param array<int, string|null> $header CSV header fields.
	 * @return array<string, int>
	 */
	private static function headers( array $header ): array {
		$headers = array();
		foreach ( $header as $index => $name ) {
			$name = trim( (string) $name );
			if ( 0 === $index ) {
				$name = preg_replace( '/^\xEF\xBB\xBF/', '', $name );
			}
			if ( in_array( $name, array( 'match_type', 'email', 'customer_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'reason', 'status' ), true ) && ! isset( $headers[ $name ] ) ) {
				$headers[ $name ] = $index;
			}
		}

		return $headers;
	}

	/**
	 * Checks whether a CSV row has no content.
	 *
	 * @param array<int, string|null> $values CSV row fields.
	 * @return bool Whether the row is empty.
	 */
	private static function is_empty_row( array $values ): bool {
		foreach ( $values as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Maps row values to supported fields.
	 *
	 * @param array<string, int>      $headers Header positions.
	 * @param array<int, string|null> $values  CSV row fields.
	 * @return array<string, string>
	 */
	private static function fields( array $headers, array $values ): array {
		$fields = array();
		foreach ( array( 'match_type', 'email', 'customer_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'reason', 'status' ) as $field ) {
			$fields[ $field ] = isset( $headers[ $field ], $values[ $headers[ $field ] ] ) ? (string) $values[ $headers[ $field ] ] : '';
		}

		return $fields;
	}

	/**
	 * Converts an optional CSV status to an active flag.
	 *
	 * @param string $status CSV status.
	 * @return bool Whether the entry is active.
	 * @throws InvalidArgumentException When the status is unsupported.
	 */
	private static function is_active( string $status ): bool {
		$status = trim( $status );
		if ( '' === $status || 'active' === $status ) {
			return true;
		}
		if ( 'inactive' === $status ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The REST response serializes this translated validation message.
		throw new InvalidArgumentException( __( 'Choose a valid status.', 'wp-buyer-blacklist' ) );
	}

	/**
	 * Builds one validated entry representation.
	 *
	 * @param array<string, string> $fields    CSV fields.
	 * @param array<string, string> $countries WooCommerce country codes and names.
	 * @return array{match_type: string, value: string|array<string, string>, canonical_hash: string}
	 * @throws InvalidArgumentException When the fields do not identify a valid entry.
	 */
	private static function entry( array $fields, array $countries ): array {
		$match_type = trim( $fields['match_type'] );
		if ( 'email' === $match_type ) {
			try {
				$entry = EmailEntry::from_email( $fields['email'] );
			} catch ( InvalidArgumentException $exception ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The REST response serializes this translated validation message.
				throw new InvalidArgumentException( __( 'Enter a valid email address.', 'wp-buyer-blacklist' ) );
			}
			return array(
				'match_type'     => 'email',
				'value'          => $entry->canonical_value(),
				'canonical_hash' => $entry->canonical_hash(),
			);
		}
		if ( 'customer_id' === $match_type ) {
			$customer_id = trim( $fields['customer_id'] );
			if ( ! ctype_digit( $customer_id ) || 0 === (int) $customer_id ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The REST response serializes this translated validation message.
				throw new InvalidArgumentException( __( 'Enter a positive Customer ID.', 'wp-buyer-blacklist' ) );
			}
			$entry = CustomerIdEntry::from_customer_id( (int) $customer_id );
			return array(
				'match_type'     => 'customer_id',
				'value'          => $entry->canonical_value(),
				'canonical_hash' => $entry->canonical_hash(),
			);
		}
		if ( ! in_array( $match_type, array( 'billing_address', 'shipping_address' ), true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The REST response serializes this translated validation message.
			throw new InvalidArgumentException( __( 'Choose a valid Match type.', 'wp-buyer-blacklist' ) );
		}
		try {
			$entry = AddressEntry::from_fields( $match_type, $fields, $countries );
		} catch ( InvalidArgumentException $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The REST response serializes this translated validation message.
			throw new InvalidArgumentException( __( 'Enter a complete address with a valid country.', 'wp-buyer-blacklist' ) );
		}
		return array(
			'match_type'     => $entry->match_type(),
			'value'          => $entry->canonical_value(),
			'canonical_hash' => $entry->canonical_hash(),
		);
	}
}

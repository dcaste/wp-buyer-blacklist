<?php
/**
 * WordPress personal data exporter and eraser.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Privacy;

use WP_Buyer_Blacklist\Entries\EntryRepository;

/**
 * Exports and erases email identities through the WordPress privacy tools.
 */
final class PersonalDataTools {

	/**
	 * Entry storage.
	 *
	 * @var EntryRepository
	 */
	private EntryRepository $entries;

	/**
	 * Identity dossiers.
	 *
	 * @var DossierBuilder
	 */
	private DossierBuilder $dossiers;

	/**
	 * Creates the privacy tools.
	 *
	 * @param EntryRepository $entries  Entry storage.
	 * @param DossierBuilder  $dossiers Identity dossiers.
	 */
	public function __construct( EntryRepository $entries, DossierBuilder $dossiers ) {
		$this->entries  = $entries;
		$this->dossiers = $dossiers;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_personal_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_personal_data_eraser' ) );
	}

	/**
	 * Registers the WordPress personal-data exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Personal-data exporters.
	 * @return array<string, array<string, mixed>> Personal-data exporters.
	 */
	public function register_personal_data_exporter( array $exporters ): array {
		$exporters['wp-buyer-blacklist'] = array(
			'exporter_friendly_name' => __( 'Buyer Blacklist', 'wp-buyer-blacklist' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Exports data for email identities through WordPress privacy tools.
	 *
	 * @param string $email_address Buyer email address.
	 * @param int    $page          Export page.
	 * @return array{data: list<array<string, mixed>>, done: bool} Personal data.
	 */
	public function export_personal_data( string $email_address, int $page ): array {
		unset( $page );
		$data = array();
		foreach ( $this->dossiers->identity_dossiers_for_email( $email_address ) as $dossier ) {
			$entry  = $dossier['entry'];
			$data[] = array(
				'group_id'    => 'wp-buyer-blacklist',
				'group_label' => __( 'Buyer Blacklist', 'wp-buyer-blacklist' ),
				'item_id'     => 'entry-' . $entry['id'],
				'data'        => array(
					array(
						'name'  => __( 'Email', 'wp-buyer-blacklist' ),
						'value' => $entry['value'],
					),
					array(
						'name'  => __( 'Reason', 'wp-buyer-blacklist' ),
						'value' => $entry['reason'],
					),
					array(
						'name'  => __( 'Blocked attempts', 'wp-buyer-blacklist' ),
						'value' => implode( "\n", array_map( fn( $attempt ) => $attempt['outcome'] . ' at ' . $attempt['created_at'], $dossier['blocked_attempts'] ) ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Registers the WordPress personal-data eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Personal-data erasers.
	 * @return array<string, array<string, mixed>> Personal-data erasers.
	 */
	public function register_personal_data_eraser( array $erasers ): array {
		$erasers['wp-buyer-blacklist'] = array(
			'eraser_friendly_name' => __( 'Buyer Blacklist', 'wp-buyer-blacklist' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Erases data for email identities through WordPress privacy tools.
	 *
	 * @param string $email_address Buyer email address.
	 * @param int    $page          Erasure page.
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool} Erasure result.
	 */
	public function erase_personal_data( string $email_address, int $page ): array {
		unset( $page );
		$removed = false;
		foreach ( $this->dossiers->identity_dossiers_for_email( $email_address ) as $dossier ) {
			$removed = $this->entries->erase( (int) $dossier['entry']['id'] ) || $removed;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}

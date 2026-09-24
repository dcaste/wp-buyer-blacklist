<?php
/**
 * Plugin bootstrap.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

use WP_Buyer_Blacklist\Access\OperatorAccess;
use WP_Buyer_Blacklist\Admin\AdminPage;
use WP_Buyer_Blacklist\Enforcement\AddressCheckoutBlocker;
use WP_Buyer_Blacklist\Enforcement\CheckoutEnforcement;
use WP_Buyer_Blacklist\Enforcement\CustomerIdCheckoutBlocker;
use WP_Buyer_Blacklist\Enforcement\EmailCheckoutBlocker;
use WP_Buyer_Blacklist\Entries\EntriesController;
use WP_Buyer_Blacklist\ImportExport\CsvExportDownload;
use WP_Buyer_Blacklist\ImportExport\ImportExportController;
use WP_Buyer_Blacklist\Settings\BlacklistSettings;
use WP_Buyer_Blacklist\Settings\BuyerBlockMessage;
use WP_Buyer_Blacklist\Settings\SettingsTab;
use WP_Error;

/**
 * Builds the plugin modules and registers Blacklist administration.
 */
final class Plugin {

	/**
	 * REST namespace shared by every controller.
	 */
	public const REST_NAMESPACE = 'wp-buyer-blacklist/v1';

	/**
	 * Email entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Checkout enforcement.
	 *
	 * @var CheckoutEnforcement
	 */
	private CheckoutEnforcement $checkout_enforcement;

	/**
	 * Plugin settings.
	 *
	 * @var BlacklistSettings
	 */
	private BlacklistSettings $settings;

	/**
	 * Blacklist entry REST routes.
	 *
	 * @var EntriesController
	 */
	private EntriesController $entries_controller;

	/**
	 * CSV import REST routes.
	 *
	 * @var ImportExportController
	 */
	private ImportExportController $import_export_controller;

	/**
	 * Full Blacklist CSV download.
	 *
	 * @var CsvExportDownload
	 */
	private CsvExportDownload $csv_export_download;

	/**
	 * Buyer Blacklist admin page.
	 *
	 * @var AdminPage
	 */
	private AdminPage $admin_page;

	/**
	 * Customer and Order enrollment workflow.
	 *
	 * @var SourceEnrollment
	 */
	private SourceEnrollment $source_enrollment;

	/**
	 * Blacklist operator access policy.
	 *
	 * @var OperatorAccess
	 */
	private OperatorAccess $operator_access;

	/**
	 * Creates the plugin.
	 *
	 * @param EmailEntryRepository $entries Email entry storage.
	 */
	public function __construct( EmailEntryRepository $entries ) {
		$this->entries                  = $entries;
		$buyer_block_message            = new BuyerBlockMessage();
		$email_blocker                  = new EmailCheckoutBlocker( $entries, $entries );
		$customer_id_blocker            = new CustomerIdCheckoutBlocker( $entries );
		$address_blocker                = new AddressCheckoutBlocker( $entries );
		$this->checkout_enforcement     = new CheckoutEnforcement( $email_blocker, $customer_id_blocker, $address_blocker, $buyer_block_message );
		$this->settings                 = new BlacklistSettings( $buyer_block_message );
		$this->operator_access          = new OperatorAccess();
		$this->source_enrollment        = new SourceEnrollment( $entries, $this->operator_access );
		$this->entries_controller       = new EntriesController( $entries, $this->operator_access );
		$this->import_export_controller = new ImportExportController( $entries, $this->operator_access );
		$this->csv_export_download      = new CsvExportDownload( $entries );
		$this->admin_page               = new AdminPage( $this->operator_access, new SettingsTab( $buyer_block_message ) );
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_post_wp_buyer_blacklist_dossier_export', array( $this, 'download_identity_dossier' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_personal_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_personal_data_eraser' ) );
		$this->entries_controller->register();
		$this->import_export_controller->register();
		$this->csv_export_download->register();
		$this->admin_page->register();
		$this->settings->register();
		$this->checkout_enforcement->register();
		$this->source_enrollment->register();
	}

	/** Registers the private entry API. @return void */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/email-entries/(?P<id>\\d+)/dossier',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_identity_dossier' ),
				'permission_callback' => array( $this, 'can_manage_privacy_data' ),
			)
		);
	}

	/**
	 * Checks the privacy administration capability.
	 *
	 * @return bool
	 */
	public function can_manage_privacy_data(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Sends one identity dossier as JSON to an administrator.
	 *
	 * @return void
	 */
	public function download_identity_dossier(): void {
		if ( ! $this->can_manage_privacy_data() ) {
			wp_die( esc_html__( 'You are not allowed to export Blacklist data.', 'wp-buyer-blacklist' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- identity_dossier_for_download() verifies this request nonce.
		$entry_id = isset( $_GET['entry_id'] ) ? absint( wp_unslash( $_GET['entry_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- identity_dossier_for_download() verifies this request nonce.
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$dossier = $this->identity_dossier_for_download( $entry_id, $nonce );
		if ( is_wp_error( $dossier ) ) {
			wp_die( esc_html( $dossier->get_error_message() ), (int) $dossier->get_error_data()['status'] );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="buyer-blacklist-dossier-' . $entry_id . '.json"' );
		echo wp_json_encode( $dossier, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() encodes every dossier value.
		exit;
	}

	/**
	 * Gets an identity dossier for an administrator download.
	 *
	 * @param int    $entry_id Blacklist entry ID.
	 * @param string $nonce    Admin action nonce.
	 * @return array<string, mixed>|WP_Error Identity dossier or download error.
	 */
	public function identity_dossier_for_download( int $entry_id, string $nonce ): array|WP_Error {
		if ( ! $this->can_manage_privacy_data() ) {
			return new WP_Error( 'wp_buyer_blacklist_forbidden', __( 'You are not allowed to export Blacklist data.', 'wp-buyer-blacklist' ), array( 'status' => 403 ) );
		}
		if ( ! wp_verify_nonce( $nonce, 'wp_buyer_blacklist_dossier_export' ) ) {
			return new WP_Error( 'wp_buyer_blacklist_invalid_nonce', __( 'The download link has expired. Refresh the page and try again.', 'wp-buyer-blacklist' ), array( 'status' => 403 ) );
		}

		$dossier = $this->entries->identity_dossier( $entry_id );
		if ( null === $dossier ) {
			return new WP_Error( 'wp_buyer_blacklist_not_found', __( 'The Blacklist entry could not be found.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return $dossier;
	}

	/**
	 * Gets an identity dossier as structured JSON.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_identity_dossier( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$dossier = $this->entries->identity_dossier( absint( $request['id'] ) );
		if ( null === $dossier ) {
			return new \WP_Error( 'wp_buyer_blacklist_not_found', __( 'The Blacklist entry could not be found.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return new \WP_REST_Response( $dossier );
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
		foreach ( $this->entries->identity_dossiers_for_email( $email_address ) as $dossier ) {
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
		foreach ( $this->entries->identity_dossiers_for_email( $email_address ) as $dossier ) {
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

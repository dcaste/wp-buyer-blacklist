<?php
/**
 * Blacklist entry REST routes.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Entries;

use InvalidArgumentException;
use WP_Buyer_Blacklist\Access\OperatorAccess;
use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Plugin;
use WP_Buyer_Blacklist\Privacy\DossierBuilder;
use WP_Buyer_Blacklist\Privacy\PrivacyDataAccess;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Lists, creates, updates and deletes Blacklist entries for the admin app.
 */
final class EntriesController {

	/**
	 * Entry storage.
	 *
	 * @var EntryRepository
	 */
	private EntryRepository $entries;

	/**
	 * Audit events of each entry.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit_log;

	/**
	 * Blocked attempts of each entry.
	 *
	 * @var BlockedAttemptLog
	 */
	private BlockedAttemptLog $blocked_attempts;

	/**
	 * Identity dossiers, used to find the current Buyer.
	 *
	 * @var DossierBuilder
	 */
	private DossierBuilder $dossiers;

	/**
	 * Blacklist operator access policy.
	 *
	 * @var OperatorAccess
	 */
	private OperatorAccess $operator_access;

	/**
	 * Privacy data access check.
	 *
	 * @var PrivacyDataAccess
	 */
	private PrivacyDataAccess $privacy_access;

	/**
	 * Finds the registered Buyer an entry identifies.
	 *
	 * @var BuyerLookup
	 */
	private BuyerLookup $buyers;

	/**
	 * Creates the controller.
	 *
	 * @param EntryRepository   $entries          Entry storage.
	 * @param AuditLog          $audit_log        Audit events of each entry.
	 * @param BlockedAttemptLog $blocked_attempts Blocked attempts of each entry.
	 * @param DossierBuilder    $dossiers         Identity dossiers, used to find the current Buyer.
	 * @param OperatorAccess    $operator_access  Blacklist operator access policy.
	 * @param PrivacyDataAccess $privacy_access   Privacy data access check.
	 * @param BuyerLookup       $buyers           Current Buyer for an entry.
	 */
	public function __construct( EntryRepository $entries, AuditLog $audit_log, BlockedAttemptLog $blocked_attempts, DossierBuilder $dossiers, OperatorAccess $operator_access, PrivacyDataAccess $privacy_access, BuyerLookup $buyers ) {
		$this->entries          = $entries;
		$this->audit_log        = $audit_log;
		$this->blocked_attempts = $blocked_attempts;
		$this->dossiers         = $dossiers;
		$this->operator_access  = $operator_access;
		$this->privacy_access   = $privacy_access;
		$this->buyers           = $buyers;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the entry routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/entry-summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_entry_summary' ),
				'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
			)
		);
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/email-entries',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_email_entries' ),
					'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_email_entry' ),
					'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
					'args'                => array(
						'email'  => array(
							'type'     => 'string',
							'required' => true,
						),
						'reason' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/address-entries',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_address_entry' ),
					'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
					'args'                => array(
						'match_type'     => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array( 'billing_address', 'shipping_address' ),
						),
						'address_line_1' => array( 'type' => 'string' ),
						'address_line_2' => array( 'type' => 'string' ),
						'city'           => array( 'type' => 'string' ),
						'state'          => array( 'type' => 'string' ),
						'postcode'       => array( 'type' => 'string' ),
						'country'        => array( 'type' => 'string' ),
						'reason'         => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/email-entries/(?P<id>\\d+)/blocked-attempts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_email_entry_blocked_attempts' ),
				'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
				'args'                => $this->id_args(),
			)
		);
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/email-entries/(?P<id>\\d+)/buyer-context',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_buyer_context' ),
				'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
				'args'                => $this->id_args(),
			)
		);
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/email-entries/(?P<id>\\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_email_entry_audit_events' ),
					'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
					'args'                => $this->id_args(),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_email_entry' ),
					'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
					'args'                => $this->id_args() + array(
						'reason'    => array( 'type' => 'string' ),
						'is_active' => array( 'type' => 'boolean' ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_email_entry' ),
					'permission_callback' => array( $this->privacy_access, 'can_manage_privacy_data' ),
					'args'                => $this->id_args() + array(
						'confirm' => array( 'type' => 'boolean' ),
					),
				),
			)
		);
	}

	/**
	 * Declares the entry ID taken from the route path.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function id_args(): array {
		return array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);
	}

	/**
	 * Gets email entries for the admin application.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_email_entries(): array {
		return $this->entries->all();
	}

	/**
	 * Gets totals for the Manage entries summary cards.
	 *
	 * @return array{total_entries: int, active_entries: int, inactive_entries: int, blocked_attempts: int}
	 */
	public function get_entry_summary(): array {
		return $this->entries->summary();
	}

	/**
	 * Creates an email entry through the REST API.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_email_entry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$reason = $this->reason_from_request( $request );
		if ( '' === trim( $reason ) ) {
			return $this->reason_required_error();
		}
		try {
			$entry = EmailEntry::from_email( (string) $request->get_param( 'email' ) );
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error( 'wp_buyer_blacklist_invalid_email', __( 'Enter a valid email address.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( ! $this->entries->create( $entry, $reason ) ) {
			return new WP_Error( 'wp_buyer_blacklist_duplicate', __( 'That email already has a Blacklist entry.', 'wp-buyer-blacklist' ), array( 'status' => 409 ) );
		}
		return new WP_REST_Response( array( 'message' => __( 'Email entry created.', 'wp-buyer-blacklist' ) ), 201 );
	}

	/**
	 * Creates an address entry through the REST API.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_address_entry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$reason = $this->reason_from_request( $request );
		if ( '' === trim( $reason ) ) {
			return $this->reason_required_error();
		}

		$fields = array();
		foreach ( array( 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country' ) as $field ) {
			$fields[ $field ] = (string) $request->get_param( $field );
		}

		try {
			$entry = AddressEntry::from_fields( (string) $request->get_param( 'match_type' ), $fields, WC()->countries->get_countries() );
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error( 'wp_buyer_blacklist_invalid_address', __( 'Enter a complete address with a valid country.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( ! $this->entries->create_address( $entry, $reason ) ) {
			return new WP_Error( 'wp_buyer_blacklist_duplicate', __( 'That address already has a Blacklist entry.', 'wp-buyer-blacklist' ), array( 'status' => 409 ) );
		}

		return new WP_REST_Response( array( 'message' => __( 'Address entry created.', 'wp-buyer-blacklist' ) ), 201 );
	}

	/**
	 * Gets an entry's Audit events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return list<array<string, mixed>>
	 */
	public function get_email_entry_audit_events( WP_REST_Request $request ): array {
		return $this->audit_log->audit_events( absint( $request['id'] ) );
	}

	/**
	 * Gets an entry's Blocked attempts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return list<array<string, string>>
	 */
	public function get_email_entry_blocked_attempts( WP_REST_Request $request ): array {
		return $this->blocked_attempts->blocked_attempts( absint( $request['id'] ) );
	}

	/**
	 * Gets current Buyer data when an entry identifies one registered Buyer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error Buyer context or not-found error.
	 */
	public function get_buyer_context( WP_REST_Request $request ): array|WP_Error {
		$dossier = $this->dossiers->identity_dossier( absint( $request['id'] ) );
		if ( null === $dossier ) {
			return new WP_Error( 'wp_buyer_blacklist_not_found', __( 'The Blacklist entry could not be found.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		$user = $this->buyers->find( $dossier['entry'] );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'buyer'   => null,
				'message' => __( 'No uniquely identified registered Buyer.', 'wp-buyer-blacklist' ),
			);
		}

		return array( 'buyer' => $this->buyers->details( $user ) );
	}

	/**
	 * Updates an entry reason or status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_email_entry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$entry_id = absint( $request['id'] );
		if ( $this->has_identity_update( $request ) ) {
			return new WP_Error( 'wp_buyer_blacklist_identity_immutable', __( 'A Blacklist entry identity cannot be changed. Deactivate it and create a replacement entry instead.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( null !== $request->get_param( 'reason' ) && null !== $request->get_param( 'is_active' ) ) {
			return new WP_Error( 'wp_buyer_blacklist_multiple_updates', __( 'Update either the reason or status in one request.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( null !== $request->get_param( 'reason' ) ) {
			$reason = $this->reason_from_request( $request );
			if ( '' === trim( $reason ) ) {
				return $this->reason_required_error();
			}
			$updated = $this->entries->update_reason( $entry_id, $reason );
		} elseif ( null !== $request->get_param( 'is_active' ) ) {
			$updated = $this->entries->set_active( $entry_id, (bool) $request->get_param( 'is_active' ) );
		} else {
			return new WP_Error( 'wp_buyer_blacklist_missing_update', __( 'Provide a reason or status to update.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}

		if ( ! $updated ) {
			return new WP_Error( 'wp_buyer_blacklist_update_failed', __( 'The Blacklist entry could not be updated.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'message' => __( 'Blacklist entry updated.', 'wp-buyer-blacklist' ) ) );
	}

	/**
	 * Checks whether a request attempts to change an immutable identity.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool Whether the request contains an identity field.
	 */
	private function has_identity_update( WP_REST_Request $request ): bool {
		foreach ( array( 'match_type', 'value', 'email', 'customer_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'canonical_value', 'canonical_hash' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Deletes an entry after confirmation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_email_entry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( true !== $request->get_param( 'confirm' ) ) {
			return new WP_Error( 'wp_buyer_blacklist_delete_not_confirmed', __( 'Confirm deletion before removing this Blacklist entry.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( ! $this->entries->delete( absint( $request['id'] ) ) ) {
			return new WP_Error( 'wp_buyer_blacklist_delete_failed', __( 'The Blacklist entry could not be deleted.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'message' => __( 'Blacklist entry deleted.', 'wp-buyer-blacklist' ) ) );
	}

	/**
	 * Builds the error for an entry reason that is empty.
	 *
	 * @return WP_Error
	 */
	private function reason_required_error(): WP_Error {
		return new WP_Error( 'wp_buyer_blacklist_reason_required', __( 'Enter a reason for this Blacklist entry.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
	}

	/**
	 * Gets the sanitized reason from a REST request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function reason_from_request( WP_REST_Request $request ): string {
		return sanitize_textarea_field( (string) $request->get_param( 'reason' ) );
	}
}

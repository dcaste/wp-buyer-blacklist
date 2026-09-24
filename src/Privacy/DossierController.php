<?php
/**
 * Identity dossier REST route.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Privacy;

use WP_Buyer_Blacklist\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Returns an identity dossier as structured JSON to an administrator.
 */
final class DossierController {

	/**
	 * Identity dossiers.
	 *
	 * @var DossierBuilder
	 */
	private DossierBuilder $dossiers;

	/**
	 * Privacy data access check.
	 *
	 * @var PrivacyDataAccess
	 */
	private PrivacyDataAccess $privacy_access;

	/**
	 * Creates the controller.
	 *
	 * @param DossierBuilder    $dossiers       Identity dossiers.
	 * @param PrivacyDataAccess $privacy_access Privacy data access check.
	 */
	public function __construct( DossierBuilder $dossiers, PrivacyDataAccess $privacy_access ) {
		$this->dossiers       = $dossiers;
		$this->privacy_access = $privacy_access;
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
	 * Registers the dossier route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/email-entries/(?P<id>\\d+)/dossier',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_identity_dossier' ),
				'permission_callback' => array( $this->privacy_access, 'can_manage_privacy_data' ),
			)
		);
	}

	/**
	 * Gets an identity dossier as structured JSON.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_identity_dossier( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$dossier = $this->dossiers->identity_dossier( absint( $request['id'] ) );
		if ( null === $dossier ) {
			return new WP_Error( 'wp_buyer_blacklist_not_found', __( 'The Blacklist entry could not be found.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $dossier );
	}
}

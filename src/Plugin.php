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
use WP_Buyer_Blacklist\Privacy\DossierController;
use WP_Buyer_Blacklist\Privacy\DossierDownload;
use WP_Buyer_Blacklist\Privacy\PersonalDataTools;
use WP_Buyer_Blacklist\Privacy\PrivacyDataAccess;
use WP_Buyer_Blacklist\Settings\BlacklistSettings;
use WP_Buyer_Blacklist\Settings\BuyerBlockMessage;
use WP_Buyer_Blacklist\Settings\SettingsTab;

/**
 * Builds the plugin modules and registers them with WordPress.
 */
final class Plugin {

	/**
	 * REST namespace shared by every controller.
	 */
	public const REST_NAMESPACE = 'wp-buyer-blacklist/v1';

	/**
	 * Feature modules, in registration order.
	 *
	 * @var list<DossierController|DossierDownload|PersonalDataTools|EntriesController|ImportExportController|CsvExportDownload|AdminPage|BlacklistSettings|CheckoutEnforcement|SourceEnrollment>
	 */
	private array $modules;

	/**
	 * Creates the plugin and its modules.
	 *
	 * @param EmailEntryRepository $entries Entry storage.
	 */
	public function __construct( EmailEntryRepository $entries ) {
		$buyer_block_message = new BuyerBlockMessage();
		$operator_access     = new OperatorAccess();
		$privacy_access      = new PrivacyDataAccess();

		$this->modules = array(
			new DossierController( $entries, $privacy_access ),
			new DossierDownload( $entries, $privacy_access ),
			new PersonalDataTools( $entries ),
			new EntriesController( $entries, $operator_access, $privacy_access ),
			new ImportExportController( $entries, $operator_access ),
			new CsvExportDownload( $entries, $privacy_access ),
			new AdminPage( $operator_access, $privacy_access, new SettingsTab( $buyer_block_message ) ),
			new BlacklistSettings( $buyer_block_message ),
			new CheckoutEnforcement(
				new EmailCheckoutBlocker( $entries, $entries ),
				new CustomerIdCheckoutBlocker( $entries ),
				new AddressCheckoutBlocker( $entries ),
				$buyer_block_message
			),
			new SourceEnrollment( $entries, $operator_access ),
		);
	}

	/**
	 * Registers every module's WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( $this->modules as $module ) {
			$module->register();
		}
	}
}

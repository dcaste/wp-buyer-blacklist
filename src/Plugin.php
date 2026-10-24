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
use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Enforcement\CheckoutEnforcement;
use WP_Buyer_Blacklist\Enforcement\CustomerIdCheckoutBlocker;
use WP_Buyer_Blacklist\Enforcement\EmailCheckoutBlocker;
use WP_Buyer_Blacklist\Enrollment\CustomerScreen;
use WP_Buyer_Blacklist\Enrollment\Enroller;
use WP_Buyer_Blacklist\Enrollment\EnrollmentPage;
use WP_Buyer_Blacklist\Enrollment\OrderScreen;
use WP_Buyer_Blacklist\Entries\AuditLog;
use WP_Buyer_Blacklist\Entries\BuyerLookup;
use WP_Buyer_Blacklist\Entries\EntriesController;
use WP_Buyer_Blacklist\Entries\EntryRepository;
use WP_Buyer_Blacklist\ImportExport\CsvExportDownload;
use WP_Buyer_Blacklist\ImportExport\CsvExporter;
use WP_Buyer_Blacklist\ImportExport\ImportExportController;
use WP_Buyer_Blacklist\ImportExport\ImportHistory;
use WP_Buyer_Blacklist\Privacy\DossierBuilder;
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
	 * @var list<DossierController|DossierDownload|PersonalDataTools|EntriesController|ImportExportController|CsvExportDownload|AdminPage|BlacklistSettings|CheckoutEnforcement|CustomerScreen|OrderScreen|EnrollmentPage>
	 */
	private array $modules;

	/**
	 * Creates the plugin and its modules.
	 *
	 * @param \wpdb $database WordPress database connection.
	 */
	public function __construct( \wpdb $database ) {
		$audit_log           = new AuditLog( $database );
		$blocked_attempts    = new BlockedAttemptLog( $database );
		$entries             = new EntryRepository( $database, $audit_log, $blocked_attempts );
		$dossiers            = new DossierBuilder( $database, $audit_log, $blocked_attempts );
		$buyer_block_message = new BuyerBlockMessage();
		$operator_access     = new OperatorAccess();
		$privacy_access      = new PrivacyDataAccess();
		$enroller            = new Enroller( $entries );
		$enrollment_page     = new EnrollmentPage( $enroller, $operator_access );

		$this->modules = array(
			new DossierController( $dossiers, $privacy_access ),
			new DossierDownload( $dossiers, $privacy_access ),
			new PersonalDataTools( $entries, $dossiers ),
			new EntriesController( $entries, $audit_log, $blocked_attempts, $dossiers, $operator_access, $privacy_access, new BuyerLookup() ),
			new ImportExportController( $entries, new ImportHistory( $database ), $operator_access ),
			new CsvExportDownload( new CsvExporter( $database ), $privacy_access ),
			new AdminPage( $operator_access, $privacy_access, new SettingsTab( $buyer_block_message ) ),
			new BlacklistSettings( $buyer_block_message ),
			new CheckoutEnforcement(
				new EmailCheckoutBlocker( $entries, $blocked_attempts ),
				new CustomerIdCheckoutBlocker( $entries, $blocked_attempts ),
				new AddressCheckoutBlocker( $entries, $blocked_attempts ),
				$buyer_block_message
			),
			new CustomerScreen( $entries, $enroller, $enrollment_page, $operator_access ),
			new OrderScreen( $enrollment_page, $operator_access ),
			$enrollment_page,
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

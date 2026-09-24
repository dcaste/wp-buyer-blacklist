<?php
/**
 * Decides who may handle privacy data.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Privacy;

/**
 * Limits deleting, erasing and exporting Blacklist data to administrators.
 */
final class PrivacyDataAccess {

	/**
	 * Checks whether the current user may delete, erase or export Blacklist data.
	 *
	 * @return bool
	 */
	public function can_manage_privacy_data(): bool {
		return current_user_can( 'manage_options' );
	}
}

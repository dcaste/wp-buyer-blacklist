<?php
/**
 * Carries a redirect out of a handler that would otherwise exit.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Thrown from the wp_redirect filter with the redirect location as its message.
 */
class RedirectException extends Exception {
}

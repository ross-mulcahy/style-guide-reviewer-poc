<?php
/**
 * Per-user rate limiter backed by transients.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

namespace StyleGuideReviewer\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Sliding-window counter: N requests per 60 s per user. Default 10/min,
 * filterable via `sgr_rate_limit_per_minute`.
 */
final class RateLimiter {

	public const WINDOW_SECONDS    = 60;
	public const DEFAULT_PER_MIN   = 10;
	private const TRANSIENT_PREFIX = 'sgr_rl_';

	/**
	 * Increment the user's counter and return whether they are within the
	 * allowed quota.
	 *
	 * @param int $user_id Current user ID. 0 is treated as anonymous, shared.
	 */
	public static function check( int $user_id ): bool {
		$limit = self::limit();
		if ( $limit <= 0 ) {
			return true; // Disabled.
		}

		$key     = self::TRANSIENT_PREFIX . $user_id;
		$current = (int) get_transient( $key );
		if ( $current >= $limit ) {
			return false;
		}

		// Preserve remaining TTL by reading the timeout option directly —
		// set_transient resets it to self::WINDOW_SECONDS otherwise.
		$timeout_key = '_transient_timeout_' . $key;
		$expires_at  = (int) get_option( $timeout_key );
		$ttl         = $expires_at > time() ? $expires_at - time() : self::WINDOW_SECONDS;

		set_transient( $key, $current + 1, $ttl );

		return true;
	}

	/**
	 * Test-only helper: clear a user's counter.
	 */
	public static function reset( int $user_id ): void {
		delete_transient( self::TRANSIENT_PREFIX . $user_id );
	}

	/**
	 * Effective limit after filtering.
	 */
	public static function limit(): int {
		/**
		 * Filters the number of reviews a user may run per minute.
		 *
		 * @param int $per_min Requests per 60 s. Return 0 to disable.
		 */
		return (int) apply_filters( 'sgr_rate_limit_per_minute', self::DEFAULT_PER_MIN );
	}
}

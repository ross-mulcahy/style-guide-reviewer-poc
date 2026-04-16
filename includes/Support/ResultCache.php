<?php
/**
 * SHA-256 content-hash cache for review results, stored in post_meta.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

namespace StyleGuideReviewer\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Writes / reads cached AI review payloads keyed by a stable content hash.
 * Invalidation is driven by `save_post`, so any edit to the post drops the
 * cache entry automatically.
 */
final class ResultCache {

	/**
	 * The hidden post_meta key where the cache payload lives. Leading
	 * underscore keeps it out of the default REST response.
	 */
	public const META_KEY = '_sgr_review_cache';

	public const DEFAULT_TTL_SECONDS = DAY_IN_SECONDS;

	/**
	 * Compute a stable hash across the guide + normalized content pair.
	 */
	public static function hash( string $guide, string $content ): string {
		return hash( 'sha256', $guide . "\n---\n" . $content );
	}

	/**
	 * Look up a cached result.
	 *
	 * @return array<string,mixed>|null Cached payload, or null if missing/expired.
	 */
	public static function get( int $post_id, string $hash ): ?array {
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $raw ) || empty( $raw['hash'] ) || $raw['hash'] !== $hash ) {
			return null;
		}

		$stored_at = (int) ( $raw['storedAt'] ?? 0 );
		$ttl       = self::ttl();
		if ( $ttl > 0 && ( time() - $stored_at ) > $ttl ) {
			return null;
		}

		return is_array( $raw['payload'] ?? null ) ? $raw['payload'] : null;
	}

	/**
	 * Persist a cached result.
	 *
	 * @param array<string,mixed> $payload Serializable review result.
	 */
	public static function set( int $post_id, string $hash, array $payload ): void {
		update_post_meta(
			$post_id,
			self::META_KEY,
			[
				'hash'     => $hash,
				'storedAt' => time(),
				'payload'  => $payload,
			]
		);
	}

	/**
	 * `save_post` hook: drop the cache whenever a post changes.
	 *
	 * @param int $post_id ID of the post that was saved.
	 */
	public static function invalidate_for_post( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		delete_post_meta( $post_id, self::META_KEY );
	}

	/**
	 * Cache TTL after filtering.
	 */
	public static function ttl(): int {
		/**
		 * Filters how long (seconds) a cached review result remains valid.
		 *
		 * @param int $ttl Seconds. Return <= 0 to disable expiration.
		 */
		return (int) apply_filters( 'sgr_cache_ttl', self::DEFAULT_TTL_SECONDS );
	}
}

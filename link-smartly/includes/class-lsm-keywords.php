<?php
/**
 * Keyword-to-URL mapping storage.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages keyword-to-URL mappings stored in wp_options with transient caching.
 *
 * Each keyword entry is an associative array:
 *   'id'         => (string) Unique identifier.
 *   'keyword'    => (string) The keyword phrase to match.
 *   'url'        => (string) Target URL (relative or absolute).
 *   'active'     => (bool)   Whether this mapping is active.
 *   'group'      => (string) Category/group label for organization.
 *   'synonyms'   => (string) Comma-separated alternative keyword phrases.
 *   'max_uses'   => (int)    Per-keyword max uses per post; 0 = use global.
 *   'nofollow'   => (string) 'default', 'yes', or 'no' — per-keyword override.
 *   'new_tab'    => (string) 'default', 'yes', or 'no' — per-keyword override.
 *   'start_date' => (string) Y-m-d start date or empty for no limit.
 *   'end_date'   => (string) Y-m-d end date or empty for no limit.
 *   'link_count' => (int)    Total number of times this keyword was auto-linked.
 *
 * @since 1.0.0
 */
class Lsm_Keywords {

	/**
	 * Option name for keyword mappings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME = 'lsm_keywords';

	/**
	 * Transient name for the cached active keyword map.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TRANSIENT_NAME = 'lsm_active_keywords';

	/**
	 * Transient expiration in seconds (24 hours).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TRANSIENT_EXPIRY = DAY_IN_SECONDS;

	/**
	 * Get all keyword mappings (active and inactive).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> List of keyword mappings.
	 */
	public function get_all(): array {
		$keywords = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $keywords ) ) {
			return array();
		}

		return $keywords;
	}

	/**
	 * Get only active keyword mappings, sorted by keyword length (longest first).
	 *
	 * Uses the plugin cache layer to avoid repeated option reads on the front-end.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Active keyword mappings.
	 */
	public function get_active(): array {
		$cached = Lsm_Cache::get( 'active_keywords' );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$all    = $this->get_all();
		$today  = gmdate( 'Y-m-d' );
		$active = array_filter(
			$all,
			static function ( array $item ) use ( $today ): bool {
				if ( empty( $item['active'] ) ) {
					return false;
				}

				// Schedule filtering.
				$start = $item['start_date'] ?? '';
				$end   = $item['end_date'] ?? '';

				if ( '' !== $start && $today < $start ) {
					return false;
				}

				if ( '' !== $end && $today > $end ) {
					return false;
				}

				return true;
			}
		);

		usort(
			$active,
			static function ( array $a, array $b ): int {
				return mb_strlen( $b['keyword'] ) <=> mb_strlen( $a['keyword'] );
			}
		);

		$active = array_values( $active );

		/**
		 * Filters the active keyword mappings before caching.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $active Active keyword mappings.
		 */
		$active = apply_filters( 'lsm_active_keywords', $active );

		if ( ! is_array( $active ) ) {
			$active = array();
		}

		Lsm_Cache::set( 'active_keywords', $active, self::TRANSIENT_EXPIRY );

		return $active;
	}

	/**
	 * Save the full keyword mappings array.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $keywords Keyword mappings.
	 * @return bool True if updated, false otherwise.
	 */
	public function save_all( array $keywords ): bool {
		$clean = array_map( array( $this, 'sanitize_entry' ), $keywords );
		$clean = array_values( $clean );

		$result = update_option( self::OPTION_NAME, $clean );

		$this->flush_cache();

		/**
		 * Fires after all keyword mappings are saved.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $clean Sanitized keyword mappings.
		 */
		do_action( 'lsm_keywords_saved', $clean );

		return $result;
	}

	/**
	 * Add a single keyword mapping.
	 *
	 * @since 1.0.0
	 *
	 * @param string $keyword The keyword phrase.
	 * @param string $url     The target URL.
	 * @param bool   $active  Whether the mapping is active.
	 * @return string The ID of the new entry.
	 */
	public function add( string $keyword, string $url, bool $active = true ): string {
		$all = $this->get_all();

		$id = wp_generate_uuid4();

		$all[] = $this->sanitize_entry(
			array(
				'id'      => $id,
				'keyword' => $keyword,
				'url'     => $url,
				'active'  => $active,
			)
		);

		$this->save_all( $all );

		return $id;
	}

	/**
	 * Update a single keyword mapping by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $id   The entry ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool True if found and updated, false otherwise.
	 */
	public function update( string $id, array $data ): bool {
		$all   = $this->get_all();
		$found = false;

		foreach ( $all as $index => $entry ) {
			if ( $entry['id'] === $id ) {
				$all[ $index ] = $this->sanitize_entry( array_merge( $entry, $data ) );
				$found         = true;
				break;
			}
		}

		if ( ! $found ) {
			return false;
		}

		return $this->save_all( $all );
	}

	/**
	 * Delete a keyword mapping by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id The entry ID.
	 * @return bool True if found and deleted, false otherwise.
	 */
	public function delete( string $id ): bool {
		$all     = $this->get_all();
		$initial = count( $all );

		$all = array_filter(
			$all,
			static function ( array $entry ) use ( $id ): bool {
				return $entry['id'] !== $id;
			}
		);

		if ( count( $all ) === $initial ) {
			return false;
		}

		return $this->save_all( $all );
	}

	/**
	 * Delete all keyword mappings.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if deleted, false otherwise.
	 */
	public function delete_all(): bool {
		$this->flush_cache();

		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Flush the active keywords cache.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		Lsm_Cache::delete( 'active_keywords' );
	}

	/**
	 * Get the count of all keyword mappings.
	 *
	 * @since 1.0.0
	 *
	 * @return int Total count.
	 */
	public function count(): int {
		return count( $this->get_all() );
	}

	/**
	 * Sanitize a single keyword entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $entry Raw entry data.
	 * @return array<string, mixed> Sanitized entry.
	 */
	public function sanitize_entry( array $entry ): array {
		$nofollow = isset( $entry['nofollow'] ) ? sanitize_key( $entry['nofollow'] ) : 'default';
		$new_tab  = isset( $entry['new_tab'] ) ? sanitize_key( $entry['new_tab'] ) : 'default';

		if ( ! in_array( $nofollow, array( 'default', 'yes', 'no' ), true ) ) {
			$nofollow = 'default';
		}

		if ( ! in_array( $new_tab, array( 'default', 'yes', 'no' ), true ) ) {
			$new_tab = 'default';
		}

		return array(
			'id'         => ! empty( $entry['id'] ) ? sanitize_text_field( $entry['id'] ) : wp_generate_uuid4(),
			'keyword'    => isset( $entry['keyword'] ) ? sanitize_text_field( $entry['keyword'] ) : '',
			'url'        => isset( $entry['url'] ) ? esc_url_raw( $entry['url'] ) : '',
			'active'     => ! empty( $entry['active'] ),
			'group'      => isset( $entry['group'] ) ? sanitize_text_field( $entry['group'] ) : '',
			'synonyms'   => isset( $entry['synonyms'] ) ? sanitize_text_field( $entry['synonyms'] ) : '',
			'max_uses'   => isset( $entry['max_uses'] ) ? absint( $entry['max_uses'] ) : 0,
			'nofollow'   => $nofollow,
			'new_tab'    => $new_tab,
			'start_date' => isset( $entry['start_date'] ) ? self::sanitize_date( $entry['start_date'] ) : '',
			'end_date'   => isset( $entry['end_date'] ) ? self::sanitize_date( $entry['end_date'] ) : '',
			'link_count' => isset( $entry['link_count'] ) ? absint( $entry['link_count'] ) : 0,
		);
	}

	/**
	 * Sanitize a date string to Y-m-d format.
	 *
	 * Returns empty string if the input is not a valid date.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $value Raw date input.
	 * @return string Sanitized date in Y-m-d format, or empty string.
	 */
	private static function sanitize_date( mixed $value ): string {
		$value = sanitize_text_field( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		$parts = explode( '-', $value );

		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Find keyword entries that match a given keyword phrase (for duplicate detection).
	 *
	 * @since 1.1.0
	 *
	 * @param string $keyword   The keyword phrase to search for.
	 * @param string $exclude_id Optional entry ID to exclude (for edits).
	 * @return array<int, array<string, mixed>> Matching entries.
	 */
	public function find_duplicates( string $keyword, string $exclude_id = '' ): array {
		$all     = $this->get_all();
		$keyword = mb_strtolower( trim( $keyword ) );

		return array_filter(
			$all,
			static function ( array $entry ) use ( $keyword, $exclude_id ): bool {
				if ( '' !== $exclude_id && $entry['id'] === $exclude_id ) {
					return false;
				}

				return mb_strtolower( trim( $entry['keyword'] ) ) === $keyword;
			}
		);
	}

	/**
	 * Get all unique group labels.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string> Sorted group labels.
	 */
	public function get_groups(): array {
		$all    = $this->get_all();
		$groups = array();

		foreach ( $all as $entry ) {
			$group = trim( $entry['group'] ?? '' );

			if ( '' !== $group ) {
				$groups[ $group ] = true;
			}
		}

		$labels = array_keys( $groups );
		sort( $labels );

		return $labels;
	}

	/**
	 * Increment the link count for a keyword entry.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id    The entry ID.
	 * @param int    $count Number to add. Default 1.
	 * @return void
	 */
	public function increment_link_count( string $id, int $count = 1 ): void {
		$all = $this->get_all();

		foreach ( $all as $index => $entry ) {
			if ( $entry['id'] === $id ) {
				$all[ $index ]['link_count'] = ( $all[ $index ]['link_count'] ?? 0 ) + $count;
				update_option( self::OPTION_NAME, $all );
				break;
			}
		}
	}

	/**
	 * Reset all link counts to zero.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function reset_link_counts(): void {
		$all = $this->get_all();

		foreach ( $all as $index => $entry ) {
			$all[ $index ]['link_count'] = 0;
		}

		update_option( self::OPTION_NAME, $all );
		$this->flush_cache();
		$this->reset_linked_posts();
	}

	/**
	 * Option name for the keyword-to-post mapping.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const LINKED_POSTS_OPTION = 'lsm_linked_posts';

	/**
	 * Record that a keyword was linked in a specific post.
	 *
	 * @since 1.2.0
	 *
	 * @param string $keyword_id The keyword entry ID.
	 * @param int    $post_id    The post ID where the link was inserted.
	 * @return void
	 */
	public function record_linked_post( string $keyword_id, int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$map = $this->get_all_linked_posts();

		if ( ! isset( $map[ $keyword_id ] ) ) {
			$map[ $keyword_id ] = array();
		}

		$map[ $keyword_id ][ $post_id ] = $post->post_title;

		update_option( self::LINKED_POSTS_OPTION, $map, false );
	}

	/**
	 * Get all posts where a specific keyword has been linked.
	 *
	 * @since 1.2.0
	 *
	 * @param string $keyword_id The keyword entry ID.
	 * @return array<int, string> Map of post_id => post_title.
	 */
	public function get_linked_posts( string $keyword_id ): array {
		$map = $this->get_all_linked_posts();

		if ( ! isset( $map[ $keyword_id ] ) || ! is_array( $map[ $keyword_id ] ) ) {
			return array();
		}

		return $map[ $keyword_id ];
	}

	/**
	 * Get the full keyword-to-posts mapping.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array<int, string>> Map of keyword_id => [post_id => title].
	 */
	public function get_all_linked_posts(): array {
		$map = get_option( self::LINKED_POSTS_OPTION, array() );

		if ( ! is_array( $map ) ) {
			return array();
		}

		return $map;
	}

	/**
	 * Replace the entire linked posts map (used by scan).
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, array<int, string>> $map The full mapping.
	 * @return void
	 */
	public function save_linked_posts( array $map ): void {
		update_option( self::LINKED_POSTS_OPTION, $map, false );
	}

	/**
	 * Reset all linked post records.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function reset_linked_posts(): void {
		delete_option( self::LINKED_POSTS_OPTION );
	}
}

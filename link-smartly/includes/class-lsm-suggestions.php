<?php
/**
 * Keyword suggestion engine and orphan content detector.
 *
 * @package LinkSmartly
 * @since   1.3.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans published content for existing links and suggests keyword-URL mappings.
 * Also detects orphan pages that no keyword mapping points to.
 *
 * @since 1.3.0
 */
class Lsm_Suggestions {

	/**
	 * Number of posts to process per batch.
	 *
	 * @since 1.3.0
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Cache key for suggestions.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	const CACHE_KEY = 'suggestions';

	/**
	 * Cache expiry in seconds (12 hours).
	 *
	 * @since 1.3.0
	 * @var int
	 */
	const CACHE_EXPIRY = 12 * HOUR_IN_SECONDS;

	/**
	 * Keywords manager instance.
	 *
	 * @since 1.3.0
	 * @var Lsm_Keywords
	 */
	private Lsm_Keywords $keywords;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param Lsm_Keywords $keywords Keywords manager.
	 */
	public function __construct( Lsm_Keywords $keywords ) {
		$this->keywords = $keywords;
	}

	/**
	 * Scan published posts for existing links and suggest keyword-URL pairs.
	 *
	 * Extracts anchor text and href from all <a> tags in published content,
	 * deduplicates against existing keyword mappings, and returns suggestions
	 * sorted by frequency (most common first).
	 *
	 * @since 1.3.0
	 *
	 * @param int $batch_offset Starting offset for pagination (0-based).
	 * @return array{suggestions: array<int, array{keyword: string, url: string, frequency: int, source_posts: array<int, string>}>, scanned: int, total: int}
	 */
	public function scan_existing_links( int $batch_offset = 0 ): array {
		$settings   = Lsm_Settings::get_all();
		$post_types = $settings['post_types'] ?? array( 'post', 'page' );

		$query_args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => self::BATCH_SIZE,
			'offset'         => $batch_offset,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);

		$query   = new WP_Query( $query_args );
		$total   = (int) $query->found_posts;
		$scanned = 0;

		// Get existing keyword-URL pairs for deduplication.
		$existing_map = $this->build_existing_map();

		// Accumulate raw link data from cached results (if continuing batch).
		$cached = Lsm_Cache::get( self::CACHE_KEY );
		$links  = ( false !== $cached && is_array( $cached ) ) ? $cached : array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$post_id = (int) $post_id;
				$post    = get_post( $post_id );

				if ( ! $post instanceof WP_Post || '' === $post->post_content ) {
					continue;
				}

				$extracted = $this->extract_links_from_content( $post->post_content, $post_id );

				foreach ( $extracted as $link ) {
					$key = mb_strtolower( $link['keyword'] ) . '|' . $link['url'];

					if ( isset( $links[ $key ] ) ) {
						++$links[ $key ]['frequency'];

						if ( ! in_array( $link['source_post'], $links[ $key ]['source_posts'], true ) ) {
							$links[ $key ]['source_posts'][] = $link['source_post'];
						}
					} else {
						$links[ $key ] = array(
							'keyword'      => $link['keyword'],
							'url'          => $link['url'],
							'frequency'    => 1,
							'source_posts' => array( $link['source_post'] ),
						);
					}
				}

				++$scanned;
			}
		}

		wp_reset_postdata();

		// Cache accumulated results for batch continuation.
		Lsm_Cache::set( self::CACHE_KEY, $links, self::CACHE_EXPIRY );

		// Filter out already-mapped keywords and build final suggestions.
		$suggestions = array();

		foreach ( $links as $link ) {
			$lower_keyword = mb_strtolower( $link['keyword'] );

			if ( isset( $existing_map[ $lower_keyword ] ) ) {
				continue;
			}

			// Skip very short anchor text (likely navigation).
			if ( mb_strlen( $link['keyword'] ) < 3 ) {
				continue;
			}

			$suggestions[] = $link;
		}

		// Sort by frequency descending.
		usort(
			$suggestions,
			static function ( array $a, array $b ): int {
				return $b['frequency'] <=> $a['frequency'];
			}
		);

		return array(
			'suggestions' => array_values( $suggestions ),
			'scanned'     => $scanned,
			'total'       => $total,
		);
	}

	/**
	 * Find published pages/posts that no keyword mapping points to.
	 *
	 * Compares published post URLs against all keyword target URLs.
	 *
	 * @since 1.3.0
	 *
	 * @return array<int, array{post_id: int, title: string, url: string, post_type: string}>
	 */
	public function find_orphan_pages(): array {
		$settings   = Lsm_Settings::get_all();
		$post_types = $settings['post_types'] ?? array( 'post', 'page' );

		$all_keywords = $this->keywords->get_all();

		// Build a set of normalized keyword target URLs.
		$mapped_urls = array();

		foreach ( $all_keywords as $entry ) {
			$url = $entry['url'] ?? '';

			if ( '' === $url ) {
				continue;
			}

			$mapped_urls[ $this->normalize_url( $url ) ] = true;
		}

		$query_args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$query   = new WP_Query( $query_args );
		$orphans = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$post_id   = (int) $post_id;
				$permalink = (string) get_permalink( $post_id );
				$normal    = $this->normalize_url( $permalink );

				if ( ! isset( $mapped_urls[ $normal ] ) ) {
					$post = get_post( $post_id );

					if ( $post instanceof WP_Post ) {
						$orphans[] = array(
							'post_id'   => $post_id,
							'title'     => $post->post_title,
							'url'       => $permalink,
							'post_type' => $post->post_type,
						);
					}
				}
			}
		}

		wp_reset_postdata();

		return $orphans;
	}

	/**
	 * Clear cached suggestion results.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function flush(): void {
		Lsm_Cache::delete( self::CACHE_KEY );
	}

	/**
	 * Extract links from post content using DOMDocument.
	 *
	 * @since 1.3.0
	 *
	 * @param string $content Raw post content.
	 * @param int    $post_id Post ID for source tracking.
	 * @return array<int, array{keyword: string, url: string, source_post: int}>
	 */
	private function extract_links_from_content( string $content, int $post_id ): array {
		$links = array();

		if ( '' === trim( $content ) ) {
			return $links;
		}

		$prev_errors = libxml_use_internal_errors( true );

		$doc = new DOMDocument();
		$doc->loadHTML(
			'<html><head><meta charset="UTF-8"></head><body>' . $content . '</body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $prev_errors );

		$anchors = $doc->getElementsByTagName( 'a' );

		foreach ( $anchors as $anchor ) {
			$href = $anchor->getAttribute( 'href' );
			$text = trim( $anchor->textContent );

			if ( '' === $href || '' === $text ) {
				continue;
			}

			// Skip external links.
			if ( $this->is_external_url( $href ) ) {
				continue;
			}

			// Skip anchor-only links.
			if ( 0 === strpos( $href, '#' ) ) {
				continue;
			}

			// Skip JavaScript links.
			if ( 0 === stripos( $href, 'javascript:' ) ) {
				continue;
			}

			// Normalize the URL.
			$url = esc_url_raw( $href );

			if ( '' === $url ) {
				continue;
			}

			$links[] = array(
				'keyword'     => $text,
				'url'         => $url,
				'source_post' => $post_id,
			);
		}

		return $links;
	}

	/**
	 * Check if a URL is external.
	 *
	 * @since 1.3.0
	 *
	 * @param string $url URL to check.
	 * @return bool True if external.
	 */
	private function is_external_url( string $url ): bool {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );

		// Relative URLs are internal.
		if ( null === $url_host || false === $url_host ) {
			return false;
		}

		return strtolower( (string) $url_host ) !== strtolower( (string) $site_host );
	}

	/**
	 * Build a lookup map of existing keyword phrases (lowercase).
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, true> Map of lowercase keyword => true.
	 */
	private function build_existing_map(): array {
		$all = $this->keywords->get_all();
		$map = array();

		foreach ( $all as $entry ) {
			$keyword = $entry['keyword'] ?? '';

			if ( '' !== $keyword ) {
				$map[ mb_strtolower( $keyword ) ] = true;
			}

			// Also include synonyms.
			$synonyms = $entry['synonyms'] ?? '';

			if ( '' !== $synonyms ) {
				$parts = array_map( 'trim', explode( ',', $synonyms ) );

				foreach ( $parts as $synonym ) {
					if ( '' !== $synonym ) {
						$map[ mb_strtolower( $synonym ) ] = true;
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Normalize a URL for comparison by removing scheme, trailing slashes, and www.
	 *
	 * @since 1.3.0
	 *
	 * @param string $url URL to normalize.
	 * @return string Normalized URL.
	 */
	private function normalize_url( string $url ): string {
		// Make relative URLs absolute.
		if ( '' !== $url && '/' === $url[0] ) {
			$url = home_url( $url );
		}

		$url = strtolower( $url );
		$url = (string) preg_replace( '#^https?://#', '', $url );
		$url = (string) preg_replace( '#^www\.#', '', $url );
		$url = rtrim( $url, '/' );

		return $url;
	}
}

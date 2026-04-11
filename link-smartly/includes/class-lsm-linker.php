<?php
/**
 * Content auto-linker using DOMDocument.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans post content and inserts internal links based on keyword-to-URL mappings.
 *
 * Uses DOMDocument for reliable HTML parsing to avoid linking inside
 * headings, existing anchors, code blocks, or other protected elements.
 *
 * @since 1.0.0
 */
class Lsm_Linker {

	/**
	 * Keywords manager instance.
	 *
	 * @since 1.0.0
	 * @var Lsm_Keywords
	 */
	private Lsm_Keywords $keywords;

	/**
	 * Plugin settings.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * HTML tag names that should never contain auto-links.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	const PROTECTED_TAGS = array(
		'a',
		'h1',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
		'code',
		'pre',
		'script',
		'style',
		'textarea',
		'button',
		'select',
		'option',
		'img',
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Lsm_Keywords         $keywords Keywords manager.
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	public function __construct( Lsm_Keywords $keywords, array $settings ) {
		$this->keywords = $keywords;
		$this->settings = $settings;
	}

	/**
	 * Process post content and insert auto-links.
	 *
	 * Hooked to the_content filter at a late priority.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The post content.
	 * @return string Modified content with auto-links inserted.
	 */
	public function process_content( string $content ): string {
		if ( ! $this->should_process( $content ) ) {
			return $content;
		}

		$keyword_map = $this->keywords->get_active();
		$highlight_mode = $this->is_highlight_mode();

		if ( empty( $keyword_map ) ) {
			return $content;
		}

		// Content processing cache: skip re-processing unchanged content.
		$post    = get_post();
		$post_id = ( $post instanceof WP_Post ) ? $post->ID : 0;

		if ( ! $highlight_mode && $post_id > 0 ) {
			$keywords_hash = md5( (string) wp_json_encode( $keyword_map ) );
			$settings_hash = $this->get_cache_settings_hash();
			$cache_key     = Lsm_Cache::get_versioned_key( 'content', 'content_' . $post_id );
			$cache_hash    = md5( $content . $keywords_hash . $settings_hash . (string) $post_id );
			$cached        = Lsm_Cache::get( $cache_key );

			if ( is_array( $cached )
				&& isset( $cached['hash'], $cached['result'] )
				&& $cached['hash'] === $cache_hash
			) {
				return $cached['result'];
			}
		}

		$current_url = $this->get_current_url();

		/**
		 * Filters the keyword map before processing.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $keyword_map Active keyword mappings.
		 * @param string                           $content     The post content.
		 */
		$keyword_map = apply_filters( 'lsm_keyword_map', $keyword_map, $content );

		if ( ! is_array( $keyword_map ) ) {
			return $content;
		}

		$max_links = (int) ( $this->settings['max_links_per_post'] ?? 3 );

		/**
		 * Filters the maximum number of auto-links per post.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $max_links Maximum auto-links.
		 * @param string $content   The post content.
		 */
		$max_links = (int) apply_filters( 'lsm_max_links_per_post', $max_links, $content );

		$result = $this->insert_links( $content, $keyword_map, $current_url, $max_links, $highlight_mode );

		/**
		 * Fires after auto-links have been inserted into content.
		 *
		 * @since 1.0.0
		 *
		 * @param string $result  The modified content.
		 * @param string $content The original content.
		 * @param int    $max_links Maximum links allowed.
		 */
		do_action( 'lsm_after_link_insertion', $result, $content, $max_links );

		// Store in content cache.
		if ( ! $highlight_mode && $post_id > 0 && isset( $cache_key, $cache_hash ) ) {
			Lsm_Cache::set(
				$cache_key,
				array(
					'hash'   => $cache_hash,
					'result' => $result,
				),
				DAY_IN_SECONDS
			);
		}

		return $result;
	}

	/**
	 * Check whether the current content should be processed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The post content.
	 * @return bool True if content should be processed.
	 */
	private function should_process( string $content ): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$allowed_types = (array) ( $this->settings['post_types'] ?? array( 'post', 'page' ) );

		/**
		 * Filters the post types that auto-linking applies to.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string> $allowed_types Allowed post type slugs.
		 */
		$allowed_types = (array) apply_filters( 'lsm_post_types', $allowed_types );

		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return false;
		}

		// Check post-level exclusion via meta box.
		if ( Lsm_Meta_Box::is_excluded( $post->ID ) ) {
			return false;
		}

		$min_words = (int) ( $this->settings['min_content_words'] ?? 300 );

		$word_count = str_word_count( wp_strip_all_tags( $content ) );

		if ( $word_count < $min_words ) {
			return false;
		}

		return true;
	}

	/**
	 * Insert links into the HTML content using DOMDocument.
	 *
	 * @since 1.0.0
	 *
	 * @param string                           $content     HTML content.
	 * @param array<int, array<string, mixed>> $keyword_map Keyword mappings (sorted longest-first).
	 * @param string                           $current_url The current page URL.
	 * @param int                              $max_links   Maximum links to insert.
	 * @param bool                             $dry_run     Whether this is a preview (skip counting).
	 * @return string Modified HTML content.
	 */
	private function insert_links( string $content, array $keyword_map, string $current_url, int $max_links, bool $dry_run = false ): string {
		$previous_libxml_state = libxml_use_internal_errors( true );

		$doc = new DOMDocument( '1.0', 'UTF-8' );

		$wrapped = '<div id="lsm-wrap">' . $content . '</div>';
		$html    = '<?xml encoding="UTF-8"><html><body>' . $wrapped . '</body></html>';

		if ( ! $doc->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR ) ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous_libxml_state );
			return $content;
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_libxml_state );

		$xpath       = new DOMXPath( $doc );
		$text_nodes  = $xpath->query( '//div[@id="lsm-wrap"]//text()' );
		$links_added = 0;
		$linked_urls = array();

		if ( false === $text_nodes ) {
			return $content;
		}

		foreach ( $keyword_map as $mapping ) {
			if ( $links_added >= $max_links ) {
				break;
			}

			$keyword = $mapping['keyword'] ?? '';
			$url     = $mapping['url'] ?? '';

			if ( '' === $keyword || '' === $url ) {
				continue;
			}

			// Per-keyword lifetime limit (0 = unlimited).
			$kw_max_uses = (int) ( $mapping['max_uses'] ?? 0 );

			if ( $kw_max_uses > 0 && (int) ( $mapping['link_count'] ?? 0 ) >= $kw_max_uses ) {
				continue;
			}

			$absolute_url = $this->make_absolute_url( $url );

			if ( $this->urls_match( $absolute_url, $current_url ) ) {
				continue;
			}

			if ( isset( $linked_urls[ $absolute_url ] ) ) {
				continue;
			}

			// Build keyword list: primary keyword + synonyms.
			$keywords_to_match = array( $keyword );
			$synonyms_str      = trim( $mapping['synonyms'] ?? '' );

			if ( '' !== $synonyms_str ) {
				$synonym_list      = array_filter( array_map( 'trim', explode( ',', $synonyms_str ) ) );
				$keywords_to_match = array_merge( $keywords_to_match, $synonym_list );
			}

			$replaced = $this->replace_in_text_nodes( $doc, $text_nodes, $keywords_to_match, $url, $mapping );

			if ( $replaced ) {
				++$links_added;
				$linked_urls[ $absolute_url ] = true;

				// Track link insertion for analytics.
				if ( ! $dry_run ) {
					$this->keywords->increment_link_count( $mapping['id'] );

					$current_post = get_post();

					if ( $current_post instanceof WP_Post ) {
						$this->keywords->record_linked_post( $mapping['id'], $current_post->ID );
					}
				}

				$text_nodes = $xpath->query( '//div[@id="lsm-wrap"]//text()' );

				if ( false === $text_nodes ) {
					break;
				}
			}
		}

		$result = $this->extract_content( $doc );

		// Safeguard: never return empty string if original content existed.
		if ( '' === $result && '' !== $content ) {
			return $content;
		}

		return $result;
	}

	/**
	 * Replace the first occurrence of a keyword in eligible text nodes.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMDocument          $doc        The DOM document.
	 * @param DOMNodeList          $text_nodes All text nodes.
	 * @param array<int, string>   $keywords   Keywords to match (primary + synonyms).
	 * @param string               $url        The target URL.
	 * @param array<string, mixed> $mapping    The full keyword mapping.
	 * @return bool True if a replacement was made.
	 */
	private function replace_in_text_nodes( DOMDocument $doc, DOMNodeList $text_nodes, array $keywords, string $url, array $mapping ): bool {
		// Sort longest-first so longer synonyms match preferentially.
		usort( $keywords, static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a ) );

		$parts   = array_map( static fn( string $k ): string => preg_quote( $k, '/' ), $keywords );
		$pattern = '/\b(' . implode( '|', $parts ) . ')\b/iu';

		foreach ( $text_nodes as $text_node ) {
			if ( ! $text_node instanceof DOMText ) {
				continue;
			}

			if ( $this->is_inside_protected_tag( $text_node ) ) {
				continue;
			}

			$node_value = $text_node->nodeValue ?? '';

			if ( 1 !== preg_match( $pattern, $node_value, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$match_text = $matches[1][0];

			// PREG_OFFSET_CAPTURE returns byte offsets; convert to character offset.
			$byte_offset = $matches[1][1];
			$before_text = mb_substr( $node_value, 0, mb_strlen( substr( $node_value, 0, $byte_offset ) ) );
			$after_text  = mb_substr( $node_value, mb_strlen( $before_text ) + mb_strlen( $match_text ) );

			$link = $this->create_link_element( $doc, $match_text, $url, $mapping );

			$parent = $text_node->parentNode;

			if ( ! $parent instanceof DOMNode ) {
				continue;
			}

			if ( '' !== $before_text ) {
				$parent->insertBefore( $doc->createTextNode( $before_text ), $text_node );
			}

			$parent->insertBefore( $link, $text_node );

			if ( '' !== $after_text ) {
				$parent->insertBefore( $doc->createTextNode( $after_text ), $text_node );
			}

			$parent->removeChild( $text_node );

			return true;
		}

		return false;
	}

	/**
	 * Check if a text node is inside a protected HTML element.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMText $node The text node to check.
	 * @return bool True if inside a protected tag.
	 */
	private function is_inside_protected_tag( DOMText $node ): bool {
		$parent = $node->parentNode;

		while ( $parent instanceof DOMElement ) {
			if ( in_array( strtolower( $parent->nodeName ), self::PROTECTED_TAGS, true ) ) {
				return true;
			}

			$parent = $parent->parentNode;
		}

		return false;
	}

	/**
	 * Create an anchor element for the auto-link.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMDocument          $doc     The DOM document.
	 * @param string               $text    The link text.
	 * @param string               $url     The target URL.
	 * @param array<string, mixed> $mapping The keyword mapping data.
	 * @return DOMElement The anchor element.
	 */
	private function create_link_element( DOMDocument $doc, string $text, string $url, array $mapping ): DOMElement {
		$link = $doc->createElement( 'a' );
		$link->setAttribute( 'href', esc_url( $url ) );

		$css_class = $this->settings['link_class'] ?? 'lsm-auto-link';

		// Add highlight class when ?lsm_highlight=1 is present for visual verification.
		if ( $this->is_highlight_mode() ) {
			$css_class .= ' lsm-highlight';
		}

		if ( '' !== $css_class ) {
			$link->setAttribute( 'class', $css_class );
		}

		if ( ! empty( $this->settings['add_title_attr'] ) ) {
			$link->setAttribute( 'title', esc_attr( $mapping['keyword'] ?? $text ) );
		}

		$kw_nofollow = $mapping['nofollow'] ?? 'default';
		$kw_new_tab  = $mapping['new_tab'] ?? 'default';
		$is_external = $this->is_external_url( $url );

		// Determine nofollow: per-keyword override > external auto-detect > global setting.
		$add_nofollow = false;

		if ( 'yes' === $kw_nofollow ) {
			$add_nofollow = true;
		} elseif ( 'no' === $kw_nofollow ) {
			$add_nofollow = false;
		} elseif ( $is_external ) {
			$add_nofollow = true;
		} elseif ( ! empty( $this->settings['nofollow'] ) ) {
			$add_nofollow = true;
		}

		// Determine new tab: per-keyword override > external auto-detect > global setting.
		$add_new_tab = false;

		if ( 'yes' === $kw_new_tab ) {
			$add_new_tab = true;
		} elseif ( 'no' === $kw_new_tab ) {
			$add_new_tab = false;
		} elseif ( $is_external ) {
			$add_new_tab = true;
		} elseif ( ! empty( $this->settings['new_tab'] ) ) {
			$add_new_tab = true;
		}

		$rel_parts = array();

		if ( $add_nofollow ) {
			$rel_parts[] = 'nofollow';
		}

		if ( $add_new_tab ) {
			$link->setAttribute( 'target', '_blank' );
			$rel_parts[] = 'noopener';
		}

		if ( ! empty( $rel_parts ) ) {
			$link->setAttribute( 'rel', implode( ' ', array_unique( $rel_parts ) ) );
		}

		/**
		 * Filters the link element attributes before insertion.
		 *
		 * @since 1.0.0
		 *
		 * @param DOMElement           $link    The anchor element.
		 * @param array<string, mixed> $mapping The keyword mapping.
		 * @param array<string, mixed> $settings Plugin settings.
		 */
		$link = apply_filters( 'lsm_link_element', $link, $mapping, $this->settings );

		if ( ! $link instanceof DOMElement ) {
			$link = $doc->createElement( 'a' );
			$link->setAttribute( 'href', esc_url( $url ) );
		}

		$link->appendChild( $doc->createTextNode( $text ) );

		return $link;
	}

	/**
	 * Check whether a URL points to an external domain.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url The URL to check.
	 * @return bool True if external.
	 */
	private function is_external_url( string $url ): bool {
		$link_host = wp_parse_url( $url, PHP_URL_HOST );

		if ( empty( $link_host ) ) {
			return false;
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		return strtolower( (string) $link_host ) !== strtolower( (string) $site_host );
	}

	/**
	 * Extract the inner HTML of the wrapper div from the DOM document.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMDocument $doc The DOM document.
	 * @return string The inner HTML content.
	 */
	private function extract_content( DOMDocument $doc ): string {
		$wrapper = $doc->getElementById( 'lsm-wrap' );

		if ( ! $wrapper instanceof DOMElement ) {
			return '';
		}

		$inner_html = '';

		foreach ( $wrapper->childNodes as $child ) {
			$inner_html .= $doc->saveHTML( $child );
		}

		return $inner_html;
	}

	/**
	 * Get the current page URL for self-link detection.
	 *
	 * @since 1.0.0
	 *
	 * @return string Current page URL path.
	 */
	private function get_current_url(): string {
		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		return (string) get_permalink( $post );
	}

	/**
	 * Check whether the current request is in highlight verification mode.
	 *
	 * @since 1.4.0
	 *
	 * @return bool True when highlight mode is active.
	 */
	private function is_highlight_mode(): bool {
		if ( ! function_exists( 'lsm_is_highlight_mode' ) ) {
			return false;
		}

		return lsm_is_highlight_mode();
	}

	/**
	 * Build a hash of settings that affect rendered link markup.
	 *
	 * @since 1.3.0
	 *
	 * @return string Cache settings hash.
	 */
	private function get_cache_settings_hash(): string {
		$cache_settings = array(
			'max_links_per_post' => (int) ( $this->settings['max_links_per_post'] ?? 3 ),
			'min_content_words'  => (int) ( $this->settings['min_content_words'] ?? 300 ),
			'link_class'         => (string) ( $this->settings['link_class'] ?? 'lsm-auto-link' ),
			'add_title_attr'     => ! empty( $this->settings['add_title_attr'] ),
			'nofollow'           => ! empty( $this->settings['nofollow'] ),
			'new_tab'            => ! empty( $this->settings['new_tab'] ),
			'post_types'         => array_values( (array) ( $this->settings['post_types'] ?? array() ) ),
		);

		return md5( (string) wp_json_encode( $cache_settings ) );
	}

	/**
	 * Convert a relative URL to an absolute URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL (relative or absolute).
	 * @return string Absolute URL.
	 */
	private function make_absolute_url( string $url ): string {
		if ( str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' ) ) {
			return $url;
		}

		return home_url( $url );
	}

	/**
	 * Compare two URLs to determine if they point to the same page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url_a First URL.
	 * @param string $url_b Second URL.
	 * @return bool True if URLs match.
	 */
	private function urls_match( string $url_a, string $url_b ): bool {
		$path_a = untrailingslashit( (string) wp_parse_url( $url_a, PHP_URL_PATH ) );
		$path_b = untrailingslashit( (string) wp_parse_url( $url_b, PHP_URL_PATH ) );

		return strtolower( $path_a ) === strtolower( $path_b );
	}

	/**
	 * Process content for preview purposes (dry-run).
	 *
	 * Returns both the modified content and details of links that would be inserted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The post content to process.
	 * @param string $url     The URL of the post (for self-link detection).
	 * @return array{content: string, links: array<int, array<string, string>>} Preview data.
	 */
	public function preview( string $content, string $url ): array {
		$keyword_map = $this->keywords->get_active();

		if ( empty( $keyword_map ) ) {
			return array(
				'content' => $content,
				'links'   => array(),
			);
		}

		$max_links = (int) ( $this->settings['max_links_per_post'] ?? 3 );
		$modified  = $this->insert_links( $content, $keyword_map, $url, $max_links, true );

		$links = $this->extract_inserted_links( $modified );

		return array(
			'content' => $modified,
			'links'   => $links,
		);
	}

	/**
	 * Extract auto-inserted links from the modified content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Modified HTML content.
	 * @return array<int, array<string, string>> List of inserted links with keyword and URL.
	 */
	private function extract_inserted_links( string $content ): array {
		$css_class = preg_quote( $this->settings['link_class'] ?? 'lsm-auto-link', '/' );
		$pattern   = '/<a[^>]*class="' . $css_class . '"[^>]*href="([^"]*)"[^>]*>([^<]*)<\/a>/i';

		$links = array();

		if ( 1 > preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			return $links;
		}

		foreach ( $matches as $match ) {
			$links[] = array(
				'url'     => $match[1],
				'keyword' => $match[2],
			);
		}

		return $links;
	}
}

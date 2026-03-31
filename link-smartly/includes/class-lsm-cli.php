<?php
/**
 * WP-CLI commands for Link Smartly.
 *
 * @package LinkSmartly
 * @since   1.1.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage Link Smartly keyword mappings and settings from the command line.
 *
 * ## EXAMPLES
 *
 *     wp lsm list
 *     wp lsm add "contact us" /contact/
 *     wp lsm delete <id>
 *     wp lsm stats
 *     wp lsm flush-cache
 *
 * @since 1.1.0
 */
class Lsm_Cli {

	/**
	 * Keywords manager instance.
	 *
	 * @since 1.1.0
	 * @var Lsm_Keywords
	 */
	private Lsm_Keywords $keywords;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param Lsm_Keywords $keywords Keywords manager.
	 */
	public function __construct( Lsm_Keywords $keywords ) {
		$this->keywords = $keywords;
	}

	/**
	 * List all keyword mappings.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--status=<status>]
	 * : Filter by status.
	 * ---
	 * options:
	 *   - active
	 *   - inactive
	 *   - all
	 * ---
	 *
	 * [--group=<group>]
	 * : Filter by group name.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lsm list
	 *     wp lsm list --status=active
	 *     wp lsm list --format=csv
	 *     wp lsm list --group=services
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function list_( array $args, array $assoc_args ): void {
		$all    = $this->keywords->get_all();
		$status = $assoc_args['status'] ?? 'all';
		$group  = $assoc_args['group'] ?? '';
		$format = $assoc_args['format'] ?? 'table';

		if ( 'active' === $status ) {
			$all = array_filter( $all, static fn( array $e ): bool => ! empty( $e['active'] ) );
		} elseif ( 'inactive' === $status ) {
			$all = array_filter( $all, static fn( array $e ): bool => empty( $e['active'] ) );
		}

		if ( '' !== $group ) {
			$all = array_filter(
				$all,
				static fn( array $e ): bool => strtolower( $e['group'] ?? '' ) === strtolower( $group )
			);
		}

		$all = array_values( $all );

		if ( empty( $all ) ) {
			WP_CLI::log( __( 'No keyword mappings found.', 'link-smartly' ) );
			return;
		}

		$fields = array( 'id', 'keyword', 'url', 'active', 'group', 'link_count' );

		WP_CLI\Utils\format_items( $format, $all, $fields );
	}

	/**
	 * Add a new keyword mapping.
	 *
	 * ## OPTIONS
	 *
	 * <keyword>
	 * : The keyword phrase to match.
	 *
	 * <url>
	 * : The target URL.
	 *
	 * [--group=<group>]
	 * : Optional group label.
	 *
	 * [--synonyms=<synonyms>]
	 * : Comma-separated synonyms.
	 *
	 * [--inactive]
	 * : Create as inactive.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lsm add "contact us" /contact/
	 *     wp lsm add "our services" /services/ --group=main
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function add( array $args, array $assoc_args ): void {
		$keyword = $args[0] ?? '';
		$url     = $args[1] ?? '';

		if ( '' === $keyword || '' === $url ) {
			WP_CLI::error( __( 'Both keyword and URL are required.', 'link-smartly' ) );
			return;
		}

		$duplicates = $this->keywords->find_duplicates( $keyword );

		if ( ! empty( $duplicates ) ) {
			WP_CLI::warning(
				sprintf(
					/* translators: %s: keyword phrase */
					__( 'Duplicate keyword found: "%s". Adding anyway.', 'link-smartly' ),
					$keyword
				)
			);
		}

		$all   = $this->keywords->get_all();
		$entry = $this->keywords->sanitize_entry(
			array(
				'id'       => wp_generate_uuid4(),
				'keyword'  => $keyword,
				'url'      => $url,
				'active'   => ! isset( $assoc_args['inactive'] ),
				'group'    => $assoc_args['group'] ?? '',
				'synonyms' => $assoc_args['synonyms'] ?? '',
			)
		);

		$all[] = $entry;
		$this->keywords->save_all( $all );

		WP_CLI::success(
			sprintf(
				/* translators: 1: keyword phrase 2: target URL */
				__( 'Added: "%1$s" → %2$s', 'link-smartly' ),
				$keyword,
				$url
			)
		);
	}

	/**
	 * Delete a keyword mapping by ID.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The keyword entry UUID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lsm delete 550e8400-e29b-41d4-a716-446655440000
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function delete( array $args, array $assoc_args ): void {
		$id = $args[0] ?? '';

		if ( '' === $id ) {
			WP_CLI::error( __( 'Entry ID is required.', 'link-smartly' ) );
			return;
		}

		if ( $this->keywords->delete( $id ) ) {
			WP_CLI::success( __( 'Keyword mapping deleted.', 'link-smartly' ) );
		} else {
			WP_CLI::error( __( 'Keyword mapping not found.', 'link-smartly' ) );
		}
	}

	/**
	 * Show link statistics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lsm stats
	 *     wp lsm stats --format=json
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function stats( array $args, array $assoc_args ): void {
		$all    = $this->keywords->get_all();
		$format = $assoc_args['format'] ?? 'table';

		usort(
			$all,
			static fn( array $a, array $b ): int => ( $b['link_count'] ?? 0 ) <=> ( $a['link_count'] ?? 0 )
		);

		if ( empty( $all ) ) {
			WP_CLI::log( __( 'No keyword mappings found.', 'link-smartly' ) );
			return;
		}

		$fields = array( 'keyword', 'url', 'link_count', 'active', 'group' );

		WP_CLI\Utils\format_items( $format, $all, $fields );

		$total = array_sum( array_column( $all, 'link_count' ) );

		WP_CLI::log(
			sprintf(
				/* translators: %d: total link count */
				__( 'Total auto-links inserted: %d', 'link-smartly' ),
				$total
			)
		);
	}

	/**
	 * Flush the active keywords transient cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lsm flush-cache
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function flush_cache( array $args, array $assoc_args ): void {
		$this->keywords->flush_cache();
		WP_CLI::success( __( 'Active keywords cache flushed.', 'link-smartly' ) );
	}

	/**
	 * Reset all link count statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lsm reset-stats
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function reset_stats( array $args, array $assoc_args ): void {
		WP_CLI::confirm( __( 'Are you sure you want to reset all link statistics?', 'link-smartly' ) );

		$this->keywords->reset_link_counts();
		WP_CLI::success( __( 'All link statistics have been reset.', 'link-smartly' ) );
	}

	/**
	 * Import keywords from a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV file.
	 *
	 * [--mode=<mode>]
	 * : Import mode.
	 * ---
	 * default: append
	 * options:
	 *   - append
	 *   - replace
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lsm import keywords.csv
	 *     wp lsm import keywords.csv --mode=replace
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = $args[0] ?? '';

		if ( '' === $file || ! file_exists( $file ) ) {
			WP_CLI::error( __( 'CSV file not found.', 'link-smartly' ) );
			return;
		}

		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			WP_CLI::error( __( 'Cannot open CSV file.', 'link-smartly' ) );
			return;
		}

		$header = fgetcsv( $handle );

		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			WP_CLI::error( __( 'Invalid CSV file.', 'link-smartly' ) );
			return;
		}

		$header      = array_map( 'strtolower', array_map( 'trim', $header ) );
		$keyword_col = array_search( 'keyword', $header, true );
		$url_col     = array_search( 'url', $header, true );
		$active_col  = array_search( 'active', $header, true );
		$group_col   = array_search( 'group', $header, true );

		if ( false === $keyword_col || false === $url_col ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			WP_CLI::error( __( 'CSV must have "keyword" and "url" columns.', 'link-smartly' ) );
			return;
		}

		$entries = array();

		while ( false !== ( $row = fgetcsv( $handle ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( ! is_array( $row ) ) {
				continue;
			}

			$keyword = trim( $row[ $keyword_col ] ?? '' );
			$url     = trim( $row[ $url_col ] ?? '' );

			if ( '' === $keyword || '' === $url ) {
				continue;
			}

			$active = true;
			$group  = '';

			if ( false !== $active_col && isset( $row[ $active_col ] ) ) {
				$active = '0' !== trim( $row[ $active_col ] );
			}

			if ( false !== $group_col && isset( $row[ $group_col ] ) ) {
				$group = trim( $row[ $group_col ] );
			}

			$entries[] = $this->keywords->sanitize_entry(
				array(
					'id'      => wp_generate_uuid4(),
					'keyword' => $keyword,
					'url'     => $url,
					'active'  => $active,
					'group'   => $group,
				)
			);
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$mode = $assoc_args['mode'] ?? 'append';

		if ( 'replace' === $mode ) {
			$this->keywords->save_all( $entries );
		} else {
			$existing = $this->keywords->get_all();
			$this->keywords->save_all( array_merge( $existing, $entries ) );
		}

		WP_CLI::success(
			sprintf(
				/* translators: %d: number of imported entries */
				__( 'Imported %d keyword mappings.', 'link-smartly' ),
				count( $entries )
			)
		);
	}

	/**
	 * Check the health of keyword target URLs.
	 *
	 * Performs HTTP HEAD requests to verify URLs are reachable.
	 * Processes URLs in batches of 50 with rate limiting.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format for results.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * [--status=<status>]
	 * : Only show URLs with this health status.
	 * ---
	 * options:
	 *   - ok
	 *   - redirect
	 *   - error
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp link-smartly check-urls
	 *     wp link-smartly check-urls --status=error
	 *     wp link-smartly check-urls --format=json
	 *
	 * @since 1.2.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function check_urls( array $args, array $assoc_args ): void {
		$health  = new Lsm_Health( $this->keywords );
		$all     = $this->keywords->get_all();
		$urls    = array();

		foreach ( $all as $entry ) {
			$url = $entry['url'] ?? '';

			if ( '' !== $url ) {
				$urls[ $url ] = true;
			}
		}

		$urls  = array_keys( $urls );
		$total = count( $urls );

		if ( 0 === $total ) {
			WP_CLI::log( __( 'No keyword URLs to check.', 'link-smartly' ) );
			return;
		}

		WP_CLI::log(
			sprintf(
				/* translators: %d: total URL count */
				__( 'Checking %d unique URLs…', 'link-smartly' ),
				$total
			)
		);

		$progress = \WP_CLI\Utils\make_progress_bar( __( 'Checking URLs', 'link-smartly' ), $total );
		$offset   = 0;

		while ( $offset < $total ) {
			$result  = $health->check_urls( $offset );
			$offset += $result['checked'];

			for ( $i = 0; $i < $result['checked']; $i++ ) {
				$progress->tick();
			}
		}

		$progress->finish();

		$results = $health->get_results();
		$format  = $assoc_args['format'] ?? 'table';
		$filter  = $assoc_args['status'] ?? '';
		$rows    = array();

		foreach ( $results as $url => $data ) {
			if ( '' !== $filter && $data['status'] !== $filter ) {
				continue;
			}

			$rows[] = array(
				'url'        => $url,
				'status'     => $data['status'],
				'code'       => $data['code'],
				'checked_at' => $data['checked_at'],
			);
		}

		if ( empty( $rows ) ) {
			WP_CLI::log( __( 'No results match the specified filter.', 'link-smartly' ) );
			return;
		}

		WP_CLI\Utils\format_items( $format, $rows, array( 'url', 'status', 'code', 'checked_at' ) );

		$summary = $health->get_summary();

		WP_CLI::log(
			sprintf(
				/* translators: 1: ok count, 2: redirect count, 3: error count, 4: unknown count */
				__( 'Summary: %1$d OK, %2$d redirects, %3$d errors, %4$d unchecked.', 'link-smartly' ),
				$summary['ok'],
				$summary['redirect'],
				$summary['error'],
				$summary['unknown']
			)
		);
	}

	/**
	 * Scan published content and suggest keyword-URL mappings.
	 *
	 * Extracts existing <a> tags from published posts and suggests
	 * keyword-URL pairs not yet mapped.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lsm suggest
	 *     wp lsm suggest --format=json
	 *
	 * @since 1.3.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function suggest( array $args, array $assoc_args ): void {
		$suggestions_engine = new Lsm_Suggestions( $this->keywords );

		WP_CLI::log( __( 'Scanning published content for link suggestions…', 'link-smartly' ) );

		$offset  = 0;
		$results = array();

		do {
			$batch  = $suggestions_engine->scan_existing_links( $offset );
			$offset += $batch['scanned'];

			if ( 0 === $batch['scanned'] ) {
				break;
			}
		} while ( $offset < $batch['total'] );

		$format      = $assoc_args['format'] ?? 'table';
		$suggestions = $batch['suggestions'] ?? array();

		if ( empty( $suggestions ) ) {
			WP_CLI::log( __( 'No new keyword suggestions found.', 'link-smartly' ) );
			return;
		}

		$rows = array();

		foreach ( $suggestions as $item ) {
			$rows[] = array(
				'keyword'   => $item['keyword'],
				'url'       => $item['url'],
				'frequency' => $item['frequency'],
				'posts'     => count( $item['source_posts'] ),
			);
		}

		WP_CLI\Utils\format_items( $format, $rows, array( 'keyword', 'url', 'frequency', 'posts' ) );

		WP_CLI::success(
			sprintf(
				/* translators: %d: number of suggestions */
				__( 'Found %d keyword suggestions.', 'link-smartly' ),
				count( $suggestions )
			)
		);
	}

	/**
	 * List published pages not targeted by any keyword mapping.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp lsm orphans
	 *     wp lsm orphans --format=json
	 *
	 * @since 1.3.0
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function orphans( array $args, array $assoc_args ): void {
		$suggestions_engine = new Lsm_Suggestions( $this->keywords );
		$orphans            = $suggestions_engine->find_orphan_pages();

		if ( empty( $orphans ) ) {
			WP_CLI::success( __( 'No orphan pages found — all pages are targeted by keyword mappings.', 'link-smartly' ) );
			return;
		}

		$format = $assoc_args['format'] ?? 'table';
		$rows   = array();

		foreach ( $orphans as $item ) {
			$rows[] = array(
				'post_id'   => $item['post_id'],
				'title'     => $item['title'],
				'url'       => $item['url'],
				'post_type' => $item['post_type'],
			);
		}

		WP_CLI\Utils\format_items( $format, $rows, array( 'post_id', 'title', 'url', 'post_type' ) );

		WP_CLI::warning(
			sprintf(
				/* translators: %d: number of orphan pages */
				__( '%d orphan pages found with no keyword mappings.', 'link-smartly' ),
				count( $orphans )
			)
		);
	}
}

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
}

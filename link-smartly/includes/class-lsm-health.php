<?php
/**
 * URL health checker for keyword target URLs.
 *
 * @package LinkSmartly
 * @since   1.2.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch-checks keyword target URLs and stores health results in a transient.
 *
 * Health statuses:
 *   'ok'      — HTTP 2xx response.
 *   'redirect' — HTTP 3xx response.
 *   'error'   — HTTP 4xx/5xx or request failure.
 *   'unknown' — Not yet checked.
 *
 * @since 1.2.0
 */
class Lsm_Health {

	/**
	 * Transient name for health results.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const TRANSIENT_NAME = 'lsm_url_health';

	/**
	 * Transient expiration in seconds (12 hours).
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const TRANSIENT_EXPIRY = 12 * HOUR_IN_SECONDS;

	/**
	 * Maximum URLs to check in a single batch.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Delay between requests in microseconds (200ms = 5 req/sec).
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const REQUEST_DELAY_US = 200000;

	/**
	 * Request timeout in seconds.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const REQUEST_TIMEOUT = 10;

	/**
	 * Maximum URLs to check per cron batch (lower to avoid timeouts).
	 *
	 * @since 1.3.0
	 * @var int
	 */
	const CRON_BATCH_SIZE = 20;

	/**
	 * WP-Cron event name.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	const CRON_HOOK = 'lsm_weekly_health_check';

	/**
	 * Keywords manager instance.
	 *
	 * @since 1.2.0
	 * @var Lsm_Keywords
	 */
	private Lsm_Keywords $keywords;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param Lsm_Keywords $keywords Keywords manager.
	 */
	public function __construct( Lsm_Keywords $keywords ) {
		$this->keywords = $keywords;
	}

	/**
	 * Run a health check on all unique keyword URLs.
	 *
	 * Processes up to BATCH_SIZE URLs per call, rate-limited.
	 *
	 * @since 1.2.0
	 *
	 * @param int $batch_offset Starting offset for pagination (0-based).
	 * @return array{checked: int, total: int, results: array<string, array{status: string, code: int, checked_at: string}>}
	 */
	public function check_urls( int $batch_offset = 0 ): array {
		$all  = $this->keywords->get_all();
		$urls = $this->extract_unique_urls( $all );

		$total   = count( $urls );
		$batch   = array_slice( $urls, $batch_offset, self::BATCH_SIZE );
		$results = $this->get_results();
		$checked = 0;

		foreach ( $batch as $url ) {
			$absolute_url = $this->make_absolute_url( $url );
			$result       = $this->check_single_url( $absolute_url );

			$results[ $url ] = array(
				'status'     => $result['status'],
				'code'       => $result['code'],
				'checked_at' => gmdate( 'Y-m-d H:i:s' ),
			);

			++$checked;

			// Rate limiting: sleep between requests if not the last one.
			if ( $checked < count( $batch ) ) {
				usleep( self::REQUEST_DELAY_US );
			}
		}

		Lsm_Cache::set( 'url_health', $results, self::TRANSIENT_EXPIRY );

		return array(
			'checked' => $checked,
			'total'   => $total,
			'results' => $results,
		);
	}

	/**
	 * Get cached health check results.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array{status: string, code: int, checked_at: string}> Results keyed by URL.
	 */
	public function get_results(): array {
		$results = Lsm_Cache::get( 'url_health' );

		if ( ! is_array( $results ) ) {
			return array();
		}

		return $results;
	}

	/**
	 * Get the health status for a specific URL.
	 *
	 * @since 1.2.0
	 *
	 * @param string $url The keyword target URL.
	 * @return string Status: 'ok', 'redirect', 'error', or 'unknown'.
	 */
	public function get_url_status( string $url ): string {
		$results = $this->get_results();

		if ( ! isset( $results[ $url ] ) ) {
			return 'unknown';
		}

		return $results[ $url ]['status'];
	}

	/**
	 * Get a summary of all health results.
	 *
	 * @since 1.2.0
	 *
	 * @return array{ok: int, redirect: int, error: int, unknown: int, total: int} Summary counts.
	 */
	public function get_summary(): array {
		$all     = $this->keywords->get_all();
		$urls    = $this->extract_unique_urls( $all );
		$results = $this->get_results();

		$summary = array(
			'ok'       => 0,
			'redirect' => 0,
			'error'    => 0,
			'unknown'  => 0,
			'total'    => count( $urls ),
		);

		foreach ( $urls as $url ) {
			if ( ! isset( $results[ $url ] ) ) {
				++$summary['unknown'];
				continue;
			}

			$status = $results[ $url ]['status'];

			if ( isset( $summary[ $status ] ) ) {
				++$summary[ $status ];
			} else {
				++$summary['unknown'];
			}
		}

		return $summary;
	}

	/**
	 * Get keywords that have broken URLs.
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, array<string, mixed>> Keywords with error status URLs.
	 */
	public function get_broken_keywords(): array {
		$all     = $this->keywords->get_all();
		$results = $this->get_results();
		$broken  = array();

		foreach ( $all as $entry ) {
			$url = $entry['url'] ?? '';

			if ( '' === $url ) {
				continue;
			}

			if ( isset( $results[ $url ] ) && 'error' === $results[ $url ]['status'] ) {
				$entry['health_code']    = $results[ $url ]['code'];
				$entry['health_checked'] = $results[ $url ]['checked_at'];
				$broken[]                = $entry;
			}
		}

		return $broken;
	}

	/**
	 * Clear all cached health results.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function flush(): void {
		Lsm_Cache::delete( 'url_health' );
	}

	/**
	 * Check a single URL via HEAD request.
	 *
	 * @since 1.2.0
	 *
	 * @param string $url Absolute URL to check.
	 * @return array{status: string, code: int} Check result.
	 */
	private function check_single_url( string $url ): array {
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => self::REQUEST_TIMEOUT,
				'redirection' => 0,
				'sslverify'   => true,
				'user-agent'  => 'LinkSmartly/' . LSM_VERSION . ' (Health Check)',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 'error',
				'code'   => 0,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'status' => 'ok',
				'code'   => $code,
			);
		}

		if ( $code >= 300 && $code < 400 ) {
			return array(
				'status' => 'redirect',
				'code'   => $code,
			);
		}

		return array(
			'status' => 'error',
			'code'   => $code,
		);
	}

	/**
	 * Extract unique target URLs from all keyword entries.
	 *
	 * @since 1.2.0
	 *
	 * @param array<int, array<string, mixed>> $keywords All keyword mappings.
	 * @return array<int, string> Unique URLs.
	 */
	private function extract_unique_urls( array $keywords ): array {
		$urls = array();

		foreach ( $keywords as $entry ) {
			$url = $entry['url'] ?? '';

			if ( '' !== $url ) {
				$urls[ $url ] = true;
			}
		}

		return array_keys( $urls );
	}

	/**
	 * Convert a relative URL to an absolute URL.
	 *
	 * @since 1.2.0
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
	 * Run a cron-safe batch health check (lower batch size, no user context).
	 *
	 * Processes all URLs in CRON_BATCH_SIZE increments.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function cron_check(): void {
		$settings = Lsm_Settings::get_all();

		if ( empty( $settings['cron_health_check'] ) ) {
			return;
		}

		$keywords = new Lsm_Keywords();
		$health   = new self( $keywords );
		$all      = $keywords->get_all();
		$urls     = $health->extract_unique_urls( $all );
		$total    = count( $urls );
		$offset   = 0;

		while ( $offset < $total ) {
			$result  = $health->check_urls( $offset );
			$offset += $result['checked'];

			if ( 0 === $result['checked'] ) {
				break;
			}
		}
	}

	/**
	 * Schedule the weekly cron health check.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the weekly cron health check.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function unschedule_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}

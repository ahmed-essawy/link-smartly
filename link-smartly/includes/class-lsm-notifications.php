<?php
/**
 * Email digest notifications.
 *
 * @package LinkSmartly
 * @since   1.3.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends weekly email digest with keyword/health summary.
 *
 * @since 1.3.0
 */
class Lsm_Notifications {

	/**
	 * WP-Cron hook name for the email digest.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	const CRON_HOOK = 'lsm_email_digest';

	/**
	 * Schedule the weekly email digest cron event.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the weekly email digest cron event.
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

	/**
	 * Send the weekly email digest.
	 *
	 * Checks the email_digest setting before sending.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function send_digest(): void {
		$settings = Lsm_Settings::get_all();

		if ( empty( $settings['email_digest'] ) ) {
			return;
		}

		$keywords = new Lsm_Keywords();
		$all      = $keywords->get_all();

		if ( empty( $all ) ) {
			return;
		}

		$health      = new Lsm_Health( $keywords );
		$health_data = $health->get_results();
		$active      = array_filter( $all, static fn( array $e ): bool => ! empty( $e['active'] ) );
		$total_links = 0;
		$zero_links  = array();
		$top_five    = array();
		$broken_urls = array();

		// Gather stats.
		foreach ( $all as $entry ) {
			$count        = (int) ( $entry['link_count'] ?? 0 );
			$total_links += $count;

			if ( 0 === $count && ! empty( $entry['active'] ) ) {
				$zero_links[] = $entry['keyword'];
			}

			$top_five[] = array(
				'keyword'    => $entry['keyword'],
				'link_count' => $count,
			);
		}

		// Sort top performers.
		usort(
			$top_five,
			static function ( array $a, array $b ): int {
				return $b['link_count'] <=> $a['link_count'];
			}
		);
		$top_five = array_slice( $top_five, 0, 5 );

		// Broken URLs.
		foreach ( $health_data as $url => $result ) {
			if ( isset( $result['status'] ) && 'broken' === $result['status'] ) {
				$broken_urls[] = $url;
			}
		}

		$site_name = get_bloginfo( 'name' );
		$admin_url = admin_url( 'options-general.php?page=link-smartly' );

		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] Link Smartly — Weekly Digest', 'link-smartly' ), $site_name );

		$body = self::build_email_body(
			array(
				'total_keywords' => count( $all ),
				'active'         => count( $active ),
				'total_links'    => $total_links,
				'zero_links'     => $zero_links,
				'top_five'       => $top_five,
				'broken_urls'    => $broken_urls,
				'admin_url'      => $admin_url,
				'site_name'      => $site_name,
			)
		);

		$admin_email = get_option( 'admin_email' );

		if ( ! empty( $admin_email ) ) {
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			wp_mail( $admin_email, $subject, $body, $headers );
		}
	}

	/**
	 * Build the HTML email body.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $data Email data.
	 * @return string HTML email body.
	 */
	private static function build_email_body( array $data ): string {
		$html  = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">';
		$html .= '<h2 style="color: #1d2327; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">';
		$html .= esc_html__( 'Link Smartly — Weekly Digest', 'link-smartly' ) . '</h2>';

		// Summary stats.
		$html .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
		$html .= '<tr>';
		$html .= '<td style="padding: 12px; background: #f0f6fc; text-align: center; border-radius: 4px;">';
		$html .= '<strong style="font-size: 24px; color: #2271b1;">' . esc_html( (string) $data['total_keywords'] ) . '</strong><br>';
		$html .= '<span style="color: #646970;">' . esc_html__( 'Keywords', 'link-smartly' ) . '</span></td>';
		$html .= '<td style="padding: 12px; background: #f0f6fc; text-align: center; border-radius: 4px;">';
		$html .= '<strong style="font-size: 24px; color: #00a32a;">' . esc_html( (string) $data['active'] ) . '</strong><br>';
		$html .= '<span style="color: #646970;">' . esc_html__( 'Active', 'link-smartly' ) . '</span></td>';
		$html .= '<td style="padding: 12px; background: #f0f6fc; text-align: center; border-radius: 4px;">';
		$html .= '<strong style="font-size: 24px; color: #2271b1;">' . esc_html( (string) $data['total_links'] ) . '</strong><br>';
		$html .= '<span style="color: #646970;">' . esc_html__( 'Links Inserted', 'link-smartly' ) . '</span></td>';
		$html .= '</tr></table>';

		// Top performers.
		if ( ! empty( $data['top_five'] ) ) {
			$html .= '<h3 style="color: #1d2327;">' . esc_html__( 'Top Performers', 'link-smartly' ) . '</h3>';
			$html .= '<table style="width: 100%; border-collapse: collapse;">';

			foreach ( $data['top_five'] as $item ) {
				$html .= '<tr style="border-bottom: 1px solid #dcdcde;">';
				$html .= '<td style="padding: 8px 0;">' . esc_html( $item['keyword'] ) . '</td>';
				$html .= '<td style="padding: 8px 0; text-align: right; font-weight: 600;">';

				/* translators: %d: number of links */
				$html .= esc_html( sprintf( _n( '%d link', '%d links', $item['link_count'], 'link-smartly' ), $item['link_count'] ) );
				$html .= '</td></tr>';
			}

			$html .= '</table>';
		}

		// Broken URLs.
		if ( ! empty( $data['broken_urls'] ) ) {
			$html .= '<h3 style="color: #d63638;">' . esc_html__( 'Broken URLs Detected', 'link-smartly' ) . '</h3>';
			$html .= '<ul style="padding-left: 20px;">';

			foreach ( $data['broken_urls'] as $url ) {
				$html .= '<li style="color: #d63638;">' . esc_html( $url ) . '</li>';
			}

			$html .= '</ul>';
		}

		// Zero-link keywords.
		if ( ! empty( $data['zero_links'] ) ) {
			$html .= '<h3 style="color: #dba617;">' . esc_html__( 'Keywords With No Links', 'link-smartly' ) . '</h3>';
			$html .= '<p style="color: #646970;">';
			$html .= esc_html__( 'These active keywords have not matched any content yet:', 'link-smartly' );
			$html .= '</p><ul style="padding-left: 20px;">';

			foreach ( array_slice( $data['zero_links'], 0, 10 ) as $kw ) {
				$html .= '<li>' . esc_html( $kw ) . '</li>';
			}

			if ( count( $data['zero_links'] ) > 10 ) {
				/* translators: %d: number of remaining keywords */
				$html .= '<li><em>' . esc_html( sprintf( __( 'and %d more…', 'link-smartly' ), count( $data['zero_links'] ) - 10 ) ) . '</em></li>';
			}

			$html .= '</ul>';
		}

		// CTA.
		$html .= '<p style="margin-top: 30px; text-align: center;">';
		$html .= '<a href="' . esc_url( $data['admin_url'] ) . '" style="display: inline-block; padding: 10px 24px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 600;">';
		$html .= esc_html__( 'View Full Dashboard', 'link-smartly' ) . '</a></p>';

		$html .= '<p style="color: #a7aaad; font-size: 12px; text-align: center; margin-top: 20px;">';
		/* translators: %s: site name */
		$html .= esc_html( sprintf( __( 'Sent by Link Smartly on %s', 'link-smartly' ), $data['site_name'] ) );
		$html .= '<br>';
		$html .= esc_html__( 'To stop receiving this digest, disable it in Link Smartly → Settings → Automation.', 'link-smartly' );
		$html .= '</p></div>';

		return $html;
	}
}

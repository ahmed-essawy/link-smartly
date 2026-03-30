<?php
/**
 * REST API endpoints for Link Smartly.
 *
 * @package LinkSmartly
 * @since   1.1.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST API routes for keywords CRUD and statistics.
 *
 * @since 1.1.0
 */
class Lsm_Rest {

	/**
	 * REST namespace.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const NAMESPACE = 'link-smartly/v1';

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
	 * Initialize hooks.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/keywords',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_keywords' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_keyword' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_keyword_args( true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/keywords/(?P<id>[a-f0-9-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_keyword' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_keyword' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_keyword_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_keyword' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Check if the request has manage_options permission.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if permitted.
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get all keyword mappings.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response with keywords.
	 */
	public function get_keywords( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->keywords->get_all(), 200 );
	}

	/**
	 * Get a single keyword mapping.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response with keyword or error.
	 */
	public function get_keyword( WP_REST_Request $request ): WP_REST_Response {
		$id  = sanitize_text_field( $request->get_param( 'id' ) );
		$all = $this->keywords->get_all();

		foreach ( $all as $entry ) {
			if ( $entry['id'] === $id ) {
				return new WP_REST_Response( $entry, 200 );
			}
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Keyword not found.', 'link-smartly' ) ),
			404
		);
	}

	/**
	 * Create a new keyword mapping.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response with the new entry.
	 */
	public function create_keyword( WP_REST_Request $request ): WP_REST_Response {
		$all   = $this->keywords->get_all();
		$entry = $this->keywords->sanitize_entry(
			array(
				'id'         => wp_generate_uuid4(),
				'keyword'    => $request->get_param( 'keyword' ),
				'url'        => $request->get_param( 'url' ),
				'active'     => (bool) $request->get_param( 'active' ),
				'group'      => $request->get_param( 'group' ) ?? '',
				'synonyms'   => $request->get_param( 'synonyms' ) ?? '',
				'max_uses'   => $request->get_param( 'max_uses' ) ?? 0,
				'nofollow'   => $request->get_param( 'nofollow' ) ?? 'default',
				'new_tab'    => $request->get_param( 'new_tab' ) ?? 'default',
				'start_date' => $request->get_param( 'start_date' ) ?? '',
				'end_date'   => $request->get_param( 'end_date' ) ?? '',
			)
		);

		$all[] = $entry;
		$this->keywords->save_all( $all );

		return new WP_REST_Response( $entry, 201 );
	}

	/**
	 * Update an existing keyword mapping.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response with updated entry or error.
	 */
	public function update_keyword( WP_REST_Request $request ): WP_REST_Response {
		$id   = sanitize_text_field( $request->get_param( 'id' ) );
		$data = array_filter(
			$request->get_params(),
			static fn( string $key ): bool => in_array(
				$key,
				array( 'keyword', 'url', 'active', 'group', 'synonyms', 'max_uses', 'nofollow', 'new_tab', 'start_date', 'end_date' ),
				true
			),
			ARRAY_FILTER_USE_KEY
		);

		if ( $this->keywords->update( $id, $data ) ) {
			$all = $this->keywords->get_all();

			foreach ( $all as $entry ) {
				if ( $entry['id'] === $id ) {
					return new WP_REST_Response( $entry, 200 );
				}
			}
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Keyword not found.', 'link-smartly' ) ),
			404
		);
	}

	/**
	 * Delete a keyword mapping.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response.
	 */
	public function delete_keyword( WP_REST_Request $request ): WP_REST_Response {
		$id = sanitize_text_field( $request->get_param( 'id' ) );

		if ( $this->keywords->delete( $id ) ) {
			return new WP_REST_Response( null, 204 );
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Keyword not found.', 'link-smartly' ) ),
			404
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response with settings.
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Lsm_Settings::get_all(), 200 );
	}

	/**
	 * Update plugin settings.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response with updated settings.
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$input = $request->get_params();
		Lsm_Settings::save( $input );

		return new WP_REST_Response( Lsm_Settings::get_all(), 200 );
	}

	/**
	 * Get link statistics.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Response with statistics.
	 */
	public function get_stats( WP_REST_Request $request ): WP_REST_Response {
		$all   = $this->keywords->get_all();
		$total = 0;
		$stats = array();

		foreach ( $all as $entry ) {
			$count   = $entry['link_count'] ?? 0;
			$total  += $count;
			$stats[] = array(
				'id'         => $entry['id'],
				'keyword'    => $entry['keyword'],
				'url'        => $entry['url'],
				'link_count' => $count,
				'group'      => $entry['group'] ?? '',
			);
		}

		usort( $stats, static fn( array $a, array $b ): int => $b['link_count'] <=> $a['link_count'] );

		return new WP_REST_Response(
			array(
				'total'    => $total,
				'keywords' => $stats,
			),
			200
		);
	}

	/**
	 * Get argument definitions for keyword create/update endpoints.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $required Whether keyword and url are required.
	 * @return array<string, array<string, mixed>> Argument definitions.
	 */
	private function get_keyword_args( bool $required ): array {
		return array(
			'keyword'    => array(
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'url'        => array(
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'esc_url_raw',
			),
			'active'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'group'      => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'synonyms'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'max_uses'   => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'nofollow'   => array(
				'type'    => 'string',
				'default' => 'default',
				'enum'    => array( 'default', 'yes', 'no' ),
			),
			'new_tab'    => array(
				'type'    => 'string',
				'default' => 'default',
				'enum'    => array( 'default', 'yes', 'no' ),
			),
			'start_date' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'end_date'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}

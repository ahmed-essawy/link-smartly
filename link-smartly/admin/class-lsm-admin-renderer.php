<?php
/**
 * Admin page rendering — notices, tabs, and preview results.
 *
 * @package LinkSmartly
 * @since   1.3.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders all admin tab content, notices, and preview results.
 *
 * @since 1.3.0
 */
class Lsm_Admin_Renderer {

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
	 * Render admin notices based on query parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['added'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Keyword mapping added.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Keyword mapping deleted.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['toggled'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Keyword mapping updated.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['edited'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Keyword mapping updated.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['bulk'] ) ) {
			$bulk_action = sanitize_key( wp_unslash( $_GET['bulk'] ) );
			$bulk_count  = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;

			if ( $bulk_count > 0 ) {
				$bulk_msg = '';

				switch ( $bulk_action ) {
					case 'activate':
						/* translators: %d: Number of keywords activated. */
						$bulk_msg = sprintf( _n( '%d keyword activated.', '%d keywords activated.', $bulk_count, 'link-smartly' ), $bulk_count );
						break;
					case 'deactivate':
						/* translators: %d: Number of keywords deactivated. */
						$bulk_msg = sprintf( _n( '%d keyword deactivated.', '%d keywords deactivated.', $bulk_count, 'link-smartly' ), $bulk_count );
						break;
					case 'delete':
						/* translators: %d: Number of keywords deleted. */
						$bulk_msg = sprintf( _n( '%d keyword deleted.', '%d keywords deleted.', $bulk_count, 'link-smartly' ), $bulk_count );
						break;
				}

				if ( '' !== $bulk_msg ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $bulk_msg ) . '</p></div>';
				}
			}
		}

		if ( isset( $_GET['undo_success'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Action undone. Keywords restored.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['undo_failed'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Could not undo. The undo data has expired.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['imported'] ) ) {
			$count = absint( $_GET['imported'] );
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf(
				/* translators: %d: Number of keyword mappings imported. */
				esc_html( _n( '%d keyword mapping imported.', '%d keyword mappings imported.', $count, 'link-smartly' ) ),
				(int) $count
			);
			echo '</p></div>';
		}

		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_key( wp_unslash( $_GET['error'] ) );
			$msg   = $this->get_error_message( $error );

			if ( '' !== $msg ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get a human-readable error message by error code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code Error code.
	 * @return string Error message.
	 */
	private function get_error_message( string $code ): string {
		$messages = array(
			'empty_fields'      => __( 'Both keyword and URL are required.', 'link-smartly' ),
			'invalid_csv'       => __( 'Invalid CSV file. Please upload a valid CSV file.', 'link-smartly' ),
			'upload_failed'     => __( 'File upload failed. Please try again.', 'link-smartly' ),
			'no_file'           => __( 'No file selected for import.', 'link-smartly' ),
			'invalid_post'      => __( 'Invalid post ID. Please enter a valid post ID.', 'link-smartly' ),
			'duplicate_keyword' => __( 'This keyword already exists. Duplicate keywords are not allowed.', 'link-smartly' ),
		);

		return $messages[ $code ] ?? '';
	}

	/**
	 * Render the keywords management tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $keywords All keyword mappings.
	 * @return void
	 */
	public function render_keywords_tab( array $keywords ): void {
		$groups = $this->keywords->get_groups();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search_term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter_group = isset( $_GET['group'] ) ? sanitize_text_field( wp_unslash( $_GET['group'] ) ) : '';
		$filter_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Apply filters.
		$filtered = $keywords;

		if ( '' !== $search_term ) {
			$filtered = array_filter(
				$filtered,
				static function ( array $entry ) use ( $search_term ): bool {
					return false !== mb_stripos( $entry['keyword'], $search_term )
						|| false !== mb_stripos( $entry['url'], $search_term )
						|| false !== mb_stripos( $entry['group'] ?? '', $search_term )
						|| false !== mb_stripos( $entry['synonyms'] ?? '', $search_term );
				}
			);
		}

		if ( '' !== $filter_group ) {
			$filtered = array_filter(
				$filtered,
				static function ( array $entry ) use ( $filter_group ): bool {
					return ( $entry['group'] ?? '' ) === $filter_group;
				}
			);
		}

		if ( '' !== $filter_status ) {
			$filtered = array_filter(
				$filtered,
				static function ( array $entry ) use ( $filter_status ): bool {
					if ( 'active' === $filter_status ) {
						return ! empty( $entry['active'] );
					}
					return empty( $entry['active'] );
				}
			);
		}

		$filtered = array_values( $filtered );
		?>
		<div class="lsm-keywords-section">
			<h2><?php esc_html_e( 'Add New Keyword Mapping', 'link-smartly' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-add-form">
				<input type="hidden" name="action" value="lsm_add_keyword" />
				<?php wp_nonce_field( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="lsm-keyword"><?php esc_html_e( 'Keyword Phrase', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="lsm-keyword"
								   name="lsm_keyword"
								   class="regular-text"
								   required
								   placeholder="<?php esc_attr_e( 'e.g., contact us', 'link-smartly' ); ?>"
								   aria-required="true" />
							<p class="description"><?php esc_html_e( 'The exact word or phrase to match in your content. Use natural phrases your visitors would read (e.g., "contact us", "pricing plans"). Case does not matter.', 'link-smartly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lsm-url"><?php esc_html_e( 'Target URL', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="url"
								   id="lsm-url"
								   name="lsm_url"
								   class="regular-text"
								   required
								   placeholder="<?php esc_attr_e( 'e.g., /contact/', 'link-smartly' ); ?>"
								   aria-required="true" />
							<p class="description"><?php esc_html_e( 'The page this keyword should link to. Use a relative path for pages on your own site (e.g., /contact/) or a full URL for external sites (e.g., https://example.com/page/). Tip: copy the URL from your browser address bar.', 'link-smartly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lsm-group"><?php esc_html_e( 'Group', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="lsm-group"
								   name="lsm_group"
								   class="regular-text"
								   placeholder="<?php esc_attr_e( 'e.g., Navigation, Products', 'link-smartly' ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. A label to organize your keywords (e.g., "Navigation", "Products", "Blog"). Leave empty if you don\'t need to group them. Groups only help you filter — they do not appear on your site.', 'link-smartly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lsm-synonyms"><?php esc_html_e( 'Synonyms', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="lsm-synonyms"
								   name="lsm_synonyms"
								   class="regular-text"
								   placeholder="<?php esc_attr_e( 'e.g., reach out, get in touch', 'link-smartly' ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. Other phrases that mean the same thing and should link to the same page. Separate with commas (e.g., "reach out, get in touch"). Leave empty if you only need the main keyword.', 'link-smartly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Advanced Options', 'link-smartly' ); ?></th>
						<td>
							<fieldset>
								<label for="lsm-max-uses">
									<?php esc_html_e( 'Max uses:', 'link-smartly' ); ?>
									<input type="number"
										   id="lsm-max-uses"
										   name="lsm_max_uses"
										   value="0"
										   min="0"
										   class="small-text" />
								</label>
								<p class="description"><?php esc_html_e( 'How many times this keyword can be auto-linked across all your posts. Leave at 0 for unlimited (recommended for most users).', 'link-smartly' ); ?></p>

								<br />
								<label for="lsm-nofollow-kw"><?php esc_html_e( 'Nofollow:', 'link-smartly' ); ?></label>
								<select id="lsm-nofollow-kw" name="lsm_nofollow_kw">
									<option value="default"><?php esc_html_e( 'Use global setting (recommended)', 'link-smartly' ); ?></option>
									<option value="yes"><?php esc_html_e( 'Yes — tell search engines not to follow', 'link-smartly' ); ?></option>
									<option value="no"><?php esc_html_e( 'No — let search engines follow', 'link-smartly' ); ?></option>
								</select>

								<label for="lsm-new-tab-kw" class="lsm-kw-inline-label"><?php esc_html_e( 'New tab:', 'link-smartly' ); ?></label>
								<select id="lsm-new-tab-kw" name="lsm_new_tab_kw">
									<option value="default"><?php esc_html_e( 'Use global setting (recommended)', 'link-smartly' ); ?></option>
									<option value="yes"><?php esc_html_e( 'Yes — open in new tab', 'link-smartly' ); ?></option>
									<option value="no"><?php esc_html_e( 'No — open in same tab', 'link-smartly' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Leave both on "Use global setting" unless you need this specific keyword to behave differently from your defaults in the Settings tab.', 'link-smartly' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Schedule', 'link-smartly' ); ?></th>
						<td>
							<label for="lsm-start-date"><?php esc_html_e( 'From:', 'link-smartly' ); ?></label>
							<input type="date" id="lsm-start-date" name="lsm_start_date" value="" />
							<label for="lsm-end-date" class="lsm-kw-inline-label"><?php esc_html_e( 'Until:', 'link-smartly' ); ?></label>
							<input type="date" id="lsm-end-date" name="lsm_end_date" value="" />
							<p class="description"><?php esc_html_e( 'Optional. Set a date range to auto-link this keyword only during a specific period (e.g., a seasonal promotion). Leave both empty to keep the keyword active indefinitely — this is the best choice for most keywords.', 'link-smartly' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Add Keyword', 'link-smartly' ), 'primary', 'submit', true ); ?>
			</form>

			<hr />

			<h2>
				<?php esc_html_e( 'Keyword Mappings', 'link-smartly' ); ?>
				<span class="lsm-count">(<?php echo esc_html( (string) count( $keywords ) ); ?>)</span>
			</h2>

			<?php // Search and filter bar. ?>
			<div class="lsm-filter-bar">
				<form method="get" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" class="lsm-search-form">
					<input type="hidden" name="page" value="<?php echo esc_attr( Lsm_Admin::PAGE_SLUG ); ?>" />
					<input type="hidden" name="tab" value="keywords" />

					<input type="search"
						   name="s"
						   value="<?php echo esc_attr( $search_term ); ?>"
						   placeholder="<?php esc_attr_e( 'Search keywords…', 'link-smartly' ); ?>"
						   class="lsm-search-input" />

					<?php if ( ! empty( $groups ) ) : ?>
						<select name="group" class="lsm-filter-select lsm-filter-group">
							<option value=""><?php esc_html_e( 'All Groups', 'link-smartly' ); ?></option>
							<?php foreach ( $groups as $group ) : ?>
								<option value="<?php echo esc_attr( $group ); ?>" <?php selected( $filter_group, $group ); ?>>
									<?php echo esc_html( $group ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>

					<select name="status" class="lsm-filter-select lsm-filter-status">
						<option value=""><?php esc_html_e( 'All Statuses', 'link-smartly' ); ?></option>
						<option value="active" <?php selected( $filter_status, 'active' ); ?>><?php esc_html_e( 'Active', 'link-smartly' ); ?></option>
						<option value="inactive" <?php selected( $filter_status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'link-smartly' ); ?></option>
					</select>

					<?php submit_button( esc_html__( 'Filter', 'link-smartly' ), 'secondary', 'submit', false ); ?>

					<?php if ( '' !== $search_term || '' !== $filter_group || '' !== $filter_status ) : ?>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . Lsm_Admin::PAGE_SLUG . '&tab=keywords' ) ); ?>" class="button lsm-clear-filters"><?php esc_html_e( 'Clear', 'link-smartly' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<?php if ( empty( $filtered ) ) : ?>
				<p><?php esc_html_e( 'No keyword mappings found. Add your first keyword above or import from CSV.', 'link-smartly' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="lsm-bulk-form">
					<input type="hidden" name="action" value="lsm_bulk_action" />
					<?php wp_nonce_field( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' ); ?>

					<div class="lsm-bulk-bar">
						<select name="lsm_bulk_action" class="lsm-bulk-select">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'link-smartly' ); ?></option>
							<option value="activate"><?php esc_html_e( 'Activate', 'link-smartly' ); ?></option>
							<option value="deactivate"><?php esc_html_e( 'Deactivate', 'link-smartly' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'link-smartly' ); ?></option>
						</select>
						<button type="submit" class="button lsm-bulk-apply-btn"><?php esc_html_e( 'Apply', 'link-smartly' ); ?></button>
					</div>

				<div class="lsm-table-scroll">
					<table class="widefat striped lsm-keywords-table">
						<thead>
							<tr>
								<th scope="col" class="lsm-col-check"><input type="checkbox" id="lsm-select-all" /></th>
								<th scope="col" class="lsm-col-keyword lsm-sortable" data-orderby="keyword"><?php esc_html_e( 'Keyword', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-url lsm-sortable" data-orderby="url"><?php esc_html_e( 'Target URL', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-group lsm-sortable" data-orderby="group"><?php esc_html_e( 'Group', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-status lsm-sortable" data-orderby="status"><?php esc_html_e( 'Status', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-links lsm-sortable" data-orderby="link_count"><?php esc_html_e( 'Links', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-actions"><?php esc_html_e( 'Actions', 'link-smartly' ); ?></th>
							</tr>
						</thead>
						<tbody class="lsm-keywords-tbody">
							<?php foreach ( $filtered as $entry ) : ?>
								<tr class="lsm-keyword-row" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
									<td class="lsm-col-check">
										<input type="checkbox" name="lsm_bulk_ids[]" value="<?php echo esc_attr( $entry['id'] ); ?>" class="lsm-row-check" />
									</td>
									<td class="lsm-col-keyword">
										<span class="lsm-keyword-text"><?php echo esc_html( $entry['keyword'] ); ?></span>
										<input type="text"
											   class="lsm-edit-keyword regular-text"
											   value="<?php echo esc_attr( $entry['keyword'] ); ?>"
											   aria-label="<?php esc_attr_e( 'Edit keyword', 'link-smartly' ); ?>"
											   style="display:none;" />
										<?php if ( ! empty( $entry['synonyms'] ) ) : ?>
											<br /><span class="lsm-synonyms-label"><?php echo esc_html( $entry['synonyms'] ); ?></span>
										<?php endif; ?>
									</td>
									<td class="lsm-col-url">
										<span class="lsm-url-text"><?php echo esc_html( $entry['url'] ); ?></span>
										<input type="text"
											   class="lsm-edit-url regular-text"
											   value="<?php echo esc_attr( $entry['url'] ); ?>"
											   aria-label="<?php esc_attr_e( 'Edit URL', 'link-smartly' ); ?>"
											   style="display:none;" />
									</td>
									<td class="lsm-col-group">
										<?php if ( ! empty( $entry['group'] ) ) : ?>
											<span class="lsm-group-badge"><?php echo esc_html( $entry['group'] ); ?></span>
										<?php else : ?>
											<span class="lsm-no-group">&mdash;</span>
										<?php endif; ?>
									</td>
									<td class="lsm-col-status">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form">
											<input type="hidden" name="action" value="lsm_toggle_keyword" />
											<input type="hidden" name="lsm_keyword_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
											<input type="hidden" name="lsm_active" value="<?php echo $entry['active'] ? '0' : '1'; ?>" />
											<?php wp_nonce_field( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' ); ?>
											<button type="submit"
													class="button lsm-toggle-btn <?php echo $entry['active'] ? 'lsm-active' : 'lsm-inactive'; ?>"
													aria-label="<?php echo $entry['active'] ? esc_attr__( 'Deactivate keyword', 'link-smartly' ) : esc_attr__( 'Activate keyword', 'link-smartly' ); ?>">
												<?php echo $entry['active'] ? esc_html__( 'Active', 'link-smartly' ) : esc_html__( 'Inactive', 'link-smartly' ); ?>
											</button>
										</form>
									</td>
									<td class="lsm-col-links">
										<?php echo esc_html( (string) ( $entry['link_count'] ?? 0 ) ); ?>
									</td>
									<td class="lsm-col-actions">
										<button type="button"
												class="button lsm-edit-btn"
												aria-label="<?php esc_attr_e( 'Edit this keyword mapping', 'link-smartly' ); ?>">
											<?php esc_html_e( 'Edit', 'link-smartly' ); ?>
										</button>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form lsm-edit-form" style="display:none;">
											<input type="hidden" name="action" value="lsm_edit_keyword" />
											<input type="hidden" name="lsm_keyword_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
											<input type="hidden" name="lsm_keyword" class="lsm-edit-keyword-hidden" value="" />
											<input type="hidden" name="lsm_url" class="lsm-edit-url-hidden" value="" />
											<input type="hidden" name="lsm_group" value="<?php echo esc_attr( $entry['group'] ?? '' ); ?>" />
											<input type="hidden" name="lsm_synonyms" value="<?php echo esc_attr( $entry['synonyms'] ?? '' ); ?>" />
											<input type="hidden" name="lsm_max_uses" value="<?php echo esc_attr( (string) ( $entry['max_uses'] ?? 0 ) ); ?>" />
											<input type="hidden" name="lsm_nofollow_kw" value="<?php echo esc_attr( $entry['nofollow'] ?? 'default' ); ?>" />
											<input type="hidden" name="lsm_new_tab_kw" value="<?php echo esc_attr( $entry['new_tab'] ?? 'default' ); ?>" />
											<input type="hidden" name="lsm_start_date" value="<?php echo esc_attr( $entry['start_date'] ?? '' ); ?>" />
											<input type="hidden" name="lsm_end_date" value="<?php echo esc_attr( $entry['end_date'] ?? '' ); ?>" />
											<?php wp_nonce_field( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' ); ?>
											<button type="submit"
													class="button button-primary lsm-save-edit-btn"
													aria-label="<?php esc_attr_e( 'Save changes', 'link-smartly' ); ?>">
												<?php esc_html_e( 'Save', 'link-smartly' ); ?>
											</button>
											<button type="button"
													class="button lsm-cancel-edit-btn"
													aria-label="<?php esc_attr_e( 'Cancel editing', 'link-smartly' ); ?>">
												<?php esc_html_e( 'Cancel', 'link-smartly' ); ?>
											</button>
										</form>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form lsm-delete-form">
											<input type="hidden" name="action" value="lsm_delete_keyword" />
											<input type="hidden" name="lsm_keyword_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
											<?php wp_nonce_field( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' ); ?>
											<button type="submit"
													class="button lsm-delete-btn"
													aria-label="<?php esc_attr_e( 'Delete this keyword mapping', 'link-smartly' ); ?>">
												<?php esc_html_e( 'Delete', 'link-smartly' ); ?>
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				</form>

				<div class="lsm-table-footer">
					<div class="lsm-pagination"></div>
					<div class="lsm-per-page-wrap">
						<label for="lsm-per-page"><?php esc_html_e( 'Per page:', 'link-smartly' ); ?></label>
						<select id="lsm-per-page" class="lsm-per-page-select">
							<option value="25">25</option>
							<option value="50">50</option>
							<option value="100">100</option>
						</select>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the analytics tab.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $keywords All keyword mappings.
	 * @return void
	 */
	public function render_analytics_tab( array $keywords ): void {
		$total_links  = 0;
		$linked_posts = $this->keywords->get_all_linked_posts();

		foreach ( $keywords as $entry ) {
			$total_links += (int) ( $entry['link_count'] ?? 0 );
		}

		// Count unique posts across all keywords.
		$unique_posts = array();

		foreach ( $linked_posts as $posts ) {
			if ( is_array( $posts ) ) {
				foreach ( array_keys( $posts ) as $pid ) {
					$unique_posts[ $pid ] = true;
				}
			}
		}

		// Sort by link_count descending.
		usort(
			$keywords,
			static function ( array $a, array $b ): int {
				return ( $b['link_count'] ?? 0 ) <=> ( $a['link_count'] ?? 0 );
			}
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$scanned = isset( $_GET['scanned'] ) ? absint( $_GET['scanned'] ) : -1;
		?>
		<div class="lsm-analytics-section">
			<h2><?php esc_html_e( 'Link Analytics', 'link-smartly' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These statistics track only links automatically inserted by Link Smartly. Manually added links in the post editor are not counted.', 'link-smartly' ); ?></p>

			<?php if ( $scanned >= 0 ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: number of posts scanned */
							esc_html__( 'Scan complete. %d posts scanned for keyword matches.', 'link-smartly' ),
							intval( $scanned )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="lsm-analytics-summary">
				<div class="lsm-stat-card">
					<span class="lsm-stat-number"><?php echo esc_html( (string) count( $keywords ) ); ?></span>
					<span class="lsm-stat-label"><?php esc_html_e( 'Total Keywords', 'link-smartly' ); ?></span>
				</div>
				<div class="lsm-stat-card">
					<span class="lsm-stat-number"><?php echo esc_html( (string) count( array_filter( $keywords, static fn( array $e ): bool => ! empty( $e['active'] ) ) ) ); ?></span>
					<span class="lsm-stat-label"><?php esc_html_e( 'Active Keywords', 'link-smartly' ); ?></span>
				</div>
				<div class="lsm-stat-card">
					<span class="lsm-stat-number"><?php echo esc_html( (string) $total_links ); ?></span>
					<span class="lsm-stat-label"><?php esc_html_e( 'Auto-Links Inserted', 'link-smartly' ); ?></span>
					<span class="lsm-stat-sub"><?php esc_html_e( 'by plugin only', 'link-smartly' ); ?></span>
				</div>
				<div class="lsm-stat-card">
					<span class="lsm-stat-number"><?php echo esc_html( (string) count( $unique_posts ) ); ?></span>
					<span class="lsm-stat-label"><?php esc_html_e( 'Posts With Links', 'link-smartly' ); ?></span>
				</div>
			</div>

			<div class="lsm-analytics-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form">
					<input type="hidden" name="action" value="lsm_scan_posts" />
					<?php wp_nonce_field( 'lsm_scan_posts', 'lsm_nonce' ); ?>
					<button type="submit" class="button button-primary" onclick="return window.confirm('<?php echo esc_js( __( 'Scan all published posts to discover where keywords are used? This may take a moment on large sites.', 'link-smartly' ) ); ?>');">
						<?php esc_html_e( 'Scan All Posts', 'link-smartly' ); ?>
					</button>
				</form>

				<?php if ( ! empty( $keywords ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form">
						<input type="hidden" name="action" value="lsm_reset_stats" />
						<?php wp_nonce_field( 'lsm_reset_stats', 'lsm_nonce' ); ?>
						<button type="submit" class="button" onclick="return window.confirm('<?php echo esc_js( __( 'Reset all link counts and post mappings to zero?', 'link-smartly' ) ); ?>');">
							<?php esc_html_e( 'Reset All Counts', 'link-smartly' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $keywords ) ) : ?>
				<div class="lsm-analytics-table-header">
					<div>
						<h3><?php esc_html_e( 'Keywords by Performance', 'link-smartly' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Click a keyword row to see which posts it was auto-linked in.', 'link-smartly' ); ?></p>
					</div>
					<button type="button" class="button lsm-expand-all-btn" data-expand-text="<?php esc_attr_e( 'Expand All', 'link-smartly' ); ?>" data-collapse-text="<?php esc_attr_e( 'Collapse All', 'link-smartly' ); ?>">
						<?php esc_html_e( 'Expand All', 'link-smartly' ); ?>
					</button>
				</div>
				<table class="widefat striped lsm-analytics-table">
					<thead>
						<tr>
							<th scope="col">#</th>
							<th scope="col"><?php esc_html_e( 'Keyword', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'URL', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Group', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Auto-Links', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Posts', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Max Uses', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'link-smartly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $keywords as $index => $entry ) :
							$kw_posts = $linked_posts[ $entry['id'] ] ?? array();
							$post_count = is_array( $kw_posts ) ? count( $kw_posts ) : 0;
						?>
							<tr class="lsm-analytics-row <?php echo $post_count > 0 ? 'lsm-has-posts' : ''; ?>" data-keyword-id="<?php echo esc_attr( $entry['id'] ); ?>">
								<td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
								<td>
									<?php if ( $post_count > 0 ) : ?>
										<span class="lsm-expand-arrow">&#9656;</span>
									<?php endif; ?>
									<?php echo esc_html( $entry['keyword'] ); ?>
								</td>
								<td><code><?php echo esc_html( $entry['url'] ); ?></code></td>
								<td><?php echo esc_html( $entry['group'] ?? '' ); ?></td>
								<td><strong><?php echo esc_html( (string) ( $entry['link_count'] ?? 0 ) ); ?></strong></td>
								<td>
									<?php if ( $post_count > 0 ) : ?>
										<span class="lsm-post-count-badge"><?php echo esc_html( (string) $post_count ); ?></span>
									<?php else : ?>
										<span class="lsm-no-posts">&mdash;</span>
									<?php endif; ?>
								</td>
								<td><?php echo 0 === (int) ( $entry['max_uses'] ?? 0 ) ? esc_html__( 'Unlimited', 'link-smartly' ) : esc_html( (string) $entry['max_uses'] ); ?></td>
								<td>
									<span class="lsm-status-dot <?php echo ! empty( $entry['active'] ) ? 'lsm-dot-active' : 'lsm-dot-inactive'; ?>"></span>
									<?php echo ! empty( $entry['active'] ) ? esc_html__( 'Active', 'link-smartly' ) : esc_html__( 'Inactive', 'link-smartly' ); ?>
								</td>
							</tr>
							<?php if ( $post_count > 0 ) : ?>
								<tr class="lsm-where-used-row" data-parent="<?php echo esc_attr( $entry['id'] ); ?>" style="display: none;">
									<td colspan="8">
										<div class="lsm-where-used-list">
											<strong><?php esc_html_e( 'Used in:', 'link-smartly' ); ?></strong>
											<ul>
												<?php foreach ( $kw_posts as $pid => $title ) : ?>
													<li>
														<a href="<?php echo esc_url( get_edit_post_link( (int) $pid ) ?? '' ); ?>">
															<?php echo esc_html( $title ); ?>
														</a>
														<span class="lsm-post-id">(#<?php echo esc_html( (string) $pid ); ?>)</span>
														<a href="<?php echo esc_url( (string) get_permalink( (int) $pid ) ); ?>" target="_blank" rel="noopener" class="lsm-view-link" aria-label="<?php esc_attr_e( 'View post', 'link-smartly' ); ?>">&#8599;</a>
														<a href="<?php echo esc_url( add_query_arg( 'lsm_highlight', '1', (string) get_permalink( (int) $pid ) ) ); ?>" target="_blank" rel="noopener" class="lsm-highlight-link" aria-label="<?php esc_attr_e( 'View with highlights', 'link-smartly' ); ?>" title="<?php esc_attr_e( 'View with auto-links highlighted', 'link-smartly' ); ?>">&#128269;</a>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php
			// Link Distribution by URL.
			if ( ! empty( $keywords ) ) :
				$url_dist = array();

				foreach ( $keywords as $entry ) {
					$url = $entry['url'] ?? '';

					if ( '' === $url ) {
						continue;
					}

					if ( ! isset( $url_dist[ $url ] ) ) {
						$url_dist[ $url ] = array(
							'url'        => $url,
							'keywords'   => 0,
							'link_count' => 0,
						);
					}

					++$url_dist[ $url ]['keywords'];
					$url_dist[ $url ]['link_count'] += (int) ( $entry['link_count'] ?? 0 );
				}

				// Sort by link_count descending.
				usort(
					$url_dist,
					static function ( array $a, array $b ): int {
						return $b['link_count'] <=> $a['link_count'];
					}
				);
			?>
				<h3><?php esc_html_e( 'Link Distribution by URL', 'link-smartly' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Shows how links are distributed across target URLs. Highlights pages that receive no links or an unusually high number.', 'link-smartly' ); ?></p>
				<table class="widefat striped lsm-distribution-table">
					<thead>
						<tr>
							<th scope="col">#</th>
							<th scope="col"><?php esc_html_e( 'Target URL', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Keywords Pointing', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Total Links', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Distribution', 'link-smartly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$max_dist_links = ! empty( $url_dist ) ? max( array_column( $url_dist, 'link_count' ) ) : 1;

						foreach ( $url_dist as $di => $dist ) :
							$bar_width = $max_dist_links > 0 ? (int) round( ( $dist['link_count'] / $max_dist_links ) * 100 ) : 0;
							$row_class = '';

							if ( 0 === $dist['link_count'] ) {
								$row_class = 'lsm-dist-zero';
							} elseif ( $dist['link_count'] > ( $total_links / max( count( $url_dist ), 1 ) ) * 3 ) {
								$row_class = 'lsm-dist-heavy';
							}
						?>
							<tr class="<?php echo esc_attr( $row_class ); ?>">
								<td><?php echo esc_html( (string) ( $di + 1 ) ); ?></td>
								<td><code><?php echo esc_html( $dist['url'] ); ?></code></td>
								<td><?php echo esc_html( (string) $dist['keywords'] ); ?></td>
								<td><strong><?php echo esc_html( (string) $dist['link_count'] ); ?></strong></td>
								<td>
									<div class="lsm-dist-bar-container">
										<div class="lsm-dist-bar" style="width: <?php echo esc_attr( (string) $bar_width ); ?>%;"></div>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php
			// Auto-Links by Post — inverted view for per-post verification.
			if ( ! empty( $linked_posts ) ) :
				$posts_view = array();

				foreach ( $linked_posts as $kw_id => $posts ) {
					if ( ! is_array( $posts ) ) {
						continue;
					}

					foreach ( $posts as $pid => $title ) {
						if ( ! isset( $posts_view[ $pid ] ) ) {
							$posts_view[ $pid ] = array(
								'title'    => $title,
								'keywords' => array(),
							);
						}

						// Find the keyword entry for this ID.
						foreach ( $keywords as $kw_entry ) {
							if ( $kw_entry['id'] === $kw_id ) {
								$posts_view[ $pid ]['keywords'][] = array(
									'keyword' => $kw_entry['keyword'],
									'url'     => $kw_entry['url'],
								);
								break;
							}
						}
					}
				}

				// Sort by number of keywords descending.
				uasort(
					$posts_view,
					static function ( array $a, array $b ): int {
						return count( $b['keywords'] ) <=> count( $a['keywords'] );
					}
				);
			?>
				<h3><?php esc_html_e( 'Auto-Links by Post', 'link-smartly' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Shows which auto-links the plugin inserted in each post. Use the highlight button to visually verify links on the front end.', 'link-smartly' ); ?></p>
				<table class="widefat striped lsm-posts-view-table">
					<thead>
						<tr>
							<th scope="col">#</th>
							<th scope="col"><?php esc_html_e( 'Post', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Auto-Links', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Keywords → URLs', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'link-smartly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$pv_index = 0;

						foreach ( $posts_view as $pid => $pv_data ) :
							++$pv_index;
						?>
							<tr>
								<td><?php echo esc_html( (string) $pv_index ); ?></td>
								<td>
									<strong><?php echo esc_html( $pv_data['title'] ); ?></strong>
									<span class="lsm-post-id">(#<?php echo esc_html( (string) $pid ); ?>)</span>
								</td>
								<td><span class="lsm-post-count-badge"><?php echo esc_html( (string) count( $pv_data['keywords'] ) ); ?></span></td>
								<td>
									<ul class="lsm-keyword-url-list">
										<?php foreach ( $pv_data['keywords'] as $kw_info ) : ?>
											<li>
												<strong><?php echo esc_html( $kw_info['keyword'] ); ?></strong>
												<span class="lsm-arrow">&rarr;</span>
												<code><?php echo esc_html( $kw_info['url'] ); ?></code>
											</li>
										<?php endforeach; ?>
									</ul>
								</td>
								<td class="lsm-pv-actions">
									<a href="<?php echo esc_url( get_edit_post_link( (int) $pid ) ?? '' ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'link-smartly' ); ?></a>
									<a href="<?php echo esc_url( (string) get_permalink( (int) $pid ) ); ?>" target="_blank" rel="noopener" class="button button-small"><?php esc_html_e( 'View', 'link-smartly' ); ?></a>
									<a href="<?php echo esc_url( add_query_arg( 'lsm_highlight', '1', (string) get_permalink( (int) $pid ) ) ); ?>" target="_blank" rel="noopener" class="button button-small lsm-btn-highlight"><?php esc_html_e( 'Highlight', 'link-smartly' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>              $settings   Current plugin settings.
	 * @param array<string, WP_Post_Type|object> $post_types Available post types.
	 * @return void
	 */
	public function render_settings_tab( array $settings, array $post_types ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lsm_save_settings" />
			<?php wp_nonce_field( Lsm_Admin::NONCE_SETTINGS, 'lsm_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Auto-Linking', 'link-smartly' ); ?></th>
					<td>
						<label for="lsm-enabled">
							<input type="checkbox"
								   id="lsm-enabled"
								   name="lsm_enabled"
								   value="1"
								   <?php checked( $settings['enabled'] ); ?> />
							<?php esc_html_e( 'Automatically insert internal links into content', 'link-smartly' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Master switch. When checked, the plugin scans your posts and adds links based on your keyword mappings. Uncheck to pause all auto-linking without losing your settings. Default: On.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="lsm-max-links"><?php esc_html_e( 'Max Links Per Post', 'link-smartly' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="lsm-max-links"
							   name="lsm_max_links"
							   value="<?php echo esc_attr( (string) $settings['max_links_per_post'] ); ?>"
							   min="1"
							   max="50"
							   step="1"
							   class="small-text" />
						<p class="description"><?php esc_html_e( 'How many auto-links can appear in a single post. Too many links look spammy to readers and search engines. Recommended: 3 for short posts, up to 5 for long-form content (2,000+ words). Default: 3.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="lsm-min-words"><?php esc_html_e( 'Minimum Content Words', 'link-smartly' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="lsm-min-words"
							   name="lsm_min_words"
							   value="<?php echo esc_attr( (string) $settings['min_content_words'] ); ?>"
							   min="0"
							   max="5000"
							   step="1"
							   class="small-text" />
						<p class="description"><?php esc_html_e( 'Posts shorter than this word count will not get any auto-links. Short posts with too many links look unnatural. Set to 0 to allow links in all posts regardless of length. Default: 300 words.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Post Types', 'link-smartly' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Post Types', 'link-smartly' ); ?></span></legend>
							<?php foreach ( $post_types as $pt ) : ?>
								<label>
									<input type="checkbox"
										   name="lsm_post_types[]"
										   value="<?php echo esc_attr( $pt->name ); ?>"
										   <?php checked( in_array( $pt->name, (array) $settings['post_types'], true ) ); ?> />
									<?php echo esc_html( $pt->labels->name ); ?>
								</label><br />
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Choose which content types get auto-links. Most sites only need Posts and Pages. Default: Posts and Pages.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="lsm-link-class"><?php esc_html_e( 'Link CSS Class', 'link-smartly' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="lsm-link-class"
							   name="lsm_link_class"
							   value="<?php echo esc_attr( $settings['link_class'] ); ?>"
							   class="regular-text" />
						<p class="description"><?php esc_html_e( 'A CSS class name added to every auto-generated link. Useful if you want to style them differently or track clicks in analytics. Leave the default unless you know what CSS classes are. Default: lsm-auto-link.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Link Attributes', 'link-smartly' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Link Attributes', 'link-smartly' ); ?></span></legend>
							<label>
								<input type="checkbox"
									   name="lsm_title_attr"
									   value="1"
									   <?php checked( $settings['add_title_attr'] ); ?> />
								<?php esc_html_e( 'Add title attribute to links', 'link-smartly' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Shows a tooltip when visitors hover over a link. Helpful for accessibility. Default: On.', 'link-smartly' ); ?></p>
							<br />
							<label>
								<input type="checkbox"
									   name="lsm_nofollow"
									   value="1"
									   <?php checked( $settings['nofollow'] ); ?> />
								<?php esc_html_e( 'Add rel="nofollow" to links', 'link-smartly' ); ?>
							</label>
							<p class="description lsm-desc-warning"><?php esc_html_e( 'Tells search engines not to pass SEO value through these links. Do NOT check this for internal links — it blocks link equity flow, which is the whole point of internal linking. Only check this if all your keyword links go to external sites. Default: Off.', 'link-smartly' ); ?></p>
							<br />
							<label>
								<input type="checkbox"
									   name="lsm_new_tab"
									   value="1"
									   <?php checked( $settings['new_tab'] ); ?> />
								<?php esc_html_e( 'Open links in a new tab', 'link-smartly' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Internal links usually should NOT open in a new tab — it can annoy readers. Only enable this if your links go to external sites. Default: Off.', 'link-smartly' ); ?></p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Automation', 'link-smartly' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Automation', 'link-smartly' ); ?></span></legend>
							<label>
								<input type="checkbox"
									   name="lsm_cron_health_check"
									   value="1"
									   <?php checked( $settings['cron_health_check'] ?? true ); ?> />
								<?php esc_html_e( 'Automatic weekly URL health checks', 'link-smartly' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Runs a background check on all your keyword target URLs once a week to detect broken links. Results appear in the Health tab. Default: On.', 'link-smartly' ); ?></p>
							<br />
							<label>
								<input type="checkbox"
									   name="lsm_email_digest"
									   value="1"
									   <?php checked( $settings['email_digest'] ?? false ); ?> />
								<?php esc_html_e( 'Weekly email digest', 'link-smartly' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Sends a weekly summary email with broken URLs, zero-link keywords, and top performers to the site admin. Default: Off.', 'link-smartly' ); ?></p>
						</fieldset>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the import/export tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_import_export_tab(): void {
		?>
		<div class="lsm-import-export-section">
			<h2><?php esc_html_e( 'Export Keywords', 'link-smartly' ); ?></h2>
			<p><?php esc_html_e( 'Download all your keyword mappings as a CSV file. You can open it in Excel or Google Sheets, or use it as a backup before making bulk changes.', 'link-smartly' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lsm_export_csv" />
				<?php wp_nonce_field( 'lsm_csv_action', 'lsm_nonce' ); ?>
				<?php submit_button( esc_html__( 'Export CSV', 'link-smartly' ), 'secondary', 'submit', true ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Export Analytics', 'link-smartly' ); ?></h2>
			<p><?php esc_html_e( 'Download a detailed report including link counts, posts linked, and URL health status for all keywords.', 'link-smartly' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lsm_export_analytics" />
				<?php wp_nonce_field( 'lsm_csv_action', 'lsm_nonce' ); ?>
				<?php submit_button( esc_html__( 'Export Analytics CSV', 'link-smartly' ), 'secondary', 'submit', true ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Import Keywords', 'link-smartly' ); ?></h2>
			<p><?php esc_html_e( 'Upload a CSV file to add keywords in bulk. Your CSV file must have at least two columns: keyword and url. Additional optional columns: active (1 or 0), group, synonyms, nofollow, new_tab, max_uses, start_date, end_date.', 'link-smartly' ); ?></p>
			<form method="post"
				  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				  enctype="multipart/form-data">
				<input type="hidden" name="action" value="lsm_import_csv" />
				<?php wp_nonce_field( 'lsm_csv_action', 'lsm_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="lsm-csv-file"><?php esc_html_e( 'CSV File', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="file"
								   id="lsm-csv-file"
								   name="lsm_csv_file"
								   accept=".csv"
								   required
								   aria-required="true" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Import Mode', 'link-smartly' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( 'Import Mode', 'link-smartly' ); ?></span></legend>
								<label>
									<input type="radio" name="lsm_import_mode" value="append" checked="checked" />
									<?php esc_html_e( 'Append — add new keywords to your existing list (safe, recommended)', 'link-smartly' ); ?>
								</label><br />
								<label>
									<input type="radio" name="lsm_import_mode" value="replace" />
									<?php esc_html_e( 'Replace — delete ALL existing keywords first, then import (use with caution!)', 'link-smartly' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Import CSV', 'link-smartly' ), 'primary', 'submit', true ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the preview/test tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_preview_tab(): void {
		$settings   = Lsm_Settings::get_all();
		$post_types = (array) ( $settings['post_types'] ?? array( 'post', 'page' ) );

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		?>
		<div class="lsm-preview-section">
			<h2><?php esc_html_e( 'Preview Auto-Links', 'link-smartly' ); ?></h2>
			<p class="lsm-preview-description">
				<?php esc_html_e( 'Select a post or page to see which auto-links would be inserted. This is a dry-run — no changes are saved to your content.', 'link-smartly' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-preview-form">
				<input type="hidden" name="action" value="lsm_preview" />
				<?php wp_nonce_field( 'lsm_preview_action', 'lsm_nonce' ); ?>

				<div class="lsm-preview-controls">
					<label for="lsm-preview-post-id" class="lsm-preview-label">
						<?php esc_html_e( 'Choose a post:', 'link-smartly' ); ?>
					</label>

					<?php if ( ! empty( $posts ) ) : ?>
						<select id="lsm-preview-post-id"
								name="lsm_post_id"
								class="lsm-preview-select"
								required
								aria-required="true">
							<option value=""><?php esc_html_e( '— Select a post or page —', 'link-smartly' ); ?></option>
							<?php
							$grouped = array();
							foreach ( $posts as $p ) {
								$type_obj   = get_post_type_object( $p->post_type );
								$type_label = $type_obj ? $type_obj->labels->singular_name : $p->post_type;
								$grouped[ $type_label ][] = $p;
							}
							ksort( $grouped );

							foreach ( $grouped as $type_label => $group_posts ) :
								?>
								<optgroup label="<?php echo esc_attr( $type_label ); ?>">
									<?php foreach ( $group_posts as $p ) : ?>
										<option value="<?php echo esc_attr( (string) $p->ID ); ?>">
											<?php echo esc_html( $p->post_title ); ?> (ID: <?php echo esc_html( (string) $p->ID ); ?>)
										</option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'No published posts found for the configured post types.', 'link-smartly' ); ?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $posts ) ) : ?>
						<?php submit_button( esc_html__( 'Preview Links', 'link-smartly' ), 'primary', 'submit', false ); ?>
					<?php endif; ?>
				</div>
			</form>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['preview_results'] ) ) {
				$this->render_preview_results();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render preview results from cache data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_preview_results(): void {
		$cache_key = 'preview_results_' . get_current_user_id();
		$results   = Lsm_Cache::get( $cache_key );
		Lsm_Cache::delete( $cache_key );

		if ( ! is_array( $results ) ) {
			return;
		}

		$links    = $results['links'] ?? array();
		$title    = $results['title'] ?? '';
		$excluded = ! empty( $results['excluded'] );
		?>
		<div class="lsm-preview-results">
			<h3>
				<span class="dashicons dashicons-visibility lsm-preview-results-icon"></span>
				<?php
				printf(
					/* translators: %s: Post title. */
					esc_html__( 'Preview Results for "%s"', 'link-smartly' ),
					esc_html( $title )
				);
				?>
			</h3>

			<?php if ( empty( $links ) ) : ?>
				<div class="lsm-preview-empty">
					<span class="dashicons dashicons-info-outline lsm-preview-empty-icon"></span>
					<?php if ( $excluded ) : ?>
						<p><strong><?php esc_html_e( 'Auto-linking is disabled for this post, so no links will be inserted.', 'link-smartly' ); ?></strong></p>
						<p><?php esc_html_e( 'Remove the post-level exclusion in the editor if you want Link Smartly to process this content.', 'link-smartly' ); ?></p>
					<?php else : ?>
						<p><strong><?php esc_html_e( 'No auto-links would be inserted for this content.', 'link-smartly' ); ?></strong></p>
						<p><?php esc_html_e( 'Possible reasons:', 'link-smartly' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'The content is shorter than the minimum word count setting.', 'link-smartly' ); ?></li>
							<li><?php esc_html_e( 'None of your keywords appear in the content.', 'link-smartly' ); ?></li>
							<li><?php esc_html_e( 'Matching keywords are inside headings, existing links, or code blocks.', 'link-smartly' ); ?></li>
							<li><?php esc_html_e( 'This post is excluded via the post-level setting.', 'link-smartly' ); ?></li>
						</ul>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="lsm-preview-summary">
					<span class="dashicons dashicons-yes-alt lsm-preview-summary-icon"></span>
					<?php
					printf(
						/* translators: %d: Number of links that would be inserted. */
						esc_html( _n(
							'%d auto-link would be inserted into this content.',
							'%d auto-links would be inserted into this content.',
							count( $links ),
							'link-smartly'
						) ),
						count( $links )
					);
					?>
				</div>

				<table class="widefat striped lsm-preview-table">
					<thead>
						<tr>
							<th scope="col" class="lsm-col-num">#</th>
							<th scope="col"><?php esc_html_e( 'Keyword Found', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Would Link To', 'link-smartly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $links as $index => $link ) : ?>
							<tr>
								<td class="lsm-col-num"><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
								<td><strong><?php echo esc_html( $link['keyword'] ); ?></strong></td>
								<td>
									<code><?php echo esc_html( $link['url'] ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}

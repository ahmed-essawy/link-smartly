/**
 * Link Smartly — Admin JavaScript
 *
 * Provides AJAX-powered CRUD, pagination, search/filter, and sorting
 * for the keywords table. Falls back to form submission when JS fails.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

/* global lsmAdmin */

(function() {
	'use strict';

	/* ------------------------------------------------------------------
	 * State
	 * ----------------------------------------------------------------*/

	var state = {
		page:    1,
		perPage: 25,
		search:  '',
		group:   '',
		status:  '',
		orderby: 'keyword',
		order:   'asc',
		loading: false
	};

	var debounceTimer = null;

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Build a FormData‑like query string for fetch POST.
	 *
	 * @param {Object} params Key–value pairs.
	 * @return {string} URL-encoded body string.
	 */
	function buildBody( params ) {
		var parts = [];
		var key;

		for ( key in params ) {
			if ( params.hasOwnProperty( key ) ) {
				if ( Array.isArray( params[ key ] ) ) {
					for ( var i = 0; i < params[ key ].length; i++ ) {
						parts.push( encodeURIComponent( key + '[]' ) + '=' + encodeURIComponent( params[ key ][ i ] ) );
					}
				} else {
					parts.push( encodeURIComponent( key ) + '=' + encodeURIComponent( params[ key ] ) );
				}
			}
		}

		return parts.join( '&' );
	}

	/**
	 * Send an AJAX POST request.
	 *
	 * @param {string}   action   WordPress AJAX action name.
	 * @param {Object}   data     Additional POST parameters.
	 * @param {Function} callback Called with (success, responseData).
	 */
	function ajaxPost( action, data, callback ) {
		if ( typeof lsmAdmin === 'undefined' || ! lsmAdmin.ajaxUrl ) {
			callback( false, { message: 'AJAX not available.' } );
			return;
		}

		data.action = action;
		data.nonce  = lsmAdmin.nonce;

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', lsmAdmin.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );

		xhr.onreadystatechange = function() {
			if ( 4 !== xhr.readyState ) {
				return;
			}

			var json;

			try {
				json = JSON.parse( xhr.responseText );
			} catch ( e ) {
				callback( false, { message: 'Invalid server response.' } );
				return;
			}

			callback( !! json.success, json.data || {} );
		};

		xhr.send( buildBody( data ) );
	}

	/**
	 * Show an admin notice inside the tab content area.
	 *
	 * @param {string} message  Notice text.
	 * @param {string} type     'success', 'error', or 'warning'.
	 */
	function showNotice( message, type ) {
		var container = document.querySelector( '.lsm-tab-content' );

		if ( ! container ) {
			return;
		}

		var existing = container.querySelectorAll( '.lsm-ajax-notice' );

		for ( var i = 0; i < existing.length; i++ ) {
			existing[ i ].parentNode.removeChild( existing[ i ] );
		}

		var notice = document.createElement( 'div' );
		notice.className = 'notice notice-' + type + ' is-dismissible lsm-ajax-notice';
		notice.innerHTML = '<p>' + escHtml( message ) + '</p>';
		container.insertBefore( notice, container.firstChild );

		// Auto-dismiss after 4 seconds.
		setTimeout( function() {
			if ( notice.parentNode ) {
				notice.parentNode.removeChild( notice );
			}
		}, 4000 );
	}

	/**
	 * Minimal HTML-entity escaping for display.
	 *
	 * @param {string} str Raw string.
	 * @return {string} Escaped HTML.
	 */
	function escHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	/* ------------------------------------------------------------------
	 * Initialization
	 * ----------------------------------------------------------------*/

	/**
	 * Initialize all event listeners when the DOM is ready.
	 */
	function init() {
		bindDeleteConfirmations();
		bindEditButtons();
		bindBulkActions();
		bindAnalyticsToggle();
		bindAjaxPagination();
		bindAjaxSearch();
		bindSortableHeaders();
		bindPerPageSelector();
		bindAjaxAddForm();
	}

	/* ------------------------------------------------------------------
	 * Legacy — Delete Confirmations (form-based)
	 * ----------------------------------------------------------------*/

	/**
	 * Bind confirmation dialogs to delete buttons.
	 */
	function bindDeleteConfirmations() {
		var deleteForms = document.querySelectorAll( '.lsm-delete-form' );

		for ( var i = 0; i < deleteForms.length; i++ ) {
			deleteForms[ i ].addEventListener( 'submit', function( event ) {
				var message = ( typeof lsmAdmin !== 'undefined' && lsmAdmin.confirmDelete )
					? lsmAdmin.confirmDelete
					: 'Are you sure you want to delete this keyword mapping?';

				if ( ! window.confirm( message ) ) {
					event.preventDefault();
					return;
				}

				// Intercept and use AJAX if available.
				if ( typeof lsmAdmin !== 'undefined' && lsmAdmin.ajaxUrl ) {
					event.preventDefault();
					var form = event.target;
					var idInput = form.querySelector( 'input[name="lsm_keyword_id"]' );

					if ( ! idInput ) {
						return;
					}

					ajaxPost( 'lsm_delete_keyword', { id: idInput.value }, function( success, data ) {
						if ( success ) {
							showNotice( data.message || 'Deleted.', 'success' );
							fetchKeywords();
						} else {
							showNotice( data.message || 'Delete failed.', 'error' );
						}
					});
				}
			});
		}
	}

	/* ------------------------------------------------------------------
	 * Legacy — Inline Edit (form-based with AJAX enhancement)
	 * ----------------------------------------------------------------*/

	/**
	 * Bind inline edit functionality (edit, save, cancel).
	 */
	function bindEditButtons() {
		var editButtons = document.querySelectorAll( '.lsm-edit-btn' );

		for ( var i = 0; i < editButtons.length; i++ ) {
			editButtons[ i ].addEventListener( 'click', handleEditClick );
		}

		var cancelButtons = document.querySelectorAll( '.lsm-cancel-edit-btn' );

		for ( var j = 0; j < cancelButtons.length; j++ ) {
			cancelButtons[ j ].addEventListener( 'click', handleCancelClick );
		}

		var editForms = document.querySelectorAll( '.lsm-edit-form' );

		for ( var k = 0; k < editForms.length; k++ ) {
			editForms[ k ].addEventListener( 'submit', handleEditSubmit );
		}
	}

	/**
	 * Handle the Edit button click — show inline inputs, hide text.
	 *
	 * @param {Event} event The click event.
	 */
	function handleEditClick( event ) {
		var row = event.target.closest( '.lsm-keyword-row' );

		if ( ! row ) {
			return;
		}

		var keywordText  = row.querySelector( '.lsm-keyword-text' );
		var keywordInput = row.querySelector( '.lsm-edit-keyword' );
		var urlText      = row.querySelector( '.lsm-url-text' );
		var urlInput     = row.querySelector( '.lsm-edit-url' );
		var editBtn      = row.querySelector( '.lsm-edit-btn' );
		var editForm     = row.querySelector( '.lsm-edit-form' );
		var deleteForm   = row.querySelector( '.lsm-delete-form' );

		keywordText.style.display  = 'none';
		keywordInput.style.display = '';
		urlText.style.display      = 'none';
		urlInput.style.display     = '';
		editBtn.style.display      = 'none';
		editForm.style.display     = 'inline-block';

		if ( deleteForm ) {
			deleteForm.style.display = 'none';
		}

		keywordInput.focus();
	}

	/**
	 * Handle the Cancel button click — restore text display, hide inputs.
	 *
	 * @param {Event} event The click event.
	 */
	function handleCancelClick( event ) {
		var row = event.target.closest( '.lsm-keyword-row' );

		if ( ! row ) {
			return;
		}

		var keywordText  = row.querySelector( '.lsm-keyword-text' );
		var keywordInput = row.querySelector( '.lsm-edit-keyword' );
		var urlText      = row.querySelector( '.lsm-url-text' );
		var urlInput     = row.querySelector( '.lsm-edit-url' );
		var editBtn      = row.querySelector( '.lsm-edit-btn' );
		var editForm     = row.querySelector( '.lsm-edit-form' );
		var deleteForm   = row.querySelector( '.lsm-delete-form' );

		keywordInput.value = keywordText.textContent;
		urlInput.value     = urlText.textContent;

		keywordText.style.display  = '';
		keywordInput.style.display = 'none';
		urlText.style.display      = '';
		urlInput.style.display     = 'none';
		editBtn.style.display      = '';
		editForm.style.display     = 'none';

		if ( deleteForm ) {
			deleteForm.style.display = '';
		}
	}

	/**
	 * Handle the edit form submit — AJAX if available, else hidden fields fallback.
	 *
	 * @param {Event} event The submit event.
	 */
	function handleEditSubmit( event ) {
		var row = event.target.closest( '.lsm-keyword-row' );

		if ( ! row ) {
			return;
		}

		var keywordInput  = row.querySelector( '.lsm-edit-keyword' );
		var urlInput      = row.querySelector( '.lsm-edit-url' );
		var keywordHidden = row.querySelector( '.lsm-edit-keyword-hidden' );
		var urlHidden     = row.querySelector( '.lsm-edit-url-hidden' );

		if ( keywordHidden ) {
			keywordHidden.value = keywordInput.value;
		}

		if ( urlHidden ) {
			urlHidden.value = urlInput.value;
		}

		// Intercept for AJAX.
		if ( typeof lsmAdmin !== 'undefined' && lsmAdmin.ajaxUrl ) {
			event.preventDefault();
			var idInput = row.querySelector( 'input[name="lsm_keyword_id"]' );

			if ( ! idInput ) {
				return;
			}

			ajaxPost( 'lsm_edit_keyword', {
				id:      idInput.value,
				keyword: keywordInput.value,
				url:     urlInput.value
			}, function( success, data ) {
				if ( success ) {
					showNotice( data.message || 'Updated.', 'success' );
					fetchKeywords();
				} else {
					showNotice( data.message || 'Update failed.', 'error' );
				}
			});
		}
	}

	/* ------------------------------------------------------------------
	 * Bulk Actions (with AJAX enhancement)
	 * ----------------------------------------------------------------*/

	/**
	 * Bind bulk action controls: select-all checkbox and submit confirmation.
	 */
	function bindBulkActions() {
		var selectAll = document.getElementById( 'lsm-select-all' );

		if ( selectAll ) {
			selectAll.addEventListener( 'change', function() {
				var checkboxes = document.querySelectorAll( '.lsm-row-check' );

				for ( var i = 0; i < checkboxes.length; i++ ) {
					checkboxes[ i ].checked = selectAll.checked;
				}
			});
		}

		var bulkForm = document.getElementById( 'lsm-bulk-form' );

		if ( bulkForm ) {
			bulkForm.addEventListener( 'submit', function( event ) {
				var selectEl = bulkForm.querySelector( '.lsm-bulk-select' );
				var action   = selectEl ? selectEl.value : '';

				if ( '' === action ) {
					event.preventDefault();
					return;
				}

				var checked = bulkForm.querySelectorAll( '.lsm-row-check:checked' );

				if ( 0 === checked.length ) {
					event.preventDefault();
					return;
				}

				if ( 'delete' === action ) {
					var message = ( typeof lsmAdmin !== 'undefined' && lsmAdmin.confirmBulkDelete )
						? lsmAdmin.confirmBulkDelete
						: 'Are you sure you want to delete the selected keyword mappings?';

					if ( ! window.confirm( message ) ) {
						event.preventDefault();
						return;
					}
				}

				// Intercept for AJAX.
				if ( typeof lsmAdmin !== 'undefined' && lsmAdmin.ajaxUrl ) {
					event.preventDefault();
					var ids = [];

					for ( var i = 0; i < checked.length; i++ ) {
						ids.push( checked[ i ].value );
					}

					ajaxPost( 'lsm_bulk_action', {
						bulk_action: action,
						ids:         ids
					}, function( success, data ) {
						if ( success ) {
							showNotice( data.message || 'Done.', 'success' );
							fetchKeywords();
						} else {
							showNotice( data.message || 'Bulk action failed.', 'error' );
						}
					});
				}
			});
		}
	}

	/* ------------------------------------------------------------------
	 * AJAX Add Form
	 * ----------------------------------------------------------------*/

	/**
	 * Intercept the "Add New Keyword" form for AJAX submission.
	 */
	function bindAjaxAddForm() {
		var addForm = document.querySelector( '.lsm-add-form' );

		if ( ! addForm || typeof lsmAdmin === 'undefined' || ! lsmAdmin.ajaxUrl ) {
			return;
		}

		addForm.addEventListener( 'submit', function( event ) {
			event.preventDefault();

			var keyword   = addForm.querySelector( '[name="lsm_keyword"]' );
			var url       = addForm.querySelector( '[name="lsm_url"]' );
			var group     = addForm.querySelector( '[name="lsm_group"]' );
			var synonyms  = addForm.querySelector( '[name="lsm_synonyms"]' );
			var maxUses   = addForm.querySelector( '[name="lsm_max_uses"]' );
			var nofollow  = addForm.querySelector( '[name="lsm_nofollow_kw"]' );
			var newTab    = addForm.querySelector( '[name="lsm_new_tab_kw"]' );
			var startDate = addForm.querySelector( '[name="lsm_start_date"]' );
			var endDate   = addForm.querySelector( '[name="lsm_end_date"]' );

			var data = {
				keyword:     keyword   ? keyword.value   : '',
				url:         url       ? url.value       : '',
				group:       group     ? group.value     : '',
				synonyms:    synonyms  ? synonyms.value  : '',
				max_uses:    maxUses   ? maxUses.value   : '0',
				nofollow_kw: nofollow  ? nofollow.value  : 'default',
				new_tab_kw:  newTab    ? newTab.value    : 'default',
				start_date:  startDate ? startDate.value : '',
				end_date:    endDate   ? endDate.value   : ''
			};

			ajaxPost( 'lsm_add_keyword', data, function( success, respData ) {
				if ( success ) {
					showNotice( respData.message || 'Added.', 'success' );
					addForm.reset();
					fetchKeywords();
				} else {
					showNotice( respData.message || 'Add failed.', 'error' );
				}
			});
		});
	}

	/* ------------------------------------------------------------------
	 * AJAX Toggle (delegated)
	 * ----------------------------------------------------------------*/

	/**
	 * Handle toggle button clicks via event delegation on the table.
	 *
	 * @param {Event} event The click event.
	 */
	function handleToggleClick( event ) {
		var btn = event.target.closest( '.lsm-toggle-btn' );

		if ( ! btn ) {
			return;
		}

		// Only intercept if AJAX is available; otherwise let the form submit naturally.
		if ( typeof lsmAdmin === 'undefined' || ! lsmAdmin.ajaxUrl ) {
			return;
		}

		var form = btn.closest( '.lsm-inline-form' );

		if ( ! form ) {
			return;
		}

		event.preventDefault();

		var idInput     = form.querySelector( 'input[name="lsm_keyword_id"]' );
		var activeInput = form.querySelector( 'input[name="lsm_active"]' );

		if ( ! idInput ) {
			return;
		}

		// Toggle: current button class tells us what state it's in now.
		var currentlyActive = btn.classList.contains( 'lsm-active' );
		var newActive       = currentlyActive ? '0' : '1';

		ajaxPost( 'lsm_toggle_keyword', {
			id:     idInput.value,
			active: newActive
		}, function( success, data ) {
			if ( success ) {
				fetchKeywords();
			} else {
				showNotice( data.message || 'Toggle failed.', 'error' );
			}
		});
	}

	/* ------------------------------------------------------------------
	 * AJAX Pagination
	 * ----------------------------------------------------------------*/

	/**
	 * Bind click handlers for pagination links (delegated).
	 */
	function bindAjaxPagination() {
		var paginationWrap = document.querySelector( '.lsm-pagination' );

		if ( ! paginationWrap ) {
			return;
		}

		paginationWrap.addEventListener( 'click', function( event ) {
			var link = event.target.closest( '.lsm-page-link' );

			if ( ! link ) {
				return;
			}

			event.preventDefault();
			var targetPage = parseInt( link.getAttribute( 'data-page' ), 10 );

			if ( targetPage > 0 ) {
				state.page = targetPage;
				fetchKeywords();
			}
		});
	}

	/**
	 * Bind per-page selector change events.
	 */
	function bindPerPageSelector() {
		var selector = document.querySelector( '.lsm-per-page-select' );

		if ( ! selector ) {
			return;
		}

		selector.addEventListener( 'change', function() {
			state.perPage = parseInt( selector.value, 10 ) || 25;
			state.page    = 1;
			fetchKeywords();
		});
	}

	/* ------------------------------------------------------------------
	 * AJAX Search / Filter
	 * ----------------------------------------------------------------*/

	/**
	 * Bind search input and filter dropdowns with debounced AJAX fetch.
	 */
	function bindAjaxSearch() {
		var searchInput  = document.querySelector( '.lsm-search-input' );
		var groupFilter  = document.querySelector( '.lsm-filter-group' );
		var statusFilter = document.querySelector( '.lsm-filter-status' );
		var clearBtn     = document.querySelector( '.lsm-clear-filters' );
		var searchForm   = document.querySelector( '.lsm-search-form' );

		// Prevent form submission (no-JS fallback) when JS is active.
		if ( searchForm ) {
			searchForm.addEventListener( 'submit', function( event ) {
				event.preventDefault();
				state.page = 1;
				fetchKeywords();
			});
		}

		if ( searchInput ) {
			searchInput.addEventListener( 'input', function() {
				state.search = searchInput.value;
				state.page   = 1;
				debounce( fetchKeywords, 300 );
			});
		}

		if ( groupFilter ) {
			groupFilter.addEventListener( 'change', function() {
				state.group = groupFilter.value;
				state.page  = 1;
				fetchKeywords();
			});
		}

		if ( statusFilter ) {
			statusFilter.addEventListener( 'change', function() {
				state.status = statusFilter.value;
				state.page   = 1;
				fetchKeywords();
			});
		}

		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function( event ) {
				event.preventDefault();
				state.search  = '';
				state.group   = '';
				state.status  = '';
				state.page    = 1;

				if ( searchInput )  { searchInput.value  = ''; }
				if ( groupFilter )  { groupFilter.value  = ''; }
				if ( statusFilter ) { statusFilter.value = ''; }

				fetchKeywords();
			});
		}
	}

	/**
	 * Debounce a function call.
	 *
	 * @param {Function} fn    The function to debounce.
	 * @param {number}   delay Milliseconds to wait.
	 */
	function debounce( fn, delay ) {
		if ( debounceTimer ) {
			clearTimeout( debounceTimer );
		}

		debounceTimer = setTimeout( fn, delay );
	}

	/* ------------------------------------------------------------------
	 * Sortable Column Headers
	 * ----------------------------------------------------------------*/

	/**
	 * Bind click handlers for sortable table headers.
	 */
	function bindSortableHeaders() {
		var headers = document.querySelectorAll( '.lsm-sortable' );

		for ( var i = 0; i < headers.length; i++ ) {
			headers[ i ].addEventListener( 'click', function( event ) {
				var th     = event.currentTarget;
				var column = th.getAttribute( 'data-orderby' );

				if ( ! column ) {
					return;
				}

				if ( state.orderby === column ) {
					state.order = ( 'asc' === state.order ) ? 'desc' : 'asc';
				} else {
					state.orderby = column;
					state.order   = 'asc';
				}

				state.page = 1;
				fetchKeywords();
			});
		}
	}

	/* ------------------------------------------------------------------
	 * AJAX Fetch & Render Keywords
	 * ----------------------------------------------------------------*/

	/**
	 * Fetch keywords from the server and re-render the table.
	 */
	function fetchKeywords() {
		if ( state.loading ) {
			return;
		}

		if ( typeof lsmAdmin === 'undefined' || ! lsmAdmin.ajaxUrl ) {
			return;
		}

		state.loading = true;
		setTableLoading( true );

		ajaxPost( 'lsm_fetch_keywords', {
			page:     state.page,
			per_page: state.perPage,
			search:   state.search,
			group:    state.group,
			status:   state.status,
			orderby:  state.orderby,
			order:    state.order
		}, function( success, data ) {
			state.loading = false;
			setTableLoading( false );

			if ( success ) {
				renderKeywordsTable( data.items || [], data.total || 0, data.total_pages || 1 );
				renderPagination( data.page || 1, data.total_pages || 1, data.total || 0 );
				updateSortIndicators();
			}
		});
	}

	/**
	 * Show/hide a loading overlay on the keywords table.
	 *
	 * @param {boolean} loading Whether loading is active.
	 */
	function setTableLoading( loading ) {
		var table = document.querySelector( '.lsm-keywords-table' );

		if ( ! table ) {
			return;
		}

		table.style.opacity = loading ? '0.5' : '1';
	}

	/**
	 * Render the keywords table body from AJAX data.
	 *
	 * @param {Array}  items Array of keyword objects.
	 * @param {number} total Total matching items.
	 * @param {number} totalPages Total pages.
	 */
	function renderKeywordsTable( items, total, totalPages ) {
		var tbody = document.querySelector( '.lsm-keywords-tbody' );

		if ( ! tbody ) {
			return;
		}

		if ( 0 === items.length ) {
			tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;">' +
				escHtml( lsmAdmin.noResults || 'No keyword mappings found.' ) + '</td></tr>';
			return;
		}

		var html = '';

		for ( var i = 0; i < items.length; i++ ) {
			var item      = items[ i ];
			var activeClass = item.active ? 'lsm-active' : 'lsm-inactive';
			var activeText  = item.active
				? ( lsmAdmin.textActive || 'Active' )
				: ( lsmAdmin.textInactive || 'Inactive' );
			var groupBadge  = item.group
				? '<span class="lsm-group-badge">' + escHtml( item.group ) + '</span>'
				: '<span class="lsm-no-group">&mdash;</span>';
			var healthBadge = '';

			if ( typeof lsmAdmin.healthResults !== 'undefined' && lsmAdmin.healthResults[ item.url ] ) {
				var hs = lsmAdmin.healthResults[ item.url ].status;
				var hClass = 'lsm-health-unknown';

				if ( 'ok' === hs ) {
					hClass = 'lsm-health-ok';
				} else if ( 'redirect' === hs ) {
					hClass = 'lsm-health-redirect';
				} else if ( 'error' === hs ) {
					hClass = 'lsm-health-error';
				}

				healthBadge = ' <span class="lsm-health-badge ' + hClass + '" title="' + escHtml( hs ) + '">&bull;</span>';
			}

			html += '<tr class="lsm-keyword-row" data-id="' + escHtml( item.id ) + '">';
			html += '<td class="lsm-col-check"><input type="checkbox" class="lsm-row-check" value="' + escHtml( item.id ) + '" /></td>';
			html += '<td class="lsm-col-keyword"><span class="lsm-keyword-text">' + escHtml( item.keyword ) + '</span>';

			if ( item.synonyms ) {
				html += '<br><span class="lsm-synonyms-label">' + escHtml( item.synonyms ) + '</span>';
			}

			html += '<input type="text" class="lsm-edit-keyword regular-text" value="' + escHtml( item.keyword ) + '" style="display:none" />';
			html += '</td>';
			html += '<td class="lsm-col-url"><span class="lsm-url-text">' + escHtml( item.url ) + '</span>' + healthBadge;
			html += '<input type="text" class="lsm-edit-url regular-text" value="' + escHtml( item.url ) + '" style="display:none" />';
			html += '</td>';
			html += '<td class="lsm-col-group">' + groupBadge + '</td>';
			html += '<td class="lsm-col-status">';
			html += '<button type="button" class="button button-small lsm-toggle-btn ' + activeClass +
				'" data-id="' + escHtml( item.id ) + '" data-active="' + ( item.active ? '1' : '0' ) + '">' +
				escHtml( activeText ) + '</button>';
			html += '</td>';
			html += '<td class="lsm-col-links">' + ( item.link_count || 0 ) + '</td>';
			html += '<td class="lsm-col-actions">';
			html += '<button type="button" class="button button-small lsm-edit-btn">' + escHtml( lsmAdmin.textEdit || 'Edit' ) + '</button> ';
			html += '<button type="button" class="button button-small lsm-delete-btn lsm-ajax-delete-btn" data-id="' + escHtml( item.id ) + '">' + escHtml( lsmAdmin.textDelete || 'Delete' ) + '</button>';
			html += '</td>';
			html += '</tr>';
		}

		tbody.innerHTML = html;

		// Re-bind events for the new rows.
		bindDynamicRowEvents();
	}

	/**
	 * Bind events on dynamically rendered table rows.
	 */
	function bindDynamicRowEvents() {
		// Edit buttons.
		var editBtns = document.querySelectorAll( '.lsm-keywords-tbody .lsm-edit-btn' );

		for ( var i = 0; i < editBtns.length; i++ ) {
			editBtns[ i ].addEventListener( 'click', handleDynamicEditClick );
		}

		// Delete buttons.
		var delBtns = document.querySelectorAll( '.lsm-keywords-tbody .lsm-ajax-delete-btn' );

		for ( var j = 0; j < delBtns.length; j++ ) {
			delBtns[ j ].addEventListener( 'click', handleDynamicDeleteClick );
		}

		// Toggle buttons.
		var toggleBtns = document.querySelectorAll( '.lsm-keywords-tbody .lsm-toggle-btn' );

		for ( var k = 0; k < toggleBtns.length; k++ ) {
			toggleBtns[ k ].addEventListener( 'click', handleDynamicToggleClick );
		}

		// Select-all checkbox reset.
		var selectAll = document.getElementById( 'lsm-select-all' );

		if ( selectAll ) {
			selectAll.checked = false;
		}
	}

	/**
	 * Handle edit click on a dynamically rendered row.
	 *
	 * @param {Event} event The click event.
	 */
	function handleDynamicEditClick( event ) {
		var row = event.target.closest( '.lsm-keyword-row' );

		if ( ! row ) {
			return;
		}

		var keywordText  = row.querySelector( '.lsm-keyword-text' );
		var keywordInput = row.querySelector( '.lsm-edit-keyword' );
		var urlText      = row.querySelector( '.lsm-url-text' );
		var urlInput     = row.querySelector( '.lsm-edit-url' );
		var editBtn      = row.querySelector( '.lsm-edit-btn' );
		var deleteBtn    = row.querySelector( '.lsm-ajax-delete-btn' );

		if ( keywordText )  { keywordText.style.display  = 'none'; }
		if ( keywordInput ) { keywordInput.style.display = ''; }
		if ( urlText )      { urlText.style.display      = 'none'; }
		if ( urlInput )     { urlInput.style.display     = ''; }
		if ( editBtn )      { editBtn.style.display      = 'none'; }
		if ( deleteBtn )    { deleteBtn.style.display     = 'none'; }

		// Create save/cancel buttons.
		var actions = row.querySelector( '.lsm-col-actions' );

		if ( actions && ! actions.querySelector( '.lsm-save-edit-btn' ) ) {
			var saveBtn = document.createElement( 'button' );
			saveBtn.type      = 'button';
			saveBtn.className = 'button button-small button-primary lsm-save-edit-btn';
			saveBtn.textContent = lsmAdmin.textSave || 'Save';

			var cancelBtn = document.createElement( 'button' );
			cancelBtn.type      = 'button';
			cancelBtn.className = 'button button-small lsm-cancel-edit-btn';
			cancelBtn.textContent = lsmAdmin.textCancel || 'Cancel';
			cancelBtn.style.marginLeft = '4px';

			saveBtn.addEventListener( 'click', function() {
				var id = row.getAttribute( 'data-id' );

				ajaxPost( 'lsm_edit_keyword', {
					id:      id,
					keyword: keywordInput.value,
					url:     urlInput.value
				}, function( success, data ) {
					if ( success ) {
						showNotice( data.message || 'Updated.', 'success' );
						fetchKeywords();
					} else {
						showNotice( data.message || 'Update failed.', 'error' );
					}
				});
			});

			cancelBtn.addEventListener( 'click', function() {
				fetchKeywords();
			});

			actions.appendChild( saveBtn );
			actions.appendChild( cancelBtn );
		}

		if ( keywordInput ) {
			keywordInput.focus();
		}
	}

	/**
	 * Handle delete click on a dynamically rendered row.
	 *
	 * @param {Event} event The click event.
	 */
	function handleDynamicDeleteClick( event ) {
		var btn = event.target.closest( '.lsm-ajax-delete-btn' );

		if ( ! btn ) {
			return;
		}

		var message = ( typeof lsmAdmin !== 'undefined' && lsmAdmin.confirmDelete )
			? lsmAdmin.confirmDelete
			: 'Are you sure you want to delete this keyword mapping?';

		if ( ! window.confirm( message ) ) {
			return;
		}

		var id = btn.getAttribute( 'data-id' );

		ajaxPost( 'lsm_delete_keyword', { id: id }, function( success, data ) {
			if ( success ) {
				showNotice( data.message || 'Deleted.', 'success' );
				fetchKeywords();
			} else {
				showNotice( data.message || 'Delete failed.', 'error' );
			}
		});
	}

	/**
	 * Handle toggle click on a dynamically rendered row.
	 *
	 * @param {Event} event The click event.
	 */
	function handleDynamicToggleClick( event ) {
		var btn = event.target.closest( '.lsm-toggle-btn' );

		if ( ! btn ) {
			return;
		}

		var id            = btn.getAttribute( 'data-id' );
		var currentActive = btn.getAttribute( 'data-active' );
		var newActive     = ( '1' === currentActive ) ? '0' : '1';

		ajaxPost( 'lsm_toggle_keyword', {
			id:     id,
			active: newActive
		}, function( success, data ) {
			if ( success ) {
				fetchKeywords();
			} else {
				showNotice( data.message || 'Toggle failed.', 'error' );
			}
		});
	}

	/**
	 * Render pagination controls.
	 *
	 * @param {number} currentPage  Current page number.
	 * @param {number} totalPages   Total number of pages.
	 * @param {number} totalItems   Total number of matching items.
	 */
	function renderPagination( currentPage, totalPages, totalItems ) {
		var container = document.querySelector( '.lsm-pagination' );

		if ( ! container ) {
			return;
		}

		if ( totalPages <= 1 ) {
			container.innerHTML = '<span class="lsm-pagination-info">' + totalItems + ' ' +
				escHtml( lsmAdmin.textItems || 'items' ) + '</span>';
			return;
		}

		var html = '<span class="lsm-pagination-info">' +
			escHtml( lsmAdmin.textPage || 'Page' ) + ' ' + currentPage + ' / ' + totalPages +
			' (' + totalItems + ' ' + escHtml( lsmAdmin.textItems || 'items' ) + ')</span> ';

		if ( currentPage > 1 ) {
			html += '<a href="#" class="button button-small lsm-page-link" data-page="1">&laquo;</a> ';
			html += '<a href="#" class="button button-small lsm-page-link" data-page="' + ( currentPage - 1 ) + '">&lsaquo;</a> ';
		}

		var startPage = Math.max( 1, currentPage - 2 );
		var endPage   = Math.min( totalPages, currentPage + 2 );

		for ( var p = startPage; p <= endPage; p++ ) {
			if ( p === currentPage ) {
				html += '<span class="button button-small button-primary lsm-page-current">' + p + '</span> ';
			} else {
				html += '<a href="#" class="button button-small lsm-page-link" data-page="' + p + '">' + p + '</a> ';
			}
		}

		if ( currentPage < totalPages ) {
			html += '<a href="#" class="button button-small lsm-page-link" data-page="' + ( currentPage + 1 ) + '">&rsaquo;</a> ';
			html += '<a href="#" class="button button-small lsm-page-link" data-page="' + totalPages + '">&raquo;</a>';
		}

		container.innerHTML = html;
	}

	/**
	 * Update sort indicator arrows on table headers.
	 */
	function updateSortIndicators() {
		var headers = document.querySelectorAll( '.lsm-sortable' );

		for ( var i = 0; i < headers.length; i++ ) {
			var th     = headers[ i ];
			var column = th.getAttribute( 'data-orderby' );
			var arrow  = th.querySelector( '.lsm-sort-arrow' );

			if ( ! arrow ) {
				arrow = document.createElement( 'span' );
				arrow.className = 'lsm-sort-arrow';
				th.appendChild( arrow );
			}

			th.classList.remove( 'lsm-sorted', 'lsm-sorted-asc', 'lsm-sorted-desc' );

			if ( column === state.orderby ) {
				th.classList.add( 'lsm-sorted' );
				th.classList.add( 'asc' === state.order ? 'lsm-sorted-asc' : 'lsm-sorted-desc' );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Analytics Toggle
	 * ----------------------------------------------------------------*/

	/**
	 * Bind click handler for analytics rows to toggle "Where Used" detail rows.
	 */
	function bindAnalyticsToggle() {
		var rows = document.querySelectorAll( '.lsm-analytics-row.lsm-has-posts' );

		for ( var i = 0; i < rows.length; i++ ) {
			rows[ i ].addEventListener( 'click', handleAnalyticsRowClick );
		}
	}

	/**
	 * Handle click on an analytics row — toggle the corresponding "Where Used" row.
	 *
	 * @param {Event} event The click event.
	 */
	function handleAnalyticsRowClick( event ) {
		var row       = event.currentTarget;
		var keywordId = row.getAttribute( 'data-keyword-id' );

		if ( ! keywordId ) {
			return;
		}

		var detailRow = document.querySelector( '.lsm-where-used-row[data-parent="' + keywordId + '"]' );

		if ( ! detailRow ) {
			return;
		}

		if ( 'none' === detailRow.style.display ) {
			detailRow.style.display = '';
			row.classList.add( 'lsm-row-expanded' );
		} else {
			detailRow.style.display = 'none';
			row.classList.remove( 'lsm-row-expanded' );
		}
	}

	/* ------------------------------------------------------------------
	 * Boot
	 * ----------------------------------------------------------------*/

	if ( 'loading' !== document.readyState ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
})();

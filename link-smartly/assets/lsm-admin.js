/**
 * Link Smartly — Admin JavaScript
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

/* global lsmAdmin */

(function() {
	'use strict';

	/**
	 * Initialize all event listeners when the DOM is ready.
	 */
	function init() {
		bindDeleteConfirmations();
		bindEditButtons();
		bindBulkActions();
		bindAnalyticsToggle();
	}

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
				}
			});
		}
	}

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
					}
				}
			});
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
	 * Handle the edit form submit — copy input values to hidden fields.
	 *
	 * @param {Event} event The submit event.
	 */
	function handleEditSubmit( event ) {
		var row = event.target.closest( '.lsm-keyword-row' );

		if ( ! row ) {
			return;
		}

		var keywordInput   = row.querySelector( '.lsm-edit-keyword' );
		var urlInput       = row.querySelector( '.lsm-edit-url' );
		var keywordHidden  = row.querySelector( '.lsm-edit-keyword-hidden' );
		var urlHidden      = row.querySelector( '.lsm-edit-url-hidden' );

		if ( keywordHidden ) {
			keywordHidden.value = keywordInput.value;
		}

		if ( urlHidden ) {
			urlHidden.value = urlInput.value;
		}
	}

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
		var row = event.currentTarget;
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

	if ( 'loading' !== document.readyState ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
})();

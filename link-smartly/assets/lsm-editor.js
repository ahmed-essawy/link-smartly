/**
 * Link Smartly — Block Editor Sidebar Panel
 *
 * Registers a PluginDocumentSettingPanel in the Gutenberg sidebar
 * with a toggle to exclude posts from auto-linking and a keyword count.
 *
 * @package LinkSmartly
 * @since   1.3.0
 */

(function () {
	'use strict';

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = (wp.editor && wp.editor.PluginDocumentSettingPanel)
		? wp.editor.PluginDocumentSettingPanel
		: wp.editPost.PluginDocumentSettingPanel;
	var el = wp.element.createElement;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var ToggleControl = wp.components.ToggleControl;
	var __ = wp.i18n.__;

	var metaKey = window.lsmEditor && window.lsmEditor.metaKey ? window.lsmEditor.metaKey : '_lsm_exclude';
	var keywordCount = window.lsmEditor && window.lsmEditor.keywordCount ? parseInt(window.lsmEditor.keywordCount, 10) : 0;

	/**
	 * Link Smartly sidebar panel component.
	 *
	 * @return {Object} React element.
	 */
	function LsmSidebarPanel() {
		var metaValue = useSelect(function (select) {
			var meta = select('core/editor').getEditedPostAttribute('meta');
			return meta && meta[metaKey] ? meta[metaKey] : '';
		}, []);

		var editPost = useDispatch('core/editor').editPost;

		var isExcluded = metaValue === '1';

		function onToggle(newValue) {
			var metaUpdate = {};
			metaUpdate[metaKey] = newValue ? '1' : '';
			editPost({ meta: metaUpdate });
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'link-smartly',
				title: __('Link Smartly', 'link-smartly'),
				icon: 'admin-links'
			},
			el(ToggleControl, {
				label: __('Disable auto-linking', 'link-smartly'),
				help: isExcluded
					? __('Auto-linking is disabled for this post.', 'link-smartly')
					: __('Auto-linking is enabled for this post.', 'link-smartly'),
				checked: isExcluded,
				onChange: onToggle
			}),
			el(
				'p',
				{ className: 'lsm-editor-keyword-count', style: { color: '#646970', fontSize: '12px', marginTop: '12px' } },
				/* translators: %d: number of active keywords */
				wp.i18n.sprintf(__('%d active keywords configured', 'link-smartly'), keywordCount)
			)
		);
	}

	registerPlugin('link-smartly', {
		render: LsmSidebarPanel,
		icon: 'admin-links'
	});
})();

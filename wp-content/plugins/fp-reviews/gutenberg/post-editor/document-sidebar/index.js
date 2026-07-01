/**
 * Reviews for FirmPilot – Document sidebar panel (Review details) below Categories. Controls are direct children of `PluginDocumentSettingPanel` (core pattern); spacing is default BaseControl margins.
 */
( function ( wp ) {
	if (!wp.plugins || !wp.data || !wp.editor) return;
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerPlugin = wp.plugins.registerPlugin;
	if (!wp.editor || !wp.editor.PluginDocumentSettingPanel) {
		return;
	}
	var PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var META_PREFIX = '_fp_review_';

	var DEFAULT_OPTIONS = {
		name: '',
		job_title: '',
		company: '',
		location: '',
		rating: 0
	};

	function ReviewDetailsPanel() {
		var postType = useSelect(function (select) { return select('core/editor').getCurrentPostType(); }, []);
		var meta = useSelect(function (select) { return select('core/editor').getEditedPostAttribute('meta') || {}; }, []);
		var editPost = useDispatch('core/editor').editPost;
		if (postType !== 'fp_review') return null;
		function getMeta(key) { var v = meta[META_PREFIX + key]; return v !== undefined && v !== null ? String(v) : ''; }
		function setMeta(key, value) { editPost({ meta: Object.assign({}, meta, { [META_PREFIX + key]: value }) }); }
		return el(PluginDocumentSettingPanel, { className: 'fp-reviews-document-panel', name: 'fp-reviews-content', title: __('Content', 'firmpilot-reviews') },
			el( TextControl, { label: __('Name', 'firmpilot-reviews'), help: __('Shown as the reviewer name on the site (separate from the document title).', 'firmpilot-reviews'), value: getMeta('name') || DEFAULT_OPTIONS.name, onChange: function (v) { setMeta('name', v || ''); } }),
			el( TextControl, { label: __('Job Title', 'firmpilot-reviews'), value: getMeta('job_title') || DEFAULT_OPTIONS.job_title, onChange: function (v) { setMeta('job_title', v || ''); } }),
			el( TextControl, { label: __('Company', 'firmpilot-reviews'), value: getMeta('company') || DEFAULT_OPTIONS.company, onChange: function (v) { setMeta('company', v || ''); } }),
			el( TextControl, { label: __('Location', 'firmpilot-reviews'), value: getMeta('location') || DEFAULT_OPTIONS.location, onChange: function (v) { setMeta('location', v || ''); } }),
			el( RangeControl, { label: __('Rating', 'firmpilot-reviews'), value: Math.max(0, Math.min(5, parseInt(getMeta('rating'), 10) || DEFAULT_OPTIONS.rating)), min: 0, max: 5, onChange: function (v) { setMeta('rating', String(v)); } })
		);
	}
	registerPlugin('fp-reviews-document-sidebar', { render: ReviewDetailsPanel });
} )( window.wp );


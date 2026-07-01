/**
 * Reviews for FirmPilot – list view thumbnail/edit via wp.media (vanilla JS, no jQuery).
 */
( function () {
	'use strict';
	var frame;

	function getOpt(name, def) {
		return (typeof fpListThumb !== 'undefined' && fpListThumb[name]) ? fpListThumb[name] : def;
	}

	document.addEventListener('click', function (e) {
		var removeBtn = e.target.closest('.fp-column__thumb__remove');
		if (removeBtn) {
			e.preventDefault();
			e.stopPropagation();
			var postId = removeBtn.getAttribute('data-post-id');
			var nonce = removeBtn.getAttribute('data-nonce');
			var removeAction = getOpt('removeThumbnailAction', '');
			if (!postId || !nonce || !removeAction) return;
			var cell = document.querySelector('#post-' + postId + ' .column-thumb');
			if (!cell) return;
			var body = new FormData();
			body.append('action', removeAction);
			body.append('post_id', postId);
			body.append('_ajax_nonce', nonce);
			fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: body,
				credentials: 'same-origin'
			}).then(function (r) { return r && r.text ? r.text() : null; }).then(function (html) {
				if (cell && html) cell.innerHTML = html;
			});
			return;
		}

		var link = e.target.closest('.fp-column__thumb');
		if (!link) return;
		e.preventDefault();

		var postId = link.getAttribute('data-post-id');
		var nonce = link.getAttribute('data-nonce');
		var currentThumbId = link.getAttribute('data-thumbnail-id') || 0;

		if (!postId || !nonce) return;

		if (frame) frame.close();

		frame = wp.media({
			title: getOpt('changeTitle', 'Featured image'),
			library: { type: 'image' },
			multiple: false,
			button: { text: getOpt('buttonText', 'Use as thumbnail') }
		});

		wp.media.model.settings.post.id = parseInt(postId, 10);

		frame.on('open', function () {
			var selection = frame.state().get('selection');
			if (currentThumbId && selection) {
				var attachment = wp.media.attachment(parseInt(currentThumbId, 10));
				attachment.fetch();
				selection.add(attachment ? [attachment] : []);
			}
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var thumbId = attachment.id;
			var getAction = getOpt('getThumbnailAction', '');
			if (!getAction) {
				location.reload();
				return;
			}
			var body = new FormData();
			body.append('action', getAction);
			body.append('post_id', postId);
			body.append('thumbnail_id', thumbId);
			body.append('_ajax_nonce', nonce);

			fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: body,
				credentials: 'same-origin'
			}).then(function (r) { return r && r.text ? r.text() : null; }).then(function (html) {
				var cell = document.querySelector('#post-' + postId + ' .column-thumb');
				if (cell && html) {
					var changeTitle = (getOpt('changeTitle', 'Change featured image') || '').replace(/"/g, '&quot;');
					var href = link.getAttribute('href') || '#';
					var newLink = '<a href="' + href + '" class="fp-column__thumb" data-post-id="' + postId + '" data-nonce="' + nonce + '" data-thumbnail-id="' + thumbId + '" title="' + changeTitle + '">' + html + '</a>';
					cell.innerHTML = newLink;
				}
			});
		});

		frame.open();
	});
})();

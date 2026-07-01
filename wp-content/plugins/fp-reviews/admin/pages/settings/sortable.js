/**
 * FirmPilot Reviews – vanilla JS table row reorder (classic admin list tables). No jQuery, no HTML5 DnD. Uses mousedown/mousemove/mouseup on handle only.
 */
(function () {
	'use strict';

	function bindRowSortable(list, opts) {
		var table = list.closest('table');
		var thead = table && table.querySelector('thead');
		var colCount = thead ? thead.querySelectorAll('th').length + 1 : 2;
		var dragged = null;
		var placeholder = null;
		var ghost = null;
		var startX = 0;
		var startY = 0;
		var offsetX = 0;
		var offsetY = 0;
		var dragStarted = false;

		function getRows() {
			return Array.prototype.slice.call(list.querySelectorAll('tr[id^="post-"]'));
		}

		function getOrder() {
			return getRows().map(function (tr) {
				return (tr.id || '').replace('post-', '');
			}).filter(Boolean);
		}

		function saveOrder() {
			var order = getOrder();
			if (order.length === 0) return;
			var fd = new FormData();
			fd.append('action', opts.action);
			fd.append('nonce', opts.nonce);
			order.forEach(function (id) { fd.append('order[]', id); });
			fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: fd,
				credentials: 'same-origin'
			});
		}

		function createPlaceholder() {
			var tr = document.createElement('tr');
			tr.className = 'fp-sortable__placeholder';
			tr.innerHTML = '<td colspan="' + colCount + '"></td>';
			return tr;
		}

		function createGhost(row, colWidths) {
			var wrapper = document.createElement('div');
			wrapper.className = 'fp-sortable__ghost';
			wrapper.setAttribute('aria-hidden', 'true');
			var tbl = document.createElement('table');
			tbl.className = table ? table.className : '';
			tbl.style.tableLayout = 'fixed';
			tbl.style.width = (colWidths.total || table.offsetWidth) + 'px';
			var colgroup = document.createElement('colgroup');
			for (var i = 0; i < colWidths.length; i++) {
				var col = document.createElement('col');
				col.style.width = colWidths[i] + 'px';
				colgroup.appendChild(col);
			}
			tbl.appendChild(colgroup);
			var clone = row.cloneNode(true);
			clone.removeAttribute('id');
			tbl.appendChild(clone);
			wrapper.appendChild(tbl);
			document.body.appendChild(wrapper);
			return wrapper;
		}

		function positionGhost(clientX, clientY) {
			if (!ghost) return;
			ghost.style.left = (clientX - offsetX) + 'px';
			ghost.style.top = (clientY - offsetY) + 'px';
		}

		function getRowUnderCursor(clientY) {
			var trs = list.querySelectorAll('tr');
			for (var i = 0; i < trs.length; i++) {
				var r = trs[i];
				var rect = r.getBoundingClientRect();
				if (clientY >= rect.top && clientY <= rect.bottom) return r;
			}
			return null;
		}

		function movePlaceholder(clientY) {
			if (!placeholder || !dragged || !placeholder.parentNode) return;
			var target = getRowUnderCursor(clientY);
			if (!target || target === placeholder) return;
			list.insertBefore(placeholder, target);
		}

		function onMouseMove(e) {
			if (!dragStarted) {
				if (Math.abs(e.clientY - startY) < 5) return;
				dragStarted = true;
				var rect = dragged.getBoundingClientRect();
				offsetX = startX - rect.left;
				offsetY = startY - rect.top;
				var colWidths = [];
				for (var i = 0; i < dragged.cells.length; i++) {
					colWidths.push(dragged.cells[i].offsetWidth);
				}
				colWidths.total = table ? table.offsetWidth : 0;
				document.body.classList.add('fp-sortable__dragging');
				placeholder = createPlaceholder();
				list.insertBefore(placeholder, dragged);
				list.removeChild(dragged);
				ghost = createGhost(dragged, colWidths);
				positionGhost(e.clientX, e.clientY);
			}
			positionGhost(e.clientX, e.clientY);
			movePlaceholder(e.clientY);
		}

		function onMouseUp() {
			document.removeEventListener('mousemove', onMouseMove);
			document.removeEventListener('mouseup', onMouseUp);
			document.body.classList.remove('fp-sortable__dragging');
			if (ghost && ghost.parentNode) ghost.parentNode.removeChild(ghost);
			ghost = null;
			if (dragStarted && dragged && placeholder) {
				list.insertBefore(dragged, placeholder);
				list.removeChild(placeholder);
				saveOrder();
			}
			dragged = null;
			placeholder = null;
			dragStarted = false;
		}

		function onMouseDown(e) {
			var handle = e.target.closest && e.target.closest(opts.handleSelector);
			if (!handle) return;
			var row = handle.closest('tr');
			if (!row || !row.id || row.id.indexOf('post-') !== 0) return;
			if (e.button !== 0 || e.ctrlKey || e.metaKey) return;

			e.preventDefault();
			dragged = row;
			startX = e.clientX;
			startY = e.clientY;
			dragStarted = false;

			document.addEventListener('mousemove', onMouseMove);
			document.addEventListener('mouseup', onMouseUp);
		}

		list.addEventListener('mousedown', onMouseDown);
	}

	function init() {
		var opts = typeof fpSortableOpts !== 'undefined' ? fpSortableOpts : null;
		if (!opts || !opts.action || !opts.nonce || !opts.handleSelector) return;

		var list = document.getElementById('the-list');
		if (!list || !list.querySelector(opts.handleSelector)) return;

		bindRowSortable(list, opts);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

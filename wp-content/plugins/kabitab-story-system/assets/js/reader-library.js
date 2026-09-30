(function () {
	'use strict';

	var input = document.querySelector('[data-kabitab-saved-search]');
	var container = document.querySelector('[data-kabitab-saved-items]');
	var result = document.querySelector('[data-kabitab-saved-result]');
	if (!input || !container || !result) { return; }

	var items = Array.prototype.slice.call(container.querySelectorAll('.simplefavorites-list li'));
	if (!items.length) {
		input.parentElement.hidden = true;
		return;
	}

	function render() {
		var query = input.value.trim().toLocaleLowerCase();
		var visible = 0;
		items.forEach(function (item) {
			var matches = !query || (item.textContent || '').toLocaleLowerCase().indexOf(query) !== -1;
			item.hidden = !matches;
			if (matches) { visible += 1; }
		});
		result.textContent = visible + (visible === 1 ? ' saved story shown' : ' saved stories shown');
	}

	input.addEventListener('input', render);
	render();
}());

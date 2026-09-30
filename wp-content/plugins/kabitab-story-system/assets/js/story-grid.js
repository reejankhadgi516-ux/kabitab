(function () {
	function initialise(grid) {
		// Explore searches and pagination are rendered by WordPress so a reader
		// can search the full published catalogue, rather than only this page.
		if (grid.hasAttribute('data-kabitab-server-grid')) { return; }

		var search = grid.querySelector('[data-kabitab-story-search]');
		var filters = Array.prototype.slice.call(grid.querySelectorAll('[data-kabitab-genre]'));
		var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-kabitab-story-card]'));
		var empty = grid.querySelector('[data-kabitab-empty]');
		var resultCount = grid.querySelector('[data-kabitab-result-count]');
		var activeGenre = 'all';
		var initialTopic = (new URLSearchParams(window.location.search).get('topic') || '').trim();

		// Home-page genre cards pass a reader-friendly topic here. Search terms are
		// deliberately used rather than hard-coded taxonomy slugs, so renamed genres
		// and bilingual labels continue to work without editing the home page again.
		if (initialTopic && search) {
			search.value = initialTopic;
		}

		function applyFilters() {
			var query = search ? search.value.trim().toLocaleLowerCase() : '';
			var visible = 0;
			cards.forEach(function (card) {
				var matchesGenre = activeGenre === 'all' || (' ' + card.dataset.kabitabGenres + ' ').indexOf(' ' + activeGenre + ' ') !== -1;
				var matchesQuery = !query || (card.dataset.kabitabSearch || '').toLocaleLowerCase().indexOf(query) !== -1;
				var show = matchesGenre && matchesQuery;
				card.hidden = !show;
				if (show) { visible += 1; }
			});
			if (empty) { empty.hidden = visible !== 0; }
			if (resultCount) { resultCount.textContent = visible + ' प्रकाशित रचना'; }
		}

		if (search) { search.addEventListener('input', applyFilters); }
		filters.forEach(function (filter) {
			filter.addEventListener('click', function () {
				activeGenre = filter.dataset.kabitabGenre || 'all';
				filters.forEach(function (button) {
					var active = button === filter;
					button.classList.toggle('is-active', active);
					button.setAttribute('aria-pressed', active ? 'true' : 'false');
				});
				applyFilters();
			});
		});
		applyFilters();
	}

	function boot() { document.querySelectorAll('[data-kabitab-story-grid]').forEach(initialise); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
}());

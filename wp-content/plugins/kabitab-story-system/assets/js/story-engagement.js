(function () {
	'use strict';

	var settings = window.kabitabStoryEngagement || {};
	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-kabitab-story-like], [data-kabitab-chapter-vote]');
		if (!button || button.disabled || !settings.ajaxUrl) {
			return;
		}

		event.preventDefault();
		button.disabled = true;
		var data = new FormData();
		var isChapterVote = button.hasAttribute('data-kabitab-chapter-vote');
		data.append('action', isChapterVote ? 'kabitab_toggle_chapter_vote' : 'kabitab_toggle_story_like');
		data.append(isChapterVote ? 'chapter_id' : 'story_id', button.getAttribute(isChapterVote ? 'data-chapter-id' : 'data-story-id') || '');
		data.append('nonce', button.getAttribute('data-nonce') || '');

		window.fetch(settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (response) { return response.json(); })
			.then(function (result) {
				if (!result.success) {
					throw new Error((result.data && result.data.message) || settings.error);
				}
				var active = isChapterVote ? Boolean(result.data.voted) : Boolean(result.data.liked);
				button.classList.toggle('is-liked', active);
				button.setAttribute('aria-pressed', active ? 'true' : 'false');
				button.textContent = result.data.label;
				document.querySelectorAll(isChapterVote ? '[data-kabitab-chapter-vote-count]' : '[data-kabitab-like-count]').forEach(function (count) { count.textContent = String(result.data.count); });
			})
			.catch(function () {
				window.alert(settings.error || 'We could not update that like. Please try again.');
			})
			.finally(function () {
				button.disabled = false;
			});
	});
}());

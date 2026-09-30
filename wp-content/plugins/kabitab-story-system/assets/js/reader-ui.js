(function () {
	'use strict';

	var body = document.body;
	var content = document.querySelector('.kabitab-reading-content');
	var progress = document.querySelector('[data-kabitab-reading-progress]');
	var progressRoot = progress ? progress.parentElement : null;
	var sizeButtons = Array.prototype.slice.call(document.querySelectorAll('[data-kabitab-text-size]'));
	var themeButton = document.querySelector('[data-kabitab-reader-theme]');
	var readButton = document.querySelector('[data-kabitab-read-aloud]');
	var pauseButton = document.querySelector('[data-kabitab-read-aloud-pause]');
	var selectionButton = document.querySelector('[data-kabitab-read-selection]');
	var speedSelect = document.querySelector('[data-kabitab-read-speed]');
	var voiceSelect = document.querySelector('[data-kabitab-read-voice]');
	var readStatus = document.querySelector('[data-kabitab-read-aloud-status]');
	var storageKey = 'kabitab-reader-preferences';
	var preferences = { size: 'normal', theme: 'day', readSpeed: '0.88', readVoice: '' };
	var progressConfig = window.kabitabReaderProgress || {};
	var latestPercent = 0;
	var lastSavedPercent = -1;
	var saveTimer = 0;
	var activeUtterance = null;
	var restoredReadingPosition = false;

	if (!body || !content) {
		return;
	}

	try {
		var stored = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
		if (['small', 'normal', 'large'].indexOf(stored.size) !== -1) { preferences.size = stored.size; }
		if (stored.theme === 'night') { preferences.theme = 'night'; }
		if (['0.75', '0.88', '1', '1.15'].indexOf(String(stored.readSpeed)) !== -1) { preferences.readSpeed = String(stored.readSpeed); }
		if (typeof stored.readVoice === 'string') { preferences.readVoice = stored.readVoice; }
	} catch (error) {
		preferences = { size: 'normal', theme: 'day', readSpeed: '0.88', readVoice: '' };
	}

	function savePreferences() {
		try { window.localStorage.setItem(storageKey, JSON.stringify(preferences)); } catch (error) { /* Controls still work without storage. */ }
	}

	function applyPreferences() {
		body.classList.remove('kabitab-reader-size-small', 'kabitab-reader-size-large', 'kabitab-reader-night');
		if (preferences.size === 'small') { body.classList.add('kabitab-reader-size-small'); }
		else if (preferences.size === 'large') { body.classList.add('kabitab-reader-size-large'); }
		if (preferences.theme === 'night') { body.classList.add('kabitab-reader-night'); }
		sizeButtons.forEach(function (button) { button.setAttribute('aria-pressed', button.getAttribute('data-kabitab-text-size') === preferences.size ? 'true' : 'false'); });
		if (themeButton) {
			themeButton.setAttribute('aria-pressed', preferences.theme === 'night' ? 'true' : 'false');
			themeButton.textContent = preferences.theme === 'night' ? 'Day mode' : 'Night mode';
		}
		if (speedSelect) { speedSelect.value = preferences.readSpeed; }
	}

	function updateProgress() {
		if (!progress || !progressRoot) { return; }
		var rect = content.getBoundingClientRect();
		var contentTop = window.scrollY + rect.top;
		var readableDistance = Math.max(content.scrollHeight - window.innerHeight * 0.45, 1);
		var percent = Math.max(0, Math.min(100, ((window.scrollY - contentTop) / readableDistance) * 100));
		latestPercent = percent >= 98 ? 100 : Math.round(percent);
		progress.style.width = percent.toFixed(1) + '%';
		progressRoot.setAttribute('aria-valuenow', String(Math.round(percent)));
	}

	function sendReadingProgress(useKeepalive) {
		if (!progressConfig.loggedIn || !progressConfig.ajaxUrl || !progressConfig.nonce || !progressConfig.chapterId || !progressConfig.storyId || latestPercent === lastSavedPercent) { return; }
		var data = new FormData();
		data.append('action', 'kabitab_save_reading_progress');
		data.append('nonce', progressConfig.nonce);
		data.append('chapter_id', String(progressConfig.chapterId));
		data.append('story_id', String(progressConfig.storyId));
		data.append('percent', String(latestPercent));
		lastSavedPercent = latestPercent;
		window.fetch(progressConfig.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data, keepalive: Boolean(useKeepalive) }).catch(function () { lastSavedPercent = -1; });
	}

	function scheduleReadingProgress() {
		if (!progressConfig.loggedIn) { return; }
		window.clearTimeout(saveTimer);
		saveTimer = window.setTimeout(function () { sendReadingProgress(false); }, 3500);
	}

	function restoreReadingPosition() {
		var savedPercent = Number(progressConfig.resumePercent || 0);
		if (restoredReadingPosition || !progressConfig.resumeRequested || savedPercent < 5) { return; }
		var rect = content.getBoundingClientRect();
		var contentTop = window.scrollY + rect.top;
		var readableDistance = Math.max(content.scrollHeight - window.innerHeight * 0.45, 1);
		var target = contentTop + (readableDistance * Math.min(100, savedPercent) / 100);
		restoredReadingPosition = true;
		window.scrollTo(0, Math.max(0, Math.round(target)));
		updateProgress();
		if (window.history && window.history.replaceState && window.URL) {
			var currentUrl = new window.URL(window.location.href);
			currentUrl.searchParams.delete('kabitab_resume');
			window.history.replaceState({}, document.title, currentUrl.pathname + currentUrl.search + currentUrl.hash);
		}
	}

	function setStatus(message) {
		if (readStatus) { readStatus.textContent = message || ''; }
	}

	function setReadState(state, message) {
		var active = state === 'playing' || state === 'paused';
		if (readButton) {
			readButton.setAttribute('aria-pressed', active ? 'true' : 'false');
			readButton.textContent = active ? 'Stop reading' : 'Read chapter';
		}
		if (pauseButton) {
			pauseButton.disabled = !active;
			pauseButton.textContent = state === 'paused' ? 'Resume' : 'Pause';
		}
		setStatus(message);
	}

	function voices() { return window.speechSynthesis ? window.speechSynthesis.getVoices() : []; }
	function isNepaliVoice(voice) { return /^ne(?:-|_)/i.test(voice.lang || '') || /nepali/i.test(voice.name || ''); }

	function populateVoiceChoices() {
		if (!voiceSelect || !window.speechSynthesis) { return; }
		var nepaliVoices = voices().filter(isNepaliVoice);
		voiceSelect.innerHTML = '<option value="">Device default</option>';
		nepaliVoices.forEach(function (voice) {
			var option = document.createElement('option');
			option.value = voice.voiceURI;
			option.textContent = voice.name + (voice.lang ? ' (' + voice.lang + ')' : '');
			voiceSelect.appendChild(option);
		});
		if (preferences.readVoice && nepaliVoices.some(function (voice) { return voice.voiceURI === preferences.readVoice; })) {
			voiceSelect.value = preferences.readVoice;
		} else {
			voiceSelect.value = '';
			preferences.readVoice = '';
		}
		voiceSelect.disabled = nepaliVoices.length === 0;
		voiceSelect.title = nepaliVoices.length === 0 ? 'No Nepali device voice is available.' : '';
	}

	function selectedVoice() {
		var allVoices = voices();
		if (preferences.readVoice) {
			var chosen = allVoices.find(function (voice) { return voice.voiceURI === preferences.readVoice; });
			if (chosen) { return chosen; }
		}
		return allVoices.find(isNepaliVoice) || null;
	}

	function stopReading(message) {
		if (window.speechSynthesis) { window.speechSynthesis.cancel(); }
		activeUtterance = null;
		setReadState('idle', message || 'Reading stopped.');
	}

	function speakText(text, label) {
		if (!text) { setReadState('idle', 'There is no text available to read aloud.'); return; }
		if (!window.speechSynthesis || !window.SpeechSynthesisUtterance) { setReadState('idle', 'Read aloud is not available in this browser.'); return; }
		window.speechSynthesis.cancel();
		var utterance = new SpeechSynthesisUtterance(text);
		var voice = selectedVoice();
		utterance.lang = voice ? voice.lang : 'ne-NP';
		utterance.rate = Number(preferences.readSpeed);
		if (voice) { utterance.voice = voice; }
		activeUtterance = utterance;
		utterance.onstart = function () { if (activeUtterance === utterance) { setReadState('playing', label + (voice ? ' with ' + voice.name + '.' : ' with your device voice.')); } };
		utterance.onend = function () { if (activeUtterance === utterance) { activeUtterance = null; setReadState('idle', 'Finished reading.'); } };
		utterance.onerror = function () { if (activeUtterance === utterance) { activeUtterance = null; setReadState('idle', 'Read aloud could not start in this browser.'); } };
		setReadState('playing', 'Preparing audio…');
		window.speechSynthesis.speak(utterance);
	}

	function selectedPassage() {
		var selection = window.getSelection();
		if (!selection || selection.rangeCount === 0 || !selection.toString().trim()) { return ''; }
		var range = selection.getRangeAt(0);
		return content.contains(range.commonAncestorContainer) ? selection.toString().replace(/\s+/g, ' ').trim() : '';
	}

	sizeButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			preferences.size = button.getAttribute('data-kabitab-text-size') || 'normal';
			applyPreferences(); savePreferences(); updateProgress();
		});
	});
	if (themeButton) {
		themeButton.addEventListener('click', function () { preferences.theme = preferences.theme === 'night' ? 'day' : 'night'; applyPreferences(); savePreferences(); });
	}

	if (readButton) {
		if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) {
			readButton.disabled = true;
			if (selectionButton) { selectionButton.disabled = true; }
			setStatus('Read aloud is not available in this browser.');
		} else {
			readButton.addEventListener('click', function () {
				if (activeUtterance) { stopReading(); return; }
				speakText((content.textContent || '').replace(/\s+/g, ' ').trim(), 'Reading chapter');
			});
			if (pauseButton) {
				pauseButton.addEventListener('click', function () {
					if (!activeUtterance) { return; }
					if (window.speechSynthesis.paused) { window.speechSynthesis.resume(); setReadState('playing', 'Reading resumed.'); }
					else { window.speechSynthesis.pause(); setReadState('paused', 'Reading paused.'); }
				});
			}
			if (selectionButton) {
				selectionButton.addEventListener('click', function () {
					var passage = selectedPassage();
					if (!passage) { setStatus('Select words in the chapter first, then use this button.'); return; }
					speakText(passage, 'Reading selected text');
				});
			}
			if (speedSelect) { speedSelect.addEventListener('change', function () { preferences.readSpeed = speedSelect.value; savePreferences(); setStatus('Speed will apply when you start reading.'); }); }
			if (voiceSelect) { voiceSelect.addEventListener('change', function () { preferences.readVoice = voiceSelect.value; savePreferences(); setStatus('Voice will apply when you start reading.'); }); }
			populateVoiceChoices();
			window.speechSynthesis.onvoiceschanged = populateVoiceChoices;
		}
	}

	window.addEventListener('scroll', function () { updateProgress(); scheduleReadingProgress(); }, { passive: true });
	window.addEventListener('resize', updateProgress);
	window.addEventListener('pagehide', function () {
		window.clearTimeout(saveTimer);
		sendReadingProgress(true);
		if ('speechSynthesis' in window) { window.speechSynthesis.cancel(); }
	});
	applyPreferences();
	updateProgress();
	if (document.readyState === 'complete') { restoreReadingPosition(); }
	else { window.addEventListener('load', restoreReadingPosition, { once: true }); }
}());

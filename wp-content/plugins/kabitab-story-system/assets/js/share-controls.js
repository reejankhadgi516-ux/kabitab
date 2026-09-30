(function () {
	'use strict';

	function fallbackCopy(text) {
		var input = document.createElement('textarea');
		input.value = text;
		input.setAttribute('readonly', '');
		input.style.position = 'fixed';
		input.style.opacity = '0';
		document.body.appendChild(input);
		input.select();
		var copied = false;
		try { copied = document.execCommand('copy'); } catch (error) { copied = false; }
		document.body.removeChild(input);
		return copied;
	}

	function copyLink(url) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(url).then(function () { return true; }).catch(function () { return fallbackCopy(url); });
		}
		return Promise.resolve(fallbackCopy(url));
	}

	document.querySelectorAll('[data-kabitab-share]').forEach(function (control) {
		var title = control.getAttribute('data-kabitab-share-title') || document.title;
		var url = control.getAttribute('data-kabitab-share-url') || window.location.href;
		var shareButton = control.querySelector('[data-kabitab-share-button]');
		var copyButton = control.querySelector('[data-kabitab-copy-link]');
		var status = control.querySelector('[data-kabitab-share-status]');

		function setStatus(message) { if (status) { status.textContent = message; } }
		function copyAndConfirm() {
			copyLink(url).then(function (copied) { setStatus(copied ? 'Link copied.' : 'Copy the link from your browser address bar.'); });
		}

		if (shareButton) {
			shareButton.addEventListener('click', function () {
				if (!navigator.share) { copyAndConfirm(); return; }
				navigator.share({ title: title, url: url }).then(function () { setStatus('Ready to share.'); }).catch(function (error) {
					if (error && error.name === 'AbortError') { setStatus('Sharing cancelled.'); return; }
					copyAndConfirm();
				});
			});
		}
		if (copyButton) { copyButton.addEventListener('click', copyAndConfirm); }
	});
}());

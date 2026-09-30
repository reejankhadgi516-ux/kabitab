(function () {
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-kabitab-follow-writer]');
    if (!button || button.disabled || !window.kabitabWriterFollowing) return;

    event.preventDefault();
    button.disabled = true;

    var payload = new URLSearchParams();
    payload.set('action', 'kabitab_toggle_writer_follow');
    payload.set('author_id', button.getAttribute('data-author-id'));
    payload.set('nonce', button.getAttribute('data-nonce'));

    fetch(window.kabitabWriterFollowing.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: payload.toString()
    })
      .then(function (response) { return response.json(); })
      .then(function (response) {
        if (!response.success) throw new Error();
        button.textContent = response.data.label;
        button.classList.toggle('is-following', response.data.following);
        button.setAttribute('aria-pressed', response.data.following ? 'true' : 'false');
      })
      .catch(function () {
        window.alert(window.kabitabWriterFollowing.error);
      })
      .finally(function () {
        button.disabled = false;
      });
  });
}());

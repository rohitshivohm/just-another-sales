(function () {
  function bindCopyButtons() {
    document.querySelectorAll('.jaqr-copy-shortcode').forEach(function (button) {
      button.addEventListener('click', function () {
        var selector = button.getAttribute('data-copy-target');
        var target = selector ? document.querySelector(selector) : null;
        if (!target) return;

        target.select();
        target.setSelectionRange(0, 99999);

        try {
          document.execCommand('copy');
          button.textContent = 'Copied!';
          setTimeout(function () {
            button.textContent = 'Copy Shortcode';
          }, 1200);
        } catch (e) {
          // no-op fallback
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindCopyButtons);
  } else {
    bindCopyButtons();
  }
})();

(function () {
  var QR_API = 'https://api.qrserver.com/v1/create-qr-code/';

  function byId(id, root) {
    return (root || document).getElementById(id);
  }

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function buildPayload(type, content) {
    var value = (content || '').trim();
    if (!value) return window.location.origin + '/';
    switch ((type || 'url').toLowerCase()) {
      case 'phone':
        return 'tel:' + value.replace(/\s+/g, '');
      case 'email':
        return 'mailto:' + value;
      case 'text':
      default:
        return value;
    }
  }

  function buildQrUrl(opts) {
    var params = new URLSearchParams({
      size: opts.size + 'x' + opts.size,
      margin: String(opts.margin),
      data: opts.payload,
      color: opts.fg.replace('#', ''),
      bgcolor: opts.bg.replace('#', ''),
      format: 'png',
    });
    return QR_API + '?' + params.toString();
  }

  function buildShortcode(opts) {
    return '[jaqr type="' + opts.type + '" content="' + opts.content.replace(/"/g, '&quot;') + '" size="' + opts.size + '" frame="' + opts.frame.replace(/"/g, '&quot;') + '" alt="' + opts.alt.replace(/"/g, '&quot;') + '" show_center_text="' + (opts.showCenter ? '1' : '0') + '" center_text="' + opts.centerText.replace(/"/g, '&quot;') + '" fg="' + opts.fg + '" bg="' + opts.bg + '" margin="' + opts.margin + '"]';
  }

  function readBuilderState(form) {
    var type = qs('[name="type"]', form)?.value || 'url';
    var content = qs('[name="content"]', form)?.value || '';
    return {
      type: type,
      content: content,
      payload: buildPayload(type, content),
      size: parseInt(qs('[name="size"]', form)?.value || '220', 10),
      frame: qs('[name="frame"]', form)?.value || '',
      alt: qs('[name="alt"]', form)?.value || 'QR code',
      centerText: qs('[name="center_text"]', form)?.value || '',
      showCenter: !!qs('[name="show_center_text"]:checked', form),
      fg: qs('[name="fg"]', form)?.value || '#000000',
      bg: qs('[name="bg"]', form)?.value || '#ffffff',
      margin: parseInt(qs('[name="margin"]', form)?.value || '1', 10),
    };
  }

  function readPostState(root) {
    var type = byId('jaqr_type', root)?.value || 'url';
    var content = byId('jaqr_content', root)?.value || '';
    return {
      type: type,
      content: content,
      payload: buildPayload(type, content),
      size: parseInt(byId('jaqr_size', root)?.value || '220', 10),
      frame: byId('jaqr_frame', root)?.value || '',
      alt: byId('jaqr_alt', root)?.value || 'QR code',
      centerText: byId('jaqr_center_text', root)?.value || '',
      showCenter: !!qs('[name="jaqr_show_center_text"]:checked', root),
      fg: byId('jaqr_fg', root)?.value || '#000000',
      bg: byId('jaqr_bg', root)?.value || '#ffffff',
      margin: parseInt(byId('jaqr_margin', root)?.value || '1', 10),
    };
  }

  function updatePreview(root, state, isBuilder) {
    var img = qs('.jaqr-canvas .jaqr-image', root);
    if (img) img.src = buildQrUrl(state);

    var frame = qs('.jaqr-frame', root);
    if (frame) {
      frame.textContent = state.frame;
      frame.style.display = state.frame ? '' : 'none';
    }

    var badge = qs('.jaqr-center-badge', root);
    if (badge) {
      badge.textContent = state.centerText;
      badge.style.display = state.showCenter && state.centerText ? '' : 'none';
    }

    var shortcode = isBuilder ? byId('jaqr_builder_shortcode') : null;
    if (shortcode) shortcode.value = buildShortcode(state);

    qsa('.jaqr-actions .jaqr-btn', root).forEach(function (btn) {
      var format = btn.textContent.indexOf('SVG') !== -1 ? 'svg' : 'png';
      var params = new URLSearchParams({
        size: state.size + 'x' + state.size,
        margin: String(state.margin),
        data: state.payload,
        color: state.fg.replace('#', ''),
        bgcolor: state.bg.replace('#', ''),
        format: format,
      });
      btn.href = QR_API + '?' + params.toString();
    });
  }

  function bindLiveBuilder() {
    var form = qs('.jaqr-live-form[data-live="builder"]');
    if (!form) return;

    var root = form.closest('.jaqr-builder-grid');
    var handler = function () {
      updatePreview(root, readBuilderState(form), true);
    };

    qsa('input, select, textarea', form).forEach(function (el) {
      el.addEventListener('input', handler);
      el.addEventListener('change', handler);
    });
  }

  function bindLivePostEditor() {
    var root = qs('.jaqr-live-form[data-live="post"]');
    if (!root) return;

    var handler = function () {
      updatePreview(root, readPostState(root), false);
    };

    qsa('input, select, textarea', root).forEach(function (el) {
      el.addEventListener('input', handler);
      el.addEventListener('change', handler);
    });
  }

  function bindCopyButtons() {
    qsa('.jaqr-copy-shortcode').forEach(function (button) {
      button.addEventListener('click', function () {
        var selector = button.getAttribute('data-copy-target');
        var target = selector ? qs(selector) : null;
        if (!target) return;

        target.select();
        target.setSelectionRange(0, 99999);

        try {
          document.execCommand('copy');
          var old = button.textContent;
          button.textContent = 'Copied!';
          setTimeout(function () {
            button.textContent = old || 'Copy Shortcode';
          }, 1200);
        } catch (e) {
          // no-op fallback
        }
      });
    });
  }

  function init() {
    bindCopyButtons();
    bindLiveBuilder();
    bindLivePostEditor();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

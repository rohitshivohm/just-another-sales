(function (wp) {
  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) return;

  const { registerBlockType } = wp.blocks;
  const { createElement: el } = wp.element;
  const { InspectorControls } = wp.blockEditor;
  const { PanelBody, TextControl, RangeControl } = wp.components;

  registerBlockType('jaqr/qr-code', {
    title: 'Just Another QR',
    icon: 'qrcode',
    category: 'widgets',
    attributes: {
      content: { type: 'string', default: window.location.origin + window.location.pathname },
      size: { type: 'number', default: 220 },
      alt: { type: 'string', default: 'QR code' },
      frame: { type: 'string', default: '' },
    },
    edit: function (props) {
      const { attributes, setAttributes } = props;

      return el('div', { className: 'jaqr-block-editor' },
        el(InspectorControls, {},
          el(PanelBody, { title: 'QR Settings', initialOpen: true },
            el(TextControl, {
              label: 'Content/URL',
              value: attributes.content,
              onChange: (v) => setAttributes({ content: v }),
            }),
            el(RangeControl, {
              label: 'Size',
              min: 100,
              max: 1024,
              value: attributes.size,
              onChange: (v) => setAttributes({ size: v }),
            }),
            el(TextControl, {
              label: 'Alt text',
              value: attributes.alt,
              onChange: (v) => setAttributes({ alt: v }),
            }),
            el(TextControl, {
              label: 'Frame label',
              value: attributes.frame,
              onChange: (v) => setAttributes({ frame: v }),
            }),
          )
        ),
        el('p', {}, 'QR preview is rendered on the frontend via dynamic block rendering.')
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp);

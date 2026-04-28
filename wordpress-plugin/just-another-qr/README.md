# Just Another QR (WordPress Plugin Scaffold)

This plugin scaffold is structured to support the full feature set requested for an advanced QR product.

## Implemented and working now

- Static QR rendering via shortcode (`[jaqr]`) and widget.
- Content types in shortcode: URL, text, phone, email, SMS, WhatsApp, WiFi, vCard.
- Gutenberg block registration (`jaqr/qr-code`) with server-side rendering.
- QR library and campaign custom post types.
- QR code editor UI in each `QR Code` post:
  - Configure content type + value
  - Toggle dynamic redirect
  - Set destination URL, size, alt text, frame
  - See live preview + copy shortcode (`[jaqr_code id=\"123\"]`)
- Dynamic QR redirect endpoint (`/wp-json/jaqr/v1/track/{id}`) with total + daily scan counts.
- Admin dashboard + settings pages.
- Frontend styling (frame label + shadow effect).

## Settings behavior (now connected)

- `Enable Dynamic QR`:
  - ON: codes with **Enable dynamic tracking** checked use tracked redirect URL.
  - OFF: tracked redirects are blocked, and QR output uses static payload.
- `Default QR Size`:
  - Applies to shortcode/block/render defaults and new QR code records.

## Next implementation phases for full parity

1. **Designer UI:** gradients, dot/eye style, logo overlay, margin/alignment controls, SVG pipeline.
2. **Dynamic routing engine:** device/OS/geo rules, schedules, expiry by scans, password protection, A/B rotation.
3. **Analytics warehouse:** unique visitors, browser/OS/city, charts, CSV exports, realtime dashboard.
4. **Campaign orchestration:** campaign-level assignment, statuses, date windows, comparison reports.
5. **Bulk operations:** CSV import generator, bulk campaign assignment, ZIP export pipeline.
6. **Permissions:** role-based feature toggles and visibility constraints.
7. **Integrations:** GA event stream + UTM helpers, WooCommerce product/cart rendering hooks.
8. **Media/export:** reliable PNG/SVG generation inside WordPress without third-party API dependency.

## Quick usage

- Shortcode URL:
  - `[jaqr type="url" content="https://example.com"]`
- Shortcode WhatsApp:
  - `[jaqr type="whatsapp" phone="15551234567" message="Hello"]`
- Dynamic QR destination:
  - Create `jaqr_code` post and set meta `_jaqr_target_url`.
  - Use track URL: `/wp-json/jaqr/v1/track/{POST_ID}`.

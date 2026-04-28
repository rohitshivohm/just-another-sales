<?php

namespace JAQR;

if (! defined('ABSPATH')) {
    exit;
}

class Shortcode
{
    public static function register(): void
    {
        add_shortcode('jaqr', [self::class, 'render_shortcode']);
    }

    public static function render_shortcode(array $atts = []): string
    {
        $settings = get_option('jaqr_settings', []);
        $default_size = max(100, min(1024, (int) ($settings['default_size'] ?? 220)));

        $atts = shortcode_atts([
            'type' => 'url',
            'content' => home_url('/'),
            'size' => $default_size,
            'margin' => 1,
            'fg' => '#000000',
            'bg' => '#ffffff',
            'alt' => __('QR code', 'just-another-qr'),
            'frame' => '',
            'phone' => '',
            'email' => '',
            'subject' => '',
            'body' => '',
            'message' => '',
            'ssid' => '',
            'password' => '',
            'encryption' => 'WPA',
            'show_center_text' => 0,
            'center_text' => '',
        ], $atts, 'jaqr');

        $atts['content'] = self::build_payload($atts);

        return Renderer::render_qr($atts);
    }

    public static function build_payload(array $atts): string
    {
        $type = strtolower((string) $atts['type']);

        return match ($type) {
            'text' => (string) $atts['content'],
            'phone' => 'tel:' . preg_replace('/\s+/', '', (string) ($atts['phone'] ?: $atts['content'])),
            'email' => sprintf('mailto:%s?subject=%s&body=%s', rawurlencode((string) ($atts['email'] ?: $atts['content'])), rawurlencode((string) $atts['subject']), rawurlencode((string) $atts['body'])),
            'sms' => sprintf('smsto:%s:%s', preg_replace('/\s+/', '', (string) ($atts['phone'] ?: $atts['content'])), (string) ($atts['message'] ?: $atts['content'])),
            'whatsapp' => 'https://wa.me/' . preg_replace('/\D+/', '', (string) ($atts['phone'] ?: $atts['content'])) . '?text=' . rawurlencode((string) $atts['message']),
            'wifi' => sprintf('WIFI:T:%s;S:%s;P:%s;;', sanitize_text_field((string) $atts['encryption']), sanitize_text_field((string) $atts['ssid']), sanitize_text_field((string) $atts['password'])),
            'vcard' => self::build_vcard($atts),
            default => esc_url_raw((string) $atts['content']),
        };
    }

    private static function build_vcard(array $atts): string
    {
        $name = sanitize_text_field((string) ($atts['name'] ?? ''));
        $org = sanitize_text_field((string) ($atts['org'] ?? ''));
        $phone = sanitize_text_field((string) ($atts['phone'] ?? ''));
        $email = sanitize_email((string) ($atts['email'] ?? ''));

        return "BEGIN:VCARD\nVERSION:3.0\nFN:{$name}\nORG:{$org}\nTEL:{$phone}\nEMAIL:{$email}\nEND:VCARD";
    }
}

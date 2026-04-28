<?php

namespace JAQR;

if (! defined('ABSPATH')) {
    exit;
}

class Qr_Generator
{
    /**
     * Builds a QR image endpoint URL. Uses a remote image API as a portable default.
     * You can swap this with an in-plugin generator later (BaconQrCode/endroid, etc).
     */
    public static function build_image_url(array $args): string
    {
        $payload = rawurlencode((string) ($args['content'] ?? home_url('/')));
        $size = max(100, min((int) ($args['size'] ?? 220), 2048));
        $margin = max(0, min((int) ($args['margin'] ?? 1), 20));

        $color = self::sanitize_hex($args['fg'] ?? '#000000', '000000');
        $bg = self::sanitize_hex($args['bg'] ?? '#ffffff', 'ffffff');

        $base = 'https://api.qrserver.com/v1/create-qr-code/';

        return add_query_arg([
            'size' => $size . 'x' . $size,
            'margin' => $margin,
            'data' => $payload,
            'color' => $color,
            'bgcolor' => $bg,
            'format' => 'png',
        ], $base);
    }

    public static function build_svg_data_uri(array $args): string
    {
        $url = self::build_image_url(array_merge($args, ['format' => 'svg']));

        return esc_url_raw($url);
    }

    private static function sanitize_hex(string $hex, string $fallback): string
    {
        $hex = ltrim($hex, '#');

        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return strtolower($hex);
        }

        return $fallback;
    }
}

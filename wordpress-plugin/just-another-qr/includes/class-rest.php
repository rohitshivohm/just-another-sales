<?php

namespace JAQR;

use WP_REST_Request;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

class Rest
{
    public static function register_routes(): void
    {
        register_rest_route('jaqr/v1', '/preview', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [self::class, 'preview'],
        ]);

        register_rest_route('jaqr/v1', '/track/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [self::class, 'track_and_redirect'],
            'args' => [
                'id' => [
                    'validate_callback' => static fn ($param) => is_numeric($param),
                ],
            ],
        ]);
    }

    public static function preview(WP_REST_Request $request)
    {
        $content = (string) $request->get_param('content');
        $size = (int) $request->get_param('size');

        return [
            'image' => Qr_Generator::build_image_url([
                'content' => $content ?: home_url('/'),
                'size' => $size ?: 220,
            ]),
        ];
    }

    public static function track_and_redirect(WP_REST_Request $request)
    {
        if (! Code_Manager::dynamic_enabled_globally()) {
            return new \WP_Error('jaqr_dynamic_disabled', __('Dynamic QR is disabled in plugin settings.', 'just-another-qr'), ['status' => 403]);
        }

        $id = (int) $request->get_param('id');

        $target = get_post_meta($id, '_jaqr_target_url', true);
        if (! $target) {
            return new \WP_Error('jaqr_not_found', __('QR destination not configured.', 'just-another-qr'), ['status' => 404]);
        }

        self::record_scan($id);

        wp_redirect(esc_url_raw((string) $target), 302);
        exit;
    }

    private static function record_scan(int $post_id): void
    {
        $total = (int) get_post_meta($post_id, '_jaqr_total_scans', true);
        update_post_meta($post_id, '_jaqr_total_scans', $total + 1);

        $daily_key = '_jaqr_scans_' . gmdate('Ymd');
        $daily = (int) get_post_meta($post_id, $daily_key, true);
        update_post_meta($post_id, $daily_key, $daily + 1);
    }
}

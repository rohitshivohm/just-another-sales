<?php

namespace JAQR;

if (! defined('ABSPATH')) {
    exit;
}

class Post_Types
{
    public static function activate(): void
    {
        self::register();
        flush_rewrite_rules();
    }

    public static function register(): void
    {
        register_post_type('jaqr_code', [
            'label' => __('QR Codes', 'just-another-qr'),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => ['title', 'custom-fields'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        register_post_type('jaqr_campaign', [
            'label' => __('QR Campaigns', 'just-another-qr'),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'custom-fields'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }
}

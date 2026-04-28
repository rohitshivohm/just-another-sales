<?php

namespace JAQR;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->hooks();
    }

    private function load_dependencies(): void
    {
        require_once JAQR_DIR . 'includes/class-post-types.php';
        require_once JAQR_DIR . 'includes/class-qr-generator.php';
        require_once JAQR_DIR . 'includes/class-renderer.php';
        require_once JAQR_DIR . 'includes/class-shortcode.php';
        require_once JAQR_DIR . 'includes/class-widget.php';
        require_once JAQR_DIR . 'includes/class-rest.php';
        require_once JAQR_DIR . 'includes/class-admin.php';
    }

    private function hooks(): void
    {
        register_activation_hook(JAQR_FILE, [Post_Types::class, 'activate']);

        add_action('init', [Post_Types::class, 'register']);
        add_action('init', [Shortcode::class, 'register']);
        add_action('init', [$this, 'register_block']);
        add_action('widgets_init', [JAQR_Widget::class, 'register']);

        add_action('rest_api_init', [Rest::class, 'register_routes']);
        add_action('admin_menu', [Admin::class, 'register_menu']);
        add_action('admin_init', [Admin::class, 'register_settings']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_filter('render_block', [$this, 'render_jaqr_block'], 10, 2);
    }

    public function enqueue_frontend_assets(): void
    {
        wp_register_style('jaqr-style', JAQR_URL . 'assets/css/jaqr.css', [], JAQR_VERSION);
        wp_enqueue_style('jaqr-style');
    }

    public function enqueue_admin_assets(): void
    {
        wp_register_script('jaqr-block', JAQR_URL . 'assets/js/block.js', ['wp-blocks', 'wp-element', 'wp-editor'], JAQR_VERSION, true);
    }

    public function register_block(): void
    {
        $this->enqueue_admin_assets();

        register_block_type('jaqr/qr-code', [
            'editor_script' => 'jaqr-block',
            'render_callback' => static function (array $attrs): string {
                return Renderer::render_qr([
                    'content' => $attrs['content'] ?? home_url('/'),
                    'size' => (int) ($attrs['size'] ?? 220),
                    'alt' => $attrs['alt'] ?? __('QR code', 'just-another-qr'),
                    'frame' => $attrs['frame'] ?? '',
                ]);
            },
        ]);
    }

    /**
     * Lightweight block rendering without build step.
     */
    public function render_jaqr_block(string $block_content, array $block): string
    {
        if (($block['blockName'] ?? '') !== 'jaqr/qr-code') {
            return $block_content;
        }

        $attrs = $block['attrs'] ?? [];

        return Renderer::render_qr([
            'content' => $attrs['content'] ?? home_url('/'),
            'size' => (int) ($attrs['size'] ?? 220),
            'alt' => $attrs['alt'] ?? __('QR code', 'just-another-qr'),
            'frame' => ! empty($attrs['frame']) ? (string) $attrs['frame'] : '',
        ]);
    }
}

<?php

namespace JAQR;

if (! defined('ABSPATH')) {
    exit;
}

class Code_Manager
{
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
        add_action('save_post_jaqr_code', [self::class, 'save_code_meta']);

        add_shortcode('jaqr_code', [self::class, 'render_code_shortcode']);

        add_filter('manage_jaqr_code_posts_columns', [self::class, 'register_columns']);
        add_action('manage_jaqr_code_posts_custom_column', [self::class, 'render_columns'], 10, 2);
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box(
            'jaqr-code-config',
            __('QR Code Configuration', 'just-another-qr'),
            [self::class, 'render_code_meta_box'],
            'jaqr_code',
            'normal',
            'high'
        );
    }

    public static function render_code_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('jaqr_save_code_meta', 'jaqr_code_nonce');

        $meta = self::get_code_meta($post->ID);
        $preview = self::resolve_qr_content($post->ID, $meta);
        ?>
        <p>
            <label for="jaqr_type"><strong><?php esc_html_e('Content Type', 'just-another-qr'); ?></strong></label><br>
            <select id="jaqr_type" name="jaqr_type">
                <?php foreach (self::content_types() as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['type'], $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="jaqr_content"><strong><?php esc_html_e('Content / URL', 'just-another-qr'); ?></strong></label><br>
            <input class="widefat" id="jaqr_content" name="jaqr_content" type="text" value="<?php echo esc_attr($meta['content']); ?>">
        </p>
        <p>
            <label for="jaqr_target_url"><strong><?php esc_html_e('Dynamic Destination URL', 'just-another-qr'); ?></strong></label><br>
            <input class="widefat" id="jaqr_target_url" name="jaqr_target_url" type="url" value="<?php echo esc_attr($meta['target_url']); ?>">
            <small><?php esc_html_e('If enabled, scans go through tracking URL and redirect to this destination.', 'just-another-qr'); ?></small>
        </p>
        <p>
            <label><input type="checkbox" name="jaqr_is_dynamic" value="1" <?php checked($meta['is_dynamic'], 1); ?>> <?php esc_html_e('Enable dynamic tracking for this QR', 'just-another-qr'); ?></label>
        </p>
        <p>
            <label for="jaqr_size"><strong><?php esc_html_e('Size (px)', 'just-another-qr'); ?></strong></label><br>
            <input id="jaqr_size" name="jaqr_size" type="number" min="100" max="1024" value="<?php echo esc_attr((string) $meta['size']); ?>">
        </p>
        <p>
            <label for="jaqr_alt"><strong><?php esc_html_e('Alt Text', 'just-another-qr'); ?></strong></label><br>
            <input class="widefat" id="jaqr_alt" name="jaqr_alt" type="text" value="<?php echo esc_attr($meta['alt']); ?>">
        </p>
        <p>
            <label for="jaqr_frame"><strong><?php esc_html_e('Frame Label', 'just-another-qr'); ?></strong></label><br>
            <input class="widefat" id="jaqr_frame" name="jaqr_frame" type="text" value="<?php echo esc_attr($meta['frame']); ?>">
        </p>
        <hr>
        <p><strong><?php esc_html_e('Quick Embed', 'just-another-qr'); ?></strong></p>
        <code>[jaqr_code id="<?php echo esc_html((string) $post->ID); ?>"]</code>
        <p><strong><?php esc_html_e('Live Preview', 'just-another-qr'); ?></strong></p>
        <?php echo Renderer::render_qr([
            'content' => $preview,
            'size' => $meta['size'],
            'alt' => $meta['alt'],
            'frame' => $meta['frame'],
        ]); ?>
        <?php
    }

    public static function save_code_meta(int $post_id): void
    {
        if (! isset($_POST['jaqr_code_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['jaqr_code_nonce'])), 'jaqr_save_code_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $type = sanitize_key((string) ($_POST['jaqr_type'] ?? 'url'));
        $content = sanitize_text_field((string) ($_POST['jaqr_content'] ?? ''));
        $target_url = esc_url_raw((string) ($_POST['jaqr_target_url'] ?? ''));
        $is_dynamic = empty($_POST['jaqr_is_dynamic']) ? 0 : 1;
        $size = max(100, min(1024, (int) ($_POST['jaqr_size'] ?? self::default_size())));
        $alt = sanitize_text_field((string) ($_POST['jaqr_alt'] ?? __('QR code', 'just-another-qr')));
        $frame = sanitize_text_field((string) ($_POST['jaqr_frame'] ?? ''));

        update_post_meta($post_id, '_jaqr_type', $type);
        update_post_meta($post_id, '_jaqr_content', $content);
        update_post_meta($post_id, '_jaqr_target_url', $target_url);
        update_post_meta($post_id, '_jaqr_is_dynamic', $is_dynamic);
        update_post_meta($post_id, '_jaqr_size', $size);
        update_post_meta($post_id, '_jaqr_alt', $alt);
        update_post_meta($post_id, '_jaqr_frame', $frame);
    }

    public static function render_code_shortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'jaqr_code');

        $id = (int) $atts['id'];
        if ($id <= 0 || get_post_type($id) !== 'jaqr_code') {
            return '';
        }

        $meta = self::get_code_meta($id);
        $content = self::resolve_qr_content($id, $meta);

        return Renderer::render_qr([
            'content' => $content,
            'size' => $meta['size'],
            'alt' => $meta['alt'],
            'frame' => $meta['frame'],
        ]);
    }

    public static function resolve_qr_content(int $post_id, array $meta): string
    {
        $base_content = Shortcode::build_payload([
            'type' => $meta['type'],
            'content' => $meta['content'],
            'phone' => $meta['content'],
            'email' => $meta['content'],
            'subject' => '',
            'body' => '',
            'message' => '',
            'ssid' => '',
            'password' => '',
            'encryption' => 'WPA',
        ]);

        if (! self::dynamic_enabled_globally() || (int) $meta['is_dynamic'] !== 1 || empty($meta['target_url'])) {
            return $base_content;
        }

        return rest_url('jaqr/v1/track/' . $post_id);
    }

    public static function get_code_meta(int $post_id): array
    {
        return [
            'type' => (string) get_post_meta($post_id, '_jaqr_type', true) ?: 'url',
            'content' => (string) get_post_meta($post_id, '_jaqr_content', true) ?: home_url('/'),
            'target_url' => (string) get_post_meta($post_id, '_jaqr_target_url', true),
            'is_dynamic' => (int) get_post_meta($post_id, '_jaqr_is_dynamic', true),
            'size' => (int) get_post_meta($post_id, '_jaqr_size', true) ?: self::default_size(),
            'alt' => (string) get_post_meta($post_id, '_jaqr_alt', true) ?: __('QR code', 'just-another-qr'),
            'frame' => (string) get_post_meta($post_id, '_jaqr_frame', true),
            'total_scans' => (int) get_post_meta($post_id, '_jaqr_total_scans', true),
        ];
    }

    public static function register_columns(array $columns): array
    {
        $columns['jaqr_shortcode'] = __('Shortcode', 'just-another-qr');
        $columns['jaqr_scans'] = __('Scans', 'just-another-qr');

        return $columns;
    }

    public static function render_columns(string $column, int $post_id): void
    {
        if ($column === 'jaqr_shortcode') {
            echo '<code>[jaqr_code id="' . esc_html((string) $post_id) . '"]</code>';
            return;
        }

        if ($column === 'jaqr_scans') {
            echo esc_html((string) ((int) get_post_meta($post_id, '_jaqr_total_scans', true)));
        }
    }

    public static function dynamic_enabled_globally(): bool
    {
        $settings = get_option('jaqr_settings', []);

        return ! empty($settings['enable_dynamic']);
    }

    private static function default_size(): int
    {
        $settings = get_option('jaqr_settings', []);

        return max(100, min(1024, (int) ($settings['default_size'] ?? 220)));
    }

    private static function content_types(): array
    {
        return [
            'url' => __('URL', 'just-another-qr'),
            'text' => __('Text', 'just-another-qr'),
            'phone' => __('Phone', 'just-another-qr'),
            'email' => __('Email', 'just-another-qr'),
            'sms' => __('SMS', 'just-another-qr'),
            'whatsapp' => __('WhatsApp', 'just-another-qr'),
            'wifi' => __('WiFi', 'just-another-qr'),
            'vcard' => __('vCard', 'just-another-qr'),
        ];
    }
}

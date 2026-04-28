<?php

namespace JAQR;

if (! defined('ABSPATH')) {
    exit;
}

class Admin
{
    public static function register_menu(): void
    {
        add_menu_page(
            __('Just Another QR', 'just-another-qr'),
            __('Just Another QR', 'just-another-qr'),
            'manage_options',
            'jaqr-dashboard',
            [self::class, 'render_dashboard'],
            'dashicons-qrcode',
            56
        );

        add_submenu_page('jaqr-dashboard', __('Dashboard', 'just-another-qr'), __('Dashboard', 'just-another-qr'), 'manage_options', 'jaqr-dashboard', [self::class, 'render_dashboard']);
        add_submenu_page('jaqr-dashboard', __('QR Library', 'just-another-qr'), __('QR Library', 'just-another-qr'), 'edit_posts', 'edit.php?post_type=jaqr_code');
        add_submenu_page('jaqr-dashboard', __('Campaigns', 'just-another-qr'), __('Campaigns', 'just-another-qr'), 'edit_posts', 'edit.php?post_type=jaqr_campaign');
        add_submenu_page('jaqr-dashboard', __('Settings', 'just-another-qr'), __('Settings', 'just-another-qr'), 'manage_options', 'jaqr-settings', [self::class, 'render_settings']);
    }

    public static function register_settings(): void
    {
        register_setting('jaqr_settings', 'jaqr_settings', [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_settings'],
            'default' => self::default_settings(),
        ]);
    }

    public static function sanitize_settings($input): array
    {
        $input = is_array($input) ? $input : [];

        return [
            'enable_dynamic' => empty($input['enable_dynamic']) ? 0 : 1,
            'utm_enabled' => empty($input['utm_enabled']) ? 0 : 1,
            'default_size' => max(100, min(1024, (int) ($input['default_size'] ?? 220))),
        ];
    }

    public static function render_dashboard(): void
    {
        $total_qr = (int) wp_count_posts('jaqr_code')->publish;
        $total_campaigns = (int) wp_count_posts('jaqr_campaign')->publish;
        $latest_codes = get_posts([
            'post_type' => 'jaqr_code',
            'post_status' => 'publish',
            'numberposts' => 5,
        ]);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Just Another QR Dashboard', 'just-another-qr'); ?></h1>
            <p><?php esc_html_e('Create QR codes, embed them with shortcode, and track dynamic redirect scans.', 'just-another-qr'); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=jaqr_code')); ?>">
                    <?php esc_html_e('Create QR Code', 'just-another-qr'); ?>
                </a>
            </p>
            <ul>
                <li><strong><?php esc_html_e('QR Codes', 'just-another-qr'); ?>:</strong> <?php echo esc_html((string) $total_qr); ?></li>
                <li><strong><?php esc_html_e('Campaigns', 'just-another-qr'); ?>:</strong> <?php echo esc_html((string) $total_campaigns); ?></li>
            </ul>
            <h2><?php esc_html_e('Recent QR Codes', 'just-another-qr'); ?></h2>
            <?php if (! empty($latest_codes)) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'just-another-qr'); ?></th>
                            <th><?php esc_html_e('Shortcode', 'just-another-qr'); ?></th>
                            <th><?php esc_html_e('Scans', 'just-another-qr'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($latest_codes as $code): ?>
                        <tr>
                            <td><a href="<?php echo esc_url(get_edit_post_link($code->ID)); ?>"><?php echo esc_html(get_the_title($code->ID)); ?></a></td>
                            <td><code>[jaqr_code id="<?php echo esc_html((string) $code->ID); ?>"]</code></td>
                            <td><?php echo esc_html((string) ((int) get_post_meta($code->ID, '_jaqr_total_scans', true))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('No QR codes yet. Create one to get started.', 'just-another-qr'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_settings(): void
    {
        $settings = wp_parse_args((array) get_option('jaqr_settings', []), self::default_settings());
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Just Another QR Settings', 'just-another-qr'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('jaqr_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Dynamic QR', 'just-another-qr'); ?></th>
                        <td><input type="checkbox" name="jaqr_settings[enable_dynamic]" value="1" <?php checked(! empty($settings['enable_dynamic'])); ?> /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable UTM Support', 'just-another-qr'); ?></th>
                        <td><input type="checkbox" name="jaqr_settings[utm_enabled]" value="1" <?php checked(! empty($settings['utm_enabled'])); ?> /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default QR Size', 'just-another-qr'); ?></th>
                        <td><input type="number" min="100" max="1024" name="jaqr_settings[default_size]" value="<?php echo esc_attr((string) ($settings['default_size'] ?? 220)); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function default_settings(): array
    {
        return [
            'enable_dynamic' => 1,
            'utm_enabled' => 1,
            'default_size' => 220,
        ];
    }
}

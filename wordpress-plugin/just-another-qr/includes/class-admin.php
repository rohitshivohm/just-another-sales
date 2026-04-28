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
        add_submenu_page('jaqr-dashboard', __('QR Builder', 'just-another-qr'), __('QR Builder', 'just-another-qr'), 'edit_posts', 'jaqr-builder', [self::class, 'render_builder']);
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
            <p><?php esc_html_e('You do NOT need to create a QR Code post for every use-case — you can also use the QR Builder page or the [jaqr] shortcode directly.', 'just-another-qr'); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=jaqr_code')); ?>">
                    <?php esc_html_e('Create QR Code', 'just-another-qr'); ?>
                </a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=jaqr-builder')); ?>">
                    <?php esc_html_e('Open QR Builder', 'just-another-qr'); ?>
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

    public static function render_builder(): void
    {
        $settings = wp_parse_args((array) get_option('jaqr_settings', []), self::default_settings());
        $type = sanitize_key((string) ($_GET['type'] ?? 'url'));
        $content = sanitize_text_field((string) ($_GET['content'] ?? home_url('/')));
        $size = max(100, min(1024, (int) ($_GET['size'] ?? $settings['default_size'])));
        $frame = sanitize_text_field((string) ($_GET['frame'] ?? ''));
        $alt = sanitize_text_field((string) ($_GET['alt'] ?? __('QR code', 'just-another-qr')));

        $shortcode = sprintf(
            '[jaqr type="%s" content="%s" size="%d" frame="%s" alt="%s"]',
            esc_attr($type),
            esc_attr($content),
            $size,
            esc_attr($frame),
            esc_attr($alt)
        );
        ?>
        <div class="wrap jaqr-admin-wrap">
            <h1><?php esc_html_e('QR Builder', 'just-another-qr'); ?></h1>
            <p><?php esc_html_e('Generate a QR instantly without creating a post. Tune values, preview, and copy shortcode.', 'just-another-qr'); ?></p>

            <div class="jaqr-builder-grid">
                <div class="jaqr-card">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="jaqr-builder" />
                        <p>
                            <label for="jaqr_builder_type"><strong><?php esc_html_e('Type', 'just-another-qr'); ?></strong></label><br>
                            <select id="jaqr_builder_type" name="type">
                                <option value="url" <?php selected($type, 'url'); ?>><?php esc_html_e('URL', 'just-another-qr'); ?></option>
                                <option value="text" <?php selected($type, 'text'); ?>><?php esc_html_e('Text', 'just-another-qr'); ?></option>
                                <option value="phone" <?php selected($type, 'phone'); ?>><?php esc_html_e('Phone', 'just-another-qr'); ?></option>
                                <option value="email" <?php selected($type, 'email'); ?>><?php esc_html_e('Email', 'just-another-qr'); ?></option>
                            </select>
                        </p>
                        <p>
                            <label for="jaqr_builder_content"><strong><?php esc_html_e('Content', 'just-another-qr'); ?></strong></label><br>
                            <input class="widefat" id="jaqr_builder_content" name="content" type="text" value="<?php echo esc_attr($content); ?>" />
                        </p>
                        <p>
                            <label for="jaqr_builder_size"><strong><?php esc_html_e('Size', 'just-another-qr'); ?></strong></label><br>
                            <input id="jaqr_builder_size" name="size" type="number" min="100" max="1024" value="<?php echo esc_attr((string) $size); ?>" />
                        </p>
                        <p>
                            <label for="jaqr_builder_frame"><strong><?php esc_html_e('Frame Label', 'just-another-qr'); ?></strong></label><br>
                            <input class="widefat" id="jaqr_builder_frame" name="frame" type="text" value="<?php echo esc_attr($frame); ?>" />
                        </p>
                        <p>
                            <label for="jaqr_builder_alt"><strong><?php esc_html_e('Alt Text', 'just-another-qr'); ?></strong></label><br>
                            <input class="widefat" id="jaqr_builder_alt" name="alt" type="text" value="<?php echo esc_attr($alt); ?>" />
                        </p>
                        <p>
                            <button class="button button-primary" type="submit"><?php esc_html_e('Generate Preview', 'just-another-qr'); ?></button>
                        </p>
                    </form>
                </div>

                <div class="jaqr-card">
                    <h2><?php esc_html_e('Live Preview', 'just-another-qr'); ?></h2>
                    <?php echo do_shortcode($shortcode); ?>

                    <h3><?php esc_html_e('Shortcode', 'just-another-qr'); ?></h3>
                    <textarea class="widefat jaqr-shortcode-output" rows="3" readonly><?php echo esc_textarea($shortcode); ?></textarea>
                    <p>
                        <button type="button" class="button jaqr-copy-shortcode" data-copy-target=".jaqr-shortcode-output">
                            <?php esc_html_e('Copy Shortcode', 'just-another-qr'); ?>
                        </button>
                    </p>
                </div>
            </div>
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

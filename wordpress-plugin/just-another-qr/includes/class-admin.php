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
            'brand_name' => sanitize_text_field((string) ($input['brand_name'] ?? '')),
            'show_brand_center' => empty($input['show_brand_center']) ? 0 : 1,
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
            <h2><?php esc_html_e('Last 7 Days Scan Trend', 'just-another-qr'); ?></h2>
            <div class="jaqr-trend">
                <?php foreach (self::get_scan_trend(7) as $point) : ?>
                    <div class="jaqr-trend-item">
                        <span class="jaqr-trend-label"><?php echo esc_html($point['label']); ?></span>
                        <span class="jaqr-trend-bar"><span style="width: <?php echo esc_attr((string) $point['percent']); ?>%"></span></span>
                        <span class="jaqr-trend-value"><?php echo esc_html((string) $point['value']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
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
                    <tr>
                        <th scope="row"><?php esc_html_e('Brand Name', 'just-another-qr'); ?></th>
                        <td><input type="text" class="regular-text" name="jaqr_settings[brand_name]" value="<?php echo esc_attr((string) ($settings['brand_name'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Show Brand in QR Center by Default', 'just-another-qr'); ?></th>
                        <td><input type="checkbox" name="jaqr_settings[show_brand_center]" value="1" <?php checked(! empty($settings['show_brand_center'])); ?> /></td>
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
        $center_text = sanitize_text_field((string) ($_GET['center_text'] ?? ($settings['brand_name'] ?? '')));
        $show_center_text = isset($_GET['show_center_text'])
            ? (int) (((string) $_GET['show_center_text']) === '1')
            : (int) ($settings['show_brand_center'] ?? 0);
        $fg = sanitize_hex_color((string) ($_GET['fg'] ?? '#000000')) ?: '#000000';
        $bg = sanitize_hex_color((string) ($_GET['bg'] ?? '#ffffff')) ?: '#ffffff';
        $margin = max(0, min(20, (int) ($_GET['margin'] ?? 1)));

        $shortcode = sprintf(
            '[jaqr type="%s" content="%s" size="%d" frame="%s" alt="%s" show_center_text="%d" center_text="%s" fg="%s" bg="%s" margin="%d"]',
            esc_attr($type),
            esc_attr($content),
            $size,
            esc_attr($frame),
            esc_attr($alt),
            $show_center_text,
            esc_attr($center_text),
            esc_attr($fg),
            esc_attr($bg),
            $margin
        );
        ?>
        <div class="wrap jaqr-admin-wrap">
            <h1><?php esc_html_e('QR Builder', 'just-another-qr'); ?></h1>
            <p><?php esc_html_e('Generate a QR instantly without creating a post. Tune values, preview, and copy shortcode.', 'just-another-qr'); ?></p>

            <div class="jaqr-builder-grid">
                <div class="jaqr-card">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="jaqr-builder" />
                        <input type="hidden" name="show_center_text" value="0" />
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
                            <label><input type="checkbox" name="show_center_text" value="1" <?php checked($show_center_text, 1); ?> /> <?php esc_html_e('Show brand text in center', 'just-another-qr'); ?></label>
                        </p>
                        <p>
                            <label for="jaqr_builder_center_text"><strong><?php esc_html_e('Center Brand Text', 'just-another-qr'); ?></strong></label><br>
                            <input class="widefat" id="jaqr_builder_center_text" name="center_text" type="text" value="<?php echo esc_attr($center_text); ?>" />
                        </p>
                        <p>
                            <label for="jaqr_builder_fg"><strong><?php esc_html_e('Foreground', 'just-another-qr'); ?></strong></label><br>
                            <input id="jaqr_builder_fg" name="fg" type="color" value="<?php echo esc_attr($fg); ?>" />
                        </p>
                        <p>
                            <label for="jaqr_builder_bg"><strong><?php esc_html_e('Background', 'just-another-qr'); ?></strong></label><br>
                            <input id="jaqr_builder_bg" name="bg" type="color" value="<?php echo esc_attr($bg); ?>" />
                        </p>
                        <p>
                            <label for="jaqr_builder_margin"><strong><?php esc_html_e('Margin', 'just-another-qr'); ?></strong></label><br>
                            <input id="jaqr_builder_margin" name="margin" type="number" min="0" max="20" value="<?php echo esc_attr((string) $margin); ?>" />
                        </p>
                        <p>
                            <button class="button button-primary" type="submit"><?php esc_html_e('Generate Preview', 'just-another-qr'); ?></button>
                        </p>
                    </form>
                </div>

                <div class="jaqr-card">
                    <h2><?php esc_html_e('Live Preview', 'just-another-qr'); ?></h2>
                    <?php echo Renderer::render_qr([
                        'content' => Shortcode::build_payload([
                            'type' => $type,
                            'content' => $content,
                            'phone' => $content,
                            'email' => $content,
                            'subject' => '',
                            'body' => '',
                            'message' => '',
                            'ssid' => '',
                            'password' => '',
                            'encryption' => 'WPA',
                        ]),
                        'size' => $size,
                        'alt' => $alt,
                        'frame' => $frame,
                        'show_center_text' => $show_center_text,
                        'center_text' => $center_text,
                        'fg' => $fg,
                        'bg' => $bg,
                        'margin' => $margin,
                        'show_downloads' => true,
                    ]); ?>

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
            'brand_name' => '',
            'show_brand_center' => 0,
        ];
    }

    private static function get_scan_trend(int $days = 7): array
    {
        $days = max(1, min(31, $days));
        $codes = get_posts([
            'post_type' => 'jaqr_code',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        $series = [];
        $max = 0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = gmdate('Ymd', strtotime("-{$i} days"));
            $label = gmdate('M d', strtotime("-{$i} days"));
            $value = 0;
            foreach ($codes as $id) {
                $value += (int) get_post_meta((int) $id, '_jaqr_scans_' . $day, true);
            }
            $max = max($max, $value);
            $series[] = ['label' => $label, 'value' => $value];
        }

        foreach ($series as &$point) {
            $point['percent'] = $max > 0 ? max(4, (int) round(($point['value'] / $max) * 100)) : 4;
        }

        return $series;
    }
}

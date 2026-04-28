<?php

namespace JAQR;

if (! defined('ABSPATH')) {
    exit;
}

class Renderer
{
    public static function render_qr(array $atts): string
    {
        $settings = get_option('jaqr_settings', []);
        $default_size = max(100, min(1024, (int) ($settings['default_size'] ?? 220)));

        $defaults = [
            'content' => home_url('/'),
            'size' => $default_size,
            'margin' => 1,
            'fg' => '#000000',
            'bg' => '#ffffff',
            'alt' => __('QR code', 'just-another-qr'),
            'frame' => '',
            'class' => '',
            'show_center_text' => false,
            'center_text' => '',
            'show_downloads' => false,
        ];

        $args = wp_parse_args($atts, $defaults);
        $img = Qr_Generator::build_image_url($args);

        $label = trim((string) $args['frame']);
        $wrapper_class = 'jaqr-wrap ' . sanitize_html_class((string) $args['class']);
        $center_text = trim((string) $args['center_text']);
        $show_center_text = ! empty($args['show_center_text']) && $center_text !== '';
        $download_png = Qr_Generator::build_download_url($args, 'png');
        $download_svg = Qr_Generator::build_download_url($args, 'svg');

        ob_start();
        ?>
        <figure class="<?php echo esc_attr($wrapper_class); ?>">
            <?php if ($label !== ''): ?>
                <figcaption class="jaqr-frame"><?php echo esc_html($label); ?></figcaption>
            <?php endif; ?>
            <div class="jaqr-canvas">
                <img
                    src="<?php echo esc_url($img); ?>"
                    alt="<?php echo esc_attr((string) $args['alt']); ?>"
                    width="<?php echo esc_attr((int) $args['size']); ?>"
                    height="<?php echo esc_attr((int) $args['size']); ?>"
                    loading="lazy"
                    decoding="async"
                />
                <?php if ($show_center_text) : ?>
                    <span class="jaqr-center-badge"><?php echo esc_html($center_text); ?></span>
                <?php endif; ?>
            </div>
            <?php if (! empty($args['show_downloads'])) : ?>
                <div class="jaqr-actions">
                    <a class="jaqr-btn" href="<?php echo esc_url($download_png); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Download PNG', 'just-another-qr'); ?></a>
                    <a class="jaqr-btn" href="<?php echo esc_url($download_svg); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Download SVG', 'just-another-qr'); ?></a>
                </div>
            <?php endif; ?>
        </figure>
        <?php

        return (string) ob_get_clean();
    }
}

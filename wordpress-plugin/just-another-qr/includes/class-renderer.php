<?php

namespace JAQR;

if (! defined('ABSPATH')) {
    exit;
}

class Renderer
{
    public static function render_qr(array $atts): string
    {
        $defaults = [
            'content' => home_url('/'),
            'size' => 220,
            'margin' => 1,
            'fg' => '#000000',
            'bg' => '#ffffff',
            'alt' => __('QR code', 'just-another-qr'),
            'frame' => '',
            'class' => '',
        ];

        $args = wp_parse_args($atts, $defaults);
        $img = Qr_Generator::build_image_url($args);

        $label = trim((string) $args['frame']);
        $wrapper_class = 'jaqr-wrap ' . sanitize_html_class((string) $args['class']);

        ob_start();
        ?>
        <figure class="<?php echo esc_attr($wrapper_class); ?>">
            <?php if ($label !== ''): ?>
                <figcaption class="jaqr-frame"><?php echo esc_html($label); ?></figcaption>
            <?php endif; ?>
            <img
                src="<?php echo esc_url($img); ?>"
                alt="<?php echo esc_attr((string) $args['alt']); ?>"
                width="<?php echo esc_attr((int) $args['size']); ?>"
                height="<?php echo esc_attr((int) $args['size']); ?>"
                loading="lazy"
                decoding="async"
            />
        </figure>
        <?php

        return (string) ob_get_clean();
    }
}

<?php

namespace JAQR;

if (! defined('ABSPATH')) {
    exit;
}

class JAQR_Widget extends \WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'jaqr_widget',
            __('Just Another QR', 'just-another-qr'),
            ['description' => __('Displays a customizable QR code.', 'just-another-qr')]
        );
    }

    public static function register(): void
    {
        register_widget(self::class);
    }

    public function widget($args, $instance): void
    {
        echo $args['before_widget'];

        $content = $instance['content'] ?? home_url('/');
        $size = (int) ($instance['size'] ?? 220);

        echo Renderer::render_qr([
            'content' => $content,
            'size' => $size,
            'alt' => $instance['alt'] ?? __('QR code', 'just-another-qr'),
            'frame' => $instance['frame'] ?? '',
        ]);

        echo $args['after_widget'];
    }

    public function form($instance): void
    {
        $content = esc_attr($instance['content'] ?? home_url('/'));
        $size = (int) ($instance['size'] ?? 220);
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('content')); ?>"><?php esc_html_e('Content/URL', 'just-another-qr'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('content')); ?>" name="<?php echo esc_attr($this->get_field_name('content')); ?>" type="text" value="<?php echo $content; ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('size')); ?>"><?php esc_html_e('Size', 'just-another-qr'); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('size')); ?>" name="<?php echo esc_attr($this->get_field_name('size')); ?>" type="number" step="1" min="100" max="1024" value="<?php echo esc_attr((string) $size); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance): array
    {
        return [
            'content' => sanitize_text_field((string) ($new_instance['content'] ?? '')),
            'size' => (int) ($new_instance['size'] ?? 220),
            'alt' => sanitize_text_field((string) ($new_instance['alt'] ?? '')),
            'frame' => sanitize_text_field((string) ($new_instance['frame'] ?? '')),
        ];
    }
}

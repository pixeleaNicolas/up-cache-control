<?php
/**
 * Plugin Name: Up Cache Control
 * Description: Gestionnaire de cache pour désactiver les transients liés à Gutenberg et contrôler le cache.
 * Version: 1.1
 * Author: GEHIN Nicolas
 * Text Domain: up-cache-control
 * Domain Path: /languages
 */


defined('ABSPATH') || exit;

class Up_Cache_Control {
    private static $instance = null;
    
    private $transient_filters = [
        'disable_block_patterns_cache' => [
            '_wp_block_patterns_cache',
            '_wp_block_pattern_categories_cache'
        ],
        'disable_block_styles_cache' => [
            '_wp_block_styles_cache'
        ],
        'disable_gutenberg_features_cache' => [
            '_wp_gutenberg_features'
        ]
    ];

    private $default_options = [
        'disable_block_patterns_cache' => false,
        'disable_block_styles_cache' => false,
        'disable_gutenberg_features_cache' => false,
        'auto_flush_cache_on_update' => false,
    ];

    private function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('upgrader_process_complete', [$this, 'maybe_flush_cache'], 10, 2);
        $this->apply_transient_filters();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'up-cache-control',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    private function get_options() {
        return wp_parse_args(
            (array) get_option('up_cache_control_options', []),
            $this->default_options
        );
    }

    public function add_settings_page() {
        add_options_page(
            __('Up Cache Control', 'up-cache-control'),
            __('Up Cache', 'up-cache-control'),
            'manage_options',
            'up-cache-control',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        $options = $this->get_options();
        ?>
        <div class="wrap">
            <h1><?php _e('Up Cache Control', 'up-cache-control'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('up_cache_control_settings');
                do_settings_sections('up-cache-control');
                ?>
                <table class="form-table">
                    <?php foreach ($this->transient_filters as $key => $transients) : ?>
                        <tr>
                            <th scope="row"><?php printf(__('Désactiver le cache pour %s', 'up-cache-control'), $this->get_feature_name($key)); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="up_cache_control_options[<?php echo esc_attr($key); ?>]" 
                                           <?php checked($options[$key]); ?>>
                                    <?php printf(__('Désactiver le cache pour %s', 'up-cache-control'), $this->get_feature_name($key)); ?>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th scope="row"><?php _e('Vider automatiquement le cache', 'up-cache-control'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="up_cache_control_options[auto_flush_cache_on_update]" 
                                       <?php checked($options['auto_flush_cache_on_update']); ?>>
                                <?php _e('Vider le cache après les mises à jour', 'up-cache-control'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_feature_name($key) {
        $names = [
            'disable_block_patterns_cache' => __('Gutenberg Patterns', 'up-cache-control'),
            'disable_block_styles_cache' => __('Styles de blocs', 'up-cache-control'),
            'disable_gutenberg_features_cache' => __('Fonctionnalités Gutenberg', 'up-cache-control')
        ];
        return $names[$key] ?? $key;
    }

    public function register_settings() {
        register_setting(
            'up_cache_control_settings',
            'up_cache_control_options',
            ['sanitize_callback' => [$this, 'sanitize_options']]
        );
    }

    public function sanitize_options($input) {
        $sanitized = [];
        foreach ($this->default_options as $key => $default) {
            $sanitized[$key] = isset($input[$key]) && $input[$key] === 'on';
        }
        return $sanitized;
    }

    private function apply_transient_filters() {
        $options = $this->get_options();
        
        foreach ($this->transient_filters as $option_key => $transients) {
            if ($options[$option_key]) {
                foreach ($transients as $transient) {
                    add_filter("pre_set_transient_$transient", '__return_true');
                }
            }
        }
    }

    public function maybe_flush_cache($upgrader, $options) {
        if ($this->get_options()['auto_flush_cache_on_update']) {
            wp_cache_flush();
            $this->flush_transients_cache();
        }
    }

    private function flush_transients_cache() {
        foreach ($this->transient_filters as $transients) {
            foreach ($transients as $transient) {
                delete_transient($transient);
            }
        }
    }
}

// Initialisation du plugin
Up_Cache_Control::get_instance();
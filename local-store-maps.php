<?php
/**
 * Plugin Name: Local Store Maps
 * Description: Quản lý danh sách địa điểm cửa hàng và hiển thị bản đồ bằng shortcode [local_store] không cần Google Maps API.
 * Version: 1.1.18
 * Author: Wordpress
 * Text Domain: local-store-maps
 */

if (!defined('ABSPATH')) {
    exit;
}

final class LSM_Local_Store_Maps {
    const VERSION = '1.1.18';
    const OPTION_KEY = 'lsm_settings';
    const REGION_SEED_OPTION = 'lsm_regions_seeded_v1';
    const POST_TYPE = 'local_store';
    const TAXONOMY = 'local_store_region';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_store_meta'));

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'maybe_seed_regions'));

        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));

        add_shortcode('local_store', array($this, 'shortcode'));

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'store_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'store_column_content'), 10, 2);
    }

    public static function activate() {
        self::instance()->register_post_type();
        self::instance()->register_taxonomy();
        self::instance()->seed_regions();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function plugin_url($path = '') {
        return plugin_dir_url(__FILE__) . ltrim($path, '/');
    }

    public static function plugin_path($path = '') {
        return plugin_dir_path(__FILE__) . ltrim($path, '/');
    }

    public function defaults() {
        return array(
            'default_lat' => '21.0277644',
            'default_lng' => '105.8341598',
            'zoom' => '13',
            'marker_icon' => '',
            'google_maps_api' => '',
            'use_fontawesome' => '1',
            'enable_direction' => '1',
            'primary_color' => '#ffcc00',
        );
    }

    public function get_settings() {
        $settings = get_option(self::OPTION_KEY, array());
        return wp_parse_args(is_array($settings) ? $settings : array(), $this->defaults());
    }

    public function register_post_type() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name' => 'Cửa hàng',
                'singular_name' => 'Cửa hàng',
                'menu_name' => 'Cửa hàng',
                'add_new' => 'Thêm mới',
                'add_new_item' => 'Thêm mới cửa hàng',
                'edit_item' => 'Sửa cửa hàng',
                'new_item' => 'Cửa hàng mới',
                'view_item' => 'Xem cửa hàng',
                'search_items' => 'Tìm cửa hàng',
                'not_found' => 'Không có cửa hàng',
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-store',
            'supports' => array('title', 'editor', 'thumbnail', 'page-attributes'),
            'rewrite' => array('slug' => 'cua-hang'),
            'show_in_rest' => true,
        ));
    }

    public function register_taxonomy() {
        register_taxonomy(self::TAXONOMY, self::POST_TYPE, array(
            'labels' => array(
                'name' => 'Tỉnh thành',
                'singular_name' => 'Tỉnh thành',
                'menu_name' => 'Tỉnh thành',
                'all_items' => 'Tất cả tỉnh thành',
                'edit_item' => 'Sửa tỉnh thành',
                'add_new_item' => 'Thêm mới tỉnh thành',
                'parent_item' => 'Tỉnh/Thành cha',
            ),
            'hierarchical' => true,
            'show_admin_column' => true,
            'rewrite' => array('slug' => 'tinh-thanh'),
            'show_in_rest' => true,
        ));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'lsm_store_info',
            'Thông tin cửa hàng',
            array($this, 'render_store_metabox'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_store_metabox($post) {
        wp_nonce_field('lsm_save_store_meta', 'lsm_store_nonce');

        $settings = $this->get_settings();

        $fields = array(
            'localstore_address' => get_post_meta($post->ID, 'localstore_address', true),
            'localstore_phone' => get_post_meta($post->ID, 'localstore_phone', true),
            'localstore_hotline' => get_post_meta($post->ID, 'localstore_hotline', true),
            'localstore_email' => get_post_meta($post->ID, 'localstore_email', true),
            'localstore_open_hours' => get_post_meta($post->ID, 'localstore_open_hours', true),
            'localstore_link_to' => get_post_meta($post->ID, 'localstore_link_to', true),
            'localstore_maps' => get_post_meta($post->ID, 'localstore_maps', true),
            'localstore_maps_lng' => get_post_meta($post->ID, 'localstore_maps_lng', true),
        );

        $lat = $fields['localstore_maps'] !== '' ? $fields['localstore_maps'] : $settings['default_lat'];
        $lng = $fields['localstore_maps_lng'] !== '' ? $fields['localstore_maps_lng'] : $settings['default_lng'];
        ?>
        <div class="lsm-admin-grid"
             data-default-lat="<?php echo esc_attr($settings['default_lat']); ?>"
             data-default-lng="<?php echo esc_attr($settings['default_lng']); ?>"
             data-default-zoom="<?php echo esc_attr($settings['zoom']); ?>">

            <div class="lsm-admin-fields">
                <?php $this->text_field('Địa chỉ', 'localstore_address', $fields['localstore_address']); ?>
                <?php $this->text_field('Số điện thoại', 'localstore_phone', $fields['localstore_phone']); ?>
                <?php $this->text_field('Hotline', 'localstore_hotline', $fields['localstore_hotline']); ?>
                <?php $this->text_field('Email', 'localstore_email', $fields['localstore_email']); ?>
                <?php $this->text_field('Giờ mở cửa', 'localstore_open_hours', $fields['localstore_open_hours']); ?>
                <?php $this->text_field('Đường dẫn của nút Xem thêm', 'localstore_link_to', $fields['localstore_link_to']); ?>
            </div>

            <div class="lsm-admin-map-wrap">
                <div class="lsm-coordinate-row">
                    <label>
                        Latitude
                        <input type="text" id="localstore_maps" name="localstore_maps" value="<?php echo esc_attr($lat); ?>">
                    </label>
                    <label>
                        Longitude
                        <input type="text" id="localstore_maps_lng" name="localstore_maps_lng" value="<?php echo esc_attr($lng); ?>">
                    </label>
                </div>

                <div id="lsm-admin-map" class="lsm-admin-map"></div>
                <p class="description">Kéo icon hoặc click vào bản đồ để chọn địa chỉ chính xác.</p>
            </div>
        </div>
        <?php
    }

    private function text_field($label, $name, $value) {
        ?>
        <div class="lsm-field-row">
            <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label>
            <input type="text" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
        </div>
        <?php
    }

    public function save_store_meta($post_id) {
        if (!isset($_POST['lsm_store_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lsm_store_nonce'])), 'lsm_save_store_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $text_fields = array(
            'localstore_address',
            'localstore_phone',
            'localstore_hotline',
            'localstore_email',
            'localstore_open_hours',
            'localstore_maps',
            'localstore_maps_lng',
        );

        foreach ($text_fields as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
            update_post_meta($post_id, $field, $value);
        }

        $url_fields = array(
            'localstore_link_to',
            'localstore_marker_icon',
        );

        foreach ($url_fields as $field) {
            $value = isset($_POST[$field]) ? esc_url_raw(wp_unslash($_POST[$field])) : '';
            update_post_meta($post_id, $field, $value);
        }
    }

    public function admin_menu() {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            'Cài đặt cửa hàng',
            'Cài đặt',
            'manage_options',
            'lsm-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('lsm_settings_group', self::OPTION_KEY, array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        $defaults = $this->defaults();

        return array(
            'default_lat' => sanitize_text_field($input['default_lat'] ?? $defaults['default_lat']),
            'default_lng' => sanitize_text_field($input['default_lng'] ?? $defaults['default_lng']),
            'zoom' => absint($input['zoom'] ?? $defaults['zoom']),
            'marker_icon' => esc_url_raw($input['marker_icon'] ?? ''),
            'google_maps_api' => sanitize_text_field($input['google_maps_api'] ?? ''),
            'use_fontawesome' => !empty($input['use_fontawesome']) ? '1' : '0',
            'enable_direction' => !empty($input['enable_direction']) ? '1' : '0',
            'primary_color' => sanitize_hex_color($input['primary_color'] ?? $defaults['primary_color']) ?: $defaults['primary_color'],
        );
    }

    public function maybe_seed_regions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (get_option(self::REGION_SEED_OPTION) === self::VERSION) {
            return;
        }

        $region_count = wp_count_terms(self::TAXONOMY, array(
            'hide_empty' => false,
        ));

        if (is_wp_error($region_count) || (int) $region_count >= 100) {
            update_option(self::REGION_SEED_OPTION, self::VERSION, false);
            return;
        }

        $this->seed_regions();
    }

    public function seed_regions() {
        if (!taxonomy_exists(self::TAXONOMY)) {
            $this->register_taxonomy();
        }

        $response = wp_remote_get('https://provinces.open-api.vn/api/v1/?depth=2', array(
            'timeout' => 20,
            'redirection' => 3,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $regions = json_decode($body, true);

        if (!is_array($regions)) {
            return false;
        }

        foreach ($regions as $province) {
            if (empty($province['name'])) {
                continue;
            }

            $parent_id = $this->ensure_region_term($this->clean_region_name($province['name']), 0);

            if (!$parent_id || empty($province['districts']) || !is_array($province['districts'])) {
                continue;
            }

            foreach ($province['districts'] as $district) {
                if (empty($district['name'])) {
                    continue;
                }

                $this->ensure_region_term($this->clean_region_name($district['name']), $parent_id);
            }
        }

        update_option(self::REGION_SEED_OPTION, self::VERSION, false);
        return true;
    }

    private function ensure_region_term($name, $parent_id = 0) {
        $slug = sanitize_title($name);
        $exists = term_exists($slug, self::TAXONOMY, $parent_id);

        if (is_array($exists) && !empty($exists['term_id'])) {
            return (int) $exists['term_id'];
        }

        $exists = term_exists($name, self::TAXONOMY, $parent_id);

        if (is_array($exists) && !empty($exists['term_id'])) {
            return (int) $exists['term_id'];
        }

        $created = wp_insert_term($name, self::TAXONOMY, array(
            'parent' => (int) $parent_id,
            'slug' => $slug,
        ));

        if (is_wp_error($created)) {
            return 0;
        }

        return (int) $created['term_id'];
    }

    private function clean_region_name($name) {
        $name = trim(wp_strip_all_tags($name));
        $prefixes = array(
            'Thành phố ',
            'Tỉnh ',
            'Quận ',
            'Huyện ',
            'Thị xã ',
        );

        foreach ($prefixes as $prefix) {
            if (strpos($name, $prefix) === 0) {
                return trim(substr($name, strlen($prefix)));
            }
        }

        return $name;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        ?>
        <div class="wrap lsm-settings">
            <h1>Cài đặt cửa hàng</h1>
            <p>Sử dụng shortcode <code>[local_store]</code> để hiển thị bản đồ cửa hàng.</p>

            <h2 class="nav-tab-wrapper">
                <a href="#lsm-general" class="nav-tab nav-tab-active">Cài đặt chung</a>
                <a href="#lsm-license" class="nav-tab">License</a>
                <a href="#lsm-update" class="nav-tab">Check update</a>
            </h2>

            <form method="post" action="options.php" id="lsm-general" class="lsm-tab-panel is-active">
                <?php settings_fields('lsm_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Tọa độ mặc định</th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_lat]" value="<?php echo esc_attr($settings['default_lat']); ?>">
                            <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_lng]" value="<?php echo esc_attr($settings['default_lng']); ?>">
                            <p>
                                <a href="https://www.openstreetmap.org/" target="_blank" rel="noopener">Công cụ lấy tọa độ</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Zoom</th>
                        <td>
                            <input type="number" min="1" max="20" name="<?php echo esc_attr(self::OPTION_KEY); ?>[zoom]" value="<?php echo esc_attr($settings['zoom']); ?>">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Icon marker</th>
                        <td>
                            <input type="hidden" id="lsm_marker_icon" name="<?php echo esc_attr(self::OPTION_KEY); ?>[marker_icon]" value="<?php echo esc_attr($settings['marker_icon']); ?>">
                            <button type="button" class="button lsm-media-button" data-target="#lsm_marker_icon">Chọn icon</button>
                            <button type="button" class="button lsm-media-clear" data-target="#lsm_marker_icon">Xóa</button>
                            <div class="lsm-image-preview">
                                <?php
                                if ($settings['marker_icon']) {
                                    echo '<img src="' . esc_url($settings['marker_icon']) . '" alt="">';
                                } else {
                                    echo '<span class="lsm-default-pin"></span>';
                                }
                                ?>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Google Maps API</th>
                        <td>
                            <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[google_maps_api]" value="<?php echo esc_attr($settings['google_maps_api']); ?>">
                            <p class="description">Không bắt buộc. Bản đồ đang dùng Leaflet/OpenStreetMap.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Sử dụng Font Awesome</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[use_fontawesome]" value="1" <?php checked($settings['use_fontawesome'], '1'); ?>>
                                Có sử dụng
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Bật chỉ đường</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_direction]" value="1" <?php checked($settings['enable_direction'], '1'); ?>>
                                Có
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Màu chủ đạo</th>
                        <td>
                            <input type="text" class="lsm-color-field" name="<?php echo esc_attr(self::OPTION_KEY); ?>[primary_color]" value="<?php echo esc_attr($settings['primary_color']); ?>">
                            <p class="description">Màu này áp dụng cho giao diện chính. Nút Xem thêm và Chỉ đường luôn dùng màu xanh mặc định.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Lưu thay đổi'); ?>
            </form>

            <div id="lsm-license" class="lsm-tab-panel">
                <p>License dang de trong.</p>
            </div>

            <div id="lsm-update" class="lsm-tab-panel">
                <p>Ban dang dung phien ban <?php echo esc_html(self::VERSION); ?>.</p>
            </div>
        </div>
        <?php
    }

    public function admin_assets($hook) {
        $screen = get_current_screen();
        $screen_post_type = $screen && isset($screen->post_type) ? $screen->post_type : '';
        $screen_taxonomy = $screen && isset($screen->taxonomy) ? $screen->taxonomy : '';

        $is_store_admin = $screen && ($screen_post_type === self::POST_TYPE || $screen_taxonomy === self::TAXONOMY);
        $is_settings = $hook === self::POST_TYPE . '_page_lsm-settings';

        if (!$is_store_admin && !$is_settings) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');

        wp_enqueue_style('lsm-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
        wp_enqueue_script('lsm-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

        wp_enqueue_style('lsm-admin', self::plugin_url('assets/css/admin.css'), array('lsm-leaflet'), self::VERSION);
        wp_enqueue_script('lsm-admin', self::plugin_url('assets/js/admin.js'), array('jquery', 'wp-color-picker', 'lsm-leaflet'), self::VERSION, true);
    }

    public function frontend_assets() {
        $settings = $this->get_settings();
        $style_deps = array('lsm-leaflet');

        wp_register_style('lsm-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
        wp_register_script('lsm-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

        if ($settings['use_fontawesome'] === '1') {
            wp_register_style(
                'lsm-fontawesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
                array(),
                '6.5.2'
            );
            $style_deps[] = 'lsm-fontawesome';
        }

        wp_register_style('lsm-frontend', self::plugin_url('assets/css/frontend.css'), $style_deps, self::VERSION);
        wp_register_script('lsm-frontend', self::plugin_url('assets/js/frontend.js'), array('jquery', 'lsm-leaflet'), self::VERSION, true);
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array(
            'height' => '650',
            'posts_per_page' => '-1',
        ), $atts, 'local_store');

        wp_enqueue_style('lsm-frontend');
        wp_enqueue_script('lsm-frontend');

        $settings = $this->get_settings();
        $stores = $this->get_store_data((int) $atts['posts_per_page']);
        $regions = $this->get_region_tree();
        $id = 'lsm-map-' . wp_generate_uuid4();
        $config_name = 'LSM_DATA_' . str_replace('-', '_', $id);
        $height = max(420, absint($atts['height']));

        wp_localize_script('lsm-frontend', $config_name, array(
            'stores' => $stores,
            'regions' => $regions,
            'settings' => array(
                'defaultLat' => (float) $settings['default_lat'],
                'defaultLng' => (float) $settings['default_lng'],
                'zoom' => (int) $settings['zoom'],
                'markerIcon' => esc_url($settings['marker_icon']),
                'primaryColor' => $settings['primary_color'],
                'enableDirection' => $settings['enable_direction'] === '1',
                'tileUrl' => 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                'tileAttribution' => '&copy; OpenStreetMap &copy; CARTO',
            ),
        ));

        ob_start();
        ?>
        <div class="lsm-store-locator"
             style="--lsm-primary: <?php echo esc_attr($settings['primary_color']); ?>; --lsm-height: <?php echo esc_attr($height); ?>px;"
             data-config="<?php echo esc_attr($config_name); ?>">

            <aside class="lsm-panel">
                <div class="lsm-search">
                    <div class="lsm-search-inner">
                        <input type="search" class="lsm-keyword" placeholder="Nhập từ khoá tìm kiếm" aria-label="Nhập từ khóa">
                        <button type="button" class="lsm-search-button" aria-label="Tìm kiếm">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="lsm-filters">
                    <select class="lsm-province" aria-label="Tỉnh thành">
                        <option value="">Toàn Quốc</option>
                        <?php foreach ($regions as $region) : ?>
                            <option value="<?php echo esc_attr($region['id']); ?>">
                                <?php echo esc_html($region['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="lsm-district" aria-label="Quận huyện">
                        <option value="">Quận/Huyện</option>
                    </select>
                </div>
                <div class="lsm-result-count" aria-live="polite">
                    Tìm thấy <strong class="lsm-result-number">0</strong> cửa hàng
                </div>
                <div class="lsm-list" role="list"></div>
            </aside>

            <section class="lsm-map-area">
                <button type="button" class="lsm-toggle-panel" aria-label="Thu gọn danh sách">‹</button>
                <div id="<?php echo esc_attr($id); ?>" class="lsm-map"></div>
            </section>
        </div>
        <?php

        return ob_get_clean();
    }

    private function get_store_data($posts_per_page) {
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'orderby' => array(
                'menu_order' => 'ASC',
                'title' => 'ASC',
            ),
        ));

        $stores = array();

        foreach ($query->posts as $post) {
            $lat = get_post_meta($post->ID, 'localstore_maps', true);
            $lng = get_post_meta($post->ID, 'localstore_maps_lng', true);
            $has_coordinates = is_numeric($lat) && is_numeric($lng);

            $terms = wp_get_post_terms($post->ID, self::TAXONOMY);
            $term_ids = array();
            $parent_ids = array();

            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $term_ids[] = (int) $term->term_id;

                    if ($term->parent) {
                        $parent_ids[] = (int) $term->parent;
                    } else {
                        $parent_ids[] = (int) $term->term_id;
                    }
                }
            }

            $image = get_the_post_thumbnail_url($post, 'medium');

            $stores[] = array(
                'id' => (int) $post->ID,
                'title' => html_entity_decode(get_the_title($post), ENT_QUOTES, get_bloginfo('charset')),
                'content' => wp_strip_all_tags(get_the_excerpt($post)),
                'address' => get_post_meta($post->ID, 'localstore_address', true),
                'phone' => get_post_meta($post->ID, 'localstore_phone', true),
                'hotline' => get_post_meta($post->ID, 'localstore_hotline', true),
                'email' => get_post_meta($post->ID, 'localstore_email', true),
                'openHours' => get_post_meta($post->ID, 'localstore_open_hours', true),
                'link' => get_post_meta($post->ID, 'localstore_link_to', true),
                'lat' => $has_coordinates ? (float) $lat : null,
                'lng' => $has_coordinates ? (float) $lng : null,
                'hasCoordinates' => $has_coordinates,
                'image' => $image ?: '',
                'markerIcon' => get_post_meta($post->ID, 'localstore_marker_icon', true),
                'terms' => array_values(array_unique($term_ids)),
                'parents' => array_values(array_unique($parent_ids)),
            );
        }

        wp_reset_postdata();

        return $stores;
    }

    private function get_region_tree() {
        $parents = get_terms(array(
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'parent' => 0,
            'orderby' => 'name',
            'order' => 'ASC',
        ));

        if (is_wp_error($parents)) {
            return array();
        }

        $tree = array();

        foreach ($parents as $parent) {
            $children = get_terms(array(
                'taxonomy' => self::TAXONOMY,
                'hide_empty' => false,
                'parent' => $parent->term_id,
                'orderby' => 'name',
                'order' => 'ASC',
            ));

            $tree[] = array(
                'id' => (int) $parent->term_id,
                'name' => $parent->name,
                'children' => is_wp_error($children) ? array() : array_map(function ($child) {
                    return array(
                        'id' => (int) $child->term_id,
                        'name' => $child->name,
                    );
                }, $children),
            );
        }

        return $tree;
    }

    public function store_columns($columns) {
        $columns['localstore_address'] = 'Địa chỉ';
        $columns['localstore_phone'] = 'Số điện thoại';
        return $columns;
    }

    public function store_column_content($column, $post_id) {
        if ($column === 'localstore_address') {
            echo esc_html(get_post_meta($post_id, 'localstore_address', true));
        }

        if ($column === 'localstore_phone') {
            echo esc_html(get_post_meta($post_id, 'localstore_phone', true));
        }
    }
}

register_activation_hook(__FILE__, array('LSM_Local_Store_Maps', 'activate'));
register_deactivation_hook(__FILE__, array('LSM_Local_Store_Maps', 'deactivate'));

LSM_Local_Store_Maps::instance();

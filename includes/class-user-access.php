<?php
class MyAdMaterials_UserAccess {
    public function __construct() {
        // Добавляем роли
        add_action('init', array($this, 'add_user_roles'));

        // Шорткод для вывода статистики
        add_shortcode('advertiser_stats', array($this, 'stats_shortcode'));

        // REST API endpoint для статистики пользователя
        add_action('rest_api_init', array($this, 'register_user_stats_endpoint'));

        // Добавляем колонку в список пользователей
        add_filter('manage_users_columns', array($this, 'add_user_column'));
        add_action('manage_users_custom_column', array($this, 'fill_user_column'), 10, 3);
    }

    public function add_user_roles() {
        add_role('advertiser', 'Рекламодатель', array(
            'read' => true,
            'edit_posts' => false,
            'upload_files' => true
        ));

        add_role('ad_publisher', 'Вебмастер', array(
            'read' => true,
            'edit_posts' => false
        ));
    }

    public function stats_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="ad-stats-notice"><p>Войдите для просмотра статистики</p></div>';
        }

        $user_id = get_current_user_id();
        $stats = $this->get_user_stats($user_id);
        
        // Если пользователь администратор - показываем всю статистику
        if (current_user_can('manage_options')) {
            $stats = $this->get_all_stats();
        }
        
        // Добавляем названия материалов
        foreach ($stats as &$stat) {
            if ($stat->ad_id > 0) {
                $post = get_post($stat->ad_id);
                $stat->ad_title = $post ? $post->post_title : 'Удаленный материал';
            } else {
                $stat->ad_title = 'Тестовая реклама';
            }
            
            // Рассчитываем CTR
            $stat->ctr = ($stat->impressions > 0) 
                ? round(($stat->clicks / $stat->impressions) * 100, 2) 
                : 0;
        }

        // Подключаем шаблон
        ob_start();
        include MY_AD_MATERIALS_EXTENDED_PATH . 'templates/stats-shortcode.php';
        return ob_get_clean();
    }

    private function get_all_stats() {
        global $wpdb;
        
        $days = 30; // Статистика за 30 дней
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                a.ad_id,
                p.post_title as ad_title,
                COUNT(CASE WHEN a.action = 'impression' THEN 1 END) as impressions,
                COUNT(CASE WHEN a.action = 'click' THEN 1 END) as clicks,
                ROUND(COUNT(CASE WHEN a.action = 'click' THEN 1 END) * 100.0 / 
                      NULLIF(COUNT(CASE WHEN a.action = 'impression' THEN 1 END), 0), 2) as ctr
            FROM {$wpdb->prefix}ad_analytics a
            LEFT JOIN {$wpdb->posts} p ON a.ad_id = p.ID
            WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY a.ad_id
            ORDER BY impressions DESC
        ", $days));
    }

    public function register_user_stats_endpoint() {
        register_rest_route('custom/v1', '/user/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_stats_api'),
            'permission_callback' => array($this, 'check_user_access')
        ));
    }

    public function get_user_stats_api($request) {
        $user_id = get_current_user_id();
        return rest_ensure_response($this->get_user_stats($user_id));
    }

    private function get_user_stats($user_id) {
        global $wpdb;
        
        $ad_ids = $this->get_user_ad_ids($user_id);
        if (empty($ad_ids)) return array();

        $placeholders = implode(',', array_fill(0, count($ad_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT ad_id, 
                    COUNT(CASE WHEN action = 'impression' THEN 1 END) as impressions,
                    COUNT(CASE WHEN action = 'click' THEN 1 END) as clicks
             FROM {$wpdb->prefix}ad_analytics
             WHERE ad_id IN ($placeholders)
             GROUP BY ad_id",
            $ad_ids
        );

        return $wpdb->get_results($query);
    }

    private function get_user_ad_ids($user_id) {
        $args = array(
            'post_type' => 'ad_material',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_advertiser_id',
                    'value' => $user_id
                )
            )
        );

        return get_posts($args);
    }

    public function add_user_column($columns) {
        $columns['ad_stats'] = 'Рекм. материалы';
        return $columns;
    }

    public function fill_user_column($output, $column_name, $user_id) {
        if ($column_name === 'ad_stats') {
            $count = count($this->get_user_ad_ids($user_id));
            return $count ?: '0';
        }
        return $output;
    }

    public function check_user_access() {
        return is_user_logged_in() && (current_user_can('advertiser') || current_user_can('ad_publisher'));
    }
}
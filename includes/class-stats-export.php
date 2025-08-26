<?php
class MyAdMaterials_StatsExport {
    public function __construct() {
        // Добавляем пункт экспорта в админке
        add_action('admin_menu', array($this, 'add_export_page'));

        // AJAX обработчики для экспорта
        add_action('wp_ajax_ad_export_stats', array($this, 'handle_export_request'));
        add_action('wp_ajax_nopriv_ad_export_stats', array($this, 'deny_export_request'));

        // REST API для экспорта
        add_action('rest_api_init', array($this, 'register_export_endpoints'));

        // Крон-задача для автоматического экспорта
        add_action('init', array($this, 'schedule_exports'));
        add_action('ad_materials_daily_export', array($this, 'generate_daily_export'));
    }

    public function add_export_page() {
        add_submenu_page(
            'edit.php?post_type=ad_material',
            'Экспорт статистики',
            '📤 Экспорт',
            'manage_options',
            'ad-stats-export',
            array($this, 'render_export_page')
        );
    }

    public function render_export_page() {
        // Получаем список рекламных материалов
        $ads = get_posts(array(
            'post_type' => 'ad_material',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        // Подключаем шаблон
        include MY_AD_MATERIALS_EXTENDED_PATH . 'templates/export-page.php';
    }

    /**
     * Обработка AJAX запроса на экспорт
     */
    public function handle_export_request() {
        // Проверка nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ad_export_stats')) {
            wp_die('Ошибка безопасности');
        }

        // Проверка прав
        if (!current_user_can('manage_options') && !current_user_can('advertiser')) {
            wp_die('Недостаточно прав для экспорта');
        }

        // Параметры экспорта
        $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
        $ad_id = isset($_GET['ad_id']) ? intval($_GET['ad_id']) : 0;

        // Создаем фейковый запрос для совместимости с нашими функциями
        $request = new WP_REST_Request('GET');
        $request->set_param('days', $days);
        $request->set_param('ad_id', $ad_id);

        // Вызываем соответствующую функцию экспорта
        if ($format === 'xml') {
            $this->export_xml($request);
        } else {
            $this->export_csv($request);
        }
        
        exit;
    }

    /**
     * Запрет экспорта для неавторизованных пользователей
     */
    public function deny_export_request() {
        wp_die('Для экспорта необходимо авторизоваться.');
    }

    public function register_export_endpoints() {
        register_rest_route('custom/v1', '/stats/export/xml', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_xml'),
            'permission_callback' => array($this, 'check_export_permissions')
        ));

        register_rest_route('custom/v1', '/stats/export/csv', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_csv'),
            'permission_callback' => array($this, 'check_export_permissions')
        ));
    }

    public function export_xml($request) {
        $stats = $this->get_export_data($request);
        
        $xml = new SimpleXMLElement('<stats></stats>');
        foreach ($stats as $item) {
            $stat = $xml->addChild('stat');
            $stat->addChild('date', $item->date);
            $stat->addChild('impressions', $item->impressions);
            $stat->addChild('clicks', $item->clicks);
            $stat->addChild('ctr', $item->ctr);
        }

        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="ad-stats-export.xml"');
        echo $xml->asXML();
        exit;
    }

    public function export_csv($request) {
        $stats = $this->get_export_data($request);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ad-stats-export.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Дата', 'Показы', 'Клики', 'CTR'));
        
        foreach ($stats as $stat) {
            fputcsv($output, array(
                $stat->date,
                $stat->impressions,
                $stat->clicks,
                $stat->ctr
            ));
        }
        
        fclose($output);
        exit;
    }

    private function get_export_data($request) {
        global $wpdb;
        
        $days = $request->get_param('days') ?: 30;
        $ad_id = $request->get_param('ad_id');
        
        $query = "SELECT 
                    DATE(created_at) as date,
                    COUNT(CASE WHEN action = 'impression' THEN 1 END) as impressions,
                    COUNT(CASE WHEN action = 'click' THEN 1 END) as clicks,
                    ROUND(COUNT(CASE WHEN action = 'click' THEN 1 END) * 100.0 / 
                          NULLIF(COUNT(CASE WHEN action = 'impression' THEN 1 END), 0), 2) as ctr
                  FROM {$wpdb->prefix}ad_analytics
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)";
        
        $params = array($days);
        
        if ($ad_id) {
            $query .= " AND ad_id = %d";
            $params[] = $ad_id;
        }
        
        $query .= " GROUP BY DATE(created_at) ORDER BY date DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    public function schedule_exports() {
        if (!wp_next_scheduled('ad_materials_daily_export')) {
            wp_schedule_event(time(), 'daily', 'ad_materials_daily_export');
        }
    }

    public function generate_daily_export() {
        $stats = $this->get_export_data(new WP_REST_Request());
        
        // Сохраняем в uploads
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/ad-stats-daily-export-' . date('Y-m-d') . '.csv';
        
        $file = fopen($file_path, 'w');
        fputcsv($file, array('Дата', 'Показы', 'Клики', 'CTR'));
        
        foreach ($stats as $stat) {
            fputcsv($file, array(
                $stat->date,
                $stat->impressions,
                $stat->clicks,
                $stat->ctr
            ));
        }
        
        fclose($file);
    }

    public function check_export_permissions() {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('advertiser'));
    }
}
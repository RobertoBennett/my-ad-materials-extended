<?php
class MyAdMaterials_Reports {
    public function __construct() {
        // Настройки в админке
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));

        // Крон-задачи
        add_action('init', array($this, 'schedule_reports'));
        add_action('ad_materials_send_weekly_reports', array($this, 'send_weekly_reports'));
        
        // Добавляем обработчик для отправки тестового письма
        add_action('admin_post_send_test_report', array($this, 'send_test_report'));
    }

    public function register_settings() {
        register_setting('ad_materials_reports', 'ad_report_recipients');
        register_setting('ad_materials_reports', 'ad_report_frequency');
        register_setting('ad_materials_reports', 'ad_report_template');
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=ad_material',
            'Настройки отчетов',
            '📧 Отчеты',
            'manage_options',
            'ad-reports-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        // Добавим кнопку для отправки тестового письма
        include MY_AD_MATERIALS_EXTENDED_PATH . 'templates/reports-settings.php';
        
        echo '<form method="post" action="' . admin_url('admin-post.php') . '" style="margin-top:30px;">';
        echo '<input type="hidden" name="action" value="send_test_report">';
        wp_nonce_field('send_test_report', '_test_nonce');
        submit_button('Отправить тестовое письмо', 'secondary', 'send_test');
        echo '</form>';
    }

    public function send_weekly_reports() {
        $users = $this->get_subscribed_users();
        if (empty($users)) {
            error_log('No subscribed users for reports');
            return;
        }

        $stats = $this->get_weekly_stats();
        $template = get_option('ad_report_template', $this->get_default_template());
        $date_range = date('d.m.Y', strtotime('-7 days')) . ' - ' . date('d.m.Y');

        foreach ($users as $user) {
            $email = $user->user_email;
            $subject = 'Еженедельный отчет по рекламным материалам';
            $message = $this->compile_template($template, $stats, $date_range);

            $sent = wp_mail($email, $subject, $message, array(
                'Content-Type: text/html; charset=UTF-8'
            ));
            
            if (!$sent) {
                error_log("Failed to send report to: $email");
            } else {
                error_log("Report sent to: $email");
            }
        }
    }

    private function get_subscribed_users() {
        // Получаем всех пользователей с подпиской
        return get_users(array(
            'meta_key' => 'ad_report_subscription',
            'meta_value' => '1',
            'fields' => 'all'
        ));
    }

    public function send_test_report() {
        if (!isset($_POST['_test_nonce']) || !wp_verify_nonce($_POST['_test_nonce'], 'send_test_report')) {
            wp_die('Ошибка безопасности');
        }

        $current_user = wp_get_current_user();
        $email = $current_user->user_email;
        
        $stats = $this->get_weekly_stats();
        $template = get_option('ad_report_template', $this->get_default_template());
        $date_range = 'Тестовый период: ' . date('d.m.Y');
        
        $subject = 'Тестовый отчет по рекламным материалам';
        $message = $this->compile_template($template, $stats, $date_range);

        $sent = wp_mail($email, $subject, $message, array(
            'Content-Type: text/html; charset=UTF-8'
        ));
        
        if ($sent) {
            add_settings_error('ad_reports', 'test_sent', 'Тестовое письмо отправлено!', 'success');
        } else {
            add_settings_error('ad_reports', 'test_failed', 'Ошибка отправки тестового письма', 'error');
        }
        
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('edit.php?post_type=ad_material&page=ad-reports-settings'));
        exit;
    }

    private function get_weekly_stats() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT 
                a.ad_id,
                p.post_title as ad_title,
                COUNT(CASE WHEN a.action = 'impression' THEN 1 END) as impressions,
                COUNT(CASE WHEN a.action = 'click' THEN 1 END) as clicks,
                ROUND(COUNT(CASE WHEN a.action = 'click' THEN 1 END) * 100.0 / 
                      NULLIF(COUNT(CASE WHEN a.action = 'impression' THEN 1 END), 0), 2) as ctr
            FROM {$wpdb->prefix}ad_analytics a
            LEFT JOIN {$wpdb->posts} p ON a.ad_id = p.ID
            WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY a.ad_id
            ORDER BY impressions DESC
            LIMIT 20
        ");
    }

    private function compile_template($template, $stats, $date_range) {
        // Подключаем шаблон письма
        ob_start();
        include MY_AD_MATERIALS_EXTENDED_PATH . 'templates/email-template.php';
        return ob_get_clean();
    }

    private function get_default_template() {
        return '<h1>Еженедельный отчет</h1>
                <p>Статистика за последние 7 дней:</p>
                <table>
                    <tr>
                        <th>Материал</th>
                        <th>Показы</th>
                        <th>Клики</th>
                        <th>CTR</th>
                    </tr>
                    {{#stats}}
                    <tr>
                        <td>{{ad_title}}</td>
                        <td>{{impressions}}</td>
                        <td>{{clicks}}</td>
                        <td>{{ctr}}%</td>
                    </tr>
                    {{/stats}}
                </table>';
    }

    public function schedule_reports() {
        $frequency = get_option('ad_report_frequency', 'weekly');
        
        if (!wp_next_scheduled('ad_materials_send_weekly_reports')) {
            wp_schedule_event(time(), $frequency, 'ad_materials_send_weekly_reports');
        }
    }
}
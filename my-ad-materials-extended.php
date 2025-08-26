<?php
/*
 * Plugin Name: My Ad Materials Extended
 * Description: Расширение для плагина My Ad Materials с мультипользовательским доступом, экспортом статистики и рассылкой отчетов
 * Plugin URI: https://yoursite.com/
 * Version: 1.5
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: Robert Bennett
 * Text Domain: My Ad Materials Extended
 */

defined('ABSPATH') || exit;

// Определяем константу пути
if (!defined('MY_AD_MATERIALS_EXTENDED_PATH')) {
    define('MY_AD_MATERIALS_EXTENDED_PATH', plugin_dir_path(__FILE__));
}

class MyAdMaterialsExtended {
    // Константа с именем главного файла родительского плагина
    const PARENT_PLUGIN_FILE = 'my-ad-materials.php';

    public function __construct() {
        // Проверяем наличие родительского плагина
        add_action('plugins_loaded', array($this, 'init'), 20); // Более высокий приоритет
        
        // Обработчик подписок
        add_action('admin_post_toggle_report_subscription', array($this, 'handle_subscription_toggle'));
    }

    public function init() {
        // Попытка загрузить функции родительского плагина
        $this->load_parent_functions();
        
        // Проверяем загружены ли функции родительского плагина
        if (!function_exists('create_analytics_table') || !post_type_exists('ad_material')) {
            add_action('admin_notices', array($this, 'show_parent_plugin_error'));
            return;
        }

        // Инициализируем компоненты
        $this->init_components();
    }

    private function load_parent_functions() {
        // Если функции не загружены, попробуем загрузить их вручную
        if (!function_exists('create_analytics_table')) {
            // Путь к родительскому плагину
            $parent_plugin = WP_PLUGIN_DIR . '/my-ad-materials/' . self::PARENT_PLUGIN_FILE;
            
            if (file_exists($parent_plugin)) {
                require_once $parent_plugin;
            }
        }
    }

    public function show_parent_plugin_error() {
        $message = sprintf(
            'Плагин <strong>%s</strong> активирован, но не загрузил необходимые функции. Попробуйте переактивировать его.',
            'My Ad Materials'
        );
        
        echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
    }

    public function init_components() {
        // 1. Мультипользовательский доступ
        require_once MY_AD_MATERIALS_EXTENDED_PATH . 'includes/class-user-access.php';
        new MyAdMaterials_UserAccess();

        // 2. Экспорт статистики
        require_once MY_AD_MATERIALS_EXTENDED_PATH . 'includes/class-stats-export.php';
        new MyAdMaterials_StatsExport();

        // 3. Рассылка отчетов
        require_once MY_AD_MATERIALS_EXTENDED_PATH . 'includes/class-reports.php';
        new MyAdMaterials_Reports();

        // Добавляем ссылку на настройки в списке плагинов
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_links'));
    }

    public function add_plugin_links($links) {
        $settings_link = '<a href="' . admin_url('edit.php?post_type=ad_material&page=ad-reports-settings') . '">' . 
                         'Настройки отчетов' . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function handle_subscription_toggle() {
        if (!isset($_POST['_subscription_nonce']) || 
            !wp_verify_nonce($_POST['_subscription_nonce'], 'toggle_subscription_' . $_POST['user_id'])) {
            wp_die('Ошибка безопасности');
        }

        $user_id = intval($_POST['user_id']);
        $status = intval($_POST['status']);
        
        update_user_meta($user_id, 'ad_report_subscription', $status);
        
        $redirect_url = admin_url('edit.php?post_type=ad_material&page=ad-reports-settings');
        wp_redirect($redirect_url);
        exit;
    }
}

// Запуск плагина
new MyAdMaterialsExtended();
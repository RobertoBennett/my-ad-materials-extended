<?php
/**
 * Шаблон страницы настроек отчетов
 */
?>
<div class="wrap">
    <h1>Настройки отчетов</h1>
    
    <!-- Вывод сообщений об ошибках/успехе -->
    <?php settings_errors('ad_reports'); ?>
    
    <form method="post" action="options.php">
        <?php settings_fields('ad_materials_reports'); ?>
        <?php do_settings_sections('ad_materials_reports'); ?>
        
        <table class="form-table">
            <tr>
                <th><label for="report_recipients">Получатели:</label></th>
                <td>
                    <input type="text" 
                           id="report_recipients" 
                           name="ad_report_recipients" 
                           value="<?php echo esc_attr(get_option('ad_report_recipients')); ?>" 
                           class="regular-text"
                           placeholder="email1@example.com, email2@example.com">
                    <p class="description">Укажите email-адреса через запятую</p>
                </td>
            </tr>
            <tr>
                <th><label for="report_frequency">Частота отправки:</label></th>
                <td>
                    <select id="report_frequency" name="ad_report_frequency">
                        <option value="daily" <?php selected(get_option('ad_report_frequency'), 'daily'); ?>>Ежедневно</option>
                        <option value="weekly" <?php selected(get_option('ad_report_frequency'), 'weekly'); ?>>Еженедельно</option>
                        <option value="monthly" <?php selected(get_option('ad_report_frequency'), 'monthly'); ?>>Ежемесячно</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="report_template">Шаблон письма:</label></th>
                <td>
                    <textarea id="report_template" 
                              name="ad_report_template" 
                              rows="10" 
                              class="large-text"><?php echo esc_textarea(get_option('ad_report_template')); ?></textarea>
                    <p class="description">Используйте переменные: {date}, {stats_table}</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Сохранить настройки'); ?>
    </form>
    
    <h2>Управление подписками</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Пользователь</th>
                <th>Email</th>
                <th>Роль</th>
                <th>Статус подписки</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $users = get_users(array(
                'role__in' => array('administrator', 'advertiser', 'ad_publisher')
            ));
            
            foreach ($users as $user) :
                $subscribed = get_user_meta($user->ID, 'ad_report_subscription', true);
            ?>
            <tr>
                <td><?php echo esc_html($user->display_name); ?></td>
                <td><?php echo esc_html($user->user_email); ?></td>
                <td><?php echo implode(', ', $user->roles); ?></td>
                <td><?php echo $subscribed ? 'Подписан' : 'Не подписан'; ?></td>
                <td>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_report_subscription">
                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                        <input type="hidden" name="status" value="<?php echo $subscribed ? 0 : 1; ?>">
                        <?php wp_nonce_field('toggle_subscription_' . $user->ID, '_subscription_nonce'); ?>
                        <button type="submit" class="button">
                            <?php echo $subscribed ? 'Отписать' : 'Подписать'; ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
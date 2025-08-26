<?php
/**
 * Шаблон страницы экспорта статистики
 */
?>
<div class="wrap">
    <h1>Экспорт статистики рекламы</h1>
    
    <form method="get" action="<?php echo admin_url('admin-ajax.php'); ?>">
        <input type="hidden" name="action" value="ad_export_stats">
        
        <table class="form-table">
            <tr>
                <th><label for="export_format">Формат экспорта:</label></th>
                <td>
                    <select name="format" id="export_format" required>
                        <option value="csv">CSV</option>
                        <option value="xml">XML</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="period">Период:</label></th>
                <td>
                    <select name="days" id="period">
                        <option value="7">7 дней</option>
                        <option value="30" selected>30 дней</option>
                        <option value="90">90 дней</option>
                        <option value="365">1 год</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ad_id">Материал:</label></th>
                <td>
                    <select name="ad_id" id="ad_id">
                        <option value="">Все материалы</option>
                        <?php foreach ($ads as $ad) : ?>
                            <option value="<?php echo $ad->ID; ?>">
                                <?php echo esc_html($ad->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        
        <?php wp_nonce_field('ad_export_stats', '_wpnonce'); ?>
        <?php submit_button('Экспортировать данные', 'primary', 'submit'); ?>
    </form>
</div>
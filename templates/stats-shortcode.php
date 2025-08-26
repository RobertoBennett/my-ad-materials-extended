<?php
/**
 * Шаблон для вывода статистики через шорткод [advertiser_stats]
 * 
 * @param array $stats Статистика для отображения
 */
?>
<div class="advertiser-stats">
    <?php if (empty($stats)) : ?>
        <div class="ad-stats-notice">
            <p>Статистика пока отсутствует. Данные появятся после первых показов рекламы.</p>
            <?php if (current_user_can('manage_options')) : ?>
                <p>Как администратор, вы можете просматривать статистику всех рекламных материалов.</p>
            <?php elseif (current_user_can('advertiser')) : ?>
                <p>Как рекламодатель, вы видите статистику только по своим материалам.</p>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <table class="ad-stats-table">
            <thead>
                <tr>
                    <th>Материал</th>
                    <th>Показы</th>
                    <th>Клики</th>
                    <th>CTR (%)</th>
                    <?php if (current_user_can('manage_options')) : ?>
                        <th>Владелец</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats as $stat) : 
                    $owner = $stat->ad_id > 0 ? get_userdata(get_post_meta($stat->ad_id, '_advertiser_id', true)) : null;
                ?>
                    <tr>
                        <td><?php echo esc_html($stat->ad_title); ?></td>
                        <td><?php echo number_format($stat->impressions); ?></td>
                        <td><?php echo number_format($stat->clicks); ?></td>
                        <td><?php echo number_format($stat->ctr, 2); ?>%</td>
                        <?php if (current_user_can('manage_options')) : ?>
                            <td>
                                <?php if ($owner) : ?>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $owner->ID); ?>">
                                        <?php echo esc_html($owner->display_name); ?>
                                    </a>
                                <?php else : ?>
                                    Система
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="ad-stats-summary">
            <p>Всего показов: <strong><?php echo number_format(array_sum(array_column($stats, 'impressions'))); ?></strong></p>
            <p>Всего кликов: <strong><?php echo number_format(array_sum(array_column($stats, 'clicks'))); ?></strong></p>
            <p>Общий CTR: <strong><?php 
                $total_impressions = array_sum(array_column($stats, 'impressions'));
                $total_clicks = array_sum(array_column($stats, 'clicks'));
                echo $total_impressions ? number_format($total_clicks / $total_impressions * 100, 2) : '0.00';
            ?>%</strong></p>
        </div>
    <?php endif; ?>
</div>

<style>
    .ad-stats-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        font-size: 14px;
    }
    .ad-stats-table th {
        background-color: #f5f7fa;
        border: 1px solid #dfe2e5;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
    }
    .ad-stats-table td {
        border: 1px solid #dfe2e5;
        padding: 10px 15px;
    }
    .ad-stats-table tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    .ad-stats-summary {
        background-color: #f1f8ff;
        border-left: 4px solid #2188ff;
        padding: 15px;
        margin-top: 20px;
        border-radius: 0 4px 4px 0;
    }
    .ad-stats-summary p {
        margin: 5px 0;
        font-size: 15px;
    }
    .ad-stats-notice {
        background-color: #f8f9fa;
        border: 1px solid #e1e4e8;
        padding: 20px;
        border-radius: 4px;
        text-align: center;
        max-width: 600px;
        margin: 20px auto;
    }
    .ad-stats-notice p {
        margin: 10px 0;
        font-size: 16px;
        line-height: 1.6;
    }
</style>
<?php
/**
 * Шаблон email-отчета
 * 
 * @param array $stats Статистика для отчета
 * @param string $date_range Период отчета
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Отчет по рекламным материалам</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f5f5f5; padding: 15px; text-align: center; }
        .stats-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .stats-table th, .stats-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .stats-table th { background-color: #f9f9f9; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; color: #777; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Отчет по рекламным материалам</h1>
            <p>Статистика за период: <?php echo $date_range; ?></p>
        </div>
        
        <table class="stats-table">
            <thead>
                <tr>
                    <th>Рекламный материал</th>
                    <th>Показы</th>
                    <th>Клики</th>
                    <th>CTR (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats as $stat) : ?>
                    <tr>
                        <td><?php echo esc_html($stat->ad_title); ?></td>
                        <td><?php echo number_format($stat->impressions, 0, '', ' '); ?></td>
                        <td><?php echo number_format($stat->clicks, 0, '', ' '); ?></td>
                        <td><?php echo number_format($stat->ctr, 2); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Это автоматическое сообщение. Пожалуйста, не отвечайте на него.</p>
            <p>Вы получили это письмо, так как подписаны на отчеты рекламной платформы.</p>
            <p><a href="<?php echo admin_url('edit.php?post_type=ad_material&page=ad-reports-settings'); ?>">Изменить настройки отчетов</a></p>
        </div>
    </div>
</body>
</html>
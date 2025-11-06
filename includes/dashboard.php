<?php
// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Créer la page de menu pour le dashboard
add_action('admin_menu', 'pdf_analytics_dashboard_menu');

function pdf_analytics_dashboard_menu() {
    add_menu_page(
        __('PDF Analytics', 'pdf-analytics'),
        __('PDF Analytics', 'pdf-analytics'),
        'manage_options',
        'pdf-analytics-dashboard',
        'pdf_analytics_dashboard_page',
        'dashicons-chart-line',
        26
    );
    
    // Sous-menu pour les détails géographiques
    add_submenu_page(
        'pdf-analytics-dashboard',
        __('Geographical Statistics', 'pdf-analytics'),
        __('Geolocation', 'pdf-analytics'),
        'manage_options',
        'pdf-analytics-geo',
        'pdf_analytics_geo_page'
    );
}

// Enregistrer les styles CSS pour le dashboard
add_action('admin_enqueue_scripts', 'pdf_analytics_dashboard_styles');

function pdf_analytics_dashboard_styles($hook) {
    if (strpos($hook, 'pdf-analytics') === false) {
        return;
    }
    
    // Charger jQuery (nécessaire pour Chart.js dans certains cas)
    wp_enqueue_script('jquery');
    
    // Charger Chart.js depuis le CDN
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true);
    
    // Ajouter un CSS de base pour les graphiques
    wp_add_inline_style('wp-admin', '
        .chart-container { position: relative; height: 300px; }
        .chart-container-small { position: relative; height: 250px; }
    ');
}

// Page du dashboard principal
function pdf_analytics_dashboard_page() {
    $stats = get_pdf_global_stats();
    $top_posts = get_top_pdf_posts(10);
    $current_user = wp_get_current_user();
    ?>
    
    <div class="wrap pdf-analytics-dashboard">
        <style>
            .pdf-analytics-dashboard {
                background: #f0f0f1;
                padding: 20px;
            }
            
            .dashboard-header {
                background: linear-gradient(135deg, #044229 0%, #065d3a 100%);
                color: white;
                padding: 30px;
                border-radius: 12px;
                margin-bottom: 30px;
                box-shadow: 0 10px 30px rgba(4, 66, 41, 0.3);
            }
            
            .dashboard-header h1 {
                color: white;
                margin: 0 0 10px 0;
                font-size: 32px;
                font-weight: 700;
            }
            
            .dashboard-header p {
                margin: 0;
                opacity: 0.9;
                font-size: 16px;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .stat-card {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                transition: transform 0.2s, box-shadow 0.2s;
                border-left: 4px solid #044229;
            }
            
            .stat-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 20px rgba(4, 66, 41, 0.2);
            }
            
            .stat-card.views { border-left-color: #044229; }
            .stat-card.downloads { border-left-color: #065d3a; }
            .stat-card.conversion { border-left-color: #F2CFB3; }
            .stat-card.articles { border-left-color: #D4A373; }
            
            .stat-icon {
                font-size: 36px;
                margin-bottom: 10px;
                display: block;
            }
            
            .stat-label {
                font-size: 12px;
                text-transform: uppercase;
                color: #666;
                font-weight: 600;
                letter-spacing: 0.5px;
                margin-bottom: 5px;
            }
            
            .stat-value {
                font-size: 36px;
                font-weight: 700;
                color: #1e1e1e;
                margin-bottom: 10px;
            }
            
            .stat-change {
                font-size: 13px;
                color: #666;
            }
            
            .stat-change.positive {
                color: #00a32a;
            }
            
            .stat-change.negative {
                color: #d63638;
            }
            
            .dashboard-section {
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                margin-bottom: 30px;
            }
            
            .section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f0f1;
            }
            
            .section-title {
                font-size: 20px;
                font-weight: 700;
                color: #1e1e1e;
                margin: 0;
            }
            
            .section-actions {
                display: flex;
                gap: 10px;
            }
            
            .filter-btn {
                padding: 8px 16px;
                border: 1px solid #ddd;
                background: white;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 13px;
            }
            
            .filter-btn:hover, .filter-btn.active {
                background: #044229;
                color: white;
                border-color: #044229;
            }
            
            .chart-container {
                position: relative;
                height: 300px;
                margin-bottom: 20px;
            }
            
            .top-articles-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
            }
            
            .top-articles-table thead th {
                background: #f9f9f9;
                padding: 15px;
                text-align: left;
                font-weight: 600;
                color: #666;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 2px solid #e0e0e0;
            }
            
            .top-articles-table tbody tr {
                transition: background 0.2s;
            }
            
            .top-articles-table tbody tr:hover {
                background: #f9f9f9;
            }
            
            .top-articles-table tbody td {
                padding: 15px;
                border-bottom: 1px solid #f0f0f1;
                font-size: 14px;
            }
            
            .rank-badge {
                display: inline-block;
                width: 30px;
                height: 30px;
                line-height: 30px;
                text-align: center;
                border-radius: 50%;
                font-weight: 700;
                font-size: 13px;
            }
            
            .rank-badge.gold {
                background: linear-gradient(135deg, #F2CFB3 0%, #D4A373 100%);
                color: #044229;
                font-weight: 800;
            }
            
            .rank-badge.silver {
                background: linear-gradient(135deg, #e8e8e8 0%, #c8c8c8 100%);
                color: #044229;
                font-weight: 800;
            }
            
            .rank-badge.bronze {
                background: linear-gradient(135deg, #C9A88D 0%, #A68A64 100%);
                color: white;
                font-weight: 800;
            }
            
            .rank-badge.normal {
                background: #f0f0f1;
                color: #666;
            }
            
            .article-title-cell {
                max-width: 300px;
            }
            
            .article-title {
                font-weight: 600;
                color: #1e1e1e;
                text-decoration: none;
                display: block;
                margin-bottom: 5px;
            }
            
            .article-title:hover {
                color: #667eea;
            }
            
            .article-meta {
                font-size: 12px;
                color: #999;
            }
            
            .metric-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 600;
            }
            
            .metric-badge.views {
                background: #e7e9fc;
                color: #667eea;
            }
            
            .metric-badge.downloads {
                background: #e1f0ff;
                color: #2271b1;
            }
            
            .metric-badge.conversion {
                background: #e6f4ea;
                color: #00a32a;
            }
            
            .progress-bar {
                width: 100%;
                height: 8px;
                background: #f0f0f1;
                border-radius: 4px;
                overflow: hidden;
                margin-top: 5px;
            }
            
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #044229 0%, #065d3a 100%);
                transition: width 0.3s;
            }
            
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                color: #999;
            }
            
            .empty-state-icon {
                font-size: 64px;
                margin-bottom: 20px;
                opacity: 0.3;
            }
            
            .empty-state h3 {
                font-size: 20px;
                color: #666;
                margin-bottom: 10px;
            }
            
            .empty-state p {
                color: #999;
                margin: 0;
            }
        </style>
        
        <!-- Header -->
        <div class="dashboard-header">
            <h1>📊 <?php _e('PDF Analytics Dashboard', 'pdf-analytics'); ?></h1>
            <p><?php printf(__('Hello %s 👋 | Last update: %s', 'pdf-analytics'), esc_html($current_user->display_name), date('d/m/Y H:i')); ?></p>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card views">
                <span class="stat-icon">👁️</span>
                <div class="stat-label"><?php _e('Total Views', 'pdf-analytics'); ?></div>
                <div class="stat-value"><?php echo number_format($stats['total_views'], 0, ',', ' '); ?></div>
                <div class="stat-change">
                    <strong><?php echo $stats['views_today']; ?></strong> <?php _e('today', 'pdf-analytics'); ?> • 
                    <strong><?php echo $stats['views_week']; ?></strong> <?php _e('this week', 'pdf-analytics'); ?>
                </div>
            </div>
            
            <div class="stat-card downloads">
                <span class="stat-icon">⬇️</span>
                <div class="stat-label"><?php _e('Total Downloads', 'pdf-analytics'); ?></div>
                <div class="stat-value"><?php echo number_format($stats['total_downloads'], 0, ',', ' '); ?></div>
                <div class="stat-change">
                    <strong><?php echo $stats['downloads_today']; ?></strong> <?php _e('today', 'pdf-analytics'); ?> • 
                    <strong><?php echo $stats['downloads_week']; ?></strong> <?php _e('this week', 'pdf-analytics'); ?>
                </div>
            </div>
            
            <div class="stat-card conversion">
                <span class="stat-icon">📈</span>
                <div class="stat-label"><?php _e('Average Conversion Rate', 'pdf-analytics'); ?></div>
                <div class="stat-value"><?php echo $stats['avg_conversion_rate']; ?>%</div>
                <div class="stat-change">
                    <?php _e('Downloads / Views', 'pdf-analytics'); ?>
                </div>
            </div>
            
            <div class="stat-card articles">
                <span class="stat-icon">📄</span>
                <div class="stat-label"><?php _e('Articles with PDF', 'pdf-analytics'); ?></div>
                <div class="stat-value"><?php echo $stats['total_posts_with_pdf']; ?></div>
                <div class="stat-change">
                    <?php _e('Available documents', 'pdf-analytics'); ?>
                </div>
            </div>
        </div>
        
        <!-- Graphique -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2 class="section-title">📊 <?php _e('Performance Evolution', 'pdf-analytics'); ?></h2>
                <div class="section-actions">
                    <button class="filter-btn active" onclick="updateChart('week')"><?php _e('7 days', 'pdf-analytics'); ?></button>
                    <button class="filter-btn" onclick="updateChart('month')"><?php _e('30 days', 'pdf-analytics'); ?></button>
                    <button class="filter-btn" onclick="updateChart('year')"><?php _e('1 year', 'pdf-analytics'); ?></button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
        
        <!-- Top Articles -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2 class="section-title">🏆 <?php _e('Top 10 Best Performing Articles', 'pdf-analytics'); ?></h2>
                <div class="section-actions">
                    <button class="filter-btn active" onclick="sortTable('views')"><?php _e('By views', 'pdf-analytics'); ?></button>
                    <button class="filter-btn" onclick="sortTable('downloads')"><?php _e('By downloads', 'pdf-analytics'); ?></button>
                    <button class="filter-btn" onclick="sortTable('conversion')"><?php _e('By conversion', 'pdf-analytics'); ?></button>
                </div>
            </div>
            
            <?php if (empty($top_posts)) : ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3><?php _e('No data available', 'pdf-analytics'); ?></h3>
                    <p><?php _e('Statistics will appear once your PDF articles are viewed.', 'pdf-analytics'); ?></p>
                </div>
            <?php else : ?>
                <table class="top-articles-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th><?php _e('Article', 'pdf-analytics'); ?></th>
                            <th style="width: 120px; text-align: center;">👁️ <?php _e('Views', 'pdf-analytics'); ?></th>
                            <th style="width: 120px; text-align: center;">⬇️ <?php _e('Downloads', 'pdf-analytics'); ?></th>
                            <th style="width: 120px; text-align: center;">📈 <?php _e('Conversion', 'pdf-analytics'); ?></th>
                            <th style="width: 150px; text-align: center;"><?php _e('Last activity', 'pdf-analytics'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($top_posts as $post) : 
                            $badge_class = 'normal';
                            if ($rank == 1) $badge_class = 'gold';
                            else if ($rank == 2) $badge_class = 'silver';
                            else if ($rank == 3) $badge_class = 'bronze';
                            
                            $max_views = $top_posts[0]['views'];
                            $progress_width = $max_views > 0 ? ($post['views'] / $max_views) * 100 : 0;
                        ?>
                            <tr>
                                <td>
                                    <span class="rank-badge <?php echo $badge_class; ?>">
                                        <?php echo $rank; ?>
                                    </span>
                                </td>
                                <td class="article-title-cell">
                                    <a href="<?php echo esc_url($post['url']); ?>" target="_blank" class="article-title">
                                        <?php echo esc_html($post['title']); ?>
                                    </a>
                                    <div class="article-meta"><?php _e('ID:', 'pdf-analytics'); ?> <?php echo $post['id']; ?></div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress_width; ?>%;"></div>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="metric-badge views">
                                        <?php echo number_format($post['views'], 0, ',', ' '); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="metric-badge downloads">
                                        <?php echo number_format($post['downloads'], 0, ',', ' '); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="metric-badge conversion">
                                        <?php echo $post['conversion_rate']; ?>%
                                    </span>
                                </td>
                                <td style="text-align: center; font-size: 12px; color: #666;">
                                    <?php 
                                    $last_activity = max(
                                        strtotime($post['last_view'] ?? ''),
                                        strtotime($post['last_download'] ?? '')
                                    );
                                    if ($last_activity > 0) {
                                        echo date('d/m/Y', $last_activity) . '<br>';
                                        echo '<small style="color: #999;">' . date('H:i', $last_activity) . '</small>';
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php 
                            $rank++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <script>
            // Vérifier que Chart.js est chargé
            if (typeof Chart === 'undefined') {
                console.error('Chart.js non chargé');
                document.getElementById('performanceChart').innerHTML = '<div style="text-align: center; padding: 50px; color: #666;">📊 <?php _e('Chart.js not loaded - waiting for data...', 'pdf-analytics'); ?></div>';
            } else {
                // Données pour le graphique
                const ctx = document.getElementById('performanceChart').getContext('2d');
                
                const chartData = {
                    labels: ['<?php _e('Mon', 'pdf-analytics'); ?>', '<?php _e('Tue', 'pdf-analytics'); ?>', '<?php _e('Wed', 'pdf-analytics'); ?>', '<?php _e('Thu', 'pdf-analytics'); ?>', '<?php _e('Fri', 'pdf-analytics'); ?>', '<?php _e('Sat', 'pdf-analytics'); ?>', '<?php _e('Sun', 'pdf-analytics'); ?>'],
                    datasets: [
                        {
                            label: '<?php _e('Views', 'pdf-analytics'); ?>',
                            data: [0, 0, 0, 0, 0, 0, 0],
                            borderColor: '#044229',
                            backgroundColor: 'rgba(4, 66, 41, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: '<?php _e('Downloads', 'pdf-analytics'); ?>',
                            data: [0, 0, 0, 0, 0, 0, 0],
                            borderColor: '#F2CFB3',
                            backgroundColor: 'rgba(242, 207, 179, 0.2)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                };
                
                const performanceChart = new Chart(ctx, {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 13,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                cornerRadius: 8,
                                titleFont: {
                                    size: 14,
                                    weight: '600'
                                },
                                bodyFont: {
                                    size: 13
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 12
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Fonctions de filtrage
            function updateChart(period) {
                // Mettre à jour les boutons actifs
                document.querySelectorAll('.section-actions .filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                event.target.classList.add('active');
                
                // Ici vous pouvez ajouter la logique pour charger les données via AJAX
                console.log('Période sélectionnée:', period);
            }
            
            function sortTable(criteria) {
                // Mettre à jour les boutons actifs
                const buttons = document.querySelectorAll('.dashboard-section:last-child .filter-btn');
                buttons.forEach(btn => btn.classList.remove('active'));
                event.target.classList.add('active');
                
                // Recharger la page avec le tri approprié
                window.location.href = '?page=pdf-analytics-dashboard&sort=' + criteria;
            }
        </script>
    </div>
    
    <?php
}

// Page des statistiques géographiques
function pdf_analytics_geo_page() {
    $geo_stats = get_geo_statistics(null, 30);
    $recent_visitors = get_recent_visitors(20);
    ?>
    
    <div class="wrap pdf-analytics-geo">
        <style>
            .pdf-analytics-geo {
                background: #f0f0f1;
                padding: 20px;
            }
            
            .geo-header {
                background: linear-gradient(135deg, #044229 0%, #065d3a 100%);
                color: white;
                padding: 30px;
                border-radius: 12px;
                margin-bottom: 30px;
                box-shadow: 0 10px 30px rgba(4, 66, 41, 0.3);
            }
            
            .geo-header h1 {
                color: white;
                margin: 0 0 10px 0;
                font-size: 32px;
                font-weight: 700;
            }
            
            .geo-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .geo-card {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }
            
            .geo-card h3 {
                margin: 0 0 20px 0;
                font-size: 18px;
                font-weight: 700;
                color: #044229;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .stat-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 0;
                border-bottom: 1px solid #f0f0f1;
            }
            
            .stat-item:last-child {
                border-bottom: none;
            }
            
            .stat-label {
                font-size: 14px;
                color: #666;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .stat-value {
                font-weight: 700;
                color: #044229;
                font-size: 16px;
            }
            
            .flag-icon {
                font-size: 20px;
            }
            
            .visitors-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
            }
            
            .visitors-table th {
                background: #f9f9f9;
                padding: 12px;
                text-align: left;
                font-size: 12px;
                text-transform: uppercase;
                font-weight: 600;
                color: #666;
                border-bottom: 2px solid #e0e0e0;
            }
            
            .visitors-table td {
                padding: 12px;
                border-bottom: 1px solid #f0f0f1;
                font-size: 13px;
            }
            
            .visitors-table tr:hover {
                background: #f9f9f9;
            }
            
            .action-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
            }
            
            .action-badge.view {
                background: #e7e9fc;
                color: #667eea;
            }
            
            .action-badge.download {
                background: #e1f0ff;
                color: #2271b1;
            }
            
            .chart-container-small {
                position: relative;
                height: 250px;
                margin-top: 10px;
            }
            
            .fallback-stats {
                padding: 15px;
                background: #f9f9f9;
                border-radius: 8px;
                margin-top: 10px;
            }
            
            .fallback-item {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .fallback-item:last-child {
                border-bottom: none;
            }
        </style>
        
        <div class="geo-header">
            <h1>🌍 <?php _e('Geographical Statistics', 'pdf-analytics'); ?></h1>
            <p><?php _e('Detailed analysis of visitors and downloads (last 30 days)', 'pdf-analytics'); ?></p>
        </div>
        
        <div class="geo-grid">
            <!-- Pays -->
            <div class="geo-card">
                <h3>🌎 <?php _e('Top Countries', 'pdf-analytics'); ?></h3>
                <?php if (!empty($geo_stats['countries'])) : ?>
                    <?php foreach ($geo_stats['countries'] as $country) : ?>
                        <div class="stat-item">
                            <span class="stat-label">
                                <span class="flag-icon"><?php echo get_country_flag($country->country_code); ?></span>
                                <?php echo esc_html($country->country); ?>
                            </span>
                            <span class="stat-value"><?php echo number_format($country->count); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p style="color: #999; text-align: center;"><?php _e('No data available', 'pdf-analytics'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Villes -->
            <div class="geo-card">
                <h3>🏙️ <?php _e('Top Cities', 'pdf-analytics'); ?></h3>
                <?php if (!empty($geo_stats['cities'])) : ?>
                    <?php foreach ($geo_stats['cities'] as $city) : ?>
                        <div class="stat-item">
                            <span class="stat-label">
                                <?php echo esc_html($city->city); ?>, <?php echo esc_html($city->country); ?>
                            </span>
                            <span class="stat-value"><?php echo number_format($city->count); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p style="color: #999; text-align: center;"><?php _e('No data available', 'pdf-analytics'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Navigateurs -->
            <div class="geo-card">
                <h3>🌐 <?php _e('Browsers', 'pdf-analytics'); ?></h3>
                <?php if (!empty($geo_stats['browsers'])) : ?>
                    <div class="chart-container-small">
                        <canvas id="browserChart"></canvas>
                    </div>
                    <!-- Fallback en liste -->
                    <div class="fallback-stats">
                        <?php foreach ($geo_stats['browsers'] as $browser) : ?>
                            <div class="fallback-item">
                                <span><?php echo esc_html($browser->browser); ?></span>
                                <strong><?php echo number_format($browser->count); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p style="color: #999; text-align: center;"><?php _e('No data available', 'pdf-analytics'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Systèmes d'exploitation -->
            <div class="geo-card">
                <h3>💻 <?php _e('Operating Systems', 'pdf-analytics'); ?></h3>
                <?php if (!empty($geo_stats['os'])) : ?>
                    <div class="chart-container-small">
                        <canvas id="osChart"></canvas>
                    </div>
                    <!-- Fallback en liste -->
                    <div class="fallback-stats">
                        <?php foreach ($geo_stats['os'] as $os) : ?>
                            <div class="fallback-item">
                                <span><?php echo esc_html($os->os); ?></span>
                                <strong><?php echo number_format($os->count); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p style="color: #999; text-align: center;"><?php _e('No data available', 'pdf-analytics'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Visiteurs récents -->
        <div class="geo-card" style="margin-bottom: 30px;">
            <h3>👥 <?php _e('Recent Activity (last 20 actions)', 'pdf-analytics'); ?></h3>
            <table class="visitors-table">
                <thead>
                    <tr>
                        <th><?php _e('Date & Time', 'pdf-analytics'); ?></th>
                        <th><?php _e('Action', 'pdf-analytics'); ?></th>
                        <th><?php _e('Article', 'pdf-analytics'); ?></th>
                        <th><?php _e('Country / City', 'pdf-analytics'); ?></th>
                        <th><?php _e('Browser', 'pdf-analytics'); ?></th>
                        <th><?php _e('OS', 'pdf-analytics'); ?></th>
                        <th><?php _e('Device', 'pdf-analytics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_visitors)) : ?>
                        <?php foreach ($recent_visitors as $visitor) : ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($visitor->created_at)); ?></td>
                                <td>
                                    <span class="action-badge <?php echo $visitor->action_type; ?>">
                                        <?php echo $visitor->action_type == 'view' ? '👁️ ' . __('View', 'pdf-analytics') : '⬇️ ' . __('Download', 'pdf-analytics'); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($visitor->post_title); ?></td>
                                <td>
                                    <?php 
                                    if ($visitor->city && $visitor->country) {
                                        echo esc_html($visitor->city) . ', ' . esc_html($visitor->country);
                                    } elseif ($visitor->country) {
                                        echo esc_html($visitor->country);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($visitor->browser); ?></td>
                                <td><?php echo esc_html($visitor->os); ?></td>
                                <td><?php echo esc_html($visitor->device); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #999; padding: 40px;">
                                <?php _e('No activity recorded yet', 'pdf-analytics'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <script>
            // Graphique des navigateurs
            <?php if (!empty($geo_stats['browsers'])) : ?>
            document.addEventListener('DOMContentLoaded', function() {
                const browserCtx = document.getElementById('browserChart');
                if (browserCtx && typeof Chart !== 'undefined') {
                    new Chart(browserCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode(array_map(function($b) { return $b->browser; }, $geo_stats['browsers'])); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_map(function($b) { return $b->count; }, $geo_stats['browsers'])); ?>,
                                backgroundColor: ['#044229', '#065d3a', '#F2CFB3', '#D4A373', '#8B7355', '#6B5B4D', '#4A3C2A']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { 
                                    position: 'bottom',
                                    labels: {
                                        padding: 15,
                                        usePointStyle: true
                                    }
                                }
                            }
                        }
                    });
                } else if (browserCtx) {
                    browserCtx.innerHTML = '<div style="text-align: center; padding: 50px; color: #666;">📊 <?php _e('Chart not available', 'pdf-analytics'); ?></div>';
                }
            });
            <?php endif; ?>
            
            // Graphique des OS
            <?php if (!empty($geo_stats['os'])) : ?>
            document.addEventListener('DOMContentLoaded', function() {
                const osCtx = document.getElementById('osChart');
                if (osCtx && typeof Chart !== 'undefined') {
                    new Chart(osCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode(array_map(function($o) { return $o->os; }, $geo_stats['os'])); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_map(function($o) { return $o->count; }, $geo_stats['os'])); ?>,
                                backgroundColor: ['#044229', '#065d3a', '#F2CFB3', '#D4A373', '#8B7355', '#6B5B4D', '#4A3C2A']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { 
                                    position: 'bottom',
                                    labels: {
                                        padding: 15,
                                        usePointStyle: true
                                    }
                                }
                            }
                        }
                    });
                } else if (osCtx) {
                    osCtx.innerHTML = '<div style="text-align: center; padding: 50px; color: #666;">📊 <?php _e('Chart not available', 'pdf-analytics'); ?></div>';
                }
            });
            <?php endif; ?>
        </script>
    </div>
    
    <?php
}
?>
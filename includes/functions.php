<?php
// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Créer la table pour stocker les détails des visiteurs
function create_pdf_analytics_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_analytics_details';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        action_type varchar(20) NOT NULL,
        user_ip varchar(100) NOT NULL,
        country varchar(100),
        country_code varchar(10),
        city varchar(100),
        region varchar(100),
        latitude varchar(50),
        longitude varchar(50),
        timezone varchar(100),
        isp varchar(255),
        browser varchar(100),
        os varchar(100),
        device varchar(50),
        user_agent text,
        referrer text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY post_id (post_id),
        KEY action_type (action_type),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Vérifier que la table existe
add_action('admin_init', 'verify_pdf_analytics_table');
function verify_pdf_analytics_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_analytics_details';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        create_pdf_analytics_table();
    }
}

// Fonction améliorée pour obtenir les informations de géolocalisation
function get_visitor_geo_info($ip) {
    // Vérifier les IPs locales
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return array(
            'country' => __('Local', 'pdf-analytics'),
            'country_code' => 'LOCAL',
            'city' => __('Local Network', 'pdf-analytics'),
            'region' => __('Local', 'pdf-analytics'),
            'latitude' => '',
            'longitude' => '',
            'timezone' => '',
            'isp' => __('Local Network', 'pdf-analytics')
        );
    }
    
    // API gratuite pour la géolocalisation (ip-api.com) - CORRIGÉ EN HTTPS
    $api_url = "https://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query";
    
    $response = wp_remote_get($api_url, array(
        'timeout' => 5,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ));
    
    if (is_wp_error($response)) {
        error_log(__('Geolocation error:', 'pdf-analytics') . ' ' . $response->get_error_message());
        return null;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($data && isset($data['status']) && $data['status'] === 'success') {
        return array(
            'country' => $data['country'] ?? '',
            'country_code' => $data['countryCode'] ?? '',
            'city' => $data['city'] ?? '',
            'region' => $data['regionName'] ?? '',
            'latitude' => $data['lat'] ?? '',
            'longitude' => $data['lon'] ?? '',
            'timezone' => $data['timezone'] ?? '',
            'isp' => $data['isp'] ?? '',
        );
    }
    
    error_log(__('Geolocation API error:', 'pdf-analytics') . ' ' . ($data['message'] ?? __('Unknown error', 'pdf-analytics')));
    return null;
}

// Fonction pour détecter le navigateur et l'OS
function get_browser_and_os($user_agent) {
    $browser = __('Unknown', 'pdf-analytics');
    $os = __('Unknown', 'pdf-analytics');
    $device = __('Desktop', 'pdf-analytics');
    
    // Détection du navigateur
    if (strpos($user_agent, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($user_agent, 'Chrome') !== false && strpos($user_agent, 'Edg') === false && strpos($user_agent, 'Edge') === false) {
        $browser = 'Chrome';
    } elseif (strpos($user_agent, 'Safari') !== false && strpos($user_agent, 'Chrome') === false) {
        $browser = 'Safari';
    } elseif (strpos($user_agent, 'Edg') !== false || strpos($user_agent, 'Edge') !== false) {
        $browser = 'Edge';
    } elseif (strpos($user_agent, 'Opera') !== false || strpos($user_agent, 'OPR') !== false) {
        $browser = 'Opera';
    }
    
    // Détection de l'OS
    if (strpos($user_agent, 'Windows NT 10') !== false) {
        $os = 'Windows 10';
    } elseif (strpos($user_agent, 'Windows NT 11') !== false) {
        $os = 'Windows 11';
    } elseif (strpos($user_agent, 'Mac OS X') !== false || strpos($user_agent, 'macOS') !== false) {
        $os = 'macOS';
    } elseif (strpos($user_agent, 'Linux') !== false) {
        $os = 'Linux';
    } elseif (strpos($user_agent, 'Android') !== false) {
        $os = 'Android';
        $device = __('Mobile', 'pdf-analytics');
    } elseif (strpos($user_agent, 'iPhone') !== false || strpos($user_agent, 'iPad') !== false) {
        $os = 'iOS';
        $device = strpos($user_agent, 'iPad') !== false ? __('Tablet', 'pdf-analytics') : __('Mobile', 'pdf-analytics');
    }
    
    return array(
        'browser' => $browser,
        'os' => $os,
        'device' => $device
    );
}

// Fonction améliorée pour enregistrer les détails du visiteur
function log_pdf_visitor_details($post_id, $action_type) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_analytics_details';
    
    // Obtenir l'IP du visiteur
    $user_ip = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $user_ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $user_ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $user_ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // Vérifier si c'est une IP valide
    if (empty($user_ip) || !filter_var($user_ip, FILTER_VALIDATE_IP)) {
        error_log('IP invalide pour la géolocalisation: ' . $user_ip);
        return false;
    }
    
    // Obtenir les infos de géolocalisation
    $geo_info = get_visitor_geo_info($user_ip);
    
    // Obtenir les infos du navigateur et OS
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $browser_os = get_browser_and_os($user_agent);
    
    // Obtenir le referrer
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Préparer les données pour l'insertion
    $data = array(
        'post_id' => $post_id,
        'action_type' => $action_type,
        'user_ip' => $user_ip,
        'country' => $geo_info['country'] ?? '',
        'country_code' => $geo_info['country_code'] ?? '',
        'city' => $geo_info['city'] ?? '',
        'region' => $geo_info['region'] ?? '',
        'latitude' => $geo_info['latitude'] ?? '',
        'longitude' => $geo_info['longitude'] ?? '',
        'timezone' => $geo_info['timezone'] ?? '',
        'isp' => $geo_info['isp'] ?? '',
        'browser' => $browser_os['browser'],
        'os' => $browser_os['os'],
        'device' => $browser_os['device'],
        'user_agent' => $user_agent,
        'referrer' => $referrer,
    );
    
    // Insérer dans la base de données
    $result = $wpdb->insert(
        $table_name,
        $data,
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        error_log('Erreur insertion données géolocalisation: ' . $wpdb->last_error);
        return false;
    }
    
    return true;
}

// Fonctions AJAX unifiées avec géolocalisation
add_action('wp_ajax_track_pdf_view', 'track_pdf_view_with_details');
add_action('wp_ajax_nopriv_track_pdf_view', 'track_pdf_view_with_details');

function track_pdf_view_with_details() {
    check_ajax_referer('pdf_action_nonce', 'nonce');
    
    $post_id = intval($_POST['post_id']);
    
    if ($post_id) {
        // Incrémenter le compteur
        $current_count = get_post_meta($post_id, 'pdf_view_count', true);
        $new_count = $current_count ? intval($current_count) + 1 : 1;
        update_post_meta($post_id, 'pdf_view_count', $new_count);
        update_post_meta($post_id, 'pdf_last_view', current_time('mysql'));
        
        // Enregistrer les détails du visiteur (géolocalisation)
        log_pdf_visitor_details($post_id, 'view');
        
        wp_send_json_success(array('count' => $new_count));
    }
    
    wp_die();
}

add_action('wp_ajax_track_pdf_download', 'track_pdf_download_with_details');
add_action('wp_ajax_nopriv_track_pdf_download', 'track_pdf_download_with_details');

function track_pdf_download_with_details() {
    check_ajax_referer('pdf_action_nonce', 'nonce');
    
    $post_id = intval($_POST['post_id']);
    
    if ($post_id) {
        // Incrémenter le compteur
        $current_count = get_post_meta($post_id, 'pdf_download_count', true);
        $new_count = $current_count ? intval($current_count) + 1 : 1;
        update_post_meta($post_id, 'pdf_download_count', $new_count);
        update_post_meta($post_id, 'pdf_last_download', current_time('mysql'));
        
        // Enregistrer les détails du visiteur (géolocalisation)
        log_pdf_visitor_details($post_id, 'download');
        
        wp_send_json_success(array('count' => $new_count));
    }
    
    wp_die();
}

// Fonction pour récupérer les statistiques géographiques
function get_geo_statistics($post_id = null, $days = 30) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_analytics_details';
    
    $where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    if ($post_id) {
        $where .= " AND post_id = " . intval($post_id);
    }
    
    // Stats par pays
    $countries = $wpdb->get_results("
        SELECT country, country_code, COUNT(*) as count
        FROM $table_name
        $where AND country != ''
        GROUP BY country, country_code
        ORDER BY count DESC
        LIMIT 10
    ");
    
    // Stats par ville
    $cities = $wpdb->get_results("
        SELECT city, country, COUNT(*) as count
        FROM $table_name
        $where AND city != ''
        GROUP BY city, country
        ORDER BY count DESC
        LIMIT 10
    ");
    
    // Stats par navigateur
    $browsers = $wpdb->get_results("
        SELECT browser, COUNT(*) as count
        FROM $table_name
        $where
        GROUP BY browser
        ORDER BY count DESC
    ");
    
    // Stats par OS
    $os_stats = $wpdb->get_results("
        SELECT os, COUNT(*) as count
        FROM $table_name
        $where
        GROUP BY os
        ORDER BY count DESC
    ");
    
    // Stats par device
    $devices = $wpdb->get_results("
        SELECT device, COUNT(*) as count
        FROM $table_name
        $where
        GROUP BY device
        ORDER BY count DESC
    ");
    
    return array(
        'countries' => $countries,
        'cities' => $cities,
        'browsers' => $browsers,
        'os' => $os_stats,
        'devices' => $devices
    );
}

// Fonction pour récupérer les visiteurs récents
function get_recent_visitors($limit = 20) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_analytics_details';
    
    return $wpdb->get_results("
        SELECT a.*, p.post_title
        FROM $table_name a
        LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
        ORDER BY a.created_at DESC
        LIMIT $limit
    ");
}

// Fonction pour récupérer les statistiques globales - VERSION CORRIGÉE
function get_pdf_global_stats() {
    global $wpdb;
    
    $stats = array(
        'total_views' => 0,
        'total_downloads' => 0,
        'total_posts_with_pdf' => 0,
        'avg_conversion_rate' => 0,
        'views_today' => 0,
        'downloads_today' => 0,
        'views_week' => 0,
        'downloads_week' => 0,
        'views_month' => 0,
        'downloads_month' => 0,
    );
    
    // Récupérer tous les posts avec PDF
    $args = array(
        'post_type' => 'post',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'pdf_file_url',
                'compare' => '!=',
                'value' => ''
            )
        )
    );
    
    $posts = get_posts($args);
    $stats['total_posts_with_pdf'] = count($posts);
    
    $conversion_rates = array();
    
    foreach ($posts as $post) {
        $views = get_post_meta($post->ID, 'pdf_view_count', true);
        $downloads = get_post_meta($post->ID, 'pdf_download_count', true);
        
        $stats['total_views'] += $views ? intval($views) : 0;
        $stats['total_downloads'] += $downloads ? intval($downloads) : 0;
        
        if ($views > 0 && $downloads > 0) {
            $conversion_rates[] = ($downloads / $views) * 100;
        }
    }
    
    if (!empty($conversion_rates)) {
        $stats['avg_conversion_rate'] = round(array_sum($conversion_rates) / count($conversion_rates), 1);
    }
    
    // Stats pour aujourd'hui, cette semaine, ce mois - VERSION CORRIGÉE
    $table_name = $wpdb->prefix . 'pdf_analytics_details';
    $today_start = date('Y-m-d 00:00:00');
    $week_start = date('Y-m-d 00:00:00', strtotime('-7 days'));
    $month_start = date('Y-m-d 00:00:00', strtotime('-30 days'));

    // Aujourd'hui
    $stats['views_today'] = $wpdb->get_var("
        SELECT COUNT(*) FROM $table_name 
        WHERE action_type = 'view' AND created_at >= '$today_start'
    ");
    $stats['downloads_today'] = $wpdb->get_var("
        SELECT COUNT(*) FROM $table_name 
        WHERE action_type = 'download' AND created_at >= '$today_start'
    ");

    // Cette semaine
    $stats['views_week'] = $wpdb->get_var("
        SELECT COUNT(*) FROM $table_name 
        WHERE action_type = 'view' AND created_at >= '$week_start'
    ");
    $stats['downloads_week'] = $wpdb->get_var("
        SELECT COUNT(*) FROM $table_name 
        WHERE action_type = 'download' AND created_at >= '$week_start'
    ");

    // Ce mois
    $stats['views_month'] = $wpdb->get_var("
        SELECT COUNT(*) FROM $table_name 
        WHERE action_type = 'view' AND created_at >= '$month_start'
    ");
    $stats['downloads_month'] = $wpdb->get_var("
        SELECT COUNT(*) FROM $table_name 
        WHERE action_type = 'download' AND created_at >= '$month_start'
    ");
    
    return $stats;
}

// Fonction pour récupérer le top des articles
function get_top_pdf_posts($limit = 10, $orderby = 'views') {
    $args = array(
        'post_type' => 'post',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'pdf_file_url',
                'compare' => '!=',
                'value' => ''
            )
        )
    );
    
    $posts = get_posts($args);
    $posts_data = array();
    
    foreach ($posts as $post) {
        $views = get_post_meta($post->ID, 'pdf_view_count', true);
        $downloads = get_post_meta($post->ID, 'pdf_download_count', true);
        
        $views = $views ? intval($views) : 0;
        $downloads = $downloads ? intval($downloads) : 0;
        
        $conversion_rate = 0;
        if ($views > 0 && $downloads > 0) {
            $conversion_rate = round(($downloads / $views) * 100, 1);
        }
        
        $posts_data[] = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'views' => $views,
            'downloads' => $downloads,
            'conversion_rate' => $conversion_rate,
            'last_view' => get_post_meta($post->ID, 'pdf_last_view', true),
            'last_download' => get_post_meta($post->ID, 'pdf_last_download', true),
            'url' => get_permalink($post->ID)
        );
    }
    
    // Trier selon le critère
    usort($posts_data, function($a, $b) use ($orderby) {
        if ($orderby == 'downloads') {
            return $b['downloads'] - $a['downloads'];
        } else if ($orderby == 'conversion') {
            return $b['conversion_rate'] - $a['conversion_rate'];
        } else {
            return $b['views'] - $a['views'];
        }
    });
    
    return array_slice($posts_data, 0, $limit);
}

// Fonction helper pour afficher les drapeaux emoji
function get_country_flag($country_code) {
    if (empty($country_code) || strlen($country_code) != 2) {
        return '🌍';
    }
    
    $country_code = strtoupper($country_code);
    $first_letter = mb_chr(ord($country_code[0]) - ord('A') + 0x1F1E6);
    $second_letter = mb_chr(ord($country_code[1]) - ord('A') + 0x1F1E6);
    
    return $first_letter . $second_letter;
}

// ========== SCRIPT JAVASCRIPT POUR LE TRACKING ==========
add_action('wp_footer', 'add_pdf_tracking_script');

function add_pdf_tracking_script() {
    if (is_single()) {
        $pdf_url = get_post_meta(get_the_ID(), 'pdf_file_url', true);
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var postId = <?php echo get_the_ID(); ?>;
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = '<?php echo wp_create_nonce('pdf_action_nonce'); ?>';
            
            <?php if ($pdf_url) : ?>
            
            // Bouton VOIR (view-pdf-btn)
            $('#view-pdf-btn').on('click', function(e) {
                console.log('Clic sur VUE PDF détecté');
                
                // Track la vue AVANT l'ouverture du PDF
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'track_pdf_view',
                        post_id: postId,
                        nonce: nonce
                    },
                    success: function(response) {
                        console.log('✓ Vue PDF enregistrée ! Total:', response.data.count);
                    },
                    error: function(xhr, status, error) {
                        console.log('✗ Erreur lors de l\'enregistrement de la vue:', error);
                    }
                });
                // Le lien s'ouvre normalement
            });
            
            // Bouton TÉLÉCHARGER (download-pdf-btn)
            $('#download-pdf-btn').on('click', function(e) {
                console.log('Clic sur TÉLÉCHARGEMENT PDF détecté');
                
                // Track le téléchargement AVANT le téléchargement
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'track_pdf_download',
                        post_id: postId,
                        nonce: nonce
                    },
                    success: function(response) {
                        console.log('✓ Téléchargement PDF enregistré ! Total:', response.data.count);
                    },
                    error: function(xhr, status, error) {
                        console.log('✗ Erreur lors de l\'enregistrement du téléchargement:', error);
                    }
                });
                // Le téléchargement se fait normalement
            });
            
            <?php else : ?>
            console.log('Aucun PDF configuré pour cet article');
            <?php endif; ?>
        });
        </script>
        <?php
    }
}

// ========== AFFICHAGE DANS LA LISTE DES ARTICLES ==========
add_filter('manage_posts_columns', 'add_pdf_stats_columns');
function add_pdf_stats_columns($columns) {
    $columns['pdf_views'] = '👁️ ' . __('PDF Views', 'pdf-analytics');
    $columns['pdf_downloads'] = '⬇️ ' . __('Downloads', 'pdf-analytics');
    return $columns;
}

add_action('manage_posts_custom_column', 'show_pdf_stats_columns', 10, 2);
function show_pdf_stats_columns($column, $post_id) {
    if ($column == 'pdf_views') {
        $count = get_post_meta($post_id, 'pdf_view_count', true);
        $last = get_post_meta($post_id, 'pdf_last_view', true);
        
        echo '<strong>' . ($count ? $count : '0') . '</strong>';
        
        if ($last) {
            echo '<br><small style="color: #666;">' . 
                 date('d/m H:i', strtotime($last)) . '</small>';
        }
    }
    
    if ($column == 'pdf_downloads') {
        $count = get_post_meta($post_id, 'pdf_download_count', true);
        $last = get_post_meta($post_id, 'pdf_last_download', true);
        
        echo '<strong style="color: #2271b1;">' . ($count ? $count : '0') . '</strong>';
        
        if ($last) {
            echo '<br><small style="color: #666;">' . 
                 date('d/m H:i', strtotime($last)) . '</small>';
        }
    }
}

// Rendre les colonnes triables
add_filter('manage_edit-post_sortable_columns', 'make_pdf_stats_sortable');
function make_pdf_stats_sortable($columns) {
    $columns['pdf_views'] = 'pdf_views';
    $columns['pdf_downloads'] = 'pdf_downloads';
    return $columns;
}

// ========== META BOX AVEC CONFIGURATION PDF ==========
add_action('add_meta_boxes', 'pdf_config_meta_box');

function pdf_config_meta_box() {
    add_meta_box(
        'pdf_config',
        '📄 ' . __('PDF Configuration & Stats', 'pdf-analytics'),
        'pdf_config_meta_box_content',
        'post',
        'side',
        'high'
    );
}

function pdf_config_meta_box_content($post) {
    wp_nonce_field('pdf_config_save', 'pdf_config_nonce');
    
    $pdf_url = get_post_meta($post->ID, 'pdf_file_url', true);
    $views = get_post_meta($post->ID, 'pdf_view_count', true);
    $downloads = get_post_meta($post->ID, 'pdf_download_count', true);
    $last_view = get_post_meta($post->ID, 'pdf_last_view', true);
    $last_download = get_post_meta($post->ID, 'pdf_last_download', true);
    ?>
    
    <p>
        <label for="pdf_file_url" style="font-weight: 600;"><?php _e('PDF URL:', 'pdf-analytics'); ?></label><br>
        <input type="text" id="pdf_file_url" name="pdf_file_url" 
               value="<?php echo esc_attr($pdf_url); ?>" 
               style="width: 100%; margin-top: 5px;" 
               placeholder="https://...">
        <small style="color: #666;"><?php _e('Paste the complete PDF URL', 'pdf-analytics'); ?></small>
    </p>
    
    <hr style="margin: 15px 0;">
    
    <div style="padding: 12px; background: #f0f0f1; border-radius: 4px;">
        <p style="margin: 0 0 12px 0; font-weight: 600;">📊 <?php _e('Statistics', 'pdf-analytics'); ?></p>
        
        <!-- VUES -->
        <div style="padding: 8px; background: #fff; border-radius: 3px; margin-bottom: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <p style="margin: 0; font-size: 11px; color: #666;">👁️ <?php _e('Views ("View" button)', 'pdf-analytics'); ?></p>
                    <p style="margin: 2px 0 0 0; font-size: 24px; font-weight: bold; color: #666;">
                        <?php echo $views ? $views : '0'; ?>
                    </p>
                </div>
                <?php if ($last_view) : ?>
                <div style="text-align: right;">
                    <p style="margin: 0; font-size: 10px; color: #999;"><?php _e('Last:', 'pdf-analytics'); ?></p>
                    <p style="margin: 2px 0 0 0; font-size: 11px; color: #666;">
                        <?php echo date('d/m/y H:i', strtotime($last_view)); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- TÉLÉCHARGEMENTS -->
        <div style="padding: 8px; background: #fff; border-radius: 3px; margin-bottom: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <p style="margin: 0; font-size: 11px; color: #666;">⬇️ <?php _e('Downloads', 'pdf-analytics'); ?></p>
                    <p style="margin: 2px 0 0 0; font-size: 24px; font-weight: bold; color: #2271b1;">
                        <?php echo $downloads ? $downloads : '0'; ?>
                    </p>
                </div>
                <?php if ($last_download) : ?>
                <div style="text-align: right;">
                    <p style="margin: 0; font-size: 10px; color: #999;"><?php _e('Last:', 'pdf-analytics'); ?></p>
                    <p style="margin: 2px 0 0 0; font-size: 11px; color: #666;">
                        <?php echo date('d/m/y H:i', strtotime($last_download)); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($views > 0 && $downloads > 0) : 
            $conversion_rate = round(($downloads / $views) * 100, 1);
        ?>
        <div style="padding: 6px; background: #d7f0ff; border-radius: 3px; text-align: center;">
            <p style="margin: 0; font-size: 11px; color: #0073aa;">
                📈 <?php _e('Conversion rate:', 'pdf-analytics'); ?> <strong><?php echo $conversion_rate; ?>%</strong>
            </p>
            <p style="margin: 2px 0 0 0; font-size: 9px; color: #666;">
                <?php echo $downloads; ?> <?php _e('downloads', 'pdf-analytics'); ?> / <?php echo $views; ?> <?php _e('views', 'pdf-analytics'); ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($views > 0 || $downloads > 0) : ?>
    <p style="margin-top: 10px;">
        <button type="button" onclick="if(confirm('<?php _e('Do you really want to reset all PDF statistics for this article?', 'pdf-analytics'); ?>')) { 
            document.getElementById('reset_pdf_stats').value='1'; 
            document.querySelector('input[name=save]').click(); 
        }" class="button button-small" style="width: 100%; color: #b32d2e;">
            🔄 <?php _e('Reset statistics', 'pdf-analytics'); ?>
        </button>
        <input type="hidden" id="reset_pdf_stats" name="reset_pdf_stats" value="0">
    </p>
    <?php endif; ?>
    
    <hr style="margin: 15px 0;">
    
    <div style="padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107; font-size: 11px;">
        <strong>ℹ️ <?php _e('How it works:', 'pdf-analytics'); ?></strong><br>
        • <strong><?php _e('"VIEW" button', 'pdf-analytics'); ?></strong> (ID: view-pdf-btn) : <?php _e('Opens PDF in browser (counts views)', 'pdf-analytics'); ?><br>
        • <strong><?php _e('"DOWNLOAD" button', 'pdf-analytics'); ?></strong> (ID: download-pdf-btn) : <?php _e('Directly downloads file (counts downloads)', 'pdf-analytics'); ?>
    </div>
    <?php
}

// Sauvegarder la configuration
add_action('save_post', 'save_pdf_config');

function save_pdf_config($post_id) {
    if (!isset($_POST['pdf_config_nonce']) || 
        !wp_verify_nonce($_POST['pdf_config_nonce'], 'pdf_config_save')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (isset($_POST['pdf_file_url'])) {
        update_post_meta($post_id, 'pdf_file_url', esc_url_raw($_POST['pdf_file_url']));
    }
    
    // Réinitialisation des stats si demandée
    if (isset($_POST['reset_pdf_stats']) && $_POST['reset_pdf_stats'] == '1') {
        delete_post_meta($post_id, 'pdf_view_count');
        delete_post_meta($post_id, 'pdf_download_count');
        delete_post_meta($post_id, 'pdf_last_view');
        delete_post_meta($post_id, 'pdf_last_download');
    }
}
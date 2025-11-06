<?php
/**
 * Plugin Name: PDF Analytics
 * Plugin URI: https://breachbuilders.org/
 * Description: Track PDF views and downloads with detailed analytics and geolocation
 * Version: 1.0.0
 * Author: WPTNF
 * Text Domain: pdf-analytics
 * Domain Path: /languages
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Définir les constantes du plugin
define('PDF_ANALYTICS_VERSION', '1.0.0');
define('PDF_ANALYTICS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PDF_ANALYTICS_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Inclure les fichiers nécessaires
require_once PDF_ANALYTICS_PLUGIN_PATH . 'includes/functions.php';
require_once PDF_ANALYTICS_PLUGIN_PATH . 'includes/dashboard.php';

// Hook d'activation
register_activation_hook(__FILE__, 'pdf_analytics_activate');
function pdf_analytics_activate() {
    // Créer la table lors de l'activation
    create_pdf_analytics_table();
    
    // Programmer une action pour vider les données temporaires si nécessaire
    if (!wp_next_scheduled('pdf_analytics_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'pdf_analytics_daily_cleanup');
    }
}

// Hook de désactivation  
register_deactivation_hook(__FILE__, 'pdf_analytics_deactivate');
function pdf_analytics_deactivate() {
    // Nettoyer les tâches programmées
    wp_clear_scheduled_hook('pdf_analytics_daily_cleanup');
}

// Charger les traductions
add_action('plugins_loaded', 'pdf_analytics_load_textdomain');
function pdf_analytics_load_textdomain() {
    load_plugin_textdomain('pdf-analytics', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Action quotidienne pour le nettoyage
add_action('pdf_analytics_daily_cleanup', 'pdf_analytics_cleanup_old_data');
function pdf_analytics_cleanup_old_data() {
    // Vous pouvez ajouter ici un nettoyage des vieilles données si nécessaire
    // Par exemple, supprimer les entrées de plus de 1 an
}
?>
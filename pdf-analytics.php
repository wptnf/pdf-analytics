<?php
/**
 * Plugin Name: PDF Analytics
 * Plugin URI: https://github.com/wptnf/pdf-analytics
 * Description: Track PDF views and downloads with detailed analytics and geolocation
 * Version: 1.0.0
 * Author: WPTNF
 * License: GPL v2 or later
 * Text Domain: pdf-analytics
 * Domain Path: /languages
 * Update URI: https://github.com/wptnf/pdf-analytics
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Définir les constantes du plugin
define('PDF_ANALYTICS_VERSION', '1.0.0');
define('PDF_ANALYTICS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PDF_ANALYTICS_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Inclure l'update checker
require_once PDF_ANALYTICS_PLUGIN_PATH . 'includes/plugin-update-checker.php';

// Inclure les fichiers nécessaires
require_once PDF_ANALYTICS_PLUGIN_PATH . 'includes/functions.php';
require_once PDF_ANALYTICS_PLUGIN_PATH . 'includes/dashboard.php';

// Initialiser le système de mises à jour
add_action('init', 'pdf_analytics_init_updater');
function pdf_analytics_init_updater() {
    $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
        'https://github.com/wptnf/pdf-analytics/',
        __FILE__,
        'pdf-analytics'
    );
    
    // Optionnel : Définir la branche
    $myUpdateChecker->setBranch('main');
    
    // Optionnel : Forcer les vérifications
    $myUpdateChecker->setCheckPeriod(12); // Heures
}

// Hook d'activation
register_activation_hook(__FILE__, 'pdf_analytics_activate');
function pdf_analytics_activate() {
    create_pdf_analytics_table();
}

// Hook de désactivation  
register_deactivation_hook(__FILE__, 'pdf_analytics_deactivate');
function pdf_analytics_deactivate() {
    // Nettoyage si nécessaire
}

// Charger les traductions
add_action('plugins_loaded', 'pdf_analytics_load_textdomain');
function pdf_analytics_load_textdomain() {
    load_plugin_textdomain('pdf-analytics', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
?>
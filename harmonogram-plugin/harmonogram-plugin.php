<?php
/**
 * Plugin Name: Harmonogram Odbioru Odpadów
 * Description: Plugin do zarządzania harmonogramem odbioru odpadów z możliwością uploadowania CSV-ów.
 * Version: 1.4
 * Author: Jan Kropidłowski
 * Text Domain: harmonogram-plugin
 */

// Zabezpieczenie przed bezpośrednim uruchomieniem pliku
if (!defined('ABSPATH')) {
    exit;
}

// Definiowanie stałych dla ścieżki i URL pluginu
define('HARMONOGRAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HARMONOGRAM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Inkludowanie plików
require_once HARMONOGRAM_PLUGIN_DIR . 'includes/functions.php';
require_once HARMONOGRAM_PLUGIN_DIR . 'includes/admin.php';
require_once HARMONOGRAM_PLUGIN_DIR . 'includes/csv-upload.php';
require_once HARMONOGRAM_PLUGIN_DIR . 'includes/ajax-handler.php';
require_once HARMONOGRAM_PLUGIN_DIR . 'includes/harmonogram-master-shortcode.php';
require_once HARMONOGRAM_PLUGIN_DIR . 'includes/harmonogram-mobile-shortcode.php';
require_once HARMONOGRAM_PLUGIN_DIR . 'includes/import.php';

/**
 * Rejestracja skryptów i stylów dla admina
 */
function harmonogram_plugin_enqueue_admin_scripts($hook) {
    // Ładujemy skrypty tylko na stronach pluginu w panelu admina
    if ($hook !== 'toplevel_page_harmonogram-plugin' && $hook !== 'harmonogram-plugin_page_harmonogram-plugin-csv-upload') {
        return;
    }

    // Enqueue Feather Icons
    wp_enqueue_script('feather-icons', 'https://unpkg.com/feather-icons', [], '4.28.0', true);

    // Enqueue custom admin CSS
    wp_enqueue_style('harmonogram-admin-css', HARMONOGRAM_PLUGIN_URL . 'assets/css/admin.css', [], '1.0');

    // Enqueue custom admin JS
    wp_enqueue_script('harmonogram-admin-js', HARMONOGRAM_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], '1.0', true);

    // Lokalizacja skryptów dla admina
    wp_localize_script('harmonogram-admin-js', 'harmonogramAdmin', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('harmonogram_admin_nonce'),
    ]);
}
add_action('admin_enqueue_scripts', 'harmonogram_plugin_enqueue_admin_scripts');

/**
 * Rejestracja skryptów i stylów dla frontendu
 */
function harmonogram_plugin_enqueue_frontend_scripts() {
    // Enqueue Feather Icons
    wp_enqueue_script('feather-icons', 'https://unpkg.com/feather-icons', [], '4.28.0', true);

    // Enqueue jQuery UI CSS
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');

    // Enqueue custom frontend CSS
    wp_enqueue_style(
        'harmonogram-frontend-css',
        HARMONOGRAM_PLUGIN_URL . 'assets/css/harmonogram.css',
        [],
        filemtime(HARMONOGRAM_PLUGIN_DIR . 'assets/css/harmonogram.css')
    );

    // Enqueue jQuery UI JS
    wp_enqueue_script('jquery-ui-autocomplete');

    // Enqueue custom frontend JS z dynamiczną wersją
    wp_enqueue_script(
        'harmonogram-frontend-js',
        HARMONOGRAM_PLUGIN_URL . 'assets/js/harmonogram.js',
        ['jquery', 'jquery-ui-autocomplete'],
        filemtime(HARMONOGRAM_PLUGIN_DIR . 'assets/js/harmonogram.js'),
        true
    );

    // Lokalizacja skryptów dla frontendu
    wp_localize_script('harmonogram-frontend-js', 'harmonogramPlugin', [
        'ajax_url'            => admin_url('admin-ajax.php'),
        'nonce'               => wp_create_nonce('harmonogram_nonce'),
        'no_data_message'     => __('Brak danych do wyświetlenia.', 'harmonogram-plugin'),
        'fetch_error_message' => __('Wystąpił błąd podczas pobierania danych.', 'harmonogram-plugin'),
    ]);
}
add_action('wp_enqueue_scripts', 'harmonogram_plugin_enqueue_frontend_scripts');


/**
 * Rejestracja skryptów i stylów dla frontendu mobile
 */
function harmonogram_plugin_enqueue_frontend_mobile_scripts() {
    // Enqueue custom frontend mobile CSS
    wp_enqueue_style(
        'harmonogram-frontend-mobile-css',
        HARMONOGRAM_PLUGIN_URL . 'assets/css/harmonogram-mobile.css',
        ['harmonogram-frontend-css'],
        filemtime(HARMONOGRAM_PLUGIN_DIR . 'assets/css/harmonogram-mobile.css')
    );

    // Enqueue custom frontend mobile JS z dynamiczną wersją
    wp_enqueue_script(
        'harmonogram-frontend-mobile-js',
        HARMONOGRAM_PLUGIN_URL . 'assets/js/harmonogram-mobile.js',
        ['jquery'],
        filemtime(HARMONOGRAM_PLUGIN_DIR . 'assets/js/harmonogram-mobile.js'),
        true
    );

    // Lokalizacja skryptów dla frontendu mobile
    wp_localize_script('harmonogram-frontend-mobile-js', 'harmonogramMobile', [
        'ajax_url'            => admin_url('admin-ajax.php'),
        'nonce'               => wp_create_nonce('harmonogram_nonce'),
        'no_data_message'     => __('Brak danych do wyświetlenia.', 'harmonogram-plugin'),
        'fetch_error_message' => __('Wystąpił błąd podczas pobierania danych.', 'harmonogram-plugin'),
    ]);
}
add_action('wp_enqueue_scripts', 'harmonogram_plugin_enqueue_frontend_mobile_scripts', 11);

/**
 * Dodawanie menu administracyjnego
 */
function harmonogram_plugin_add_admin_menu() {
    add_menu_page(
        __('Harmonogram Odbioru Odpadów', 'harmonogram-plugin'),
        __('Harmonogram', 'harmonogram-plugin'),
        'manage_options',
        'harmonogram-plugin',
        'harmonogram_plugin_admin_page',
        'dashicons-calendar-alt',
        6
    );

    // Dodanie podmenu do wgrywania CSV
    add_submenu_page(
        'harmonogram-plugin',
        __('Wgrywanie CSV', 'harmonogram-plugin'),
        __('Wgrywanie CSV', 'harmonogram-plugin'),
        'manage_options',
        'harmonogram-plugin-csv-upload',
        'harmonogram_plugin_csv_upload_page'
    );
}
add_action('admin_menu', 'harmonogram_plugin_add_admin_menu');

/**
 * Funkcja renderująca główną stronę admina
 */
function harmonogram_plugin_admin_page() {
    echo '<div class="wrap">';
    echo '<h1>' . __('Harmonogram Odbioru Odpadów', 'harmonogram-plugin') . '</h1>';
    echo '<p>' . __('Zarządzaj harmonogramem odbioru odpadów.', 'harmonogram-plugin') . '</p>';
    echo '</div>';
}
?>

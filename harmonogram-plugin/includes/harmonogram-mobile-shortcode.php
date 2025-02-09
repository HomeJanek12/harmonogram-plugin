<?php
if (!defined('ABSPATH')) {
    exit; // Bezpośredni dostęp zabroniony
}

/**
 * Shortcode do wyświetlania harmonogramu (wersja mobile)
 * Użycie: [harmonogram_mobile]
 */
function harmonogram_mobile_shortcode() {
    // Pobranie listy miejscowości z JSON
    $locations = harmonogram_get_locations();

    ob_start();
    ?>
    <div class="harmonogram-dashboard">
        <!-- Filtry -->
        <div class="harmonogram-filters">
            <label for="harmonogram-mobile-location-filter">
                <?php _e('Wybierz swoją miejscowość', 'harmonogram-plugin'); ?>
            </label>
            <select id="harmonogram-mobile-location-filter" aria-label="<?php esc_attr_e('Wybierz swoją miejscowość', 'harmonogram-plugin'); ?>">
                <option value=""><?php _e('Wybierz miejscowość', 'harmonogram-plugin'); ?></option>
                <?php
                if (!empty($locations)) {
                    foreach ($locations as $loc) {
                        echo '<option value="' . esc_attr($loc) . '">' . esc_html($loc) . '</option>';
                    }
                } else {
                    echo '<option value="">' . __('Brak danych miejscowości', 'harmonogram-plugin') . '</option>';
                }
                ?>
            </select>
        </div>

        <!-- Sekcja Harmonogramu -->
        <div class="harmonogram-mobile-content" style="display:none;" role="region" aria-label="<?php esc_attr_e('Harmonogram', 'harmonogram-plugin'); ?>">
            <!-- Wiadomość Ładowania -->
            <div id="loading-message" style="display:none;"><?php esc_html_e('Ładowanie...', 'harmonogram-plugin'); ?></div>

            <!-- Lista Harmonogramu -->
            <ul class="harmonogram-mobile-list" role="list">
                <!-- Dodawane przez JS -->
            </ul>

            <!-- Nagłówek Miesiąca -->
            <div class="month-header-mobile" role="heading" aria-level="2"></div>

            <!-- Nawigacja Miesięczna -->
            <div class="month-navigation-mobile" role="navigation" aria-label="<?php esc_attr_e('Nawigacja miesięczna', 'harmonogram-plugin'); ?>"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('harmonogram_mobile', 'harmonogram_mobile_shortcode');

/**
 * Funkcja do pobierania listy miejscowości z pliku JSON
 */
if (!function_exists('harmonogram_get_locations')) {
    function harmonogram_get_locations() {
        $json_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy.json';
        if (!file_exists($json_file)) {
            return [];
        }

        $json_data = json_decode(file_get_contents($json_file), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Błąd JSON: ' . json_last_error_msg());
            return [];
        }

        $locations = array_unique(array_map(function ($item) {
            return $item['miejscowosc'] ?? '';
        }, $json_data));
        $locations = array_filter($locations);
        sort($locations);
        return $locations;
    }
}

/**
 * Rejestracja skryptów i stylów
 */
function harmonogram_enqueue_scripts() {
    wp_enqueue_script(
        'harmonogram-mobile-js',
        HARMONOGRAM_PLUGIN_URL . 'assets/js/harmonogram-mobile.js',
        array('jquery'),
        null,
        true
    );
    wp_localize_script('harmonogram-mobile-js', 'harmonogramMobile', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('harmonogram_nonce'),
    ));
    wp_enqueue_style(
        'harmonogram-mobile-css',
        HARMONOGRAM_PLUGIN_URL . 'assets/css/harmonogram-mobile.css',
        array(),
        null
    );
}
add_action('wp_enqueue_scripts', 'harmonogram_enqueue_scripts');

/**
 * AJAX: Pobieranie danych harmonogramu
 */
function harmonogram_get_data_ajax() {
    check_ajax_referer('harmonogram_nonce', 'nonce');

    $location = sanitize_text_field($_POST['location'] ?? '');
    $year     = intval($_POST['year'] ?? date('Y'));

    if (empty($location)) {
        wp_send_json_error(array('message' => 'Nie podano lokalizacji.'));
    }

    $json_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy.json';
    if (!file_exists($json_file)) {
        wp_send_json_error(array('message' => 'Brak pliku z danymi harmonogramu.'));
    }

    $json_data = json_decode(file_get_contents($json_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array('message' => 'Błąd w danych JSON (harmonogramy).'));
    }

    $filtered_data = array_filter($json_data, function ($item) use ($location, $year) {
        $item_year = (int) date('Y', strtotime($item['data']));
        return (strtolower($item['miejscowosc']) === strtolower($location)) && ($item_year === $year);
    });

    $formatted_data = array_map(function ($item) {
        return array(
            'data'           => $item['data'],
            'rodzaj_odpadow' => $item['rodzaj_odpadow'],
            'opis'           => $item['opis'],
        );
    }, $filtered_data);

    wp_send_json_success(array('formatted_data' => $formatted_data));
}
add_action('wp_ajax_harmonogram_get_data', 'harmonogram_get_data_ajax');
add_action('wp_ajax_nopriv_harmonogram_get_data', 'harmonogram_get_data_ajax');

/**
 * AJAX: Generowanie pliku iCal
 */
function harmonogram_generate_ical_ajax() {
    check_ajax_referer('harmonogram_nonce', 'nonce');

    $location = sanitize_text_field($_GET['location'] ?? '');
    $year     = intval($_GET['year'] ?? date('Y'));

    if (empty($location)) {
        wp_send_json_error(array('message' => 'Nie podano lokalizacji.'));
    }

    $json_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy.json';
    if (!file_exists($json_file)) {
        wp_send_json_error(array('message' => 'Brak pliku z danymi harmonogramu.'));
    }

    $json_data = json_decode(file_get_contents($json_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array('message' => 'Błąd w danych JSON (harmonogramy).'));
    }

    $filtered_data = array_filter($json_data, function ($item) use ($location, $year) {
        $item_year = (int) date('Y', strtotime($item['data']));
        return (strtolower($item['miejscowosc']) === strtolower($location)) && ($item_year === $year);
    });

    if (empty($filtered_data)) {
        wp_send_json_error(array('message' => 'Brak danych do wygenerowania pliku iCal.'));
    }

    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//Your Company//Harmonogram Plugin//PL\r\n";

    foreach ($filtered_data as $item) {
        $event_date = date('Ymd', strtotime($item['data']));
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . uniqid() . "@harmonogram-plugin\r\n";
        $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ical .= "DTSTART;VALUE=DATE:" . $event_date . "\r\n";
        $ical .= "SUMMARY:" . sanitize_text_field($item['rodzaj_odpadow']) . "\r\n";
        if (!empty($item['opis'])) {
            $ical .= "DESCRIPTION:" . sanitize_text_field($item['opis']) . "\r\n";
        }
        $ical .= "END:VEVENT\r\n";
    }

    $ical .= "END:VCALENDAR";

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename=harmonogram.ics');
    echo $ical;
    exit;
}
add_action('wp_ajax_harmonogram_generate_ical', 'harmonogram_generate_ical_ajax');
add_action('wp_ajax_nopriv_harmonogram_generate_ical', 'harmonogram_generate_ical_ajax');

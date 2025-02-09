<?php
if (!defined('ABSPATH')) {
    exit; // Wyjście, jeśli dostęp do pliku jest bezpośredni
}

/**
 * Pobieranie URL PDF dla danej miejscowości
 */
if (!function_exists('harmonogram_plugin_get_pdf_url')) {
    function harmonogram_plugin_get_pdf_url($location) {
        $pdf_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy_pdfs.json';
        if (!file_exists($pdf_file)) {
            error_log("harmonogram_plugin_get_pdf_url: Plik JSON nie istnieje: {$pdf_file}");
            return '';
        }

        $pdf_content = file_get_contents($pdf_file);
        if ($pdf_content === false) {
            error_log("harmonogram_plugin_get_pdf_url: Nie udało się odczytać pliku JSON: {$pdf_file}");
            return '';
        }

        $pdf_data = json_decode($pdf_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("harmonogram_plugin_get_pdf_url: Błąd w odczycie danych JSON: " . json_last_error_msg());
            return '';
        }

        error_log('harmonogram_plugin_get_pdf_url: Dane PDF JSON poprawnie odczytane.');

        $location_key = mb_strtolower($location, 'UTF-8');
        return isset($pdf_data[$location_key]) ? esc_url($pdf_data[$location_key]) : '';
    }
}

/**
 * Funkcja AJAX: Pobieranie danych harmonogramu
 */
if (!function_exists('harmonogram_get_data')) {
    function harmonogram_get_data() {
        error_log('harmonogram_get_data: Funkcja rozpoczęta.');

        // Sprawdzenie nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'harmonogram_nonce')) {
            error_log('harmonogram_get_data: Nieprawidłowy token bezpieczeństwa.');
            wp_send_json_error(array('message' => __('Nieprawidłowy token bezpieczeństwa.', 'harmonogram-plugin')));
        }
        error_log('harmonogram_get_data: Token bezpieczeństwa zweryfikowany.');

        // Pobranie i walidacja parametrów
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');
        error_log("harmonogram_get_data: Otrzymano dane - Lokalizacja: {$location}, Rok: {$year}");

        if (empty($location)) {
            error_log('harmonogram_get_data: Nie wybrano miejscowości.');
            wp_send_json_error(array('message' => __('Nie wybrano miejscowości.', 'harmonogram-plugin')));
        }

        // Ładowanie danych JSON
        $json_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy.json';
        if (!file_exists($json_file)) {
            error_log("harmonogram_get_data: Plik JSON nie istnieje: {$json_file}");
            wp_send_json_error(array('message' => __('Plik z danymi nie istnieje.', 'harmonogram-plugin')));
        }
        error_log("harmonogram_get_data: Plik JSON znaleziony: {$json_file}");

        $json_content = file_get_contents($json_file);
        if ($json_content === false) {
            error_log("harmonogram_get_data: Nie udało się odczytać pliku JSON: {$json_file}");
            wp_send_json_error(array('message' => __('Nie udało się odczytać pliku z danymi.', 'harmonogram-plugin')));
        }

        $json_data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("harmonogram_get_data: Błąd w odczycie danych JSON: " . json_last_error_msg());
            wp_send_json_error(array('message' => __('Błąd w odczycie danych JSON: ', 'harmonogram-plugin') . json_last_error_msg()));
        }
        error_log('harmonogram_get_data: Dane JSON poprawnie odczytane.');

        // Filtrowanie danych – użyj mb_strtolower zamiast strtolower
        $filtered_data = array_filter($json_data, function ($item) use ($location, $year) {
            return isset($item['miejscowosc'], $item['data']) &&
                   mb_strtolower($item['miejscowosc'], 'UTF-8') === mb_strtolower($location, 'UTF-8') &&
                   date('Y', strtotime($item['data'])) == $year;
        });
        error_log('harmonogram_get_data: Dane po filtracji: ' . count($filtered_data));

        if (empty($filtered_data)) {
            error_log('harmonogram_get_data: Brak danych po filtracji.');
            wp_send_json_error(array('message' => __('Brak danych dla wybranej miejscowości.', 'harmonogram-plugin')));
        }

        // Resetowanie kluczy tablicy
        $filtered_data = array_values($filtered_data);
        error_log('harmonogram_get_data: Klucze tablicy zresetowane.');

        // Pobranie URL PDF z osobnego pliku JSON
        $pdf_url = harmonogram_plugin_get_pdf_url($location);
        error_log("harmonogram_get_data: PDF URL: {$pdf_url}");

        // Przygotowanie danych – zadbaj o poprawne typy
        $formatted_data = array_map(function ($item) {
            return array(
                'rodzaj_odpadow' => sanitize_text_field($item['rodzaj_odpadow']),
                'data' => sanitize_text_field($item['data']),
                'kolor' => isset($item['kolor']) ? sanitize_hex_color($item['kolor']) : '#000000'
            );
        }, $filtered_data);
        error_log('harmonogram_get_data: Dane sformatowane.');

        // Wysyłka odpowiedzi
        $response = array(
            'formatted_data' => $formatted_data, // Zmiana nazwy klucza
            'pdf_url' => $pdf_url
        );

        error_log('harmonogram_get_data: Odpowiedź JSON: ' . json_encode($response));
        wp_send_json_success($response);
    }
}
add_action('wp_ajax_harmonogram_get_data', 'harmonogram_get_data');
add_action('wp_ajax_nopriv_harmonogram_get_data', 'harmonogram_get_data');

/**
 * AJAX handler dla wyszukiwania lokalizacji (Autouzupełnianie)
 */
if (!function_exists('harmonogram_search_locations')) {
    function harmonogram_search_locations() {
        error_log('harmonogram_search_locations: Funkcja rozpoczęta.');

        // Sprawdzenie nonce dla bezpieczeństwa
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'harmonogram_nonce')) {
            error_log('harmonogram_search_locations: Nieprawidłowy token bezpieczeństwa.');
            wp_send_json_error(array('message' => __('Nieprawidłowy token bezpieczeństwa.', 'harmonogram-plugin')));
        }
        error_log('harmonogram_search_locations: Token bezpieczeństwa zweryfikowany.');

        // Pobranie terminu wyszukiwania
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        error_log("harmonogram_search_locations: Otrzymano termin wyszukiwania: {$term}");

        if (strlen($term) < 3) {
            error_log('harmonogram_search_locations: Za mało znaków do wyszukiwania.');
            wp_send_json_error(array('message' => __('Za mało znaków do wyszukiwania.', 'harmonogram-plugin')));
        }

        // Pobranie listy lokalizacji
        $locations = harmonogram_get_locations();
        error_log('harmonogram_search_locations: Lista lokalizacji pobrana.');

        // Filtracja lokalizacji na podstawie terminu
        $matched = array_filter($locations, function($location) use ($term) {
            return stripos($location, $term) !== false;
        });
        error_log('harmonogram_search_locations: Dane po filtracji: ' . count($matched));

        // Ograniczenie wyników do 10
        $matched = array_slice($matched, 0, 10);

        // Przygotowanie danych do zwrotu
        $results = array_map(function($loc) {
            return ['label' => $loc, 'value' => $loc];
        }, $matched);

        error_log('harmonogram_search_locations: Dane do zwrotu przygotowane.');

        wp_send_json_success($results);
    }
}
add_action('wp_ajax_harmonogram_search_locations', 'harmonogram_search_locations');
add_action('wp_ajax_nopriv_harmonogram_search_locations', 'harmonogram_search_locations');

/**
 * Funkcja logująca dodatkowe błędy (opcjonalne)
 */
if (!function_exists('harmonogram_log_error')) {
    function harmonogram_log_error() {
        // Przykład: Logowanie błędu przesłanego z frontendu
        if (isset($_POST['error_message'])) {
            $error_message = sanitize_text_field($_POST['error_message']);
            error_log("harmonogram_log_error: {$error_message}");
        }
        wp_send_json_success();
    }
}
add_action('wp_ajax_harmonogram_log_error', 'harmonogram_log_error');
add_action('wp_ajax_nopriv_harmonogram_log_error', 'harmonogram_log_error');
?>

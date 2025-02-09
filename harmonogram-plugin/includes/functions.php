<?php
if (!defined('ABSPATH')) {
    exit; // Wyjście, jeśli dostęp do pliku jest bezpośredni
}

/**
 * Funkcja pobierająca URL PDF dla danej miejscowości
 */
if (!function_exists('harmonogram_plugin_get_pdf_url')) {
    function harmonogram_plugin_get_pdf_url($miejscowosc) {
        $pdf_json_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy_pdfs.json';
        if (file_exists($pdf_json_file)) {
            $pdf_data = json_decode(file_get_contents($pdf_json_file), true);
            if (json_last_error() === JSON_ERROR_NONE && isset($pdf_data[$miejscowosc])) {
                return esc_url($pdf_data[$miejscowosc]);
            } else {
                error_log("harmonogram_plugin_get_pdf_url: Błąd w odczycie danych PDF JSON lub brak wpisu dla miejscowości: {$miejscowosc}");
            }
        } else {
            error_log("harmonogram_plugin_get_pdf_url: Plik PDF JSON nie istnieje: {$pdf_json_file}");
        }
        return '';
    }
}

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
            return $item['miejscowosc'];
        }, $json_data));

        sort($locations);
        return $locations;
    }
}


?>

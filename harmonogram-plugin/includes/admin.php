<?php
if (!defined('ABSPATH')) {
    exit; // Wyjście, jeśli dostęp do pliku jest bezpośredni
}

// Definiowanie HARMONOGRAM_PLUGIN_DIR, jeśli nie jest już zdefiniowana
if (!defined('HARMONOGRAM_PLUGIN_DIR')) {
    define('HARMONOGRAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

/**
 * Funkcja do logowania debugów w panelu administracyjnym
 */
if (!function_exists('harmonogram_plugin_debug')) {
    function harmonogram_plugin_debug($message) {
        if (current_user_can('manage_options')) { // Tylko administratorzy widzą debugi
            echo '<pre style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">';
            print_r($message);
            echo '</pre>';
        }
        // Dodatkowo logujemy do pliku debug.log
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
}

/**
 * Funkcja wyświetlająca harmonogramy z podziałem na miejscowości
 */
if (!function_exists('harmonogram_plugin_display_harmonogram')) {
    function harmonogram_plugin_display_harmonogram() {
        $json_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy.json';
        $json_data = [];

        // Sprawdzenie, czy plik JSON istnieje
        if (file_exists($json_file)) {
            $json_contents = file_get_contents($json_file);

            // Logowanie zawartości pliku JSON
            harmonogram_plugin_debug('Zawartość pliku JSON:');
            harmonogram_plugin_debug($json_contents);

            $json_data = json_decode($json_contents, true);

            // Logowanie danych po dekodowaniu JSON
            harmonogram_plugin_debug('Dane JSON po dekodowaniu:');
            harmonogram_plugin_debug($json_data);

            if (json_last_error() !== JSON_ERROR_NONE) {
                echo '<p>' . __('Błąd w pliku JSON.', 'harmonogram-plugin') . '</p>';
                error_log('Błąd JSON: ' . json_last_error_msg());
                harmonogram_plugin_debug('Błąd JSON: ' . json_last_error_msg());
                return;
            }

            // Upewnij się, że $json_data jest tablicą
            if (!is_array($json_data)) {
                $json_data = [];
                harmonogram_plugin_debug('Dane JSON nie były tablicą. Inicjalizuję jako pustą tablicę.');
            }
        } else {
            echo '<p>' . __('Plik JSON nie istnieje.', 'harmonogram-plugin') . '</p>';
            harmonogram_plugin_debug('Plik JSON nie istnieje: ' . $json_file);
            return;
        }

        // Logowanie sprawdzenia, czy dane są puste
        harmonogram_plugin_debug('Sprawdzanie, czy dane JSON są puste: ' . (empty($json_data) ? 'Tak' : 'Nie'));

        if (!empty($json_data)) {
            // Grupowanie harmonogramów według miejscowości
            $grouped_data = [];
            foreach ($json_data as $entry) {
                if (isset($entry['miejscowosc'])) {
                    $grouped_data[$entry['miejscowosc']][] = $entry;
                } else {
                    harmonogram_plugin_debug('Brak klucza "miejscowosc" w wpisie:');
                    harmonogram_plugin_debug($entry);
                }
            }

            // Logowanie danych po grupowaniu
            harmonogram_plugin_debug('Dane po grupowaniu według miejscowości:');
            harmonogram_plugin_debug($grouped_data);

            // Wyświetlanie tabeli dla każdej miejscowości
            foreach ($grouped_data as $miejscowosc => $harmonogramy) {
                echo '<h3>' . esc_html($miejscowosc) . '</h3>';
                echo '<table class="widefat fixed" cellspacing="0">';
                echo '<thead><tr>';
                echo '<th>' . __('Data', 'harmonogram-plugin') . '</th>';
                echo '<th>' . __('Rodzaj Odpadów', 'harmonogram-plugin') . '</th>';
                echo '<th>' . __('Kolor', 'harmonogram-plugin') . '</th>';
                echo '<th>' . __('Akcje', 'harmonogram-plugin') . '</th>'; // Nowa kolumna na akcje
                echo '</tr></thead>';
                echo '<tbody>';

                foreach ($harmonogramy as $harmonogram) {
                    // Upewnij się, że wszystkie potrzebne klucze istnieją
                    $data = isset($harmonogram['data']) ? esc_html($harmonogram['data']) : '';
                    $rodzaj_odpadow = isset($harmonogram['rodzaj_odpadow']) ? esc_html($harmonogram['rodzaj_odpadow']) : '';
                    $kolor = isset($harmonogram['kolor']) ? esc_attr($harmonogram['kolor']) : '#000000';

                    // Link do usuwania harmonogramu
                    $delete_url = wp_nonce_url(
                        add_query_arg([
                            'action' => 'delete_harmonogram',
                            'miejscowosc' => urlencode($harmonogram['miejscowosc']),
                            'data' => urlencode($harmonogram['data']),
                            'rodzaj_odpadow' => urlencode($harmonogram['rodzaj_odpadow']),
                        ], admin_url('admin.php?page=harmonogram-plugin-csv-upload')),
                        'harmonogram_delete_nonce'
                    );

                    echo '<tr>';
                    echo '<td>' . $data . '</td>';
                    echo '<td>' . $rodzaj_odpadow . '</td>';
                    echo '<td><span style="background-color:' . $kolor . '; display: inline-block; width: 20px; height: 20px;"></span></td>';
                    echo '<td><a href="' . esc_url($delete_url) . '" class="button button-danger">' . __('Usuń', 'harmonogram-plugin') . '</a></td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
            }
        } else {
            echo '<p>' . __('Brak danych do wyświetlenia.', 'harmonogram-plugin') . '</p>';
            harmonogram_plugin_debug('Brak danych do wyświetlenia.');
        }
    }
}
/**
 * Funkcja obsługująca usuwanie harmonogramu
 */
if (
    isset($_GET['action']) && 
    $_GET['action'] === 'delete_harmonogram' && 
    isset($_GET['miejscowosc']) && 
    isset($_GET['data']) &&
    isset($_GET['rodzaj_odpadow']) && 
    isset($_GET['_wpnonce']) && 
    wp_verify_nonce($_GET['_wpnonce'], 'harmonogram_delete_nonce')
) {
    harmonogram_plugin_delete_harmonogram($_GET['miejscowosc'], $_GET['data'], $_GET['rodzaj_odpadow']);
}

if (!function_exists('harmonogram_plugin_delete_harmonogram')) {
    /**
     * Funkcja usuwająca harmonogram na podstawie miejscowości, daty i rodzaju odpadów
     */
    function harmonogram_plugin_delete_harmonogram($miejscowosc, $data, $rodzaj_odpadow) {
        $json_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy.json';
        if (file_exists($json_file)) {
            $json_data = json_decode(file_get_contents($json_file), true);
            if (is_array($json_data)) {
                // Filtrujemy harmonogram, usuwając wpisy pasujące do miejscowości, daty i rodzaju odpadów
                $filtered_data = array_filter($json_data, function($entry) use ($miejscowosc, $data, $rodzaj_odpadow) {
                    return !(
                        isset($entry['miejscowosc'], $entry['data'], $entry['rodzaj_odpadow']) &&
                        strtolower(trim($entry['miejscowosc'])) === strtolower(trim($miejscowosc)) &&
                        $entry['data'] === $data &&
                        strtolower(trim($entry['rodzaj_odpadow'])) === strtolower(trim($rodzaj_odpadow))
                    );
                });

                // Sprawdzenie, czy coś zostało usunięte
                if (count($filtered_data) < count($json_data)) {
                    // Zapisujemy zaktualizowany JSON
                    if (file_put_contents($json_file, json_encode(array_values($filtered_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        echo '<div class="updated"><p>' . __('Harmonogram został pomyślnie usunięty.', 'harmonogram-plugin') . '</p></div>';
                        harmonogram_plugin_debug('Harmonogram usunięty: ' . $miejscowosc . ', ' . $data . ', ' . $rodzaj_odpadow);
                    } else {
                        echo '<div class="error"><p>' . __('Błąd podczas usuwania harmonogramu.', 'harmonogram-plugin') . '</p></div>';
                        harmonogram_plugin_debug('Błąd podczas zapisu danych JSON po usunięciu.');
                    }
                } else {
                    echo '<div class="error"><p>' . __('Nie znaleziono harmonogramu do usunięcia.', 'harmonogram-plugin') . '</p></div>';
                    harmonogram_plugin_debug('Nie znaleziono harmonogramu do usunięcia: ' . $miejscowosc . ', ' . $data . ', ' . $rodzaj_odpadow);
                }
            } else {
                echo '<div class="error"><p>' . __('Błąd w pliku JSON.', 'harmonogram-plugin') . '</p></div>';
                harmonogram_plugin_debug('Błąd JSON podczas usuwania harmonogramu.');
            }
        } else {
            echo '<div class="error"><p>' . __('Plik JSON nie istnieje.', 'harmonogram-plugin') . '</p></div>';
            harmonogram_plugin_debug('Plik JSON nie istnieje podczas usuwania harmonogramu.');
        }
    }
}
?>

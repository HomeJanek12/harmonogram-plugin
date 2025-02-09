<?php
if (!defined('ABSPATH')) {
    exit; // Wyjście, jeśli dostęp do pliku jest bezpośredni
}

/**
 * Funkcja renderująca stronę wgrywania CSV
 */
function harmonogram_plugin_csv_upload_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Obsługa przesłania formularza
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Import CSV
        if (
            isset($_POST['action']) && 
            $_POST['action'] === 'import_csv' && 
            check_admin_referer('harmonogram_import_nonce', 'harmonogram_import_nonce_field')
        ) {
            harmonogram_plugin_handle_csv_upload();
        }
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Wgrywanie CSV do Harmonogramu', 'harmonogram-plugin'); ?></h1>

        <!-- Import CSV -->
        <h2><?php _e('Importuj Harmonogram z CSV', 'harmonogram-plugin'); ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('harmonogram_import_nonce', 'harmonogram_import_nonce_field'); ?>
            <input type="hidden" name="action" value="import_csv">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Plik CSV', 'harmonogram-plugin'); ?></th>
                    <td>
                        <input type="file" name="harmonogram_csv" accept=".csv" required />
                        <br><br>
                        <?php submit_button(__('Importuj', 'harmonogram-plugin'), 'primary', 'import_submit'); ?>
                    </td>
                </tr>
            </table>
        </form>

        <!-- Wyświetlanie Harmonogramów -->
        <h2><?php _e('Zaimportowane Harmonogramy', 'harmonogram-plugin'); ?></h2>
        <?php 
            // Wywołanie funkcji wyświetlającej harmonogramy
            harmonogram_plugin_display_harmonogram(); 
        ?>
    </div>
    <?php
}

/**
 * Funkcja obsługująca import pliku CSV
 */
function harmonogram_plugin_handle_csv_upload() {
    // Sprawdzenie, czy plik został przesłany
    if (!empty($_FILES['harmonogram_csv']['tmp_name'])) {
        $csv_file = $_FILES['harmonogram_csv']['tmp_name'];

        // Sprawdzenie, czy plik jest rzeczywiście plikiem CSV
        $file_type = mime_content_type($csv_file);
        if ($file_type !== 'text/plain' && $file_type !== 'text/csv' && $file_type !== 'application/vnd.ms-excel') {
            echo '<div class="error"><p>' . __('Nieprawidłowy typ pliku. Proszę przesłać plik CSV.', 'harmonogram-plugin') . '</p></div>';
            harmonogram_plugin_debug('Nieprawidłowy typ pliku: ' . $file_type);
            return;
        }

        // Odczyt CSV jako tablicy
        $csv_data = array_map('str_getcsv', file($csv_file));

        // Logowanie danych CSV
        harmonogram_plugin_debug('Dane CSV:');
        harmonogram_plugin_debug($csv_data);

        // Pobranie istniejącego JSON z harmonogramami
        $json_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy.json';
        $json_data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];

        // Logowanie danych JSON przed aktualizacją
        harmonogram_plugin_debug('Dane JSON przed aktualizacją:');
        harmonogram_plugin_debug($json_data);

        // Upewnij się, że $json_data jest tablicą
        if (!is_array($json_data)) {
            $json_data = [];
            harmonogram_plugin_debug('Dane JSON nie były tablicą. Inicjalizuję jako pustą tablicę.');
        }

        // Zabezpieczenie: upewnij się, że katalog 'data' istnieje
        if (!file_exists(HARMONOGRAM_PLUGIN_DIR . 'data')) {
            mkdir(HARMONOGRAM_PLUGIN_DIR . 'data', 0755, true);
            harmonogram_plugin_debug('Utworzono katalog data.');
        }

        // Inicjalizacja liczników
        $imported = 0;
        $duplicates = 0;
        $errors = 0;

        // Przygotowanie istniejących rekordów do szybkiego sprawdzania duplikatów
        $existing_records = [];
        foreach ($json_data as $entry) {
            if (isset($entry['miejscowosc'], $entry['data'], $entry['rodzaj_odpadow'])) {
                $key = strtolower(trim($entry['miejscowosc'])) . '|' . $entry['data'] . '|' . strtolower(trim($entry['rodzaj_odpadow']));
                $existing_records[$key] = true;
            }
        }

        // Sprawdzenie poprawności CSV
        foreach ($csv_data as $index => $row) {
            // Zakładamy, że pierwsza linia to nagłówki, pomijamy je
            if ($index === 0 && (strcasecmp($row[0], 'miejscowosc') === 0 || strcasecmp($row[0], 'miejscowość') === 0)) {
                continue;
            }

            if (count($row) >= 3) { // Zakładamy, że CSV ma 3 kolumny: miejscowość, data, rodzaj_odpadow
                $miejscowosc = sanitize_text_field($row[0]); // Miejscowość
                $data = sanitize_text_field($row[1]); // Data
                $rodzaj_odpadow = sanitize_text_field($row[2]); // Rodzaj odpadów

                // Walidacja daty
                if (!validateDate($data)) {
                    echo '<div class="error"><p>' . __('Nieprawidłowy format daty w wierszu ' . ($index + 1), 'harmonogram-plugin') . '</p></div>';
                    harmonogram_plugin_debug('Nieprawidłowy format daty w wierszu ' . ($index + 1) . ': ' . $data);
                    $errors++;
                    continue;
                }

                // Tworzenie unikalnego klucza do sprawdzania duplikatów
                $record_key = strtolower(trim($miejscowosc)) . '|' . $data . '|' . strtolower(trim($rodzaj_odpadow));

                if (isset($existing_records[$record_key])) {
                    // Duplikat
                    $duplicates++;
                    harmonogram_plugin_debug('Duplikat znaleziony w wierszu ' . ($index + 1) . ': ' . $record_key);
                    continue;
                }

                // Automatyczne przypisanie kolorów (opcjonalne)
                $colorMap = [
                    'Odpady Zmieszane' => '#000000',
                    'Szkło' => '#2C6E49',
                    'Tworzywa sztuczne, metale, opakowania wielomateriałowe' => '#FCA311',
                    'Papier' => '#1E88E5',
                    'Odpady Biodegradowalne' => '#603808',
                ];
                $kolor = isset($colorMap[$rodzaj_odpadow]) ? $colorMap[$rodzaj_odpadow] : '#000000';

                // Dodanie harmonogramu do JSON
                $json_data[] = [
                    'miejscowosc' => $miejscowosc,
                    'data' => $data,
                    'rodzaj_odpadow' => $rodzaj_odpadow,
                    'kolor' => $kolor,
                ];

                // Aktualizacja istniejących rekordów
                $existing_records[$record_key] = true;

                $imported++;
            } else {
                echo '<div class="error"><p>' . __('Nieprawidłowy format CSV w wierszu ' . ($index + 1), 'harmonogram-plugin') . '</p></div>';
                harmonogram_plugin_debug('Nieprawidłowy format CSV w wierszu ' . ($index + 1));
                $errors++;
            }
        }

        // Zapisanie zaktualizowanego JSON
        if (file_put_contents($json_file, json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo '<div class="updated"><p>' . sprintf(
                __('Harmonogram zaimportowany pomyślnie. Importowane rekordy: %d, Duplikaty: %d, Błędy: %d.', 'harmonogram-plugin'),
                $imported,
                $duplicates,
                $errors
            ) . '</p></div>';
            harmonogram_plugin_debug('Dane JSON zostały pomyślnie zapisane do pliku.');
            harmonogram_plugin_debug(sprintf('Import zakończony. Zaimportowane: %d, Duplikaty: %d, Błędy: %d.', $imported, $duplicates, $errors));
        } else {
            echo '<div class="error"><p>' . __('Błąd podczas zapisu harmonogramu.', 'harmonogram-plugin') . '</p></div>';
            harmonogram_plugin_debug('Błąd podczas zapisu danych JSON do pliku.');
        }
    } else {
        echo '<div class="error"><p>' . __('Nie wybrano pliku CSV.', 'harmonogram-plugin') . '</p></div>';
        harmonogram_plugin_debug('Nie wybrano pliku CSV do przesłania.');
    }
}

/**
 * Funkcja walidująca datę w formacie YYYY-MM-DD
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>

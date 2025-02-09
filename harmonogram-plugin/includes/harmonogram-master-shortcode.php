<?php
if (!defined('ABSPATH')) {
    exit; // Bezpośredni dostęp zabroniony
}

/**
 * Shortcode do wyświetlania harmonogramu (wersja desktop)
 */
function harmonogram_master_shortcode($atts) {
    // Atrybuty shortcode
    $atts = shortcode_atts(array(
        'location' => '',
    ), $atts, 'harmonogram_master');

    // Pobieranie listy miejscowości (funkcja pomocnicza)
    $locations = harmonogram_get_locations();

    ob_start();
    ?>
    <div class="harmonogram-container">
        <div class="harmonogram-controls">
            <div id="harmonogram-location-form">
                <label class="select-label" for="location-filter">
                    <?php esc_html_e('WYBIERZ SWOJĄ MIEJSCOWOŚĆ', 'harmonogram-plugin'); ?>
                </label>
                <div class="select-container">
                    <!-- Ikonka Feather Icons (aria-hidden) -->
                    <i data-feather="map-pin" aria-hidden="true"></i>
                    <input type="text" id="location-filter" placeholder="<?php esc_attr_e('Wpisz miejscowość', 'harmonogram-plugin'); ?>" autocomplete="off" aria-label="<?php esc_attr_e('Wpisz miejscowość', 'harmonogram-plugin'); ?>" />
                </div>
            </div>
        </div>

        <div id="loading-message" style="display: none;">
            <?php esc_html_e('Ładowanie...', 'harmonogram-plugin'); ?>
        </div>

        <div class="harmonogram-tabs" style="display: none;">
            <div class="harmonogram-tab-buttons" role="tablist" aria-label="<?php esc_attr_e('Nawigacja widoków harmonogramu', 'harmonogram-plugin'); ?>">
                <button class="tab-btn active" role="tab" aria-selected="true" data-tab="list" title="<?php esc_attr_e('Lista', 'harmonogram-plugin'); ?>">
                    <i data-feather="list" aria-hidden="true"></i>
                    <span class="screen-reader-text"><?php esc_html_e('Lista', 'harmonogram-plugin'); ?></span>
                </button>
                <button class="tab-btn" role="tab" aria-selected="false" data-tab="table" title="<?php esc_attr_e('Tabela', 'harmonogram-plugin'); ?>">
                    <i data-feather="grid" aria-hidden="true"></i>
                    <span class="screen-reader-text"><?php esc_html_e('Tabela', 'harmonogram-plugin'); ?></span>
                </button>
                <button class="tab-btn" role="tab" aria-selected="false" data-tab="calendar" title="<?php esc_attr_e('Kalendarz', 'harmonogram-plugin'); ?>">
                    <i data-feather="calendar" aria-hidden="true"></i>
                    <span class="screen-reader-text"><?php esc_html_e('Kalendarz', 'harmonogram-plugin'); ?></span>
                </button>
            </div>
            <div class="harmonogram-tab-content">
                <div id="harmonogram-list" class="tab-content" role="tabpanel" aria-labelledby="tab-list" style="display: block;"></div>
                <div id="harmonogram-table" class="tab-content" role="tabpanel" aria-labelledby="tab-table" style="display: none;">
                    <table id="harmonogram-table-content" class="harmonogram-table">
                        <caption><?php esc_html_e('Tabela harmonogramu odbioru odpadów', 'harmonogram-plugin'); ?></caption>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Rodzaj Odpadów', 'harmonogram-plugin'); ?></th>
                                <?php
                                for ($m = 1; $m <= 12; $m++) {
                                    echo '<th scope="col">' . esc_html(strtoupper(date_i18n('M', mktime(0, 0, 0, $m, 10)))) . '</th>';
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="harmonogram-calendar" class="tab-content" role="tabpanel" aria-labelledby="tab-calendar" style="display: none;">
                    <div id="custom-calendar" role="grid" aria-label="<?php esc_attr_e('Kalendarz harmonogramu odbioru odpadów', 'harmonogram-plugin'); ?>"></div>
                </div>
            </div>
        </div>

        <div class="harmonogram-downloads" id="harmonogram-downloads" style="display: none;">
            <a href="#" id="harmonogram-ical" class="harmonogram-download-link" target="_blank" aria-label="<?php esc_attr_e('Pobierz iCal harmonogramu', 'harmonogram-plugin'); ?>">
                <i data-feather="calendar" aria-hidden="true"></i>
                <span class="screen-reader-text"><?php esc_html_e('Pobierz iCal', 'harmonogram-plugin'); ?></span>
            </a>
            <a href="#" id="harmonogram-pdf" class="harmonogram-download-link" target="_blank" style="display: none;" aria-label="<?php esc_attr_e('Pobierz PDF harmonogramu', 'harmonogram-plugin'); ?>">
                <i data-feather="file-text" aria-hidden="true"></i>
                <span class="screen-reader-text"><?php esc_html_e('Pobierz PDF', 'harmonogram-plugin'); ?></span>
            </a>
        </div>

        <div class="harmonogram-messages" style="display: none;">
            <ul>
                <li><?php esc_html_e('Odpady należy wystawić przed teren posesji (z pergoli śmietnikowych również) do godziny 7:00.', 'harmonogram-plugin'); ?></li>
                <li><?php esc_html_e('Odpady BIO miękkie (trawa, liście, trociny, odpadki warzywne i owocowe) należy gromadzić w stanie wolnym, bez opakowań, wyłącznie w pojemnikach (nie w workach) koloru brązowego.', 'harmonogram-plugin'); ?></li>
                <li><?php esc_html_e('Odpady BIO twarde (gałęzie) należy oddać do PSZOK - baza PGK w Bładzkowie, ul. Pucka 24.', 'harmonogram-plugin'); ?></li>
                <li><?php esc_html_e('Dopuszczalna wielkość pojemników do odpadów: 120 l i 240 l. Pojemniki muszą posiadać normę PN-EN 840-1. Pojemniki i worki muszą być zgodne z kolorystyką ustalaną w Rozporządzeniu Ministra Klimatu i Środowiska.', 'harmonogram-plugin'); ?></li>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('harmonogram_master', 'harmonogram_master_shortcode');

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
            return isset($item['miejscowosc']) ? sanitize_text_field($item['miejscowosc']) : '';
        }, $json_data));

        $locations = array_filter($locations);
        sort($locations);
        return $locations;
    }
}
?>

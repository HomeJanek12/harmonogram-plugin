<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('harmonogram_plugin_shortcode')) {
    function harmonogram_plugin_shortcode($atts) {
        ob_start();
        ?>
        <form id="location-form">
            <label for="location"><?php _e('Wybierz miejscowość:', 'harmonogram-plugin'); ?></label>
            <select id="location" name="location">
                <option value=""><?php _e('Wybierz', 'harmonogram-plugin'); ?></option>
                <?php
                $json_file = HARMONOGRAM_PLUGIN_DIR . 'data/harmonogramy.json';
                if (file_exists($json_file)) {
                    $json_data = json_decode(file_get_contents($json_file), true);
                    $locations = array_unique(array_column($json_data, 'miejscowosc'));
                    foreach ($locations as $location) {
                        echo '<option value="' . esc_attr($location) . '">' . esc_html($location) . '</option>';
                    }
                }
                ?>
            </select>
        </form>
        <div id="calendar-container">
            <div id="calendar"></div>
        </div>
        <div id="list-view-container">
            <div id="list-view"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('harmonogram', 'harmonogram_plugin_shortcode');

catch (error) {
    console.error('Błąd:', error);
    loader.style.display = 'none';
    
    // Wysyłanie błędu do serwera
    fetch(harmonogramPlugin.ajax_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: new URLSearchParams({
            action: 'harmonogram_log_error',
            message: error.message,
            location: locationSelect.value || 'Nieznana lokalizacja',
            timestamp: new Date().toISOString()
        })
    }).then(response => response.json())
      .then(data => {
          console.log('Błąd zapisany do logów:', data);
      }).catch(logError => {
          console.error('Nie udało się zapisać błędu do logów:', logError);
      });

    // Pokaż komunikat użytkownikowi
    alert(harmonogramPlugin.fetch_error_message);
}


?>
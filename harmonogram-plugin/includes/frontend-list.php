<?php
// Sprawdzenie, czy plik jest wywoływany poprzez WordPress
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="view-switcher" role="navigation" aria-label="<?php esc_attr_e('Przełączanie widoku', 'harmonogram-plugin'); ?>">
    <button data-view="calendar" aria-pressed="false"><?php _e('Kalendarz', 'harmonogram-plugin'); ?></button>
    <button data-view="list" class="active" aria-pressed="true"><?php _e('Lista', 'harmonogram-plugin'); ?></button>
</div>

<div id="list-view" role="region" aria-label="<?php esc_attr_e('Widok listy harmonogramów', 'harmonogram-plugin'); ?>"></div>
<div id="calendar" style="display: none;" role="region" aria-label="<?php esc_attr_e('Widok kalendarza harmonogramu', 'harmonogram-plugin'); ?>"></div>

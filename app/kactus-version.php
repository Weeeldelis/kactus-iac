<?php
/* Affiche la version de l'image en bas de chaque page */
add_action('wp_footer', function () {
    echo '<p style="text-align:center">Kactus version : '
        . esc_html(getenv('KACTUS_VERSION') ?: 'inconnue') . '</p>';
});

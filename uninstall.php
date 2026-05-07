<?php
/**
 * Uninstall script – csak akkor fut, ha a felhasználó TÖRLI a plugint.
 *
 * @package AIE
 */

// Csak WP eltávolítási folyamat hívhatja
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Beállítások törlése
delete_option( 'aie_settings' );

// Site-level (multisite esetén)
if ( is_multisite() ) {
    delete_site_option( 'aie_settings' );
}

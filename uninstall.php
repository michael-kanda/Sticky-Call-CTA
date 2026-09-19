<?php
/**
 * Aufraeumen beim Loeschen des Plugins.
 *
 * Geloescht wird nur, wenn das in den Einstellungen ausdruecklich aktiviert
 * wurde. Sonst bleiben Standorte und Seitenzuordnungen bei einem Neuaufsetzen
 * erhalten.
 *
 * @package DSGN_Sticky_Call
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Daten einer einzelnen Site entfernen.
 *
 * @return void
 */
function dsgn_scc_uninstall_site() {
	$settings = get_option( 'dsgn_scc_settings', array() );

	if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
		return;
	}

	delete_option( 'dsgn_scc_settings' );
	delete_post_meta_by_key( '_dsgn_scc_location' );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		dsgn_scc_uninstall_site();
		restore_current_blog();
	}
} else {
	dsgn_scc_uninstall_site();
}

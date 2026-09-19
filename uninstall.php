<?php
/**
 * Aufraeumen beim Loeschen des Plugins.
 *
 * Geloescht wird nur, wenn das in den Einstellungen ausdruecklich aktiviert
 * wurde. Sonst bleiben Standorte, Seitenzuordnungen und Zaehlerstaende bei
 * einem Neuaufsetzen erhalten.
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
	global $wpdb;

	$settings = get_option( 'dsgn_scc_settings', array() );

	if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
		return;
	}

	delete_option( 'dsgn_scc_settings' );
	delete_option( 'dsgn_scc_db_version' );
	delete_post_meta_by_key( '_dsgn_scc_location' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- eigene Tabelle, das Entfernen beim Deinstallieren ist genau der Zweck dieser Datei.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'dsgn_scc_clicks' ) );
}

/**
 * Deinstallation ausfuehren, bei Multisite fuer jede Site.
 *
 * Die Schleife steckt bewusst in einer Funktion, damit keine globalen
 * Variablen entstehen.
 *
 * @return void
 */
function dsgn_scc_uninstall() {
	if ( ! is_multisite() ) {
		dsgn_scc_uninstall_site();
		return;
	}

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
}

dsgn_scc_uninstall();

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

	global $wpdb;

	delete_option( 'dsgn_scc_settings' );
	delete_option( 'dsgn_scc_db_version' );
	delete_post_meta_by_key( '_dsgn_scc_location' );

	$table = $wpdb->prefix . 'dsgn_scc_clicks';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- eigene Tabelle, Name aus $wpdb->prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
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

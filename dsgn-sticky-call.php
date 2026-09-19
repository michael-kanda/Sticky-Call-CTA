<?php
/**
 * Plugin Name:       Sticky Call CTA
 * Plugin URI:        https://designare.at/
 * Description:       Mobiler Sticky-CTA-Button mit Telefonnummer. Nummern werden zentral als Standorte gepflegt und per Seite oder URL-Regel zugeordnet. Klicks werden als GA4-Event gemeldet.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Michael Kanda
 * Author URI:        https://designare.at/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dsgn-sticky-call
 * Domain Path:       /languages
 *
 * @package DSGN_Sticky_Call
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DSGN_SCC_VERSION', '1.2.0' );
define( 'DSGN_SCC_FILE', __FILE__ );
define( 'DSGN_SCC_PATH', plugin_dir_path( __FILE__ ) );
define( 'DSGN_SCC_URL', plugin_dir_url( __FILE__ ) );
define( 'DSGN_SCC_OPTION', 'dsgn_scc_settings' );
define( 'DSGN_SCC_META_KEY', '_dsgn_scc_location' );

require_once DSGN_SCC_PATH . 'includes/class-dsgn-scc-resolver.php';
require_once DSGN_SCC_PATH . 'includes/class-dsgn-scc-stats.php';
require_once DSGN_SCC_PATH . 'includes/class-dsgn-scc-settings.php';
require_once DSGN_SCC_PATH . 'includes/class-dsgn-scc-metabox.php';
require_once DSGN_SCC_PATH . 'includes/class-dsgn-scc-frontend.php';

/**
 * Standardwerte der Plugin-Einstellungen.
 *
 * @return array
 */
function dsgn_scc_get_defaults() {
	return array(
		'enabled'            => 1,
		'label'              => __( 'Jetzt anrufen', 'dsgn-sticky-call' ),
		'show_number'        => 1,
		'show_location'      => 0,
		'breakpoint'         => 768,
		'bg_color'           => '#1a73e8',
		'text_color'         => '#ffffff',
		'z_index'            => 9000,
		'offset_body'        => 1,
		'country_prefix'     => '+43',
		'default_location'   => '',
		'locations'          => array(),
		'rules'              => array(),
		'ga4_enabled'         => 1,
		'ga4_event'           => 'phone_call_click',
		'ga4_generate_lead'   => 0,
		'ga4_require_consent' => 0,
		'ga4_human_only'      => 1,
		'ga4_min_dwell'       => 800,
		'ga4_once_per_view'   => 1,
		'stats_enabled'       => 1,
		'delete_data'         => 0,
	);
}

/**
 * Einstellungen inklusive Defaults auslesen.
 *
 * @return array
 */
function dsgn_scc_get_settings() {
	$stored = get_option( DSGN_SCC_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	$settings = wp_parse_args( $stored, dsgn_scc_get_defaults() );

	if ( ! is_array( $settings['locations'] ) ) {
		$settings['locations'] = array();
	}

	if ( ! is_array( $settings['rules'] ) ) {
		$settings['rules'] = array();
	}

	/**
	 * Erlaubt das Überschreiben der Einstellungen zur Laufzeit.
	 *
	 * @param array $settings Einstellungen.
	 */
	return apply_filters( 'dsgn_scc_settings', $settings );
}

/**
 * Wandelt eine eingegebene Telefonnummer in einen tel:-tauglichen Wert um.
 *
 * @param string $number Eingegebene Nummer.
 * @param string $prefix Landesvorwahl, z. B. "+43".
 * @return string Nummer in E.164-Näherung oder leerer String.
 */
function dsgn_scc_to_tel( $number, $prefix = '' ) {
	$number = trim( (string) $number );

	if ( '' === $number ) {
		return '';
	}

	$has_plus = ( 0 === strpos( $number, '+' ) );
	$digits   = preg_replace( '/[^0-9]/', '', $number );

	if ( '' === $digits ) {
		return '';
	}

	if ( $has_plus ) {
		return '+' . $digits;
	}

	if ( 0 === strpos( $digits, '00' ) ) {
		return '+' . substr( $digits, 2 );
	}

	$prefix_digits = preg_replace( '/[^0-9]/', '', (string) $prefix );

	if ( '' !== $prefix_digits && 0 === strpos( $digits, '0' ) ) {
		return '+' . $prefix_digits . substr( $digits, 1 );
	}

	return $digits;
}

/**
 * Liefert einen Standort anhand seiner ID.
 *
 * @param string $location_id Standort-ID.
 * @param array  $settings    Einstellungen.
 * @return array|false
 */
function dsgn_scc_get_location( $location_id, $settings = null ) {
	if ( null === $settings ) {
		$settings = dsgn_scc_get_settings();
	}

	if ( '' === $location_id ) {
		return false;
	}

	foreach ( $settings['locations'] as $location ) {
		if ( isset( $location['id'] ) && $location['id'] === $location_id ) {
			return $location;
		}
	}

	return false;
}

/**
 * Post-Types, für die die Metabox angeboten wird.
 *
 * @return array
 */
function dsgn_scc_get_supported_post_types() {
	$post_types = get_post_types(
		array(
			'public'  => true,
			'show_ui' => true,
		),
		'names'
	);

	unset( $post_types['attachment'] );

	/**
	 * Post-Types mit Auswahlfeld für den Standort.
	 *
	 * @param array $post_types Post-Type-Namen.
	 */
	return apply_filters( 'dsgn_scc_supported_post_types', array_values( $post_types ) );
}

/**
 * Plugin initialisieren.
 *
 * @return void
 */
function dsgn_scc_init() {
	load_plugin_textdomain( 'dsgn-sticky-call', false, dirname( plugin_basename( DSGN_SCC_FILE ) ) . '/languages' );

	DSGN_SCC_Stats::init();
	DSGN_SCC_Settings::init();
	DSGN_SCC_Metabox::init();
	DSGN_SCC_Frontend::init();
}
add_action( 'plugins_loaded', 'dsgn_scc_init' );

/**
 * Bei der Aktivierung die Zaehlertabelle anlegen.
 *
 * @return void
 */
function dsgn_scc_activate() {
	DSGN_SCC_Stats::install();
}
register_activation_hook( __FILE__, 'dsgn_scc_activate' );

/**
 * Bei der Deaktivierung den Aufraeum-Cron entfernen.
 *
 * @return void
 */
function dsgn_scc_deactivate() {
	$timestamp = wp_next_scheduled( DSGN_SCC_Stats::CRON_HOOK );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, DSGN_SCC_Stats::CRON_HOOK );
	}
}
register_deactivation_hook( __FILE__, 'dsgn_scc_deactivate' );

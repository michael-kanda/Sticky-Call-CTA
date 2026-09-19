<?php
/**
 * Aggregierte Klickzaehlung je Standort.
 *
 * Gespeichert wird ausschliesslich aggregiert: Standort, Tag, Seiten-ID und
 * eine Anzahl. Kein Cookie, keine IP, keine Kennung eines Besuchers. Die
 * Zaehlung ist damit unabhaengig vom Consent-Status, der Versand an GA4
 * dagegen nicht.
 *
 * @package DSGN_Sticky_Call
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DSGN_SCC_Stats
 */
class DSGN_SCC_Stats {

	/**
	 * Schema-Version der eigenen Tabelle.
	 *
	 * @var string
	 */
	const DB_VERSION = '1';

	/**
	 * Option, in der die Schema-Version steht.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'dsgn_scc_db_version';

	/**
	 * Name des Cron-Events zum Aufraeumen.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'dsgn_scc_prune_stats';

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'prune' ) );
	}

	/**
	 * Tabellenname inklusive Praefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'dsgn_scc_clicks';
	}

	/**
	 * Tabelle anlegen, wenn sie fehlt oder veraltet ist.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::install();
	}

	/**
	 * Tabelle anlegen und Cron einplanen.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			location_id varchar(32) NOT NULL DEFAULT '',
			stat_day date NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			clicks bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY location_day_post (location_id, stat_day, post_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * REST-Route zum Zaehlen registrieren.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'dsgn-scc/v1',
			'/click',
			array(
				'methods'  => 'POST',
				'callback' => array( __CLASS__, 'handle_click' ),
				/*
				 * Bewusst oeffentlich: der Endpunkt erhoeht nur einen
				 * aggregierten Zaehler und liest nichts aus. Eine Nonce waere
				 * hier unbrauchbar, weil die Seiten im Full-Page-Cache liegen
				 * und die Nonce darin ablaufen wuerde. Missbrauch begrenzen
				 * die Pruefung der Standort-ID und die Ratenbegrenzung.
				 */
				'permission_callback' => '__return_true',
				'args'                => array(
					'location_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'post_id'     => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Klick verarbeiten.
	 *
	 * @param WP_REST_Request $request Anfrage.
	 * @return WP_REST_Response
	 */
	public static function handle_click( $request ) {
		$settings = dsgn_scc_get_settings();

		if ( empty( $settings['stats_enabled'] ) ) {
			return new WP_REST_Response( array( 'counted' => false ), 200 );
		}

		$location_id = (string) $request->get_param( 'location_id' );

		if ( ! dsgn_scc_get_location( $location_id, $settings ) ) {
			return new WP_REST_Response( array( 'counted' => false ), 200 );
		}

		if ( ! self::rate_limit_ok() ) {
			return new WP_REST_Response( array( 'counted' => false ), 200 );
		}

		self::record( $location_id, absint( $request->get_param( 'post_id' ) ) );

		return new WP_REST_Response( array( 'counted' => true ), 200 );
	}

	/**
	 * Einfache Ratenbegrenzung.
	 *
	 * Der Schluessel ist ein Hash der IP-Adresse und lebt nur wenige Minuten
	 * als Transient. Die Adresse selbst wird nicht gespeichert.
	 *
	 * @return bool
	 */
	protected static function rate_limit_ok() {
		/**
		 * Maximale Zaehlvorgaenge pro Fenster. 0 schaltet die Begrenzung ab.
		 *
		 * @param int $max Maximale Anzahl.
		 */
		$max = (int) apply_filters( 'dsgn_scc_rate_limit', 10 );

		if ( $max < 1 ) {
			return true;
		}

		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( '' === $address ) {
			return true;
		}

		$key   = 'dsgn_scc_rl_' . md5( $address . wp_salt() );
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return false;
		}

		set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Zaehler erhoehen.
	 *
	 * @param string $location_id Standort-ID.
	 * @param int    $post_id     Seiten-ID oder 0.
	 * @return void
	 */
	public static function record( $location_id, $post_id = 0 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- eigene Tabelle, Zaehler-Update ohne Cache-Nutzen.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i ( location_id, stat_day, post_id, clicks ) VALUES ( %s, %s, %d, 1 ) ON DUPLICATE KEY UPDATE clicks = clicks + 1',
				self::table_name(),
				$location_id,
				current_time( 'Y-m-d' ),
				$post_id
			)
		);
	}

	/**
	 * Zusammenfassung je Standort.
	 *
	 * @param int $days Zeitraum fuer den zweiten Wert in Tagen.
	 * @return array Standort-ID => array( 'total' => int, 'recent' => int ).
	 */
	public static function get_summary( $days = 30 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d', time() - ( absint( $days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- eigene Tabelle, Auswertung nur im Admin.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT location_id, SUM( clicks ) AS total, SUM( CASE WHEN stat_day >= %s THEN clicks ELSE 0 END ) AS recent FROM %i GROUP BY location_id',
				$since,
				self::table_name()
			),
			ARRAY_A
		);

		$summary = array();

		if ( ! is_array( $rows ) ) {
			return $summary;
		}

		foreach ( $rows as $row ) {
			$summary[ $row['location_id'] ] = array(
				'total'  => (int) $row['total'],
				'recent' => (int) $row['recent'],
			);
		}

		return $summary;
	}

	/**
	 * Alte Zeilen entfernen.
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;

		/**
		 * Aufbewahrungsdauer der Zaehlerzeilen in Tagen.
		 *
		 * @param int $days Anzahl Tage.
		 */
		$days = absint( apply_filters( 'dsgn_scc_retention_days', 400 ) );

		if ( $days < 1 ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- eigene Tabelle, geplante Aufraeumaufgabe.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE stat_day < %s',
				self::table_name(),
				$cutoff
			)
		);
	}
}

<?php
/**
 * Ermittelt den passenden Standort für den aktuellen Request.
 *
 * @package DSGN_Sticky_Call
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DSGN_SCC_Resolver
 */
class DSGN_SCC_Resolver {

	/**
	 * Zwischenspeicher für den aufgelösten Standort.
	 *
	 * @var array|false|null
	 */
	protected static $resolved = null;

	/**
	 * Liefert den anzuzeigenden Standort oder false.
	 *
	 * Reihenfolge: Auswahl am Beitrag > URL-Regel > Standardstandort.
	 *
	 * @return array|false
	 */
	public static function resolve() {
		if ( null !== self::$resolved ) {
			return self::$resolved;
		}

		self::$resolved = self::determine();

		/**
		 * Erlaubt das Überschreiben des ermittelten Standorts.
		 *
		 * @param array|false $location Standort-Array oder false.
		 */
		self::$resolved = apply_filters( 'dsgn_scc_resolved_location', self::$resolved );

		return self::$resolved;
	}

	/**
	 * Eigentliche Auflösungslogik.
	 *
	 * @return array|false
	 */
	protected static function determine() {
		$settings = dsgn_scc_get_settings();

		if ( empty( $settings['enabled'] ) || empty( $settings['locations'] ) ) {
			return false;
		}

		if ( is_admin() || is_feed() || is_embed() || is_404() ) {
			return false;
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$choice  = $post_id ? (string) get_post_meta( $post_id, DSGN_SCC_META_KEY, true ) : '';

			if ( 'off' === $choice ) {
				return false;
			}

			if ( '' !== $choice && 'inherit' !== $choice ) {
				$location = dsgn_scc_get_location( $choice, $settings );

				if ( $location ) {
					return self::prepare( $location, $settings, 'post' );
				}
			}
		}

		$rule_location_id = self::match_rules( $settings['rules'], self::get_request_path() );

		if ( '' !== $rule_location_id ) {
			if ( 'off' === $rule_location_id ) {
				return false;
			}

			$location = dsgn_scc_get_location( $rule_location_id, $settings );

			if ( $location ) {
				return self::prepare( $location, $settings, 'rule' );
			}
		}

		$location = dsgn_scc_get_location( (string) $settings['default_location'], $settings );

		if ( $location ) {
			return self::prepare( $location, $settings, 'default' );
		}

		return false;
	}

	/**
	 * Ergänzt den Standort um berechnete Werte.
	 *
	 * @param array  $location Standortdaten.
	 * @param array  $settings Einstellungen.
	 * @param string $source   Woher die Zuordnung stammt.
	 * @return array|false
	 */
	protected static function prepare( $location, $settings, $source ) {
		$location = wp_parse_args(
			$location,
			array(
				'id'       => '',
				'label'    => '',
				'number'   => '',
				'ga_label' => '',
			)
		);

		$tel = dsgn_scc_to_tel( $location['number'], $settings['country_prefix'] );

		if ( '' === $tel ) {
			return false;
		}

		$location['tel']      = $tel;
		$location['source']   = $source;
		$location['ga_label'] = '' !== $location['ga_label'] ? $location['ga_label'] : $location['label'];

		return $location;
	}

	/**
	 * Pfad des aktuellen Requests, relativ zum WordPress-Root, in Kleinbuchstaben.
	 *
	 * @return string
	 */
	public static function get_request_path() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = '/' === $home_path ? '' : untrailingslashit( $home_path );

		if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		$path = '/' . ltrim( strtolower( $path ), '/' );

		return $path;
	}

	/**
	 * Prüft alle Regeln gegen den Pfad und liefert die erste passende Standort-ID.
	 *
	 * @param array  $rules Regeln.
	 * @param string $path  Request-Pfad.
	 * @return string Standort-ID, 'off' oder leerer String.
	 */
	protected static function match_rules( $rules, $path ) {
		foreach ( $rules as $rule ) {
			if ( empty( $rule['pattern'] ) || empty( $rule['location'] ) ) {
				continue;
			}

			if ( self::pattern_matches( $rule['pattern'], $path ) ) {
				return (string) $rule['location'];
			}
		}

		return '';
	}

	/**
	 * Vergleicht ein Muster mit Platzhalter (*) gegen einen Pfad.
	 *
	 * @param string $pattern Muster, z. B. /wien/*.
	 * @param string $path    Request-Pfad.
	 * @return bool
	 */
	public static function pattern_matches( $pattern, $path ) {
		$pattern = strtolower( trim( (string) $pattern ) );

		if ( '' === $pattern ) {
			return false;
		}

		$pattern = '/' . ltrim( $pattern, '/' );
		$regex   = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#';

		$candidates = array( $path, untrailingslashit( $path ), trailingslashit( $path ) );

		foreach ( array_unique( $candidates ) as $candidate ) {
			if ( '' === $candidate ) {
				$candidate = '/';
			}

			if ( preg_match( $regex, $candidate ) ) {
				return true;
			}
		}

		return false;
	}
}

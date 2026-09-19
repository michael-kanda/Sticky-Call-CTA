<?php
/**
 * Einstellungsseite des Plugins.
 *
 * @package DSGN_Sticky_Call
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DSGN_SCC_Settings
 */
class DSGN_SCC_Settings {

	/**
	 * Slug der Einstellungsseite.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'dsgn-sticky-call';

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DSGN_SCC_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Link zur Einstellungsseite in der Plugin-Liste.
	 *
	 * @param array $links Bestehende Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Einstellungen', 'dsgn-sticky-call' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Menüeintrag anlegen.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'Sticky Call CTA', 'dsgn-sticky-call' ),
			__( 'Sticky Call CTA', 'dsgn-sticky-call' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Option registrieren.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			'dsgn_scc_options',
			DSGN_SCC_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => dsgn_scc_get_defaults(),
			)
		);
	}

	/**
	 * Admin-Assets nur auf der eigenen Seite laden.
	 *
	 * @param string $hook_suffix Aktueller Admin-Screen.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'dsgn-scc-admin',
			DSGN_SCC_URL . 'assets/css/admin.css',
			array(),
			DSGN_SCC_VERSION
		);

		wp_enqueue_script(
			'dsgn-scc-admin',
			DSGN_SCC_URL . 'assets/js/admin.js',
			array(),
			DSGN_SCC_VERSION,
			true
		);

		wp_localize_script(
			'dsgn-scc-admin',
			'dsgnSccAdmin',
			array(
				'confirmRemove' => __( 'Diesen Eintrag wirklich entfernen?', 'dsgn-sticky-call' ),
			)
		);
	}

	/**
	 * Eingaben prüfen und bereinigen.
	 *
	 * @param mixed $input Rohdaten aus dem Formular.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = dsgn_scc_get_defaults();
		$clean    = $defaults;

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		$checkboxes = array( 'enabled', 'show_number', 'show_location', 'offset_body', 'ga4_enabled', 'ga4_generate_lead', 'ga4_require_consent', 'ga4_human_only', 'ga4_once_per_view', 'stats_enabled', 'delete_data' );

		foreach ( $checkboxes as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$dwell                  = isset( $input['ga4_min_dwell'] ) ? absint( $input['ga4_min_dwell'] ) : $defaults['ga4_min_dwell'];
		$clean['ga4_min_dwell'] = min( 10000, $dwell );

		$clean['label']          = isset( $input['label'] ) ? sanitize_text_field( $input['label'] ) : $defaults['label'];
		$clean['country_prefix'] = isset( $input['country_prefix'] ) ? sanitize_text_field( $input['country_prefix'] ) : $defaults['country_prefix'];

		$breakpoint          = isset( $input['breakpoint'] ) ? absint( $input['breakpoint'] ) : $defaults['breakpoint'];
		$clean['breakpoint'] = min( 2000, max( 320, $breakpoint ) );

		$z_index          = isset( $input['z_index'] ) ? absint( $input['z_index'] ) : $defaults['z_index'];
		$clean['z_index'] = min( 2147483000, max( 1, $z_index ) );

		$clean['bg_color']   = self::sanitize_color( isset( $input['bg_color'] ) ? $input['bg_color'] : '', $defaults['bg_color'] );
		$clean['text_color'] = self::sanitize_color( isset( $input['text_color'] ) ? $input['text_color'] : '', $defaults['text_color'] );

		$event              = isset( $input['ga4_event'] ) ? sanitize_key( $input['ga4_event'] ) : '';
		$clean['ga4_event'] = '' !== $event ? substr( $event, 0, 40 ) : $defaults['ga4_event'];

		$clean['locations'] = self::sanitize_locations( isset( $input['locations'] ) ? $input['locations'] : array() );
		$clean['rules']     = self::sanitize_rules( isset( $input['rules'] ) ? $input['rules'] : array(), $clean['locations'] );

		$default_location           = isset( $input['default_location'] ) ? sanitize_key( $input['default_location'] ) : '';
		$clean['default_location']  = self::location_exists( $default_location, $clean['locations'] ) ? $default_location : '';

		if ( '' === $clean['default_location'] && ! empty( $clean['locations'] ) ) {
			$first                     = reset( $clean['locations'] );
			$clean['default_location'] = $first['id'];
		}

		return $clean;
	}

	/**
	 * Standorte bereinigen.
	 *
	 * @param mixed $locations Rohdaten.
	 * @return array
	 */
	protected static function sanitize_locations( $locations ) {
		$clean = array();

		if ( ! is_array( $locations ) ) {
			return $clean;
		}

		foreach ( $locations as $location ) {
			if ( ! is_array( $location ) ) {
				continue;
			}

			$label  = isset( $location['label'] ) ? sanitize_text_field( $location['label'] ) : '';
			$number = isset( $location['number'] ) ? sanitize_text_field( $location['number'] ) : '';

			if ( '' === $label && '' === $number ) {
				continue;
			}

			$id = isset( $location['id'] ) ? sanitize_key( $location['id'] ) : '';

			if ( '' === $id || self::location_exists( $id, $clean ) ) {
				$id = 'loc' . substr( md5( $label . $number . wp_rand() ), 0, 8 );
			}

			$clean[] = array(
				'id'       => $id,
				'label'    => $label,
				'number'   => $number,
				'ga_label' => isset( $location['ga_label'] ) ? sanitize_text_field( $location['ga_label'] ) : '',
			);
		}

		return $clean;
	}

	/**
	 * URL-Regeln bereinigen.
	 *
	 * @param mixed $rules     Rohdaten.
	 * @param array $locations Bereits bereinigte Standorte.
	 * @return array
	 */
	protected static function sanitize_rules( $rules, $locations ) {
		$clean = array();

		if ( ! is_array( $rules ) ) {
			return $clean;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['pattern'] ) ) {
				continue;
			}

			$pattern = sanitize_text_field( $rule['pattern'] );
			$pattern = preg_replace( '#[^a-z0-9\-_/\*\.%]#i', '', $pattern );

			if ( '' === $pattern ) {
				continue;
			}

			$location = isset( $rule['location'] ) ? sanitize_key( $rule['location'] ) : '';

			if ( 'off' !== $location && ! self::location_exists( $location, $locations ) ) {
				continue;
			}

			$clean[] = array(
				'pattern'  => '/' . ltrim( strtolower( $pattern ), '/' ),
				'location' => $location,
			);
		}

		return $clean;
	}

	/**
	 * Prüft, ob eine Standort-ID in der Liste vorkommt.
	 *
	 * @param string $id        Standort-ID.
	 * @param array  $locations Standorte.
	 * @return bool
	 */
	protected static function location_exists( $id, $locations ) {
		if ( '' === $id ) {
			return false;
		}

		foreach ( $locations as $location ) {
			if ( isset( $location['id'] ) && $location['id'] === $id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hex-Farbe prüfen.
	 *
	 * @param string $value    Eingabe.
	 * @param string $fallback Ersatzwert.
	 * @return string
	 */
	protected static function sanitize_color( $value, $fallback ) {
		$color = sanitize_hex_color( $value );

		return $color ? $color : $fallback;
	}

	/**
	 * Einstellungsseite ausgeben.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'dsgn-sticky-call' ) );
		}

		$settings = dsgn_scc_get_settings();
		$name     = DSGN_SCC_OPTION;
		$stats    = ! empty( $settings['stats_enabled'] ) ? DSGN_SCC_Stats::get_summary( 30 ) : array();
		?>
		<div class="wrap dsgn-scc-settings">
			<h1><?php echo esc_html__( 'Sticky Call CTA', 'dsgn-sticky-call' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'dsgn_scc_options' ); ?>

				<h2><?php echo esc_html__( 'Allgemein', 'dsgn-sticky-call' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Button aktiv', 'dsgn-sticky-call' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], 1 ); ?> />
								<?php echo esc_html__( 'Sticky-Button im Frontend anzeigen', 'dsgn-sticky-call' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsgn-scc-label"><?php echo esc_html__( 'Button-Text', 'dsgn-sticky-call' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="dsgn-scc-label" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $settings['label'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Beschriftung', 'dsgn-sticky-call' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_number]" value="1" <?php checked( $settings['show_number'], 1 ); ?> />
								<?php echo esc_html__( 'Telefonnummer im Button anzeigen', 'dsgn-sticky-call' ); ?>
							</label><br />
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_location]" value="1" <?php checked( $settings['show_location'], 1 ); ?> />
								<?php echo esc_html__( 'Standortnamen im Button anzeigen', 'dsgn-sticky-call' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsgn-scc-breakpoint"><?php echo esc_html__( 'Breakpoint', 'dsgn-sticky-call' ); ?></label></th>
						<td>
							<input type="number" min="320" max="2000" step="1" id="dsgn-scc-breakpoint" name="<?php echo esc_attr( $name ); ?>[breakpoint]" value="<?php echo esc_attr( $settings['breakpoint'] ); ?>" class="small-text" /> px
							<p class="description"><?php echo esc_html__( 'Der Button wird nur bis zu dieser Bildschirmbreite angezeigt. Die Erkennung läuft rein über CSS und ist damit cache-sicher.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsgn-scc-prefix"><?php echo esc_html__( 'Landesvorwahl', 'dsgn-sticky-call' ); ?></label></th>
						<td>
							<input type="text" id="dsgn-scc-prefix" name="<?php echo esc_attr( $name ); ?>[country_prefix]" value="<?php echo esc_attr( $settings['country_prefix'] ); ?>" class="small-text" />
							<p class="description"><?php echo esc_html__( 'Wird verwendet, um Nummern mit führender 0 in ein internationales tel:-Format zu übersetzen.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Standorte', 'dsgn-sticky-call' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Zentrale Nummernliste. Jede Seite und jede URL-Regel verweist auf einen dieser Einträge, damit eine Nummernänderung nur an einer Stelle passiert.', 'dsgn-sticky-call' ); ?></p>

				<table class="widefat striped dsgn-scc-repeater" id="dsgn-scc-locations">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Bezeichnung', 'dsgn-sticky-call' ); ?></th>
							<th><?php echo esc_html__( 'Telefonnummer', 'dsgn-sticky-call' ); ?></th>
							<th><?php echo esc_html__( 'GA4-Label (optional)', 'dsgn-sticky-call' ); ?></th>
							<th class="dsgn-scc-col-stats"><?php echo esc_html__( 'Klicks', 'dsgn-sticky-call' ); ?></th>
							<th class="dsgn-scc-col-action"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $settings['locations'] as $index => $location ) : ?>
							<tr class="dsgn-scc-row">
								<td>
									<input type="hidden" name="<?php echo esc_attr( $name ); ?>[locations][<?php echo absint( $index ); ?>][id]" value="<?php echo esc_attr( $location['id'] ); ?>" />
									<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[locations][<?php echo absint( $index ); ?>][label]" value="<?php echo esc_attr( $location['label'] ); ?>" placeholder="<?php echo esc_attr__( 'z. B. Wien', 'dsgn-sticky-call' ); ?>" />
								</td>
								<td>
									<input type="text" name="<?php echo esc_attr( $name ); ?>[locations][<?php echo absint( $index ); ?>][number]" value="<?php echo esc_attr( $location['number'] ); ?>" placeholder="+43 1 2345678" />
									<?php
									$preview = dsgn_scc_to_tel( $location['number'], $settings['country_prefix'] );
									if ( '' !== $preview ) :
										?>
										<p class="description">tel:<?php echo esc_html( $preview ); ?></p>
									<?php endif; ?>
								</td>
								<td>
									<input type="text" name="<?php echo esc_attr( $name ); ?>[locations][<?php echo absint( $index ); ?>][ga_label]" value="<?php echo esc_attr( $location['ga_label'] ); ?>" placeholder="<?php echo esc_attr__( 'Standard: Bezeichnung', 'dsgn-sticky-call' ); ?>" />
								</td>
								<td class="dsgn-scc-col-stats">
									<?php
									if ( empty( $settings['stats_enabled'] ) ) {
										echo '<span class="dsgn-scc-stat-off">' . esc_html__( 'aus', 'dsgn-sticky-call' ) . '</span>';
									} else {
										$recent = isset( $stats[ $location['id'] ] ) ? $stats[ $location['id'] ]['recent'] : 0;
										$total  = isset( $stats[ $location['id'] ] ) ? $stats[ $location['id'] ]['total'] : 0;
										?>
										<strong class="dsgn-scc-stat-recent"><?php echo esc_html( number_format_i18n( $recent ) ); ?></strong>
										<span class="dsgn-scc-stat-hint"><?php echo esc_html__( 'in 30 Tagen', 'dsgn-sticky-call' ); ?></span>
										<span class="dsgn-scc-stat-hint">
											<?php
											/* translators: %s: Gesamtzahl der Klicks. */
											printf( esc_html__( '%s gesamt', 'dsgn-sticky-call' ), esc_html( number_format_i18n( $total ) ) );
											?>
										</span>
										<?php
									}
									?>
								</td>
								<td class="dsgn-scc-col-action">
									<button type="button" class="button-link delete dsgn-scc-remove"><?php echo esc_html__( 'Entfernen', 'dsgn-sticky-call' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button dsgn-scc-add" data-target="dsgn-scc-locations"><?php echo esc_html__( 'Standort hinzufügen', 'dsgn-sticky-call' ); ?></button>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dsgn-scc-default"><?php echo esc_html__( 'Standardstandort', 'dsgn-sticky-call' ); ?></label></th>
						<td>
							<select id="dsgn-scc-default" name="<?php echo esc_attr( $name ); ?>[default_location]">
								<option value=""><?php echo esc_html__( '— kein Button ohne Zuordnung —', 'dsgn-sticky-call' ); ?></option>
								<?php foreach ( $settings['locations'] as $location ) : ?>
									<option value="<?php echo esc_attr( $location['id'] ); ?>" <?php selected( $settings['default_location'], $location['id'] ); ?>>
										<?php echo esc_html( $location['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html__( 'Greift, wenn weder an der Seite noch über eine URL-Regel etwas zugeordnet ist.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'URL-Regeln', 'dsgn-sticky-call' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Muster werden gegen den Pfad der aufgerufenen URL geprüft, die erste Übereinstimmung gewinnt. * steht für beliebige Zeichen, z. B. /wien/* oder /standort-graz-*. Neue Landingpages im passenden Pfad bekommen die Nummer damit automatisch.', 'dsgn-sticky-call' ); ?>
				</p>

				<table class="widefat striped dsgn-scc-repeater" id="dsgn-scc-rules">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'URL-Muster', 'dsgn-sticky-call' ); ?></th>
							<th><?php echo esc_html__( 'Standort', 'dsgn-sticky-call' ); ?></th>
							<th class="dsgn-scc-col-action"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $settings['rules'] as $index => $rule ) : ?>
							<tr class="dsgn-scc-row">
								<td>
									<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[rules][<?php echo absint( $index ); ?>][pattern]" value="<?php echo esc_attr( $rule['pattern'] ); ?>" placeholder="/wien/*" />
								</td>
								<td>
									<select name="<?php echo esc_attr( $name ); ?>[rules][<?php echo absint( $index ); ?>][location]">
										<?php foreach ( $settings['locations'] as $location ) : ?>
											<option value="<?php echo esc_attr( $location['id'] ); ?>" <?php selected( $rule['location'], $location['id'] ); ?>>
												<?php echo esc_html( $location['label'] ); ?>
											</option>
										<?php endforeach; ?>
										<option value="off" <?php selected( $rule['location'], 'off' ); ?>><?php echo esc_html__( '— Button ausblenden —', 'dsgn-sticky-call' ); ?></option>
									</select>
								</td>
								<td class="dsgn-scc-col-action">
									<button type="button" class="button-link delete dsgn-scc-remove"><?php echo esc_html__( 'Entfernen', 'dsgn-sticky-call' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button dsgn-scc-add" data-target="dsgn-scc-rules"><?php echo esc_html__( 'Regel hinzufügen', 'dsgn-sticky-call' ); ?></button>
				</p>

				<h2><?php echo esc_html__( 'Darstellung', 'dsgn-sticky-call' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dsgn-scc-bg"><?php echo esc_html__( 'Hintergrundfarbe', 'dsgn-sticky-call' ); ?></label></th>
						<td><input type="color" id="dsgn-scc-bg" name="<?php echo esc_attr( $name ); ?>[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="dsgn-scc-fg"><?php echo esc_html__( 'Textfarbe', 'dsgn-sticky-call' ); ?></label></th>
						<td><input type="color" id="dsgn-scc-fg" name="<?php echo esc_attr( $name ); ?>[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="dsgn-scc-zindex"><?php echo esc_html__( 'z-index', 'dsgn-sticky-call' ); ?></label></th>
						<td>
							<input type="number" min="1" step="1" id="dsgn-scc-zindex" name="<?php echo esc_attr( $name ); ?>[z_index]" value="<?php echo esc_attr( $settings['z_index'] ); ?>" class="small-text" />
							<p class="description"><?php echo esc_html__( 'Bewusst unter dem Consent-Layer halten, damit der Cookie-Banner bedienbar bleibt.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Seitenabstand', 'dsgn-sticky-call' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[offset_body]" value="1" <?php checked( $settings['offset_body'], 1 ); ?> />
								<?php echo esc_html__( 'Unten Platz freihalten, damit der Button den Footer nicht verdeckt', 'dsgn-sticky-call' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'GA4-Tracking', 'dsgn-sticky-call' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Tracking', 'dsgn-sticky-call' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ga4_enabled]" value="1" <?php checked( $settings['ga4_enabled'], 1 ); ?> />
								<?php echo esc_html__( 'Klick als GA4-Event melden', 'dsgn-sticky-call' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Nutzt gtag() – bei Site Kit bereits vorhanden – und fällt sonst auf dataLayer.push() für den Google Tag Manager zurück.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsgn-scc-event"><?php echo esc_html__( 'Eventname', 'dsgn-sticky-call' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="dsgn-scc-event" name="<?php echo esc_attr( $name ); ?>[ga4_event]" value="<?php echo esc_attr( $settings['ga4_event'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Parameter: location_label, phone_number, page_path, link_url.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Zusätzlich', 'dsgn-sticky-call' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ga4_generate_lead]" value="1" <?php checked( $settings['ga4_generate_lead'], 1 ); ?> />
								<?php echo esc_html__( 'Zusätzlich das empfohlene Event generate_lead senden', 'dsgn-sticky-call' ); ?>
							</label><br />
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ga4_require_consent]" value="1" <?php checked( $settings['ga4_require_consent'], 1 ); ?> />
								<?php echo esc_html__( 'Event nur senden, wenn window.dsgnSccConsentGranted === true gesetzt ist', 'dsgn-sticky-call' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Für Setups ohne Google Consent Mode: die Einwilligung des Cookie-Tools setzt die Variable, erst dann wird gemeldet. Der Anruf selbst funktioniert unabhängig davon.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Nur echte Interaktionen', 'dsgn-sticky-call' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ga4_human_only]" value="1" <?php checked( $settings['ga4_human_only'], 1 ); ?> />
								<?php echo esc_html__( 'Automatisierte Klicks verwerfen', 'dsgn-sticky-call' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Geprüft wird, ob der Klick vom Browser als echte Nutzereingabe gemeldet wird (event.isTrusted), ob vorher eine Eingabe wie Tippen, Scrollen oder Tastendruck stattgefunden hat und ob der Browser sich als automatisiert ausweist (navigator.webdriver). Tastaturbedienung bleibt zählbar.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsgn-scc-dwell"><?php echo esc_html__( 'Mindestverweildauer', 'dsgn-sticky-call' ); ?></label></th>
						<td>
							<input type="number" min="0" max="10000" step="100" id="dsgn-scc-dwell" name="<?php echo esc_attr( $name ); ?>[ga4_min_dwell]" value="<?php echo esc_attr( $settings['ga4_min_dwell'] ); ?>" class="small-text" /> ms
							<p class="description"><?php echo esc_html__( 'Klicks, die schneller als diese Zeit nach dem Seitenaufruf passieren, werden nicht gemeldet. 0 schaltet die Prüfung ab.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Entprellung', 'dsgn-sticky-call' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ga4_once_per_view]" value="1" <?php checked( $settings['ga4_once_per_view'], 1 ); ?> />
								<?php echo esc_html__( 'Pro Seitenaufruf höchstens ein Event senden', 'dsgn-sticky-call' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Verhindert doppelte Zählung bei Doppeltaps oder wenn nach dem Anruf zurück zur Seite gewechselt wird.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Eigene Klickzählung', 'dsgn-sticky-call' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Zählung', 'dsgn-sticky-call' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[stats_enabled]" value="1" <?php checked( $settings['stats_enabled'], 1 ); ?> />
								<?php echo esc_html__( 'Klicks je Standort in der eigenen Datenbank zählen', 'dsgn-sticky-call' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Gespeichert werden nur Standort, Tag, Seiten-ID und eine Anzahl – kein Cookie, keine IP, keine Kennung des Besuchers. Die Zahlen liegen daher meist über denen in GA4, weil auch Besucher ohne Analytics-Einwilligung mitgezählt werden. Es gelten dieselben Prüfungen auf echte Interaktionen wie beim GA4-Event.', 'dsgn-sticky-call' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Deinstallation', 'dsgn-sticky-call' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Daten löschen', 'dsgn-sticky-call' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_data]" value="1" <?php checked( $settings['delete_data'], 1 ); ?> />
								<?php echo esc_html__( 'Beim Löschen des Plugins alle Einstellungen und Seitenzuordnungen entfernen', 'dsgn-sticky-call' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>

				<template id="dsgn-scc-tmpl-dsgn-scc-locations">
					<tr class="dsgn-scc-row">
						<td>
							<input type="hidden" name="<?php echo esc_attr( $name ); ?>[locations][__INDEX__][id]" value="" />
							<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[locations][__INDEX__][label]" value="" placeholder="<?php echo esc_attr__( 'z. B. Wien', 'dsgn-sticky-call' ); ?>" />
						</td>
						<td>
							<input type="text" name="<?php echo esc_attr( $name ); ?>[locations][__INDEX__][number]" value="" placeholder="+43 1 2345678" />
						</td>
						<td>
							<input type="text" name="<?php echo esc_attr( $name ); ?>[locations][__INDEX__][ga_label]" value="" placeholder="<?php echo esc_attr__( 'Standard: Bezeichnung', 'dsgn-sticky-call' ); ?>" />
						</td>
						<td class="dsgn-scc-col-stats">
							<span class="dsgn-scc-stat-hint"><?php echo esc_html__( 'nach dem Speichern', 'dsgn-sticky-call' ); ?></span>
						</td>
						<td class="dsgn-scc-col-action">
							<button type="button" class="button-link delete dsgn-scc-remove"><?php echo esc_html__( 'Entfernen', 'dsgn-sticky-call' ); ?></button>
						</td>
					</tr>
				</template>

				<template id="dsgn-scc-tmpl-dsgn-scc-rules">
					<tr class="dsgn-scc-row">
						<td>
							<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[rules][__INDEX__][pattern]" value="" placeholder="/wien/*" />
						</td>
						<td>
							<select name="<?php echo esc_attr( $name ); ?>[rules][__INDEX__][location]">
								<?php foreach ( $settings['locations'] as $location ) : ?>
									<option value="<?php echo esc_attr( $location['id'] ); ?>"><?php echo esc_html( $location['label'] ); ?></option>
								<?php endforeach; ?>
								<option value="off"><?php echo esc_html__( '— Button ausblenden —', 'dsgn-sticky-call' ); ?></option>
							</select>
						</td>
						<td class="dsgn-scc-col-action">
							<button type="button" class="button-link delete dsgn-scc-remove"><?php echo esc_html__( 'Entfernen', 'dsgn-sticky-call' ); ?></button>
						</td>
					</tr>
				</template>
			</form>
		</div>
		<?php
	}
}

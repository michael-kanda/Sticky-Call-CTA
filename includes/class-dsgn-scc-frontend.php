<?php
/**
 * Ausgabe des Sticky-Buttons im Frontend.
 *
 * @package DSGN_Sticky_Call
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DSGN_SCC_Frontend
 */
class DSGN_SCC_Frontend {

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 100 );
	}

	/**
	 * Styles und Skript laden, aber nur wenn ein Button ausgegeben wird.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$location = DSGN_SCC_Resolver::resolve();

		if ( ! $location ) {
			return;
		}

		$settings = dsgn_scc_get_settings();

		wp_enqueue_style(
			'dsgn-scc-frontend',
			DSGN_SCC_URL . 'assets/css/frontend.css',
			array(),
			DSGN_SCC_VERSION
		);

		wp_add_inline_style( 'dsgn-scc-frontend', self::build_inline_css( $settings ) );

		wp_enqueue_script(
			'dsgn-scc-frontend',
			DSGN_SCC_URL . 'assets/js/frontend.js',
			array(),
			DSGN_SCC_VERSION,
			true
		);

		wp_localize_script(
			'dsgn-scc-frontend',
			'dsgnSccData',
			array(
				'track'          => ! empty( $settings['ga4_enabled'] ) ? 1 : 0,
				'event'          => $settings['ga4_event'],
				'generateLead'   => ! empty( $settings['ga4_generate_lead'] ) ? 1 : 0,
				'requireConsent' => ! empty( $settings['ga4_require_consent'] ) ? 1 : 0,
				'humanOnly'      => ! empty( $settings['ga4_human_only'] ) ? 1 : 0,
				'minDwell'       => absint( $settings['ga4_min_dwell'] ),
				'oncePerView'    => ! empty( $settings['ga4_once_per_view'] ) ? 1 : 0,
				'offsetBody'     => ! empty( $settings['offset_body'] ) ? 1 : 0,
				'locationId'     => $location['id'],
				'locationLabel'  => $location['ga_label'],
				'phoneNumber'    => $location['tel'],
			)
		);
	}

	/**
	 * Dynamisches CSS aus den Einstellungen.
	 *
	 * @param array $settings Einstellungen.
	 * @return string
	 */
	protected static function build_inline_css( $settings ) {
		$breakpoint = absint( $settings['breakpoint'] );
		$bg         = sanitize_hex_color( $settings['bg_color'] );
		$fg         = sanitize_hex_color( $settings['text_color'] );
		$z_index    = absint( $settings['z_index'] );

		$css  = ':root{';
		$css .= '--dsgn-scc-bg:' . ( $bg ? $bg : '#1a73e8' ) . ';';
		$css .= '--dsgn-scc-fg:' . ( $fg ? $fg : '#ffffff' ) . ';';
		$css .= '--dsgn-scc-z:' . $z_index . ';';
		$css .= '}';

		$css .= '@media (max-width:' . $breakpoint . 'px){';
		$css .= '.dsgn-scc{display:block;}';

		if ( ! empty( $settings['offset_body'] ) ) {
			$css .= 'body.dsgn-scc-offset{padding-bottom:var(--dsgn-scc-height,0px);}';
		}

		$css .= '}';

		return $css;
	}

	/**
	 * Button ausgeben.
	 *
	 * @return void
	 */
	public static function render() {
		$location = DSGN_SCC_Resolver::resolve();

		if ( ! $location ) {
			return;
		}

		$settings = dsgn_scc_get_settings();
		$label    = $settings['label'];
		$number   = $location['number'];

		$aria_parts = array( $label );

		if ( '' !== $location['label'] ) {
			$aria_parts[] = $location['label'];
		}

		$aria_parts[] = $number;
		$aria_label   = implode( ' – ', array_filter( $aria_parts ) );
		?>
		<div class="dsgn-scc" id="dsgn-scc" data-location="<?php echo esc_attr( $location['id'] ); ?>">
			<a class="dsgn-scc__link"
				href="<?php echo esc_url( 'tel:' . $location['tel'], array( 'tel' ) ); ?>"
				aria-label="<?php echo esc_attr( $aria_label ); ?>">
				<span class="dsgn-scc__icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
						<path fill="currentColor" d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C11.1 21 3 12.9 3 3c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.6.1.3 0 .7-.2 1l-2.3 2.2z" />
					</svg>
				</span>
				<span class="dsgn-scc__text">
					<span class="dsgn-scc__label"><?php echo esc_html( $label ); ?></span>
					<?php if ( ! empty( $settings['show_number'] ) || ! empty( $settings['show_location'] ) ) : ?>
						<span class="dsgn-scc__meta">
							<?php
							$meta = array();

							if ( ! empty( $settings['show_location'] ) && '' !== $location['label'] ) {
								$meta[] = $location['label'];
							}

							if ( ! empty( $settings['show_number'] ) ) {
								$meta[] = $number;
							}

							echo esc_html( implode( ' · ', $meta ) );
							?>
						</span>
					<?php endif; ?>
				</span>
			</a>
		</div>
		<?php
	}
}

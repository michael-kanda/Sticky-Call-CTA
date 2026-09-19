<?php
/**
 * Auswahlfeld für den Standort an einzelnen Beiträgen und Seiten.
 *
 * @package DSGN_Sticky_Call
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DSGN_SCC_Metabox
 */
class DSGN_SCC_Metabox {

	/**
	 * Nonce-Action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'dsgn_scc_save_location';

	/**
	 * Nonce-Feldname.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'dsgn_scc_nonce';

	/**
	 * Hooks registrieren.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Meta registrieren, damit sie auch im Block-Editor bekannt ist.
	 *
	 * @return void
	 */
	public static function register_meta() {
		foreach ( dsgn_scc_get_supported_post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				DSGN_SCC_META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => false,
					'sanitize_callback' => 'sanitize_key',
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Metabox registrieren.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		$settings = dsgn_scc_get_settings();

		if ( empty( $settings['locations'] ) ) {
			return;
		}

		add_meta_box(
			'dsgn-scc-location',
			__( 'Sticky Call CTA', 'dsgn-sticky-call' ),
			array( __CLASS__, 'render' ),
			dsgn_scc_get_supported_post_types(),
			'side',
			'default'
		);
	}

	/**
	 * Inhalt der Metabox.
	 *
	 * @param WP_Post $post Aktueller Beitrag.
	 * @return void
	 */
	public static function render( $post ) {
		$settings = dsgn_scc_get_settings();
		$current  = (string) get_post_meta( $post->ID, DSGN_SCC_META_KEY, true );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="dsgn-scc-location-select"><?php echo esc_html__( 'Telefonnummer für diese Seite', 'dsgn-sticky-call' ); ?></label>
			<select id="dsgn-scc-location-select" name="dsgn_scc_location" class="widefat">
				<option value="" <?php selected( '' === $current || 'inherit' === $current ); ?>>
					<?php echo esc_html__( 'Automatisch (URL-Regel bzw. Standard)', 'dsgn-sticky-call' ); ?>
				</option>
				<?php foreach ( $settings['locations'] as $location ) : ?>
					<option value="<?php echo esc_attr( $location['id'] ); ?>" <?php selected( $current, $location['id'] ); ?>>
						<?php echo esc_html( $location['label'] ); ?>
					</option>
				<?php endforeach; ?>
				<option value="off" <?php selected( $current, 'off' ); ?>>
					<?php echo esc_html__( 'Button hier ausblenden', 'dsgn-sticky-call' ); ?>
				</option>
			</select>
		</p>
		<p class="description">
			<?php echo esc_html__( 'Eine Auswahl hier hat Vorrang vor den URL-Regeln.', 'dsgn-sticky-call' ); ?>
		</p>
		<?php
	}

	/**
	 * Auswahl speichern.
	 *
	 * @param int     $post_id Beitrags-ID.
	 * @param WP_Post $post    Beitrag.
	 * @return void
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, dsgn_scc_get_supported_post_types(), true ) ) {
			return;
		}

		$value = isset( $_POST['dsgn_scc_location'] ) ? sanitize_key( wp_unslash( $_POST['dsgn_scc_location'] ) ) : '';

		if ( '' === $value ) {
			delete_post_meta( $post_id, DSGN_SCC_META_KEY );
			return;
		}

		if ( 'off' !== $value && ! dsgn_scc_get_location( $value ) ) {
			delete_post_meta( $post_id, DSGN_SCC_META_KEY );
			return;
		}

		update_post_meta( $post_id, DSGN_SCC_META_KEY, $value );
	}
}

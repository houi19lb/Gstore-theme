<?php
/**
 * Avisos compactos exibidos sobre imagens de produto.
 *
 * @package Gstore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gstore_get_product_image_badges' ) ) {
	/**
	 * Retorna no máximo dois avisos válidos configurados no produto.
	 *
	 * @param WC_Product|int|null $product Produto ou ID.
	 * @return array<int, array<string, string>>
	 */
	function gstore_get_product_image_badges( $product = null ) {
		if ( is_numeric( $product ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( (int) $product );
		}
		if ( ! $product && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return array();
		}

		$product_id = (int) $product->get_id();
		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) && method_exists( $product, 'get_parent_id' ) ) {
			$product_id = (int) $product->get_parent_id();
		}
		if ( $product_id <= 0 ) {
			return array();
		}

		$raw_types = get_post_meta( $product_id, '_gstore_product_image_badges', true );
		if ( is_string( $raw_types ) && '' !== trim( $raw_types ) ) {
			$decoded   = json_decode( $raw_types, true );
			$raw_types = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) )
				? $decoded
				: preg_split( '/[\s,|]+/', $raw_types );
		}
		if ( ! is_array( $raw_types ) ) {
			return array();
		}

		$custom_text = trim( sanitize_text_field( (string) get_post_meta( $product_id, '_gstore_product_image_badge_custom_text', true ) ) );
		$custom_text = function_exists( 'mb_substr' ) ? mb_substr( $custom_text, 0, 32 ) : substr( $custom_text, 0, 32 );
		$definitions = array(
			'free_shipping'  => array(
				'label' => __( 'Frete grátis', 'gstore' ),
				'tone'  => 'theme',
				'icon'  => 'fa-solid fa-truck-fast',
			),
			'installments_12' => array(
				'label' => __( '12x sem juros', 'gstore' ),
				'tone'  => 'attention',
				'icon'  => 'fa-solid fa-credit-card',
			),
			'installments_21' => array(
				'label' => __( '21x sem juros', 'gstore' ),
				'tone'  => 'attention',
				'icon'  => 'fa-solid fa-credit-card',
			),
			'custom'          => array(
				'label' => $custom_text,
				'tone'  => 'custom',
				'icon'  => 'fa-solid fa-tag',
			),
		);
		$badges      = array();

		foreach ( $raw_types as $raw_type ) {
			$type = sanitize_key( (string) $raw_type );
			if ( ! isset( $definitions[ $type ] ) || '' === $definitions[ $type ]['label'] ) {
				continue;
			}
			if ( in_array( $type, array_column( $badges, 'type' ), true ) ) {
				continue;
			}
			$badges[] = array_merge( array( 'type' => $type ), $definitions[ $type ] );
			if ( 2 === count( $badges ) ) {
				break;
			}
		}

		return apply_filters( 'gstore_product_image_badges', $badges, $product_id, $product );
	}
}

if ( ! function_exists( 'gstore_render_product_image_badges' ) ) {
	/**
	 * Renderiza avisos fora do fluxo da imagem para não alterar o tamanho do card.
	 *
	 * @param WC_Product|int|null $product Produto ou ID.
	 * @param string              $context card ou single.
	 * @return void
	 */
	function gstore_render_product_image_badges( $product = null, $context = 'card' ) {
		$badges = gstore_get_product_image_badges( $product );
		if ( empty( $badges ) ) {
			return;
		}

		$context = 'single' === $context ? 'single' : 'card';
		?>
		<div class="Gstore-product-image-badges Gstore-product-image-badges--<?php echo esc_attr( $context ); ?>" role="list" aria-label="<?php esc_attr_e( 'Destaques do produto', 'gstore' ); ?>">
			<?php foreach ( $badges as $badge ) : ?>
				<span class="Gstore-product-image-badge Gstore-product-image-badge--<?php echo esc_attr( $badge['tone'] ); ?>" role="listitem" title="<?php echo esc_attr( $badge['label'] ); ?>">
					<i class="<?php echo esc_attr( $badge['icon'] ); ?>" aria-hidden="true"></i>
					<span><?php echo esc_html( $badge['label'] ); ?></span>
				</span>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

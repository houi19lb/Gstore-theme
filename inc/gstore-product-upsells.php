<?php
/**
 * Produtos complementares configurados pelo admin GStore.
 *
 * Mantem o vinculo separado dos cross-sells nativos do WooCommerce e centraliza
 * a mesma selecao para produto, carrinho e mini-carrinho.
 *
 * @package Gstore
 */

defined( 'ABSPATH' ) || exit;

const GSTORE_PRODUCT_UPSELL_META_KEY = '_gstore_product_upsell_ids';

/**
 * Normaliza a lista ordenada de IDs configurados no admin.
 *
 * @param int $source_product_id Produto que possui as sugestoes.
 * @return array<int, int>
 */
function gstore_get_product_upsell_ids( $source_product_id ) {
	$source_product_id = absint( $source_product_id );
	if ( $source_product_id <= 0 ) {
		return array();
	}

	$raw = get_post_meta( $source_product_id, GSTORE_PRODUCT_UPSELL_META_KEY, true );
	if ( is_string( $raw ) && '' !== trim( $raw ) ) {
		$decoded = json_decode( $raw, true );
		$raw     = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) )
			? $decoded
			: preg_split( '/[\s,|]+/', $raw );
	}

	$ids = array();
	foreach ( (array) $raw as $candidate_id ) {
		$candidate_id = absint( $candidate_id );
		if ( $candidate_id <= 0 || $candidate_id === $source_product_id || in_array( $candidate_id, $ids, true ) ) {
			continue;
		}
		$ids[] = $candidate_id;
		if ( count( $ids ) >= 2 ) {
			break;
		}
	}

	return $ids;
}

/**
 * Garante que uma recomendacao pode ser adicionada sem escolha adicional.
 *
 * @param int|WC_Product $product Produto candidato.
 * @return WC_Product|null
 */
function gstore_get_eligible_product_upsell( $product ) {
	$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
	if (
		! $product
		|| ! $product->is_type( 'simple' )
		|| 'publish' !== $product->get_status()
		|| ! $product->is_visible()
		|| ! $product->is_purchasable()
		|| ! $product->is_in_stock()
	) {
		return null;
	}

	return $product;
}

/**
 * Dados serializaveis para renderizacao dos cards de recomendacao.
 *
 * @param WC_Product $product Produto elegivel.
 * @param int        $source_product_id Produto de origem da sugestao.
 * @return array<string, mixed>
 */
function gstore_get_product_upsell_card_data( $product, $source_product_id ) {
	$product = gstore_get_eligible_product_upsell( $product );
	if ( ! $product ) {
		return array();
	}

	$price = (float) wc_get_price_to_display( $product );
	return array(
		'id'                => (int) $product->get_id(),
		'source_product_id' => absint( $source_product_id ),
		'name'              => (string) $product->get_name(),
		'price'             => $price,
		'price_html'        => $product->get_price_html(),
		'image_html'        => $product->get_image(
			'woocommerce_thumbnail',
			array(
				'class'    => 'Gstore-product-upsells__image',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		),
	);
}

/**
 * Retorna as sugestoes validas configuradas em um unico produto.
 *
 * @param int              $source_product_id Produto base.
 * @param array<int, int>  $excluded_ids Produtos que nao podem aparecer.
 * @return array<int, array<string, mixed>>
 */
function gstore_get_configured_product_upsells( $source_product_id, $excluded_ids = array() ) {
	$excluded_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $excluded_ids ) ) ) );
	$items        = array();

	foreach ( gstore_get_product_upsell_ids( $source_product_id ) as $candidate_id ) {
		if ( in_array( $candidate_id, $excluded_ids, true ) ) {
			continue;
		}

		$item = gstore_get_product_upsell_card_data( $candidate_id, $source_product_id );
		if ( empty( $item ) ) {
			continue;
		}

		$items[] = $item;
	}

	return $items;
}

/**
 * Salva a ordem de entrada sem alterar a chave/mesclagem nativa do WooCommerce.
 *
 * @param string $cart_item_key Chave do item no carrinho.
 * @param int    $product_id ID do produto.
 * @param int    $quantity Quantidade adicionada.
 * @param int    $variation_id ID da variacao.
 * @param array  $variation Atributos da variacao.
 * @param array  $cart_item_data Dados extras recebidos no add-to-cart.
 * @return void
 */
function gstore_product_upsells_record_cart_item_order( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
		return;
	}

	$item =& WC()->cart->cart_contents[ $cart_item_key ];
	if ( empty( $item['gstore_upsell_added_order'] ) ) {
		$sequence = WC()->session ? (int) WC()->session->get( 'gstore_product_upsell_sequence', 0 ) : 0;
		$sequence++;
		$item['gstore_upsell_added_order'] = $sequence;
		if ( WC()->session ) {
			WC()->session->set( 'gstore_product_upsell_sequence', $sequence );
		}
	}

	if ( ! empty( $cart_item_data['gstore_upsell_source'] ) ) {
		$item['gstore_upsell_source'] = absint( $cart_item_data['gstore_upsell_source'] );
	}
}
add_action( 'woocommerce_add_to_cart', 'gstore_product_upsells_record_cart_item_order', 10, 6 );

/**
 * Seleciona as sugestoes do carrinho a partir somente dos dois primeiros itens-base.
 * Produtos adicionados como upsell nunca se tornam uma nova fonte de sugestoes.
 *
 * @return array<int, array<string, mixed>>
 */
function gstore_resolve_cart_product_upsells() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}

	$cart_items       = WC()->cart->get_cart();
	$cart_product_ids = array();
	$base_items       = array();
	$fallback_order   = 0;

	foreach ( $cart_items as $cart_item_key => $cart_item ) {
		$product_id = ! empty( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : absint( $cart_item['product_id'] ?? 0 );
		if ( $product_id > 0 ) {
			$cart_product_ids[] = $product_id;
		}

		if ( ! empty( $cart_item['gstore_upsell_source'] ) ) {
			continue;
		}

		$source_id = absint( $cart_item['product_id'] ?? 0 );
		if ( $source_id <= 0 ) {
			continue;
		}

		$fallback_order++;
		$base_items[] = array(
			'key'      => (string) $cart_item_key,
			'product'  => $source_id,
			'order'    => isset( $cart_item['gstore_upsell_added_order'] ) ? (int) $cart_item['gstore_upsell_added_order'] : ( 1000000 + $fallback_order ),
		);
	}

	usort(
		$base_items,
		static function ( $left, $right ) {
			return $left['order'] <=> $right['order'];
		}
	);

	$selected = array();
	$seen     = array_values( array_unique( $cart_product_ids ) );
	foreach ( array_slice( $base_items, 0, 2 ) as $base_item ) {
		foreach ( gstore_get_configured_product_upsells( $base_item['product'], array_merge( $cart_product_ids, $seen ) ) as $candidate ) {
			if ( in_array( $candidate['id'], $seen, true ) ) {
				continue;
			}
			$selected[] = $candidate;
			$seen[]     = $candidate['id'];
			break;
		}
	}

	return $selected;
}

/**
 * Confirma que uma sugestão ainda não está no carrinho antes de adicioná-la.
 *
 * @param int $product_id ID do produto simples sugerido.
 * @return bool
 */
function gstore_cart_contains_product_upsell( $product_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return false;
	}

	$product_id = absint( $product_id );
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( $product_id === absint( $cart_item['product_id'] ?? 0 ) || $product_id === absint( $cart_item['variation_id'] ?? 0 ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Renderiza um card de upsell adicionado por AJAX com validacao no servidor.
 *
 * @param array<string, mixed> $item Card resolvido.
 * @param string               $context Contexto visual.
 * @return void
 */
function gstore_render_product_upsell_card( $item, $context = 'cart' ) {
	if ( empty( $item['id'] ) || empty( $item['source_product_id'] ) ) {
		return;
	}
	?>
	<article class="Gstore-product-upsells__item Gstore-product-upsells__item--<?php echo esc_attr( $context ); ?>">
		<div class="Gstore-product-upsells__media">
			<?php echo wp_kses_post( $item['image_html'] ); ?>
		</div>
		<div class="Gstore-product-upsells__body">
			<h3 class="Gstore-product-upsells__name"><?php echo esc_html( $item['name'] ); ?></h3>
			<div class="Gstore-product-upsells__price"><?php echo wp_kses_post( $item['price_html'] ); ?></div>
		</div>
		<button
			type="button"
			class="Gstore-product-upsells__add"
			data-gstore-upsell-add
			data-product-id="<?php echo esc_attr( (string) $item['id'] ); ?>"
			data-source-product-id="<?php echo esc_attr( (string) $item['source_product_id'] ); ?>"
		>
			<?php esc_html_e( 'Adicionar', 'gstore' ); ?>
		</button>
	</article>
	<?php
}

/**
 * Renderiza o modulo usado no carrinho completo e no mini-carrinho.
 *
 * @param string $context cart|mini.
 * @return void
 */
function gstore_render_cart_product_upsells( $context = 'cart' ) {
	$items = gstore_resolve_cart_product_upsells();
	if ( empty( $items ) ) {
		return;
	}

	$title    = 'mini' === $context ? __( 'Combine com seu pedido', 'gstore' ) : __( 'Itens que combinam com sua compra', 'gstore' );
	$subtitle = 'mini' === $context ? __( 'Compatível com este produto', 'gstore' ) : __( 'Complete seu pedido com estes produtos', 'gstore' );
	?>
	<section class="Gstore-product-upsells Gstore-product-upsells--<?php echo esc_attr( $context ); ?>" data-gstore-product-upsells>
		<header class="Gstore-product-upsells__header">
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $subtitle ); ?></p>
		</header>
		<div class="Gstore-product-upsells__list">
			<?php foreach ( $items as $item ) : ?>
				<?php gstore_render_product_upsell_card( $item, $context ); ?>
			<?php endforeach; ?>
		</div>
		<p class="Gstore-product-upsells__status" data-gstore-upsell-status aria-live="polite"></p>
	</section>
	<?php
}

/**
 * Exibe o conjunto configurado dentro do formulario nativo da pagina de produto.
 *
 * @return void
 */
function gstore_render_single_product_upsell_bundle() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$items = gstore_get_configured_product_upsells( $product->get_id() );
	if ( empty( $items ) ) {
		return;
	}

	$base_price = (float) wc_get_price_to_display( $product );
	?>
	<section class="Gstore-product-upsells Gstore-product-upsells--single" data-gstore-upsell-bundle data-base-price="<?php echo esc_attr( (string) $base_price ); ?>">
		<header class="Gstore-product-upsells__header">
			<h2><?php esc_html_e( 'Monte seu conjunto', 'gstore' ); ?></h2>
			<p><?php esc_html_e( 'Escolha os itens que fazem sentido para você.', 'gstore' ); ?></p>
		</header>
		<div class="Gstore-product-upsells__list">
			<?php foreach ( $items as $item ) : ?>
				<label class="Gstore-product-upsells__item Gstore-product-upsells__item--single">
					<input type="checkbox" name="gstore_product_upsells[]" value="<?php echo esc_attr( (string) $item['id'] ); ?>" data-gstore-upsell-checkbox data-price="<?php echo esc_attr( (string) $item['price'] ); ?>" />
					<span class="Gstore-product-upsells__media"><?php echo wp_kses_post( $item['image_html'] ); ?></span>
					<span class="Gstore-product-upsells__body">
						<span class="Gstore-product-upsells__name"><?php echo esc_html( $item['name'] ); ?></span>
						<span class="Gstore-product-upsells__price"><?php echo wp_kses_post( $item['price_html'] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
		<div class="Gstore-product-upsells__bundle-total">
			<span><?php esc_html_e( 'Total com selecionados', 'gstore' ); ?></span>
			<strong data-gstore-upsell-total><?php echo wp_kses_post( wc_price( $base_price ) ); ?></strong>
		</div>
		<button type="submit" name="gstore_add_bundle" value="1" class="Gstore-product-upsells__bundle-add" data-gstore-upsell-bundle-add disabled>
			<?php esc_html_e( 'Adicionar conjunto', 'gstore' ); ?>
		</button>
	</section>
	<?php
}
add_action( 'woocommerce_after_add_to_cart_button', 'gstore_render_single_product_upsell_bundle', 30 );

/**
 * Depois de validar o produto principal, inclui os complementos escolhidos no mesmo carrinho.
 *
 * @param string $cart_item_key Chave do item principal.
 * @param int    $product_id Produto principal.
 * @return void
 */
function gstore_add_selected_product_upsells_to_cart( $cart_item_key, $product_id ) {
	static $is_processing = false;
	if ( $is_processing || empty( $_REQUEST['gstore_add_bundle'] ) || empty( $_REQUEST['gstore_product_upsells'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$requested = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['gstore_product_upsells'] ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$allowed   = gstore_get_product_upsell_ids( $product_id );
	$is_processing = true;
	foreach ( array_slice( $requested, 0, 2 ) as $candidate_id ) {
		if ( ! in_array( $candidate_id, $allowed, true ) || ! gstore_get_eligible_product_upsell( $candidate_id ) ) {
			continue;
		}
		WC()->cart->add_to_cart( $candidate_id, 1, 0, array(), array( 'gstore_upsell_source' => absint( $product_id ) ) );
	}
	$is_processing = false;
}
add_action( 'woocommerce_add_to_cart', 'gstore_add_selected_product_upsells_to_cart', 20, 2 );

/**
 * Valida e adiciona uma sugestao clicada em carrinho ou mini-carrinho.
 *
 * @return void
 */
function gstore_ajax_add_product_upsell() {
	check_ajax_referer( 'gstore_product_upsells', 'nonce' );
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => __( 'Carrinho indisponível.', 'gstore' ) ), 500 );
	}

	$product_id        = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	$source_product_id = isset( $_POST['source_product_id'] ) ? absint( wp_unslash( $_POST['source_product_id'] ) ) : 0;
	if (
		$product_id <= 0
		|| $source_product_id <= 0
		|| ! in_array( $product_id, gstore_get_product_upsell_ids( $source_product_id ), true )
		|| ! gstore_get_eligible_product_upsell( $product_id )
	) {
		wp_send_json_error( array( 'message' => __( 'Este produto não está disponível para adicionar.', 'gstore' ) ), 400 );
	}
	if ( gstore_cart_contains_product_upsell( $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Este produto já está no seu carrinho.', 'gstore' ) ), 400 );
	}

	$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'gstore_upsell_source' => $source_product_id ) );
	if ( ! $cart_item_key ) {
		wp_send_json_error( array( 'message' => __( 'Não foi possível adicionar o produto.', 'gstore' ) ), 400 );
	}

	WC()->cart->calculate_totals();
	wp_send_json_success(
		array(
			'cart_hash'  => WC()->cart->get_cart_hash(),
			'cart_count' => WC()->cart->get_cart_contents_count(),
			'message'    => __( 'Produto adicionado ao carrinho.', 'gstore' ),
		)
	);
}
add_action( 'wp_ajax_gstore_add_product_upsell', 'gstore_ajax_add_product_upsell' );
add_action( 'wp_ajax_nopriv_gstore_add_product_upsell', 'gstore_ajax_add_product_upsell' );

/**
 * Retorna o modulo atual do mini-carrinho, sempre recalculado pelo servidor.
 *
 * @return void
 */
function gstore_ajax_render_cart_product_upsells() {
	check_ajax_referer( 'gstore_product_upsells', 'nonce' );
	ob_start();
	gstore_render_cart_product_upsells( 'mini' );
	wp_send_json_success( array( 'html' => ob_get_clean() ) );
}
add_action( 'wp_ajax_gstore_render_cart_product_upsells', 'gstore_ajax_render_cart_product_upsells' );
add_action( 'wp_ajax_nopriv_gstore_render_cart_product_upsells', 'gstore_ajax_render_cart_product_upsells' );

/**
 * Enfileira a camada leve de interacao e o estilo compartilhado pelos tres contextos.
 *
 * @return void
 */
function gstore_enqueue_product_upsell_assets() {
	if ( is_admin() || ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$version = wp_get_theme()->get( 'Version' );
	gstore_enqueue_theme_style( 'gstore-product-upsells-css', 'assets/css/components/product-upsells.css', array( 'gstore-style' ), $version );
	wp_enqueue_script(
		'gstore-product-upsells',
		gstore_theme_asset_uri( 'assets/js/product-upsells.js' ),
		array( 'jquery' ),
		gstore_theme_asset_version( 'assets/js/product-upsells.js', $version ),
		true
	);
	wp_localize_script(
		'gstore-product-upsells',
		'gstoreProductUpsells',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gstore_product_upsells' ),
			'isCart'  => function_exists( 'is_cart' ) && is_cart(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gstore_enqueue_product_upsell_assets', 30 );

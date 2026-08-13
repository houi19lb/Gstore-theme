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
const GSTORE_PRODUCT_UPSELL_DISCOUNTS_META_KEY = '_gstore_product_upsell_discounts';

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
 * Normaliza uma regra de desconto configurada no produto-base.
 *
 * @param mixed $raw_value Valor recebido do meta.
 * @param string $type     percent|fixed.
 * @return float
 */
function gstore_normalize_product_upsell_discount_value( $raw_value, $type ) {
	$raw_value = is_scalar( $raw_value ) ? trim( (string) $raw_value ) : '';
	$raw_value = str_replace( array( 'R$', ' ' ), array( '', '' ), $raw_value );
	if ( 'percent' === $type ) {
		$raw_value = str_replace( '%', '', $raw_value );
	}
	if ( false !== strpos( $raw_value, ',' ) ) {
		$raw_value = str_replace( '.', '', $raw_value );
		$raw_value = str_replace( ',', '.', $raw_value );
	}
	$value     = is_numeric( $raw_value ) ? (float) $raw_value : 0.0;
	$maximum   = ( 'percent' === $type ) ? 100.0 : 9999999.99;

	return max( 0.0, min( $maximum, $value ) );
}

/**
 * Retorna regras de desconto por produto complementar, preservando a ordem dos
 * IDs configurados e ignorando quaisquer dados que nao estejam vinculados.
 *
 * @param int $source_product_id Produto-base.
 * @return array<int, array{type:string,value:float}>
 */
function gstore_get_product_upsell_discount_rules( $source_product_id ) {
	$ids = gstore_get_product_upsell_ids( $source_product_id );
	if ( empty( $ids ) ) {
		return array();
	}

	$raw = get_post_meta( absint( $source_product_id ), GSTORE_PRODUCT_UPSELL_DISCOUNTS_META_KEY, true );
	if ( is_string( $raw ) && '' !== trim( $raw ) ) {
		$decoded = json_decode( $raw, true );
		$raw     = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) ? $decoded : array();
	}

	$by_product_id = array();
	foreach ( (array) $raw as $rule ) {
		if ( ! is_array( $rule ) ) {
			continue;
		}

		$product_id = absint( $rule['id'] ?? $rule['product_id'] ?? 0 );
		if ( $product_id > 0 && ! isset( $by_product_id[ $product_id ] ) ) {
			$by_product_id[ $product_id ] = $rule;
		}
	}

	$rules = array();
	foreach ( $ids as $product_id ) {
		$rule  = $by_product_id[ $product_id ] ?? array();
		$type  = sanitize_key( (string) ( $rule['type'] ?? $rule['discount_type'] ?? 'none' ) );
		$value = in_array( $type, array( 'percent', 'fixed' ), true )
			? gstore_normalize_product_upsell_discount_value( $rule['value'] ?? $rule['discount_value'] ?? '', $type )
			: 0.0;

		$rules[ $product_id ] = array(
			'type'  => $value > 0 ? $type : 'none',
			'value' => $value,
		);
	}

	return $rules;
}

/**
 * Calcula o preco do complemento quando levado com seu produto-base.
 *
 * @param float                $price Preco atual do produto complementar.
 * @param array{type:string,value:float} $rule Regra do desconto.
 * @return float
 */
function gstore_get_product_upsell_discounted_price( $price, $rule ) {
	$price = max( 0.0, (float) $price );
	$type  = $rule['type'] ?? 'none';
	$value = isset( $rule['value'] ) ? (float) $rule['value'] : 0.0;
	if ( $price <= 0 || $value <= 0 ) {
		return $price;
	}

	if ( 'percent' === $type ) {
		return max( 0.0, $price * ( 1 - ( min( 100.0, $value ) / 100 ) ) );
	}
	if ( 'fixed' === $type ) {
		return max( 0.0, $price - $value );
	}

	return $price;
}

/**
 * Texto explicativo do desconto condicionado ao conjunto.
 *
 * @param array{type:string,value:float} $rule Regra do desconto.
 * @return string
 */
function gstore_get_product_upsell_discount_label( $rule ) {
	if ( empty( $rule['value'] ) || empty( $rule['type'] ) || 'none' === $rule['type'] ) {
		return '';
	}

	if ( 'percent' === $rule['type'] ) {
		$value = (float) $rule['value'];
		$value = floor( $value ) === $value ? (string) (int) $value : number_format_i18n( $value, 2 );
		return sprintf( __( '%s%% de desconto ao comprar junto', 'gstore' ), $value );
	}

	return sprintf( __( '%s de desconto ao comprar junto', 'gstore' ), wc_price( (float) $rule['value'] ) );
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

	$source_product_id = absint( $source_product_id );
	$regular_price     = (float) $product->get_price();
	$display_price     = (float) wc_get_price_to_display( $product );
	$rules             = gstore_get_product_upsell_discount_rules( $source_product_id );
	$discount          = $rules[ $product->get_id() ] ?? array( 'type' => 'none', 'value' => 0.0 );
	$discounted_price  = gstore_get_product_upsell_discounted_price( $regular_price, $discount );
	$has_discount      = $discounted_price < $regular_price;
	$discount_html     = $has_discount
		? wc_price( wc_get_price_to_display( $product, array( 'price' => $discounted_price ) ) )
		: $product->get_price_html();
	$price_html        = $has_discount
		? '<del>' . wc_price( $display_price ) . '</del> <ins>' . $discount_html . '</ins>'
		: $discount_html;

	return array(
		'id'                => (int) $product->get_id(),
		'source_product_id' => $source_product_id,
		'name'              => (string) $product->get_name(),
		'price'             => $has_discount ? $discounted_price : $regular_price,
		'price_html'        => $price_html,
		'discount_label'    => $has_discount ? gstore_get_product_upsell_discount_label( $discount ) : '',
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
 * Confirma que o produto-base da oferta ainda esta no carrinho.
 *
 * @param int $source_product_id Produto-base configurado no admin.
 * @return bool
 */
function gstore_cart_contains_product_upsell_source( $source_product_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return false;
	}

	$source_product_id = absint( $source_product_id );
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( $source_product_id === absint( $cart_item['product_id'] ?? 0 ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Aplica o desconto do complemento somente enquanto o produto-base estiver no
 * carrinho. O preco original e guardado no item para que a remocao do produto
 * principal devolva automaticamente o valor unitario normal.
 *
 * @param WC_Cart $cart Carrinho WooCommerce.
 * @return void
 */
function gstore_apply_product_upsell_discounts_to_cart( $cart ) {
	if ( ( is_admin() && ! wp_doing_ajax() ) || ! $cart instanceof WC_Cart ) {
		return;
	}

	foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
		$source_product_id = absint( $cart_item['gstore_upsell_source'] ?? 0 );
		$product_id        = absint( $cart_item['product_id'] ?? 0 );
		$product           = $cart_item['data'] ?? null;
		if ( $source_product_id <= 0 || $product_id <= 0 || ! $product instanceof WC_Product ) {
			continue;
		}

		$original_price = isset( $cart_item['gstore_upsell_regular_price'] )
			? (float) $cart_item['gstore_upsell_regular_price']
			: (float) $product->get_price();
		if ( ! isset( $cart_item['gstore_upsell_regular_price'] ) ) {
			$cart->cart_contents[ $cart_item_key ]['gstore_upsell_regular_price'] = (string) $original_price;
		}

		$rules = gstore_get_product_upsell_discount_rules( $source_product_id );
		$rule  = $rules[ $product_id ] ?? array( 'type' => 'none', 'value' => 0.0 );
		$price = gstore_cart_contains_product_upsell_source( $source_product_id )
			? gstore_get_product_upsell_discounted_price( $original_price, $rule )
			: $original_price;

		$product->set_price( $price );
	}
}
add_action( 'woocommerce_before_calculate_totals', 'gstore_apply_product_upsell_discounts_to_cart', 20 );

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
			<div class="Gstore-product-upsells__price<?php echo ! empty( $item['discount_label'] ) ? ' Gstore-product-upsells__price--discount' : ''; ?>"><?php echo wp_kses_post( $item['price_html'] ); ?></div>
			<?php if ( ! empty( $item['discount_label'] ) ) : ?>
				<p class="Gstore-product-upsells__discount-note"><?php echo esc_html( $item['discount_label'] ); ?></p>
			<?php endif; ?>
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
 * Exibe os complementos da página de produto no mesmo padrão direto do carrinho.
 * Cada botão adiciona somente o respectivo complemento via AJAX, sem checkbox ou
 * submissão do produto principal.
 *
 * @return void
 */
function gstore_render_single_product_upsells() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$items = gstore_get_configured_product_upsells( $product->get_id() );
	if ( empty( $items ) ) {
		return;
	}
	$has_discount = (bool) array_filter(
		$items,
		static function ( $item ) {
			return ! empty( $item['discount_label'] );
		}
	);

	?>
	<section class="Gstore-product-upsells Gstore-product-upsells--single" data-gstore-product-upsells>
		<header class="Gstore-product-upsells__header">
			<h2><?php echo esc_html( $has_discount ? __( 'Monte seu conjunto com desconto', 'gstore' ) : __( 'Monte seu conjunto', 'gstore' ) ); ?></h2>
			<?php if ( $has_discount ) : ?>
				<p><?php esc_html_e( 'O desconto vale quando este produto for levado junto.', 'gstore' ); ?></p>
			<?php endif; ?>
		</header>
		<div class="Gstore-product-upsells__list">
			<?php foreach ( $items as $item ) : ?>
				<?php gstore_render_product_upsell_card( $item, 'single' ); ?>
			<?php endforeach; ?>
		</div>
		<p class="Gstore-product-upsells__status" data-gstore-upsell-status aria-live="polite"></p>
	</section>
	<?php
}
add_action( 'woocommerce_after_add_to_cart_button', 'gstore_render_single_product_upsells', 30 );

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

	$upsell_product = gstore_get_eligible_product_upsell( $product_id );
	$cart_item_key  = WC()->cart->add_to_cart(
		$product_id,
		1,
		0,
		array(),
		array(
			'gstore_upsell_source'        => $source_product_id,
			'gstore_upsell_regular_price' => (string) $upsell_product->get_price(),
		)
	);
	if ( ! $cart_item_key ) {
		wp_send_json_error( array( 'message' => __( 'Não foi possível adicionar o produto.', 'gstore' ) ), 400 );
	}

	$has_source_product = gstore_cart_contains_product_upsell_source( $source_product_id );
	WC()->cart->calculate_totals();
	wp_send_json_success(
		array(
			'cart_hash'  => WC()->cart->get_cart_hash(),
			'cart_count' => WC()->cart->get_cart_contents_count(),
			'message'    => $has_source_product
				? __( 'Produto adicionado com o desconto do conjunto.', 'gstore' )
				: __( 'Produto adicionado ao carrinho. O desconto vale ao comprar junto com o produto principal.', 'gstore' ),
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

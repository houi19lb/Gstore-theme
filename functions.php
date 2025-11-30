<?php
/**
 * Funções principais do child theme Gstore.
 *
 * @package Gstore
 * 
 * ============================================
 * CONFIGURAÇÃO DO WOOCOMMERCE
 * ============================================
 * Sistema: Blocos Gutenberg (Product Collection)
 * Versão WooCommerce: 9.4.0+
 * Verificado em: 2025-11-15
 * 
 * IMPORTANTE:
 * - Este projeto usa BLOCOS do WooCommerce, não loop clássico
 * - Páginas criadas no Editor de Blocos (Gutenberg)
 * - Templates PHP clássicos (content-product.php) NÃO são usados
 * - Customizações de produtos via CSS (.wc-block-*)
 * - Estilos críticos inline via wp_head (linhas 140-224)
 * 
 * ARQUIVOS RELEVANTES:
 * - style.css (linhas 473-671) - Estilos para blocos
 * - functions.php (linhas 140-224) - Estilos críticos inline
 * - BLOCOS-WOOCOMMERCE.md - Documentação completa
 * ============================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configurações iniciais do tema filho.
 */
function gstore_after_setup_theme() {
	load_child_theme_textdomain( 'gstore', get_stylesheet_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );

	// WooCommerce.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	
	// Tamanho de imagem específico para banners (alta qualidade, sem crop)
	// Usa dimensões grandes mas sem forçar crop, permitindo que a imagem original seja usada
	add_image_size( 'gstore-banner-full', 2560, 1440, false );
}
add_action( 'after_setup_theme', 'gstore_after_setup_theme' );

/**
 * Enfileira estilos do tema pai e do child theme.
 * 
 * Nova estrutura modular:
 * 1. Sistema de tokens e base (gstore-main.css)
 * 2. Style.css legado (compatibilidade)
 * 3. Estilos específicos de página (cart, checkout, etc.)
 */
function gstore_enqueue_styles() {
	$parent_handle = 'twentytwentyfive-style';
	$parent_theme  = wp_get_theme( 'twentytwentyfive' );
	$theme_version = wp_get_theme()->get( 'Version' );

	// Tema pai
	wp_enqueue_style(
		$parent_handle,
		get_template_directory_uri() . '/style.css',
		array(),
		$parent_theme->get( 'Version' )
	);

	// Font Awesome
	wp_enqueue_style(
		'gstore-fontawesome',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
		array(),
		'6.5.1'
	);

	// Sistema modular Gstore (tokens, base, utilities, components, layouts)
	wp_enqueue_style(
		'gstore-main',
		get_theme_file_uri( 'assets/css/gstore-main.css' ),
		array( $parent_handle, 'gstore-fontawesome' ),
		$theme_version
	);

	// Style.css principal (contém estilos legados que ainda não foram migrados)
	wp_enqueue_style(
		'gstore-style',
		get_stylesheet_uri(),
		array( 'gstore-main' ),
		$theme_version
	);

	// Header CSS - carregado por último para ter prioridade sobre estilos legados
	wp_enqueue_style(
		'gstore-header-css',
		get_theme_file_uri( 'assets/css/layouts/header.css' ),
		array( 'gstore-style' ),
		$theme_version
	);

	// CSS da página de minha conta (login/registro)
	if ( class_exists( 'WooCommerce' ) && function_exists( 'is_account_page' ) && is_account_page() ) {
		wp_enqueue_style(
			'gstore-my-account-css',
			get_theme_file_uri( 'assets/css/my-account.css' ),
			array( 'gstore-style' ),
			$theme_version
		);
	}
}
add_action( 'wp_enqueue_scripts', 'gstore_enqueue_styles' );

/**
 * Enfileira scripts customizados.
 */
function gstore_enqueue_scripts() {
	wp_enqueue_script(
		'gstore-header',
		get_theme_file_uri( 'assets/js/header.js' ),
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	// Passa URLs dinâmicas do WooCommerce para o JavaScript
	// Isso corrige os links hardcoded nos templates FSE quando o idioma muda
	if ( class_exists( 'WooCommerce' ) ) {
		$myaccount_url = wc_get_page_permalink( 'myaccount' );
		wp_localize_script(
			'gstore-header',
			'gstoreAccountUrls',
			array(
				'myAccount' => $myaccount_url ? $myaccount_url : home_url( '/minha-conta/' ),
				'orders'    => $myaccount_url ? wc_get_endpoint_url( 'orders', '', $myaccount_url ) : home_url( '/minha-conta/orders/' ),
			)
		);
	}

	if ( is_front_page() ) {
		wp_enqueue_script(
			'gstore-home-hero',
			get_theme_file_uri( 'assets/js/home-hero.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);
		wp_enqueue_script(
			'gstore-home-benefits',
			get_theme_file_uri( 'assets/js/home-benefits.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);

		wp_enqueue_script(
			'gstore-home-products-carousel',
			get_theme_file_uri( 'assets/js/home-products-carousel.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);
	}

	// Script dos cards de produto
	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_script(
			'gstore-product-card',
			get_theme_file_uri( 'assets/js/product-card.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_script(
				'gstore-single-product',
				get_theme_file_uri( 'assets/js/single-product.js' ),
				array(),
				wp_get_theme()->get( 'Version' ),
				true
			);
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			wp_enqueue_script(
				'gstore-cart',
				get_theme_file_uri( 'assets/js/cart.js' ),
				array(),
				wp_get_theme()->get( 'Version' ),
				true
			);
		}

		// Script da página de minha conta (login/registro)
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_enqueue_script(
				'gstore-my-account',
				get_theme_file_uri( 'assets/js/my-account.js' ),
				array(),
				wp_get_theme()->get( 'Version' ),
				true
			);
		}

		// Script da página de catálogo (filtros retráteis mobile)
		// Carrega se for qualquer página de catálogo ou se tiver a classe Gstore-catalog-shell
		$is_catalog_page = false;
		if ( function_exists( 'is_page' ) ) {
			// Páginas estáticas de catálogo
			$catalog_pages = array( 'catalogo', 'loja', 'ofertas' );
			$catalog_templates = array( 'page-catalogo', 'page-loja', 'page-ofertas' );
			
			$is_catalog_page = is_page( $catalog_pages );
			
			// Verifica também pelo template
			if ( ! $is_catalog_page && is_page() ) {
				$template = get_page_template_slug();
				foreach ( $catalog_templates as $tpl ) {
					if ( $template === $tpl || $template === $tpl . '.html' ) {
						$is_catalog_page = true;
						break;
					}
				}
			}
		}
		
		// Também verifica se é uma página de shop/archive do WooCommerce
		if ( ! $is_catalog_page && function_exists( 'is_shop' ) ) {
			$is_catalog_page = is_shop() || is_product_category() || is_product_tag();
		}
		
		if ( $is_catalog_page ) {
			wp_enqueue_script(
				'gstore-catalog-filters',
				get_theme_file_uri( 'assets/js/catalog-filters.js' ),
				array(),
				wp_get_theme()->get( 'Version' ),
				true
			);
		}
	}

	// Localizar script para AJAX do WooCommerce
	if ( class_exists( 'WooCommerce' ) ) {
		wp_localize_script(
			'gstore-header',
			'gstore_wc',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'cart_url'   => wc_get_cart_url(),
				'cart_count' => WC()->cart->get_cart_contents_count(),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'gstore_enqueue_scripts' );

/**
 * Atualiza o fragmento do carrinho para refletir mudanças em tempo real.
 *
 * @param array $fragments Fragmentos de carrinho.
 * @return array
 */
function gstore_cart_count_fragments( $fragments ) {
	$cart_count = WC()->cart->get_cart_contents_count();
	
	ob_start();
	?>
	<span class="Gstore-cart-count" aria-label="<?php echo esc_attr( sprintf( _n( '%d item no carrinho', '%d itens no carrinho', $cart_count, 'gstore' ), $cart_count ) ); ?>">
		<?php echo esc_html( $cart_count ); ?>
	</span>
	<?php
	$fragments['.Gstore-cart-count'] = ob_get_clean();
	
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'gstore_cart_count_fragments' );

/**
 * Remove o breadcrumb padrão do WooCommerce.
 */
function gstore_remove_default_breadcrumb() {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}
add_action( 'init', 'gstore_remove_default_breadcrumb' );

/**
 * Remove o texto de privacidade do formulário de registro.
 * O texto será exibido em um modal ao invés de diretamente no formulário.
 */
function gstore_remove_registration_privacy_text() {
	remove_action( 'woocommerce_register_form', 'wc_registration_privacy_policy_text', 20 );
}
add_action( 'init', 'gstore_remove_registration_privacy_text' );

/**
 * Remove a tag "Oferta" (onsale badge) da página de produto único.
 */
function gstore_remove_sale_flash_on_single_product() {
	if ( function_exists( 'is_product' ) && is_product() ) {
		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
	}
}
add_action( 'wp', 'gstore_remove_sale_flash_on_single_product' );

/**
 * Força o uso do template customizado de produto (Gstore).
 * Remove esta função depois que os cards estiverem funcionando.
 *
 * @param string $template      Caminho do template.
 * @param string $template_name Nome do template.
 * @param string $template_path Caminho base dos templates.
 * @return string
 */
function gstore_force_custom_product_template( $template, $template_name, $template_path ) {
	if ( 'content-product.php' === $template_name ) {
		$custom_template = get_theme_file_path( 'woocommerce/content-product.php' );
		if ( file_exists( $custom_template ) ) {
			return $custom_template;
		}
	}
	return $template;
}
add_filter( 'woocommerce_locate_template', 'gstore_force_custom_product_template', 10, 3 );

/**
 * Força exibição de estrelas mesmo sem avaliações nos blocos.
 *
 * @param string $html HTML do bloco de avaliação.
 * @param array  $attributes Atributos do bloco.
 * @param object $product Produto WooCommerce.
 * @return string
 */
function gstore_always_show_rating_stars( $html, $attributes, $product ) {
	if ( empty( $html ) ) {
		// Se não há avaliações, gerar HTML de estrelas vazias
		$html = '<div class="wc-block-components-product-rating">';
		$html .= '<div class="wc-block-components-product-rating__stars">';
		$html .= '<span>★★★★★</span>';
		$html .= '</div>';
		$html .= '</div>';
	}
	return $html;
}
add_filter( 'render_block_woocommerce/product-rating', 'gstore_always_show_rating_stars', 10, 3 );

/**
 * Adiciona informações de pagamento ao bloco de preço.
 *
 * @param string $html HTML do bloco de preço.
 * @param array  $block_content Conteúdo do bloco.
 * @param object $block Objeto do bloco.
 * @return string
 */
function gstore_add_payment_info_to_price( $html, $block_content, $block ) {
	// Verifica se é o bloco de preço
	if ( empty( $html ) || strpos( $html, 'woocommerce-Price-amount' ) === false ) {
		return $html;
	}
	
	// Verifica se já tem as classes customizadas (evita duplicação)
	if ( strpos( $html, 'Gstore-payment-label' ) !== false ) {
		return $html;
	}
	
	// Tenta obter o produto do contexto
	$product = null;
	if ( isset( $block->context['postId'] ) ) {
		$product = wc_get_product( $block->context['postId'] );
	}
	
	// Se não conseguir pelo contexto, tenta pegar o produto global
	if ( ! $product ) {
		global $product;
	}
	
	// Calcula o valor da parcela
	$installment_value = 0;
	$installment_text_content = 'ou em até 12x no cartão';
	
	if ( $product && is_a( $product, 'WC_Product' ) ) {
		$price_value = floatval( $product->get_price() );
		if ( $price_value > 0 ) {
			$installment_value = $price_value / 12;
			$installment_text_content = 'ou em até 12x de ' . wc_price( $installment_value );
		}
	}
	
	// Cria os elementos de pagamento
	$payment_label = '<div class="Gstore-payment-label">À VISTA NO PIX</div>';
	$installment_text = '<div class="Gstore-installment-text">' . $installment_text_content . '</div>';
	
	// Encontra a div interna com a classe wc-block-components-product-price
	if ( strpos( $html, 'wc-block-components-product-price' ) !== false ) {
		// Adiciona o label antes do preço (logo após a abertura da div interna)
		$html = preg_replace(
			'/(<div[^>]*class="[^"]*wc-block-components-product-price[^"]*"[^>]*>\s*)/',
			'$1' . $payment_label,
			$html,
			1
		);
		
		// Adiciona o texto de parcelamento antes do fechamento das divs
		$html = preg_replace(
			'/(\s*<\/div>\s*<\/div>\s*)$/',
			$installment_text . '$1',
			$html,
			1
		);
	}
	
	return $html;
}
add_filter( 'render_block_woocommerce/product-price', 'gstore_add_payment_info_to_price', 10, 3 );

/**
 * Remove a formatação automática de parágrafos do WordPress.
 */
function gstore_disable_wpautop() {
	$filters = array(
		'the_content',
		'the_excerpt',
		'widget_text_content',
		'comment_text',
		'term_description',
		'woocommerce_short_description',
	);

	foreach ( $filters as $filter ) {
		remove_filter( $filter, 'wpautop' );
		remove_filter( $filter, 'shortcode_unautop' );
	}
}
add_action( 'init', 'gstore_disable_wpautop', 9 );

/**
 * Remove as tags <p> adicionadas automaticamente dentro dos cards personalizados.
 *
 * @param string $html HTML que contém os cards.
 * @return string
 */
function gstore_cleanup_shortcode_paragraphs( $html ) {
	if ( false === strpos( $html, 'Gstore-product-card__inner' ) ) {
		return $html;
	}

	if ( class_exists( 'DOMDocument' ) ) {
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();

		$has_mb_convert   = function_exists( 'mb_convert_encoding' );
		$needs_unwrap_div = ! $has_mb_convert;
		$content          = $has_mb_convert
			? mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' )
			: '<div>' . $html . '</div>';

		$dom->loadHTML(
			$content,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		$xpath      = new DOMXPath( $dom );
		$paragraphs = $xpath->query( '//*[contains(@class,"Gstore-product-card__inner")]//p' );

		foreach ( $paragraphs as $paragraph ) {
			while ( $paragraph->firstChild ) {
				$paragraph->parentNode->insertBefore( $paragraph->firstChild, $paragraph );
			}
			$paragraph->parentNode->removeChild( $paragraph );
		}

		$line_breaks = $xpath->query( '//*[contains(@class,"Gstore-product-card__inner")]//br' );

		foreach ( $line_breaks as $line_break ) {
			$class_attribute = $line_break->attributes ? $line_break->attributes->getNamedItem( 'class' ) : null;
			$should_keep     = $class_attribute && false !== strpos( $class_attribute->nodeValue, 'Gstore-keep-br' );

			if ( $should_keep ) {
				continue;
			}

			$line_break->parentNode->removeChild( $line_break );
		}

		$clean_html = $dom->saveHTML();

		if ( $needs_unwrap_div ) {
			$clean_html = preg_replace( '#^<div>(.*)</div>$#s', '$1', $clean_html );
		}

		libxml_clear_errors();

		return $clean_html;
	}

	$cleaned = preg_replace_callback(
		'#(<li[^>]*class="[^"]*Gstore-product-card[^"]*"[^>]*>)(.*?)(</li>)#si',
		static function ( $matches ) {
			$inner = preg_replace( '#</?p[^>]*>#i', '', $matches[2] );
			$inner = preg_replace_callback(
				'#<br[^>]*>#i',
				static function ( $br_matches ) {
					return false === stripos( $br_matches[0], 'Gstore-keep-br' ) ? '' : $br_matches[0];
				},
				$inner
			);
			return $matches[1] . $inner . $matches[3];
		},
		$html
	);

	return null === $cleaned ? $html : $cleaned;
}

/**
 * Garante que shortcodes de produtos não insiram <p> extras nos cards Gstore.
 *
 * @param string $output HTML gerado pelo shortcode.
 * @param string $tag    Nome do shortcode.
 * @return string
 */
function gstore_filter_products_shortcode_output( $output, $tag ) {
	$target_shortcodes = array(
		'products',
		'best_selling_products',
		'featured_products',
		'product_attribute',
		'product_categories',
		'product_category',
		'recent_products',
		'sale_products',
		'top_rated_products',
	);

	if ( ! in_array( $tag, $target_shortcodes, true ) ) {
		return $output;
	}

	return gstore_cleanup_shortcode_paragraphs( $output );
}
add_filter( 'do_shortcode_tag', 'gstore_filter_products_shortcode_output', 20, 2 );

/**
 * Remove os parágrafos extras também quando o conteúdo completo é renderizado.
 *
 * @param string $content Conteúdo da página/post.
 * @return string
 */
function gstore_cleanup_content_paragraphs( $content ) {
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return $content;
	}

	return gstore_cleanup_shortcode_paragraphs( $content );
}
add_filter( 'the_content', 'gstore_cleanup_content_paragraphs', 20 );

/**
 * Garante que o bloco de checkout esteja presente quando o conteúdo estiver vazio.
 *
 * @param string $content Conteúdo original da página.
 * @return string
 */
function gstore_ensure_checkout_block( $content ) {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $content;
	}

	if ( function_exists( 'has_shortcode' ) && has_shortcode( $content, 'woocommerce_checkout' ) ) {
		return $content;
	}

	$has_checkout_block = function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $content );

	if ( $has_checkout_block ) {
		return $content;
	}

	// Verifica se o shortcode já foi processado (procura pela classe do form)
	if ( false !== strpos( $content, 'woocommerce-checkout' ) ) {
		return $content;
	}

	$fallback_block = '[woocommerce_checkout]';

	return $content . do_shortcode( $fallback_block );
}
add_filter( 'the_content', 'gstore_ensure_checkout_block', 9 );

/**
 * Substitui o Checkout em bloco pelo shortcode clássico para ativar o campo CPF.
 *
 * @param string $content Conteúdo original da página.
 * @return string
 */
function gstore_force_classic_checkout_shortcode( $content ) {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $content;
	}

	if ( has_shortcode( $content, 'woocommerce_checkout' ) ) {
		return $content;
	}

	$contains_checkout_block = function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $content );

	if ( $contains_checkout_block ) {
		return do_shortcode( '[woocommerce_checkout]' );
	}

	return $content;
}
add_filter( 'the_content', 'gstore_force_classic_checkout_shortcode', 5 );

/**
 * Envolve o bloco de resumo do checkout com o card customizado da Gstore.
 *
 * @param string $block_content Conteúdo original do bloco.
 * @param array  $block         Dados do bloco.
 * @return string
 */
function gstore_wrap_checkout_order_summary_block( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'woocommerce/checkout-order-summary-block' !== $block['blockName'] ) {
		return $block_content;
	}

	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $block_content;
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		return $block_content;
	}

	$cart = WC()->cart;

	if ( ! $cart ) {
		return $block_content;
	}

	$totals          = $cart->get_totals();
	$raw_total       = isset( $totals['total'] ) ? (float) $totals['total'] : 0;
	$formatted_total = wc_price( $raw_total );
	$items_count     = max( 0, $cart->get_cart_contents_count() );
	$items_meta      = sprintf(
		_n( 'Inclui %d item. Frete e descontos detalhados abaixo.', 'Inclui %d itens. Frete e descontos detalhados abaixo.', $items_count, 'gstore' ),
		$items_count
	);

	ob_start();
	?>
	<div class="Gstore-order-summary-card" aria-label="<?php esc_attr_e( 'Resumo do pedido', 'gstore' ); ?>">
		<header class="Gstore-order-summary-card__header">
			<div>
				<span class="Gstore-order-summary-card__eyebrow"><?php esc_html_e( 'Resumo do pedido', 'gstore' ); ?></span>
				<h2 class="Gstore-order-summary-card__title"><?php esc_html_e( 'Revise antes de finalizar', 'gstore' ); ?></h2>
				<p class="Gstore-order-summary-card__description"><?php esc_html_e( 'Confira itens, valores e opções de envio antes de concluir.', 'gstore' ); ?></p>
			</div>

			<div class="Gstore-order-summary-card__total" aria-live="polite">
				<span class="Gstore-order-summary-card__total-label"><?php esc_html_e( 'Total do pedido', 'gstore' ); ?></span>
				<span class="Gstore-order-summary-card__total-amount"><?php echo wp_kses_post( $formatted_total ); ?></span>
				<span class="Gstore-order-summary-card__total-meta"><?php echo esc_html( $items_meta ); ?></span>
			</div>
		</header>

		<div class="Gstore-order-summary-card__content">
			<?php echo $block_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="Gstore-order-summary-card__assurance-grid" aria-label="<?php esc_attr_e( 'Garantias CAC Armas', 'gstore' ); ?>">
			<div class="Gstore-order-summary-card__assurance-card">
				<i class="fa-solid fa-headset" aria-hidden="true"></i>
				<div>
					<strong><?php esc_html_e( 'Atendimento dedicado', 'gstore' ); ?></strong>
					<span><?php esc_html_e( 'Equipe pronta para ajudar em cada etapa.', 'gstore' ); ?></span>
				</div>
			</div>
			<div class="Gstore-order-summary-card__assurance-card">
				<i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
				<div>
					<strong><?php esc_html_e( 'Compra segura', 'gstore' ); ?></strong>
					<span><?php esc_html_e( 'Pagamento protegido com criptografia.', 'gstore' ); ?></span>
				</div>
			</div>
			<div class="Gstore-order-summary-card__assurance-card">
				<i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
				<div>
					<strong><?php esc_html_e( 'Envio rastreado', 'gstore' ); ?></strong>
					<span><?php esc_html_e( 'Acompanhe o pedido em tempo real.', 'gstore' ); ?></span>
				</div>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}
add_filter( 'render_block', 'gstore_wrap_checkout_order_summary_block', 10, 2 );

/**
 * Substitui o Carrinho em bloco pelo shortcode clássico para ativar o layout Gstore.
 *
 * @param string $content Conteúdo original da página.
 * @return string
 */
function gstore_force_classic_cart_shortcode( $content ) {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return $content;
	}

	if ( has_shortcode( $content, 'woocommerce_cart' ) ) {
		return $content;
	}

	$contains_cart_block  = function_exists( 'has_block' ) && has_block( 'woocommerce/cart', $content );
	$contains_cart_markup = false !== stripos( $content, 'wp-block-woocommerce-cart' ) || false !== stripos( $content, 'wp-block-woocommerce-filled-cart-block' );

	if ( ! $contains_cart_block && ! $contains_cart_markup ) {
		$stripped_content = trim( wp_strip_all_tags( $content ) );

		if ( '' === $stripped_content ) {
			return do_shortcode( '[woocommerce_cart]' );
		}

		return $content;
	}

	return do_shortcode( '[woocommerce_cart]' );
}
add_filter( 'the_content', 'gstore_force_classic_cart_shortcode', 5 );

/**
 * Remove o título da página do carrinho (evita duplicação com o header customizado).
 * 
 * O WordPress Block Theme renderiza automaticamente um h1.wp-block-post-title
 * que não queremos na página do carrinho, pois já temos nosso header customizado.
 */
function gstore_remove_cart_page_title( $title, $id = null ) {
	// Só remove se estiver na página do carrinho
	if ( function_exists( 'is_cart' ) && is_cart() && in_the_loop() && is_main_query() ) {
		return '';
	}
	return $title;
}
add_filter( 'the_title', 'gstore_remove_cart_page_title', 10, 2 );

/**
 * Adiciona classe ao body para página do carrinho com template PHP.
 */
function gstore_cart_body_class( $classes ) {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		$classes[] = 'gstore-cart-template';
	}
	return $classes;
}
add_filter( 'body_class', 'gstore_cart_body_class' );

/**
 * Gera script de debug para analisar estrutura HTML do carrinho.
 */
function gstore_get_cart_debug_script() {
	return <<<'SCRIPT'
(function() {
    console.clear();
    console.log('%c🛒 DIAGNÓSTICO DO CARRINHO GSTORE', 'font-size: 20px; font-weight: bold; color: #c9a43a;');
    console.log('');
    
    // Função para mostrar computed styles
    function getStyles(el, props) {
        if (!el) return 'ELEMENTO NÃO ENCONTRADO';
        const cs = getComputedStyle(el);
        return props.map(p => `${p}: ${cs[p]}`).join(', ');
    }
    
    // Função para mostrar árvore de elementos
    function showTree(el, depth = 0) {
        if (!el || depth > 6) return;
        const indent = '  '.repeat(depth);
        const tag = el.tagName?.toLowerCase() || 'text';
        const classes = el.className ? `.${el.className.split(' ').join('.')}` : '';
        const id = el.id ? `#${el.id}` : '';
        console.log(`${indent}${tag}${id}${classes}`);
    }
    
    console.log('%c📐 ESTRUTURA HTML:', 'font-size: 14px; font-weight: bold; color: #fff; background: #333; padding: 5px;');
    
    // Encontra elementos chave
    const body = document.body;
    const wpSiteBlocks = document.querySelector('.wp-site-blocks');
    const main = document.querySelector('main');
    const cartPage = document.querySelector('.gstore-cart-page, .Gstore-cart-page');
    const cartShell = document.querySelector('.Gstore-cart-shell');
    const cartContainer = document.querySelector('.Gstore-cart-container');
    const cartHeader = document.querySelector('.Gstore-cart-header');
    
    console.log('%cBody classes:', 'color: #86efac;', body.className);
    console.log('');
    
    // Mostra hierarquia
    console.log('%c🌳 HIERARQUIA DE ELEMENTOS:', 'font-size: 14px; font-weight: bold; color: #fff; background: #333; padding: 5px;');
    
    if (cartContainer) {
        let el = cartContainer;
        let path = [];
        while (el && el !== document.body) {
            const tag = el.tagName.toLowerCase();
            const cls = el.className ? '.' + el.className.split(' ').slice(0, 2).join('.') : '';
            path.unshift(`${tag}${cls}`);
            el = el.parentElement;
        }
        console.log('Caminho até .Gstore-cart-container:');
        path.forEach((p, i) => console.log('  '.repeat(i) + '└─ ' + p));
    }
    
    console.log('');
    console.log('%c📏 ESTILOS COMPUTADOS:', 'font-size: 14px; font-weight: bold; color: #fff; background: #333; padding: 5px;');
    
    const propsToCheck = ['width', 'maxWidth', 'marginLeft', 'marginRight', 'paddingLeft', 'paddingRight'];
    
    const elements = {
        'body': body,
        '.wp-site-blocks': wpSiteBlocks,
        'main': main,
        '.gstore-cart-page': cartPage,
        '.Gstore-cart-shell': cartShell,
        '.Gstore-cart-container': cartContainer,
        '.Gstore-cart-header': cartHeader
    };
    
    Object.entries(elements).forEach(([name, el]) => {
        if (el) {
            const cs = getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            console.log(`%c${name}:`, 'color: #fbbf24; font-weight: bold;');
            console.log(`  Largura real: ${rect.width}px`);
            console.log(`  max-width: ${cs.maxWidth}`);
            console.log(`  width: ${cs.width}`);
            console.log(`  margin: ${cs.marginTop} ${cs.marginRight} ${cs.marginBottom} ${cs.marginLeft}`);
            console.log(`  padding: ${cs.paddingTop} ${cs.paddingRight} ${cs.paddingBottom} ${cs.paddingLeft}`);
            console.log('');
        } else {
            console.log(`%c${name}: NÃO ENCONTRADO`, 'color: #f87171;');
        }
    });
    
    console.log('%c🔍 PROBLEMA DE CENTRALIZAÇÃO:', 'font-size: 14px; font-weight: bold; color: #fff; background: #dc2626; padding: 5px;');
    
    if (cartContainer) {
        const cs = getComputedStyle(cartContainer);
        const rect = cartContainer.getBoundingClientRect();
        const parentRect = cartContainer.parentElement.getBoundingClientRect();
        
        console.log(`Container largura: ${rect.width}px`);
        console.log(`Container max-width computado: ${cs.maxWidth}`);
        console.log(`Container margin-left: ${cs.marginLeft}`);
        console.log(`Container margin-right: ${cs.marginRight}`);
        console.log(`Parent largura: ${parentRect.width}px`);
        console.log(`Espaço à esquerda: ${rect.left}px`);
        console.log(`Espaço à direita: ${window.innerWidth - rect.right}px`);
        
        if (cs.marginLeft === '0px' && cs.marginRight === '0px') {
            console.log('%c⚠️ MARGIN AUTO NÃO ESTÁ FUNCIONANDO!', 'color: #f87171; font-weight: bold;');
            console.log('O container tem margin 0 em vez de auto. Alguma regra CSS está sobrescrevendo.');
        }
        
        if (rect.left < 50) {
            console.log('%c⚠️ CONTAINER ESTÁ COLADO À ESQUERDA!', 'color: #f87171; font-weight: bold;');
        }
    }
    
    // Verifica regras CSS que podem estar causando problema
    console.log('');
    console.log('%c🎨 VERIFICANDO REGRAS CSS:', 'font-size: 14px; font-weight: bold; color: #fff; background: #333; padding: 5px;');
    
    if (cartContainer) {
        // Tenta encontrar a regra que está aplicando margin
        const sheets = document.styleSheets;
        let foundRules = [];
        
        for (let sheet of sheets) {
            try {
                const rules = sheet.cssRules || sheet.rules;
                for (let rule of rules) {
                    if (rule.selectorText && rule.selectorText.includes('Gstore-cart')) {
                        if (rule.style.marginLeft || rule.style.marginRight || rule.style.margin) {
                            foundRules.push({
                                selector: rule.selectorText,
                                margin: rule.style.margin || `L:${rule.style.marginLeft} R:${rule.style.marginRight}`,
                                source: sheet.href || 'inline'
                            });
                        }
                    }
                }
            } catch(e) {}
        }
        
        if (foundRules.length > 0) {
            console.log('Regras CSS que afetam margin do carrinho:');
            foundRules.forEach(r => {
                console.log(`  ${r.selector}: margin ${r.margin}`);
                console.log(`    Fonte: ${r.source}`);
            });
        }
    }
    
    console.log('');
    console.log('%c✅ FIM DO DIAGNÓSTICO', 'font-size: 14px; font-weight: bold; color: #86efac;');
})();
SCRIPT;
}

/**
 * Renderiza overlay de debug na página do carrinho.
 */
function gstore_render_cart_debug_overlay() {
	if ( ! isset( $_GET['gstore_cart_debug'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	?>
	<div id="gstore-cart-debug-overlay" style="
		position: fixed;
		top: 32px;
		right: 20px;
		width: 400px;
		max-height: 80vh;
		background: #1d2327;
		color: #f0f0f1;
		border-radius: 8px;
		box-shadow: 0 10px 40px rgba(0,0,0,0.5);
		z-index: 999999;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		font-size: 13px;
		overflow: hidden;
	">
		<div style="background: #c9a43a; color: #000; padding: 12px 16px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
			<span>🛒 Debug do Carrinho</span>
			<button onclick="this.closest('#gstore-cart-debug-overlay').remove()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #000;">&times;</button>
		</div>
		<div id="gstore-debug-content" style="padding: 16px; max-height: calc(80vh - 50px); overflow-y: auto;">
			<p style="margin: 0;">Carregando...</p>
		</div>
	</div>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const content = document.getElementById('gstore-debug-content');
		let html = '';
		
		function addSection(title) {
			html += `<h4 style="color: #c9a43a; margin: 16px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #3c434a;">${title}</h4>`;
		}
		
		function addRow(label, value, isError = false) {
			const color = isError ? '#f87171' : '#86efac';
			html += `<div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #3c434a;">
				<span style="color: #9ca3af;">${label}</span>
				<span style="color: ${color}; font-family: monospace;">${value}</span>
			</div>`;
		}
		
		// Body classes
		addSection('Body Classes');
		const bodyClasses = document.body.className.split(' ').filter(c => c.includes('cart') || c.includes('gstore') || c.includes('woocommerce'));
		html += `<code style="display: block; background: #000; padding: 8px; border-radius: 4px; word-break: break-all; font-size: 11px;">${bodyClasses.join(' ')}</code>`;
		
		// Elementos e seus estilos
		const elements = [
			{ name: '.Gstore-cart-shell', el: document.querySelector('.Gstore-cart-shell') },
			{ name: '.Gstore-cart-container', el: document.querySelector('.Gstore-cart-container') },
			{ name: '.Gstore-cart-header', el: document.querySelector('.Gstore-cart-header') },
		];
		
		elements.forEach(({name, el}) => {
			addSection(name);
			if (el) {
				const cs = getComputedStyle(el);
				const rect = el.getBoundingClientRect();
				addRow('Largura real', `${Math.round(rect.width)}px`);
				addRow('max-width', cs.maxWidth);
				addRow('margin-left', cs.marginLeft, cs.marginLeft === '0px');
				addRow('margin-right', cs.marginRight, cs.marginRight === '0px');
				addRow('padding-left', cs.paddingLeft);
				addRow('padding-right', cs.paddingRight);
				addRow('Posição X', `${Math.round(rect.left)}px`);
			} else {
				html += `<p style="color: #f87171;">Elemento não encontrado!</p>`;
			}
		});
		
		// Diagnóstico
		addSection('🔍 Diagnóstico');
		const container = document.querySelector('.Gstore-cart-container');
		if (container) {
			const cs = getComputedStyle(container);
			const rect = container.getBoundingClientRect();
			const isCentered = Math.abs((window.innerWidth - rect.width) / 2 - rect.left) < 50;
			
			if (isCentered) {
				html += `<p style="color: #86efac;">✅ Container está centralizado!</p>`;
			} else {
				html += `<p style="color: #f87171;">❌ Container NÃO está centralizado!</p>`;
				html += `<p style="color: #9ca3af; font-size: 12px;">Espaço esquerda: ${Math.round(rect.left)}px</p>`;
				html += `<p style="color: #9ca3af; font-size: 12px;">Espaço direita: ${Math.round(window.innerWidth - rect.right)}px</p>`;
				
				if (cs.marginLeft === '0px') {
					html += `<p style="color: #fbbf24;">⚠️ margin-left está 0px (deveria ser auto)</p>`;
				}
			}
		}
		
		// Hierarquia
		addSection('🌳 Hierarquia HTML');
		if (container) {
			let el = container;
			let path = [];
			while (el && el !== document.body) {
				const tag = el.tagName.toLowerCase();
				const cls = el.className ? '.' + el.className.split(' ')[0] : '';
				path.unshift(`${tag}${cls}`);
				el = el.parentElement;
			}
			html += `<code style="display: block; background: #000; padding: 8px; border-radius: 4px; font-size: 10px; line-height: 1.6;">`;
			path.forEach((p, i) => {
				html += `${'&nbsp;&nbsp;'.repeat(i)}└─ ${p}<br>`;
			});
			html += `</code>`;
		}
		
		content.innerHTML = html;
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'gstore_render_cart_debug_overlay' );

/**
 * Adiciona estilos críticos inline para garantir que os cards apareçam.
 * Funciona tanto com blocos quanto com loop clássico.
 */
function gstore_critical_product_styles() {
	if ( ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_search() ) {
		return;
	}
	?>
	<style id="Gstore-critical-styles">
		.Gstore-products-shell {
			background: #ffffff !important;
			padding: clamp(24px, 4vw, 64px) clamp(16px, 4vw, 48px);
		}

		.Gstore-products-shell .Gstore-products-grid {
			max-width: 1400px;
			margin: 0 auto;
			padding-left: var(--gstore-container-padding-inline, 20px);
			padding-right: var(--gstore-container-padding-inline, 20px);
		}

		.Gstore-products-grid ul.products,
		.Gstore-products-grid .wc-block-product-template,
		.Gstore-products-grid ul.wc-block-product-template {
			display: grid !important;
			grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
			gap: 24px !important;
			list-style: none !important;
			padding: 0 !important;
			margin: 0 !important;
			width: 100%;
		}

		.Gstore-products-grid .wc-block-product,
		.Gstore-products-grid li.wc-block-product,
		.Gstore-product-card {
			background: #fff !important;
			border-radius: 4px !important;
			border: 1px solid #e0e0e0 !important;
			display: flex !important;
			flex-direction: column !important;
			overflow: hidden !important;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
			transition: all 0.2s ease !important;
			position: relative !important;
			padding: 0 !important;
			margin: 0 !important;
			width: auto !important;
		}

		.Gstore-products-grid .wc-block-product:hover,
		.Gstore-product-card:hover {
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
			transform: translateY(-2px) !important;
		}

		.Gstore-products-grid .wc-block-product .has-text-align-center,
		.Gstore-products-grid .wc-block-product [data-text-align="center"],
		.Gstore-products-grid .wc-block-product .wp-block-post-title,
		.Gstore-products-grid .wc-block-product .wp-block-woocommerce-product-price,
		.Gstore-products-grid .wc-block-product .wc-block-components-product-price {
			text-align: left !important;
		}

		.Gstore-product-card__inner {
			background: #fff !important;
			border-radius: 4px !important;
			border: 1px solid #e0e0e0 !important;
			display: flex !important;
			flex-direction: column !important;
			overflow: hidden !important;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
			transition: all 0.2s ease !important;
			height: 100% !important;
		}

		.Gstore-products-grid ul.products li.product {
			margin: 0 !important;
			padding: 0 !important;
			width: auto !important;
			float: none !important;
		}

		@media (max-width: 1024px) {
			.Gstore-products-grid ul.products,
			.Gstore-products-grid .wc-block-product-template,
			.Gstore-products-grid ul.wc-block-product-template {
				grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
			}
		}

		@media (max-width: 640px) {
			.Gstore-products-grid ul.products,
			.Gstore-products-grid .wc-block-product-template,
			.Gstore-products-grid ul.wc-block-product-template {
				grid-template-columns: 1fr !important;
				gap: 16px !important;
			}

			.Gstore-product-card__inner,
			.Gstore-products-grid .wc-block-product {
				min-height: auto !important;
			}
		}
	</style>
	<?php
}
add_action( 'wp_head', 'gstore_critical_product_styles', 999 );

/**
 * Estilos e bibliotecas específicos do checkout.
 */
function gstore_enqueue_checkout_assets() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$theme_version = wp_get_theme()->get( 'Version' );

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		// CSS do checkout base
		wp_enqueue_style(
			'gstore-checkout',
			get_theme_file_uri( 'assets/css/checkout.css' ),
			array( 'gstore-style', 'gstore-fontawesome' ),
			$theme_version
		);

		// CSS do checkout em 3 etapas
		wp_enqueue_style(
			'gstore-checkout-steps',
			get_theme_file_uri( 'assets/css/checkout-steps.css' ),
			array( 'gstore-checkout' ),
			$theme_version
		);

		wp_enqueue_script(
			'gstore-checkout-cleanup',
			get_theme_file_uri( 'assets/js/checkout-cleanup.js' ),
			array(),
			$theme_version,
			true
		);

		wp_enqueue_script(
			'gstore-checkout-gestalt',
			get_theme_file_uri( 'assets/js/checkout-gestalt.js' ),
			array( 'jquery' ),
			$theme_version,
			true
		);

		// JavaScript do checkout em 3 etapas
		wp_enqueue_script(
			'gstore-checkout-steps',
			get_theme_file_uri( 'assets/js/checkout-steps.js' ),
			array( 'jquery' ),
			$theme_version,
			true
		);
	}

	if ( function_exists( 'is_cart' ) && is_cart() ) {
		wp_enqueue_style(
			'gstore-cart',
			get_theme_file_uri( 'assets/css/cart.css' ),
			array( 'gstore-style', 'gstore-fontawesome' ),
			$theme_version
		);
	}
    
    // Script de Auto-fill do CEP
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        wp_enqueue_script(
            'gstore-cep-autofill',
            get_theme_file_uri( 'assets/js/cep-autofill.js' ),
            array( 'jquery' ),
            $theme_version,
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'gstore_enqueue_checkout_assets', 40 );

/**
 * Move o texto de privacidade para baixo do botão de finalizar compra.
 */
function gstore_move_privacy_policy_text() {
    remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
    add_action( 'woocommerce_review_order_after_submit', 'wc_checkout_privacy_policy_text', 20 );
}
add_action( 'init', 'gstore_move_privacy_policy_text' );

/**
 * Customiza os campos do checkout:
 * - Remove o campo de país
 * - Move o CEP para o topo
 */
function gstore_customize_checkout_fields( $fields ) {
    // Remover país
    unset( $fields['billing']['billing_country'] );
    unset( $fields['shipping']['shipping_country'] );

    // Reordenar CEP para o topo da seção de endereço (prioridade 45, logo após CPF que é 35)
    if ( isset( $fields['billing']['billing_postcode'] ) ) {
        $fields['billing']['billing_postcode']['priority'] = 45;
        $fields['billing']['billing_postcode']['class'] = array('form-row-wide', 'address-field');
        $fields['billing']['billing_postcode']['clear'] = true;
    }
    
    if ( isset( $fields['shipping']['shipping_postcode'] ) ) {
        $fields['shipping']['shipping_postcode']['priority'] = 45;
        $fields['shipping']['shipping_postcode']['class'] = array('form-row-wide', 'address-field');
        $fields['shipping']['shipping_postcode']['clear'] = true;
    }

    // Ajustar prioridade do endereço para vir depois do CEP
    if ( isset( $fields['billing']['billing_address_1'] ) ) {
        $fields['billing']['billing_address_1']['priority'] = 50;
    }
    
    if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
        $fields['shipping']['shipping_address_1']['priority'] = 50;
    }

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'gstore_customize_checkout_fields', 1000 );

/**
 * Verifica se as páginas essenciais existem na inicialização.
 * 
 * Nota: Use o menu "Setup Gstore" para criar todas as páginas de uma vez.
 * Esta função apenas cria páginas essenciais do WooCommerce se não existirem.
 */
function gstore_check_essential_pages() {
	// Só roda no admin para evitar sobrecarga no frontend
	if ( ! is_admin() ) {
		return;
	}
	
	// Só verifica uma vez por sessão usando transient
	$checked = get_transient( 'gstore_pages_checked' );
	if ( $checked ) {
		return;
	}
	
	// Verifica se a página de catálogo existe (para compatibilidade com versões anteriores)
	$catalog_page = get_page_by_path( 'catalogo' );
	if ( ! $catalog_page ) {
		wp_insert_post( array(
			'post_title'   => 'Catálogo',
			'post_name'    => 'catalogo',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		) );
	}
	
	// Define transient para não verificar novamente por 1 hora
	set_transient( 'gstore_pages_checked', true, HOUR_IN_SECONDS );
}
add_action( 'admin_init', 'gstore_check_essential_pages' );

/**
 * Gateway Blu (Link de Pagamento).
 */
if ( class_exists( 'WooCommerce' ) && class_exists( 'WC_Payment_Gateway' ) ) {

require_once get_theme_file_path( 'inc/class-gstore-blu-payment-gateway.php' );
} else {

}

/**
 * Filtro para deixar apenas a Blu como gateway (Opcional/Solicitado).
 */
require_once get_theme_file_path( 'inc/blu-filter.php' );

/**
 * Registra o suporte a Blocos para o Gateway Blu.
 */
add_action( 'woocommerce_blocks_payment_method_type_registration', 'gstore_blu_register_payment_method_type' );
function gstore_blu_register_payment_method_type( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {

	// Verifica se a classe AbstractPaymentMethodType está disponível
	if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		// Loga o erro para debug apenas se WP_DEBUG estiver ativo
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'GSTORE BLU: AbstractPaymentMethodType class not available. WooCommerce Blocks may not be installed or version incompatible.',
					array( 'source' => 'gstore-blu-blocks' )
				);
			} elseif ( function_exists( 'error_log' ) ) {
				error_log( 'GSTORE BLU: AbstractPaymentMethodType class not available. WooCommerce Blocks may not be installed or version incompatible.' );
			}
		}
		return;
	}

	try {
		// Inclui o arquivo da classe de integração com blocos
		$blocks_file = get_theme_file_path( 'inc/class-gstore-blu-payment-gateway-blocks.php' );
		
		if ( ! file_exists( $blocks_file ) ) {
			throw new Exception( 'Blocks integration file not found: ' . $blocks_file );
		}

		require_once $blocks_file;

		// Verifica se a classe foi carregada corretamente
		if ( ! class_exists( 'Gstore_Blu_Payment_Gateway_Blocks' ) ) {
			throw new Exception( 'Gstore_Blu_Payment_Gateway_Blocks class not loaded after including file' );
		}

		// Registra a integração
		$payment_method_registry->register( new Gstore_Blu_Payment_Gateway_Blocks() );

		// Loga sucesso apenas se debug estiver ativo
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				'GSTORE BLU: Blocks integration registered successfully',
				array( 'source' => 'gstore-blu-blocks' )
			);
		}

	} catch ( Exception $e ) {
		// Loga o erro apenas se WP_DEBUG estiver ativo
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'GSTORE BLU: Error registering blocks integration: ' . $e->getMessage(),
					array(
						'source' => 'gstore-blu-blocks',
						'trace'  => $e->getTraceAsString(),
					)
				);
			} elseif ( function_exists( 'error_log' ) ) {
				error_log( 'GSTORE BLU: Error registering blocks integration: ' . $e->getMessage() );
			}
		}
	}
}



/**
 * Adiciona campo CPF no checkout e salva no pedido.
 */
function gstore_add_cpf_field( $fields ) {
    // Se for o filtro woocommerce_billing_fields, o array é direto
    // Se for woocommerce_checkout_fields, tem 'billing'
    
    if ( isset( $fields['billing'] ) ) {
        $fields['billing']['billing_cpf'] = array(
            'label'       => __('CPF', 'gstore'),
            'placeholder' => _x('000.000.000-00', 'placeholder', 'gstore'),
            'required'    => true,
            'class'       => array('form-row-wide', 'address-field'),
            'clear'       => true,
            'priority'    => 35,
        );
    } else {
        $fields['billing_cpf'] = array(
            'label'       => __('CPF', 'gstore'),
            'placeholder' => _x('000.000.000-00', 'placeholder', 'gstore'),
            'required'    => true,
            'class'       => array('form-row-wide', 'address-field'),
            'clear'       => true,
            'priority'    => 35,
        );
    }
    
    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'gstore_add_cpf_field', 20 );
add_filter( 'woocommerce_billing_fields', 'gstore_add_cpf_field', 20 );

function gstore_save_cpf_field( $order_id ) {
    if ( ! empty( $_POST['billing_cpf'] ) ) {
        $cpf = preg_replace( '/[^0-9]/', '', $_POST['billing_cpf'] );
        update_post_meta( $order_id, 'billing_cpf', $cpf );
        update_post_meta( $order_id, '_billing_cpf', $cpf );
    }
}
add_action( 'woocommerce_checkout_update_order_meta', 'gstore_save_cpf_field' );

/**
 * Exibe o CPF no painel de administração do pedido.
 */
function gstore_display_cpf_admin_order_data( $order ) {
    $cpf = $order->get_meta( 'billing_cpf' );
    if ( $cpf ) {
        echo '<p><strong>' . __('CPF', 'gstore') . ':</strong> ' . esc_html( $cpf ) . '</p>';
    }
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'gstore_display_cpf_admin_order_data', 10, 1 );

/**
 * Endpoint AJAX para obter o resumo do carrinho no checkout de 3 etapas.
 */
function gstore_get_cart_summary_ajax() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( array( 'message' => 'WooCommerce não está ativo.' ) );
	}

	$cart = WC()->cart;

	if ( ! $cart ) {
		wp_send_json_error( array( 'message' => 'Carrinho não encontrado.' ) );
	}

	$items = array();
	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		$product = $cart_item['data'];
		$items[] = array(
			'key'      => $cart_item_key,
			'name'     => $product->get_name(),
			'quantity' => $cart_item['quantity'],
			'subtotal' => wc_price( $cart_item['line_subtotal'] ),
			'image'    => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
		);
	}

	$totals = $cart->get_totals();

	$response = array(
		'items_count' => $cart->get_cart_contents_count(),
		'items'       => $items,
		'total'       => wc_price( $totals['total'] ),
		'totals'      => array(
			'subtotal' => wc_price( $totals['subtotal'] ),
			'shipping' => $totals['shipping_total'] > 0 ? wc_price( $totals['shipping_total'] ) : null,
			'discount' => $totals['discount_total'] > 0 ? wc_price( $totals['discount_total'] ) : null,
		),
	);

	wp_send_json_success( $response );
}
add_action( 'wp_ajax_gstore_get_cart_summary', 'gstore_get_cart_summary_ajax' );
add_action( 'wp_ajax_nopriv_gstore_get_cart_summary', 'gstore_get_cart_summary_ajax' );

/**
 * ============================================
 * PÁGINA MINHA CONTA - CUSTOMIZAÇÕES
 * ============================================
 */

/**
 * Sobrescreve o template de navegação do WooCommerce.
 */
function gstore_custom_account_navigation() {
	wc_get_template( 'myaccount/navigation.php' );
}
remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation' );
add_action( 'woocommerce_account_navigation', 'gstore_custom_account_navigation' );

/**
 * Renomeia os itens do menu da conta.
 *
 * @param array $items Itens do menu.
 * @return array
 */
function gstore_rename_account_menu_items( $items ) {
	$items['dashboard']       = __( 'Painel', 'gstore' );
	$items['orders']          = __( 'Pedidos', 'gstore' );
	$items['downloads']       = __( 'Downloads', 'gstore' );
	$items['edit-address']    = __( 'Endereços', 'gstore' );
	$items['edit-account']    = __( 'Meus Dados', 'gstore' );
	$items['customer-logout'] = __( 'Sair', 'gstore' );
	
	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'gstore_rename_account_menu_items' );

/**
 * Adiciona classe body customizada para a página minha conta.
 *
 * @param array $classes Classes do body.
 * @return array
 */
function gstore_myaccount_body_class( $classes ) {
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$classes[] = 'gstore-myaccount-page';
	}
	return $classes;
}
add_filter( 'body_class', 'gstore_myaccount_body_class' );

/**
 * Força o navegador e eventuais caches de hospedagem a não armazenarem
 * a página "Minha Conta". Isso evita que o formulário de cadastro use
 * nonces expirados (comuns em hosts que ativam cache para visitantes).
 */
function gstore_disable_account_page_cache() {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( function_exists( 'wc_nocache_headers' ) ) {
		wc_nocache_headers();
	} else {
		nocache_headers();
	}
}
add_action( 'template_redirect', 'gstore_disable_account_page_cache', 0 );

/**
 * Exibe uma mensagem amigável quando o nonce do formulário expira.
 * Sem isso o WooCommerce simplesmente ignora o POST e nada acontece.
 */
function gstore_handle_expired_register_nonce() {
	if ( empty( $_POST['register'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	if ( ! function_exists( 'wc_add_notice' ) ) {
		return;
	}

	$nonce_value = '';

	if ( isset( $_POST['woocommerce-register-nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce_value = sanitize_text_field( wp_unslash( $_POST['woocommerce-register-nonce'] ) );
	} elseif ( isset( $_POST['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce_value = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
	}

	if ( $nonce_value && wp_verify_nonce( $nonce_value, 'woocommerce-register' ) ) {
		return;
	}

	wc_add_notice(
		__( 'Não conseguimos validar sua sessão de cadastro. Atualize a página e tente novamente.', 'gstore' ),
		'error'
	);
}
add_action( 'wp_loaded', 'gstore_handle_expired_register_nonce', 5 );

/**
 * Remove o wrapper padrão do WooCommerce na página minha conta
 * para usarmos nosso próprio layout.
 */
function gstore_remove_myaccount_wrapper() {
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	}
}
add_action( 'wp', 'gstore_remove_myaccount_wrapper' );

/**
 * Retorna o ícone SVG para cada endpoint do menu da conta.
 *
 * @param string $endpoint Endpoint do menu.
 * @return string SVG do ícone.
 */
function gstore_get_myaccount_icon( $endpoint ) {
	$icons = array(
		'dashboard'       => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
		'orders'          => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>',
		'downloads'       => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>',
		'edit-address'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
		'edit-account'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
		'customer-logout' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
		'payment-methods' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>',
	);

	$default_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>';

	return isset( $icons[ $endpoint ] ) ? $icons[ $endpoint ] : $default_icon;
}

/**
 * ============================================
 * FUNÇÕES HELPER PARA IMAGENS DA BIBLIOTECA
 * ============================================
 */

/**
 * Aumenta a qualidade das imagens JPEG para melhor qualidade em produção.
 * 
 * O WordPress usa qualidade 82 por padrão. Aumentamos para 92 para banners.
 * Isso garante que imagens novas e redimensionadas tenham qualidade máxima.
 * 
 * NOTA: Imagens já carregadas precisarão ser regeneradas para aplicar a nova qualidade.
 * Use um plugin como "Regenerate Thumbnails" ou faça upload novamente das imagens.
 * 
 * @param int    $quality Qualidade atual (82 padrão).
 * @param string $mime_type Tipo MIME da imagem.
 * @return int Nova qualidade.
 */
function gstore_increase_jpeg_quality( $quality, $mime_type ) {
	if ( 'image/jpeg' === $mime_type ) {
		// Aumenta a qualidade para 92 (qualidade alta, ainda com compressão)
		// 92 é um bom equilíbrio entre qualidade e tamanho de arquivo
		return 92;
	}
	
	// Para WebP e PNG, mantém a qualidade padrão
	return $quality;
}
add_filter( 'jpeg_quality', 'gstore_increase_jpeg_quality', 10, 2 );
add_filter( 'wp_editor_set_quality', 'gstore_increase_jpeg_quality', 10, 2 );

/**
 * Retorna a URL de uma imagem da biblioteca de mídia pelo ID.
 *
 * @param int    $attachment_id ID da imagem na biblioteca.
 * @param string $size          Tamanho da imagem (thumbnail, medium, large, full, etc.).
 * @return string URL da imagem ou string vazia se não encontrada.
 */
function gstore_get_image_url( $attachment_id, $size = 'full' ) {
	if ( ! $attachment_id ) {
		return '';
	}

	// Para banners (tamanho 'full'), garante que sempre use a imagem original
	if ( 'full' === $size ) {
		$image_url = wp_get_attachment_image_url( $attachment_id, 'full' );
		// Se não encontrar, tenta pegar a URL do arquivo original diretamente
		if ( ! $image_url ) {
			$image_url = wp_get_attachment_url( $attachment_id );
		}
	} else {
		$image_url = wp_get_attachment_image_url( $attachment_id, $size );
	}

	return $image_url ? $image_url : '';
}

/**
 * Retorna a tag <img> completa de uma imagem da biblioteca.
 *
 * @param int    $attachment_id ID da imagem na biblioteca.
 * @param string $size          Tamanho da imagem.
 * @param string $alt           Texto alternativo (opcional, usa o alt da mídia se não fornecido).
 * @param array  $attr          Atributos adicionais para a tag img.
 * @return string Tag <img> completa ou string vazia.
 */
function gstore_get_image_tag( $attachment_id, $size = 'full', $alt = '', $attr = array() ) {
	if ( ! $attachment_id ) {
		return '';
	}

	// Se alt não foi fornecido, tenta pegar da mídia
	if ( empty( $alt ) ) {
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	}

	$default_attr = array(
		'src'    => gstore_get_image_url( $attachment_id, $size ),
		'alt'    => $alt ? $alt : '',
		'loading' => 'lazy',
		'decoding' => 'async',
	);

	$attr = wp_parse_args( $attr, $default_attr );

	if ( empty( $attr['src'] ) ) {
		return '';
	}

	$img_tag = '<img';
	foreach ( $attr as $key => $value ) {
		$img_tag .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
	}
	$img_tag .= ' />';

	return $img_tag;
}

/**
 * Shortcode para retornar URL de imagem da biblioteca.
 * 
 * Uso: [gstore_image_url id="123" size="full"]
 * 
 * @param array $atts Atributos do shortcode.
 * @return string URL da imagem.
 */
function gstore_image_url_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'   => 0,
			'size' => 'full',
		),
		$atts,
		'gstore_image_url'
	);

	$attachment_id = absint( $atts['id'] );
	if ( ! $attachment_id ) {
		return '';
	}

	return esc_url( gstore_get_image_url( $attachment_id, $atts['size'] ) );
}
add_shortcode( 'gstore_image_url', 'gstore_image_url_shortcode' );

/**
 * Shortcode para retornar tag <img> completa da biblioteca.
 * 
 * Uso: [gstore_image id="123" size="full" alt="Descrição"]
 * 
 * @param array $atts Atributos do shortcode.
 * @return string Tag <img> completa.
 */
function gstore_image_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'   => 0,
			'size' => 'full',
			'alt'  => '',
		),
		$atts,
		'gstore_image'
	);

	$attachment_id = absint( $atts['id'] );
	if ( ! $attachment_id ) {
		return '';
	}

	return gstore_get_image_tag( $attachment_id, $atts['size'], $atts['alt'] );
}
add_shortcode( 'gstore_image', 'gstore_image_shortcode' );

/**
 * Shortcode para renderizar o banner do YouTube condicionalmente.
 * Só exibe se o banner estiver configurado em Configurações Gstore.
 *
 * Uso: [gstore_banner_youtube]
 *
 * @return string HTML do banner ou string vazia se não configurado.
 */
function gstore_banner_youtube_shortcode() {
	$banner_id = gstore_get_banner_youtube_id();
	
	if ( $banner_id <= 0 ) {
		return ''; // Não exibe nada se não estiver configurado
	}
	
	$banner_url = wp_get_attachment_url( $banner_id );
	$banner_alt = esc_attr( get_option( 'gstore_banner_youtube_alt', 'Conheça o conteúdo da CAC Armas no YouTube' ) );
	
	if ( empty( $banner_url ) ) {
		return '';
	}
	
	return sprintf(
		'<figure class="wp-block-image alignfull Gstore-home-transition">
			<img src="%s" alt="%s" />
		</figure>',
		esc_url( $banner_url ),
		$banner_alt
	);
}
add_shortcode( 'gstore_banner_youtube', 'gstore_banner_youtube_shortcode' );

/**
 * ============================================
 * PÁGINA DE CONFIGURAÇÕES DO TEMA - ADMIN
 * ============================================
 */

/**
 * Adiciona página de configurações do tema no menu do admin.
 */
function gstore_add_theme_settings_page() {
	add_theme_page(
		__( 'Configurações do Tema Gstore', 'gstore' ),
		__( 'Configurações Gstore', 'gstore' ),
		'manage_options',
		'gstore-settings',
		'gstore_render_settings_page'
	);
}
add_action( 'admin_menu', 'gstore_add_theme_settings_page' );

/**
 * Registra as opções do tema.
 */
function gstore_register_theme_settings() {
	// Hero Slider
	register_setting( 'gstore_settings', 'gstore_hero_slide_1_id', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 0,
	) );
	register_setting( 'gstore_settings', 'gstore_hero_slide_1_alt', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => '',
	) );
	
	register_setting( 'gstore_settings', 'gstore_hero_slide_2_id', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 0,
	) );
	register_setting( 'gstore_settings', 'gstore_hero_slide_2_alt', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => '',
	) );
	
	// Banner YouTube
	register_setting( 'gstore_settings', 'gstore_banner_youtube_id', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 0,
	) );
	register_setting( 'gstore_settings', 'gstore_banner_youtube_alt', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => 'Conheça o conteúdo da CAC Armas no YouTube',
	) );
	
	// Logo do Site
	register_setting( 'gstore_settings', 'gstore_logo_id', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 0,
	) );
	register_setting( 'gstore_settings', 'gstore_logo_alt', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => 'Logo CAC Armas',
	) );
}
add_action( 'admin_init', 'gstore_register_theme_settings' );

/**
 * Renderiza a página de configurações.
 */
function gstore_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// Verifica se o formulário foi submetido
	if ( isset( $_GET['settings-updated'] ) ) {
		add_settings_error( 'gstore_messages', 'gstore_message', __( 'Configurações salvas com sucesso!', 'gstore' ), 'updated' );
	}
	
	settings_errors( 'gstore_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><?php _e( 'Configure as imagens do tema Gstore. Selecione as imagens da biblioteca de mídia do WordPress.', 'gstore' ); ?></p>
		
		<form action="options.php" method="post">
			<?php
			settings_fields( 'gstore_settings' );
			do_settings_sections( 'gstore_settings' );
			?>
			
			<h2 class="title"><?php _e( 'Logo do Site', 'gstore' ); ?></h2>
			<p class="description"><?php _e( 'Configure a logo que será exibida no header do site. Se não houver logo configurada, será exibido o título do site.', 'gstore' ); ?></p>
			
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="gstore_logo_id"><?php _e( 'Logo', 'gstore' ); ?></label>
					</th>
					<td>
						<?php gstore_render_media_selector( 'gstore_logo_id', 'gstore_logo_alt', get_option( 'gstore_logo_id', 0 ), get_option( 'gstore_logo_alt', 'Logo CAC Armas' ) ); ?>
					</td>
				</tr>
			</table>
			
			<h2 class="title"><?php _e( 'Hero Slider - Slides da Página Inicial', 'gstore' ); ?></h2>
			<p class="description"><?php _e( 'Configure as imagens do slider principal da página inicial.', 'gstore' ); ?></p>
			
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="gstore_hero_slide_1_id"><?php _e( 'Slide 1', 'gstore' ); ?></label>
					</th>
					<td>
						<?php gstore_render_media_selector( 'gstore_hero_slide_1_id', 'gstore_hero_slide_1_alt', get_option( 'gstore_hero_slide_1_id', 0 ), get_option( 'gstore_hero_slide_1_alt', '' ) ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gstore_hero_slide_2_id"><?php _e( 'Slide 2', 'gstore' ); ?></label>
					</th>
					<td>
						<?php gstore_render_media_selector( 'gstore_hero_slide_2_id', 'gstore_hero_slide_2_alt', get_option( 'gstore_hero_slide_2_id', 0 ), get_option( 'gstore_hero_slide_2_alt', '' ) ); ?>
					</td>
				</tr>
			</table>
			
			<h2 class="title"><?php _e( 'Banners', 'gstore' ); ?></h2>
			<p class="description"><?php _e( 'Configure os banners exibidos no site.', 'gstore' ); ?></p>
			
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="gstore_banner_youtube_id"><?php _e( 'Banner YouTube', 'gstore' ); ?></label>
					</th>
					<td>
						<?php gstore_render_media_selector( 'gstore_banner_youtube_id', 'gstore_banner_youtube_alt', get_option( 'gstore_banner_youtube_id', 0 ), get_option( 'gstore_banner_youtube_alt', 'Conheça o conteúdo da CAC Armas no YouTube' ) ); ?>
					</td>
				</tr>
			</table>
			
			<?php submit_button( __( 'Salvar Configurações', 'gstore' ) ); ?>
		</form>
	</div>
	
	<style>
		.gstore-media-selector {
			display: flex;
			align-items: flex-start;
			gap: 15px;
			margin-bottom: 10px;
		}
		.gstore-media-preview {
			width: 150px;
			height: 150px;
			border: 2px dashed #ccc;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #f9f9f9;
			border-radius: 4px;
		}
		.gstore-media-preview img {
			max-width: 100%;
			max-height: 100%;
			object-fit: contain;
		}
		.gstore-media-preview.has-image {
			border-color: #2271b1;
			border-style: solid;
		}
		.gstore-media-controls {
			flex: 1;
		}
		.gstore-media-controls input[type="hidden"] {
			display: none;
		}
		.gstore-media-controls .button {
			margin-right: 5px;
		}
		.gstore-media-controls .description {
			margin-top: 8px;
			font-style: italic;
			color: #646970;
		}
		.gstore-alt-field {
			margin-top: 10px;
		}
		.gstore-alt-field label {
			display: block;
			margin-bottom: 5px;
			font-weight: 600;
		}
		.gstore-alt-field input[type="text"] {
			width: 100%;
			max-width: 500px;
		}
	</style>
	
	<script>
	jQuery(document).ready(function($) {
		// Abre o seletor de mídia
		$(document).on('click', '.gstore-select-media', function(e) {
			e.preventDefault();
			
			var button = $(this);
			var inputId = button.data('input-id');
			var previewId = button.data('preview-id');
			var input = $('#' + inputId);
			var preview = $('#' + previewId);
			
			var mediaUploader = wp.media({
				title: 'Selecione uma imagem',
				button: {
					text: 'Usar esta imagem'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});
			
			mediaUploader.on('select', function() {
				var attachment = mediaUploader.state().get('selection').first().toJSON();
				input.val(attachment.id);
				preview.html('<img src="' + attachment.url + '" alt="' + attachment.alt + '" />');
				preview.addClass('has-image');
				preview.closest('.gstore-media-selector').find('.gstore-remove-media').show();
			});
			
			mediaUploader.open();
		});
		
		// Remove a imagem selecionada
		$(document).on('click', '.gstore-remove-media', function(e) {
			e.preventDefault();
			
			var button = $(this);
			var inputId = button.data('input-id');
			var previewId = button.data('preview-id');
			var input = $('#' + inputId);
			var preview = $('#' + previewId);
			
			input.val(0);
			preview.html('<span style="color: #999;">Nenhuma imagem selecionada</span>');
			preview.removeClass('has-image');
			button.hide();
		});
		
		// Carrega previews existentes ao carregar a página
		$('.gstore-media-preview').each(function() {
			var preview = $(this);
			var inputId = preview.data('input-id');
			var input = $('#' + inputId);
			var imageId = input.val();
			
			if (imageId && imageId != '0') {
				$.ajax({
					url: gstoreSettings.ajax_url,
					type: 'POST',
					data: {
						action: 'gstore_get_image_data',
						image_id: imageId,
						nonce: gstoreSettings.nonce
					},
					success: function(response) {
						if (response.success && response.data.url) {
							preview.html('<img src="' + response.data.url + '" alt="' + (response.data.alt || '') + '" />');
							preview.addClass('has-image');
							preview.closest('.gstore-media-selector').find('.gstore-remove-media').show();
						}
					}
				});
			}
		});
	});
	</script>
	<?php
}

/**
 * Renderiza o seletor de mídia.
 */
function gstore_render_media_selector( $input_id, $alt_input_id, $current_id = 0, $current_alt = '' ) {
	$preview_id = $input_id . '_preview';
	$has_image = $current_id > 0;
	?>
	<div class="gstore-media-selector">
		<div id="<?php echo esc_attr( $preview_id ); ?>" class="gstore-media-preview" data-input-id="<?php echo esc_attr( $input_id ); ?>">
			<?php if ( $has_image ) : ?>
				<?php
				$image_url = wp_get_attachment_image_url( $current_id, 'thumbnail' );
				if ( $image_url ) :
					?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $current_alt ); ?>" />
				<?php else : ?>
					<span style="color: #999;">Imagem não encontrada</span>
				<?php endif; ?>
			<?php else : ?>
				<span style="color: #999;">Nenhuma imagem selecionada</span>
			<?php endif; ?>
		</div>
		<div class="gstore-media-controls">
			<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( $current_id ); ?>" />
			<button type="button" class="button gstore-select-media" data-input-id="<?php echo esc_attr( $input_id ); ?>" data-preview-id="<?php echo esc_attr( $preview_id ); ?>">
				<?php _e( 'Selecionar Imagem', 'gstore' ); ?>
			</button>
			<button type="button" class="button gstore-remove-media" data-input-id="<?php echo esc_attr( $input_id ); ?>" data-preview-id="<?php echo esc_attr( $preview_id ); ?>" style="<?php echo $has_image ? '' : 'display: none;'; ?>">
				<?php _e( 'Remover', 'gstore' ); ?>
			</button>
			<p class="description">
				<?php _e( 'ID da imagem:', 'gstore' ); ?> <strong><?php echo esc_html( $current_id ? $current_id : 'Nenhuma' ); ?></strong>
			</p>
			
			<div class="gstore-alt-field">
				<label for="<?php echo esc_attr( $alt_input_id ); ?>">
					<?php _e( 'Texto Alternativo (Alt)', 'gstore' ); ?>
				</label>
				<input type="text" id="<?php echo esc_attr( $alt_input_id ); ?>" name="<?php echo esc_attr( $alt_input_id ); ?>" value="<?php echo esc_attr( $current_alt ); ?>" class="regular-text" />
				<p class="description"><?php _e( 'Descrição da imagem para acessibilidade e SEO.', 'gstore' ); ?></p>
			</div>
		</div>
	</div>
	<?php
}

/**
 * AJAX: Retorna dados de uma imagem.
 */
function gstore_ajax_get_image_data() {
	check_ajax_referer( 'gstore_ajax', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}
	
	$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
	
	if ( ! $image_id ) {
		wp_send_json_error( array( 'message' => __( 'ID da imagem não fornecido.', 'gstore' ) ) );
	}
	
	$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
	$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
	
	if ( ! $image_url ) {
		wp_send_json_error( array( 'message' => __( 'Imagem não encontrada.', 'gstore' ) ) );
	}
	
	wp_send_json_success( array(
		'url' => $image_url,
		'alt' => $image_alt,
		'id'  => $image_id,
	) );
}
add_action( 'wp_ajax_gstore_get_image_data', 'gstore_ajax_get_image_data' );

/**
 * Enfileira scripts e estilos necessários na página de configurações.
 */
function gstore_enqueue_settings_assets( $hook ) {
	if ( 'appearance_page_gstore-settings' !== $hook ) {
		return;
	}
	
	wp_enqueue_media();
	wp_enqueue_script( 'jquery' );
	
	// Localiza script para AJAX
	wp_localize_script( 'jquery', 'gstoreSettings', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'gstore_ajax' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'gstore_enqueue_settings_assets' );

/**
 * Funções helper para obter IDs configurados.
 */
function gstore_get_hero_slide_1_id() {
	return absint( get_option( 'gstore_hero_slide_1_id', 0 ) );
}

function gstore_get_hero_slide_2_id() {
	return absint( get_option( 'gstore_hero_slide_2_id', 0 ) );
}

function gstore_get_banner_youtube_id() {
	return absint( get_option( 'gstore_banner_youtube_id', 0 ) );
}

function gstore_get_logo_id() {
	return absint( get_option( 'gstore_logo_id', 0 ) );
}

/**
 * Filtro para modificar o bloco site-logo para usar a logo configurada.
 * 
 * @param string $block_content Conteúdo do bloco.
 * @param array  $block         Dados do bloco.
 * @return string
 */
function gstore_custom_site_logo_block( $block_content, $block ) {
	// Verifica se é o bloco site-logo
	if ( empty( $block['blockName'] ) || 'core/site-logo' !== $block['blockName'] ) {
		return $block_content;
	}
	
	// Verifica se está no header (pela classe ou contexto)
	$is_in_header = false;
	if ( isset( $block['attrs']['className'] ) && strpos( $block['attrs']['className'], 'Gstore-header__logo' ) !== false ) {
		$is_in_header = true;
	}
	
	// Se não está no header, não modifica
	if ( ! $is_in_header ) {
		return $block_content;
	}
	
	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();
	
	if ( $logo_id > 0 ) {
		$logo_url = gstore_get_image_url( $logo_id, 'full' );
		$logo_alt = get_option( 'gstore_logo_alt', 'Logo CAC Armas' );
		
		if ( $logo_url ) {
			// Substitui o conteúdo do bloco pela logo configurada
			$home_url = esc_url( home_url( '/' ) );
			$site_name = esc_attr( get_bloginfo( 'name' ) );
			$logo_html = sprintf(
				'<div class="wp-block-site-logo Gstore-header__logo"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 36px; max-width: 180px; width: auto; height: auto;" /></a></div>',
				$home_url,
				$site_name,
				esc_url( $logo_url ),
				esc_attr( $logo_alt )
			);
			
			return $logo_html;
		}
	}
	
	return $block_content;
}
add_filter( 'render_block', 'gstore_custom_site_logo_block', 10, 2 );

/**
 * Substitui o link de texto da logo pela imagem configurada no header HTML.
 * 
 * @param string $content Conteúdo do template part.
 * @return string
 */
function gstore_replace_header_logo_html( $content ) {
	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();
	
	if ( $logo_id <= 0 ) {
		return $content;
	}
	
	$logo_url = gstore_get_image_url( $logo_id, 'full' );
	$logo_alt = get_option( 'gstore_logo_alt', 'Logo CAC Armas' );
	
	if ( ! $logo_url ) {
		return $content;
	}
	
	$home_url = esc_url( home_url( '/' ) );
	$site_name = esc_attr( get_bloginfo( 'name' ) );
	
	// HTML da logo com imagem
	$logo_html = sprintf(
		'<a href="%s" class="Gstore-header__logo" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 36px; max-width: 180px; width: auto; height: auto;" /></a>',
		$home_url,
		$site_name,
		esc_url( $logo_url ),
		esc_attr( $logo_alt )
	);
	
	// Padrão 1: Link com classe Gstore-header__logo
	// Captura: <a href="/" class="Gstore-header__logo">ARMA<span class="Gstore-logo-highlight">STORE</span></a>
	$pattern1 = '/<a\s+[^>]*class="[^"]*Gstore-header__logo[^"]*"[^>]*>.*?<\/a>/is';
	$content = preg_replace( $pattern1, $logo_html, $content );
	
	// Padrão 2: Link com rel="home" que contém "CAC ARMAS" ou "CACARMAS" (sem classe específica)
	// Captura: <a href="..." rel="home">CAC ARMAS</a>
	$pattern2 = '/<a\s+[^>]*rel=["\']home["\'][^>]*>.*?ARMA.*?STORE.*?<\/a>/is';
	$content = preg_replace( $pattern2, $logo_html, $content );
	
	// Padrão 3: Link com rel="home" que aponta para a home (mais genérico)
	// Só substitui se estiver dentro do header para evitar substituir outros links
	if ( strpos( $content, 'Gstore-header' ) !== false || strpos( $content, 'Gstore-header__logo' ) !== false ) {
		$pattern3 = '/<a\s+[^>]*href=["\']([^"\']*\/|' . preg_quote( $home_url, '/' ) . ')[^"\']*["\'][^>]*rel=["\']home["\'][^>]*>CAC\s*ARMAS<\/a>/is';
		$content = preg_replace( $pattern3, $logo_html, $content );
	}
	
	return $content;
}
add_filter( 'render_block_core/template-part', 'gstore_replace_header_logo_html', 10, 1 );
add_filter( 'render_block', 'gstore_replace_header_logo_html', 20, 1 );
add_filter( 'the_content', 'gstore_replace_header_logo_html', 5 );

/**
 * Processa placeholders de imagens nos templates HTML.
 * 
 * Substitui placeholders como {{gstore_image:123}} por URLs reais da biblioteca.
 * 
 * @param string $content Conteúdo do template.
 * @return string Conteúdo processado.
 */
function gstore_process_image_placeholders( $content ) {
	if ( empty( $content ) ) {
		return $content;
	}
	
	// Placeholders especiais que usam configurações do tema
	// {{gstore_hero_slide_1}}, {{gstore_hero_slide_2}}, {{gstore_banner_youtube}}
	// IMPORTANTE: Sempre usa 'full' para banners, garantindo qualidade máxima
	$hero_slide_1_id = gstore_get_hero_slide_1_id();
	$hero_slide_2_id = gstore_get_hero_slide_2_id();
	$banner_youtube_id = gstore_get_banner_youtube_id();
	
	// Para banners, sempre usa a URL do arquivo original sem redimensionamento
	$hero_slide_1_url = $hero_slide_1_id > 0 ? wp_get_attachment_url( $hero_slide_1_id ) : '';
	$hero_slide_2_url = $hero_slide_2_id > 0 ? wp_get_attachment_url( $hero_slide_2_id ) : '';
	$banner_youtube_url = $banner_youtube_id > 0 ? wp_get_attachment_url( $banner_youtube_id ) : '';
	
	$content = str_replace( '{{gstore_hero_slide_1}}', $hero_slide_1_url, $content );
	$content = str_replace( '{{gstore_hero_slide_2}}', $hero_slide_2_url, $content );
	$content = str_replace( '{{gstore_banner_youtube}}', $banner_youtube_url, $content );
	
	// Placeholders para textos alternativos
	$content = str_replace( '{{gstore_hero_slide_1_alt}}', esc_attr( get_option( 'gstore_hero_slide_1_alt', 'Campanha Excedente Black Week CAC Armas' ) ), $content );
	$content = str_replace( '{{gstore_hero_slide_2_alt}}', esc_attr( get_option( 'gstore_hero_slide_2_alt', 'Produtos da Black Week com a mesma condição CAC Armas' ) ), $content );
	$content = str_replace( '{{gstore_banner_youtube_alt}}', esc_attr( get_option( 'gstore_banner_youtube_alt', 'Conheça o conteúdo da CAC Armas no YouTube' ) ), $content );
	
	// Padrão: {{gstore_image:ID:size}} para URL apenas
	$pattern = '/\{\{gstore_image:(\d+)(?::([^}]+))?\}\}/';
	
	$content = preg_replace_callback(
		$pattern,
		function( $matches ) {
			$attachment_id = absint( $matches[1] );
			$size          = isset( $matches[2] ) && ! empty( $matches[2] ) ? $matches[2] : 'full';
			
			if ( ! $attachment_id ) {
				return '';
			}
			
			$url = gstore_get_image_url( $attachment_id, $size );
			return $url ? esc_url( $url ) : '';
		},
		$content
	);
	
	// Padrão: {{gstore_image_tag:ID:size:alt}} para tag completa
	$pattern_tag = '/\{\{gstore_image_tag:(\d+)(?::([^:}]+))?(?::([^}]+))?\}\}/';
	
	$content = preg_replace_callback(
		$pattern_tag,
		function( $matches ) {
			$attachment_id = absint( $matches[1] );
			$size          = isset( $matches[2] ) && ! empty( $matches[2] ) ? $matches[2] : 'full';
			$alt           = isset( $matches[3] ) ? $matches[3] : '';
			
			if ( ! $attachment_id ) {
				return '';
			}
			
			return gstore_get_image_tag( $attachment_id, $size, $alt );
		},
		$content
	);
	
	return $content;
}

/**
 * Filtro para processar placeholders em conteúdo de posts/páginas.
 */
add_filter( 'the_content', 'gstore_process_image_placeholders', 5 );
add_filter( 'widget_text', 'gstore_process_image_placeholders', 5 );

/**
 * Processa template parts HTML carregando e substituindo placeholders.
 * 
 * Esta função pode ser usada para processar templates HTML manualmente.
 * 
 * @param string $template_path Caminho do template part.
 * @return string Conteúdo processado.
 */
function gstore_load_template_part( $template_path ) {
	$template_file = get_theme_file_path( $template_path );
	
	if ( ! file_exists( $template_file ) ) {
		return '';
	}
	
	ob_start();
	include $template_file;
	$content = ob_get_clean();
	
	return gstore_process_image_placeholders( $content );
}

/**
 * Filtro para processar blocos HTML customizados do Gutenberg.
 * 
 * Processa placeholders quando blocos HTML são renderizados.
 */
add_filter( 'render_block_core/html', 'gstore_process_block_html', 10, 2 );
function gstore_process_block_html( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_image_placeholders( $block_content );
	}
	return $block_content;
}

/**
 * Filtro para processar template parts quando renderizados.
 * 
 * Processa placeholders em template parts HTML do Gutenberg.
 */
add_filter( 'render_block_core/template-part', 'gstore_process_template_part_block', 10, 2 );
function gstore_process_template_part_block( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_image_placeholders( $block_content );
	}
	return $block_content;
}

/**
 * Filtro para processar blocos de imagem do Gutenberg.
 * 
 * Processa placeholders em blocos de imagem.
 */
add_filter( 'render_block_core/image', 'gstore_process_image_block', 10, 2 );
function gstore_process_image_block( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_image_placeholders( $block_content );
	}
	return $block_content;
}

/**
 * Filtro para processar todo o conteúdo renderizado.
 * 
 * Processa placeholders em qualquer conteúdo renderizado pelo WordPress.
 */
add_filter( 'render_block', 'gstore_process_all_blocks', 10, 2 );
function gstore_process_all_blocks( $block_content, $block ) {
	if ( ! empty( $block_content ) && is_string( $block_content ) ) {
		// Verifica se contém placeholders antes de processar
		if ( strpos( $block_content, '{{gstore_' ) !== false ) {
			$block_content = gstore_process_image_placeholders( $block_content );
		}
	}
	return $block_content;
}

/**
 * Processa o output final da página para garantir que placeholders sejam substituídos.
 * 
 * Este é um filtro de último recurso que processa todo o HTML antes de ser enviado ao navegador.
 */
add_action( 'template_redirect', 'gstore_start_output_buffer', 1 );
function gstore_start_output_buffer() {
	if ( ! is_admin() ) {
		ob_start( 'gstore_process_final_output' );
	}
}

/**
 * Processa o output final e para o buffer.
 */
add_action( 'shutdown', 'gstore_end_output_buffer', 0 );
function gstore_end_output_buffer() {
	if ( ! is_admin() && ob_get_level() > 0 ) {
		ob_end_flush();
	}
}

/**
 * Processa o output final da página.
 * 
 * @param string $buffer Conteúdo HTML da página.
 * @return string Conteúdo processado.
 */
function gstore_process_final_output( $buffer ) {
	if ( empty( $buffer ) ) {
		return $buffer;
	}
	
	// Processa placeholders no output final
	$buffer = gstore_process_image_placeholders( $buffer );
	
	// Substitui a logo no header se configurada
	$buffer = gstore_replace_header_logo_html( $buffer );
	
	// Remove classe Gstore-cart-shell do wrapper do WooCommerce para evitar conflitos
	// O bloco page-content-wrapper adiciona classes que geram padding indesejado
	$buffer = gstore_strip_cart_shell_from_wc_wrapper( $buffer );
	
	return $buffer;
}

/**
 * Remove classes problemáticas do wrapper do WooCommerce na página do carrinho.
 *
 * O bloco woocommerce/page-content-wrapper e entry-content adicionam classes como
 * is-layout-constrained e wp-block-group-is-layout-constrained que aplicam
 * max-width: 720px e padding indesejado. Esta função remove essas classes
 * para permitir que o layout do carrinho use 1280px.
 *
 * NOTA: Apenas o carrinho precisa dessa remoção. As páginas de
 * atendimento e produto único mantêm suas classes para estilização.
 *
 * @param string $html HTML da página.
 * @return string HTML processado.
 */
function gstore_strip_cart_shell_from_wc_wrapper( $html ) {
	if ( empty( $html ) || strpos( $html, 'data-page="cart"' ) === false ) {
		return $html;
	}
	
	// Remove a classe Gstore-cart-shell do main que tem data-block-name="woocommerce/page-content-wrapper"
	$html = preg_replace(
		'/(<main[^>]*data-block-name="woocommerce\/page-content-wrapper"[^>]*class="[^"]*)\bGstore-cart-shell\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	// Remove classes is-layout-constrained do main wrapper do carrinho
	$html = preg_replace(
		'/(<main[^>]*data-page="cart"[^>]*class="[^"]*)\bis-layout-constrained\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	// Remove classes wp-block-group-is-layout-constrained do main wrapper do carrinho
	$html = preg_replace(
		'/(<main[^>]*data-page="cart"[^>]*class="[^"]*)\bwp-block-group-is-layout-constrained\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	// Remove classes is-layout-constrained do entry-content wrapper
	$html = preg_replace(
		'/(<div[^>]*class="[^"]*entry-content[^"]*)\bis-layout-constrained\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	// Remove classes wp-block-post-content-is-layout-constrained do entry-content wrapper
	$html = preg_replace(
		'/(<div[^>]*class="[^"]*entry-content[^"]*)\bwp-block-post-content-is-layout-constrained\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	return $html;
}

/**
 * ============================================
 * SETUP DO TEMA - CRIAÇÃO AUTOMÁTICA DE PÁGINAS
 * ============================================
 * 
 * Sistema que permite criar todas as páginas necessárias
 * para o funcionamento do tema Gstore com um clique.
 */

/**
 * Retorna a lista de páginas que o tema precisa.
 * 
 * @return array Lista de páginas com configurações.
 */
function gstore_get_required_pages() {
	return array(
		'home' => array(
			'title'       => 'Home',
			'slug'        => 'home',
			'template'    => 'page-home',
			'content'     => '',
			'description' => 'Página inicial da loja com hero, benefícios, lançamentos e promoções.',
			'set_as'      => 'front_page',
		),
		'catalogo' => array(
			'title'       => 'Catálogo',
			'slug'        => 'catalogo',
			'template'    => 'page-catalogo',
			'content'     => '',
			'description' => 'Página de catálogo com filtros e lista de produtos.',
			'wc_option'   => null,
		),
		'loja' => array(
			'title'       => 'Loja',
			'slug'        => 'loja',
			'template'    => 'page-loja',
			'content'     => '',
			'description' => 'Página principal da loja WooCommerce com layout de catálogo.',
			'wc_option'   => 'woocommerce_shop_page_id',
		),
		'ofertas' => array(
			'title'       => 'Ofertas',
			'slug'        => 'ofertas',
			'template'    => 'page-ofertas',
			'content'     => '',
			'description' => 'Página de produtos em promoção.',
			'wc_option'   => null,
		),
		'carrinho' => array(
			'title'       => 'Carrinho',
			'slug'        => 'carrinho',
			'template'    => 'page-carrinho', // Template de blocos HTML (sem .html)
			'content'     => '', // Conteúdo vazio - o template de blocos renderiza tudo
			'description' => 'Página do carrinho de compras.',
			'wc_option'   => 'woocommerce_cart_page_id',
		),
		'finalizar-compra' => array(
			'title'       => 'Finalizar Compra',
			'slug'        => 'finalizar-compra',
			'template'    => 'page-checkout',
			'content'     => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
			'description' => 'Página de checkout com formulário de endereço e pagamento.',
			'wc_option'   => 'woocommerce_checkout_page_id',
		),
		'minha-conta' => array(
			'title'       => 'Minha Conta',
			'slug'        => 'minha-conta',
			'template'    => '',
			'content'     => '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->',
			'description' => 'Área do cliente para gerenciar pedidos, endereços e dados.',
			'wc_option'   => 'woocommerce_myaccount_page_id',
		),
		'atendimento' => array(
			'title'       => 'Atendimento',
			'slug'        => 'atendimento',
			'template'    => 'page-atendimento',
			'content'     => '',
			'description' => 'Central de atendimento com todos os canais de contato.',
			'wc_option'   => null,
		),
		'politica-de-privacidade' => array(
			'title'       => 'Política de Privacidade',
			'slug'        => 'politica-de-privacidade',
			'template'    => '',
			'content'     => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Política de Privacidade</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Esta página descreve como coletamos, usamos e protegemos suas informações pessoais.</p><!-- /wp:paragraph -->',
			'description' => 'Página com a política de privacidade da loja.',
			'wc_option'   => null,
			'wp_option'   => 'wp_page_for_privacy_policy',
		),
		'termos-de-uso' => array(
			'title'       => 'Termos de Uso',
			'slug'        => 'termos-de-uso',
			'template'    => '',
			'content'     => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Termos de Uso</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Ao utilizar nosso site, você concorda com os termos e condições descritos nesta página.</p><!-- /wp:paragraph -->',
			'description' => 'Página com os termos e condições de uso.',
			'wc_option'   => 'woocommerce_terms_page_id',
		),
		'blog' => array(
			'title'       => 'Blog',
			'slug'        => 'blog',
			'template'    => '',
			'content'     => '',
			'description' => 'Página que exibe os posts do blog.',
			'set_as'      => 'posts_page',
		),
	);
}

/**
 * Adiciona menu de Setup do Tema no admin.
 */
function gstore_add_setup_menu() {
	add_menu_page(
		__( 'Setup Gstore', 'gstore' ),
		__( 'Setup Gstore', 'gstore' ),
		'manage_options',
		'gstore-setup',
		'gstore_render_setup_page',
		'dashicons-store',
		59
	);
}
add_action( 'admin_menu', 'gstore_add_setup_menu' );

/**
 * Verifica se uma página existe pelo slug.
 * 
 * @param string $slug Slug da página.
 * @return WP_Post|null Post encontrado ou null.
 */
function gstore_get_page_by_slug( $slug ) {
	$page = get_page_by_path( $slug );
	
	if ( ! $page ) {
		// Tenta encontrar com query mais específica
		$pages = get_posts( array(
			'name'        => $slug,
			'post_type'   => 'page',
			'post_status' => array( 'publish', 'draft', 'private' ),
			'numberposts' => 1,
		) );
		
		$page = ! empty( $pages ) ? $pages[0] : null;
	}
	
	return $page;
}

/**
 * Cria uma página do tema.
 * 
 * @param string $page_key Chave da página na lista de páginas.
 * @param bool   $force    Se true, recria a página mesmo se já existir.
 * @return array Resultado da operação.
 */
function gstore_create_page( $page_key, $force = false ) {
	$pages = gstore_get_required_pages();
	
	if ( ! isset( $pages[ $page_key ] ) ) {
		return array(
			'success' => false,
			'message' => __( 'Página não encontrada nas configurações.', 'gstore' ),
		);
	}
	
	$page_config = $pages[ $page_key ];
	$existing_page = gstore_get_page_by_slug( $page_config['slug'] );
	
	// Se a página já existe e não é forçado, apenas retorna sucesso
	if ( $existing_page && ! $force ) {
		return array(
			'success' => true,
			'message' => __( 'Página já existe.', 'gstore' ),
			'page_id' => $existing_page->ID,
			'action'  => 'exists',
		);
	}
	
	// Se força recriação, deleta a existente
	if ( $existing_page && $force ) {
		wp_delete_post( $existing_page->ID, true );
	}
	
	// Prepara os dados da nova página
	$page_data = array(
		'post_title'   => $page_config['title'],
		'post_name'    => $page_config['slug'],
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $page_config['content'],
	);
	
	// Insere a página
	$page_id = wp_insert_post( $page_data );
	
	if ( is_wp_error( $page_id ) ) {
		return array(
			'success' => false,
			'message' => $page_id->get_error_message(),
		);
	}
	
	// Define o template se especificado
	if ( ! empty( $page_config['template'] ) ) {
		update_post_meta( $page_id, '_wp_page_template', $page_config['template'] );
	}
	
	// Configura opções do WooCommerce
	if ( ! empty( $page_config['wc_option'] ) && class_exists( 'WooCommerce' ) ) {
		update_option( $page_config['wc_option'], $page_id );
	}
	
	// Configura opções do WordPress
	if ( ! empty( $page_config['wp_option'] ) ) {
		update_option( $page_config['wp_option'], $page_id );
	}
	
	// Define como página inicial ou de posts
	if ( ! empty( $page_config['set_as'] ) ) {
		if ( 'front_page' === $page_config['set_as'] ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $page_id );
		} elseif ( 'posts_page' === $page_config['set_as'] ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_for_posts', $page_id );
		}
	}
	
	return array(
		'success' => true,
		'message' => __( 'Página criada com sucesso!', 'gstore' ),
		'page_id' => $page_id,
		'action'  => 'created',
	);
}

/**
 * Cria todas as páginas do tema.
 * 
 * @param bool $force Se true, recria todas as páginas.
 * @return array Resultados das operações.
 */
function gstore_create_all_pages( $force = false ) {
	$pages = gstore_get_required_pages();
	$results = array();
	
	foreach ( $pages as $page_key => $page_config ) {
		$results[ $page_key ] = gstore_create_page( $page_key, $force );
	}
	
	return $results;
}

/**
 * Executa diagnóstico dos assets críticos do tema.
 *
 * @return array Resultado da verificação.
 */
function gstore_run_asset_diagnostics() {
	$assets = array(
		'assets/css/gstore-main.css',
		'assets/css/layouts/header.css',
		'assets/js/header.js',
		'assets/js/home-hero.js',
		'assets/js/home-benefits.js',
		'assets/js/home-products-carousel.js',
	);
	$missing = array();

	foreach ( $assets as $asset_path ) {
		if ( ! file_exists( get_theme_file_path( $asset_path ) ) ) {
			$missing[] = $asset_path;
		}
	}

	if ( ! empty( $missing ) ) {
		return array(
			'success' => false,
			'message' => __( 'Encontramos arquivos ausentes. Reenvie o tema ou copie novamente a pasta de assets.', 'gstore' ),
			'missing' => $missing,
		);
	}

	return array(
		'success' => true,
		'message' => __( 'Todos os arquivos críticos estão presentes. O layout mobile será aplicado automaticamente.', 'gstore' ),
	);
}

/**
 * ============================================
 * DIAGNÓSTICO DE CSS - VERIFICAÇÃO EM PRODUÇÃO
 * ============================================
 * 
 * Sistema que verifica se regras CSS críticas estão
 * sendo aplicadas corretamente no frontend.
 */

/**
 * Retorna as regras CSS críticas que devem ser verificadas.
 * 
 * @return array Lista de regras com seletores e propriedades esperadas.
 */
function gstore_get_css_diagnostic_rules() {
	return array(
		'benefits_slider_controls_hidden' => array(
			'name'        => 'Setas do carrossel de benefícios (mobile)',
			'selector'    => '.Gstore-home-benefits__slider-control--prev',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'mobile',
			'description' => 'As setas de navegação devem estar ocultas no mobile (@media max-width: 900px)',
			'css_file'    => 'style.css',
			'css_line'    => '~2320',
		),
		'benefits_slider_controls_next_hidden' => array(
			'name'        => 'Seta próximo do carrossel (mobile)',
			'selector'    => '.Gstore-home-benefits__slider-control--next',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'mobile',
			'description' => 'A seta próximo deve estar oculta no mobile',
			'css_file'    => 'style.css',
			'css_line'    => '~2320',
		),
		'benefits_slider_dots_hidden' => array(
			'name'        => 'Dots do carrossel de benefícios (mobile)',
			'selector'    => '.Gstore-home-benefits__slider-dots',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'mobile',
			'description' => 'Os dots de navegação devem estar ocultos no mobile',
			'css_file'    => 'style.css',
			'css_line'    => '~2324',
		),
		'benefits_slider_visible' => array(
			'name'        => 'Slider de benefícios visível (mobile)',
			'selector'    => '.Gstore-home-benefits__slider',
			'property'    => 'display',
			'expected'    => 'block',
			'viewport'    => 'mobile',
			'description' => 'O slider deve estar visível no mobile',
			'css_file'    => 'style.css',
			'css_line'    => '~2307',
		),
		'benefits_inner_hidden' => array(
			'name'        => 'Grid de benefícios oculto (mobile)',
			'selector'    => '.Gstore-home-benefits__inner',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'mobile',
			'description' => 'O grid desktop de benefícios deve estar oculto no mobile',
			'css_file'    => 'style.css',
			'css_line'    => '~2303',
		),
		'header_mobile_menu_hidden' => array(
			'name'        => 'Menu mobile oculto (desktop)',
			'selector'    => '.Gstore-header__mobile-menu-toggle',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'desktop',
			'description' => 'O botão de menu mobile deve estar oculto no desktop',
			'css_file'    => 'assets/css/layouts/header.css',
			'css_line'    => 'varies',
		),
	);
}

/**
 * Gera o script JavaScript de diagnóstico para rodar no frontend.
 * 
 * @return string Código JavaScript para diagnóstico.
 */
function gstore_generate_css_diagnostics_script() {
	$rules = gstore_get_css_diagnostic_rules();
	$rules_json = wp_json_encode( $rules );
	
	$script = <<<JAVASCRIPT
(function() {
	'use strict';
	
	console.log('%c🔍 Gstore CSS Diagnostics', 'font-size: 16px; font-weight: bold; color: #2271b1;');
	console.log('Verificando regras CSS críticas...');
	console.log('');
	
	var rules = {$rules_json};
	var results = { passed: [], failed: [], notFound: [] };
	var isMobile = window.innerWidth <= 900;
	var isDesktop = window.innerWidth > 900;
	
	console.log('Viewport atual: ' + (isMobile ? 'Mobile (' + window.innerWidth + 'px)' : 'Desktop (' + window.innerWidth + 'px)'));
	console.log('');
	
	Object.keys(rules).forEach(function(key) {
		var rule = rules[key];
		var shouldCheck = (rule.viewport === 'mobile' && isMobile) || 
		                  (rule.viewport === 'desktop' && isDesktop) ||
		                  !rule.viewport;
		
		if (!shouldCheck) {
			console.log('%c⏭️ ' + rule.name + ' - Ignorado (viewport diferente)', 'color: #666;');
			return;
		}
		
		var element = document.querySelector(rule.selector);
		
		if (!element) {
			results.notFound.push(rule);
			console.log('%c❓ ' + rule.name + ' - Elemento não encontrado: ' + rule.selector, 'color: #dba617;');
			return;
		}
		
		var computedStyle = window.getComputedStyle(element);
		var actualValue = computedStyle.getPropertyValue(rule.property);
		
		if (actualValue.trim() === rule.expected) {
			results.passed.push(rule);
			console.log('%c✅ ' + rule.name + ' - OK (' + rule.property + ': ' + actualValue + ')', 'color: #00a32a;');
		} else {
			results.failed.push({ rule: rule, actual: actualValue });
			console.log('%c❌ ' + rule.name + ' - FALHOU', 'color: #d63638; font-weight: bold;');
			console.log('   Esperado: ' + rule.property + ': ' + rule.expected);
			console.log('   Atual: ' + rule.property + ': ' + actualValue);
			console.log('   Arquivo: ' + rule.css_file + ' (linha ' + rule.css_line + ')');
			console.log('   ' + rule.description);
		}
	});
	
	console.log('');
	console.log('%c📊 Resumo do Diagnóstico', 'font-size: 14px; font-weight: bold;');
	console.log('✅ Passou: ' + results.passed.length);
	console.log('❌ Falhou: ' + results.failed.length);
	console.log('❓ Não encontrado: ' + results.notFound.length);
	
	if (results.failed.length > 0) {
		console.log('');
		console.log('%c⚠️ Possíveis causas:', 'font-weight: bold; color: #dba617;');
		console.log('1. Cache do navegador - Limpe o cache e recarregue');
		console.log('2. Cache do servidor - Limpe cache do plugin de cache (LiteSpeed, WP Super Cache, etc.)');
		console.log('3. CDN com cache - Faça purge do cache da CDN');
		console.log('4. CSS não atualizado - Verifique se o deploy foi feito corretamente');
		console.log('5. Plugin conflitante - Desative plugins de otimização CSS temporariamente');
		console.log('');
		console.log('%c💡 Dica: Compare a versão do style.css local vs produção', 'color: #2271b1;');
	}
	
	// Retorna os resultados para uso programático
	return results;
})();
JAVASCRIPT;

	return $script;
}

/**
 * Adiciona painel de diagnóstico no frontend via query parameter.
 * 
 * Acesse: ?gstore_diagnostics=1 para ver o painel visual.
 */
function gstore_frontend_diagnostics_panel() {
	if ( ! isset( $_GET['gstore_diagnostics'] ) || '1' !== $_GET['gstore_diagnostics'] ) {
		return;
	}
	
	// Apenas administradores podem acessar
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	$rules = gstore_get_css_diagnostic_rules();
	?>
	<div id="gstore-diagnostics-panel" style="
		position: fixed;
		bottom: 20px;
		right: 20px;
		width: 400px;
		max-height: 80vh;
		overflow-y: auto;
		background: #1d2327;
		color: #f0f0f1;
		border-radius: 8px;
		box-shadow: 0 4px 20px rgba(0,0,0,0.3);
		z-index: 999999;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
		font-size: 13px;
	">
		<div style="
			padding: 16px;
			border-bottom: 1px solid #3c434a;
			display: flex;
			justify-content: space-between;
			align-items: center;
		">
			<h3 style="margin: 0; font-size: 14px; display: flex; align-items: center; gap: 8px;">
				🔍 Gstore CSS Diagnostics
			</h3>
			<button onclick="document.getElementById('gstore-diagnostics-panel').remove();" style="
				background: none;
				border: none;
				color: #f0f0f1;
				cursor: pointer;
				font-size: 18px;
				padding: 0;
			">&times;</button>
		</div>
		
		<div style="padding: 16px;">
			<div id="gstore-diag-viewport" style="
				background: #2c3338;
				padding: 10px;
				border-radius: 4px;
				margin-bottom: 16px;
				text-align: center;
			">
				Viewport: <strong id="gstore-diag-width"></strong>
				<span id="gstore-diag-mode" style="
					display: inline-block;
					padding: 2px 8px;
					border-radius: 3px;
					margin-left: 8px;
					font-size: 11px;
					font-weight: 600;
				"></span>
			</div>
			
			<div id="gstore-diag-results"></div>
			
			<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #3c434a;">
				<button onclick="gstoreRunDiagnostics();" style="
					width: 100%;
					padding: 10px;
					background: #2271b1;
					color: #fff;
					border: none;
					border-radius: 4px;
					cursor: pointer;
					font-weight: 500;
				">🔄 Executar Diagnóstico</button>
				
				<button onclick="console.log(gstoreGetDiagnosticsScript());" style="
					width: 100%;
					padding: 10px;
					margin-top: 8px;
					background: #3c434a;
					color: #f0f0f1;
					border: none;
					border-radius: 4px;
					cursor: pointer;
					font-weight: 500;
				">📋 Ver Script no Console</button>
			</div>
		</div>
	</div>
	
	<script>
	var gstoreDiagRules = <?php echo wp_json_encode( $rules ); ?>;
	
	function gstoreGetDiagnosticsScript() {
		return <?php echo wp_json_encode( gstore_generate_css_diagnostics_script() ); ?>;
	}
	
	function gstoreRunDiagnostics() {
		var resultsContainer = document.getElementById('gstore-diag-results');
		var widthEl = document.getElementById('gstore-diag-width');
		var modeEl = document.getElementById('gstore-diag-mode');
		var isMobile = window.innerWidth <= 900;
		
		widthEl.textContent = window.innerWidth + 'px';
		modeEl.textContent = isMobile ? 'MOBILE' : 'DESKTOP';
		modeEl.style.background = isMobile ? '#00a32a' : '#2271b1';
		
		var html = '';
		var passed = 0, failed = 0, notFound = 0;
		
		Object.keys(gstoreDiagRules).forEach(function(key) {
			var rule = gstoreDiagRules[key];
			var shouldCheck = (rule.viewport === 'mobile' && isMobile) || 
			                  (rule.viewport === 'desktop' && !isMobile) ||
			                  !rule.viewport;
			
			if (!shouldCheck) {
				return;
			}
			
			var element = document.querySelector(rule.selector);
			var status, statusColor, statusIcon;
			
			if (!element) {
				status = 'Elemento não encontrado';
				statusColor = '#dba617';
				statusIcon = '❓';
				notFound++;
			} else {
				var computedStyle = window.getComputedStyle(element);
				var actualValue = computedStyle.getPropertyValue(rule.property).trim();
				
				if (actualValue === rule.expected) {
					status = rule.property + ': ' + actualValue;
					statusColor = '#00a32a';
					statusIcon = '✅';
					passed++;
				} else {
					status = 'Esperado: ' + rule.expected + ' | Atual: ' + actualValue;
					statusColor = '#d63638';
					statusIcon = '❌';
					failed++;
				}
			}
			
			html += '<div style="background: #2c3338; padding: 12px; border-radius: 4px; margin-bottom: 8px; border-left: 3px solid ' + statusColor + ';">';
			html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
			html += '<strong>' + statusIcon + ' ' + rule.name + '</strong>';
			html += '</div>';
			html += '<div style="font-size: 11px; color: #a7aaad; margin-top: 6px;">' + status + '</div>';
			html += '<div style="font-size: 10px; color: #72777c; margin-top: 4px;">' + rule.selector + '</div>';
			html += '</div>';
		});
		
		// Resumo
		html = '<div style="display: flex; gap: 10px; margin-bottom: 16px;">' +
			'<div style="flex: 1; text-align: center; padding: 8px; background: #2c3338; border-radius: 4px;">' +
			'<div style="font-size: 20px; color: #00a32a;">' + passed + '</div>' +
			'<div style="font-size: 10px; color: #a7aaad;">Passou</div></div>' +
			'<div style="flex: 1; text-align: center; padding: 8px; background: #2c3338; border-radius: 4px;">' +
			'<div style="font-size: 20px; color: #d63638;">' + failed + '</div>' +
			'<div style="font-size: 10px; color: #a7aaad;">Falhou</div></div>' +
			'<div style="flex: 1; text-align: center; padding: 8px; background: #2c3338; border-radius: 4px;">' +
			'<div style="font-size: 20px; color: #dba617;">' + notFound + '</div>' +
			'<div style="font-size: 10px; color: #a7aaad;">N/A</div></div>' +
			'</div>' + html;
		
		resultsContainer.innerHTML = html;
	}
	
	// Executa automaticamente ao carregar
	document.addEventListener('DOMContentLoaded', gstoreRunDiagnostics);
	window.addEventListener('resize', gstoreRunDiagnostics);
	</script>
	<?php
}
add_action( 'wp_footer', 'gstore_frontend_diagnostics_panel', 9999 );

/**
 * AJAX: Retorna o script de diagnóstico para copiar.
 */
function gstore_ajax_get_diagnostics_script() {
	check_ajax_referer( 'gstore_setup_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}
	
	$script = gstore_generate_css_diagnostics_script();
	
	wp_send_json_success( array(
		'script' => $script,
		'rules'  => gstore_get_css_diagnostic_rules(),
	) );
}
add_action( 'wp_ajax_gstore_get_diagnostics_script', 'gstore_ajax_get_diagnostics_script' );

/**
 * Sincroniza as opções do WooCommerce/WordPress com as páginas atuais.
 *
 * @return array Resultado da sincronização.
 */
function gstore_sync_required_page_options() {
	$pages   = gstore_get_required_pages();
	$updates = array(
		'wc' => 0,
		'wp' => 0,
	);
	$front_page = null;
	$posts_page = null;

	foreach ( $pages as $page_config ) {
		$page = gstore_get_page_by_slug( $page_config['slug'] );

		if ( ! $page ) {
			continue;
		}

		if ( ! empty( $page_config['wc_option'] ) ) {
			update_option( $page_config['wc_option'], $page->ID );
			$updates['wc']++;
		}

		if ( ! empty( $page_config['wp_option'] ) ) {
			update_option( $page_config['wp_option'], $page->ID );
			$updates['wp']++;
		}

		if ( ! empty( $page_config['set_as'] ) ) {
			if ( 'front_page' === $page_config['set_as'] ) {
				$front_page = $page->ID;
			} elseif ( 'posts_page' === $page_config['set_as'] ) {
				$posts_page = $page->ID;
			}
		}
	}

	if ( $front_page ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page );
	}

	if ( $posts_page ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $posts_page );
	}

	$message = sprintf(
		__( '%1$d integrações WooCommerce e %2$d ajustes WordPress sincronizados.', 'gstore' ),
		$updates['wc'],
		$updates['wp']
	);

	return array(
		'success' => true,
		'message' => $message,
		'updates' => $updates,
	);
}

/**
 * Regrava as regras de permalink.
 *
 * @return array Resultado da operação.
 */
function gstore_flush_permalink_rules() {
	flush_rewrite_rules();

	return array(
		'success' => true,
		'message' => __( 'Links permanentes regenerados com sucesso.', 'gstore' ),
	);
}

/**
 * Processa ações AJAX do setup.
 */
function gstore_ajax_setup_action() {
	check_ajax_referer( 'gstore_setup_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}
	
	$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
	$page_key = isset( $_POST['page_key'] ) ? sanitize_text_field( $_POST['page_key'] ) : '';
	$force = isset( $_POST['force'] ) && 'true' === $_POST['force'];
	
	if ( 'create_single' === $action_type && ! empty( $page_key ) ) {
		$result = gstore_create_page( $page_key, $force );
		wp_send_json( $result );
	} elseif ( 'create_all' === $action_type ) {
		$results = gstore_create_all_pages( $force );
		$success_count = 0;
		$created_count = 0;
		
		foreach ( $results as $result ) {
			if ( $result['success'] ) {
				$success_count++;
				if ( 'created' === $result['action'] ) {
					$created_count++;
				}
			}
		}
		
		wp_send_json( array(
			'success' => true,
			'message' => sprintf(
				__( '%d páginas processadas, %d criadas.', 'gstore' ),
				$success_count,
				$created_count
			),
			'results' => $results,
		) );
	} elseif ( 'sync_assets' === $action_type ) {
		$result = gstore_run_asset_diagnostics();
		wp_send_json( $result );
	} elseif ( 'sync_pages' === $action_type ) {
		$result = gstore_sync_required_page_options();
		wp_send_json( $result );
	} elseif ( 'flush_permalinks' === $action_type ) {
		$result = gstore_flush_permalink_rules();
		wp_send_json( $result );
	} elseif ( 'get_css_diagnostics' === $action_type ) {
		$script = gstore_generate_css_diagnostics_script();
		$frontend_url = add_query_arg( 'gstore_diagnostics', '1', home_url( '/' ) );
		wp_send_json_success( array(
			'script'       => $script,
			'rules'        => gstore_get_css_diagnostic_rules(),
			'frontend_url' => $frontend_url,
			'message'      => __( 'Script de diagnóstico gerado! Cole no console do navegador em produção.', 'gstore' ),
		) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Ação inválida.', 'gstore' ) ) );
	}
}
add_action( 'wp_ajax_gstore_setup_action', 'gstore_ajax_setup_action' );

/**
 * Renderiza a página de setup do tema.
 */
function gstore_render_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	$pages = gstore_get_required_pages();
	?>
	<div class="wrap gstore-setup-wrap">
		<h1>
			<span class="dashicons dashicons-store" style="font-size: 30px; margin-right: 10px;"></span>
			<?php _e( 'Setup do Tema Gstore', 'gstore' ); ?>
		</h1>
		
		<div class="gstore-setup-intro">
			<p><?php _e( 'Esta ferramenta cria automaticamente todas as páginas necessárias para o funcionamento do tema Gstore. Cada página será configurada com o template correto e integrada com o WooCommerce.', 'gstore' ); ?></p>
		</div>
		
		<div class="gstore-setup-actions">
			<button type="button" id="gstore-create-all" class="button button-primary button-hero">
				<span class="dashicons dashicons-welcome-add-page"></span>
				<?php _e( 'Criar Todas as Páginas', 'gstore' ); ?>
			</button>
			
			<button type="button" id="gstore-recreate-all" class="button button-secondary">
				<span class="dashicons dashicons-update"></span>
				<?php _e( 'Recriar Todas (Sobrescrever)', 'gstore' ); ?>
			</button>
		</div>
		
		<div class="gstore-setup-status" id="gstore-setup-status" style="display: none;">
			<div class="gstore-setup-status__content">
				<span class="spinner is-active"></span>
				<span class="gstore-setup-status__message"></span>
			</div>
		</div>
		
		<table class="wp-list-table widefat fixed striped gstore-pages-table">
			<thead>
				<tr>
					<th class="column-status" style="width: 80px;"><?php _e( 'Status', 'gstore' ); ?></th>
					<th class="column-title"><?php _e( 'Página', 'gstore' ); ?></th>
					<th class="column-template" style="width: 160px;"><?php _e( 'Template', 'gstore' ); ?></th>
					<th class="column-description"><?php _e( 'Descrição', 'gstore' ); ?></th>
					<th class="column-actions" style="width: 200px;"><?php _e( 'Ações', 'gstore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pages as $page_key => $page_config ) : 
					$existing_page = gstore_get_page_by_slug( $page_config['slug'] );
					$status = $existing_page ? 'exists' : 'missing';
					$status_class = $existing_page ? 'gstore-status--success' : 'gstore-status--warning';
					$status_icon = $existing_page ? 'yes-alt' : 'warning';
					$status_text = $existing_page ? __( 'Existe', 'gstore' ) : __( 'Não existe', 'gstore' );
				?>
				<tr id="gstore-page-row-<?php echo esc_attr( $page_key ); ?>" data-page-key="<?php echo esc_attr( $page_key ); ?>">
					<td class="column-status">
						<span class="gstore-status <?php echo esc_attr( $status_class ); ?>">
							<span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>"></span>
							<?php echo esc_html( $status_text ); ?>
						</span>
					</td>
					<td class="column-title">
						<strong><?php echo esc_html( $page_config['title'] ); ?></strong>
						<div class="row-actions">
							<span class="slug">/<code><?php echo esc_html( $page_config['slug'] ); ?></code></span>
							<?php if ( $existing_page ) : ?>
								| <a href="<?php echo esc_url( get_permalink( $existing_page->ID ) ); ?>" target="_blank"><?php _e( 'Ver', 'gstore' ); ?></a>
								| <a href="<?php echo esc_url( get_edit_post_link( $existing_page->ID ) ); ?>"><?php _e( 'Editar', 'gstore' ); ?></a>
							<?php endif; ?>
						</div>
					</td>
					<td class="column-template">
						<?php if ( ! empty( $page_config['template'] ) ) : ?>
							<code><?php echo esc_html( $page_config['template'] ); ?></code>
						<?php else : ?>
							<span class="gstore-muted"><?php _e( 'Padrão', 'gstore' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="column-description">
						<?php echo esc_html( $page_config['description'] ); ?>
						<?php if ( ! empty( $page_config['wc_option'] ) ) : ?>
							<br><small class="gstore-badge gstore-badge--wc">WooCommerce</small>
						<?php endif; ?>
						<?php if ( ! empty( $page_config['set_as'] ) ) : ?>
							<br><small class="gstore-badge gstore-badge--wp">
								<?php 
								if ( 'front_page' === $page_config['set_as'] ) {
									_e( 'Página Inicial', 'gstore' );
								} elseif ( 'posts_page' === $page_config['set_as'] ) {
									_e( 'Página de Posts', 'gstore' );
								}
								?>
							</small>
						<?php endif; ?>
					</td>
					<td class="column-actions">
						<?php if ( $existing_page ) : ?>
							<button type="button" class="button gstore-recreate-page" data-page-key="<?php echo esc_attr( $page_key ); ?>">
								<span class="dashicons dashicons-update"></span>
								<?php _e( 'Recriar', 'gstore' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-primary gstore-create-page" data-page-key="<?php echo esc_attr( $page_key ); ?>">
								<span class="dashicons dashicons-plus-alt"></span>
								<?php _e( 'Criar', 'gstore' ); ?>
							</button>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<div class="gstore-setup-info">
			<h3><span class="dashicons dashicons-info"></span> <?php _e( 'Informações', 'gstore' ); ?></h3>
			<ul>
				<li><?php _e( '<strong>Criar:</strong> Cria a página apenas se ela não existir.', 'gstore' ); ?></li>
				<li><?php _e( '<strong>Recriar:</strong> Remove a página existente e cria uma nova com as configurações padrão do tema.', 'gstore' ); ?></li>
				<li><?php _e( '<strong>Templates:</strong> Páginas com template específico usam layouts customizados do tema Gstore.', 'gstore' ); ?></li>
				<li><?php _e( '<strong>WooCommerce:</strong> Páginas marcadas com WooCommerce são automaticamente configuradas nas opções da loja.', 'gstore' ); ?></li>
			</ul>
		</div>

		<div class="gstore-setup-utilities">
			<h3><span class="dashicons dashicons-hammer"></span> <?php _e( 'Automatizações úteis', 'gstore' ); ?></h3>
			<p><?php _e( 'Execute correções rápidas após instalar o tema ou migrar o site.', 'gstore' ); ?></p>

			<div class="gstore-setup-utilities__grid">
				<div class="gstore-setup-card">
					<h4><?php _e( 'Verificar assets críticos', 'gstore' ); ?></h4>
					<p><?php _e( 'Confere se os arquivos CSS e JS do carrossel e do header estão disponíveis mesmo em child themes.', 'gstore' ); ?></p>
					<button type="button" class="button button-primary gstore-utility-action" data-action="sync_assets" data-loading-text="<?php esc_attr_e( 'Verificando assets...', 'gstore' ); ?>">
						<span class="dashicons dashicons-admin-appearance"></span>
						<?php _e( 'Executar verificação', 'gstore' ); ?>
					</button>
				</div>

				<div class="gstore-setup-card">
					<h4><?php _e( 'Sincronizar páginas do WooCommerce', 'gstore' ); ?></h4>
					<p><?php _e( 'Reatribui carrinho, checkout, minha conta e páginas estáticas nas opções oficiais.', 'gstore' ); ?></p>
					<button type="button" class="button button-secondary gstore-utility-action" data-action="sync_pages" data-loading-text="<?php esc_attr_e( 'Sincronizando páginas...', 'gstore' ); ?>">
						<span class="dashicons dashicons-update-alt"></span>
						<?php _e( 'Sincronizar páginas', 'gstore' ); ?>
					</button>
				</div>

				<div class="gstore-setup-card">
					<h4><?php _e( 'Regravar links permanentes', 'gstore' ); ?></h4>
					<p><?php _e( 'Executa o flush das regras de permalink para resolver erros 404 após migrações.', 'gstore' ); ?></p>
					<button type="button" class="button gstore-utility-action" data-action="flush_permalinks" data-loading-text="<?php esc_attr_e( 'Regravando links...', 'gstore' ); ?>">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php _e( 'Regravar links', 'gstore' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="gstore-setup-diagnostics">
			<h3><span class="dashicons dashicons-visibility"></span> <?php _e( 'Diagnóstico de CSS em Produção', 'gstore' ); ?></h3>
			<p><?php _e( 'Verifique se as regras CSS críticas estão sendo aplicadas corretamente no frontend. Útil para identificar problemas de cache ou deploy.', 'gstore' ); ?></p>
			
			<div class="gstore-setup-diagnostics__actions">
				<button type="button" id="gstore-open-frontend-diag" class="button button-primary">
					<span class="dashicons dashicons-external"></span>
					<?php _e( 'Abrir Diagnóstico Visual', 'gstore' ); ?>
				</button>
				
				<button type="button" id="gstore-copy-diag-script" class="button">
					<span class="dashicons dashicons-clipboard"></span>
					<?php _e( 'Copiar Script para Console', 'gstore' ); ?>
				</button>
			</div>
			
			<div id="gstore-diag-script-container" style="display: none; margin-top: 16px;">
				<p class="description"><?php _e( 'Cole este script no console do navegador (F12) em produção:', 'gstore' ); ?></p>
				<textarea id="gstore-diag-script-textarea" readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px; background: #1d2327; color: #f0f0f1; border: 1px solid #3c434a; border-radius: 4px; padding: 12px;"></textarea>
			</div>
			
			<div class="gstore-setup-diagnostics__rules" style="margin-top: 20px;">
				<h4><?php _e( 'Regras CSS Monitoradas', 'gstore' ); ?></h4>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 30%;"><?php _e( 'Regra', 'gstore' ); ?></th>
							<th style="width: 25%;"><?php _e( 'Seletor', 'gstore' ); ?></th>
							<th style="width: 20%;"><?php _e( 'Propriedade Esperada', 'gstore' ); ?></th>
							<th style="width: 10%;"><?php _e( 'Viewport', 'gstore' ); ?></th>
							<th style="width: 15%;"><?php _e( 'Arquivo', 'gstore' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php 
						$rules = gstore_get_css_diagnostic_rules();
						foreach ( $rules as $key => $rule ) : 
						?>
						<tr>
							<td><strong><?php echo esc_html( $rule['name'] ); ?></strong></td>
							<td><code style="font-size: 11px;"><?php echo esc_html( $rule['selector'] ); ?></code></td>
							<td><code><?php echo esc_html( $rule['property'] . ': ' . $rule['expected'] ); ?></code></td>
							<td>
								<span class="gstore-badge gstore-badge--<?php echo 'mobile' === $rule['viewport'] ? 'wc' : 'wp'; ?>">
									<?php echo esc_html( ucfirst( $rule['viewport'] ) ); ?>
								</span>
							</td>
							<td><code style="font-size: 10px;"><?php echo esc_html( $rule['css_file'] ); ?></code></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- DIAGNÓSTICO DE ESTRUTURA DO CARRINHO -->
		<div class="gstore-setup-diagnostics" style="margin-top: 30px; border-top: 2px solid #c9a43a; padding-top: 20px;">
			<h3><span class="dashicons dashicons-code-standards"></span> <?php _e( 'Diagnóstico de Estrutura do Carrinho', 'gstore' ); ?></h3>
			<p><?php _e( 'Analise a estrutura HTML da página do carrinho para identificar problemas de layout. Clique no botão e depois vá para a página do carrinho.', 'gstore' ); ?></p>
			
			<?php 
			$cart_page = gstore_get_page_by_slug( 'carrinho' );
			$cart_url = $cart_page ? get_permalink( $cart_page->ID ) : wc_get_cart_url();
			?>
			
			<div class="gstore-setup-diagnostics__actions" style="margin-bottom: 20px;">
				<a href="<?php echo esc_url( add_query_arg( 'gstore_cart_debug', '1', $cart_url ) ); ?>" target="_blank" class="button button-primary">
					<span class="dashicons dashicons-visibility"></span>
					<?php _e( 'Abrir Carrinho com Diagnóstico', 'gstore' ); ?>
				</a>
				
				<button type="button" id="gstore-copy-cart-debug-script" class="button">
					<span class="dashicons dashicons-clipboard"></span>
					<?php _e( 'Copiar Script de Debug', 'gstore' ); ?>
				</button>
			</div>
			
			<div id="gstore-cart-debug-script" style="background: #1d2327; padding: 15px; border-radius: 6px; margin-top: 15px;">
				<p style="color: #f0f0f1; margin: 0 0 10px; font-size: 13px;"><strong>Cole este código no Console do navegador (F12) na página do carrinho:</strong></p>
				<pre style="color: #86efac; font-size: 12px; white-space: pre-wrap; word-break: break-all; margin: 0; max-height: 400px; overflow: auto;"><?php echo esc_html( gstore_get_cart_debug_script() ); ?></pre>
			</div>
		</div>
	</div>
	
	<style>
		.gstore-setup-wrap {
			max-width: 1200px;
		}
		.gstore-setup-wrap h1 {
			display: flex;
			align-items: center;
			margin-bottom: 20px;
		}
		.gstore-setup-intro {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-left: 4px solid #2271b1;
			padding: 12px 20px;
			margin-bottom: 20px;
		}
		.gstore-setup-intro p {
			margin: 0;
			font-size: 14px;
		}
		.gstore-setup-actions {
			margin-bottom: 20px;
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
		}
		.gstore-setup-actions .button-hero {
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.gstore-setup-actions .button-hero .dashicons {
			font-size: 24px;
		}
		.gstore-setup-status {
			background: #f0f6fc;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 15px 20px;
			margin-bottom: 20px;
		}
		.gstore-setup-status__content {
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.gstore-setup-status__content .spinner {
			float: none;
			margin: 0;
		}
		.gstore-setup-status--success {
			background: #d1e7dd;
			border-color: #badbcc;
		}
		.gstore-setup-status--error {
			background: #f8d7da;
			border-color: #f5c2c7;
		}
		.gstore-pages-table {
			margin-bottom: 20px;
		}
		.gstore-pages-table .dashicons {
			font-size: 18px;
			width: 18px;
			height: 18px;
			vertical-align: middle;
		}
		.gstore-pages-table .button .dashicons {
			margin-right: 4px;
		}
		.gstore-status {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			font-size: 12px;
			font-weight: 500;
		}
		.gstore-status--success {
			color: #00a32a;
		}
		.gstore-status--warning {
			color: #dba617;
		}
		.gstore-muted {
			color: #646970;
		}
		.gstore-badge {
			display: inline-block;
			padding: 2px 6px;
			border-radius: 3px;
			font-size: 11px;
			font-weight: 500;
			margin-top: 4px;
		}
		.gstore-badge--wc {
			background: #7f54b3;
			color: #fff;
		}
		.gstore-badge--wp {
			background: #2271b1;
			color: #fff;
		}
		.gstore-setup-info {
			background: #f6f7f7;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 15px 20px;
		}
		.gstore-setup-info h3 {
			margin: 0 0 10px;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.gstore-setup-info ul {
			margin: 0;
			padding-left: 20px;
		}
		.gstore-setup-info li {
			margin-bottom: 5px;
		}
		.gstore-setup-utilities {
			margin-top: 25px;
			padding: 20px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
		}
		.gstore-setup-utilities__grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 16px;
			margin-top: 16px;
		}
		.gstore-setup-card {
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			padding: 16px;
			display: flex;
			flex-direction: column;
			gap: 12px;
		}
		.gstore-setup-card h4 {
			margin: 0;
		}
		.gstore-utility-action .dashicons {
			margin-right: 4px;
		}
		.gstore-utility-action.is-busy {
			opacity: 0.6;
			pointer-events: none;
		}
		.row-actions .slug {
			color: #646970;
		}
		.gstore-row-updating {
			opacity: 0.6;
			pointer-events: none;
		}
		.gstore-setup-diagnostics {
			margin-top: 25px;
			padding: 20px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
		}
		.gstore-setup-diagnostics h3 {
			display: flex;
			align-items: center;
			gap: 8px;
			margin: 0 0 10px;
		}
		.gstore-setup-diagnostics__actions {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
			margin-top: 16px;
		}
		.gstore-setup-diagnostics__actions .button {
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.gstore-setup-diagnostics__rules {
			background: #f6f7f7;
			padding: 16px;
			border-radius: 4px;
		}
		.gstore-setup-diagnostics__rules h4 {
			margin: 0 0 12px;
		}
		.gstore-setup-diagnostics__rules table code {
			background: rgba(0,0,0,0.05);
			padding: 2px 6px;
			border-radius: 3px;
		}
		.gstore-copy-success {
			background: #00a32a !important;
			border-color: #00a32a !important;
			color: #fff !important;
		}
	</style>
	
	<script>
	jQuery(document).ready(function($) {
		var nonce = '<?php echo wp_create_nonce( 'gstore_setup_nonce' ); ?>';
		var defaultSuccessMessage = '<?php echo esc_js( __( 'Ação concluída.', 'gstore' ) ); ?>';
		var defaultLoadingMessage = '<?php echo esc_js( __( 'Executando ação...', 'gstore' ) ); ?>';
		
		function showStatus(message, type) {
			var $status = $('#gstore-setup-status');
			$status.removeClass('gstore-setup-status--success gstore-setup-status--error');
			
			if (type === 'success') {
				$status.addClass('gstore-setup-status--success');
				$status.find('.spinner').removeClass('is-active');
			} else if (type === 'error') {
				$status.addClass('gstore-setup-status--error');
				$status.find('.spinner').removeClass('is-active');
			} else {
				$status.find('.spinner').addClass('is-active');
			}
			
			$status.find('.gstore-setup-status__message').text(message);
			$status.show();
		}
		
		function updateRowStatus($row, success) {
			var $statusCell = $row.find('.column-status');
			var $actionsCell = $row.find('.column-actions');
			var pageKey = $row.data('page-key');
			
			if (success) {
				$statusCell.html('<span class="gstore-status gstore-status--success"><span class="dashicons dashicons-yes-alt"></span> Existe</span>');
				$actionsCell.html('<button type="button" class="button gstore-recreate-page" data-page-key="' + pageKey + '"><span class="dashicons dashicons-update"></span> Recriar</button>');
			}
			
			$row.removeClass('gstore-row-updating');
		}
		
		// Criar página individual
		$(document).on('click', '.gstore-create-page, .gstore-recreate-page', function() {
			var $btn = $(this);
			var pageKey = $btn.data('page-key');
			var $row = $('#gstore-page-row-' + pageKey);
			var force = $btn.hasClass('gstore-recreate-page');
			
			$row.addClass('gstore-row-updating');
			showStatus(force ? 'Recriando página...' : 'Criando página...', 'loading');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'gstore_setup_action',
					action_type: 'create_single',
					page_key: pageKey,
					force: force ? 'true' : 'false',
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						showStatus(response.message, 'success');
						updateRowStatus($row, true);
					} else {
						showStatus(response.message || 'Erro ao criar página.', 'error');
						$row.removeClass('gstore-row-updating');
					}
				},
				error: function() {
					showStatus('Erro de conexão.', 'error');
					$row.removeClass('gstore-row-updating');
				}
			});
		});
		
		// Criar todas as páginas
		$('#gstore-create-all').on('click', function() {
			createAllPages(false);
		});
		
		// Recriar todas as páginas
		$('#gstore-recreate-all').on('click', function() {
			if (confirm('Tem certeza? Isso irá SOBRESCREVER todas as páginas existentes com o conteúdo padrão do tema.')) {
				createAllPages(true);
			}
		});
		
		function createAllPages(force) {
			var $rows = $('.gstore-pages-table tbody tr');
			$rows.addClass('gstore-row-updating');
			showStatus('Criando páginas...', 'loading');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'gstore_setup_action',
					action_type: 'create_all',
					force: force ? 'true' : 'false',
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						showStatus(response.message, 'success');
						
						// Atualiza o status de cada linha
						$.each(response.results, function(pageKey, result) {
							var $row = $('#gstore-page-row-' + pageKey);
							if (result.success) {
								updateRowStatus($row, true);
							} else {
								$row.removeClass('gstore-row-updating');
							}
						});
					} else {
						showStatus(response.message || 'Erro ao criar páginas.', 'error');
						$rows.removeClass('gstore-row-updating');
					}
				},
				error: function() {
					showStatus('Erro de conexão.', 'error');
					$rows.removeClass('gstore-row-updating');
				}
			});
		}

		// Utilidades extras
		$(document).on('click', '.gstore-utility-action', function() {
			var $btn = $(this);
			var actionType = $btn.data('action');
			var loadingText = $btn.data('loading-text') || defaultLoadingMessage;

			if (!actionType || $btn.hasClass('is-busy')) {
				return;
			}

			$btn.addClass('is-busy').prop('disabled', true);
			showStatus(loadingText, 'loading');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'gstore_setup_action',
					action_type: actionType,
					nonce: nonce
				},
				success: function(response) {
					var message = response.message || defaultSuccessMessage;

					if (response.success) {
						showStatus(message, 'success');
					} else {
						if (response.missing && response.missing.length) {
							message += ' (' + response.missing.join(', ') + ')';
						}
						showStatus(message, 'error');
					}
				},
				error: function() {
					showStatus('Erro de conexão.', 'error');
				},
				complete: function() {
					$btn.removeClass('is-busy').prop('disabled', false);
				}
			});
		});

		// Diagnóstico CSS - Abrir no frontend
		$('#gstore-open-frontend-diag').on('click', function() {
			var frontendUrl = '<?php echo esc_js( add_query_arg( 'gstore_diagnostics', '1', home_url( '/' ) ) ); ?>';
			window.open(frontendUrl, '_blank');
		});

		// Diagnóstico CSS - Copiar script para console
		$('#gstore-copy-diag-script').on('click', function() {
			var $btn = $(this);
			var $container = $('#gstore-diag-script-container');
			var $textarea = $('#gstore-diag-script-textarea');

			if ($container.is(':visible') && $textarea.val()) {
				// Se já está visível e tem conteúdo, apenas copia
				copyToClipboard($textarea.val(), $btn);
				return;
			}

			$btn.addClass('is-busy').prop('disabled', true);
			showStatus('Gerando script de diagnóstico...', 'loading');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'gstore_setup_action',
					action_type: 'get_css_diagnostics',
					nonce: nonce
				},
				success: function(response) {
					if (response.success && response.data.script) {
						$textarea.val(response.data.script);
						$container.slideDown();
						showStatus(response.data.message, 'success');
						copyToClipboard(response.data.script, $btn);
					} else {
						showStatus('Erro ao gerar script.', 'error');
					}
				},
				error: function() {
					showStatus('Erro de conexão.', 'error');
				},
				complete: function() {
					$btn.removeClass('is-busy').prop('disabled', false);
				}
			});
		});

		function copyToClipboard(text, $btn) {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					showCopySuccess($btn);
				}).catch(function() {
					fallbackCopy(text, $btn);
				});
			} else {
				fallbackCopy(text, $btn);
			}
		}

		function fallbackCopy(text, $btn) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			document.execCommand('copy');
			$temp.remove();
			showCopySuccess($btn);
		}

		function showCopySuccess($btn) {
			var originalText = $btn.html();
			$btn.addClass('gstore-copy-success').html('<span class="dashicons dashicons-yes"></span> Copiado!');
			setTimeout(function() {
				$btn.removeClass('gstore-copy-success').html(originalText);
			}, 2000);
		}
	});
	</script>
	<?php
}

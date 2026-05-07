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

	// Locais de menu para barra desktop e mobile (atribuição em Loja > Navegação)
	register_nav_menus(
		array(
			'gstore_desktop' => __( 'Barra de navegação (Desktop)', 'gstore' ),
			'gstore_mobile'  => __( 'Barra de navegação (Mobile)', 'gstore' ),
		)
	);
}
add_action( 'after_setup_theme', 'gstore_after_setup_theme' );

/**
 * Obtém a cor de accent atual definida em assets/css/tokens.css.
 *
 * @return string Cor hex sanitizada ou string vazia se não encontrada.
 */
function gstore_get_accent_color_from_tokens_file() {
	$tokens_file = get_theme_file_path( 'assets/css/tokens.css' );

	if ( ! file_exists( $tokens_file ) || ! is_readable( $tokens_file ) ) {
		return '';
	}

	$content = file_get_contents( $tokens_file );
	if ( false === $content ) {
		return '';
	}

	if ( preg_match( '/--gstore-color-accent:\s*(#[0-9a-fA-F]{3,6})\s*;/', $content, $matches ) ) {
		$accent_color = sanitize_hex_color( $matches[1] );
		return $accent_color ? $accent_color : '';
	}

	return '';
}

/**
 * Cor padrão de accent usada pelo tema.
 *
 * @return string Cor hex.
 */
function gstore_get_default_accent_color() {
	return '#b5a642';
}

/**
 * Indica se a cor recebida e o fallback default do tema.
 *
 * @param string $color Cor hexadecimal.
 * @return bool
 */
function gstore_is_default_accent_color( $color ) {
	return strtolower( (string) $color ) === strtolower( gstore_get_default_accent_color() );
}

/**
 * Mapeia os tokens derivados de accent para as variaveis CSS persistidas por loja.
 *
 * @return array<string,string>
 */
function gstore_get_accent_token_css_var_map() {
	return array(
		'accent'       => '--gstore-color-accent',
		'accent-hover' => '--gstore-color-accent-hover',
		'accent-dark'  => '--gstore-color-accent-dark',
		'accent-light' => '--gstore-color-accent-light',
		'accent-08'    => '--gstore-color-accent-08',
		'accent-10'    => '--gstore-color-accent-10',
		'accent-12'    => '--gstore-color-accent-12',
		'accent-15'    => '--gstore-color-accent-15',
		'accent-20'    => '--gstore-color-accent-20',
	);
}

/**
 * Option que guarda overrides de design tokens independentes da pasta do tema.
 *
 * @return string
 */
function gstore_get_design_tokens_option_key() {
	return 'gstore_design_tokens_overrides';
}

/**
 * Sanitiza um valor simples de token CSS controlado pelo admin.
 *
 * @param string $value Valor CSS.
 * @return string
 */
function gstore_sanitize_design_token_value( $value ) {
	$value = trim( (string) $value );

	$hex = sanitize_hex_color( $value );
	if ( $hex ) {
		return strtolower( $hex );
	}

	if ( preg_match( '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|1|0?\.\d+)\s*\)$/i', $value, $matches ) ) {
		return sprintf(
			'rgba(%d, %d, %d, %.2f)',
			max( 0, min( 255, (int) $matches[1] ) ),
			max( 0, min( 255, (int) $matches[2] ) ),
			max( 0, min( 255, (int) $matches[3] ) ),
			max( 0, min( 1, (float) $matches[4] ) )
		);
	}

	return '';
}

/**
 * Retorna os overrides de tokens salvos no banco.
 *
 * @return array<string,string>
 */
function gstore_get_saved_design_token_overrides() {
	$raw = get_option( gstore_get_design_tokens_option_key(), array() );
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$allowed_vars = array_values( gstore_get_accent_token_css_var_map() );
	$tokens       = array();

	foreach ( $allowed_vars as $css_var ) {
		if ( ! array_key_exists( $css_var, $raw ) ) {
			continue;
		}

		$value = gstore_sanitize_design_token_value( $raw[ $css_var ] );
		if ( '' !== $value ) {
			$tokens[ $css_var ] = $value;
		}
	}

	return $tokens;
}

/**
 * Le a cor de accent do Store Info do plugin quando ela existir.
 *
 * @return string
 */
function gstore_get_store_info_accent_color() {
	if ( ! function_exists( 'gstore_store_info' ) ) {
		return '';
	}

	$store_info = gstore_store_info();
	if ( ! is_object( $store_info ) || ! method_exists( $store_info, 'get_value' ) ) {
		return '';
	}

	$color = sanitize_hex_color( (string) $store_info->get_value( 'branding.accent_color', '' ) );
	return $color ? $color : '';
}

/**
 * Sincroniza o Store Info com a cor salva em Design Tokens, quando o plugin existir.
 *
 * @param string $accent_color Cor base.
 * @return void
 */
function gstore_sync_store_info_accent_color( $accent_color ) {
	$accent_color = sanitize_hex_color( $accent_color );
	if ( ! $accent_color || ! function_exists( 'gstore_store_info' ) ) {
		return;
	}

	$store_info = gstore_store_info();
	if (
		! is_object( $store_info )
		|| ! method_exists( $store_info, 'get_all' )
		|| ! method_exists( $store_info, 'save_to_json' )
	) {
		return;
	}

	$data = $store_info->get_all();
	if ( ! is_array( $data ) ) {
		return;
	}

	if ( ! isset( $data['branding'] ) || ! is_array( $data['branding'] ) ) {
		$data['branding'] = array();
	}

	$current = isset( $data['branding']['accent_color'] ) ? sanitize_hex_color( (string) $data['branding']['accent_color'] ) : '';
	if ( $current && strtolower( $current ) === strtolower( $accent_color ) ) {
		return;
	}

	$data['branding']['accent_color'] = $accent_color;
	$store_info->save_to_json( $data );
}

/**
 * Retorna a cor de accent salva nos overrides de design tokens.
 *
 * @return string
 */
function gstore_get_accent_color_from_design_token_overrides() {
	$tokens = gstore_get_saved_design_token_overrides();
	$color  = isset( $tokens['--gstore-color-accent'] ) ? sanitize_hex_color( $tokens['--gstore-color-accent'] ) : '';

	return $color ? $color : '';
}

/**
 * Gera overrides CSS a partir da cor de accent.
 *
 * @param string $accent_color Cor base.
 * @return array<string,string>
 */
function gstore_build_accent_design_token_overrides( $accent_color ) {
	$accent_color = sanitize_hex_color( $accent_color );
	if ( ! $accent_color ) {
		return array();
	}

	$generated = gstore_generate_accent_tokens( $accent_color );
	$token_map = gstore_get_accent_token_css_var_map();
	$overrides = array();

	foreach ( $token_map as $token_key => $css_var ) {
		if ( empty( $generated[ $token_key ] ) ) {
			continue;
		}

		$value = gstore_sanitize_design_token_value( $generated[ $token_key ] );
		if ( '' !== $value ) {
			$overrides[ $css_var ] = $value;
		}
	}

	return $overrides;
}

/**
 * Persiste tokens derivados em wp_options, fora da pasta do tema.
 *
 * @param string $accent_color Cor base.
 * @return bool
 */
function gstore_persist_accent_design_token_overrides( $accent_color ) {
	$accent_color = sanitize_hex_color( $accent_color );
	if ( ! $accent_color ) {
		return false;
	}

	$current   = gstore_get_saved_design_token_overrides();
	$generated = gstore_build_accent_design_token_overrides( $accent_color );
	if ( empty( $generated ) ) {
		return false;
	}

	$next        = array_merge( $current, $generated );
	$saved_color = sanitize_hex_color( (string) get_option( 'gstore_accent_color', '' ) );
	$changed     = $next !== $current || strtolower( (string) $saved_color ) !== strtolower( $accent_color );

	if ( $next !== $current ) {
		update_option( gstore_get_design_tokens_option_key(), $next, true );
	}

	if ( ! $saved_color || strtolower( $saved_color ) !== strtolower( $accent_color ) ) {
		update_option( 'gstore_accent_color', $accent_color, true );
	}

	if ( $changed ) {
		update_option( 'gstore_tokens_last_updated', time(), true );
	}

	return true;
}

/**
 * Monta o CSS inline dos tokens por loja.
 *
 * @return string
 */
function gstore_get_design_token_overrides_css() {
	$tokens          = gstore_get_saved_design_token_overrides();
	$accent_color    = gstore_get_effective_accent_color();
	$current_accent  = isset( $tokens['--gstore-color-accent'] ) ? sanitize_hex_color( $tokens['--gstore-color-accent'] ) : '';
	$accent_mismatch = $accent_color && ( ! $current_accent || strtolower( $current_accent ) !== strtolower( $accent_color ) );

	if ( empty( $tokens ) || $accent_mismatch ) {
		$tokens = array_merge( $tokens, gstore_build_accent_design_token_overrides( $accent_color ) );
	}

	if ( empty( $tokens ) ) {
		return '';
	}

	$lines = array( ':root {' );
	foreach ( $tokens as $css_var => $value ) {
		$lines[] = sprintf( "\t%s: %s;", $css_var, $value );
	}
	$lines[] = '}';

	return implode( "\n", $lines );
}

/**
 * Retorna a URL publica do catalogo usada pelo tema.
 *
 * @return string URL do catalogo.
 */
function gstore_get_catalog_url() {
	$catalog_url  = '';
	$catalog_page = get_page_by_path( 'catalogo' );

	if ( $catalog_page instanceof WP_Post && 'publish' === $catalog_page->post_status ) {
		$catalog_url = get_permalink( $catalog_page->ID );
	}

	if ( ! $catalog_url && function_exists( 'wc_get_page_permalink' ) ) {
		$shop_url = wc_get_page_permalink( 'shop' );
		if ( is_string( $shop_url ) && '' !== $shop_url && ! is_wp_error( $shop_url ) ) {
			$catalog_url = $shop_url;
		}
	}

	if ( ! $catalog_url ) {
		$catalog_url = home_url( '/catalogo/' );
	}

	return apply_filters( 'gstore_catalog_url', user_trailingslashit( $catalog_url ) );
}

/**
 * Retorna o nome publico da loja para textos de SEO sem fixar uma marca no tema.
 *
 * @return string
 */
function gstore_get_store_display_name_for_seo() {
	$store_name = function_exists( 'gstore_get_store_name' ) ? gstore_get_store_name( 'display' ) : get_bloginfo( 'name' );
	$store_name = trim( wp_strip_all_tags( (string) $store_name ) );

	if ( '' === $store_name ) {
		$store_name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
	}

	return '' !== $store_name ? $store_name : __( 'loja', 'gstore' );
}

/**
 * Indica se a pagina atual e a landing geral /catalogo/.
 *
 * @return bool
 */
function gstore_is_catalog_landing_page() {
	return ! is_admin() && function_exists( 'is_page' ) && is_page( 'catalogo' );
}

/**
 * Titulo SEO da pagina-mae do catalogo.
 *
 * @return string
 */
function gstore_get_catalog_landing_seo_title() {
	return sprintf(
		__( 'Catálogo: Armas, Munições, Airsoft e Acessórios | %s', 'gstore' ),
		gstore_get_store_display_name_for_seo()
	);
}

/**
 * Meta description da pagina-mae do catalogo.
 *
 * @return string
 */
function gstore_get_catalog_landing_meta_description() {
	return sprintf(
		__( 'Explore o catálogo da loja %s com armas de fogo, munições, airsoft, carabinas de pressão, acessórios e itens outdoor. Filtre por marca e categoria.', 'gstore' ),
		gstore_get_store_display_name_for_seo()
	);
}

/**
 * Texto de topo da pagina-mae do catalogo.
 *
 * @return string
 */
function gstore_get_catalog_landing_intro_text() {
	return sprintf(
		__( 'No catálogo da loja %s, você encontra produtos para tiro esportivo, uso profissional, airsoft, pressão e outdoor, incluindo armas de fogo, munições, pistolas, revólveres, espingardas, carabinas, rifles, carabinas de pressão, acessórios, coldres, lanternas e vestuário. Use os filtros para buscar por marca, categoria, calibre e faixa de preço. Produtos controlados são vendidos apenas mediante documentação, autorização e requisitos legais aplicáveis.', 'gstore' ),
		gstore_get_store_display_name_for_seo()
	);
}

/**
 * Texto alternativo padrao da imagem SEO do catalogo.
 *
 * @return string
 */
function gstore_get_catalog_landing_image_alt() {
	return sprintf(
		__( 'Catálogo da loja %s com armas, munições, airsoft e acessórios', 'gstore' ),
		gstore_get_store_display_name_for_seo()
	);
}

/**
 * Shortcode do bloco introdutorio do catalogo.
 *
 * @return string
 */
function gstore_catalog_intro_shortcode() {
	$text = gstore_get_catalog_landing_intro_text();
	if ( '' === trim( $text ) ) {
		return '';
	}

	return '<section class="Gstore-catalog-seo-intro" aria-label="' . esc_attr__( 'Resumo do catálogo', 'gstore' ) . '"><p>' . esc_html( $text ) . '</p></section>';
}
add_shortcode( 'gstore_catalog_intro', 'gstore_catalog_intro_shortcode' );

/**
 * Aplica o titulo SEO dinamico no catalogo sem depender do nome de uma loja especifica.
 *
 * @param string $title Titulo original.
 * @return string
 */
function gstore_catalog_landing_pre_get_document_title( $title ) {
	return gstore_is_catalog_landing_page() ? gstore_get_catalog_landing_seo_title() : $title;
}
add_filter( 'pre_get_document_title', 'gstore_catalog_landing_pre_get_document_title', 30 );

/**
 * Compatibilidade com plugins SEO para o titulo do catalogo.
 *
 * @param string $title Titulo original.
 * @return string
 */
function gstore_catalog_landing_seo_plugin_title( $title ) {
	return gstore_is_catalog_landing_page() ? gstore_get_catalog_landing_seo_title() : $title;
}
add_filter( 'wpseo_title', 'gstore_catalog_landing_seo_plugin_title', 30 );
add_filter( 'rank_math/frontend/title', 'gstore_catalog_landing_seo_plugin_title', 30 );

/**
 * Imprime meta description da landing de catalogo quando nao houver plugin SEO assumindo.
 */
function gstore_catalog_landing_meta_description_tag() {
	if ( ! gstore_is_catalog_landing_page() || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
		return;
	}

	echo '<meta name="description" content="' . esc_attr( gstore_get_catalog_landing_meta_description() ) . '" />' . "\n";
}
add_action( 'wp_head', 'gstore_catalog_landing_meta_description_tag', 1 );

/**
 * Compatibilidade com plugins SEO para a description do catalogo.
 *
 * @param string $description Description original.
 * @return string
 */
function gstore_catalog_landing_seo_plugin_description( $description ) {
	return gstore_is_catalog_landing_page() ? gstore_get_catalog_landing_meta_description() : $description;
}
add_filter( 'wpseo_metadesc', 'gstore_catalog_landing_seo_plugin_description', 30 );
add_filter( 'rank_math/frontend/description', 'gstore_catalog_landing_seo_plugin_description', 30 );
add_filter( 'aioseo_description', 'gstore_catalog_landing_seo_plugin_description', 30 );

/**
 * Retorna uma imagem de produto para representar o catalogo geral em SEO/social.
 *
 * @return int
 */
function gstore_get_catalog_landing_product_image_id() {
	static $image_id = null;

	if ( null !== $image_id ) {
		return (int) $image_id;
	}

	$image_id    = 0;
	$product_ids = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => 24,
			'orderby'                => 'meta_value_num',
			'meta_key'               => 'total_sales',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	foreach ( array_map( 'absint', (array) $product_ids ) as $product_id ) {
		$candidate_id = absint( get_post_thumbnail_id( $product_id ) );
		if ( $candidate_id > 0 && 'attachment' === get_post_type( $candidate_id ) && wp_attachment_is_image( $candidate_id ) ) {
			$image_id = $candidate_id;
			break;
		}
	}

	return (int) $image_id;
}

/**
 * Retorna os dados da imagem SEO do catalogo.
 *
 * @return array<string,mixed>
 */
function gstore_get_catalog_landing_seo_image() {
	$image_id = gstore_get_catalog_landing_product_image_id();
	if ( $image_id <= 0 ) {
		return array();
	}

	$image = wp_get_attachment_image_src( $image_id, 'large' );
	if ( ! is_array( $image ) || empty( $image[0] ) ) {
		return array();
	}

	return array(
		'id'     => $image_id,
		'url'    => esc_url_raw( $image[0] ),
		'width'  => isset( $image[1] ) ? absint( $image[1] ) : 0,
		'height' => isset( $image[2] ) ? absint( $image[2] ) : 0,
		'type'   => (string) get_post_mime_type( $image_id ),
		'alt'    => gstore_get_catalog_landing_image_alt(),
	);
}

/**
 * Compatibilidade com plugins SEO para imagem Open Graph do catalogo.
 *
 * @param string $image_url URL original.
 * @return string
 */
function gstore_catalog_landing_seo_image_url( $image_url = '' ) {
	$image = gstore_is_catalog_landing_page() ? gstore_get_catalog_landing_seo_image() : array();
	return ! empty( $image['url'] ) ? (string) $image['url'] : $image_url;
}
add_filter( 'wpseo_opengraph_image', 'gstore_catalog_landing_seo_image_url', 25 );
add_filter( 'rank_math/opengraph/facebook/image', 'gstore_catalog_landing_seo_image_url', 25 );

/**
 * Compatibilidade com plugins SEO para alt da imagem Open Graph do catalogo.
 *
 * @param string $alt Alt original.
 * @return string
 */
function gstore_catalog_landing_seo_image_alt( $alt = '' ) {
	return gstore_is_catalog_landing_page() ? gstore_get_catalog_landing_image_alt() : $alt;
}
add_filter( 'wpseo_opengraph_image_alt', 'gstore_catalog_landing_seo_image_alt', 25 );

/**
 * Imprime metadados de imagem para a pagina-mae do catalogo.
 */
function gstore_print_catalog_landing_seo_image_meta() {
	if ( ! gstore_is_catalog_landing_page() || gstore_has_catalog_non_pagination_operational_query() ) {
		return;
	}

	$image = gstore_get_catalog_landing_seo_image();
	if ( empty( $image['url'] ) ) {
		return;
	}

	$url    = (string) $image['url'];
	$alt    = ! empty( $image['alt'] ) ? (string) $image['alt'] : '';
	$type   = ! empty( $image['type'] ) ? (string) $image['type'] : '';
	$width  = ! empty( $image['width'] ) ? absint( $image['width'] ) : 0;
	$height = ! empty( $image['height'] ) ? absint( $image['height'] ) : 0;

	echo '<link rel="image_src" href="' . esc_url( $url ) . '" />' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $url ) . '" />' . "\n";
	echo '<meta property="og:image:secure_url" content="' . esc_url( $url ) . '" />' . "\n";
	if ( $type ) {
		echo '<meta property="og:image:type" content="' . esc_attr( $type ) . '" />' . "\n";
	}
	if ( $width > 0 ) {
		echo '<meta property="og:image:width" content="' . esc_attr( (string) $width ) . '" />' . "\n";
	}
	if ( $height > 0 ) {
		echo '<meta property="og:image:height" content="' . esc_attr( (string) $height ) . '" />' . "\n";
	}
	if ( $alt ) {
		echo '<meta property="og:image:alt" content="' . esc_attr( $alt ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'gstore_print_catalog_landing_seo_image_meta', 5 );

/**
 * Monta um ItemList simples com produtos visiveis na pagina do catalogo.
 *
 * @param string $page_url URL canonica da pagina.
 * @return array<string,mixed>
 */
function gstore_get_catalog_landing_item_list_schema( $page_url ) {
	$page     = gstore_get_catalog_product_page_request();
	$per_page = 15;
	$offset   = max( 0, ( $page - 1 ) * $per_page );
	$products = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => $per_page,
			'offset'                 => $offset,
			'orderby'                => 'meta_value_num',
			'meta_key'               => 'total_sales',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$items = array();
	foreach ( array_map( 'absint', (array) $products ) as $index => $product_id ) {
		$permalink = get_permalink( $product_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			continue;
		}

		$product = array(
			'@type' => 'Product',
			'@id'   => trailingslashit( $permalink ) . '#product',
			'url'   => esc_url_raw( $permalink ),
			'name'  => get_the_title( $product_id ),
		);

		$image_url = get_the_post_thumbnail_url( $product_id, 'large' );
		if ( is_string( $image_url ) && '' !== $image_url ) {
			$product['image'] = esc_url_raw( $image_url );
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $offset + $index + 1,
			'item'     => $product,
		);
	}

	if ( empty( $items ) ) {
		return array();
	}

	return array(
		'@type'           => 'ItemList',
		'@id'             => trailingslashit( $page_url ) . '#itemlist',
		'itemListOrder'   => 'https://schema.org/ItemListOrderDescending',
		'numberOfItems'   => count( $items ),
		'itemListElement' => $items,
	);
}

/**
 * Imprime CollectionPage, BreadcrumbList e ItemList da pagina-mae do catalogo.
 */
function gstore_print_catalog_landing_schema() {
	if ( ! gstore_is_catalog_landing_page() || gstore_has_catalog_non_pagination_operational_query() ) {
		return;
	}

	$page_url = gstore_get_catalog_landing_canonical_url( gstore_get_catalog_url() );
	$site_url = home_url( '/' );
	$site_id  = trailingslashit( $site_url ) . '#website';
	$page_id  = trailingslashit( $page_url ) . '#webpage';
	$image    = gstore_get_catalog_landing_seo_image();

	$collection_page = array(
		'@type'        => 'CollectionPage',
		'@id'          => $page_id,
		'url'          => $page_url,
		'name'         => gstore_get_catalog_landing_seo_title(),
		'headline'     => __( 'Catálogo de Produtos', 'gstore' ),
		'description'  => gstore_get_catalog_landing_meta_description(),
		'isPartOf'     => array(
			'@id' => $site_id,
		),
		'inLanguage'   => (string) get_bloginfo( 'language' ),
		'breadcrumb'   => array(
			'@id' => trailingslashit( $page_url ) . '#breadcrumb',
		),
	);

	if ( ! empty( $image['url'] ) ) {
		$collection_page['primaryImageOfPage'] = array(
			'@type'   => 'ImageObject',
			'url'     => (string) $image['url'],
			'caption' => ! empty( $image['alt'] ) ? (string) $image['alt'] : '',
		);
	}

	$item_list = gstore_get_catalog_landing_item_list_schema( $page_url );
	if ( ! empty( $item_list ) ) {
		$collection_page['mainEntity'] = array(
			'@id' => $item_list['@id'],
		);
	}

	$graph = array(
		array(
			'@type' => 'WebSite',
			'@id'   => $site_id,
			'url'   => $site_url,
			'name'  => gstore_get_store_display_name_for_seo(),
		),
		$collection_page,
		array(
			'@type'           => 'BreadcrumbList',
			'@id'             => trailingslashit( $page_url ) . '#breadcrumb',
			'itemListElement' => array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => __( 'Início', 'gstore' ),
					'item'     => $site_url,
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => __( 'Catálogo', 'gstore' ),
					'item'     => $page_url,
				),
			),
		),
	);

	if ( ! empty( $item_list ) ) {
		$graph[] = $item_list;
	}

	echo '<script type="application/ld+json" id="gstore-catalog-landing-schema">'
		. wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		. '</script>' . "\n";
}
add_action( 'wp_head', 'gstore_print_catalog_landing_schema', 30 );

/**
 * Resolve um termo product_cat por slug e retorna seu link nativo.
 *
 * @param string $slug Slug do termo.
 * @return string
 */
function gstore_get_product_category_native_link_by_slug( $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug || ! taxonomy_exists( 'product_cat' ) ) {
		return '';
	}

	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	$link = get_term_link( $term, 'product_cat' );
	if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
		return '';
	}

	return $link;
}

/**
 * Resolve links internos antigos de categoria para URLs publicas atuais.
 *
 * @param string $legacy_url URL encontrada no HTML.
 * @return string URL corrigida ou string vazia.
 */
function gstore_resolve_legacy_category_public_url( $legacy_url ) {
	$path = (string) wp_parse_url( (string) $legacy_url, PHP_URL_PATH );
	if ( '' === $path || false === strpos( $path, '/categoria-produto/' ) ) {
		return '';
	}

	$legacy_path = trim( (string) preg_replace( '#^.*?/categoria-produto/#', '', $path ), '/' );
	if ( '' === $legacy_path ) {
		return '';
	}

	$segments = array_values( array_filter( explode( '/', $legacy_path ) ) );
	if ( count( $segments ) >= 3 && 'page' === strtolower( (string) $segments[ count( $segments ) - 2 ] ) && ctype_digit( (string) end( $segments ) ) ) {
		$segments = array_slice( $segments, 0, -2 );
	}
	$legacy_path = implode( '/', array_map( 'sanitize_title', $segments ) );
	$leaf        = ! empty( $segments ) ? sanitize_title( (string) end( $segments ) ) : '';

	if ( '' !== $leaf ) {
		$native_category_link = gstore_get_product_category_native_link_by_slug( $leaf );
		if ( '' !== $native_category_link ) {
			return $native_category_link;
		}
	}

	$clean_pages = array(
		'promocoes'     => '/ofertas/',
		'ofertas'       => '/ofertas/',
		'programas'     => '/programas/',
		'pro-training'  => '/pro-training/',
		'clube-de-tiro' => '/clube-de-tiro/',
	);
	if ( isset( $clean_pages[ $legacy_path ] ) ) {
		return home_url( $clean_pages[ $legacy_path ] );
	}
	if ( isset( $clean_pages[ $leaf ] ) ) {
		return home_url( $clean_pages[ $leaf ] );
	}

	$catalog_filters = array(
		'tiro-longo' => 'tiro-longo',
		'lancamento' => 'lancamento',
	);
	if ( isset( $catalog_filters[ $legacy_path ] ) ) {
		return add_query_arg( 'filter_cat[]', $catalog_filters[ $legacy_path ], gstore_get_catalog_url() );
	}
	if ( isset( $catalog_filters[ $leaf ] ) ) {
		return add_query_arg( 'filter_cat[]', $catalog_filters[ $leaf ], gstore_get_catalog_url() );
	}

	return '';
}

/**
 * Remove links publicados para /categoria-produto/ sem depender de editar blocos salvos.
 *
 * @param string $content HTML renderizado.
 * @return string
 */
function gstore_rewrite_legacy_category_links( $content ) {
	if ( ! is_string( $content ) || false === strpos( $content, 'categoria-produto' ) ) {
		return $content;
	}

	return (string) preg_replace_callback(
		'/(href\s*=\s*)(["\'])([^"\']*\/categoria-produto\/[^"\']*)\2/i',
		static function ( $matches ) {
			$target = gstore_resolve_legacy_category_public_url( html_entity_decode( (string) $matches[3], ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $target ) {
				return $matches[0];
			}

			return $matches[1] . $matches[2] . esc_url( $target ) . $matches[2];
		},
		$content
	);
}
add_filter( 'render_block', 'gstore_rewrite_legacy_category_links', 20 );
add_filter( 'the_content', 'gstore_rewrite_legacy_category_links', 20 );

/**
 * Escolhe o sitemap publico anunciado no robots.txt.
 *
 * @return string URL absoluta do sitemap.
 */
function gstore_get_public_sitemap_url() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		$sitemap_url = home_url( '/sitemap_index.xml' );
	} elseif (
		defined( 'RANK_MATH_VERSION' )
		|| function_exists( 'rank_math' )
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' )
		|| function_exists( 'the_seo_framework' )
		|| defined( 'AIOSEO_VERSION' )
	) {
		$sitemap_url = home_url( '/sitemap.xml' );
	} else {
		$sitemap_url = home_url( '/sitemap.xml' );
	}

	return apply_filters( 'gstore_public_sitemap_url', $sitemap_url );
}

/**
 * Mantem apenas um Sitemap: valido no robots.txt.
 *
 * @param string $output Conteudo original.
 * @param bool   $public Se o site esta publico.
 * @return string
 */
function gstore_filter_robots_sitemap( $output, $public ) {
	$output = preg_replace( '/^\s*Sitemap:\s*.*(?:\r?\n)?/mi', '', (string) $output );
	$output = preg_replace(
		array(
			'/^\s*Disallow:\s*\/\*\?add-to-cart=.*(?:\r?\n)?/mi',
			'/^\s*Disallow:\s*\/\*\?orderby=.*(?:\r?\n)?/mi',
			'/^\s*Disallow:\s*\/\*\?filter_.*(?:\r?\n)?/mi',
			'/^\s*Disallow:\s*\/\*\?rating_filter=.*(?:\r?\n)?/mi',
			'/^\s*Disallow:\s*\/\*\?min_price=.*(?:\r?\n)?/mi',
			'/^\s*Disallow:\s*\/\*\?max_price=.*(?:\r?\n)?/mi',
			'/^\s*Disallow:\s*\/\*\?s=.*(?:\r?\n)?/mi',
			'/^\s*Disallow:\s*\/\*\?post_type=product.*(?:\r?\n)?/mi',
		),
		'',
		$output
	);

	if ( ! $public ) {
		return rtrim( (string) $output ) . "\n";
	}

	$sitemap_url = esc_url_raw( gstore_get_public_sitemap_url() );
	if ( '' === $sitemap_url ) {
		return $output;
	}

	$output = rtrim( (string) $output );

	return $output . "\n\nSitemap: " . $sitemap_url . "\n";
}
add_filter( 'robots_txt', 'gstore_filter_robots_sitemap', 20, 2 );

/**
 * Taxonomias de produto que podem ter archives comerciais indexaveis.
 *
 * @return string[]
 */
function gstore_get_public_product_taxonomies() {
	$taxonomies = array( 'product_cat', 'product_tag' );

	if ( function_exists( 'gstore_get_footer_brand_taxonomies' ) ) {
		$taxonomies = array_merge( $taxonomies, gstore_get_footer_brand_taxonomies() );
	}

	return array_values( array_unique( array_filter( $taxonomies, 'taxonomy_exists' ) ) );
}

/**
 * Indica se o termo de produto deve ser indexavel.
 *
 * @param mixed $term Termo consultado.
 * @return bool
 */
function gstore_is_indexable_product_term( $term ) {
	if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, gstore_get_public_product_taxonomies(), true ) ) {
		return true;
	}

	$blocked_slugs = apply_filters(
		'gstore_noindex_product_term_slugs',
		array( 'sem-categoria', 'uncategorized', 'diversos', 'diversas' )
	);
	$blocked_slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) $blocked_slugs ) ) ) );

	if ( in_array( sanitize_title( (string) $term->slug ), $blocked_slugs, true ) ) {
		return false;
	}

	if ( isset( $term->count ) && (int) $term->count < 1 ) {
		return false;
	}

	return true;
}

/**
 * IDs de termos de produto que nao devem entrar no sitemap nativo.
 *
 * @param string $taxonomy Taxonomia.
 * @return int[]
 */
function gstore_get_noindex_product_term_ids_for_sitemap( $taxonomy ) {
	$taxonomy = sanitize_key( (string) $taxonomy );
	if ( '' === $taxonomy || ! in_array( $taxonomy, gstore_get_public_product_taxonomies(), true ) ) {
		return array();
	}

	static $ids_by_taxonomy = array();
	if ( isset( $ids_by_taxonomy[ $taxonomy ] ) ) {
		return $ids_by_taxonomy[ $taxonomy ];
	}

	$blocked_slugs = apply_filters(
		'gstore_noindex_product_term_slugs',
		array( 'sem-categoria', 'uncategorized', 'diversos', 'diversas' )
	);
	$blocked_slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) $blocked_slugs ) ) ) );
	if ( empty( $blocked_slugs ) ) {
		$ids_by_taxonomy[ $taxonomy ] = array();
		return $ids_by_taxonomy[ $taxonomy ];
	}

	$term_ids = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'slug'       => $blocked_slugs,
			'fields'     => 'ids',
		)
	);

	$ids_by_taxonomy[ $taxonomy ] = is_wp_error( $term_ids )
		? array()
		: array_values( array_unique( array_filter( array_map( 'absint', (array) $term_ids ) ) ) );

	return $ids_by_taxonomy[ $taxonomy ];
}

/**
 * Detecta archive de termo de produto que deve sair do indice.
 *
 * @return bool
 */
function gstore_is_non_indexable_product_term_archive() {
	if ( ! function_exists( 'is_tax' ) ) {
		return false;
	}

	if ( ! is_tax( gstore_get_public_product_taxonomies() ) ) {
		return false;
	}

	return ! gstore_is_indexable_product_term( get_queried_object() );
}

/**
 * Query vars que tornam catalogo/archives uma pagina operacional, nao uma landing indexavel.
 *
 * @param string $key Query var.
 * @return bool
 */
function gstore_is_catalog_operational_query_key( $key ) {
	$key = sanitize_key( (string) $key );
	if ( '' === $key ) {
		return false;
	}

	$exact_keys = array(
		'filter_cat',
		'filter',
		'orderby',
		'min_price',
		'max_price',
		'rating_filter',
		'stock_status',
		'price',
		'q',
		's',
		'paged',
		'product-page',
	);

	if ( in_array( $key, $exact_keys, true ) ) {
		return true;
	}

	return 0 === strpos( $key, 'filter_' ) || 0 === strpos( $key, 'attribute_' ) || 0 === strpos( $key, 'pa_' );
}

/**
 * Query vars que representam apenas paginacao rastreavel do catalogo.
 *
 * @param string $key Query var.
 * @return bool
 */
function gstore_is_catalog_pagination_query_key( $key ) {
	return in_array( sanitize_key( (string) $key ), array( 'product-page', 'paged', 'page' ), true );
}

/**
 * Indica se a requisicao atual tem filtros, ordenacao ou busca alem de paginacao.
 *
 * @return bool
 */
function gstore_has_catalog_non_pagination_operational_query() {
	foreach ( array_keys( (array) $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( gstore_is_catalog_operational_query_key( $key ) && ! gstore_is_catalog_pagination_query_key( $key ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Indica se a URL atual e uma pagina de catalogo ou filtro que nao deve indexar.
 *
 * @return bool
 */
function gstore_should_noindex_catalog_request() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}

	if ( function_exists( 'is_page' ) && is_page( 'favoritos' ) ) {
		return true;
	}

	if ( gstore_is_non_indexable_product_term_archive() ) {
		return true;
	}

	if ( ! gstore_has_catalog_non_pagination_operational_query() ) {
		return false;
	}

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return true;
	}

	if ( function_exists( 'is_page' ) && is_page( array( 'catalogo', 'ofertas' ) ) ) {
		return true;
	}

	if ( function_exists( 'is_tax' ) && is_tax( gstore_get_public_product_taxonomies() ) ) {
		return true;
	}

	return function_exists( 'gstore_is_generated_category_catalog_page' ) && gstore_is_generated_category_catalog_page();
}

/**
 * Adiciona noindex,follow em filtros, buscas de catalogo e paginas pessoais vazias.
 *
 * @param array<string, bool|string> $robots Regras atuais.
 * @return array<string, bool|string>
 */
function gstore_catalog_robots_directives( $robots ) {
	if ( ! gstore_should_noindex_catalog_request() ) {
		return $robots;
	}

	unset( $robots['index'], $robots['nofollow'] );
	$robots['noindex'] = true;
	$robots['follow']  = true;

	return $robots;
}
add_filter( 'wp_robots', 'gstore_catalog_robots_directives', 20 );

/**
 * Compatibilidade com plugins SEO que substituem a meta robots nativa.
 *
 * @param string $robots Regras em string.
 * @return string
 */
function gstore_catalog_wpseo_robots_directives( $robots ) {
	if ( ! gstore_should_noindex_catalog_request() ) {
		return $robots;
	}

	$parts = array_filter( array_map( 'trim', explode( ',', (string) $robots ) ) );
	$parts = array_filter(
		$parts,
		static function ( $part ) {
			$part = strtolower( (string) $part );
			return ! in_array( $part, array( 'index', 'nofollow' ), true );
		}
	);
	$parts[] = 'noindex';
	$parts[] = 'follow';

	return implode( ', ', array_values( array_unique( $parts ) ) );
}
add_filter( 'wpseo_robots', 'gstore_catalog_wpseo_robots_directives', 20 );

/**
 * Compatibilidade com Rank Math/AIOSEO para filtros de catalogo.
 *
 * @param array $robots Regras.
 * @return array
 */
function gstore_catalog_array_robots_directives( $robots ) {
	if ( ! gstore_should_noindex_catalog_request() ) {
		return $robots;
	}

	$robots = is_array( $robots ) ? $robots : array();
	unset( $robots['index'], $robots['nofollow'] );
	$robots['noindex'] = true;
	$robots['follow']  = true;

	return $robots;
}
add_filter( 'rank_math/frontend/robots', 'gstore_catalog_array_robots_directives', 20 );
add_filter( 'aioseo_robots_meta', 'gstore_catalog_array_robots_directives', 20 );

/**
 * Mantem sitemaps nativos livres de categorias vazias.
 *
 * @param array  $args     Args de get_terms().
 * @param string $taxonomy Taxonomia.
 * @return array
 */
function gstore_filter_product_taxonomy_sitemap_args( $args, $taxonomy ) {
	if ( in_array( $taxonomy, gstore_get_public_product_taxonomies(), true ) ) {
		$args['hide_empty'] = true;
		$args['exclude']    = array_values(
			array_unique(
				array_merge(
					array_map( 'absint', (array) ( $args['exclude'] ?? array() ) ),
					gstore_get_noindex_product_term_ids_for_sitemap( $taxonomy )
				)
			)
		);
	}

	return $args;
}
add_filter( 'wp_sitemaps_taxonomies_query_args', 'gstore_filter_product_taxonomy_sitemap_args', 20, 2 );

/**
 * Remove termos noindex dos sitemaps de plugins SEO que expõem entrada por objeto.
 *
 * @param mixed  $url    Entrada original do sitemap.
 * @param string $type   Tipo de objeto.
 * @param mixed  $object Objeto do sitemap.
 * @return mixed
 */
function gstore_filter_product_term_sitemap_entry( $url, $type, $object ) {
	if ( $object instanceof WP_Term && ! gstore_is_indexable_product_term( $object ) ) {
		return false;
	}

	return $url;
}
add_filter( 'wpseo_sitemap_entry', 'gstore_filter_product_term_sitemap_entry', 20, 3 );
add_filter( 'rank_math/sitemap/entry', 'gstore_filter_product_term_sitemap_entry', 20, 3 );

/**
 * Fallback para ambientes em que o plugin SEO nao fornece filtro publico.
 */
function gstore_catalog_print_noindex_fallback() {
	if ( function_exists( 'wp_robots' ) || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
		return;
	}

	if ( ! gstore_should_noindex_catalog_request() ) {
		return;
	}

	echo '<meta name="robots" content="noindex,follow" />' . "\n";
}
add_action( 'wp_head', 'gstore_catalog_print_noindex_fallback', 1 );

/**
 * Canonical limpo para URLs filtradas do catalogo.
 *
 * @param string  $canonical_url URL canonica original.
 * @param WP_Post $post          Post consultado.
 * @return string
 */
function gstore_catalog_query_canonical_url( $canonical_url, $post ) {
	if ( ! gstore_should_noindex_catalog_request() || ( function_exists( 'is_page' ) && is_page( 'favoritos' ) ) ) {
		return $canonical_url;
	}

	return gstore_get_catalog_query_canonical_url( $canonical_url, $post );
}
add_filter( 'get_canonical_url', 'gstore_catalog_query_canonical_url', 20, 2 );

/**
 * Retorna canonical limpo para URLs filtradas do catalogo.
 *
 * @param string       $canonical_url URL atual.
 * @param WP_Post|null $post          Post atual.
 * @return string
 */
function gstore_get_catalog_query_canonical_url( $canonical_url, $post = null ) {
	$queried_object = get_queried_object();
	if ( $queried_object instanceof WP_Term && in_array( $queried_object->taxonomy, gstore_get_public_product_taxonomies(), true ) ) {
		$term_link = get_term_link( $queried_object );
		if ( ! is_wp_error( $term_link ) && is_string( $term_link ) && '' !== $term_link ) {
			return $term_link;
		}
	}

	if ( $post instanceof WP_Post ) {
		$permalink = get_permalink( $post->ID );
		if ( $permalink ) {
			return $permalink;
		}
	}

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return gstore_get_catalog_url();
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = $request_uri ? (string) wp_parse_url( $request_uri, PHP_URL_PATH ) : '';

	return $path ? home_url( $path ) : $canonical_url;
}

/**
 * Retorna canonical proprio da landing do catalogo, preservando paginas paginadas.
 *
 * @param string $canonical_url Canonical original.
 * @return string
 */
function gstore_get_catalog_landing_canonical_url( $canonical_url = '' ) {
	if ( ! gstore_is_catalog_landing_page() ) {
		return $canonical_url;
	}

	if ( gstore_has_catalog_non_pagination_operational_query() ) {
		return gstore_get_catalog_url();
	}

	$page = gstore_get_catalog_product_page_request();
	if ( $page > 1 ) {
		return add_query_arg( 'product-page', $page, gstore_get_catalog_url() );
	}

	return gstore_get_catalog_url();
}

/**
 * Canonical da landing /catalogo/ com paginação indexavel.
 *
 * @param string       $canonical_url URL canonica original.
 * @param WP_Post|null $post          Post atual.
 * @return string
 */
function gstore_catalog_landing_canonical_url( $canonical_url, $post = null ) {
	return gstore_get_catalog_landing_canonical_url( $canonical_url );
}
add_filter( 'get_canonical_url', 'gstore_catalog_landing_canonical_url', 21, 2 );

/**
 * Compatibilidade com canonical de plugins SEO.
 *
 * @param string $canonical_url URL canonica.
 * @return string
 */
function gstore_catalog_query_canonical_url_for_seo_plugins( $canonical_url ) {
	if ( ! gstore_should_noindex_catalog_request() || ( function_exists( 'is_page' ) && is_page( 'favoritos' ) ) ) {
		return $canonical_url;
	}

	$post = get_queried_object();
	return gstore_get_catalog_query_canonical_url( $canonical_url, $post instanceof WP_Post ? $post : null );
}
add_filter( 'wpseo_canonical', 'gstore_catalog_query_canonical_url_for_seo_plugins', 20 );
add_filter( 'rank_math/frontend/canonical', 'gstore_catalog_query_canonical_url_for_seo_plugins', 20 );

/**
 * Compatibilidade com plugins SEO para canonical da landing /catalogo/.
 *
 * @param string $canonical_url Canonical original.
 * @return string
 */
function gstore_catalog_landing_canonical_url_for_seo_plugins( $canonical_url ) {
	return gstore_get_catalog_landing_canonical_url( $canonical_url );
}
add_filter( 'wpseo_canonical', 'gstore_catalog_landing_canonical_url_for_seo_plugins', 21 );
add_filter( 'rank_math/frontend/canonical', 'gstore_catalog_landing_canonical_url_for_seo_plugins', 21 );
add_filter( 'aioseo_canonical_url', 'gstore_catalog_landing_canonical_url_for_seo_plugins', 21 );

/**
 * Retorna a URL canonica para archives publicos de taxonomias de produto.
 *
 * @return string
 */
function gstore_get_product_taxonomy_canonical_url() {
	if ( ! function_exists( 'is_tax' ) || ! is_tax( gstore_get_public_product_taxonomies() ) ) {
		return '';
	}

	$queried_object = get_queried_object();
	if ( ! $queried_object instanceof WP_Term || ! in_array( $queried_object->taxonomy, gstore_get_public_product_taxonomies(), true ) ) {
		return '';
	}

	$term_link = get_term_link( $queried_object, $queried_object->taxonomy );
	if ( is_wp_error( $term_link ) || ! is_string( $term_link ) || '' === $term_link ) {
		return '';
	}

	$paged = max( absint( get_query_var( 'paged' ) ), absint( get_query_var( 'page' ) ) );
	if ( $paged > 1 && gstore_should_use_clean_catalog_pagination_urls() ) {
		return user_trailingslashit( trailingslashit( $term_link ) . 'page/' . $paged, 'paged' );
	}

	return $term_link;
}

/**
 * Canonical proprio para categorias/subcategorias/marcas/tag de produto.
 *
 * @param string $canonical_url URL canonica original.
 * @return string
 */
function gstore_product_taxonomy_canonical_url_for_seo_plugins( $canonical_url ) {
	$product_taxonomy_canonical = gstore_get_product_taxonomy_canonical_url();

	return '' !== $product_taxonomy_canonical ? $product_taxonomy_canonical : $canonical_url;
}
add_filter( 'wpseo_canonical', 'gstore_product_taxonomy_canonical_url_for_seo_plugins', 19 );
add_filter( 'rank_math/frontend/canonical', 'gstore_product_taxonomy_canonical_url_for_seo_plugins', 19 );
add_filter( 'aioseo_canonical_url', 'gstore_product_taxonomy_canonical_url_for_seo_plugins', 19 );

/**
 * Retorna a pagina de produtos solicitada via query param controlado.
 *
 * @return int
 */
function gstore_get_catalog_product_page_request() {
	$page = isset( $_GET['product-page'] ) ? absint( wp_unslash( $_GET['product-page'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	return max( 1, $page );
}

/**
 * Indica se a pagina atual pode usar URLs limpas /page/N/ para paginacao.
 *
 * @return bool
 */
function gstore_should_use_clean_catalog_pagination_urls() {
	if ( ! function_exists( 'is_shop' ) || ( ! is_shop() && ( ! function_exists( 'is_product_taxonomy' ) || ! is_product_taxonomy() ) ) ) {
		return false;
	}

	foreach ( array_keys( (array) $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = sanitize_key( (string) $key );
		if ( '' === $key ) {
			continue;
		}

		return false;
	}

	return true;
}

/**
 * Faz archives do catalogo aceitarem ?product-page=N como pagina da query principal.
 *
 * O WooCommerce/WordPress estava gerando links de paginacao quebrados em archives
 * renderizados pelo legacy-template do block theme. Usamos um parametro proprio para
 * manter filtros como URLs operacionais noindex e evitar a rota /page/N sequestrada
 * por redirects legados.
 *
 * @param WP_Query $query Query atual.
 */
function gstore_apply_catalog_product_page_to_main_query( $query ) {
	if ( is_admin() || ! ( $query instanceof WP_Query ) || ! $query->is_main_query() ) {
		return;
	}

	$page = gstore_get_catalog_product_page_request();
	if ( $page <= 1 ) {
		return;
	}

	$is_product_archive = false;
	if ( method_exists( $query, 'is_post_type_archive' ) && $query->is_post_type_archive( 'product' ) ) {
		$is_product_archive = true;
	}
	if ( method_exists( $query, 'is_tax' ) && $query->is_tax( gstore_get_public_product_taxonomies() ) ) {
		$is_product_archive = true;
	}

	if ( ! $is_product_archive ) {
		return;
	}

	$query->set( 'paged', $page );
}
add_action( 'pre_get_posts', 'gstore_apply_catalog_product_page_to_main_query', 9 );

/**
 * Monta a URL base da paginacao do catalogo preservando filtros operacionais.
 *
 * @return string
 */
function gstore_get_catalog_pagination_base_url() {
	$base_url = '';
	$queried  = get_queried_object();

	if ( $queried instanceof WP_Term && in_array( $queried->taxonomy, gstore_get_public_product_taxonomies(), true ) ) {
		$link = get_term_link( $queried, $queried->taxonomy );
		if ( ! is_wp_error( $link ) && is_string( $link ) && '' !== $link ) {
			$base_url = $link;
		}
	}

	if ( '' === $base_url && function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_permalink' ) ) {
		$shop_url = wc_get_page_permalink( 'shop' );
		if ( is_string( $shop_url ) && '' !== $shop_url && ! is_wp_error( $shop_url ) ) {
			$base_url = $shop_url;
		}
	}

	if ( '' === $base_url && $queried instanceof WP_Post ) {
		$permalink = get_permalink( $queried->ID );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			$base_url = $permalink;
		}
	}

	if ( '' === $base_url ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = $request_uri ? (string) wp_parse_url( $request_uri, PHP_URL_PATH ) : '';
		$base_url    = $path ? home_url( $path ) : home_url( '/' );
	}

	$args = array();
	foreach ( (array) $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = sanitize_key( (string) $key );
		if ( '' === $key || in_array( $key, array( 'product-page', 'paged', 'page', 'add-to-cart' ), true ) ) {
			continue;
		}

		if ( is_array( $value ) ) {
			$args[ $key ] = array_map(
				static function ( $item ) {
					return sanitize_text_field( wp_unslash( $item ) );
				},
				$value
			);
			continue;
		}

		$args[ $key ] = sanitize_text_field( wp_unslash( $value ) );
	}

	return empty( $args ) ? $base_url : add_query_arg( $args, $base_url );
}

/**
 * Converte URLs internas de paginacao em caminhos relativos para evitar reescrita por buffers de hospedagem.
 *
 * @param string $url URL absoluta ou relativa.
 * @return string
 */
function gstore_get_catalog_pagination_href( $url ) {
	$url  = (string) $url;
	$path = wp_parse_url( $url, PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		return $url;
	}

	$query    = wp_parse_url( $url, PHP_URL_QUERY );
	$fragment = wp_parse_url( $url, PHP_URL_FRAGMENT );
	$href     = $path;
	if ( is_string( $query ) && '' !== $query ) {
		$href .= '?' . $query;
	}
	if ( is_string( $fragment ) && '' !== $fragment ) {
		$href .= '#' . $fragment;
	}

	return $href;
}

/**
 * Escapa hrefs de paginacao evitando que buffers finais reescrevam /page/N/.
 *
 * @param string $url URL absoluta ou relativa.
 * @return string
 */
function gstore_escape_catalog_pagination_href( $url ) {
	return str_replace( '/', '&#47;', esc_url( gstore_get_catalog_pagination_href( $url ) ) );
}

/**
 * Paginacao propria para archives do catalogo.
 */
function gstore_catalog_pagination() {
	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return;
	}

	if ( ! function_exists( 'wc_get_loop_prop' ) || ! wc_get_loop_prop( 'is_paginated' ) || ! function_exists( 'woocommerce_products_will_display' ) || ! woocommerce_products_will_display() ) {
		return;
	}

	$total = (int) wc_get_loop_prop( 'total_pages' );
	if ( $total <= 1 ) {
		return;
	}

	$current = max(
		1,
		gstore_get_catalog_product_page_request(),
		absint( wc_get_loop_prop( 'current_page' ) ),
		absint( get_query_var( 'paged' ) )
	);

	$base_url    = gstore_get_catalog_pagination_base_url();
	$placeholder = 'GSTORE_PAGE_NUMBER';
	$raw_base    = gstore_should_use_clean_catalog_pagination_urls()
		? user_trailingslashit( trailingslashit( $base_url ) . 'page/' . $placeholder, 'paged' )
		: add_query_arg( 'product-page', $placeholder, $base_url );

	$page_url = static function ( $page ) use ( $base_url, $raw_base, $placeholder ) {
		$page = absint( $page );
		if ( $page <= 1 ) {
			return $base_url;
		}

		return str_replace( $placeholder, (string) $page, $raw_base );
	};

	$pages    = array();
	$end_size = 2;
	$mid_size = 2;
	for ( $page = 1; $page <= $total; $page++ ) {
		if ( $page <= $end_size || $page > $total - $end_size || abs( $page - $current ) <= $mid_size ) {
			$pages[] = $page;
		}
	}

	$link_items = array();
	if ( $current > 1 ) {
		$link_items[] = '<li><a class="prev page-numbers" rel="prev" href="' . gstore_escape_catalog_pagination_href( $page_url( $current - 1 ) ) . '">' . ( is_rtl() ? '&rarr;' : '&larr;' ) . '</a></li>';
	}

	$previous_page = 0;
	foreach ( $pages as $page ) {
		if ( $previous_page && $page > $previous_page + 1 ) {
			$link_items[] = '<li><span class="page-numbers dots">&hellip;</span></li>';
		}

		if ( $page === $current ) {
			$link_items[] = '<li><span aria-current="page" class="page-numbers current">' . esc_html( number_format_i18n( $page ) ) . '</span></li>';
		} else {
			$link_items[] = '<li><a class="page-numbers" aria-label="' . esc_attr( sprintf( __( 'Pagina %s', 'gstore' ), number_format_i18n( $page ) ) ) . '" href="' . gstore_escape_catalog_pagination_href( $page_url( $page ) ) . '">' . esc_html( number_format_i18n( $page ) ) . '</a></li>';
		}

		$previous_page = $page;
	}

	if ( $current < $total ) {
		$link_items[] = '<li><a class="next page-numbers" rel="next" href="' . gstore_escape_catalog_pagination_href( $page_url( $current + 1 ) ) . '">' . ( is_rtl() ? '&larr;' : '&rarr;' ) . '</a></li>';
	}

	$links = '<ul class="page-numbers">' . implode( "\n", $link_items ) . '</ul>';

	echo '<nav class="woocommerce-pagination" aria-label="' . esc_attr__( 'Paginacao de produtos', 'gstore' ) . '">';
	echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</nav>';
}

/**
 * Substitui a paginacao padrao do WooCommerce por uma versao estavel no catalogo.
 */
function gstore_replace_woocommerce_catalog_pagination() {
	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return;
	}

	if ( function_exists( 'woocommerce_pagination' ) ) {
		remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
	}

	add_action( 'woocommerce_after_shop_loop', 'gstore_catalog_pagination', 10 );
}
add_action( 'wp', 'gstore_replace_woocommerce_catalog_pagination', 20 );

/**
 * Fallback de canonical quando nao ha plugin SEO imprimindo em taxonomias.
 */
function gstore_print_product_taxonomy_canonical_fallback() {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
		return;
	}

	$product_taxonomy_canonical = gstore_get_product_taxonomy_canonical_url();
	if ( '' === $product_taxonomy_canonical ) {
		return;
	}

	echo '<link rel="canonical" href="' . esc_url( $product_taxonomy_canonical ) . '" />' . "\n";
}
add_action( 'wp_head', 'gstore_print_product_taxonomy_canonical_fallback', 2 );

/**
 * Obtém a cor de accent efetiva (salva no admin ou fallback do tema).
 *
 * @return string Cor hex.
 */
function gstore_get_effective_accent_color() {
	$saved_color = sanitize_hex_color( (string) get_option( 'gstore_accent_color', '' ) );
	$token_color = gstore_get_accent_color_from_design_token_overrides();
	$store_info_color = gstore_get_store_info_accent_color();

	if ( $token_color && ! gstore_is_default_accent_color( $token_color ) ) {
		return $token_color;
	}

	if ( $saved_color && ! gstore_is_default_accent_color( $saved_color ) ) {
		return $saved_color;
	}

	if ( $store_info_color && ! gstore_is_default_accent_color( $store_info_color ) ) {
		return $store_info_color;
	}

	if ( $token_color ) {
		return $token_color;
	}

	if ( $saved_color ) {
		return $saved_color;
	}

	if ( $store_info_color ) {
		return $store_info_color;
	}

	return gstore_get_default_accent_color();
}

/**
 * Garante que a cor de accent esteja persistida no banco antes de confiar nos arquivos.
 *
 * @return string Cor hex persistida ou fallback seguro.
 */
function gstore_ensure_persisted_accent_color() {
	$saved_color = sanitize_hex_color( (string) get_option( 'gstore_accent_color', '' ) );
	$token_color = gstore_get_accent_color_from_design_token_overrides();
	$store_info_color = gstore_get_store_info_accent_color();

	if ( $token_color && ! gstore_is_default_accent_color( $token_color ) ) {
		gstore_persist_accent_design_token_overrides( $token_color );
		return $token_color;
	}

	if ( $saved_color && ! gstore_is_default_accent_color( $saved_color ) ) {
		gstore_persist_accent_design_token_overrides( $saved_color );
		return $saved_color;
	}

	if ( $store_info_color && ! gstore_is_default_accent_color( $store_info_color ) ) {
		gstore_persist_accent_design_token_overrides( $store_info_color );
		return $store_info_color;
	}

	if ( $token_color ) {
		gstore_persist_accent_design_token_overrides( $token_color );
		return $token_color;
	}

	if ( $saved_color ) {
		gstore_persist_accent_design_token_overrides( $saved_color );
		return $saved_color;
	}

	if ( $store_info_color ) {
		gstore_persist_accent_design_token_overrides( $store_info_color );
		return $store_info_color;
	}

	$file_color       = gstore_get_accent_color_from_tokens_file();
	$is_legacy_orange = $file_color && in_array( strtolower( $file_color ), array( '#ff5c00', '#ff5500' ), true );
	$default_color    = gstore_get_default_accent_color();
	$accent_color     = $file_color && ! $is_legacy_orange ? $file_color : $default_color;

	gstore_persist_accent_design_token_overrides( $accent_color );

	return $accent_color;
}

/**
 * Reaplica a cor de accent salva quando a atualização do tema sobrescreve os tokens.
 */
function gstore_maybe_restore_saved_accent_tokens() {
	$saved_color = gstore_ensure_persisted_accent_color();
	if ( ! $saved_color ) {
		return;
	}

	$tokens_file = get_theme_file_path( 'assets/css/tokens.css' );
	if ( ! file_exists( $tokens_file ) || ! is_writable( $tokens_file ) ) {
		return;
	}

	$current_file_color = gstore_get_accent_color_from_tokens_file();
	if ( $current_file_color && strtolower( $current_file_color ) === strtolower( $saved_color ) ) {
		return;
	}

	gstore_update_accent_tokens_in_file( $saved_color );

	$updated_file_color = gstore_get_accent_color_from_tokens_file();
	if ( $updated_file_color && strtolower( $updated_file_color ) === strtolower( $saved_color ) ) {
		update_option( 'gstore_tokens_last_updated', time() );
	}
}
add_action( 'after_setup_theme', 'gstore_maybe_restore_saved_accent_tokens', 99 );
add_action( 'after_switch_theme', 'gstore_maybe_restore_saved_accent_tokens', 20 );

/**
 * Reaplica a cor salva também depois de atualizações feitas pelo atualizador nativo.
 *
 * @param WP_Upgrader $upgrader   Instância do upgrader.
 * @param array       $hook_extra Dados do processo.
 */
function gstore_restore_saved_accent_tokens_after_upgrade( $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['type'] ) || 'theme' !== $hook_extra['type'] ) {
		return;
	}

	gstore_maybe_restore_saved_accent_tokens();
}
add_action( 'upgrader_process_complete', 'gstore_restore_saved_accent_tokens_after_upgrade', 20, 2 );

/**
 * Retorna o ID do produto a partir de um objeto WC_Product.
 *
 * @param WC_Product|int|null $product Objeto do produto ou ID.
 * @return int ID do produto, ou 0 se inválido.
 */
function gstore_get_product_id( $product ) {
	if ( $product instanceof WC_Product ) {
		return (int) $product->get_id();
	}
	if ( is_numeric( $product ) ) {
		return (int) $product;
	}
	return 0;
}

/**
 * Normaliza um contexto de ocultação de preço no tema.
 *
 * @param string $context Contexto bruto.
 * @return string
 */
function gstore_normalize_hidden_price_context( $context ) {
	$context = sanitize_key( (string) $context );

	if ( 'product' === $context ) {
		$context = 'single';
	}

	$allowed = array( 'card', 'search', 'single', 'cart', 'checkout' );

	return in_array( $context, $allowed, true ) ? $context : '';
}

/**
 * Sanitiza a lista de contextos de ocultação do preço.
 *
 * @param mixed $raw_contexts Valor bruto salvo.
 * @return array<int, string>
 */
function gstore_sanitize_hidden_price_contexts( $raw_contexts ) {
	if ( class_exists( '\GStore\Services\Hidden_Price_Service' ) ) {
		return \GStore\Services\Hidden_Price_Service::sanitize_hidden_price_contexts( $raw_contexts );
	}

	$contexts = array();

	if ( is_array( $raw_contexts ) ) {
		$contexts = $raw_contexts;
	} elseif ( is_string( $raw_contexts ) ) {
		$raw_contexts = trim( $raw_contexts );

		if ( '' !== $raw_contexts ) {
			$decoded = json_decode( $raw_contexts, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$contexts = $decoded;
			} else {
				$contexts = preg_split( '/[\s,|]+/', $raw_contexts );
			}
		}
	}

	$sanitized = array();

	foreach ( $contexts as $context ) {
		$normalized = gstore_normalize_hidden_price_context( $context );
		if ( '' === $normalized || in_array( $normalized, $sanitized, true ) ) {
			continue;
		}
		$sanitized[] = $normalized;
	}

	return $sanitized;
}

/**
 * Retorna os contextos configurados para o produto.
 *
 * @param WC_Product|int|null $product Produto, variação ou ID.
 * @return array<int, string>
 */
function gstore_get_product_hidden_price_contexts( $product ) {
	$product_id = gstore_get_product_id( $product );
	if ( $product_id <= 0 ) {
		return array();
	}

	if ( function_exists( 'wc_get_product' ) ) {
		$product_object = $product instanceof WC_Product ? $product : wc_get_product( $product_id );
		if ( $product_object instanceof WC_Product_Variation && method_exists( $product_object, 'get_parent_id' ) ) {
			$parent_id = (int) $product_object->get_parent_id();
			if ( $parent_id > 0 ) {
				$product_id = $parent_id;
			}
		}
	}

	$contexts = gstore_sanitize_hidden_price_contexts( get_post_meta( $product_id, '_gstore_hide_price_contexts', true ) );

	if ( empty( $contexts ) && (bool) get_post_meta( $product_id, '_gstore_hide_price', true ) ) {
		$contexts = array( 'card', 'search', 'single', 'cart', 'checkout' );
	}

	return $contexts;
}

/**
 * Verifica se o produto está com preço oculto via plugin/admin.
 *
 * @param WC_Product|int|null $product Produto, variação ou ID.
 * @param string              $context Contexto desejado ou `auto`.
 * @return bool
 */
function gstore_product_hides_price( $product, $context = 'auto' ) {
	$product_id = gstore_get_product_id( $product );
	if ( $product_id <= 0 ) {
		return false;
	}

	if ( class_exists( '\GStore\Services\Hidden_Price_Service' ) ) {
		return \GStore\Services\Hidden_Price_Service::should_hide_price_on_current_request( $product, $context );
	}

	if ( is_admin() ) {
		return false;
	}

	$contexts = gstore_get_product_hidden_price_contexts( $product );
	if ( empty( $contexts ) ) {
		return false;
	}

	if ( 'auto' === $context || '' === $context || null === $context ) {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$context = 'checkout';
		} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
			$context = 'cart';
		} elseif ( function_exists( 'is_product' ) && is_product() ) {
			$context = 'single';
		} else {
			$context = 'card';
		}
	}

	$context = gstore_normalize_hidden_price_context( $context );

	return '' !== $context && in_array( $context, $contexts, true );
}

/**
 * Verifica se algum item do carrinho está ocultando preço no contexto informado.
 *
 * @param string $context Contexto desejado.
 * @return bool
 */
function gstore_cart_hides_price( $context ) {
	if ( ! function_exists( 'WC' ) ) {
		return false;
	}

	$wc = WC();
	if ( ! $wc || ! isset( $wc->cart ) || ! $wc->cart ) {
		return false;
	}

	$cart = $wc->cart->get_cart();
	if ( ! is_array( $cart ) || empty( $cart ) ) {
		return false;
	}

	foreach ( $cart as $cart_item ) {
		$check_id = isset( $cart_item['variation_id'] ) && (int) $cart_item['variation_id'] > 0
			? (int) $cart_item['variation_id']
			: ( isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0 );

		if ( $check_id > 0 && gstore_product_hides_price( $check_id, $context ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Retorna o HTML da máscara visual do preço oculto.
 *
 * @param string $context Contexto visual: inline, card, single ou block.
 * @return string
 */
function gstore_get_hidden_price_mask_html( $context = 'inline' ) {
	if ( class_exists( '\GStore\Services\Hidden_Price_Service' ) ) {
		return \GStore\Services\Hidden_Price_Service::get_mask_html( $context );
	}

	$allowed_contexts = array( 'inline', 'card', 'single', 'block' );
	$context          = in_array( $context, $allowed_contexts, true ) ? $context : 'inline';
	$classes          = 'gstore-hidden-price gstore-hidden-price--' . $context;
	$aria_label       = __( 'Preço oculto', 'gstore' );
	$eyebrow_text     = 'single' === $context ? __( 'Preço oculto nesta página', 'gstore' ) : __( 'Preço oculto nesta área', 'gstore' );
	$hint_text        = 'single' === $context
		? __( 'O valor continua disponível nas áreas liberadas para este produto.', 'gstore' )
		: __( 'O valor aparece apenas nas áreas liberadas para este produto.', 'gstore' );
	$eyebrow_html     = 'inline' === $context ? '' : '<span class="gstore-hidden-price__eyebrow">' . esc_html( $eyebrow_text ) . '</span>';
	$hint_html        = 'inline' === $context ? '' : '<span class="gstore-hidden-price__hint">' . esc_html( $hint_text ) . '</span>';

	return sprintf(
		'<span class="%1$s" aria-label="%2$s">%3$s<span class="gstore-hidden-price__value" aria-hidden="true">R$ 000.000,00</span>%4$s</span>',
		esc_attr( $classes ),
		esc_attr( $aria_label ),
		$eyebrow_html,
		$hint_html
	);
}

/**
 * Desativa o crop das imagens de produto do WooCommerce.
 * Garante que as miniaturas mantenham a proporção original.
 */

// 1. Forçar configuração de crop no WooCommerce (banco de dados)
function gstore_set_woocommerce_image_settings() {
	// Desativa crop para thumbnails (usa 'uncropped' que significa sem corte)
	if ( get_option( 'woocommerce_thumbnail_cropping' ) !== 'uncropped' ) {
		update_option( 'woocommerce_thumbnail_cropping', 'uncropped' );
	}
}
add_action( 'after_setup_theme', 'gstore_set_woocommerce_image_settings', 20 );

// 2. Filtro para woocommerce_thumbnail (loop de produtos)
function gstore_thumbnail_size( $size ) {
	return array(
		'width'  => 300,
		'height' => 9999,
		'crop'   => false,
	);
}
add_filter( 'woocommerce_get_image_size_thumbnail', 'gstore_thumbnail_size' );

// 3. Filtro para woocommerce_gallery_thumbnail (galeria de produto)
function gstore_gallery_thumbnail_size( $size ) {
	return array(
		'width'  => 100,
		'height' => 9999,
		'crop'   => false,
	);
}
add_filter( 'woocommerce_get_image_size_gallery_thumbnail', 'gstore_gallery_thumbnail_size' );

// 4. Filtro para woocommerce_single (imagem principal do produto)
function gstore_single_size( $size ) {
	return array(
		'width'  => 1000,
		'height' => 9999,
		'crop'   => false,
	);
}
add_filter( 'woocommerce_get_image_size_single', 'gstore_single_size' );

/**
 * Ajusta disponibilidade de variações para estoque.
 *
 * Garante que variações sem estoque não sejam consideradas compráveis e
 * expõe flags extras no JSON de variações para o front-end.
 */
function gstore_variation_is_purchasable( $purchasable, $variation ) {
	if ( ! $variation instanceof WC_Product_Variation ) {
		return $purchasable;
	}

	if ( ! $variation->is_in_stock() ) {
		return false;
	}

	return $purchasable;
}
add_filter( 'woocommerce_variation_is_purchasable', 'gstore_variation_is_purchasable', 10, 2 );

function gstore_available_variation_stock_payload( $data, $product, $variation ) {
	if ( ! $variation instanceof WC_Product_Variation ) {
		return $data;
	}

	// Verificar estoque de forma mais precisa
	$is_in_stock = $variation->is_in_stock();

	// Se o estoque é gerenciado, verificar a quantidade real
	if ( $variation->managing_stock() ) {
		$stock_quantity = $variation->get_stock_quantity();
		// Se quantidade é 0 ou menor, considerar fora de estoque
		if ( $stock_quantity <= 0 ) {
			$is_in_stock = false;
		}
	}

	// Também verificar o status direto do post meta como fallback
	$stock_status = get_post_meta( $variation->get_id(), '_stock_status', true );
	if ( 'outofstock' === $stock_status ) {
		$is_in_stock = false;
	}

	$data['gstore_is_in_stock'] = $is_in_stock;
	$data['is_in_stock'] = $is_in_stock; // Sobrescreve o valor padrão do WooCommerce
	$data['gstore_is_purchasable'] = $is_in_stock && $variation->is_purchasable();
	$data['gstore_stock_text'] = $is_in_stock
		? __( 'Disponível', 'gstore' )
		: __( 'Sem estoque no momento', 'gstore' );

	return $data;
}
add_filter( 'woocommerce_available_variation', 'gstore_available_variation_stock_payload', 10, 3 );

/**
 * Normaliza a chave de um atributo de variação.
 *
 * @param string $attribute Chave bruta.
 * @return string
 */
function gstore_normalize_variation_attribute_key( $attribute ) {
	$attribute = sanitize_key( (string) $attribute );
	if ( '' === $attribute ) {
		return '';
	}

	if ( 0 === strpos( $attribute, 'attribute_' ) ) {
		$attribute = substr( $attribute, strlen( 'attribute_' ) );
		$attribute = sanitize_key( (string) $attribute );
		if ( '' === $attribute ) {
			return '';
		}
	}

	if ( 0 === strpos( $attribute, 'pa_' ) ) {
		$base = preg_replace( '/^(pa_)+/', '', $attribute );
		$base = sanitize_key( (string) $base );
		return '' === $base ? '' : 'pa_' . $base;
	}

	return $attribute;
}

/**
 * Busca a ordem salva no admin para os valores de um atributo de variação.
 *
 * Prioriza o meta `_gstore_variants` e faz fallback para `menu_order` das variações.
 *
 * @param WC_Product $product   Produto pai.
 * @param string     $attribute Atributo do dropdown.
 * @return array
 */
function gstore_get_admin_ordered_variation_values( $product, $attribute ) {
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return array();
	}

	$attribute = gstore_normalize_variation_attribute_key( $attribute );
	if ( '' === $attribute ) {
		return array();
	}

	$ordered_values = array();
	$meta_raw       = (string) get_post_meta( $product->get_id(), '_gstore_variants', true );

	if ( '' !== trim( $meta_raw ) ) {
		$decoded = json_decode( $meta_raw, true );
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $variant ) {
				if ( ! is_array( $variant ) ) {
					continue;
				}

				$attrs = array();
				if ( isset( $variant['attributes'] ) && is_array( $variant['attributes'] ) ) {
					$attrs = $variant['attributes'];
				} elseif ( isset( $variant['attrsByKey'] ) && is_array( $variant['attrsByKey'] ) ) {
					$attrs = $variant['attrsByKey'];
				}

				if ( empty( $attrs ) ) {
					continue;
				}

				foreach ( $attrs as $raw_key => $raw_value ) {
					$key = gstore_normalize_variation_attribute_key( $raw_key );
					if ( $key !== $attribute ) {
						continue;
					}

					$value = sanitize_text_field( (string) $raw_value );
					if ( '' !== $value ) {
						$ordered_values[] = $value;
					}
					break;
				}
			}
		}
	}

	if ( ! empty( $ordered_values ) ) {
		return array_values( array_unique( $ordered_values ) );
	}

	$children = method_exists( $product, 'get_children' ) ? (array) $product->get_children() : array();
	if ( empty( $children ) ) {
		return array();
	}

	usort(
		$children,
		static function( $a, $b ) {
			$a = (int) $a;
			$b = (int) $b;

			$order_a = (int) get_post_field( 'menu_order', $a );
			$order_b = (int) get_post_field( 'menu_order', $b );

			if ( $order_a === $order_b ) {
				return $a <=> $b;
			}

			return $order_a <=> $order_b;
		}
	);

	foreach ( $children as $child_id ) {
		$child_id = (int) $child_id;
		if ( $child_id <= 0 ) {
			continue;
		}

		$value = (string) get_post_meta( $child_id, 'attribute_' . $attribute, true );
		if ( '' === $value && 0 === strpos( $attribute, 'pa_' ) ) {
			$base       = preg_replace( '/^pa_/', '', $attribute );
			$legacy_key = 'pa_pa_' . $base;
			$value      = (string) get_post_meta( $child_id, 'attribute_' . $legacy_key, true );
		}

		$value = sanitize_text_field( $value );
		if ( '' !== $value ) {
			$ordered_values[] = $value;
		}
	}

	return array_values( array_unique( $ordered_values ) );
}

/**
 * Reordena o HTML do dropdown de variações conforme a ordem salva no admin.
 *
 * O WooCommerce ignora a ordem de $args['options'] para atributos de taxonomia:
 * ele itera sobre os termos (ordenados por nome/ID) e apenas verifica pertencimento.
 * Por isso reordenamos o HTML final das <option> tags.
 *
 * @param string $html      HTML gerado pelo WooCommerce.
 * @param array  $args      Argumentos do dropdown.
 * @return string
 */
function gstore_sort_variation_dropdown_html_by_admin_order( $html, $args ) {
	$product   = isset( $args['product'] ) ? $args['product'] : null;
	$attribute = isset( $args['attribute'] ) ? $args['attribute'] : '';

	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) || '' === $attribute ) {
		return $html;
	}

	$attribute = gstore_normalize_variation_attribute_key( $attribute );
	if ( '' === $attribute ) {
		return $html;
	}

	$ordered_values = gstore_get_admin_ordered_variation_values( $product, $attribute );
	if ( empty( $ordered_values ) ) {
		return $html;
	}

	$is_taxonomy = taxonomy_exists( $attribute );
	$normalize   = static function( $value ) use ( $is_taxonomy ) {
		$value = (string) $value;
		return $is_taxonomy ? sanitize_title( $value ) : sanitize_text_field( $value );
	};

	$desired_order = array();
	foreach ( $ordered_values as $value ) {
		$key = $normalize( $value );
		if ( '' !== $key && ! isset( $desired_order[ $key ] ) ) {
			$desired_order[ $key ] = count( $desired_order );
		}
	}

	if ( empty( $desired_order ) ) {
		return $html;
	}

	if ( ! preg_match_all( '/<option\s[^>]*value="([^"]*)"[^>]*>[^<]*<\/option>/i', $html, $matches, PREG_SET_ORDER ) ) {
		return $html;
	}

	$placeholder_options = array();
	$value_options       = array();

	foreach ( $matches as $m ) {
		$full_tag    = $m[0];
		$option_val  = $m[1];

		if ( '' === $option_val ) {
			$placeholder_options[] = $full_tag;
		} else {
			$value_options[] = array(
				'html'  => $full_tag,
				'value' => $option_val,
			);
		}
	}

	usort(
		$value_options,
		static function( $a, $b ) use ( $desired_order, $normalize ) {
			$key_a = $normalize( $a['value'] );
			$key_b = $normalize( $b['value'] );
			$pos_a = isset( $desired_order[ $key_a ] ) ? $desired_order[ $key_a ] : PHP_INT_MAX;
			$pos_b = isset( $desired_order[ $key_b ] ) ? $desired_order[ $key_b ] : PHP_INT_MAX;

			return $pos_a <=> $pos_b;
		}
	);

	$select_open = preg_match( '/^(<select[^>]*>)/i', $html, $sel_match ) ? $sel_match[1] : '<select>';
	$new_html    = $select_open . "\n";

	foreach ( $placeholder_options as $ph ) {
		$new_html .= "\t" . $ph . "\n";
	}
	foreach ( $value_options as $vo ) {
		$new_html .= "\t" . $vo['html'] . "\n";
	}

	$new_html .= '</select>';

	return $new_html;
}
add_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'gstore_sort_variation_dropdown_html_by_admin_order', 10, 2 );

/**
 * SEO: título do documento na página single do produto.
 * Usa meta _gstore_seo_title se preenchido; fallback = nome do produto.
 *
 * @param array $title_parts Partes do título (title, page, tagline).
 * @return array
 */
function gstore_product_document_title_parts( $title_parts ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return $title_parts;
	}
	$product_id = (int) get_queried_object_id();
	if ( $product_id <= 0 ) {
		return $title_parts;
	}
	$seo_title = get_post_meta( $product_id, '_gstore_seo_title', true );
	$title_parts['title'] = is_string( $seo_title ) && trim( $seo_title ) !== ''
		? trim( $seo_title )
		: get_the_title( $product_id );
	return $title_parts;
}
add_filter( 'document_title_parts', 'gstore_product_document_title_parts', 10, 1 );

/**
 * SEO: meta description na página single do produto.
 * Usa meta _gstore_seo_meta_description se preenchido; fallback = resumo (descrição curta).
 */
function gstore_product_meta_description() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product_id = (int) get_queried_object_id();
	if ( $product_id <= 0 ) {
		return;
	}
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
	if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
		return;
	}
	$desc = get_post_meta( $product_id, '_gstore_seo_meta_description', true );
	if ( ! is_string( $desc ) || trim( $desc ) === '' ) {
		$desc = $product->get_short_description();
	}
	$desc = wp_strip_all_tags( $desc );
	$desc = trim( $desc );
	if ( strlen( $desc ) > 160 ) {
		$desc = substr( $desc, 0, 157 ) . '...';
	}
	if ( $desc === '' ) {
		return;
	}
	echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
}
add_action( 'wp_head', 'gstore_product_meta_description', 1 );

/**
 * Verifica se a página atual é a página institucional Sobre nós.
 *
 * @return bool
 */
function gstore_is_sobre_nos_page() {
	return function_exists( 'is_page' ) && is_page( 'sobre-nos' );
}

/**
 * Retorna a descrição SEO da página Sobre nós.
 *
 * @return string
 */
function gstore_get_sobre_nos_seo_description() {
	$store_name = trim( (string) gstore_get_store_name( 'display' ) );
	$city_state = trim( (string) gstore_get_address( 'city_state' ) );
	$location   = '' !== $city_state ? ' em ' . $city_state : '';

	$description = sprintf(
		'Conheça a %s%s: dados da empresa, compra legal de arma, documentação, CRAF, Guia de Tráfego, CAC, prazos e envio.',
		$store_name,
		$location
	);

	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
		if ( mb_strlen( $description, 'UTF-8' ) > 160 ) {
			return rtrim( mb_substr( $description, 0, 157, 'UTF-8' ) ) . '...';
		}
		return $description;
	}

	if ( strlen( $description ) > 160 ) {
		return rtrim( substr( $description, 0, 157 ) ) . '...';
	}

	return $description;
}

/**
 * SEO: título do documento na página Sobre nós.
 *
 * @param array $title_parts Partes do título.
 * @return array
 */
function gstore_sobre_nos_document_title_parts( $title_parts ) {
	if ( ! gstore_is_sobre_nos_page() ) {
		return $title_parts;
	}

	$title_parts['title'] = sprintf(
		'Sobre a %s e compra legal',
		gstore_get_store_name( 'display' )
	);

	return $title_parts;
}
add_filter( 'document_title_parts', 'gstore_sobre_nos_document_title_parts', 10, 1 );

/**
 * SEO: meta description da página Sobre nós.
 */
function gstore_sobre_nos_meta_description() {
	if ( ! gstore_is_sobre_nos_page() ) {
		return;
	}

	echo '<meta name="description" content="' . esc_attr( gstore_get_sobre_nos_seo_description() ) . '" />' . "\n";
}
add_action( 'wp_head', 'gstore_sobre_nos_meta_description', 1 );

/**
 * Perguntas usadas no JSON-LD FAQPage da página Sobre nós.
 *
 * @return array<int, array{question:string, answer:string}>
 */
function gstore_get_sobre_nos_faq_items() {
	$store_name = gstore_get_store_name( 'display' );

	return array(
		array(
			'question' => 'Comprar arma pela internet é legal?',
			'answer'   => 'Sim, desde que a venda seja feita por empresa regularizada e o comprador cumpra todas as exigências legais. A compra de arma de fogo depende de autorização, registro e liberação antes do envio ou retirada.',
		),
		array(
			'question' => 'Quais documentos preciso para comprar uma arma?',
			'answer'   => 'Depende do perfil. Cidadãos normalmente precisam de autorização de aquisição da Polícia Federal e documentos pessoais. CACs seguem exigências vinculadas ao CR, autorização, CRAF e regras do acervo.',
		),
		array(
			'question' => 'Qual é a diferença entre cidadão e CAC?',
			'answer'   => 'O cidadão compra para finalidade permitida em processo próprio. CAC é colecionador, atirador desportivo ou caçador com Certificado de Registro e regras específicas para acervo, aquisição e transporte.',
		),
		array(
			'question' => 'Quando a nota fiscal é emitida?',
			'answer'   => 'A nota fiscal de produto controlado é emitida conforme a etapa documental aplicável, normalmente depois da conferência da autorização ou documentação necessária para o processo.',
		),
		array(
			'question' => 'Quando a arma ou munição é enviada?',
			'answer'   => 'O envio ocorre somente após a liberação documental, emissão dos documentos exigidos e confirmação da modalidade de transporte disponível para o destino.',
		),
		array(
			'question' => 'Posso comprar munição online?',
			'answer'   => 'Pode, desde que apresente a documentação exigida para o calibre e perfil de compra, como documento pessoal, CRAF válido e CR quando aplicável.',
		),
		array(
			'question' => 'Carabina de pressão e airsoft têm o mesmo processo?',
			'answer'   => 'Não. Esses produtos seguem regras próprias e normalmente não passam pelo mesmo processo de arma de fogo, mas podem exigir documento, idade mínima e cuidados de transporte.',
		),
		array(
			'question' => 'A loja ajuda com a documentação?',
			'answer'   => sprintf( 'Sim. A %s orienta sobre os documentos esperados, confere o material recebido e indica os próximos passos, sem substituir a análise do órgão competente.', $store_name ),
		),
	);
}

/**
 * SEO: JSON-LD Organization + FAQPage da página Sobre nós.
 */
function gstore_sobre_nos_json_ld() {
	if ( ! gstore_is_sobre_nos_page() ) {
		return;
	}

	$store_name  = gstore_get_store_name( 'display' );
	$description = gstore_get_sobre_nos_seo_description();
	$logo_id     = (int) get_theme_mod( 'custom_logo' );
	$logo_url    = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

	if ( ! $logo_url && function_exists( 'get_site_icon_url' ) ) {
		$logo_url = get_site_icon_url( 512 );
	}

	$organization = array(
		'@type'       => 'Organization',
		'@id'         => home_url( '/#organization' ),
		'name'        => $store_name,
		'url'         => home_url( '/' ),
		'description' => $description,
	);

	if ( $logo_url ) {
		$organization['logo'] = esc_url_raw( $logo_url );
	}

	$cnpj = trim( (string) gstore_get_cnpj() );
	if ( '' !== $cnpj ) {
		$organization['taxID'] = $cnpj;
	}

	$phone = trim( (string) gstore_get_phone() );
	if ( '' !== $phone ) {
		$organization['telephone'] = $phone;
	}

	$email = gstore_get_store_email();
	if ( '' !== $email ) {
		$organization['email'] = $email;
	}

	$address = gstore_store_info()->get_value( 'address' );
	if ( is_array( $address ) ) {
		$organization['address'] = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => trim( implode( ', ', array_filter( array( $address['street'] ?? '', $address['neighborhood'] ?? '' ), 'strlen' ) ) ),
				'addressLocality' => $address['city'] ?? '',
				'addressRegion'   => $address['state'] ?? '',
				'postalCode'      => $address['zipcode'] ?? '',
				'addressCountry'  => $address['country'] ?? 'Brasil',
			)
		);
	}

	$same_as = array_filter(
		array(
			gstore_get_social_link( 'instagram' ),
			gstore_get_social_link( 'facebook' ),
			gstore_get_social_link( 'youtube' ),
			gstore_get_telegram_link(),
		)
	);

	if ( ! empty( $same_as ) ) {
		$organization['sameAs'] = array_values( $same_as );
	}

	$faq_entities = array();
	foreach ( gstore_get_sobre_nos_faq_items() as $item ) {
		$faq_entities[] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $item['question'] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( $item['answer'] ),
			),
		);
	}

	$schema = array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			$organization,
			array(
				'@type'      => 'FAQPage',
				'@id'        => home_url( '/sobre-nos/#faq' ),
				'url'        => home_url( '/sobre-nos/' ),
				'mainEntity' => $faq_entities,
			),
		),
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
add_action( 'wp_head', 'gstore_sobre_nos_json_ld', 30 );

// 5. Atualizar opções do banco de dados (executa apenas uma vez para garantir robustez)
function gstore_force_woocommerce_image_options() {
	// Executa apenas uma vez (ou quando necessário)
	if ( get_option( 'gstore_image_options_set' ) === 'v2' ) {
		return;
	}

	// Opção principal de crop
	update_option( 'woocommerce_thumbnail_cropping', 'uncropped' );

	// Opções de tamanho (formato antigo, ainda respeitado)
	update_option( 'woocommerce_thumbnail_image_width', 300 );
	update_option( 'woocommerce_single_image_width', 1000 );

	// Marca como configurado
	update_option( 'gstore_image_options_set', 'v2' );
}
add_action( 'init', 'gstore_force_woocommerce_image_options' );

/**
 * Adiciona resource hints (preconnect, dns-prefetch) para melhorar performance.
 *
 * Adiciona preconnect para CDNs e recursos externos para reduzir latência
 * na primeira conexão. Isso pode economizar ~300ms no tempo de carregamento.
 */
function gstore_add_resource_hints() {
	// Preconnect para FontAwesome CDN (prioridade alta)
	echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";

	// DNS-prefetch para outras origens externas (menos crítico)
	echo '<link rel="dns-prefetch" href="https://upload.wikimedia.org">' . "\n";
	echo '<link rel="dns-prefetch" href="https://secure.gravatar.com">' . "\n";
}
add_action( 'wp_head', 'gstore_add_resource_hints', 1 );

/**
 * Adiciona font-display: swap para FontAwesome para melhorar FCP.
 *
 * Isso garante que o texto seja visível imediatamente enquanto as fontes carregam,
 * evitando FOIT (Flash of Invisible Text) e melhorando a percepção de velocidade.
 */
function gstore_fontawesome_font_display() {
	?>
	<style id="gstore-fontawesome-font-display">
		/* Force font-display: swap for FontAwesome webfonts */
		@font-face {
			font-family: 'Font Awesome 6 Brands';
			font-style: normal;
			font-weight: 400;
			font-display: swap;
		}
		@font-face {
			font-family: 'Font Awesome 6 Free';
			font-style: normal;
			font-weight: 400;
			font-display: swap;
		}
		@font-face {
			font-family: 'Font Awesome 6 Free';
			font-style: normal;
			font-weight: 900;
			font-display: swap;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'gstore_fontawesome_font_display', 2 );

/**
 * Adiciona preload para Font Awesome CSS para melhorar performance.
 *
 * Preload permite que o navegador baixe o recurso com prioridade alta
 * sem bloquear a renderização inicial.
 */
function gstore_preload_fontawesome() {
	?>
	<link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
	<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>
	<?php
}
add_action( 'wp_head', 'gstore_preload_fontawesome', 1 );

/**
 * Inline CSS crítico acima da dobra (header e hero básico).
 *
 * Isso reduz render blocking ao colocar estilos essenciais diretamente no HTML,
 * permitindo que o header e hero sejam renderizados imediatamente.
 */
function gstore_inline_critical_css() {
	// CSS crítico mínimo para renderização inicial do header e hero
	$critical_css = '
		/* Reset header */
		header.Gstore-header-shell,
		.Gstore-header-shell {
			width: 100% !important;
			max-width: none !important;
			margin: 0 !important;
			padding: 0 !important;
		}

		/* Top bar básico */
		.Gstore-top-bar {
			background-color: #0a0a0a;
			color: #fff;
			font-size: 14px;
		}

		.Gstore-top-bar__inner {
			max-width: 1280px;
			margin: 0 auto;
			padding: 4px 20px;
			display: flex;
			flex-wrap: wrap;
			justify-content: space-between;
			align-items: center;
			gap: 16px;
		}

		.Gstore-top-bar__link {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			color: #fff;
			text-decoration: none;
		}

		/* Header principal básico */
		.Gstore-header {
			background-color: #0a0a0a;
		}

		.Gstore-header__inner {
			max-width: 1280px;
			margin: 0 auto;
			padding: 6px 20px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 16px;
		}

		.Gstore-header__logo {
			color: #fff;
			text-decoration: none;
			font-weight: bold;
			font-size: 20px;
		}

		/* Hero slider básico */
		.Gstore-hero-slider {
			position: relative;
			width: 100%;
			overflow: hidden;
		}

		.Gstore-hero-slider__track {
			display: flex;
			transition: transform 0.5s ease;
		}

		.Gstore-hero-slider__slide {
			min-width: 100%;
			margin: 0;
			padding: 0;
		}

		.Gstore-hero-slider__slide img {
			width: 100%;
			height: auto;
			display: block;
		}
	';

	// Minifica o CSS crítico (remove espaços extras)
	$critical_css = preg_replace( '/\s+/', ' ', $critical_css );
	$critical_css = str_replace( array( '; ', ' {', '{ ', ' }', '} ', ': ' ), array( ';', '{', '{', '}', '}', ':' ), $critical_css );
	$critical_css = trim( $critical_css );

	?>
	<style id="gstore-critical-css">
		<?php echo $critical_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</style>
	<?php
}
add_action( 'wp_head', 'gstore_inline_critical_css', 3 );

/**
 * Adiciona preload para recursos críticos (imagens hero, fontes, etc).
 *
 * Preload ajuda o navegador a priorizar recursos críticos,
 * melhorando LCP e FCP.
 */
function gstore_add_preload_resources() {
	// Preload da primeira imagem do hero (se disponível)
	if ( function_exists( 'gstore_get_hero_slide_1_id' ) ) {
		$hero_slide_1_id = gstore_get_hero_slide_1_id();
		if ( $hero_slide_1_id > 0 ) {
			$hero_src = wp_get_attachment_image_src( $hero_slide_1_id, 'full' );
			if ( $hero_src && ! empty( $hero_src[0] ) ) {
				$hero_url    = $hero_src[0];
				$hero_srcset = wp_get_attachment_image_srcset( $hero_slide_1_id, 'full' );
				$preload_tag = '<link rel="preload" as="image" href="' . esc_url( $hero_url ) . '"';
				if ( ! empty( $hero_srcset ) ) {
					$preload_tag .= ' imagesrcset="' . esc_attr( $hero_srcset ) . '" imagesizes="100vw"';
				}
				$preload_tag .= ' fetchpriority="high">';
				echo $preload_tag . "\n";
			}
		}
	}

	// Preload de fontes críticas (se necessário)
	$critical_fonts = apply_filters( 'gstore_critical_fonts', array() );
	foreach ( $critical_fonts as $font_url ) {
		echo '<link rel="preload" as="font" href="' . esc_url( $font_url ) . '" crossorigin>' . "\n";
	}

	// Preload do CSS crítico do tema pai (se necessário)
	if ( is_front_page() ) {
		$parent_theme = wp_get_theme( 'twentytwentyfive' );
		if ( $parent_theme->exists() ) {
			$parent_css = get_template_directory_uri() . '/style.css';
			echo '<link rel="preload" as="style" href="' . esc_url( $parent_css ) . '">' . "\n";
		}
	}
}
add_action( 'wp_head', 'gstore_add_preload_resources', 1 );

/**
 * Expandida lista de CSS não crítico que pode ser deferido.
 *
 * Adiciona mais CSS à lista de defer, incluindo layouts e componentes
 * que não são necessários para renderização inicial.
 */
function gstore_defer_non_critical_css( $tag, $handle, $href, $media ) {
	// Não aplica em modo de desenvolvimento para facilitar debug
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return $tag;
	}
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		return $tag;
	}

	// Android: alguns browsers/versões podem falhar em disparar onload em <link rel="stylesheet">
	// quando usamos a técnica media="print" (o CSS fica preso em print e a página fica sem estilo).
	// Preferimos confiabilidade no Android e não deferimos CSS por esta técnica nesse caso.
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if ( $ua && preg_match( '/Android/i', $ua ) ) {
		return $tag;
	}

	// Lista expandida de CSS não crítico que pode ser deferido
	$non_critical_css = array(
		// Font Awesome - não crítico para renderização inicial (ícones podem carregar depois)
		'gstore-fontawesome',

		// CSS de páginas específicas
		'gstore-my-account-css',
		'gstore-como-comprar-arma-css',
		'gstore-informativo-css',
		'gstore-sobre-nos-css',
		'gstore-notices-css',

		// CSS de layouts que não estão acima da dobra
		'gstore-header-css',      // Já inlinado como crítico, pode defer o resto

		// CSS de componentes não críticos
		'gstore-product-card-css', // Não está acima da dobra na home

		// CSS do WooCommerce que não é crítico
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'woocommerce-blocktheme',
		'wc-blocks-style',
		'wc-blocks-editor-style',
		'blocks-mini-cart-css',
		'blocks-customer-account-css',
		'blocks-packages-style-css',
		'blocks-mini-cart-contents-css',
		'woocommerce-general',    // CSS geral do WooCommerce
		'woocommerce-inline',     // CSS inline do WooCommerce

		// CSS de layouts específicos
		'gstore-home-css',         // Pode defer se não for home ou se hero já foi renderizado
	);

	// CSS de home que só deve ser deferido se não for a home page
	if ( 'gstore-home-css' === $handle && is_front_page() ) {
		// Não defer na home, mas pode ser otimizado de outra forma
		return $tag;
	}

	// CSS de header - defer apenas partes não críticas (já tem inline crítico)
	if ( 'gstore-header-css' === $handle ) {
		// Defer apenas se não for a primeira carga (já tem inline)
		// Na prática, pode defer pois já temos CSS crítico inline
	}

	// Verifica se é CSS não crítico
	if ( ! in_array( $handle, $non_critical_css, true ) ) {
		return $tag;
	}

	// Verifica se já tem defer/preload
	if ( strpos( $tag, 'onload=' ) !== false || strpos( $tag, 'media="print"' ) !== false ) {
		return $tag;
	}

	$original_tag = $tag;

	// Marca link como "deferido" para permitir fallback JS se o onload falhar.
	// (Não usa DOMDocument por performance e para evitar efeitos colaterais no HTML gerado pelo WP.)
	$tag = preg_replace(
		'/<link\\s/i',
		'<link data-gstore-deferred="1" data-gstore-media="' . esc_attr( $media ) . '" ',
		$tag,
		1
	);

	// Aplica técnica de defer usando JavaScript
	// Troca media para print e depois muda para all quando carregar
	// Suporta tanto aspas simples quanto duplas
	if ( strpos( $tag, "media='" ) !== false ) {
		$deferred_tag = str_replace(
			"media='{$media}'",
			"media='print' onload=\"this.media=this.getAttribute('data-gstore-media')||'all'\"",
			$tag
		);
		$noscript_tag = str_replace( "media='print'", "media='{$media}'", $original_tag );
	} else {
		$deferred_tag = str_replace(
			'media="' . $media . '"',
			'media="print" onload="this.media=this.getAttribute(\'data-gstore-media\')||\'all\'"',
			$tag
		);
		$noscript_tag = str_replace( 'media="print"', 'media="' . $media . '"', $original_tag );
	}

	// Adiciona noscript fallback para browsers sem JS
	$deferred_tag .= '<noscript>' . $noscript_tag . '</noscript>';

	return $deferred_tag;
}
add_filter( 'style_loader_tag', 'gstore_defer_non_critical_css', 10, 4 );

/**
 * Fallback para CSS deferido via media="print": se o onload falhar, reativa o CSS.
 *
 * Importante: só atua em links marcados pelo tema (data-gstore-deferred="1").
 */
function gstore_deferred_css_fallback_script() {
	?>
	<script id="gstore-deferred-css-fallback">
	(function() {
		'use strict';
		function fixDeferredCss() {
			var links = document.querySelectorAll('link[data-gstore-deferred="1"][rel~="stylesheet"][media="print"]');
			for (var i = 0; i < links.length; i++) {
				var link = links[i];
				var targetMedia = link.getAttribute('data-gstore-media') || 'all';
				link.media = targetMedia;
			}
		}
		// Tenta cedo (após parse) e também após load, e com um timeout de segurança.
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fixDeferredCss);
		} else {
			fixDeferredCss();
		}
		window.addEventListener('load', fixDeferredCss);
		setTimeout(fixDeferredCss, 2500);
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'gstore_deferred_css_fallback_script', 4 );

/**
 * Otimiza carregamento de scripts para reduzir main-thread work.
 *
 * Aplica defer/async quando apropriado, especialmente para scripts
 * que não são necessários para renderização inicial.
 */
function gstore_optimize_script_loading( $tag, $handle, $src ) {
	// Não aplica em modo de desenvolvimento
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return $tag;
	}
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		return $tag;
	}

	// Scripts que podem ser deferidos (não críticos para renderização inicial)
	$defer_scripts = array(
		'gstore-home-benefits',        // Não crítico para primeira renderização
		'gstore-home-products-carousel', // Carrossel pode carregar depois
		'gstore-product-card',          // Não crítico acima da dobra
		'gstore-my-account',            // Página específica
	);

	// Scripts que podem usar async (não dependem de outros)
	$async_scripts = array();

	// Aplica defer
	if ( in_array( $handle, $defer_scripts, true ) ) {
		// Verifica se já tem defer ou async
		if ( strpos( $tag, ' defer' ) === false && strpos( $tag, ' async' ) === false ) {
			$tag = str_replace( ' src', ' defer src', $tag );
		}
	}

	// Aplica async
	if ( in_array( $handle, $async_scripts, true ) ) {
		if ( strpos( $tag, ' defer' ) === false && strpos( $tag, ' async' ) === false ) {
			$tag = str_replace( ' src', ' async src', $tag );
		}
	}

	return $tag;
}
add_filter( 'script_loader_tag', 'gstore_optimize_script_loading', 10, 3 );

/**
 * Otimiza back/forward cache (bfcache) removendo barreiras.
 *
 * Garante que a página pode ser restaurada do bfcache corretamente
 * e evita listeners que bloqueiam essa funcionalidade.
 */
function gstore_fix_back_forward_cache() {
	?>
	<script id="gstore-bfcache-fix">
	(function() {
		'use strict';

		// Detecta quando a página é restaurada do bfcache
		window.addEventListener('pageshow', function(event) {
			if (event.persisted) {
				// Página restaurada do bfcache - pode precisar re-inicializar alguns recursos
				// Mas não força reload completo

				// Se houver scripts que precisam reinicializar, podem escutar este evento
				window.dispatchEvent(new CustomEvent('gstore:bfcache:restore'));
			}
		});

		// Evita uso de beforeunload quando possível (bloqueia bfcache)
		// Intercepta tentativas de adicionar beforeunload apenas em produção
		if (!window.location.hostname.includes('localhost') && !window.location.hostname.includes('127.0.0.1')) {
			var originalAddEventListener = window.addEventListener;
			window.addEventListener = function(type, listener, options) {
				// Bloqueia beforeunload que impede bfcache (exceto se realmente necessário)
				if (type === 'beforeunload' && typeof listener === 'function') {
					// Permite apenas se for realmente necessário (ex: formulário com dados não salvos)
					// Na prática, não bloqueamos, mas logamos para debug
					if (window.console && console.warn) {
						console.warn('[Gstore Performance] beforeunload listener detectado - pode afetar bfcache');
					}
				}
				return originalAddEventListener.call(this, type, listener, options);
			};
		}

		// Garante que não há referências a objetos que podem impedir bfcache
		// Remove referências circulares comuns
		if ('requestIdleCallback' in window) {
			requestIdleCallback(function() {
				// Limpa timers órfãos que podem impedir bfcache
				// Não faz nada agressivo, apenas garante limpeza
			}, { timeout: 1000 });
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'gstore_fix_back_forward_cache', 1 );

/**
 * Remove preload automático do WordPress para o style.css do tema filho.
 *
 * O WordPress 5.8+ adiciona automaticamente um preload para o stylesheet do tema,
 * mas isso pode causar avisos no console se o CSS não for usado imediatamente.
 * Como o CSS está sendo enfileirado corretamente, o preload não é necessário.
 */
function gstore_remove_automatic_stylesheet_preload( $hints, $relation_type ) {
	// Remove apenas preload do stylesheet do tema filho
	if ( 'preload' === $relation_type ) {
		$stylesheet_uri = get_stylesheet_uri();
		foreach ( $hints as $key => $hint ) {
			if ( isset( $hint['href'] ) && $hint['href'] === $stylesheet_uri ) {
				unset( $hints[ $key ] );
			}
		}
	}
	return $hints;
}
add_filter( 'wp_resource_hints', 'gstore_remove_automatic_stylesheet_preload', 10, 2 );

/**
 * Previne conflitos de múltiplas instâncias do React e problemas de acessibilidade.
 *
 * 1. O erro "Failed to execute 'removeChild'" geralmente ocorre quando há
 *    múltiplas instâncias do React ou conflitos entre React e outras bibliotecas.
 *
 * 2. O problema de aria-hidden ocorre quando o WooCommerce define aria-hidden="true"
 *    no wp-site-blocks enquanto o botão do mini-cart ainda tem foco.
 */
function gstore_prevent_react_conflicts() {
	?>
	<script id="gstore-react-conflict-fix">
	(function() {
		'use strict';

		// Previne erros de removeChild do React de forma segura
		// Apenas intercepta chamadas que falhariam
		var originalRemoveChild = Node.prototype.removeChild;
		Node.prototype.removeChild = function(child) {
			// Verifica se child é válido
			if (!child) {
				return child;
			}

			try {
				// Verifica se o nó ainda está no DOM e se é realmente filho
				if (this.contains && this.contains(child)) {
					// Verifica se o nó ainda tem um parent (pode ter sido removido por outro processo)
					if (child.parentNode === this) {
						return originalRemoveChild.call(this, child);
					} else if (child.parentNode) {
						// O nó tem um parent diferente, não é filho deste nó
						// Retorna sem erro
						return child;
					} else {
						// O nó não tem parent, já foi removido
						// Retorna sem erro
						return child;
					}
				}
				// Se não for filho, retorna o nó sem erro
				return child;
			} catch (e) {
				// Se houver erro, apenas retorna o nó sem lançar exceção
				// Isso previne que o erro quebre a renderização do React
				if (window.location.hostname.includes('localhost') || window.location.hostname.includes('127.0.0.1')) {
					console.warn('[Gstore] Erro ao remover nó (prevenido):', e.message);
				}
				return child;
			}
		};

		// Corrige problema de aria-hidden no mini-cart
		// Quando o drawer do mini-cart é aberto, o WooCommerce define aria-hidden="true"
		// no wp-site-blocks, mas o botão do mini-cart ainda pode ter foco
		function fixMiniCartAriaHidden() {
			var wpSiteBlocks = document.querySelector('.wp-site-blocks');
			var miniCartButton = document.querySelector('.wc-block-mini-cart__button');
			var miniCartDrawer = document.querySelector('.wc-block-mini-cart__drawer');

			if (!wpSiteBlocks || !miniCartButton || !miniCartDrawer) {
				return;
			}

			// Intercepta quando o WooCommerce tenta definir aria-hidden="true"
			// Verifica se há elementos focáveis antes de aplicar
			var originalSetAttribute = Element.prototype.setAttribute;
			Element.prototype.setAttribute = function(name, value) {
				// Se está tentando definir aria-hidden="true" no wp-site-blocks
				if (name === 'aria-hidden' && value === 'true' && this === wpSiteBlocks) {
					var activeElement = document.activeElement;

					// Verifica se há um elemento focável dentro do wp-site-blocks
					// que não está dentro do drawer do mini-cart
					var focusableElements = wpSiteBlocks.querySelectorAll(
						'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
					);

					var hasFocusedElement = false;
					for (var i = 0; i < focusableElements.length; i++) {
						var el = focusableElements[i];
						// Ignora elementos dentro do drawer (eles devem estar hidden)
						if (!el.closest('.wc-block-mini-cart__drawer')) {
							if (el === activeElement || el.contains(activeElement)) {
								hasFocusedElement = true;
								break;
							}
						}
					}

					// Se há um elemento focável, não aplica aria-hidden no wp-site-blocks
					// Aplica apenas no conteúdo principal (não no header)
					if (hasFocusedElement) {
						var mainContent = document.querySelector('main:not(.Gstore-header)');
						if (mainContent && !mainContent.closest('.wc-block-mini-cart__drawer')) {
							mainContent.setAttribute('aria-hidden', 'true');
						}
						// Não aplica no wp-site-blocks
						return;
					}
				}

				// Para outros casos, usa o comportamento padrão
				return originalSetAttribute.call(this, name, value);
			};

			// Observa mudanças no drawer do mini-cart para limpar aria-hidden quando fechar
			var drawerObserver = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
						var isDrawerOpen = miniCartDrawer.classList.contains('is-open');

						// Quando o drawer fecha, remove aria-hidden do conteúdo principal
						if (!isDrawerOpen) {
							var mainContent = document.querySelector('main[aria-hidden="true"]');
							if (mainContent) {
								mainContent.removeAttribute('aria-hidden');
							}
							// Também remove do wp-site-blocks se estiver definido
							if (wpSiteBlocks.getAttribute('aria-hidden') === 'true') {
								wpSiteBlocks.removeAttribute('aria-hidden');
							}
						}
					}
				});
			});

			drawerObserver.observe(miniCartDrawer, {
				attributes: true,
				attributeFilter: ['class']
			});
		}

		// Inicializa quando o DOM estiver pronto
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fixMiniCartAriaHidden);
		} else {
			fixMiniCartAriaHidden();
		}

		// Também tenta após um pequeno delay para garantir que o WooCommerce carregou
		setTimeout(fixMiniCartAriaHidden, 1000);
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'gstore_prevent_react_conflicts', 999 );

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
	$stylesheet_file = get_stylesheet_directory() . '/style.css';
	$stylesheet_version = ( file_exists( $stylesheet_file ) ) ? (string) filemtime( $stylesheet_file ) : $theme_version;
	$footer_css_file = get_theme_file_path( 'assets/css/layouts/footer.css' );
	$footer_css_version = file_exists( $footer_css_file ) ? (string) filemtime( $footer_css_file ) : $theme_version;
	$header_css_file = get_theme_file_path( 'assets/css/layouts/header.css' );
	$header_css_version = file_exists( $header_css_file ) ? (string) filemtime( $header_css_file ) : $theme_version;

	// Obtém timestamp da última atualização dos tokens para forçar recarregamento
	$tokens_version = get_option( 'gstore_tokens_last_updated', time() );
	$gstore_version = $theme_version . '.' . $tokens_version;

	// Tema pai (só carrega se Gstore for realmente child theme do TT5,
	// caso contrário get_template_directory_uri() aponta para o próprio Gstore
	// e o style.css seria carregado duas vezes com versões diferentes)
	if ( is_child_theme() && $parent_theme->exists() ) {
		wp_enqueue_style(
			$parent_handle,
			get_template_directory_uri() . '/style.css',
			array(),
			$parent_theme->get( 'Version' )
		);
	} else {
		wp_register_style( $parent_handle, false );
	}

	// Font Awesome - Carregado de forma não bloqueante
	// Usa técnica de media="print" + onload para evitar render blocking
	wp_enqueue_style(
		'gstore-fontawesome',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
		array(),
		'6.5.1'
	);

	// Sistema modular Gstore (tokens, base, utilities, components, layouts)
	// Usa versão com timestamp para forçar recarregamento quando tokens são atualizados
	wp_enqueue_style(
		'gstore-main',
		get_theme_file_uri( 'assets/css/gstore-main.css' ),
		array( $parent_handle, 'gstore-fontawesome' ),
		$gstore_version
	);

	// Style.css principal (contém estilos legados que ainda não foram migrados)
	wp_enqueue_style(
		'gstore-style',
		get_stylesheet_uri(),
		array( 'gstore-main' ),
		$stylesheet_version
	);

	// Footer Gstore (migrado do style.css para módulo dedicado)
	wp_enqueue_style(
		'gstore-footer-css',
		get_theme_file_uri( 'assets/css/layouts/footer.css' ),
		array( 'gstore-style' ),
		$footer_css_version
	);

	// Header CSS - carregado por último para ter prioridade sobre estilos legados
	wp_enqueue_style(
		'gstore-header-css',
		get_theme_file_uri( 'assets/css/layouts/header.css' ),
		array( 'gstore-style' ),
		$header_css_version
	);

	// Mini-cart CSS dedicado.
	// Carrega separadamente com filemtime para evitar stale cache do @import em gstore-main.css.
	$mini_cart_css_file = get_theme_file_path( 'assets/css/components/mini-cart.css' );
	$mini_cart_css_version = file_exists( $mini_cart_css_file ) ? (string) filemtime( $mini_cart_css_file ) : $theme_version;
	wp_enqueue_style(
		'gstore-mini-cart-css',
		get_theme_file_uri( 'assets/css/components/mini-cart.css' ),
		array( 'gstore-header-css' ),
		$mini_cart_css_version
	);

	// Botão flutuante do Telegram (usa href dinâmico da top bar; sem hardcode)
	wp_enqueue_style(
		'gstore-telegram-floating-css',
		get_theme_file_uri( 'assets/css/components/telegram-floating.css' ),
		array( 'gstore-header-css' ),
		$theme_version
	);

	// CSS da página de minha conta (login/registro)
	if ( class_exists( 'WooCommerce' ) && function_exists( 'is_account_page' ) && is_account_page() ) {
		$my_account_css_file    = get_theme_file_path( 'assets/css/my-account.css' );
		$my_account_css_version = file_exists( $my_account_css_file ) ? (string) filemtime( $my_account_css_file ) : $theme_version;
		wp_enqueue_style(
			'gstore-my-account-css',
			get_theme_file_uri( 'assets/css/my-account.css' ),
			array( 'gstore-style' ),
			$my_account_css_version
		);

		// Fulfillment timeline (apenas na página de detalhes do pedido).
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' ) ) {
			wp_enqueue_style(
				'gstore-fulfillment-timeline-css',
				get_theme_file_uri( 'assets/css/fulfillment-timeline.css' ),
				array( 'gstore-my-account-css' ),
				filemtime( get_theme_file_path( 'assets/css/fulfillment-timeline.css' ) )
			);
		}
	}

	// CSS da página de como comprar arma
	if ( is_page( 'como-comprar-arma' ) ) {
		wp_enqueue_style(
			'gstore-como-comprar-arma-css',
			get_theme_file_uri( 'assets/css/como-comprar-arma.css' ),
			array( 'gstore-style' ),
			$theme_version
		);
	}

	// CSS da página informativo (pós-venda)
	if ( is_page( 'informativo' ) ) {
		wp_enqueue_style(
			'gstore-informativo-css',
			get_theme_file_uri( 'assets/css/informativo.css' ),
			array( 'gstore-style' ),
			$theme_version
		);
	}

	// CSS da página Sobre nós
	if ( is_page( 'sobre-nos' ) ) {
		$sobre_nos_css_file = get_theme_file_path( 'assets/css/sobre-nos.css' );
		$sobre_nos_css_version = file_exists( $sobre_nos_css_file ) ? (string) filemtime( $sobre_nos_css_file ) : $theme_version;
		wp_enqueue_style(
			'gstore-sobre-nos-css',
			get_theme_file_uri( 'assets/css/sobre-nos.css' ),
			array( 'gstore-style' ),
			$sobre_nos_css_version
		);
	}

	if ( ( function_exists( 'is_privacy_policy' ) && is_privacy_policy() ) || is_page( 'politica-de-privacidade' ) ) {
		wp_enqueue_style(
			'gstore-privacy-policy-css',
			get_theme_file_uri( 'assets/css/privacy-policy.css' ),
			array( 'gstore-style' ),
			$theme_version
		);
	}

	// CSS de Notificações e Modais
	wp_enqueue_style(
		'gstore-notices-css',
		get_theme_file_uri( 'assets/css/components/notices.css' ),
		array( 'gstore-main' ),
		$theme_version
	);

	// CSS do Toast de Adicionar ao Carrinho
	wp_enqueue_style(
		'gstore-add-to-cart-toast-css',
		get_theme_file_uri( 'assets/css/components/add-to-cart-toast.css' ),
		array( 'gstore-main' ),
		$theme_version
	);

	// Filtro de Categorias Marketplace
	$category_filter_css_file = get_theme_file_path( 'assets/css/category-filter.css' );
	$category_filter_css_version = file_exists( $category_filter_css_file ) ? (string) filemtime( $category_filter_css_file ) : $theme_version;
	wp_enqueue_style(
		'gstore-category-filter',
		get_theme_file_uri( 'assets/css/category-filter.css' ),
		array( 'gstore-style' ),
		$category_filter_css_version
	);

	// Filtro de Categorias Marketplace - JS
	$category_filter_js_file = get_theme_file_path( 'assets/js/category-filter.js' );
	$category_filter_js_version = file_exists( $category_filter_js_file ) ? (string) filemtime( $category_filter_js_file ) : $theme_version;
	wp_enqueue_script(
		'gstore-category-filter-js',
		get_theme_file_uri( 'assets/js/category-filter.js' ),
		array(),
		$category_filter_js_version,
		true
	);

	$design_token_overrides_css = gstore_get_design_token_overrides_css();
	if ( '' !== $design_token_overrides_css ) {
		wp_add_inline_style( 'gstore-header-css', $design_token_overrides_css );
	}
}
add_action( 'wp_enqueue_scripts', 'gstore_enqueue_styles' );

/**
 * Adiciona cabeçalhos de política de permissões para permitir pagamentos e clipboard.
 * Isso resolve o erro: Permissions policy violation: payment is not allowed in this document.
 */
function gstore_add_permissions_policy_header() {
	if ( ! is_admin() ) {
		// Permite pagamentos e área de transferência (para Pix) no documento e iframes.
		header( 'Permissions-Policy: payment=*, clipboard-write=*' );
	}
}
add_action( 'send_headers', 'gstore_add_permissions_policy_header' );

/**
 * Verifica se a request atual está pedindo o manifest PWA.
 *
 * @return bool
 */
function gstore_is_pwa_manifest_request() {
	return isset( $_GET['gstore_manifest'] ) && '1' === (string) wp_unslash( $_GET['gstore_manifest'] );
}

/**
 * Verifica se a request atual está pedindo o service worker PWA.
 *
 * @return bool
 */
function gstore_is_pwa_service_worker_request() {
	return isset( $_GET['gstore_sw'] ) && '1' === (string) wp_unslash( $_GET['gstore_sw'] );
}

/**
 * Verifica se a request atual está pedindo um ícone PWA gerado pelo tema.
 *
 * @return bool
 */
function gstore_is_pwa_icon_request() {
	return gstore_get_pwa_icon_request_size() > 0;
}

/**
 * Retorna o tamanho solicitado para o ícone PWA.
 *
 * @return int
 */
function gstore_get_pwa_icon_request_size() {
	if ( ! isset( $_GET['gstore_pwa_icon'] ) ) {
		return 0;
	}

	$size = absint( wp_unslash( $_GET['gstore_pwa_icon'] ) );
	if ( $size < 64 || $size > 1024 ) {
		return 0;
	}

	return $size;
}

/**
 * Retorna uma versão curta do branding PWA para quebrar cache de manifest/ícones.
 *
 * @return string
 */
function gstore_get_pwa_branding_version() {
	static $version = null;

	if ( null !== $version ) {
		return $version;
	}

	$attachment_id = gstore_get_pwa_icon_source_attachment_id();
	$parts         = array(
		'pwa-branding-v2',
		(string) get_option( 'blogname', '' ),
		(string) get_option( 'site_icon', 0 ),
		(string) get_option( 'gstore_logo_id', 0 ),
		(string) $attachment_id,
		(string) wp_get_theme()->get( 'Version' ),
	);

	if ( $attachment_id > 0 ) {
		$attachment_meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $attachment_meta ) ) {
			$parts[] = wp_json_encode( $attachment_meta );
		}

		$attachment_file = get_attached_file( $attachment_id );
		if ( is_string( $attachment_file ) && '' !== $attachment_file && file_exists( $attachment_file ) ) {
			$parts[] = (string) filemtime( $attachment_file );
			$parts[] = (string) filesize( $attachment_file );
		}
	}

	$version = substr( md5( implode( '|', $parts ) ), 0, 12 );
	return $version;
}

/**
 * Retorna a URL do manifest PWA.
 *
 * @return string
 */
function gstore_get_pwa_manifest_url() {
	return esc_url_raw(
		add_query_arg(
			array(
				'gstore_manifest' => '1',
				'v'               => gstore_get_pwa_branding_version(),
			),
			home_url( '/' )
		)
	);
}

/**
 * Retorna a URL do service worker PWA.
 *
 * @return string
 */
function gstore_get_pwa_service_worker_url() {
	return home_url( '/?gstore_sw=1' );
}

/**
 * Retorna a URL inicial do app instalado.
 *
 * @return string
 */
function gstore_get_pwa_start_url() {
	return home_url( '/' );
}

/**
 * Retorna a scope do app instalado.
 *
 * @return string
 */
function gstore_get_pwa_scope_url() {
	return home_url( '/' );
}

/**
 * Retorna o path da scope do app instalado.
 *
 * @return string
 */
function gstore_get_pwa_scope_path() {
	$scope_path = wp_parse_url( gstore_get_pwa_scope_url(), PHP_URL_PATH );
	return is_string( $scope_path ) && '' !== $scope_path ? trailingslashit( $scope_path ) : '/';
}

/**
 * Retorna o nome da aplicação PWA.
 *
 * @return string
 */
function gstore_get_pwa_app_name() {
	$name = trim( wp_specialchars_decode( (string) get_option( 'blogname', '' ), ENT_QUOTES ) );
	if ( '' === $name ) {
		$name = trim( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	}

	return '' !== $name ? $name : 'GStore';
}

/**
 * Retorna o nome curto da aplicação PWA.
 *
 * @return string
 */
function gstore_get_pwa_short_name() {
	return gstore_get_pwa_app_name();
}

/**
 * Retorna a cor principal do app instalado.
 *
 * @return string
 */
function gstore_get_pwa_theme_color() {
	$accent = sanitize_hex_color( gstore_get_effective_accent_color() );
	return $accent ? $accent : '#b5a642';
}

/**
 * Retorna a cor de fundo do app instalado.
 *
 * @return string
 */
function gstore_get_pwa_background_color() {
	return '#ffffff';
}

/**
 * Adiciona um ícone ao manifest quando houver um attachment válido.
 *
 * @param array       $icons         Lista acumulada de ícones.
 * @param int         $attachment_id ID do attachment.
 * @param int|null    $size          Tamanho desejado.
 * @param string|null $purpose       Purpose do ícone.
 * @return array
 */
function gstore_maybe_add_pwa_icon_from_attachment( array $icons, $attachment_id, $size = null, $purpose = 'any' ) {
	$attachment_id = absint( $attachment_id );
	if ( $attachment_id <= 0 ) {
		return $icons;
	}

	$image_args = null;
	if ( null !== $size ) {
		$image_args = array( (int) $size, (int) $size );
	}

	$src = $image_args ? wp_get_attachment_image_url( $attachment_id, $image_args ) : wp_get_attachment_image_url( $attachment_id, 'full' );
	if ( ! $src ) {
		return $icons;
	}

	$sizes = '';
	if ( null !== $size ) {
		$sizes = (int) $size . 'x' . (int) $size;
	} else {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$width    = isset( $metadata['width'] ) ? absint( $metadata['width'] ) : 0;
		$height   = isset( $metadata['height'] ) ? absint( $metadata['height'] ) : 0;
		if ( $width > 0 && $height > 0 ) {
			$sizes = $width . 'x' . $height;
		}
	}

	$filetype = wp_check_filetype( (string) wp_parse_url( $src, PHP_URL_PATH ) );
	$type     = ! empty( $filetype['type'] ) ? $filetype['type'] : 'image/png';

	$key = $src . '|' . $sizes . '|' . (string) $purpose;
	$icons[ $key ] = array(
		'src'     => esc_url_raw( $src ),
		'sizes'   => $sizes,
		'type'    => $type,
		'purpose' => $purpose ? (string) $purpose : 'any',
	);

	return $icons;
}

/**
 * Retorna o attachment usado como fonte para os ícones PWA.
 *
 * @return int
 */
function gstore_get_pwa_icon_source_attachment_id() {
	$site_icon_id = absint( get_option( 'site_icon', 0 ) );
	if ( $site_icon_id > 0 ) {
		return $site_icon_id;
	}

	if ( function_exists( 'gstore_get_logo_id' ) ) {
		$logo_id = absint( gstore_get_logo_id() );
		if ( $logo_id > 0 ) {
			return $logo_id;
		}
	}

	return absint( get_theme_mod( 'custom_logo' ) );
}

/**
 * Retorna a URL de ícone PWA para um tamanho exato.
 *
 * @param int $size Tamanho desejado.
 * @return string
 */
function gstore_get_pwa_icon_url_for_size( $size ) {
	$size = absint( $size );
	if ( $size <= 0 ) {
		return '';
	}

	$site_icon_id = absint( get_option( 'site_icon', 0 ) );
	if ( $site_icon_id > 0 && function_exists( 'get_site_icon_url' ) ) {
		$site_icon_url = get_site_icon_url( $size );
		if ( $site_icon_url ) {
			return esc_url_raw(
				add_query_arg(
					array(
						'v' => gstore_get_pwa_branding_version(),
					),
					$site_icon_url
				)
			);
		}
	}

	$attachment_id = gstore_get_pwa_icon_source_attachment_id();
	if ( $attachment_id <= 0 ) {
		return '';
	}

	return esc_url_raw(
		add_query_arg(
			array(
				'gstore_pwa_icon' => $size,
				'v'               => gstore_get_pwa_branding_version(),
			),
			home_url( '/' )
		)
	);
}

/**
 * Retorna o tipo MIME mais provável do ícone PWA.
 *
 * @param string $url URL do ícone.
 * @return string
 */
function gstore_get_pwa_icon_mime_type( $url ) {
	$filetype = wp_check_filetype( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	return ! empty( $filetype['type'] ) ? $filetype['type'] : 'image/png';
}

/**
 * Retorna os ícones do manifest PWA.
 *
 * @return array<int, array<string, string>>
 */
function gstore_get_pwa_icon_entries() {
	$icons = array();
	foreach ( array( 192, 512 ) as $size ) {
		$src = gstore_get_pwa_icon_url_for_size( $size );
		if ( ! $src ) {
			continue;
		}

		$icons[] = array(
			'src'     => $src,
			'sizes'   => $size . 'x' . $size,
			'type'    => gstore_get_pwa_icon_mime_type( $src ),
			'purpose' => 'any maskable',
		);
	}

	return $icons;
}

/**
 * Retorna os atalhos do manifest PWA.
 *
 * @return array<int, array<string, mixed>>
 */
function gstore_get_pwa_shortcuts() {
	$icons         = gstore_get_pwa_icon_entries();
	$shortcut_icon = ! empty( $icons ) ? array_slice( $icons, 0, 1 ) : array();
	$home_url      = home_url( '/' );
	$atendimento   = home_url( '/atendimento/' );
	$myaccount     = home_url( '/minha-conta/' );

	if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) {
		$myaccount_permalink = wc_get_page_permalink( 'myaccount' );
		if ( $myaccount_permalink ) {
			$myaccount = $myaccount_permalink;
		}
	}

	return array(
		array(
			'name'        => __( 'Home', 'gstore' ),
			'short_name'  => __( 'Home', 'gstore' ),
			'description' => __( 'Abrir a home principal do site.', 'gstore' ),
			'url'         => $home_url,
			'icons'       => $shortcut_icon,
		),
		array(
			'name'        => __( 'Atendimento', 'gstore' ),
			'short_name'  => __( 'Atendimento', 'gstore' ),
			'description' => __( 'Abrir a central de atendimento.', 'gstore' ),
			'url'         => $atendimento,
			'icons'       => $shortcut_icon,
		),
		array(
			'name'        => __( 'Minha Conta', 'gstore' ),
			'short_name'  => __( 'Conta', 'gstore' ),
			'description' => __( 'Abrir a área da conta do cliente.', 'gstore' ),
			'url'         => $myaccount,
			'icons'       => $shortcut_icon,
		),
	);
}

/**
 * Monta o payload do manifest PWA.
 *
 * @return array<string, mixed>
 */
function gstore_build_pwa_manifest() {
	$app_name        = gstore_get_pwa_app_name();
	$description     = function_exists( 'gstore_get_meta' ) ? trim( (string) gstore_get_meta( 'description' ) ) : '';
	$description     = '' !== $description ? $description : get_bloginfo( 'description' );
	$description     = trim( (string) $description );
	$theme_color     = gstore_get_pwa_theme_color();
	$background      = gstore_get_pwa_background_color();
	$start_url       = gstore_get_pwa_start_url();
	$scope_url       = gstore_get_pwa_scope_url();
	$scope_path      = gstore_get_pwa_scope_path();
	$start_url_path  = wp_parse_url( $start_url, PHP_URL_PATH );
	$start_url_path  = is_string( $start_url_path ) && '' !== $start_url_path ? $start_url_path : '/';

	return array(
		'id'                           => trailingslashit( $start_url ),
		'name'                         => $app_name,
		'short_name'                   => gstore_get_pwa_short_name(),
		'description'                  => $description,
		'lang'                         => str_replace( '_', '-', get_locale() ),
		'dir'                          => is_rtl() ? 'rtl' : 'ltr',
		'start_url'                    => $start_url_path,
		'scope'                        => $scope_path,
		'display'                      => 'standalone',
		'orientation'                  => 'any',
		'theme_color'                  => $theme_color,
		'background_color'             => $background,
		'icons'                        => gstore_get_pwa_icon_entries(),
		'shortcuts'                    => gstore_get_pwa_shortcuts(),
		'prefer_related_applications'  => false,
	);
}

/**
 * Emite o manifest PWA.
 *
 * @return void
 */
function gstore_output_pwa_manifest() {
	if ( ! gstore_is_pwa_manifest_request() ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow', true );

	echo wp_json_encode( gstore_build_pwa_manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'template_redirect', 'gstore_output_pwa_manifest', 0 );

/**
 * Emite um ícone PWA quadrado em PNG quando não houver site icon nativo.
 *
 * @return void
 */
function gstore_output_pwa_icon() {
	if ( ! gstore_is_pwa_icon_request() ) {
		return;
	}

	$size          = gstore_get_pwa_icon_request_size();
	$attachment_id = gstore_get_pwa_icon_source_attachment_id();
	if ( $size <= 0 || $attachment_id <= 0 ) {
		status_header( 404 );
		exit;
	}

	$fallback_url = wp_get_attachment_image_url( $attachment_id, array( $size, $size ) );
	if ( ! $fallback_url ) {
		$fallback_url = gstore_get_image_url( $attachment_id, 'full' );
	}

	if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagepng' ) ) {
		if ( $fallback_url ) {
			wp_safe_redirect( $fallback_url, 302 );
			exit;
		}

		status_header( 404 );
		exit;
	}

	$source_path = get_attached_file( $attachment_id );
	if ( ( ! is_string( $source_path ) || '' === $source_path || ! file_exists( $source_path ) ) && function_exists( 'wp_get_original_image_path' ) ) {
		$source_path = wp_get_original_image_path( $attachment_id );
	}

	if ( ! is_string( $source_path ) || '' === $source_path || ! file_exists( $source_path ) || ! is_readable( $source_path ) ) {
		if ( $fallback_url ) {
			wp_safe_redirect( $fallback_url, 302 );
			exit;
		}

		status_header( 404 );
		exit;
	}

	$image_data = file_get_contents( $source_path );
	if ( false === $image_data ) {
		if ( $fallback_url ) {
			wp_safe_redirect( $fallback_url, 302 );
			exit;
		}

		status_header( 404 );
		exit;
	}

	$source_image = @imagecreatefromstring( $image_data );
	if ( false === $source_image ) {
		if ( $fallback_url ) {
			wp_safe_redirect( $fallback_url, 302 );
			exit;
		}

		status_header( 404 );
		exit;
	}

	$source_width  = imagesx( $source_image );
	$source_height = imagesy( $source_image );
	if ( $source_width <= 0 || $source_height <= 0 ) {
		imagedestroy( $source_image );
		if ( $fallback_url ) {
			wp_safe_redirect( $fallback_url, 302 );
			exit;
		}

		status_header( 404 );
		exit;
	}

	$canvas = imagecreatetruecolor( $size, $size );
	if ( false === $canvas ) {
		imagedestroy( $source_image );
		if ( $fallback_url ) {
			wp_safe_redirect( $fallback_url, 302 );
			exit;
		}

		status_header( 404 );
		exit;
	}

	$background_rgb = gstore_hex_to_rgb( gstore_get_pwa_background_color() );
	if ( ! is_array( $background_rgb ) || ! isset( $background_rgb['r'], $background_rgb['g'], $background_rgb['b'] ) ) {
		$background_rgb = array(
			'r' => 255,
			'g' => 255,
			'b' => 255,
		);
	}

	imagealphablending( $canvas, false );
	imagesavealpha( $canvas, true );

	$background = imagecolorallocate( $canvas, (int) $background_rgb['r'], (int) $background_rgb['g'], (int) $background_rgb['b'] );
	imagefilledrectangle( $canvas, 0, 0, $size, $size, $background );

	imagealphablending( $canvas, true );
	imagealphablending( $source_image, true );
	imagesavealpha( $source_image, true );

	$max_target_size = (int) floor( $size * 0.72 );
	$max_target_size = max( $max_target_size, (int) floor( $size * 0.5 ) );
	$scale           = min( $max_target_size / $source_width, $max_target_size / $source_height );
	$scale           = $scale > 0 ? $scale : 1;
	$target_width    = max( 1, (int) round( $source_width * $scale ) );
	$target_height   = max( 1, (int) round( $source_height * $scale ) );
	$target_x        = (int) floor( ( $size - $target_width ) / 2 );
	$target_y        = (int) floor( ( $size - $target_height ) / 2 );

	imagecopyresampled(
		$canvas,
		$source_image,
		$target_x,
		$target_y,
		0,
		0,
		$target_width,
		$target_height,
		$source_width,
		$source_height
	);

	status_header( 200 );
	header( 'Content-Type: image/png' );
	header( 'Cache-Control: public, max-age=31536000, immutable' );
	header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + YEAR_IN_SECONDS ) . ' GMT' );
	header( 'X-Robots-Tag: noindex, nofollow', true );

	imagepng( $canvas );

	imagedestroy( $canvas );
	imagedestroy( $source_image );
	exit;
}
add_action( 'template_redirect', 'gstore_output_pwa_icon', 0 );

/**
 * Monta o conteúdo do service worker PWA.
 *
 * @return string
 */
function gstore_build_pwa_service_worker() {
	$version_seed = array(
		wp_get_theme()->get( 'Version' ),
		file_exists( get_theme_file_path( 'assets/js/pwa-install.js' ) ) ? (string) filemtime( get_theme_file_path( 'assets/js/pwa-install.js' ) ) : '',
		file_exists( get_theme_file_path( 'style.css' ) ) ? (string) filemtime( get_theme_file_path( 'style.css' ) ) : '',
	);

	$config_json = wp_json_encode(
		array(
			'staticCacheName' => 'gstore-pwa-static-' . md5( implode( '|', $version_seed ) ),
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	return "const GSTORE_PWA_SW = " . $config_json . ";\n" . <<<'JS'
const STATIC_CACHE_NAME = GSTORE_PWA_SW.staticCacheName;
const STATIC_DESTINATIONS = new Set(['style', 'script', 'font', 'image']);
const BYPASS_PATH_FRAGMENTS = [
  '/wp-admin',
  '/wp-login.php',
  '/wp-json/',
  '/cart',
  '/checkout',
  '/carrinho',
  '/finalizar-compra',
  '/minha-conta',
  '/my-account'
];
const BYPASS_QUERY_KEYS = [
  'wc-ajax',
  'add-to-cart',
  'remove_item',
  'rest_route',
  'gstore_manifest',
  'gstore_sw',
  'gstore_pwa_icon'
];

function isSameOrigin(url) {
  return url.origin === self.location.origin;
}

function hasBlockedPath(url) {
  return BYPASS_PATH_FRAGMENTS.some((fragment) => url.pathname.indexOf(fragment) !== -1);
}

function hasBlockedQuery(url) {
  return BYPASS_QUERY_KEYS.some((key) => url.searchParams.has(key));
}

function shouldBypass(request) {
  if (request.method !== 'GET') {
    return true;
  }

  const url = new URL(request.url);

  if (!isSameOrigin(url)) {
    return true;
  }

  if (hasBlockedPath(url) || hasBlockedQuery(url)) {
    return true;
  }

  return false;
}

function isNavigationRequest(request) {
  return request.mode === 'navigate' || request.destination === 'document';
}

function isStaticAssetRequest(request, url) {
  if (STATIC_DESTINATIONS.has(request.destination)) {
    return true;
  }

  return /\.(?:css|js|mjs|woff2?|ttf|otf|png|jpe?g|webp|gif|svg|avif|ico)$/i.test(url.pathname);
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(STATIC_CACHE_NAME);
  const cached = await cache.match(request);
  const networkPromise = fetch(request)
    .then((response) => {
      if (response && response.ok && (response.type === 'basic' || response.type === 'cors')) {
        cache.put(request, response.clone());
      }
      return response;
    })
    .catch(() => null);

  if (cached) {
    return cached;
  }

  const networkResponse = await networkPromise;
  if (networkResponse) {
    return networkResponse;
  }

  return fetch(request);
}

async function networkFirstNavigation(request) {
  try {
    return await fetch(request);
  } catch (error) {
    const cache = await caches.open(STATIC_CACHE_NAME);
    const cached = await cache.match(request);
    if (cached) {
      return cached;
    }
    throw error;
  }
}

self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith('gstore-pwa-static-') && key !== STATIC_CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event && event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (shouldBypass(request)) {
    event.respondWith(fetch(request));
    return;
  }

  const url = new URL(request.url);

  if (isNavigationRequest(request)) {
    event.respondWith(networkFirstNavigation(request));
    return;
  }

  if (isStaticAssetRequest(request, url)) {
    event.respondWith(staleWhileRevalidate(request));
  }
});
JS;
}

/**
 * Emite o service worker PWA.
 *
 * @return void
 */
function gstore_output_pwa_service_worker() {
	if ( ! gstore_is_pwa_service_worker_request() ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Service-Worker-Allowed: ' . gstore_get_pwa_scope_path() );
	header( 'X-Robots-Tag: noindex, nofollow', true );

	echo gstore_build_pwa_service_worker();
	exit;
}
add_action( 'template_redirect', 'gstore_output_pwa_service_worker', 0 );

/**
 * Adiciona as meta tags PWA globais do site.
 *
 * @return void
 */
function gstore_add_pwa_meta_tags() {
	if ( is_admin() ) {
		return;
	}

	$manifest_url  = gstore_get_pwa_manifest_url();
	$theme_color   = gstore_get_pwa_theme_color();
	$background    = gstore_get_pwa_background_color();
	$site_icon_192 = gstore_get_pwa_icon_url_for_size( 192 );
	$site_icon_180 = gstore_get_pwa_icon_url_for_size( 180 );
	$app_name      = gstore_get_pwa_app_name();
	?>
	<link rel="manifest" href="<?php echo esc_url( $manifest_url ); ?>" />
	<meta name="theme-color" content="<?php echo esc_attr( $theme_color ); ?>" />
	<meta name="mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-status-bar-style" content="default" />
	<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $app_name ); ?>" />
	<meta name="application-name" content="<?php echo esc_attr( $app_name ); ?>" />
	<meta name="msapplication-TileColor" content="<?php echo esc_attr( $background ); ?>" />
	<?php if ( $site_icon_192 ) : ?>
		<link rel="icon" sizes="192x192" href="<?php echo esc_url( $site_icon_192 ); ?>" />
	<?php endif; ?>
	<?php if ( $site_icon_180 ) : ?>
		<link rel="apple-touch-icon" href="<?php echo esc_url( $site_icon_180 ); ?>" />
	<?php endif; ?>
	<?php
}
add_action( 'wp_head', 'gstore_add_pwa_meta_tags', 2 );

/**
 * Enfileira scripts customizados.
 */
function gstore_enqueue_scripts() {
	$header_js_file = get_theme_file_path( 'assets/js/header.js' );
	$header_js_version = file_exists( $header_js_file ) ? (string) filemtime( $header_js_file ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_script(
		'gstore-header',
		get_theme_file_uri( 'assets/js/header.js' ),
		array(),
		$header_js_version,
		true
	);

	$pwa_install_js_path    = get_theme_file_path( 'assets/js/pwa-install.js' );
	$pwa_install_js_version = file_exists( $pwa_install_js_path ) ? (string) filemtime( $pwa_install_js_path ) : wp_get_theme()->get( 'Version' );
	wp_enqueue_script(
		'gstore-pwa-install',
		get_theme_file_uri( 'assets/js/pwa-install.js' ),
		array(),
		$pwa_install_js_version,
		true
	);

	$blog_image_fit_js_path    = get_theme_file_path( 'assets/js/blog-image-fit.js' );
	$blog_image_fit_js_version = file_exists( $blog_image_fit_js_path ) ? (string) filemtime( $blog_image_fit_js_path ) : wp_get_theme()->get( 'Version' );
	wp_enqueue_script(
		'gstore-blog-image-fit',
		get_theme_file_uri( 'assets/js/blog-image-fit.js' ),
		array(),
		$blog_image_fit_js_version,
		true
	);

	$pwa_myaccount_url = home_url( '/minha-conta/' );
	if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) {
		$myaccount_permalink = wc_get_page_permalink( 'myaccount' );
		if ( $myaccount_permalink ) {
			$pwa_myaccount_url = $myaccount_permalink;
		}
	}

	wp_localize_script(
		'gstore-pwa-install',
		'gstorePwaConfig',
		array(
			'manifestUrl'       => gstore_get_pwa_manifest_url(),
			'serviceWorkerUrl'  => gstore_get_pwa_service_worker_url(),
			'startUrl'          => gstore_get_pwa_start_url(),
			'scopeUrl'          => gstore_get_pwa_scope_url(),
			'scopePath'         => gstore_get_pwa_scope_path(),
			'canShowInstallCta' => is_user_logged_in() && current_user_can( 'manage_options' ),
			'isAtendimentoPage' => is_page( 'atendimento' ),
			'atendimentoUrl'    => home_url( '/atendimento/' ),
			'myAccountUrl'      => $pwa_myaccount_url,
			'themeColor'        => gstore_get_pwa_theme_color(),
			'texts'             => array(
				'badge'       => __( 'Android App', 'gstore' ),
				'title'       => __( 'Instalar o site como aplicativo', 'gstore' ),
				'description' => __( 'Teste a versão instalada no Android para validar navegacao, atalhos e experiencia em modo app.', 'gstore' ),
				'button'      => __( 'Instalar app', 'gstore' ),
				'close'       => __( 'Fechar', 'gstore' ),
				'hint'        => __( 'O app abre na Home e mantem acesso normal a Atendimento e Minha Conta.', 'gstore' ),
			),
		)
	);

	// Autocomplete / Busca inteligente (produtos + categorias)
	$product_search_js_path    = get_theme_file_path( 'assets/js/product-search-autocomplete.js' );
	$product_search_js_version = file_exists( $product_search_js_path ) ? (string) filemtime( $product_search_js_path ) : wp_get_theme()->get( 'Version' );
	wp_enqueue_script(
		'gstore-product-search-autocomplete',
		get_theme_file_uri( 'assets/js/product-search-autocomplete.js' ),
		array(),
		$product_search_js_version,
		true
	);
	wp_localize_script(
		'gstore-product-search-autocomplete',
		'gstoreProductSearch',
		array(
			'endpoint'   => rest_url( 'gstore/v1/search-suggest' ),
			'catalogUrl' => gstore_get_catalog_url(),
			'minChars'   => 2,
			'limit'      => 8,
		)
	);

	// Botão flutuante do Telegram (espelha o href do link existente na top bar)
	$telegram_floating_js_path    = get_theme_file_path( 'assets/js/telegram-floating.js' );
	$telegram_floating_js_version = file_exists( $telegram_floating_js_path ) ? (string) filemtime( $telegram_floating_js_path ) : wp_get_theme()->get( 'Version' );
	wp_enqueue_script(
		'gstore-telegram-floating',
		get_theme_file_uri( 'assets/js/telegram-floating.js' ),
		array(),
		$telegram_floating_js_version,
		true
	);

	// Config do quick action de atendimento (tema) para integrar com bridge do plugin/Chatwoot.
	wp_localize_script(
		'gstore-telegram-floating',
		'gstoreSupportQuickAction',
		array(
			'storageKey'          => 'gstore_support_preference',
			'chatPreferenceValue' => 'chat_site',
			'uiStrategy'          => 'A',
			'texts'               => array(
				'openSupport'      => __( 'Atendimento', 'gstore' ),
				'openSupportShort' => __( 'Atendimento', 'gstore' ),
				'openChat'         => __( 'Abrir chat', 'gstore' ),
				'close'            => __( 'Fechar', 'gstore' ),
				'selectorEyebrow'  => __( 'Atendimento', 'gstore' ),
				'selectorTitle'    => __( 'Como prefere falar com a gente?', 'gstore' ),
				'selectorText'     => __( 'Escolha o canal. Se optar pelo chat do site, o botão passa a abrir o chat direto.', 'gstore' ),
				'chatTitle'        => __( 'Chat do site', 'gstore' ),
				'chatReadyDesc'    => __( 'Atendimento dentro do site (abre direto nas próximas vezes)', 'gstore' ),
				'chatLoadingDesc'  => __( 'Carregando chat...', 'gstore' ),
				'telegramTitle'    => __( 'Telegram', 'gstore' ),
				'telegramDesc'     => __( 'Abrir grupo oficial no Telegram', 'gstore' ),
				'chatEyebrow'      => __( 'Atendimento online', 'gstore' ),
				'chatModalTitle'   => __( 'Chat do site', 'gstore' ),
				'chatModalHint'    => __( 'Abrindo o chat do site. Se o widget estiver carregando, aguarde alguns segundos.', 'gstore' ),
				'changeChannel'    => __( 'Trocar canal', 'gstore' ),
			),
		)
	);

	// Passa URLs do tema para o JavaScript (respeitam subdiretório quando WP está em /subdir/)
	$gstore_account_urls = array(
		'homeUrl'        => home_url( '/' ),
		'atendimentoUrl' => home_url( '/atendimento' ),
		'favoritosUrl'   => home_url( '/favoritos/' ),
	);
	if ( class_exists( 'WooCommerce' ) ) {
		$myaccount_url = wc_get_page_permalink( 'myaccount' );
		$gstore_account_urls['myAccount'] = $myaccount_url ? $myaccount_url : home_url( '/minha-conta/' );
		$gstore_account_urls['orders']    = $myaccount_url ? wc_get_endpoint_url( 'orders', '', $myaccount_url ) : home_url( '/minha-conta/orders/' );
	}
	wp_localize_script(
		'gstore-header',
		'gstoreAccountUrls',
		$gstore_account_urls
	);

		if ( is_front_page() ) {
			$home_hero_js_path    = get_theme_file_path( 'assets/js/home-hero.js' );
			$home_hero_js_version = file_exists( $home_hero_js_path ) ? (string) filemtime( $home_hero_js_path ) : wp_get_theme()->get( 'Version' );
			wp_enqueue_script(
				'gstore-home-hero',
				get_theme_file_uri( 'assets/js/home-hero.js' ),
				array(),
				$home_hero_js_version,
				true
			);

			$home_benefits_js_path    = get_theme_file_path( 'assets/js/home-benefits.js' );
			$home_benefits_js_version = file_exists( $home_benefits_js_path ) ? (string) filemtime( $home_benefits_js_path ) : wp_get_theme()->get( 'Version' );
			wp_enqueue_script(
				'gstore-home-benefits',
				get_theme_file_uri( 'assets/js/home-benefits.js' ),
				array(),
				$home_benefits_js_version,
				true
			);

			wp_enqueue_script(
				'gstore-home-products-carousel',
				get_theme_file_uri( 'assets/js/home-products-carousel.js' ),
				array(),
				wp_get_theme()->get( 'Version' ),
				true
			);

			$home_blog_pagination_js_path    = get_theme_file_path( 'assets/js/home-blog-pagination.js' );
			$home_blog_pagination_js_version = file_exists( $home_blog_pagination_js_path ) ? (string) filemtime( $home_blog_pagination_js_path ) : wp_get_theme()->get( 'Version' );
			wp_enqueue_script(
				'gstore-home-blog-pagination',
				get_theme_file_uri( 'assets/js/home-blog-pagination.js' ),
				array(),
				$home_blog_pagination_js_version,
				true
			);
		}

	// Script para posts únicos do blog
	if ( is_single() && get_post_type() === 'post' ) {
		wp_enqueue_script(
			'gstore-blog-single',
			get_theme_file_uri( 'assets/js/blog-single.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);
	}

	// Script dos cards de produto
	if ( class_exists( 'WooCommerce' ) ) {
		// Core de Favoritos (conta + fallback localStorage)
		$favorites_core_js_path    = get_theme_file_path( 'assets/js/favorites-core.js' );
		$favorites_core_js_version = file_exists( $favorites_core_js_path ) ? (string) filemtime( $favorites_core_js_path ) : wp_get_theme()->get( 'Version' );
		wp_enqueue_script(
			'gstore-favorites-core',
			get_theme_file_uri( 'assets/js/favorites-core.js' ),
			array(),
			$favorites_core_js_version,
			true
		);

		$initial_favorite_ids = array();
		if ( is_user_logged_in() ) {
			$stored = get_user_meta( get_current_user_id(), 'gstore_favorites', true );
			if ( is_array( $stored ) ) {
				$seen = array();
				foreach ( $stored as $id ) {
					$id = absint( $id );
					if ( $id <= 0 ) {
						continue;
					}
					if ( isset( $seen[ $id ] ) ) {
						continue;
					}
					$seen[ $id ]          = true;
					$initial_favorite_ids[] = (string) $id;
				}
			}
		}

		wp_localize_script(
			'gstore-favorites-core',
			'gstoreFavoritesConfig',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'gstore_favorites' ),
				'isLoggedIn'  => is_user_logged_in(),
				'initialIds'  => $initial_favorite_ids,
				'catalogUrl'  => gstore_get_catalog_url(),
				'favoritesUrl'=> home_url( '/favoritos/' ),
			)
		);

		wp_enqueue_script(
			'gstore-product-card',
			get_theme_file_uri( 'assets/js/product-card.js' ),
			array( 'gstore-favorites-core' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = (int) get_queried_object_id();

			$single_product_js_path = get_theme_file_path( 'assets/js/single-product.js' );
			$single_product_js_version = file_exists( $single_product_js_path ) ? (string) filemtime( $single_product_js_path ) : wp_get_theme()->get( 'Version' );

			wp_enqueue_script(
				'gstore-single-product',
				get_theme_file_uri( 'assets/js/single-product.js' ),
				array( 'gstore-favorites-core' ),
				$single_product_js_version,
				true
			);

			wp_localize_script(
				'gstore-single-product',
				'gstoreSingleProductInstallments',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'action'    => 'gstore_blu_get_product_installment_quotes',
					'productId' => $product_id,
				)
			);

			$custom_notice = (string) get_post_meta( $product_id, '_gstore_custom_notice', true );
			$custom_notice = trim( $custom_notice );

			$product_notice_js_path = get_theme_file_path( 'assets/js/gstore-product-notice.js' );
			$product_notice_js_version = file_exists( $product_notice_js_path ) ? (string) filemtime( $product_notice_js_path ) : wp_get_theme()->get( 'Version' );

			wp_enqueue_script(
				'gstore-product-notice',
				get_theme_file_uri( 'assets/js/gstore-product-notice.js' ),
				array(),
				$product_notice_js_version,
				true
			);

			$product_notice_css_path = get_theme_file_path( 'assets/css/components/product-notice.css' );
			$product_notice_css_version = file_exists( $product_notice_css_path ) ? (string) filemtime( $product_notice_css_path ) : wp_get_theme()->get( 'Version' );

			wp_enqueue_style(
				'gstore-product-notice-css',
				get_theme_file_uri( 'assets/css/components/product-notice.css' ),
				array( 'gstore-main' ),
				$product_notice_css_version
			);

			wp_localize_script(
				'gstore-product-notice',
				'gstoreProductNotice',
				array(
					'active' => '' !== $custom_notice,
					'text'   => $custom_notice,
				)
			);
		}

		// Script da página de favoritos
		if ( function_exists( 'is_page' ) && is_page( 'favoritos' ) ) {
			$favorites_page_js_path    = get_theme_file_path( 'assets/js/favorites-page.js' );
			$favorites_page_js_version = file_exists( $favorites_page_js_path ) ? (string) filemtime( $favorites_page_js_path ) : wp_get_theme()->get( 'Version' );
			wp_enqueue_script(
				'gstore-favorites-page',
				get_theme_file_uri( 'assets/js/favorites-page.js' ),
				array( 'gstore-favorites-core' ),
				$favorites_page_js_version,
				true
			);
		}

		// Script da página informativo (pós-venda)
		if ( function_exists( 'is_page' ) && is_page( 'informativo' ) ) {
			$informativo_js_path    = get_theme_file_path( 'assets/js/informativo.js' );
			$informativo_js_version = file_exists( $informativo_js_path ) ? (string) filemtime( $informativo_js_path ) : wp_get_theme()->get( 'Version' );
			wp_enqueue_script(
				'gstore-informativo-js',
				get_theme_file_uri( 'assets/js/informativo.js' ),
				array(),
				$informativo_js_version,
				true
			);
			wp_localize_script(
				'gstore-informativo-js',
				'gstoreInformativoData',
				array(
					'airports' => gstore_get_informativo_airports_flat(),
				)
			);
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$cart_js_path    = get_theme_file_path( 'assets/js/cart.js' );
			$cart_js_version = file_exists( $cart_js_path ) ? (string) filemtime( $cart_js_path ) : wp_get_theme()->get( 'Version' );

			wp_enqueue_script(
				'gstore-cart',
				get_theme_file_uri( 'assets/js/cart.js' ),
				array( 'jquery' ),
				$cart_js_version,
				true
			);
			wp_localize_script(
				'gstore-cart',
				'gstoreCartData',
				array(
					'ajax_url'         => admin_url( 'admin-ajax.php' ),
					'mixed_cart_nonce' => wp_create_nonce( 'gstore_cart_token_group' ),
					'cart_url'         => wc_get_cart_url(),
					'checkout_url'     => wc_get_checkout_url(),
				)
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

			// Fulfillment timeline JS (apenas na página de detalhes do pedido).
			if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' ) ) {
				$view_order_id = absint( get_query_var( 'view-order' ) );
				if ( $view_order_id > 0 ) {
					wp_enqueue_script(
						'gstore-fulfillment-timeline',
						get_theme_file_uri( 'assets/js/fulfillment-timeline.js' ),
						array(),
						filemtime( get_theme_file_path( 'assets/js/fulfillment-timeline.js' ) ),
						true
					);
					wp_localize_script( 'gstore-fulfillment-timeline', 'gstoreFulfillment', array(
						'restUrl'      => esc_url_raw( rest_url( 'gstore/v1/' ) ),
						'nonce'        => wp_create_nonce( 'wp_rest' ),
						'orderId'      => $view_order_id,
						'maxFileSize'  => 10 * 1024 * 1024,
						'allowedTypes' => array( 'application/pdf', 'image/png', 'image/jpeg' ),
					) );
				}
			}
		}

		// Script da página de catálogo (filtros retráteis mobile)
		// Carrega se for qualquer página de catálogo ou se tiver a classe Gstore-catalog-shell
		$is_catalog_page = false;
		if ( function_exists( 'is_page' ) ) {
			// Páginas estáticas de catálogo
			$catalog_pages = array( 'catalogo', 'ofertas' );
			$catalog_templates = array( 'page-catalogo', 'page-ofertas' );

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
				(string) @filemtime( get_theme_file_path( 'assets/js/catalog-filters.js' ) ),
				true
			);
		}

	/*
	wp_enqueue_script(
		'gstore-catalog-categories-tree',
		get_theme_file_uri( 'assets/js/catalog-categories-tree.js' ),
		array(),
		(string) @filemtime( get_theme_file_path( 'assets/js/catalog-categories-tree.js' ) ),
		true
	);
	*/
	// #endregion

		// Script para sincronização do Mini Cart Block (versão simplificada)
		// Dependências: wc-settings (fornece storeApiNonce), wp-data (fornece wp.data store)

		// Script para gerenciar avisos do WooCommerce (slide-in e auto-dismiss)
		wp_enqueue_script(
			'gstore-notices',
			get_theme_file_uri( 'assets/js/notices.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);

		// Toast de Adicionar ao Carrinho (substitui link "Ver carrinho" por modal)
		wp_enqueue_script(
			'gstore-add-to-cart-toast',
			get_theme_file_uri( 'assets/js/add-to-cart-toast.js' ),
			array( 'jquery' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		// Ponte entre eventos jQuery do WooCommerce e o mini-cart block.
		// Necessário quando otimizações/cache impedem o bridge nativo do bloco.
		wp_enqueue_script(
			'gstore-mini-cart-block-bridge',
			get_theme_file_uri( 'assets/js/mini-cart-block-bridge.js' ),
			array( 'jquery' ),
			(string) @filemtime( get_theme_file_path( 'assets/js/mini-cart-block-bridge.js' ) ),
			true
		);

		// Interceptador de nonce expirado para a WC Store API (mini-cart drawer)
		wp_enqueue_script(
			'gstore-store-api-nonce-refresh',
			get_theme_file_uri( 'assets/js/store-api-nonce-refresh.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			false // No <head> para interceptar fetch antes do WC Blocks
		);

		wp_localize_script(
			'gstore-store-api-nonce-refresh',
			'gstoreNonceRefresh',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	// Localizar script para AJAX do WooCommerce
	if ( class_exists( 'WooCommerce' ) ) {
		wp_localize_script(
			'gstore-header',
			'gstore_wc',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'cart_url'         => wc_get_cart_url(),
				'cart_count'       => WC()->cart->get_cart_contents_count(),
				'mixed_cart'       => function_exists( 'gstore_blu_get_cart_token_groups' ) ? gstore_blu_get_cart_token_groups() : array( 'is_mixed' => false ),
				'mixed_cart_nonce' => wp_create_nonce( 'gstore_cart_token_group' ),
			)
		);

		// Intercepta botao checkout do minicart quando carrinho misto.
		wp_add_inline_script( 'gstore-header', '
			(function(){
				if(!window.gstore_wc||!window.gstore_wc.mixed_cart||!window.gstore_wc.mixed_cart.is_mixed)return;
				document.addEventListener("click",function(e){
					var btn=e.target.closest(".wc-block-mini-cart__footer-actions a, .wp-block-woocommerce-mini-cart-checkout-button-block");
					if(btn){e.preventDefault();e.stopPropagation();window.location.href=window.gstore_wc.cart_url;}
				},true);
			})();
		' );
	}
}
add_action( 'wp_enqueue_scripts', 'gstore_enqueue_scripts' );

/**
 * Redireciona checkout para carrinho quando há mistura de tokens Blu.
 */
add_action( 'template_redirect', function () {
	if (
		function_exists( 'is_checkout' ) && is_checkout()
		&& ! is_order_received_page()
		&& function_exists( 'gstore_blu_get_cart_token_groups' )
	) {
		$groups = gstore_blu_get_cart_token_groups();
		if ( ! empty( $groups['is_mixed'] ) ) {
			wp_safe_redirect( add_query_arg( 'mixed_cart', '1', wc_get_cart_url() ) );
			exit;
		}
	}
} );

/**
 * ============================================================
 * Proteção de sessão WooCommerce contra REST GET (LiteSpeed)
 *
 * O WC Blocks mini-cart faz GET para /wc/store/v1/cart ao carregar
 * cada página.  Sem proteção, o WooCommerce responde com Set-Cookie
 * contendo sessão vazia, sobrescrevendo a sessão real do cliente.
 *
 * As medidas abaixo:
 *  1. Detectam requisições REST GET (somente leitura).
 *  2. Bloqueiam wc_setcookie() durante essas requisições.
 *  3. Removem qualquer Set-Cookie que escape do filtro.
 *  4. Impedem que a sessão seja salva no banco (_dirty=false).
 * ============================================================
 */

/* --- PASSO 1: Detecção imediata de REST GET readonly --- */
if ( ! defined( 'GSTORE_READONLY_REQUEST' ) ) {
	$_gstore_is_rest_get = false;
	if (
		isset( $_SERVER['REQUEST_METHOD'] ) &&
		'GET' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) &&
		isset( $_SERVER['REQUEST_URI'] )
	) {
		$_gstore_uri = (string) $_SERVER['REQUEST_URI'];
		if (
			strpos( $_gstore_uri, '/wp-json/wc/store' ) !== false ||
			strpos( $_gstore_uri, 'rest_route=/wc/store' ) !== false
		) {
			$_gstore_is_rest_get = true;
		}
	}
	if ( $_gstore_is_rest_get ) {
		define( 'GSTORE_READONLY_REQUEST', true );
	}
	unset( $_gstore_is_rest_get, $_gstore_uri );
}

/* --- PASSO 2: Bloquear wc_setcookie() durante REST GET readonly --- */
add_filter( 'woocommerce_set_cookie_enabled', function ( $enabled ) {
	if ( defined( 'GSTORE_READONLY_REQUEST' ) && GSTORE_READONLY_REQUEST ) {
		return false;
	}
	return $enabled;
}, 10, 1 );

/* --- PASSO 3: Forçar path='/' nos cookies WC em requisições normais --- */
add_filter( 'woocommerce_set_cookie_options', function ( $options ) {
	if ( defined( 'GSTORE_READONLY_REQUEST' ) && GSTORE_READONLY_REQUEST ) {
		return $options; // Não altera em readonly (cookie será bloqueado mesmo).
	}
	if ( is_array( $options ) ) {
		$options['path'] = '/';
	}
	return $options;
}, 10, 1 );

/* --- PASSO 4: Nuclear – remove TODOS os Set-Cookie de respostas REST GET --- */
add_action( 'shutdown', function () {
	if ( ! defined( 'GSTORE_READONLY_REQUEST' ) || ! GSTORE_READONLY_REQUEST ) {
		return;
	}
	if ( ! headers_sent() ) {
		header_remove( 'Set-Cookie' );
	}
}, -10 );

/* --- PASSO 5: _dirty=false + remove save_data na sessão durante REST GET --- */
add_action( 'shutdown', function () {
	if ( ! defined( 'GSTORE_READONLY_REQUEST' ) || ! GSTORE_READONLY_REQUEST ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce' ) || ! WC()->session ) {
		return;
	}
	$handler = WC()->session;
	// Impede que a sessão vazia seja gravada no banco.
	if ( method_exists( $handler, 'save_data' ) ) {
		remove_action( 'shutdown', array( $handler, 'save_data' ), 20 );
	}
	// Força _dirty = false via Reflection (fallback).
	try {
		$ref = new ReflectionClass( $handler );
		if ( $ref->hasProperty( '_dirty' ) ) {
			$prop = $ref->getProperty( '_dirty' );
			$prop->setAccessible( true );
			$prop->setValue( $handler, false );
		}
	} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// Silently fail – o header_remove já garante proteção.
	}
}, 19 );

/* --- PASSO 6: Cache-Control na home quando há sessão ativa --- */
add_action( 'send_headers', function () {
	if ( defined( 'GSTORE_READONLY_REQUEST' ) && GSTORE_READONLY_REQUEST ) {
		return;
	}
	if ( ! is_front_page() && ! is_home() ) {
		return;
	}
	$has_session = false;
	foreach ( array_keys( $_COOKIE ) as $name ) {
		if ( strpos( $name, 'wp_woocommerce_session_' ) === 0 ) {
			$has_session = true;
			break;
		}
	}
	if ( $has_session && ! headers_sent() ) {
		header( 'Vary: Cookie', false );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	}
}, 1 );

/* --- PASSO 7: Forçar no-cache no LiteSpeed para WC Store API ---
 *
 * O LiteSpeed Cache cacheia respostas REST por até 7 dias por padrão.
 * Isso faz /wp-json/wc/store/v1/cart retornar um carrinho vazio em cache
 * para todos os usuários, mesmo quem tem itens.
 *
 * Solução em duas camadas:
 *  a) Hook oficial do plugin LiteSpeed Cache (litespeed_control_set_nocache)
 *  b) Header X-LiteSpeed-Cache-Control: no-cache lido diretamente pelo servidor
 */
add_action( 'rest_api_init', function () {
	add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
		$route = $request->get_route();
		if (
			strpos( $route, '/wc/store' ) !== false ||
			strpos( $route, '/wc/v' ) !== false
		) {
			// Hook do plugin LiteSpeed Cache – marca a resposta como não-cacheável.
			do_action( 'litespeed_control_set_nocache', 'WC Store API – no cache' );

			// Header direto para o servidor LiteSpeed (independente do plugin).
			if ( ! headers_sent() ) {
				header( 'X-LiteSpeed-Cache-Control: no-cache', true );
				header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
				header( 'Pragma: no-cache', true );
			}
		}
		return $result;
	}, 1, 3 );
}, 1 );


/**
 * ============================
 * Favoritos (Meus Favoritos)
 * ============================
 */

/**
 * Sanitiza uma lista de IDs.
 *
 * @param mixed $raw_ids
 * @return int[]
 */
function gstore_favorites_sanitize_ids( $raw_ids ) {
	if ( ! is_array( $raw_ids ) ) {
		return array();
	}

	$out  = array();
	$seen = array();
	foreach ( $raw_ids as $id ) {
		$id = absint( $id );
		if ( $id <= 0 ) {
			continue;
		}
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$out[]       = $id;
	}

	return $out;
}

/**
 * Toggle de favorito (somente logado).
 */
function gstore_ajax_favorites_toggle() {
	check_ajax_referer( 'gstore_favorites', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Você precisa estar logado para salvar favoritos.' ), 401 );
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( array( 'message' => 'WooCommerce não está ativo.' ), 400 );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $product_id <= 0 || 'product' !== get_post_type( $product_id ) ) {
		wp_send_json_error( array( 'message' => 'Produto inválido.' ), 400 );
	}

	$user_id = get_current_user_id();
	$stored  = get_user_meta( $user_id, 'gstore_favorites', true );
	$ids     = is_array( $stored ) ? gstore_favorites_sanitize_ids( $stored ) : array();

	$idx = array_search( $product_id, $ids, true );
	if ( false === $idx ) {
		$ids[] = $product_id;
	} else {
		array_splice( $ids, (int) $idx, 1 );
	}

	update_user_meta( $user_id, 'gstore_favorites', $ids );

	wp_send_json_success(
		array(
			'ids' => array_map( 'strval', $ids ),
		)
	);
}
add_action( 'wp_ajax_gstore_favorites_toggle', 'gstore_ajax_favorites_toggle' );

/**
 * Merge de favoritos local -> conta (somente logado).
 */
function gstore_ajax_favorites_merge() {
	check_ajax_referer( 'gstore_favorites', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Você precisa estar logado para sincronizar favoritos.' ), 401 );
	}

	$user_id = get_current_user_id();
	$stored  = get_user_meta( $user_id, 'gstore_favorites', true );
	$ids     = is_array( $stored ) ? gstore_favorites_sanitize_ids( $stored ) : array();

	$incoming = isset( $_POST['ids'] ) ? gstore_favorites_sanitize_ids( wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $incoming ) ) {
		$seen = array();
		foreach ( $ids as $id ) {
			$seen[ (int) $id ] = true;
		}
		foreach ( $incoming as $id ) {
			if ( isset( $seen[ (int) $id ] ) ) {
				continue;
			}
			$seen[ (int) $id ] = true;
			$ids[]             = (int) $id;
		}
		update_user_meta( $user_id, 'gstore_favorites', $ids );
	}

	wp_send_json_success(
		array(
			'ids' => array_map( 'strval', $ids ),
		)
	);
}
add_action( 'wp_ajax_gstore_favorites_merge', 'gstore_ajax_favorites_merge' );

/**
 * Renderiza lista de favoritos em HTML.
 * - Logado: se não vier ids, usa user meta.
 * - Deslogado: usa ids enviados.
 */
function gstore_ajax_favorites_render() {
	check_ajax_referer( 'gstore_favorites', 'nonce' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( array( 'message' => 'WooCommerce não está ativo.' ), 400 );
	}

	$ids = isset( $_POST['ids'] ) ? gstore_favorites_sanitize_ids( wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $ids ) && is_user_logged_in() ) {
		$stored = get_user_meta( get_current_user_id(), 'gstore_favorites', true );
		$ids    = is_array( $stored ) ? gstore_favorites_sanitize_ids( $stored ) : array();
	}

	if ( empty( $ids ) ) {
		wp_send_json_success(
			array(
				'html' => '',
				'ids'  => array(),
			)
		);
	}

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'posts_per_page'      => count( $ids ),
		'post__in'            => $ids,
		'orderby'             => 'post__in',
	);

	$q = new WP_Query( $args );

	ob_start();
	wc_set_loop_prop( 'columns', 3 );
	if ( $q->have_posts() ) {
		woocommerce_product_loop_start();
		while ( $q->have_posts() ) {
			$q->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();
	}
	wp_reset_postdata();
	$html = (string) ob_get_clean();

	wp_send_json_success(
		array(
			'html' => $html,
			'ids'  => array_map( 'strval', $ids ),
		)
	);
}
add_action( 'wp_ajax_gstore_favorites_render', 'gstore_ajax_favorites_render' );
add_action( 'wp_ajax_nopriv_gstore_favorites_render', 'gstore_ajax_favorites_render' );

/**
 * ============================
 * Avaliações de produto (WooCommerce)
 * ============================
 */

if ( ! function_exists( 'gstore_render_product_review' ) ) {
	/**
	 * Callback customizado para listar avaliações (reviews) do WooCommerce.
	 *
	 * @param WP_Comment $comment Comentário atual.
	 * @param array      $args    Argumentos do wp_list_comments.
	 * @param int        $depth   Profundidade.
	 */
	function gstore_render_product_review( $comment, $args, $depth ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$GLOBALS['comment'] = $comment;

		$rating        = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
		$rating_markup = $rating ? wc_get_rating_html( $rating ) : '';
		$is_verified   = wc_review_is_from_verified_owner( $comment->comment_ID );
		?>
		<article <?php comment_class( 'Gstore-review-item' ); ?> id="comment-<?php comment_ID(); ?>">
			<header class="Gstore-review-item__header">
				<div class="Gstore-review-item__author">
					<span class="Gstore-review-item__author-name"><?php comment_author(); ?></span>
					<span class="Gstore-review-item__meta">
						<?php echo esc_html( get_comment_date() ); ?>
						<?php if ( $is_verified ) : ?>
							<span aria-hidden="true">&#183;</span>
							<?php esc_html_e( 'Compra verificada', 'gstore' ); ?>
						<?php endif; ?>
					</span>
				</div>
				<div class="Gstore-review-item__rating">
					<?php if ( $rating_markup ) : ?>
						<span class="Gstore-review-stars" aria-hidden="true">
							<?php echo wp_kses_post( $rating_markup ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $rating ) : ?>
						<span class="Gstore-review-item__rating-text">
							<?php
							printf(
								/* translators: %s: numeric rating */
								esc_html__( '%s / 5', 'gstore' ),
								number_format_i18n( $rating, 1 )
							);
							?>
						</span>
					<?php endif; ?>
				</div>
			</header>
			<div class="Gstore-review-item__body">
				<?php if ( '0' === $comment->comment_approved ) : ?>
					<em><?php esc_html_e( 'Sua avaliação está aguardando moderação.', 'gstore' ); ?></em>
				<?php endif; ?>
				<?php comment_text(); ?>
			</div>
		</article>
		<?php
	}
}

/**
 * AJAX: carrega o restante das avaliações (após um offset).
 */
function gstore_ajax_load_product_reviews() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( array( 'message' => 'WooCommerce não está ativo.' ), 400 );
	}

	$product_id = isset( $_REQUEST['product_id'] ) ? absint( wp_unslash( $_REQUEST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$offset     = isset( $_REQUEST['offset'] ) ? absint( wp_unslash( $_REQUEST['offset'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$nonce      = isset( $_REQUEST['nonce'] ) ? (string) wp_unslash( $_REQUEST['nonce'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( $product_id <= 0 ) {
		wp_send_json_error( array( 'message' => 'Produto inválido.' ), 400 );
	}

	if ( ! wp_verify_nonce( $nonce, 'gstore_load_reviews_' . $product_id ) ) {
		wp_send_json_error( array( 'message' => 'Nonce inválido.' ), 403 );
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		wp_send_json_error( array( 'message' => 'Produto não encontrado.' ), 404 );
	}

	$comments = get_comments(
		array(
			'post_id' => $product_id,
			'type'    => 'review',
			'status'  => 'approve',
			'offset'  => $offset,
			'number'  => 0,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		)
	);

	ob_start();
	if ( is_array( $comments ) && ! empty( $comments ) ) {
		wp_list_comments(
			apply_filters(
				'woocommerce_product_review_list_args',
				array(
					'callback'     => 'gstore_render_product_review',
					'end-callback' => '__return_null',
					'style'        => 'div',
					'short_ping'   => true,
				)
			),
			$comments
		);
	}
	$html = (string) ob_get_clean();

	wp_send_json_success(
		array(
			'html'  => $html,
			'count' => is_array( $comments ) ? count( $comments ) : 0,
		)
	);
}
add_action( 'wp_ajax_gstore_load_product_reviews', 'gstore_ajax_load_product_reviews' );
add_action( 'wp_ajax_nopriv_gstore_load_product_reviews', 'gstore_ajax_load_product_reviews' );

/**
 * Atualiza o fragmento do carrinho para refletir mudanças em tempo real.
 *
 * @param array $fragments Fragmentos de carrinho.
 * @return array
 */

/**
 * Garante que o AJAX add to cart está habilitado e configurado corretamente.
 *
 * Por padrão, o WooCommerce já habilita AJAX, mas esta função garante
 * que não foi desabilitado por outros plugins ou configurações.
 */
function gstore_ensure_ajax_add_to_cart_enabled() {
	// Garante que o AJAX add to cart está habilitado
	if ( class_exists( 'WooCommerce' ) ) {
		// O WooCommerce já habilita AJAX por padrão via get_option('woocommerce_enable_ajax_add_to_cart')
		// Mas vamos garantir que está ativo
		if ( 'yes' !== get_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' ) ) {
			update_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' );
		}
	}
}
add_action( 'init', 'gstore_ensure_ajax_add_to_cart_enabled', 5 );

/**
 * Remove os avisos "Escolha as opções de produtos visitando…" de produtos variáveis.
 *
 * O WooCommerce gera esse aviso de erro quando o add-to-cart é acionado para um
 * produto variável sem que as variações tenham sido selecionadas (GET request com
 * ?add-to-cart=ID). Os avisos ficam na sessão e acabam aparecendo em diversas
 * páginas, causando confusão para o usuário.
 *
 * Esta função filtra os notices de erro do WooCommerce e remove apenas os que
 * correspondem a esse padrão específico, preservando todos os outros avisos.
 */
function gstore_suppress_variable_product_choose_options_notice() {
	if ( ! function_exists( 'wc_get_notices' ) || ! function_exists( 'wc_clear_notices' ) ) {
		return;
	}

	$error_notices = wc_get_notices( 'error' );

	if ( empty( $error_notices ) ) {
		return;
	}

	$had_match    = false;
	$keep_notices = array();

	foreach ( $error_notices as $notice ) {
		$text = is_array( $notice ) ? ( $notice['notice'] ?? '' ) : (string) $notice;

		// Detecta o aviso "Escolha as opções de produtos visitando" (PT-BR)
		// e "Please choose product options by visiting" (EN) do WooCommerce core.
		if (
			false !== stripos( $text, 'escolha as' )
			|| false !== stripos( $text, 'choose product options' )
		) {
			$had_match = true;
			continue; // Remove este aviso.
		}

		$keep_notices[] = $notice;
	}

	if ( ! $had_match ) {
		return;
	}

	// Limpa os avisos de erro e re-adiciona apenas os que devem permanecer.
	wc_clear_notices( 'error' );

	foreach ( $keep_notices as $notice ) {
		if ( is_array( $notice ) ) {
			wc_add_notice( $notice['notice'] ?? '', 'error', $notice['data'] ?? array() );
		} else {
			wc_add_notice( (string) $notice, 'error' );
		}
	}
}
// Executa cedo em vários pontos para garantir que o aviso nunca chegue ao frontend.
add_action( 'wp', 'gstore_suppress_variable_product_choose_options_notice', 1 );
add_action( 'woocommerce_before_shop_loop', 'gstore_suppress_variable_product_choose_options_notice', 1 );
add_action( 'woocommerce_before_single_product', 'gstore_suppress_variable_product_choose_options_notice', 1 );
add_action( 'woocommerce_before_cart', 'gstore_suppress_variable_product_choose_options_notice', 1 );
add_action( 'woocommerce_before_checkout_form', 'gstore_suppress_variable_product_choose_options_notice', 1 );

/**
 * Em listas de produtos, itens vari�veis devem abrir a p�gina de produto.
 *
 * Isso evita tentativas de add-to-cart sem atributos selecionados no cat�logo.
 *
 * @param string     $html    HTML do bot�o.
 * @param WC_Product $product Produto do loop.
 * @param array      $args    Argumentos do bot�o.
 * @return string
 */
function gstore_loop_variable_product_link( $html, $product, $args ) {
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return $html;
	}

	$classes_raw = isset( $args['class'] ) ? (string) $args['class'] : 'button';
	$classes     = preg_split( '/\\s+/', trim( $classes_raw ) );
	if ( ! is_array( $classes ) ) {
		$classes = array( 'button' );
	}

	$blocked_classes = array( 'add_to_cart_button', 'ajax_add_to_cart' );
	$classes         = array_values( array_diff( $classes, $blocked_classes ) );
	if ( empty( $classes ) ) {
		$classes = array( 'button' );
	}

	$label = isset( $args['text'] ) ? trim( (string) $args['text'] ) : '';
	if ( '' === $label ) {
		$label = __( "Ver op\xC3\xA7\xC3\xB5es", 'gstore' );
	}

	return sprintf(
		'<a href="%1$s" data-quantity="1" class="%2$s">%3$s</a>',
		esc_url( $product->get_permalink() ),
		esc_attr( implode( ' ', $classes ) ),
		esc_html( $label )
	);
}
add_filter( 'woocommerce_loop_add_to_cart_link', 'gstore_loop_variable_product_link', 10, 3 );

/**
 * Mantem AJAX de adicionar ao carrinho, mas nao expõe ?add-to-cart=ID como href rastreavel.
 *
 * @param string     $html    HTML do botao.
 * @param WC_Product $product Produto do loop.
 * @param array      $args    Argumentos do botao.
 * @return string
 */
function gstore_loop_public_add_to_cart_permalink( $html, $product, $args ) {
	if ( ! $product instanceof WC_Product || $product->is_type( 'variable' ) ) {
		return $html;
	}

	if ( false === strpos( (string) $html, 'add-to-cart=' ) ) {
		return $html;
	}

	$permalink = $product->get_permalink();
	if ( ! $permalink ) {
		return $html;
	}

	$permalink = esc_url( $permalink );

	$updated = preg_replace_callback(
		'/(<a\b[^>]*\bhref=)(["\']).*?\2/i',
		static function( $matches ) use ( $permalink ) {
			return $matches[1] . $matches[2] . $permalink . $matches[2];
		},
		(string) $html,
		1
	);

	return is_string( $updated ) ? $updated : $html;
}
add_filter( 'woocommerce_loop_add_to_cart_link', 'gstore_loop_public_add_to_cart_permalink', 20, 3 );

/**
 * Garante que os eventos WooCommerce sejam disparados corretamente.
 *
 * Adiciona suporte adicional para garantir que o evento added_to_cart
 * seja sempre disparado, mesmo em casos edge.
 */
function gstore_filter_add_to_cart_redirect( $url ) {
	// Mantém o redirect padrão no produto único
	if ( function_exists( 'is_product' ) && is_product() ) {
		return $url;
	}

	// Em chamadas AJAX, não redireciona para não quebrar a resposta
	if ( wp_doing_ajax() || isset( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return false;
	}

	// Para páginas de catálogo e afins, evita redirect após add-to-cart
	return false;
}

function gstore_ensure_cart_events() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Evita redirect em contextos que devem permanecer na mesma página
	add_filter( 'woocommerce_add_to_cart_redirect', 'gstore_filter_add_to_cart_redirect', 5 );
}
add_action( 'init', 'gstore_ensure_cart_events', 10 );


/**
 * "Comprar agora" (produto único): redireciona para o checkout após adicionar ao carrinho.
 *
 * Implementado via botão submit no template do produto único (name="gstore_buy_now").
 */
function gstore_buy_now_redirect_to_checkout( $url ) {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_checkout_url' ) ) {
		return $url;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_REQUEST['gstore_buy_now'] ) ) {
		return wc_get_checkout_url();
	}

	return $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'gstore_buy_now_redirect_to_checkout', 20 );

/**
 * "Comprar agora" (produto simples): adiciona ao carrinho respeitando quantidade e redireciona ao checkout.
 *
 * Motivo: o botão customizado `name="gstore_buy_now"` pode submeter o form sem disparar o handler nativo
 * de add-to-cart (que normalmente depende de `add-to-cart` vindo do botão padrão). Aqui tratamos no backend
 * para garantir o fluxo e evitar o aviso de reenvio de formulário ao recarregar a página.
 */
function gstore_handle_buy_now_simple_product() {
	if ( wp_doing_ajax() ) {
		return;
	}

	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_checkout_url' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['gstore_buy_now'] ) ) {
		return;
	}

	// Se algum plugin/tema imprimiu warning/deprecated em output buffer, limpamos para não quebrar o redirect.
	if ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) ) {
		while ( ob_get_level() ) {
			@ob_end_clean(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	// Tenta pegar o ID do produto a partir do contexto da página.
	$product_id = 0;
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product_id = (int) get_queried_object_id();
	}
	// Fallbacks comuns
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $product_id && isset( $_REQUEST['product_id'] ) ) {
		$product_id = (int) $_REQUEST['product_id'];
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $product_id && isset( $_REQUEST['add-to-cart'] ) ) {
		$product_id = (int) $_REQUEST['add-to-cart'];
	}

	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return;
	}

	if ( ! function_exists( 'wc_get_product' ) ) {
		return;
	}

	$product = wc_get_product( $product_id );
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	// Só cobre produto simples aqui (o seu caso). Variáveis precisam de variation_id/atributos.
	if ( ! $product->is_type( 'simple' ) ) {
		return;
	}

	// Quantidade
	$qty = 1;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_REQUEST['quantity'] ) ) {
		$qty_raw = wp_unslash( $_REQUEST['quantity'] );
		$qty     = function_exists( 'wc_stock_amount' ) ? wc_stock_amount( $qty_raw ) : (int) $qty_raw;
	}
	$qty = max( 1, (int) $qty );

	/**
	 * Fluxo robusto (igual experiência do variável):
	 * 1) Se veio via POST, fazemos PRG para uma URL GET com add-to-cart + quantity.
	 *    Assim o WooCommerce processa o add-to-cart no hook nativo dele (wp_loaded) e aplica nosso
	 *    redirect para checkout via `woocommerce_add_to_cart_redirect` quando `gstore_buy_now` estiver presente.
	 * 2) Se já estivermos no GET (por ex. depois do PRG), mandamos direto para o checkout.
	 */
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';

	if ( 'POST' === $method ) {
		$target = add_query_arg(
			array(
				'add-to-cart'    => $product_id,
				'quantity'       => $qty,
				'gstore_buy_now' => 1,
			),
			get_permalink( $product_id )
		);

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		wp_safe_redirect( $target );
		exit;
	}

	if ( function_exists( 'nocache_headers' ) ) {
		nocache_headers();
	}
	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}
add_action( 'template_redirect', 'gstore_handle_buy_now_simple_product', 0 );

/**
 * "Comprar agora" (produto variável): PRG para GET com variation_id e atributos,
 * para o WooCommerce processar add-to-cart e o filtro redirecionar ao checkout.
 */
function gstore_handle_buy_now_variable_product() {
	if ( wp_doing_ajax() ) {
		return;
	}

	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_checkout_url' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['gstore_buy_now'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$product_id = isset( $_REQUEST['add-to-cart'] ) ? absint( $_REQUEST['add-to-cart'] ) : 0;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $product_id && isset( $_REQUEST['product_id'] ) ) {
		$product_id = absint( $_REQUEST['product_id'] );
	}
	if ( ! $product_id ) {
		return;
	}

	if ( ! function_exists( 'wc_get_product' ) ) {
		return;
	}

	$product = wc_get_product( $product_id );
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( $_REQUEST['variation_id'] ) : 0;
	if ( ! $variation_id ) {
		return;
	}

	// Se algum plugin/tema imprimiu warning/deprecated em output buffer, limpamos para não quebrar o redirect.
	if ( function_exists( 'ob_get_level' ) && function_exists( 'ob_end_clean' ) ) {
		while ( ob_get_level() ) {
			@ob_end_clean(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';

	if ( 'POST' === $method ) {
		// Se veio via POST, faz PRG para GET com todos os parâmetros de variação.
		$args = array(
			'add-to-cart'    => $product_id,
			'variation_id'   => $variation_id,
			'quantity'       => isset( $_REQUEST['quantity'] ) ? max( 1, absint( $_REQUEST['quantity'] ) ) : 1, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'gstore_buy_now' => 1,
		);
		foreach ( array_keys( $_REQUEST ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( strpos( $key, 'attribute_' ) === 0 ) {
				$args[ $key ] = wp_unslash( $_REQUEST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		$target = add_query_arg( $args, get_permalink( $product_id ) );

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		wp_safe_redirect( $target );
		exit;
	}

	// GET: o WooCommerce já processou o add-to-cart no wp_loaded.
	// Redireciona explicitamente ao checkout (igual ao produto simples).
	// Não depende de woocommerce_add_to_cart_redirect que pode não disparar
	// para variáveis em algumas versões do WooCommerce.
	if ( function_exists( 'nocache_headers' ) ) {
		nocache_headers();
	}
	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}
add_action( 'template_redirect', 'gstore_handle_buy_now_variable_product', 0 );

/**
 * PRG no produto único: evita reenvio do formulário ao voltar.
 */
function gstore_flag_single_product_add_to_cart_prg( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
	$GLOBALS['gstore_single_product_added_to_cart'] = true;
}
add_action( 'woocommerce_add_to_cart', 'gstore_flag_single_product_add_to_cart_prg', 10, 6 );

function gstore_prg_single_product_add_to_cart() {
	if ( wp_doing_ajax() || isset( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	// Não interfere com "Comprar agora".
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_REQUEST['gstore_buy_now'] ) ) {
		return;
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
	if ( 'POST' !== $method ) {
		return;
	}

	if ( empty( $GLOBALS['gstore_single_product_added_to_cart'] ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( ! $request_uri ) {
		return;
	}

	// Remove o prefixo do subdiretório (se houver) para evitar duplicação com home_url().
	// Ex.: REQUEST_URI = "/loja/produto/..." e home_url() = "https://dominio.com/loja"
	// sem esta correção, home_url("/loja/produto/...") geraria "/loja/loja/produto/...".
	$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
	if ( $home_path && '/' !== $home_path && 0 === strpos( $request_uri, $home_path ) ) {
		$request_uri = substr( $request_uri, strlen( $home_path ) );
	}

	$current_url = home_url( $request_uri );
	$target      = remove_query_arg( array( 'add-to-cart', 'quantity', 'gstore_buy_now' ), $current_url );

	if ( function_exists( 'nocache_headers' ) ) {
		nocache_headers();
	}

	wp_safe_redirect( $target );
	exit;
}
add_action( 'template_redirect', 'gstore_prg_single_product_add_to_cart', 9 );

/**
 * Garante que `add-to-cart` esteja em $_REQUEST quando "Comprar agora" é usado.
 *
 * Quando o usuário clica no botão "Comprar agora" (name="gstore_buy_now"), apenas
 * esse name/value é incluído no POST. O campo `add-to-cart` (do botão "Adicionar ao
 * carrinho") NÃO é enviado. Sem ele, o WooCommerce não processa o add-to-cart.
 *
 * Este handler roda em wp_loaded priority 0 (antes de tudo) e injeta `add-to-cart`
 * a partir de `product_id`, garantindo que o fluxo do WooCommerce funcione
 * independentemente de o JavaScript ter interceptado o clique ou não.
 */
function gstore_ensure_add_to_cart_for_buy_now() {
	if ( wp_doing_ajax() || isset( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	// Só age quando "Comprar agora" está presente MAS `add-to-cart` está ausente.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['gstore_buy_now'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_REQUEST['add-to-cart'] ) && is_numeric( $_REQUEST['add-to-cart'] ) ) {
		return; // Já existe — provavelmente veio do JS redirect (GET). Nada a fazer.
	}

	// Tenta obter o product_id a partir dos campos do formulário.
	$product_id = 0;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_REQUEST['product_id'] ) ) {
		$product_id = absint( $_REQUEST['product_id'] );
	}
	if ( ! $product_id ) {
		return;
	}

	// Injeta add-to-cart para que o WooCommerce processe o produto.
	$_REQUEST['add-to-cart'] = $product_id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$_GET['add-to-cart']     = $product_id;
	$_POST['add-to-cart']    = $product_id;
}
add_action( 'wp_loaded', 'gstore_ensure_add_to_cart_for_buy_now', 0 );

/**
 * Salva o carrinho atual em sessão antes de limpar para "Comprar agora"
 *
 * Permite restaurar os itens se o cliente voltar ou cancelar a compra rápida.
 */
function gstore_save_cart_before_buy_now() {
	// Só executa se não for AJAX
	if ( wp_doing_ajax() || isset( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	// Só executa se gstore_buy_now estiver presente
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['gstore_buy_now'] ) ) {
		return;
	}

	// Só executa se add-to-cart estiver presente (indica que vai adicionar produto)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['add-to-cart'] ) ) {
		return;
	}

	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return;
	}

	// A próxima abertura do checkout via "Comprar agora" deve começar limpa na etapa 1.
	WC()->session->set( 'gstore_buy_now_checkout_reset', true );
	WC()->session->set( 'gstore_checkout_step', 0 );

	// Só salva se o carrinho não estiver vazio
	if ( WC()->cart->is_empty() ) {
		return;
	}

	// Salva o carrinho atual na sessão do WooCommerce (apenas dados essenciais, sem objetos)
	$cart_contents = WC()->cart->get_cart();
	if ( ! empty( $cart_contents ) ) {
		$saved_cart_data = array();
		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			$saved_cart_data[ $cart_item_key ] = array(
				'product_id'   => isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0,
				'quantity'     => isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1,
				'variation_id' => isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0,
				'variation'    => isset( $cart_item['variation'] ) ? $cart_item['variation'] : array(),
			);

			// Preserva metadados customizados se existirem
			if ( isset( $cart_item['gstore_shipping_rates'] ) ) {
				$saved_cart_data[ $cart_item_key ]['gstore_shipping_rates'] = $cart_item['gstore_shipping_rates'];
			}
			if ( isset( $cart_item['gstore_shipping_mode'] ) ) {
				$saved_cart_data[ $cart_item_key ]['gstore_shipping_mode'] = $cart_item['gstore_shipping_mode'];
			}
		}

		WC()->session->set( 'gstore_saved_cart_before_buy_now', $saved_cart_data );
		WC()->session->set( 'gstore_buy_now_active', true );
	}

	// Salva informações do produto que será adicionado via "Comprar agora"
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$buy_now_product_id = isset( $_REQUEST['add-to-cart'] ) ? absint( $_REQUEST['add-to-cart'] ) : 0;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$buy_now_quantity = isset( $_REQUEST['quantity'] ) ? absint( $_REQUEST['quantity'] ) : 1;
	$buy_now_quantity = max( 1, $buy_now_quantity );

	// Para produtos variáveis, tenta pegar variation_id e atributos
	$buy_now_variation_id = 0;
	$buy_now_variation = array();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_REQUEST['variation_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$buy_now_variation_id = absint( $_REQUEST['variation_id'] );
	}

	// Coleta atributos de variação se existirem
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_REQUEST ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( $_REQUEST as $key => $value ) {
			if ( strpos( $key, 'attribute_' ) === 0 ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$buy_now_variation[ $key ] = wp_unslash( $value );
			}
		}
	}

	if ( $buy_now_product_id > 0 ) {
		$buy_now_product_data = array(
			'product_id'   => $buy_now_product_id,
			'quantity'     => $buy_now_quantity,
			'variation_id' => $buy_now_variation_id,
			'variation'    => $buy_now_variation,
		);
		WC()->session->set( 'gstore_buy_now_product', $buy_now_product_data );
	}
}
add_action( 'wp_loaded', 'gstore_save_cart_before_buy_now', 1 );

/**
 * Limpa o carrinho antes de adicionar produto via "Comprar agora"
 *
 * Executa após salvar o carrinho, antes do WooCommerce processar o add-to-cart.
 */
function gstore_clear_cart_before_buy_now() {
	// Só executa se não for AJAX
	if ( wp_doing_ajax() || isset( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	// Só executa se gstore_buy_now estiver presente
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['gstore_buy_now'] ) ) {
		return;
	}

	// Só executa se add-to-cart estiver presente
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['add-to-cart'] ) ) {
		return;
	}

	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return;
	}

	// Limpa o carrinho antes de adicionar o novo produto
	WC()->cart->empty_cart();
}
add_action( 'wp_loaded', 'gstore_clear_cart_before_buy_now', 5 );

/**
 * Restaura o carrinho salvo se o cliente voltar do checkout
 *
 * Executa quando o cliente acessa qualquer página após ter usado "Comprar agora".
 */
function gstore_restore_saved_cart_if_needed() {
	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return;
	}

	// Não restaura se o cliente estiver no checkout (ainda está finalizando a compra)
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return;
	}

	// Verifica se há carrinho salvo e se "Comprar agora" estava ativo
	$saved_cart = WC()->session->get( 'gstore_saved_cart_before_buy_now' );
	$buy_now_active = WC()->session->get( 'gstore_buy_now_active' );

	// Se não há carrinho salvo ou "Comprar agora" não está mais ativo, não faz nada
	if ( empty( $saved_cart ) || ! $buy_now_active ) {
		return;
	}

	// Se o carrinho atual não está vazio, substitui pelo carrinho salvo
	if ( ! WC()->cart->is_empty() ) {
		WC()->cart->empty_cart();
	}

	// Restaura o carrinho salvo
	foreach ( $saved_cart as $cart_item_data ) {
		$product_id = isset( $cart_item_data['product_id'] ) ? $cart_item_data['product_id'] : 0;
		$quantity = isset( $cart_item_data['quantity'] ) ? $cart_item_data['quantity'] : 1;
		$variation_id = isset( $cart_item_data['variation_id'] ) ? $cart_item_data['variation_id'] : 0;
		$variation = isset( $cart_item_data['variation'] ) ? $cart_item_data['variation'] : array();

		if ( ! $product_id ) {
			continue;
		}

		$item_data = array();

		// Preserva metadados customizados se existirem
		if ( isset( $cart_item_data['gstore_shipping_rates'] ) ) {
			$item_data['gstore_shipping_rates'] = $cart_item_data['gstore_shipping_rates'];
		}
		if ( isset( $cart_item_data['gstore_shipping_mode'] ) ) {
			$item_data['gstore_shipping_mode'] = $cart_item_data['gstore_shipping_mode'];
		}

		WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $item_data );
	}

	// Limpa a sessão após restaurar
	WC()->session->set( 'gstore_saved_cart_before_buy_now', null );
	WC()->session->set( 'gstore_buy_now_active', false );
	WC()->session->set( 'gstore_buy_now_product', null );
	WC()->session->set( 'gstore_buy_now_checkout_reset', false );
}
add_action( 'template_redirect', 'gstore_restore_saved_cart_if_needed', 1 );

/**
 * Limpa a flag de "Comprar agora" após finalizar o pedido
 *
 * Apenas limpa as flags de sessão. O pedido já foi concluído e o carrinho
 * já foi esvaziado pelo WooCommerce. Para Blu (modal): mantém o mesmo comportamento.
 *
 * @param int $order_id ID do pedido (passado por woocommerce_thankyou / woocommerce_checkout_order_processed).
 */
function gstore_clear_buy_now_flag_after_order( $order_id = 0 ) {
	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return;
	}

	$order = $order_id ? wc_get_order( $order_id ) : null;
	if ( $order && $order->get_payment_method() === 'blu_checkout' ) {
		WC()->session->set( 'gstore_saved_cart_before_buy_now', null );
		WC()->session->set( 'gstore_buy_now_active', false );
		WC()->session->set( 'gstore_buy_now_product', null );
		WC()->session->set( 'gstore_buy_now_checkout_reset', false );
		return;
	}

	WC()->session->set( 'gstore_saved_cart_before_buy_now', null );
	WC()->session->set( 'gstore_buy_now_active', false );
	WC()->session->set( 'gstore_buy_now_product', null );
	WC()->session->set( 'gstore_buy_now_checkout_reset', false );
}
add_action( 'woocommerce_thankyou', 'gstore_clear_buy_now_flag_after_order', 10 );
add_action( 'woocommerce_checkout_order_processed', 'gstore_clear_buy_now_flag_after_order', 10 );

/**
 * Consome a flag de entrada do checkout via "Comprar agora".
 *
 * A flag é de uso único: na primeira carga do checkout, o frontend recebe a instrução
 * para ignorar rascunhos/retomadas antigas e recomeçar da etapa 1.
 *
 * @return bool
 */
function gstore_consume_buy_now_checkout_reset_flag() {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) || ! WC()->session ) {
		return false;
	}

	$should_reset = (bool) WC()->session->get( 'gstore_buy_now_checkout_reset', false );
	if ( $should_reset ) {
		WC()->session->set( 'gstore_buy_now_checkout_reset', false );
		WC()->session->set( 'gstore_checkout_step', 0 );
	}

	return $should_reset;
}

/**
 * Adiciona headers HTTP para evitar cache em requisições AJAX do carrinho.
 *
 * Isso é crítico em ambientes de produção onde cache pode causar
 * problemas de sincronização entre o carrinho e o mini-cart.
 */
function gstore_prevent_cart_ajax_cache() {
	// Só adiciona headers em requisições AJAX relacionadas ao carrinho
	if ( ! wp_doing_ajax() && ! isset( $_REQUEST['wc-ajax'] ) ) {
		return;
	}

	$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
	$wc_ajax = isset( $_REQUEST['wc-ajax'] ) ? $_REQUEST['wc-ajax'] : '';
	$is_cart_action = (
		strpos( $action, 'cart' ) !== false ||
		strpos( $action, 'woocommerce' ) !== false ||
		strpos( $wc_ajax, 'cart' ) !== false ||
		strpos( $wc_ajax, 'remove' ) !== false ||
		strpos( $wc_ajax, 'update' ) !== false ||
		isset( $_REQUEST['wc-ajax'] )
	);

	if ( $is_cart_action && ! headers_sent() ) {
		// Headers para evitar cache em requisições AJAX do carrinho
		// Crítico em produção com CDN/cache
		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-Accel-Buffering: no' ); // Nginx buffering
		header( 'Vary: Cookie' ); // Garante que cache varia por cookie/sessão

		// Garante que sessões sejam mantidas
		// Força o uso de cookies para sessões
		ini_set( 'session.use_cookies', '1' );
		ini_set( 'session.use_only_cookies', '1' );

		// Adiciona header para evitar cache em proxies/CDN
		header( 'X-Cache-Control: no-cache' );
	}
}
add_action( 'wp_ajax_woocommerce_add_to_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_nopriv_woocommerce_add_to_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_woocommerce_remove_from_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_nopriv_woocommerce_remove_from_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_woocommerce_update_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_nopriv_woocommerce_update_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wc_ajax_add_to_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wc_ajax_remove_from_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wc_ajax_update_cart', 'gstore_prevent_cart_ajax_cache', 1 );

/**
 * Endpoint AJAX para renovar o nonce da WC Store API.
 *
 * Quando o nonce expira (comum com LiteSpeed Cache / sessão longa),
 * o JS interceptador chama este endpoint para obter um nonce fresco
 * e repetir a requisição que falhou.
 */
function gstore_refresh_store_api_nonce() {
	$nonce = wp_create_nonce( 'wc_store_api' );
	wp_send_json_success( array( 'nonce' => $nonce ) );
}
add_action( 'wp_ajax_gstore_refresh_store_api_nonce', 'gstore_refresh_store_api_nonce' );
add_action( 'wp_ajax_nopriv_gstore_refresh_store_api_nonce', 'gstore_refresh_store_api_nonce' );

/**
 * Garante que fragmentos sejam sempre retornados após remoção de item.
 *
 * Hook específico para wc_ajax_remove_from_cart para garantir que fragmentos
 * sejam sempre incluídos na resposta, mesmo em ambientes com cache ou problemas de timing.
 */
/**
 * Remove o breadcrumb padrão do WooCommerce.
 */
function gstore_remove_default_breadcrumb() {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}
add_action( 'init', 'gstore_remove_default_breadcrumb' );

/**
 * Normaliza um path interno do site para comparacao.
 *
 * @param string $url URL absoluta ou relativa.
 * @return string
 */
function gstore_normalize_internal_site_path( $url ) {
	$url = is_string( $url ) ? trim( $url ) : '';
	if ( '' === $url ) {
		return '';
	}

	$parts = wp_parse_url( $url );
	if ( false === $parts ) {
		return '';
	}

	if ( ! empty( $parts['scheme'] ) ) {
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}
	}

	$home_parts = wp_parse_url( home_url( '/' ) );
	$home_host  = isset( $home_parts['host'] ) ? strtolower( (string) $home_parts['host'] ) : '';
	$home_port  = isset( $home_parts['port'] ) ? (int) $home_parts['port'] : 0;

	if ( ! empty( $parts['host'] ) ) {
		$url_host = strtolower( (string) $parts['host'] );
		$url_port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;

		if ( '' !== $home_host && $url_host !== $home_host ) {
			return '';
		}

		if ( $home_port > 0 && $url_port > 0 && $url_port !== $home_port ) {
			return '';
		}
	}

	$path = isset( $parts['path'] ) ? rawurldecode( (string) $parts['path'] ) : '/';
	if ( '' === $path ) {
		$path = '/';
	}

	if ( '/' !== $path ) {
		$path = '/' . ltrim( $path, '/' );
		$path = untrailingslashit( $path ) . '/';
	}

	return $path;
}

/**
 * Extrai o slug de rotas raiz internas no formato "/{slug}/".
 *
 * @param string $url URL do item de menu.
 * @return string
 */
function gstore_get_internal_root_slug_from_url( $url ) {
	$parts = wp_parse_url( $url );
	if ( false === $parts ) {
		return '';
	}

	if ( ! empty( $parts['query'] ) || ! empty( $parts['fragment'] ) ) {
		return '';
	}

	$path = gstore_normalize_internal_site_path( $url );
	if ( '' === $path ) {
		return '';
	}

	$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$home_path = is_string( $home_path ) && '' !== $home_path ? $home_path : '/';
	$home_path = '/' === $home_path ? '/' : untrailingslashit( '/' . ltrim( $home_path, '/' ) ) . '/';

	$relative_path = $path;
	if ( '/' !== $home_path ) {
		if ( 0 !== strpos( $path, $home_path ) ) {
			return '';
		}
		$relative_path = '/' . ltrim( substr( $path, strlen( $home_path ) ), '/' );
	}

	$relative_path = trim( $relative_path, '/' );
	if ( '' === $relative_path || false !== strpos( $relative_path, '/' ) ) {
		return '';
	}

	return sanitize_title( rawurldecode( $relative_path ) );
}

/**
 * Retorna o mapa de categorias expostas no menu do header.
 *
 * @return array<string, string>
 */
function gstore_get_header_menu_category_route_map() {
	static $route_map = null;

	if ( null !== $route_map ) {
		return $route_map;
	}

	$route_map      = array();
	$menu_locations = get_nav_menu_locations();
	$theme_sources  = array( 'gstore_desktop', 'gstore_mobile' );

	foreach ( $theme_sources as $theme_location ) {
		$menu_id = isset( $menu_locations[ $theme_location ] ) ? (int) $menu_locations[ $theme_location ] : 0;
		if ( $menu_id <= 0 ) {
			continue;
		}

		$menu_items = wp_get_nav_menu_items( $menu_id );
		if ( empty( $menu_items ) || is_wp_error( $menu_items ) ) {
			continue;
		}

		foreach ( $menu_items as $menu_item ) {
			if ( empty( $menu_item->url ) ) {
				continue;
			}

			$slug = gstore_get_internal_root_slug_from_url( (string) $menu_item->url );
			if ( '' === $slug || isset( $route_map[ $slug ] ) ) {
				continue;
			}

			$route_map[ $slug ] = home_url( '/' . $slug . '/' );
		}
	}

	return $route_map;
}

/**
 * Retorna os termos de categoria relevantes para os crumbs do produto, indexados pelo path.
 *
 * @param int $product_id ID do produto.
 * @return array<string, WP_Term>
 */
function gstore_get_single_product_breadcrumb_terms_by_path( $product_id ) {
	$product_id = absint( $product_id );
	if ( $product_id <= 0 ) {
		return array();
	}

	$assigned_terms = wc_get_product_terms(
		$product_id,
		'product_cat',
		array(
			'fields' => 'all',
		)
	);

	if ( empty( $assigned_terms ) || is_wp_error( $assigned_terms ) ) {
		return array();
	}

	$term_ids = array();
	foreach ( $assigned_terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}

		$term_ids[] = (int) $term->term_id;
		$term_ids   = array_merge(
			$term_ids,
			array_map( 'absint', get_ancestors( (int) $term->term_id, 'product_cat', 'taxonomy' ) )
		);
	}

	$term_ids = array_values( array_unique( array_filter( array_map( 'absint', $term_ids ) ) ) );
	if ( empty( $term_ids ) ) {
		return array();
	}

	$terms_by_path = array();
	foreach ( $term_ids as $term_id ) {
		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
			continue;
		}

		$term_link = get_term_link( $term );
		if ( is_wp_error( $term_link ) ) {
			continue;
		}

		$normalized_path = gstore_normalize_internal_site_path( $term_link );
		if ( '' === $normalized_path ) {
			continue;
		}

		$terms_by_path[ $normalized_path ] = $term;
	}

	return $terms_by_path;
}

/**
 * Reescreve links de categorias no breadcrumb do produto para usar menu do header ou catalogo filtrado.
 *
 * @param array $crumbs     Breadcrumb gerado pelo WooCommerce.
 * @param mixed $breadcrumb Instancia do breadcrumb.
 * @return array
 */
function gstore_filter_product_breadcrumb_category_links( $crumbs, $breadcrumb ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return $crumbs;
	}

	$product_id = get_queried_object_id();
	if ( $product_id <= 0 || ! is_array( $crumbs ) || empty( $crumbs ) ) {
		return $crumbs;
	}

	$terms_by_path = gstore_get_single_product_breadcrumb_terms_by_path( $product_id );
	if ( empty( $terms_by_path ) ) {
		return $crumbs;
	}

	$menu_route_map = gstore_get_header_menu_category_route_map();
	$catalog_url    = gstore_get_catalog_url();

	foreach ( $crumbs as $index => $crumb ) {
		if ( ! is_array( $crumb ) || empty( $crumb[1] ) || ! is_string( $crumb[1] ) ) {
			continue;
		}

		$normalized_path = gstore_normalize_internal_site_path( $crumb[1] );
		if ( '' === $normalized_path || ! isset( $terms_by_path[ $normalized_path ] ) ) {
			continue;
		}

		$term = $terms_by_path[ $normalized_path ];
		if ( ! $term instanceof WP_Term || empty( $term->slug ) ) {
			continue;
		}

		$target_url = get_term_link( $term, 'product_cat' );
		if ( is_wp_error( $target_url ) || ! is_string( $target_url ) || '' === $target_url ) {
			$target_url = isset( $menu_route_map[ $term->slug ] )
				? $menu_route_map[ $term->slug ]
				: $catalog_url;
		}

		$crumbs[ $index ][1] = $target_url;
	}

	return $crumbs;
}
add_filter( 'woocommerce_get_breadcrumb', 'gstore_filter_product_breadcrumb_category_links', 20, 2 );

/**
 * Remove o último crumb duplicado do breadcrumb na página de produto.
 *
 * O WooCommerce adiciona o nome do produto como último item do breadcrumb,
 * mas em alguns casos ele aparece duplicado. Esta função remove a duplicata.
 *
 * @param array $crumbs Breadcrumb gerado pelo WooCommerce.
 * @return array
 */
function gstore_remove_duplicate_product_breadcrumb( $crumbs ) {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return $crumbs;
	}

	if ( ! is_array( $crumbs ) || count( $crumbs ) < 2 ) {
		return $crumbs;
	}

	$last  = end( $crumbs );
	$prev  = prev( $crumbs );

	if ( is_array( $last ) && is_array( $prev ) && $last[0] === $prev[0] ) {
		array_pop( $crumbs );
	}

	return $crumbs;
}
add_filter( 'woocommerce_get_breadcrumb', 'gstore_remove_duplicate_product_breadcrumb', 30 );

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
 * ============================================
 * CÁLCULO DE PARCELAS
 * ============================================
 * O tema deve refletir a mesma regra do plugin/admin sempre que possível.
 * A divisão simples permanece apenas como fallback quando o plugin não estiver disponível.
 */

/**
 * Calcula o valor da parcela por divisão simples.
 *
 * @param float $price        Valor total do produto.
 * @param int   $installments Número de parcelas.
 * @return float Valor da parcela.
 */
function gstore_calculate_installment_amount( $price, $installments = 12 ) {
	$price        = (float) $price;
	$installments = (int) $installments;

	if ( $price <= 0 || $installments <= 0 ) {
		return 0.0;
	}

	return $price / $installments;
}

/**
 * Resolve o ID usado para consultar parcelas do produto.
 *
 * Para variáveis, usa a variação mais barata para alinhar com a UI do card.
 *
 * @param WC_Product $product Produto.
 * @return int
 */
function gstore_resolve_installment_product_id( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return 0;
	}

	$installment_product_id = gstore_get_product_id( $product );

	if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_prices' ) ) {
		$variation_prices = $product->get_variation_prices( true );
		if ( ! empty( $variation_prices['price'] ) ) {
			reset( $variation_prices['price'] );
			$cheapest_variation_id = (int) key( $variation_prices['price'] );
			if ( $cheapest_variation_id > 0 ) {
				$installment_product_id = $cheapest_variation_id;
			}
		}
	}

	return $installment_product_id;
}

/**
 * Retorna o preço base usado no fallback do parcelamento.
 *
 * @param WC_Product $product Produto.
 * @param string     $context Contexto desejado.
 * @return float
 */
function gstore_get_installment_display_price( $product, $context = 'auto' ) {
	if ( ! $product instanceof WC_Product ) {
		return 0.0;
	}
	if ( gstore_product_hides_price( $product, $context ) ) {
		return 0.0;
	}

	$price = 0.0;

	if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' ) ) {
		$price = (float) $product->get_variation_price( 'min', true );
	} else {
		$raw_price = (float) $product->get_price();
		if ( function_exists( 'wc_get_price_to_display' ) ) {
			$price = (float) wc_get_price_to_display(
				$product,
				array(
					'price' => $raw_price,
					'qty'   => 1,
				)
			);
		} else {
			$price = $raw_price;
		}
	}

	if ( $price <= 0 && method_exists( $product, 'get_price' ) ) {
		$price = (float) $product->get_price();
	}
	if ( $price <= 0 && method_exists( $product, 'get_regular_price' ) ) {
		$price = (float) $product->get_regular_price();
	}

	return max( 0.0, $price );
}

/**
 * Seleciona a melhor quote para exibir no tema.
 *
 * @param array $quotes Quotes do plugin.
 * @param int   $preferred_installments Parcela preferida.
 * @return array
 */
function gstore_get_preferred_installment_quote( $quotes, $preferred_installments = 0 ) {
	if ( ! is_array( $quotes ) || empty( $quotes ) ) {
		return array();
	}

	$preferred_installments = absint( $preferred_installments );
	if ( $preferred_installments > 0 ) {
		$preferred_key = (string) $preferred_installments;
		if ( isset( $quotes[ $preferred_key ] ) && is_array( $quotes[ $preferred_key ] ) ) {
			return $quotes[ $preferred_key ];
		}
	}

	$keys = array();
	foreach ( array_keys( $quotes ) as $quote_key ) {
		$quote_key = absint( $quote_key );
		if ( $quote_key > 0 ) {
			$keys[] = $quote_key;
		}
	}

	if ( empty( $keys ) ) {
		return array();
	}

	rsort( $keys, SORT_NUMERIC );
	$selected_key = (string) $keys[0];

	return isset( $quotes[ $selected_key ] ) && is_array( $quotes[ $selected_key ] )
		? $quotes[ $selected_key ]
		: array();
}

/**
 * Retorna os dados de preview de parcelamento já alinhados ao plugin/admin.
 *
 * @param WC_Product $product Produto.
 * @param int        $max_installments Parcelas máximas.
 * @param int        $quantity Quantidade.
 * @param string     $context Contexto desejado.
 * @return array
 */
function gstore_get_product_installment_preview_data( $product, $max_installments = 21, $quantity = 1, $context = 'auto' ) {
	$data = array(
		'product_id'            => 0,
		'installments'          => 0,
		'per_installment_html'  => '',
		'per_installment_text'  => '',
		'total_html'            => '',
		'total_text'            => '',
	);

	if ( ! $product instanceof WC_Product ) {
		return $data;
	}
	if ( gstore_product_hides_price( $product, $context ) ) {
		return $data;
	}

	$max_installments    = max( 1, (int) $max_installments );
	$quantity            = max( 1, (int) $quantity );
	$product_id          = gstore_resolve_installment_product_id( $product );
	$data['product_id']  = $product_id;

	if ( $product_id > 0 && function_exists( 'gstore_blu_get_product_installment_quotes_data' ) ) {
		$quotes_data = gstore_blu_get_product_installment_quotes_data( $product_id, $quantity, $max_installments );
		if ( ! is_wp_error( $quotes_data ) && ! empty( $quotes_data['quotes'] ) && is_array( $quotes_data['quotes'] ) ) {
			$preferred_quote = gstore_get_preferred_installment_quote( $quotes_data['quotes'], $max_installments );
			if ( ! empty( $preferred_quote['installments'] ) && ! empty( $preferred_quote['per_installment'] ) ) {
				$data['installments']         = (int) $preferred_quote['installments'];
				$data['per_installment_html'] = (string) $preferred_quote['per_installment'];
				$data['per_installment_text'] = isset( $preferred_quote['per_installment_text'] ) ? (string) $preferred_quote['per_installment_text'] : '';
				$data['total_html']           = isset( $preferred_quote['total'] ) ? (string) $preferred_quote['total'] : '';
				$data['total_text']           = isset( $preferred_quote['total_text'] ) ? (string) $preferred_quote['total_text'] : '';
				return $data;
			}
		}
	}

	$display_price = gstore_get_installment_display_price( $product, $context );
	if ( $display_price <= 0 ) {
		return $data;
	}

	$installment_amount            = gstore_calculate_installment_amount( $display_price * $quantity, $max_installments );
	$installment_amount_html       = $installment_amount > 0 ? wc_price( $installment_amount ) : '';
	$data['installments']          = $max_installments;
	$data['per_installment_html']  = $installment_amount_html;
	$data['per_installment_text']  = $installment_amount_html ? html_entity_decode( wp_strip_all_tags( $installment_amount_html ) ) : '';
	$data['total_html']            = wc_price( $display_price * $quantity );
	$data['total_text']            = html_entity_decode( wp_strip_all_tags( $data['total_html'] ) );

	return $data;
}

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
	if ( $product && is_a( $product, 'WC_Product' ) && gstore_product_hides_price( $product, 'card' ) ) {
		return gstore_get_hidden_price_mask_html( 'block' );
	}

	// Busca o preview alinhado ao plugin/admin; se indisponível, cai no fallback simples.
	$installment_value = 0;
	$installment_text_content = 'ou em até 21x no cartão';

	if ( $product && is_a( $product, 'WC_Product' ) ) {
		$installment_preview = gstore_get_product_installment_preview_data( $product, 21, 1, 'card' );
		if ( ! empty( $installment_preview['installments'] ) && ! empty( $installment_preview['per_installment_html'] ) ) {
			$installment_text_content = sprintf(
				'ou em até %1$dx de %2$s',
				(int) $installment_preview['installments'],
				$installment_preview['per_installment_html']
			);
		} else {
			$price_value = gstore_get_installment_display_price( $product, 'card' );
			if ( $price_value > 0 ) {
				$installment_value = gstore_calculate_installment_amount( $price_value, 21 );
				$installment_text_content = 'ou em até 21x de ' . wc_price( $installment_value );
			}
		}
	}

	// Desconto Pix: substitui o preço exibido pelo preço com desconto (mesmo padrão del/ins).
	$pix_price_replacement = '';
	if ( $product && is_a( $product, 'WC_Product' ) && function_exists( 'gstore_blu_pix_get_discounted_price' ) ) {
		$price_for_pix = floatval( $product->get_price() );
		$pix_price     = gstore_blu_pix_get_discounted_price( $price_for_pix, $product->get_id() );
		if ( false !== $pix_price ) {
			// Preço riscado: regular (se promoção) ou current (se sem promoção).
			$regular = floatval( $product->get_regular_price() );
			$strike  = ( $product->is_on_sale() && $regular > 0 ) ? $regular : $price_for_pix;
			$pix_price_replacement = '<del>' . wc_price( $strike ) . '</del> <ins>' . wc_price( $pix_price ) . '</ins>';
		}
	}

	// Cria os elementos de pagamento
	$payment_label = '<div class="Gstore-payment-label">À VISTA NO PIX</div>';
	$installment_text = '<div class="Gstore-installment-text">' . $installment_text_content . '</div>';

	// Encontra a div interna com a classe wc-block-components-product-price
	if ( strpos( $html, 'wc-block-components-product-price' ) !== false ) {
		// Se tem desconto Pix, substitui o conteúdo do preço pelo padrão del/ins.
		if ( $pix_price_replacement ) {
			$html = preg_replace(
				'/(<span[^>]*class="[^"]*wc-block-components-product-price__value[^"]*"[^>]*>).*?(<\/span>)/s',
				'$1' . $pix_price_replacement . '$2',
				$html,
				1
			);
		}

		// Adiciona o label antes do preço (logo após a abertura da div interna)
		$html = preg_replace(
			'/(<div[^>]*class="[^"]*wc-block-components-product-price[^"]*"[^>]*>\s*)/',
			'$1' . $payment_label,
			$html,
			1
		);

		// Adiciona texto de parcelamento antes do fechamento das divs
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
 * Filtra produtos na home page priorizando produtos em estoque.
 * Se houver menos de 4 produtos em estoque, completa até 4 slots com produtos
 * sem estoque para evitar seções vazias. Se houver 4 ou mais em estoque,
 * exibe apenas produtos em estoque normalmente.
 *
 * @param array $query_args Argumentos da query do WooCommerce.
 * @return array
 */
function gstore_filter_home_products_by_stock( $query_args ) {
	// Apenas na home page
	if ( ! is_front_page() ) {
		return $query_args;
	}

	// Marca as vitrines da home para priorizar produtos "Destaque".
	$query_args['gstore_featured_first'] = 1;

	// Prepara args base para as consultas de pré-verificação:
	// remove gstore_featured_first, ativa modo rápido e limpa _stock_status herdado do shortcode.
	$base_args = $query_args;
	unset( $base_args['gstore_featured_first'] );
	$base_args['fields']                 = 'ids';
	$base_args['no_found_rows']          = true;
	$base_args['update_post_meta_cache'] = false;
	$base_args['update_post_term_cache'] = false;

	if ( ! isset( $base_args['meta_query'] ) || ! is_array( $base_args['meta_query'] ) ) {
		$base_args['meta_query'] = array();
	} else {
		foreach ( $base_args['meta_query'] as $k => $mq ) {
			if ( isset( $mq['key'] ) && '_stock_status' === $mq['key'] ) {
				unset( $base_args['meta_query'][ $k ] );
			}
		}
		$base_args['meta_query'] = array_values( $base_args['meta_query'] );
	}

	// Consulta até 4 produtos em estoque para verificar o threshold.
	$instock_args                   = $base_args;
	$instock_args['posts_per_page'] = 4;
	$instock_args['meta_query'][]   = array(
		'key'     => '_stock_status',
		'value'   => 'instock',
		'compare' => '=',
	);
	$instock_query = new WP_Query( $instock_args );
	$instock_ids   = (array) $instock_query->posts;
	$instock_count = count( $instock_ids );

	if ( $instock_count >= 4 ) {
		// Tem pelo menos 4 em estoque: exibe apenas produtos em estoque normalmente.
		if ( ! isset( $query_args['meta_query'] ) || ! is_array( $query_args['meta_query'] ) ) {
			$query_args['meta_query'] = array();
		}
		$query_args['meta_query'][] = array(
			'key'     => '_stock_status',
			'value'   => 'instock',
			'compare' => '=',
		);
	} else {
		// Menos de 4 em estoque: completa até 4 slots com produtos sem estoque.
		$slots_to_fill  = 4 - $instock_count;
		$outofstock_ids = array();

		if ( $slots_to_fill > 0 ) {
			$outofstock_args                   = $base_args;
			$outofstock_args['posts_per_page'] = $slots_to_fill;
			$outofstock_args['meta_query'][]   = array(
				'key'     => '_stock_status',
				'value'   => 'outofstock',
				'compare' => '=',
			);
			if ( ! empty( $instock_ids ) ) {
				$outofstock_args['post__not_in'] = $instock_ids;
			}
			$outofstock_query = new WP_Query( $outofstock_args );
			$outofstock_ids   = (array) $outofstock_query->posts;
		}

		$combined_ids = array_merge( $instock_ids, $outofstock_ids );

		if ( ! empty( $combined_ids ) ) {
			// Define post__in com os IDs combinados (em estoque primeiro, depois sem estoque)
			// e remove qualquer filtro _stock_status para permitir ambos os status.
			$query_args['post__in'] = $combined_ids;
			$query_args['orderby']  = 'post__in';

			if ( isset( $query_args['meta_query'] ) && is_array( $query_args['meta_query'] ) ) {
				foreach ( $query_args['meta_query'] as $k => $mq ) {
					if ( isset( $mq['key'] ) && '_stock_status' === $mq['key'] ) {
						unset( $query_args['meta_query'][ $k ] );
					}
				}
				$query_args['meta_query'] = array_values( $query_args['meta_query'] );
			}
		} else {
			// Nenhum produto disponível: mantém filtro de instock (retorna vazio).
			if ( ! isset( $query_args['meta_query'] ) || ! is_array( $query_args['meta_query'] ) ) {
				$query_args['meta_query'] = array();
			}
			$query_args['meta_query'][] = array(
				'key'     => '_stock_status',
				'value'   => 'instock',
				'compare' => '=',
			);
		}
	}

	return $query_args;
}
add_filter( 'woocommerce_shortcode_products_query', 'gstore_filter_home_products_by_stock', 10, 1 );

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
 * Restaura paragrafos apenas no corpo de posts classicos do blog.
 *
 * O tema remove wpautop globalmente para evitar quebras em areas customizadas,
 * mas posts criados pelo editor classico/customizado podem chegar sem tags
 * de bloco. Esta regra fica limitada a single post para nao afetar produtos.
 *
 * @param string $content Conteudo renderizado.
 * @return string
 */
function gstore_restore_classic_blog_post_paragraphs( $content ) {
	if ( is_admin() || '' === trim( (string) $content ) ) {
		return $content;
	}

	if ( function_exists( 'is_singular' ) && ! is_singular( 'post' ) ) {
		return $content;
	}

	$post_id = get_the_ID();
	if ( $post_id && 'post' !== get_post_type( $post_id ) ) {
		return $content;
	}

	if ( $post_id && function_exists( 'has_blocks' ) && has_blocks( $post_id ) ) {
		return $content;
	}

	$has_block_markup = preg_match( '/<(p|div|ul|ol|li|h[1-6]|blockquote|table|pre|hr|figure|section|article|br)\b/i', $content );
	if ( $has_block_markup ) {
		return $content;
	}

	$content = wpautop( $content );
	if ( function_exists( 'shortcode_unautop' ) ) {
		$content = shortcode_unautop( $content );
	}

	return $content;
}
add_filter( 'the_content', 'gstore_restore_classic_blog_post_paragraphs', 10 );

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

		/**
		 * Evita `mb_convert_encoding(..., 'HTML-ENTITIES', ...)` (deprecated no PHP 8.2+).
		 * Em vez disso, força UTF-8 no DOMDocument via header XML e sempre envolve em um wrapper <div>
		 * para conseguirmos "unwrap" com segurança no final.
		 */
		$needs_unwrap_div = true;
		$content          = '<?xml encoding="UTF-8"?>' . '<div>' . $html . '</div>';

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
	$formatted_total = gstore_cart_hides_price( 'checkout' )
		? gstore_get_hidden_price_mask_html( 'inline' )
		: wc_price( $raw_total );
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

		<div class="Gstore-order-summary-card__assurance-grid" aria-label="<?php esc_attr_e( 'Garantias da loja', 'gstore' ); ?>">
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
	if ( ! function_exists( 'is_cart' ) || ! is_cart() || ! in_the_loop() || ! is_main_query() ) {
		return $title;
	}
	// Só remove o título da própria página do carrinho, não de itens do menu nav.
	if ( $id && (int) $id === get_queried_object_id() ) {
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
 * Garante que a mensagem de carrinho vazio seja sempre exibida quando o carrinho estiver vazio.
 */
function gstore_ensure_empty_cart_message() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}

	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return;
	}

	// Se o carrinho estiver vazio, garante que a mensagem seja exibida
	if ( WC()->cart->is_empty() ) {
		// Remove qualquer filtro que possa estar escondendo a mensagem
		add_filter( 'woocommerce_cart_is_empty', '__return_true', 999 );

		// Garante que os avisos sejam exibidos
		if ( function_exists( 'woocommerce_output_all_notices' ) ) {
			// A mensagem de carrinho vazio já é exibida pelo WooCommerce automaticamente
			// Mas garantimos que ela não seja escondida
			add_filter( 'woocommerce_output_all_notices', function( $output ) {
				if ( WC()->cart->is_empty() ) {
					// Se não houver mensagem de carrinho vazio, adiciona uma
					if ( false === strpos( $output, 'cart-empty' ) ) {
						$empty_message = '<p class="cart-empty woocommerce-info">' . esc_html__( 'Seu carrinho está vazio.', 'woocommerce' ) . '</p>';
						return $empty_message . $output;
					}
				}
				return $output;
			}, 5 );
		}
	}
}
add_action( 'wp', 'gstore_ensure_empty_cart_message', 5 );

/**
 * Remove o wrapper de notices quando não há notices para exibir.
 * Evita espaços vazios na página quando não há mensagens.
 *
 * @param string $output HTML dos notices.
 * @return string
 */
function gstore_hide_empty_notices_wrapper( $output ) {
	// Se o output estiver vazio ou contiver apenas o wrapper vazio, retorna string vazia
	if ( empty( trim( $output ) ) ) {
		return '';
	}

	// Remove o wrapper se não contiver nenhum notice real
	// Verifica se há mensagens, erros ou informações
	if (
		false === strpos( $output, 'woocommerce-message' ) &&
		false === strpos( $output, 'woocommerce-error' ) &&
		false === strpos( $output, 'woocommerce-info' ) &&
		false === strpos( $output, 'wc-block-components-notice' )
	) {
		return '';
	}

	return $output;
}
add_filter( 'woocommerce_output_all_notices', 'gstore_hide_empty_notices_wrapper', 999 );



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

		// JavaScript do checkout em 3 etapas
		wp_enqueue_script(
			'gstore-checkout-steps',
			get_theme_file_uri( 'assets/js/checkout-steps.js' ),
			array( 'jquery' ),
			filemtime( get_theme_file_path( 'assets/js/checkout-steps.js' ) ),
			true
		);

		// Fornece nonce e URLs para o checkout (URLs respeitam subdiretório do WP).
		$checkout_inline  = 'window.gstoreCheckout = window.gstoreCheckout || {};';
		$checkout_inline .= 'window.gstoreCheckout.processCheckoutNonce = ' . wp_json_encode( wp_create_nonce( 'woocommerce-process_checkout' ) ) . ';';
		$checkout_inline .= 'window.gstoreCheckout.cartSummaryNonce = ' . wp_json_encode( wp_create_nonce( 'gstore_cart_summary' ) ) . ';';
		$checkout_inline .= 'window.gstoreCheckout.homeUrl = ' . wp_json_encode( home_url( '/' ) ) . ';';
		$checkout_inline .= 'window.gstoreCheckout.buyNow = ' . wp_json_encode(
			array(
				'resetStateOnLoad' => gstore_consume_buy_now_checkout_reset_flag(),
			)
		) . ';';
		$checkout_inline .= 'window.gstoreCheckout.bluResume = ' . wp_json_encode( array(
			'enabled'          => true,
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'gstore_blu_resume_payment_link' ),
			'storageKeyPrefix' => 'gstore_blu_resume_checkout',
			'version'          => 1,
			'action'           => 'gstore_blu_resume_payment_link',
		) ) . ';';
		// Garante gstoreCartSummary (resumo do carrinho) com nonce válido para evitar 403 no admin-ajax.
		$cart_summary_nonce = wp_create_nonce( 'gstore_cart_summary' );
		$checkout_inline .= 'window.gstoreCartSummary = window.gstoreCartSummary || { ajaxUrl: ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ', nonce: ' . wp_json_encode( $cart_summary_nonce ) . ' };';
		// Termos do contrato: conteúdo do modal (título, texto completo, checkbox, privacidade).
		$contract_modal_title   = __( 'Termos do contrato', 'gstore' );
		$contract_checkbox_text = __( 'Li e concordo com os', 'gstore' );
		$contract_content       = get_theme_mod( 'gstore_contract_terms_content', function_exists( 'gstore_get_default_contract_terms_content' ) ? gstore_get_default_contract_terms_content() : '' );
		// Preferir template do plugin (campo "Template HTML do contrato") quando disponível.
		if ( class_exists( '\GStore\Services\Contract_Service' ) ) {
			$plugin_contract_settings = \GStore\Services\Contract_Service::get_settings();
			if ( ! empty( $plugin_contract_settings['template'] ) && is_string( $plugin_contract_settings['template'] ) ) {
				$contract_content = $plugin_contract_settings['template'];
			}
		}
		if ( function_exists( 'gstore_process_store_info_placeholders' ) ) {
			$contract_content = gstore_process_store_info_placeholders( $contract_content );
		}
		$checkout_inline .= 'window.gstoreCheckout.contractSettings = ' . wp_json_encode( array(
			'enabled'      => true,
			'checkboxText' => $contract_checkbox_text,
			'modalTitle'   => $contract_modal_title,
			'modalContent' => $contract_content,
			'privacyUrl'   => get_privacy_policy_url(),
		) ) . ';';
		$seller_name_display = gstore_get_store_name( 'display' );
		$seller_legal        = gstore_get_store_name();
		$seller_cnpj         = gstore_get_cnpj();
		$seller_address      = gstore_get_address( 'full' );
		if ( class_exists( '\GStore\Services\Contract_Service' ) ) {
			$plugin_settings = \GStore\Services\Contract_Service::get_settings();
			if ( ! empty( $plugin_settings['seller'] ) && is_array( $plugin_settings['seller'] ) ) {
				$s = $plugin_settings['seller'];
				if ( ! empty( $s['name'] ) ) {
					$seller_name_display = $s['name'];
				}
				if ( ! empty( $s['legal_name'] ) ) {
					$seller_legal = $s['legal_name'];
				}
				if ( ! empty( $s['cnpj'] ) ) {
					$seller_cnpj = $s['cnpj'];
				}
				if ( ! empty( $s['address_full'] ) ) {
					$seller_address = $s['address_full'];
				}
			}
		}
		$checkout_inline .= 'window.gstoreCheckout.contractTokenDefaults = ' . wp_json_encode(
			array(
				'store_name'               => $seller_legal,
				'store_display_name'       => $seller_name_display,
				'cnpj'                     => $seller_cnpj,
				'address_full'             => $seller_address,
				'address_city_state'       => gstore_get_address( 'city_state' ),
				'phone'                    => gstore_get_phone(),
				'whatsapp_display'         => gstore_get_whatsapp( 'display' ),
				'contract.generated_at'    => wp_date( 'd/m/Y H:i' ),
				'seller.name'              => $seller_name_display,
				'seller.legal_name'        => $seller_legal,
				'seller.cnpj'              => $seller_cnpj,
				'seller.address_full'      => $seller_address,
			)
		) . ';';
		// Preview via AJAX (quando o servico de contrato estiver disponivel no plugin).
		$checkout_inline .= 'window.gstoreCheckout.contractPreview = ' . wp_json_encode(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gstore_contract_preview' ),
				'action'  => 'gstore_contract_preview',
			)
		) . ';';
		wp_add_inline_script( 'gstore-checkout-steps', $checkout_inline, 'before' );

		// CSS do Pix
		wp_enqueue_style(
			'gstore-checkout-pix',
			get_theme_file_uri( 'assets/css/checkout-pix.css' ),
			array( 'gstore-checkout' ),
			$theme_version
		);

		// JavaScript do Pix
		wp_enqueue_script(
			'gstore-checkout-pix',
			get_theme_file_uri( 'assets/js/checkout-pix.js' ),
			array( 'jquery' ),
			$theme_version,
			true
		);

	}

	if ( function_exists( 'is_cart' ) && is_cart() ) {
		$cart_css_path    = get_theme_file_path( 'assets/css/cart.css' );
		$cart_css_version = file_exists( $cart_css_path ) ? (string) filemtime( $cart_css_path ) : $theme_version;

		wp_enqueue_style(
			'gstore-cart',
			get_theme_file_uri( 'assets/css/cart.css' ),
			array( 'gstore-style', 'gstore-fontawesome' ),
			$cart_css_version
		);
	}

	// CSS do Pix também na página de obrigado e visualização do pedido
	if ( ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) || ( function_exists( 'is_view_order_page' ) && is_view_order_page() ) ) {
		wp_enqueue_style(
			'gstore-checkout-pix',
			get_theme_file_uri( 'assets/css/checkout-pix.css' ),
			array( 'gstore-style' ),
			$theme_version
		);

		// JavaScript do Pix para página de obrigado e visualização do pedido
		wp_enqueue_script(
			'gstore-checkout-pix',
			get_theme_file_uri( 'assets/js/checkout-pix.js' ),
			array( 'jquery' ),
			$theme_version,
			true
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

	// Calculador de Frete - Produto único, carrinho e checkout
	if (
		( function_exists( 'is_product' ) && is_product() )
		|| ( function_exists( 'is_cart' ) && is_cart() )
		|| ( function_exists( 'is_checkout' ) && is_checkout() )
	) {
		// CSS do calculador
		wp_enqueue_style(
			'gstore-shipping-calculator',
			get_theme_file_uri( 'assets/css/shipping-calculator.css' ),
			array( 'gstore-style' ),
			$theme_version
		);

		// JavaScript do calculador
		wp_enqueue_script(
			'gstore-shipping-calculator',
			get_theme_file_uri( 'assets/js/shipping-calculator.js' ),
			array( 'jquery' ),
			$theme_version,
			true
		);

		// Localiza script do calculador
		global $product;
		$product_id = 0;
		$quantity   = 1;

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$product_id = 0;
			$quantity   = 1;
		} elseif ( function_exists( 'is_product' ) && is_product() ) {
			$queried_product_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;

			if ( $queried_product_id > 0 ) {
				$product_id = $queried_product_id;
			} elseif ( $product instanceof WC_Product ) {
				$product_id = (int) $product->get_id();
			}
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && function_exists( 'WC' ) && WC()->cart ) {
			$cart_items = WC()->cart->get_cart();
			if ( ! empty( $cart_items ) ) {
				$first_item = reset( $cart_items );
				if ( ! empty( $first_item['product_id'] ) ) {
					$product_id = (int) $first_item['product_id'];
				}
				if ( ! empty( $first_item['quantity'] ) ) {
					$quantity = (int) $first_item['quantity'];
				}
			}
		}

		wp_localize_script(
			'gstore-shipping-calculator',
			'gstoreShippingCalculator',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'gstore_shipping_calculator' ),
				'productId'  => $product_id,
				'quantity'   => $quantity,
				'i18n'       => array(
					'calculate'        => __( 'Calcular frete', 'gstore' ),
					'calculating'      => __( 'Calculando...', 'gstore' ),
					'invalidCep'       => __( 'CEP inválido. Por favor, informe um CEP válido com 8 dígitos.', 'gstore' ),
					'error'            => __( 'Erro ao calcular frete. Tente novamente.', 'gstore' ),
					'frete'            => __( 'Frete', 'gstore' ),
				'destination'      => __( 'Destino', 'gstore' ),
				),
			)
		);
	}
}
add_action( 'wp', 'gstore_enqueue_checkout_assets', 40 );

/**
 * Move o texto de privacidade para baixo do botão de finalizar compra.
 */
function gstore_move_privacy_policy_text() {
    remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
    add_action( 'woocommerce_review_order_after_submit', 'wc_checkout_privacy_policy_text', 20 );
}
add_action( 'init', 'gstore_move_privacy_policy_text' );

// Regras do checkout (campos, parcelas, fees e validações) ficam no plugin gstore-core.

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
// Movido para o plugin gstore-core.

/**
 * Correção temporária: Corrige o status do Pix via JavaScript.
 * O status 'processed' significa apenas que a cobrança foi criada, não que foi paga.
 * Apenas 'paid' deve mostrar "Pagamento aprovado".
 */
add_action( 'wp_footer', function() {
	if ( ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'view-order' ) ) {
		return;
	}
	?>
	<script>
	(function() {
		// Corrige o status do Pix - apenas 'paid' deve mostrar como aprovado
		var statusMeta = document.querySelector('.pix-box__meta--muted');
		var statusBadge = document.querySelector('.pix-box__status');
		var pixBox = document.querySelector('.pix-box');

		if (statusMeta && statusBadge && pixBox) {
			var statusText = statusMeta.textContent || '';
			var match = statusText.match(/Status:\s*(\w+)/i);
			if (match) {
				var realStatus = match[1].toLowerCase();
				// Apenas 'paid' é pagamento aprovado
				if (realStatus !== 'paid') {
					statusBadge.textContent = 'AGUARDANDO PAGAMENTO';
					pixBox.classList.remove('pix-box--processed');
					pixBox.classList.add('pix-box--pending');
				}
			}
		}
	})();
	</script>
	<?php
}, 999 );

/**
 * Filtro para deixar apenas a Blu como gateway (Opcional/Solicitado).
 */
// Movido para o plugin gstore-core.

/**
 * Filtro de Categorias Marketplace.
 */
require_once get_theme_file_path( 'inc/class-gstore-category-filter.php' );

/**
 * Sistema de Debug Logs.
 */
require_once get_theme_file_path( 'inc/class-gstore-debug-logger.php' );

/**
 * Navegação do header: locais desktop/mobile e substituição do bloco Navigation.
 */
require_once get_theme_file_path( 'inc/class-gstore-nav-menu.php' );

/**
 * Dados de aeroportos para a página informativo (pós-venda).
 */
require_once get_theme_file_path( 'inc/informativo-airports.php' );

/**
 * Função helper para fazer log de debug.
 *
 * @param string $location Localização (arquivo:linha).
 * @param string $message Mensagem.
 * @param array  $data Dados adicionais.
 * @param string $session_id ID da sessão.
 * @param string $run_id ID da execução.
 * @param string $hypothesis_id ID da hipótese.
 */
function gstore_debug_log( $location, $message, $data = array(), $session_id = 'debug-session', $run_id = 'run1', $hypothesis_id = '' ) {
	if ( isset( $GLOBALS['gstore_debug_logger'] ) ) {
		$GLOBALS['gstore_debug_logger']->log( $location, $message, $data, $session_id, $run_id, $hypothesis_id );
	}
}

/**
 * Gerenciador de informações da loja (JSON centralizado).
 */
// Movido para o plugin gstore-core (persistência em wp_options).

/**
 * API Visualizer (React + Mermaid) - shortcode e enqueue dos assets.
 */
require_once get_theme_file_path( 'inc/api-visualizer.php' );

/**
 * Home Blog V1 shortcode (destaque + grid).
 */
require_once get_theme_file_path( 'home-blog-v1.php' );

/**
 * Método de envio customizado Gstore.
 *
 * Movido para o plugin gstore-core.
 */

/**
 * Helpers de frete no carrinho (baseado na config do admin).
 */
if ( ! function_exists( 'gstore_get_freight_config' ) ) {
	function gstore_get_freight_config() {
		$config = get_option( 'gstore_freight_config' );
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		$config['variations'] = isset( $config['variations'] ) && is_array( $config['variations'] )
			? $config['variations']
			: array();

		$config['rules'] = isset( $config['rules'] ) && is_array( $config['rules'] )
			? $config['rules']
			: array();

		return $config;
	}
}

if ( ! function_exists( 'gstore_parse_freight_slugs' ) ) {
	function gstore_parse_freight_slugs( $value ) {
		$value = is_string( $value ) ? $value : '';
		if ( '' === trim( $value ) ) {
			return array();
		}

		$parts = preg_split( '/\s*,\s*/', $value );
		$slugs = array();

		foreach ( $parts as $part ) {
			$slug = sanitize_title( $part );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}
}

if ( ! function_exists( 'gstore_get_product_slug_candidates' ) ) {
	function gstore_get_product_slug_candidates( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$slugs = array();

		$product_slug = sanitize_title( $product->get_slug() );
		if ( $product_slug ) {
			$slugs[] = $product_slug;
		}

		$categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
		if ( is_array( $categories ) && ! is_wp_error( $categories ) ) {
			$slugs = array_merge( $slugs, array_map( 'sanitize_title', $categories ) );
		}

		$tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'slugs' ) );
		if ( is_array( $tags ) && ! is_wp_error( $tags ) ) {
			$slugs = array_merge( $slugs, array_map( 'sanitize_title', $tags ) );
		}

		$slugs = array_filter( array_unique( array_map( 'sanitize_title', $slugs ) ) );

		return array_values( $slugs );
	}
}

if ( ! function_exists( 'gstore_find_freight_variation' ) ) {
	function gstore_find_freight_variation( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$config = gstore_get_freight_config();
		if ( empty( $config['variations'] ) ) {
			return null;
		}

		$product_slugs = gstore_get_product_slug_candidates( $product );
		if ( empty( $product_slugs ) ) {
			return null;
		}

		foreach ( $config['variations'] as $variation ) {
			if ( ! is_array( $variation ) ) {
				continue;
			}

			$slugs = array_merge(
				gstore_parse_freight_slugs( isset( $variation['mainSlugs'] ) ? $variation['mainSlugs'] : '' ),
				gstore_parse_freight_slugs( isset( $variation['extraSlugs'] ) ? $variation['extraSlugs'] : '' )
			);

			if ( empty( $slugs ) ) {
				continue;
			}

			if ( array_intersect( $product_slugs, $slugs ) ) {
				return $variation;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'gstore_get_product_freight_type' ) ) {
	function gstore_get_product_freight_type( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return 'other';
		}

		$variation = gstore_find_freight_variation( $product );
		if ( is_array( $variation ) ) {
			if ( ! empty( $variation['isAmmo'] ) ) {
				return 'ammo';
			}
			if ( ! empty( $variation['isGun'] ) ) {
				return 'gun';
			}
			if ( ! empty( $variation['isAccessory'] ) ) {
				return 'accessory';
		}
		}

		return 'other';
	}
}

if ( ! function_exists( 'gstore_get_cart_item_shipping_mode' ) ) {
	function gstore_get_cart_item_shipping_mode( $cart_item ) {
		$mode = isset( $cart_item['gstore_shipping_mode'] ) ? (string) $cart_item['gstore_shipping_mode'] : 'land';
		$mode = gstore_normalize_shipping_mode( $mode );
		return '' !== $mode ? $mode : 'land';
	}
}

if ( ! function_exists( 'gstore_get_cart_item_selected_shipping_rate' ) ) {
	function gstore_get_cart_item_selected_shipping_rate( $cart_item ) {
		$rate_id = isset( $cart_item['gstore_selected_shipping_rate'] ) ? sanitize_text_field( (string) $cart_item['gstore_selected_shipping_rate'] ) : '';
		return 0 === strpos( $rate_id, 'gstore_custom_shipping:' ) ? $rate_id : '';
	}
}

if ( ! function_exists( 'gstore_get_freight_mode_options' ) ) {
	function gstore_get_freight_mode_options( $variation, $type ) {
		$options = array();

		if ( 'ammo' === $type ) {
			$options[] = 'land';
			return $options;
		}

		$allow_land = true;
		$allow_air  = true;

		if ( is_array( $variation ) ) {
			if ( array_key_exists( 'allowLand', $variation ) ) {
				$allow_land = (bool) $variation['allowLand'];
			}
			if ( array_key_exists( 'allowAir', $variation ) ) {
				$allow_air = (bool) $variation['allowAir'];
			}
		}

		if ( $allow_land ) {
			$options[] = 'land';
		}
		if ( $allow_air ) {
			$options[] = 'air';
		}

		return $options;
	}
}

if ( ! function_exists( 'gstore_get_cart_item_freight_context' ) ) {
	function gstore_get_cart_item_freight_context( $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		$type    = gstore_get_product_freight_type( $product );
		$variation = gstore_find_freight_variation( $product );
		$options = gstore_get_freight_mode_options( $variation, $type );

		return array(
			'product'   => $product,
			'type'      => $type,
			'variation' => $variation,
			'options'   => $options,
		);
	}
}

if ( ! function_exists( 'gstore_get_cart_item_shipping_label' ) ) {
	function gstore_get_cart_item_shipping_label( $mode ) {
		$mode = gstore_normalize_shipping_mode( $mode );
		if ( 'air' === $mode ) {
			return __( 'Aéreo', 'gstore' );
		}
		if ( 'pickup' === $mode ) {
			return __( 'Retirada na loja', 'gstore' );
		}
		return __( 'Terrestre', 'gstore' );
	}
}

if ( ! function_exists( 'gstore_normalize_shipping_mode' ) ) {
	function gstore_normalize_shipping_mode( $mode ) {
		$mode = strtolower( trim( (string) $mode ) );
		if ( in_array( $mode, array( 'air', 'aereo', 'aéreo' ), true ) ) {
			return 'air';
		}
		if ( in_array( $mode, array( 'ground', 'land', 'terrestre' ), true ) ) {
			return 'land';
		}
		if ( in_array( $mode, array( 'pickup', 'retirada', 'retirada na loja', 'retirada-na-loja', 'store_pickup' ), true ) ) {
			return 'pickup';
		}

		return '';
	}
}

if ( ! function_exists( 'gstore_parse_shipping_cost' ) ) {
	function gstore_parse_shipping_cost( $value ) {
		if ( '' === trim( (string) $value ) ) {
			return 0.0;
		}

		if ( function_exists( 'wc_format_decimal' ) ) {
			$value = wc_format_decimal( $value );
		}

		return (float) $value;
	}
}

if ( ! function_exists( 'gstore_normalize_cart_rates' ) ) {
	function gstore_normalize_cart_rates( $rates ) {
		if ( ! is_array( $rates ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $rates as $rate ) {
			if ( ! is_array( $rate ) ) {
				continue;
			}

			$raw_mode = isset( $rate['mode'] ) ? $rate['mode'] : ( isset( $rate['label'] ) ? $rate['label'] : '' );
			$mode     = gstore_normalize_shipping_mode( $raw_mode );
			if ( '' === $mode ) {
				continue;
			}
			$label = isset( $rate['label'] ) ? sanitize_text_field( $rate['label'] ) : gstore_get_cart_item_shipping_label( $mode );

			$cost = 0.0;
			if ( isset( $rate['cost'] ) && is_numeric( $rate['cost'] ) ) {
				$cost = (float) $rate['cost'];
			} elseif ( isset( $rate['cost_formatted'] ) ) {
				$cost = gstore_parse_shipping_cost( $rate['cost_formatted'] );
			}

			$cost_formatted = '';
			if ( isset( $rate['cost_formatted'] ) ) {
				$cost_formatted = sanitize_text_field( $rate['cost_formatted'] );
			} elseif ( $cost > 0 && function_exists( 'wc_price' ) ) {
				$cost_formatted = wc_price( $cost );
			}

			$normalized[] = array(
				'rate_id'        => isset( $rate['rate_id'] ) ? sanitize_text_field( (string) $rate['rate_id'] ) : ( isset( $rate['id'] ) ? sanitize_text_field( (string) $rate['id'] ) : '' ),
				'mode'           => $mode,
				'label'          => $label,
				'carrier_id'     => isset( $rate['carrier_id'] ) ? sanitize_text_field( (string) $rate['carrier_id'] ) : '',
				'service_id'     => isset( $rate['service_id'] ) ? sanitize_text_field( (string) $rate['service_id'] ) : '',
				'cost'           => $cost,
				'cost_formatted' => $cost_formatted,
			);
		}

		return $normalized;
	}
}

if ( ! function_exists( 'gstore_restore_cart_item_shipping_mode' ) ) {
	function gstore_restore_cart_item_shipping_mode( $cart_item, $values ) {
		if ( isset( $values['gstore_selected_shipping_rate'] ) ) {
			$selected_rate = sanitize_text_field( (string) $values['gstore_selected_shipping_rate'] );
			if ( 0 === strpos( $selected_rate, 'gstore_custom_shipping:' ) ) {
				$cart_item['gstore_selected_shipping_rate'] = $selected_rate;
			}
		}

		if ( isset( $values['gstore_shipping_mode'] ) ) {
			$mode = gstore_normalize_shipping_mode( $values['gstore_shipping_mode'] );
			$cart_item['gstore_shipping_mode'] = '' !== $mode ? $mode : 'land';
		} elseif ( ! isset( $cart_item['gstore_shipping_mode'] ) ) {
			$cart_item['gstore_shipping_mode'] = 'land';
		}

		return $cart_item;
	}
}
add_filter( 'woocommerce_get_cart_item_from_session', 'gstore_restore_cart_item_shipping_mode', 20, 2 );

if ( ! function_exists( 'gstore_sync_cart_shipping_modes' ) ) {
	function gstore_sync_cart_shipping_modes() {
		static $syncing = false;

		if ( $syncing ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$has_posted_modes = ! empty( $_POST['gstore_shipping_mode'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$has_posted_rates = ! empty( $_POST['gstore_shipping_rates'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $has_posted_modes && ! $has_posted_rates ) {
			return;
		}

		$is_cart_context = ( is_cart() || is_checkout() );
		$is_update_cart_ajax = ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			&& ( ! empty( $_POST['update_cart'] )
				|| ( ! empty( $_REQUEST['wc-ajax'] ) && 'update_cart' === $_REQUEST['wc-ajax'] ) );
		$is_update_order_review = ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			&& ( ! empty( $_REQUEST['wc-ajax'] ) && 'update_order_review' === $_REQUEST['wc-ajax'] );
		if ( ! $is_cart_context && ! $is_update_cart_ajax && ! $is_update_order_review ) {
			return;
		}

		$posted_modes = $has_posted_modes
			? wp_unslash( $_POST['gstore_shipping_mode'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: array();
		$posted_rates = $has_posted_rates
			? wp_unslash( $_POST['gstore_shipping_rates'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: array();

		$posted_modes = is_array( $posted_modes ) ? $posted_modes : array();
		$posted_rates = is_array( $posted_rates ) ? $posted_rates : array();

		$syncing = true;
		$dirty   = false;

		foreach ( $posted_rates as $cart_item_key => $raw_rates ) {
			$cart_item_key = (string) $cart_item_key;

			if ( ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
				continue;
			}

			$decoded_rates = $raw_rates;
			if ( is_string( $raw_rates ) ) {
				$decoded_rates = json_decode( wp_unslash( $raw_rates ), true );
			}

			$normalized_rates = gstore_normalize_cart_rates( $decoded_rates );
			if ( empty( $normalized_rates ) ) {
				continue;
			}

			$previous_rates = isset( WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_rates'] )
				? WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_rates']
				: array();

			if ( $previous_rates !== $normalized_rates ) {
				$dirty = true;
			}

			WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_rates'] = $normalized_rates;
		}

		$posted_selected_rates = ! empty( $_POST['gstore_selected_shipping_rate'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST['gstore_selected_shipping_rate'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: array();
		$posted_selected_rates = is_array( $posted_selected_rates ) ? $posted_selected_rates : array();

		foreach ( $posted_modes as $cart_item_key => $mode ) {
			$cart_item_key = (string) $cart_item_key;
			$mode          = gstore_normalize_shipping_mode( $mode );
			if ( '' === $mode ) {
				continue;
			}

			if ( ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
				continue;
			}

			$rates = isset( WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_rates'] )
				? gstore_normalize_cart_rates( WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_rates'] )
				: array();

			if ( ! empty( $rates ) ) {
				$available_modes = array_map(
					function ( $rate ) {
						return isset( $rate['mode'] ) ? $rate['mode'] : 'land';
					},
					$rates
				);

				if ( ! in_array( $mode, $available_modes, true ) ) {
					$mode = $available_modes[0];
				}
			}

			$previous = isset( WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_mode'] )
				? (string) WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_mode']
				: '';

			if ( $previous !== $mode ) {
				$dirty = true;
			}

			WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_mode'] = $mode;
		}

		foreach ( $posted_selected_rates as $cart_item_key => $rate_id ) {
			$cart_item_key = (string) $cart_item_key;
			$rate_id = sanitize_text_field( (string) $rate_id );
			if ( 0 !== strpos( $rate_id, 'gstore_custom_shipping:' ) ) {
				continue;
			}
			if ( ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
				continue;
			}

			$previous = isset( WC()->cart->cart_contents[ $cart_item_key ]['gstore_selected_shipping_rate'] )
				? (string) WC()->cart->cart_contents[ $cart_item_key ]['gstore_selected_shipping_rate']
				: '';
			if ( $previous !== $rate_id ) {
				$dirty = true;
			}
			WC()->cart->cart_contents[ $cart_item_key ]['gstore_selected_shipping_rate'] = $rate_id;

			$rates = isset( WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_rates'] )
				? gstore_normalize_cart_rates( WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_rates'] )
				: array();
			foreach ( $rates as $rate ) {
				if ( isset( $rate['rate_id'] ) && $rate['rate_id'] === $rate_id && ! empty( $rate['mode'] ) ) {
					WC()->cart->cart_contents[ $cart_item_key ]['gstore_shipping_mode'] = $rate['mode'];
					break;
				}
			}
		}

		if ( $dirty ) {
			WC()->cart->set_session();
		}

		$syncing = false;
	}
}
add_action( 'woocommerce_cart_updated', 'gstore_sync_cart_shipping_modes', 20 );
add_action( 'woocommerce_update_cart_action_cart_updated', 'gstore_sync_cart_shipping_modes', 20 );
add_action( 'woocommerce_checkout_update_order_review', 'gstore_sync_cart_shipping_modes', 5 );

if ( ! function_exists( 'gstore_get_variation_shipping_cost' ) ) {
	function gstore_get_variation_shipping_cost( $variation, $mode, $quantity ) {
		if ( ! is_array( $variation ) ) {
			return 0.0;
		}

		$mode = gstore_normalize_shipping_mode( $mode );
		if ( 'pickup' === $mode ) {
			return 0.0;
		}
		$mode = 'air' === $mode ? 'air' : 'land';
		$price_key = 'air' === $mode ? 'airPrice' : 'landPrice';

		$price = isset( $variation[ $price_key ] ) ? (float) $variation[ $price_key ] : 0.0;
		if ( $price <= 0 ) {
			return 0.0;
		}

		$billing_mode = isset( $variation['billingMode'] ) ? (string) $variation['billingMode'] : 'per_item';
		if ( 'per_variation' === $billing_mode ) {
			return $price;
		}

		return $price * max( 1, (int) $quantity );
	}
}

if ( ! function_exists( 'gstore_get_cart_item_shipping_cost_display' ) ) {
	function gstore_get_cart_item_shipping_cost_display( $cart_item, $mode ) {
		if ( ! isset( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
			return '';
		}

		$rates = isset( $cart_item['gstore_shipping_rates'] )
			? gstore_normalize_cart_rates( $cart_item['gstore_shipping_rates'] )
			: array();
		if ( empty( $rates ) ) {
			return '';
		}

		$mode = gstore_normalize_shipping_mode( $mode );
		if ( '' === $mode ) {
			return '';
		}
		foreach ( $rates as $rate ) {
			if ( isset( $rate['mode'] ) && $rate['mode'] === $mode ) {
				if ( ! empty( $rate['cost_formatted'] ) ) {
					return $rate['cost_formatted'];
				}
				if ( ! empty( $rate['cost'] ) && function_exists( 'wc_price' ) ) {
					return wc_price( (float) $rate['cost'] );
				}
			}
		}

		return '';
	}
}

/**
 * Identifica a região de envio baseado no estado ou CEP.
 *
 * @param string $state Estado (UF) ou vazio.
 * @param string $postcode CEP ou vazio.
 * @return string Região: 'sul', 'resto_brasil', 'rio_janeiro'.
 */
function gstore_get_shipping_region( $state = '', $postcode = '' ) {
	// Limpa o estado
	$state = strtoupper( trim( $state ) );

	// Se tem estado, identifica diretamente
	if ( ! empty( $state ) ) {
		// Rio de Janeiro
		if ( $state === 'RJ' ) {
			return 'rio_janeiro';
		}

		// Região Sul
		if ( in_array( $state, array( 'RS', 'SC', 'PR' ), true ) ) {
			return 'sul';
		}

		// Demais estados
		return 'resto_brasil';
	}

	// Se não tem estado, tenta identificar pelo CEP
	if ( ! empty( $postcode ) ) {
		$postcode = preg_replace( '/[^0-9]/', '', $postcode );
		$first_digits = substr( $postcode, 0, 2 );

		// CEPs do Rio de Janeiro (20xxx-xxx a 23xxx-xxx)
		if ( $first_digits >= '20' && $first_digits <= '23' ) {
			return 'rio_janeiro';
		}

		// CEPs do Sul
		// RS: 90xxx-xxx a 96xxx-xxx
		// SC: 88xxx-xxx a 89xxx-xxx
		// PR: 80xxx-xxx a 83xxx-xxx
		if ( ( $first_digits >= '90' && $first_digits <= '96' ) ||
			 ( $first_digits >= '88' && $first_digits <= '89' ) ||
			 ( $first_digits >= '80' && $first_digits <= '83' ) ) {
			return 'sul';
		}
	}

	// Padrão: resto do Brasil
	return 'resto_brasil';
}

// Endpoint AJAX Pix movido para o plugin gstore-core.

/**
 * Obtém o estado (UF) a partir do CEP.
 *
 * @param string $postcode CEP (apenas números).
 * @return string Estado (UF) ou vazio se não identificado.
 */
function gstore_get_state_from_postcode( $postcode ) {
	$postcode = preg_replace( '/[^0-9]/', '', $postcode );
	if ( strlen( $postcode ) < 2 ) {
		return '';
	}

	$first_digit = substr( $postcode, 0, 1 );
	$first_two   = substr( $postcode, 0, 2 );

	// Mapeamento de CEP para Estado
	// Referência: https://www.correios.com.br/
	$cep_ranges = array(
		// São Paulo
		array( 'start' => '01', 'end' => '19', 'state' => 'SP' ),
		// Rio de Janeiro
		array( 'start' => '20', 'end' => '28', 'state' => 'RJ' ),
		// Espírito Santo
		array( 'start' => '29', 'end' => '29', 'state' => 'ES' ),
		// Minas Gerais
		array( 'start' => '30', 'end' => '39', 'state' => 'MG' ),
		// Bahia
		array( 'start' => '40', 'end' => '48', 'state' => 'BA' ),
		// Sergipe
		array( 'start' => '49', 'end' => '49', 'state' => 'SE' ),
		// Pernambuco
		array( 'start' => '50', 'end' => '56', 'state' => 'PE' ),
		// Alagoas
		array( 'start' => '57', 'end' => '57', 'state' => 'AL' ),
		// Paraíba
		array( 'start' => '58', 'end' => '58', 'state' => 'PB' ),
		// Rio Grande do Norte
		array( 'start' => '59', 'end' => '59', 'state' => 'RN' ),
		// Ceará
		array( 'start' => '60', 'end' => '63', 'state' => 'CE' ),
		// Piauí
		array( 'start' => '64', 'end' => '64', 'state' => 'PI' ),
		// Maranhão
		array( 'start' => '65', 'end' => '65', 'state' => 'MA' ),
		// Pará
		array( 'start' => '66', 'end' => '68', 'state' => 'PA' ),
		// Amapá (68900-68999)
		// Amazonas
		array( 'start' => '69', 'end' => '69', 'state' => 'AM' ),
		// Goiás e Tocantins
		array( 'start' => '70', 'end' => '72', 'state' => 'DF' ), // Distrito Federal (70000-72799)
		array( 'start' => '73', 'end' => '76', 'state' => 'GO' ), // Goiás
		array( 'start' => '77', 'end' => '77', 'state' => 'TO' ), // Tocantins
		// Mato Grosso
		array( 'start' => '78', 'end' => '78', 'state' => 'MT' ),
		// Mato Grosso do Sul
		array( 'start' => '79', 'end' => '79', 'state' => 'MS' ),
		// Paraná
		array( 'start' => '80', 'end' => '87', 'state' => 'PR' ),
		// Santa Catarina
		array( 'start' => '88', 'end' => '89', 'state' => 'SC' ),
		// Rio Grande do Sul
		array( 'start' => '90', 'end' => '99', 'state' => 'RS' ),
	);

	foreach ( $cep_ranges as $range ) {
		if ( $first_two >= $range['start'] && $first_two <= $range['end'] ) {
			// Tratamento especial para Amapá (68900-68999)
			if ( $first_two === '68' && substr( $postcode, 0, 3 ) >= '689' ) {
				return 'AP';
			}
			// Tratamento especial para Acre (69900-69999)
			if ( $first_two === '69' && substr( $postcode, 0, 3 ) >= '699' ) {
				return 'AC';
			}
			// Tratamento especial para Roraima (69300-69389)
			if ( $first_two === '69' && substr( $postcode, 0, 3 ) >= '693' && substr( $postcode, 0, 3 ) <= '693' ) {
				return 'RR';
			}
			// Tratamento especial para Rondônia (76800-76999)
			if ( $first_two === '76' && substr( $postcode, 0, 3 ) >= '768' ) {
				return 'RO';
			}
			return $range['state'];
		}
	}

	return '';
}

/**
 * Endpoint AJAX para calcular frete.
 * Retorna array de rates com modos permitidos (terrestre/aéreo) baseado na variação do produto.
 */
// Cálculo de frete via AJAX: responsabilidade do plugin gstore-core.



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
    // Verifica nonce do checkout (segurança)
    if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) ||
         ! wp_verify_nonce( $_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout' ) ) {
        return;
    }

    if ( ! empty( $_POST['billing_cpf'] ) ) {
        // Sanitiza o campo antes de processar
        $cpf = sanitize_text_field( $_POST['billing_cpf'] );
        // Remove tudo que não é número
        $cpf = preg_replace( '/[^0-9]/', '', $cpf );
        // Salva apenas se tiver conteúdo válido
        if ( ! empty( $cpf ) ) {
            update_post_meta( $order_id, 'billing_cpf', $cpf );
            update_post_meta( $order_id, '_billing_cpf', $cpf );
        }
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
 * Retorna o título amigável do método de pagamento a partir do ID do gateway.
 *
 * @param string $payment_method ID do gateway (ex: blu_pix, blu_checkout, cod).
 * @return string
 */
function gstore_get_payment_method_title_by_id( $payment_method ) {
	$payment_method = (string) $payment_method;
	if ( '' === $payment_method ) {
		return '';
	}

	// Overrides do tema (para manter o texto consistente no resumo).
	$overrides = array(
		'blu_pix'      => __( 'Pix', 'gstore' ),
		'blu_checkout' => __( 'Cartão', 'gstore' ),
	);
	if ( isset( $overrides[ $payment_method ] ) ) {
		return (string) $overrides[ $payment_method ];
	}

	if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
		return '';
	}

	$payment_method_title = '';

	// Preferir gateways disponíveis no checkout (já respeitam país/moeda/regras).
	$available = WC()->payment_gateways()->get_available_payment_gateways();
	if ( isset( $available[ $payment_method ] ) && is_object( $available[ $payment_method ] ) ) {
		$title = '';
		if ( method_exists( $available[ $payment_method ], 'get_title' ) ) {
			$title = (string) $available[ $payment_method ]->get_title();
		} elseif ( isset( $available[ $payment_method ]->title ) ) {
			$title = (string) $available[ $payment_method ]->title;
		}
		$payment_method_title = wp_strip_all_tags( $title );
	} else {
		// Fallback: gateway registrado (pode não estar "available" ainda em alguns estados).
		$all_gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $all_gateways[ $payment_method ] ) && is_object( $all_gateways[ $payment_method ] ) ) {
			$title = '';
			if ( method_exists( $all_gateways[ $payment_method ], 'get_title' ) ) {
				$title = (string) $all_gateways[ $payment_method ]->get_title();
			} elseif ( isset( $all_gateways[ $payment_method ]->title ) ) {
				$title = (string) $all_gateways[ $payment_method ]->title;
			}
			$payment_method_title = wp_strip_all_tags( $title );
		}
	}

	return (string) $payment_method_title;
}

/**
 * Exibe o método de pagamento selecionado no resumo (tabela) do checkout clássico.
 */
function gstore_checkout_review_order_payment_method_row() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$method = (string) WC()->session->get( 'chosen_payment_method', '' );
	$title  = gstore_get_payment_method_title_by_id( $method );

	if ( '' === $title ) {
		return;
	}

	?>
	<tr class="gstore-review-order-payment-method">
		<th><?php esc_html_e( 'Pagamento', 'gstore' ); ?></th>
		<td data-title="<?php esc_attr_e( 'Pagamento', 'gstore' ); ?>"><?php echo esc_html( $title ); ?></td>
	</tr>
	<?php
}
add_action( 'woocommerce_review_order_before_order_total', 'gstore_checkout_review_order_payment_method_row', 9 );

// Endpoints AJAX de checkout (resumo, parcelas) movidos para o plugin gstore-core.

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
 * Redireciona a página "Loja" para "Catálogo".
 *
 * Redireciona qualquer acesso à página /loja para /catalogo,
 * incluindo a página de arquivo do WooCommerce.
 */
function gstore_redirect_loja_to_catalogo() {
	// Verifica se é a página "loja" pelo slug
	if ( is_page( 'loja' ) ) {
		$catalogo_url = gstore_get_catalog_url();
		wp_safe_redirect( $catalogo_url, 301 );
		exit;
	}

	// Verifica se é a página de shop do WooCommerce configurada como "loja"
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$shop_page_id = wc_get_page_id( 'shop' );
		if ( $shop_page_id ) {
			$shop_page = get_post( $shop_page_id );
			if ( $shop_page && 'loja' === $shop_page->post_name ) {
				$catalogo_url = gstore_get_catalog_url();
				wp_safe_redirect( $catalogo_url, 301 );
				exit;
			}
		}
	}
}
add_action( 'template_redirect', 'gstore_redirect_loja_to_catalogo', 1 );

/**
 * Redireciona o indice vazio de marcas para o catalogo.
 *
 * Mantem archives reais como /marca/cbc/ intactos.
 */
function gstore_redirect_brand_index_to_catalogo() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = $request_uri ? (string) wp_parse_url( $request_uri, PHP_URL_PATH ) : '';
	$path        = trim( rawurldecode( (string) $path ), '/' );

	if ( 'marca' !== $path ) {
		return;
	}

	wp_safe_redirect( gstore_get_catalog_url(), 301 );
	exit;
}
add_action( 'template_redirect', 'gstore_redirect_brand_index_to_catalogo', 1 );

/**
 * Altera a URL do botão "Return to shop" para apontar para o catálogo.
 *
 * @param string $url URL original do botão.
 * @return string URL do catálogo.
 */
function gstore_return_to_shop_url( $url ) {
	$catalogo_page = get_page_by_path( 'catalogo' );
	if ( $catalogo_page ) {
		return get_permalink( $catalogo_page->ID );
	}
	return gstore_get_catalog_url();
}
add_filter( 'woocommerce_return_to_shop_redirect', 'gstore_return_to_shop_url' );

/**
 * Altera o texto do botão "Return to shop" para "Retornar para o catálogo".
 *
 * @param string $text Texto original do botão.
 * @return string Novo texto.
 */
function gstore_return_to_shop_text( $text ) {
	return __( 'Retornar para o catálogo', 'gstore' );
}
add_filter( 'woocommerce_return_to_shop_text', 'gstore_return_to_shop_text' );

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
 * Returns a valid registration nonce from POST when available.
 *
 * @return string
 */
function gstore_get_registration_nonce_from_post() {
	$nonce_value = '';

	if ( isset( $_POST['woocommerce-register-nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce_value = sanitize_text_field( wp_unslash( $_POST['woocommerce-register-nonce'] ) );
	} elseif ( isset( $_POST['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce_value = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
	}

	return $nonce_value;
}

/**
 * Redirects registration attempts with an existing email to lost-password.
 *
 * This is intentionally reusable from different hooks because environments can
 * process registration in different orders.
 *
 * @param string $fallback_email Optional email from hook context.
 * @return void
 */
function gstore_maybe_redirect_existing_account_registration( $fallback_email = '' ) {
	if ( is_user_logged_in() ) {
		return;
	}

	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		return;
	}

	if ( empty( $_POST['register'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	$nonce_value = gstore_get_registration_nonce_from_post();
	if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, 'woocommerce-register' ) ) {
		return;
	}

	$posted_email = '';

	if ( isset( $_POST['email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted_email = sanitize_email( wp_unslash( $_POST['email'] ) );
	} elseif ( is_string( $fallback_email ) ) {
		$posted_email = sanitize_email( $fallback_email );
	}

	if ( '' === $posted_email || ! is_email( $posted_email ) ) {
		return;
	}

	if ( ! function_exists( 'email_exists' ) || ! email_exists( $posted_email ) ) {
		return;
	}

	if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'gstore_existing_account_email', $posted_email );
	}

	$lost_password_url = function_exists( 'wc_lostpassword_url' ) ? wc_lostpassword_url() : wp_lostpassword_url();
	$target            = add_query_arg( 'gstore_existing_account', '1', $lost_password_url );

	wp_safe_redirect( $target );
	exit;
}

/**
 * Early interception before WooCommerce default registration flow.
 *
 * This avoids duplicate-email notices in environments where inner WC hooks
 * are bypassed or delayed by customizations.
 */
function gstore_redirect_existing_account_from_wp_loaded() {
	gstore_maybe_redirect_existing_account_registration();
}
add_action( 'wp_loaded', 'gstore_redirect_existing_account_from_wp_loaded', 4 );

/**
 * WooCommerce registration hook fallback.
 *
 * @param string   $username          Submitted username.
 * @param string   $email             Submitted email.
 * @param WP_Error $validation_errors Current validation errors.
 */
function gstore_redirect_existing_account_to_lost_password( $username, $email, $validation_errors ) {
	gstore_maybe_redirect_existing_account_registration( is_string( $email ) ? $email : '' );
}
add_action( 'woocommerce_register_post', 'gstore_redirect_existing_account_to_lost_password', 1, 3 );

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
 * FUNCIONALIDADE: REFAZER COMPRA (PEDIDOS CANCELADOS)
 * ============================================
 * Permite refazer compras de pedidos cancelados usando "Order Again".
 */

/**
 * Permite "Order Again" apenas para pedidos cancelados.
 *
 * Por padrão, o WooCommerce só permite "Order Again" para pedidos completed.
 * Esta função ajusta para aceitar apenas pedidos cancelados.
 *
 * @param array $statuses Array de status permitidos.
 * @return array Status permitidos atualizados.
 */
function gstore_allow_order_again_for_cancelled( $statuses ) {
	return array( 'cancelled' );
}
add_filter( 'woocommerce_valid_order_statuses_for_order_again', 'gstore_allow_order_again_for_cancelled', 10, 1 );

/**
 * Remove botões "Pagar" e "Cancelar" padrão do WooCommerce da tabela de pedidos.
 *
 * Esses botões nativos não são mais necessários, pois usamos "Order Again"
 * para pagar pedidos com menos de 1 dia.
 *
 * @param array    $actions Ações disponíveis para o pedido.
 * @param WC_Order $order   Objeto do pedido.
 * @return array Ações atualizadas (sem 'pay' e 'cancel').
 */
function gstore_remove_default_order_actions( $actions, $order ) {
	if ( ! is_array( $actions ) ) {
		return $actions;
	}

	// Remover ações padrão 'pay' e 'cancel'
	unset( $actions['pay'] );
	unset( $actions['cancel'] );

	return $actions;
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'gstore_remove_default_order_actions', 5, 2 );

/**
 * Adiciona botão "Refazer compra" na lista de pedidos, apenas se:
 * - Status é cancelled
 *
 * Usa funcionalidade nativa "Order Again" do WooCommerce.
 *
 * @param array    $actions Ações disponíveis para o pedido.
 * @param WC_Order $order   Objeto do pedido.
 * @return array Ações atualizadas.
 */
function gstore_add_refazer_compra_button_to_order_actions( $actions, $order ) {
	if ( ! $order instanceof WC_Order ) {
		return $actions;
	}

	// Verificar se status é cancelled
	$order_status = $order->get_status();
	if ( 'cancelled' !== $order_status ) {
		return $actions;
	}

	// Adicionar ação "Refazer compra" usando sistema nativo order_again
	$actions['order-again'] = array(
		'url'  => wp_nonce_url( add_query_arg( 'order_again', $order->get_id() ), 'woocommerce-order_again' ),
		'name' => __( 'Refazer compra', 'gstore' ),
	);

	return $actions;
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'gstore_add_refazer_compra_button_to_order_actions', 10, 2 );

/**
 * Checks whether a Blu history JSON contains a successful payment status.
 *
 * @param mixed $raw_json         Raw JSON string (or array) stored in order meta.
 * @param array $success_statuses List of statuses considered as payment evidence.
 * @return bool
 */
function gstore_my_account_order_history_has_success_status( $raw_json, array $success_statuses ) {
	if ( empty( $raw_json ) || empty( $success_statuses ) ) {
		return false;
	}

	$statuses_lookup = array();
	foreach ( $success_statuses as $success_status ) {
		$key = strtolower( trim( (string) $success_status ) );
		if ( '' !== $key ) {
			$statuses_lookup[ $key ] = true;
		}
	}

	if ( empty( $statuses_lookup ) ) {
		return false;
	}

	$entries = is_array( $raw_json ) ? $raw_json : json_decode( (string) $raw_json, true );
	if ( ! is_array( $entries ) ) {
		return false;
	}

	foreach ( $entries as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}

		$entry_status = '';
		if ( isset( $entry['status'] ) ) {
			$entry_status = strtolower( trim( (string) $entry['status'] ) );
		} elseif ( isset( $entry['payload'] ) && is_array( $entry['payload'] ) && isset( $entry['payload']['status'] ) ) {
			$entry_status = strtolower( trim( (string) $entry['payload']['status'] ) );
		}

		if ( '' !== $entry_status && isset( $statuses_lookup[ $entry_status ] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Detects payment evidence for an order, even if Woo status ended up as cancelled.
 *
 * @param WC_Order $order Pedido.
 * @return bool
 */
function gstore_my_account_order_has_payment_evidence( WC_Order $order ) {
	if ( $order->is_paid() ) {
		return true;
	}

	if ( $order->get_date_paid() ) {
		return true;
	}

	$transaction_id = trim( (string) $order->get_transaction_id() );
	if ( '' !== $transaction_id ) {
		return true;
	}

	$blu_status = strtolower( trim( (string) $order->get_meta( '_gstore_blu_status', true ) ) );
	if ( in_array( $blu_status, array( 'paid', 'success', 'confirmed' ), true ) ) {
		return true;
	}

	if ( gstore_my_account_order_history_has_success_status( $order->get_meta( '_gstore_blu_history', true ), array( 'paid', 'success', 'confirmed' ) ) ) {
		return true;
	}

	$pix_status = strtolower( trim( (string) $order->get_meta( '_gstore_blu_pix_status', true ) ) );
	if ( in_array( $pix_status, array( 'paid', 'success' ), true ) ) {
		return true;
	}

	$pix_movement_id = trim( (string) $order->get_meta( '_gstore_blu_pix_movement_id', true ) );
	if ( '' !== $pix_movement_id ) {
		return true;
	}

	if ( gstore_my_account_order_history_has_success_status( $order->get_meta( '_gstore_blu_pix_history', true ), array( 'paid', 'success' ) ) ) {
		return true;
	}

	return false;
}

/**
 * Returns the status label for the My Account > Orders table.
 *
 * @param WC_Order $order Pedido.
 * @return string
 */
function gstore_my_account_get_orders_tab_status_label( WC_Order $order ) {
	$order_status = $order->get_status();

	if ( 'cancelled' === $order_status && gstore_my_account_order_has_payment_evidence( $order ) ) {
		return __( 'Pago/Confirmado', 'gstore' );
	}

	return wc_get_order_status_name( $order_status );
}

/**
 * Configura os status exibidos na lista "Meus pedidos".
 * Hotfix temporário: inclui pedidos cancelados para evitar sumir pedido relevante.
 *
 * @param array $args Argumentos da query de pedidos do My Account.
 * @return array Argumentos atualizados.
 */
function gstore_my_account_orders_exclude_cancelled( $args ) {
	$args['status'] = array(
		'wc-pending',
		'wc-processing',
		'wc-on-hold',
		'wc-completed',
		'wc-refunded',
		'wc-failed',
		'wc-cancelled',
	);
	$args['limit']  = 5;
	return $args;
}
add_filter( 'woocommerce_my_account_my_orders_query', 'gstore_my_account_orders_exclude_cancelled', 10, 1 );

/**
 * Ajusta o rótulo do status "pending" apenas na área Minha Conta.
 *
 * Evita que o cliente veja "Aguardando pagamento" quando o pagamento já foi
 * realizado e a confirmação ainda está em processamento.
 *
 * @param array $statuses Lista de status do WooCommerce.
 * @return array
 */
function gstore_my_account_pending_payment_label( $statuses ) {
	if ( is_admin() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return $statuses;
	}

	if ( isset( $statuses['wc-pending'] ) ) {
		$statuses['wc-pending'] = __( 'Processando pagamento', 'gstore' );
	}

	return $statuses;
}
add_filter( 'wc_order_statuses', 'gstore_my_account_pending_payment_label', 20 );

/**
 * Valida estoque antes de adicionar produtos ao carrinho via "Order Again".
 *
 * Remove produtos sem estoque do array antes de adicionar ao carrinho.
 * Adiciona notice se algum produto não estiver disponível.
 *
 * @param array $cart_item_data Dados do item do carrinho.
 * @param array $order_item     Item do pedido original.
 * @param WC_Order $order       Objeto do pedido.
 * @return array|null Dados do item ou null para remover.
 */
function gstore_validate_order_again_stock( $cart_item_data, $order_item, $order ) {
	if ( ! isset( $cart_item_data['product_id'] ) ) {
		return $cart_item_data;
	}

	$product_id = absint( $cart_item_data['product_id'] );
	$variation_id = isset( $cart_item_data['variation_id'] ) ? absint( $cart_item_data['variation_id'] ) : 0;
	$quantity = isset( $cart_item_data['quantity'] ) ? absint( $cart_item_data['quantity'] ) : 1;

	// Obter produto ou variação
	$product = $variation_id > 0 ? wc_get_product( $variation_id ) : wc_get_product( $product_id );

	if ( ! $product || ! $product->exists() ) {
		// Produto deletado - não adicionar ao carrinho
		wc_add_notice(
			sprintf(
				/* translators: %s: nome do produto */
				__( 'O produto "%s" não está mais disponível.', 'gstore' ),
				$order_item->get_name()
			),
			'error'
		);
		return null; // Remove do carrinho
	}

	// Verificar estoque
	if ( ! $product->is_in_stock() ) {
		wc_add_notice(
			sprintf(
				/* translators: %s: nome do produto */
				__( 'Não existe mais estoque para o produto "%s".', 'gstore' ),
				$order_item->get_name()
			),
			'error'
		);
		return null; // Remove do carrinho
	}

	// Verificar quantidade disponível em estoque (se gerenciado)
	if ( $product->managing_stock() ) {
		$stock_quantity = $product->get_stock_quantity();
		if ( $stock_quantity !== null && $stock_quantity < $quantity ) {
			wc_add_notice(
				sprintf(
					/* translators: 1: nome do produto, 2: quantidade disponível */
					__( 'Apenas %2$d unidade(s) disponível(is) do produto "%1$s". Ajustamos a quantidade.', 'gstore' ),
					$order_item->get_name(),
					$stock_quantity
				),
				'error'
			);
			// Ajustar quantidade para o disponível
			if ( $stock_quantity > 0 ) {
				$cart_item_data['quantity'] = $stock_quantity;
			} else {
				return null; // Remove se não há estoque
			}
		}
	}

	return $cart_item_data;
}
add_filter( 'woocommerce_order_again_cart_item_data', 'gstore_validate_order_again_stock', 10, 3 );

/**
 * Redireciona para o carrinho após "Order Again" de pedidos cancelados.
 *
 * Verifica se a requisição veio de order_again e se o pedido está cancelado.
 *
 * @param string $url URL de redirecionamento.
 * @return string URL do carrinho ou URL original.
 */
function gstore_redirect_order_again_to_cart( $url ) {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_cart_url' ) ) {
		return $url;
	}

	// Verificar se veio de order_again
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['order_again'] ) ) {
		return $url;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$order_id = absint( $_REQUEST['order_again'] );
	if ( ! $order_id ) {
		return $url;
	}

	// Verificar nonce do WooCommerce
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'woocommerce-order_again' ) ) {
		return $url;
	}

	// Obter pedido
	$order = wc_get_order( $order_id );
	if ( ! $order instanceof WC_Order ) {
		return $url;
	}

	// Verificar se pedido pertence ao usuário atual
	if ( get_current_user_id() !== $order->get_user_id() ) {
		return $url;
	}

	// Verificar se status é cancelled
	$order_status = $order->get_status();
	if ( 'cancelled' !== $order_status ) {
		return $url;
	}

	// Redirecionar para carrinho
	return wc_get_cart_url();
}
add_filter( 'woocommerce_add_to_cart_redirect', 'gstore_redirect_order_again_to_cart', 25 );

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

	// Valida se o attachment existe
	$attachment = get_post( $attachment_id );
	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return '';
	}

	// Valida se é uma imagem
	$mime_type = get_post_mime_type( $attachment_id );
	if ( ! $mime_type || strpos( $mime_type, 'image/' ) !== 0 ) {
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
 * @param bool   $use_srcset    Se true, gera srcset e sizes para imagens responsivas (padrão: true).
 * @return string Tag <img> completa ou string vazia.
 */
function gstore_get_image_tag( $attachment_id, $size = 'full', $alt = '', $attr = array(), $use_srcset = true ) {
	if ( ! $attachment_id ) {
		return '';
	}

	// Se alt não foi fornecido, tenta pegar da mídia
	if ( empty( $alt ) ) {
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	}

	// URL principal
	$src_url = gstore_get_image_url( $attachment_id, $size );
	if ( empty( $src_url ) ) {
		return '';
	}

	$default_attr = array(
		'src'      => $src_url,
		'alt'      => $alt ? $alt : '',
		'loading'  => 'lazy',
		'decoding' => 'async',
	);

	// Gera srcset e sizes se solicitado e se não for 'full' (full usa imagem original)
	if ( $use_srcset && 'full' !== $size ) {
		$image_meta = wp_get_attachment_metadata( $attachment_id );
		if ( $image_meta && isset( $image_meta['sizes'] ) ) {
			// Tamanhos disponíveis para srcset
			$srcset_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large' );
			$srcset_array = array();

			// Adiciona o tamanho solicitado primeiro
			$current_size_url = gstore_get_image_url( $attachment_id, $size );
			if ( $current_size_url ) {
				$current_size_meta = wp_get_attachment_image_src( $attachment_id, $size );
				if ( $current_size_meta ) {
					$srcset_array[] = esc_url( $current_size_url ) . ' ' . $current_size_meta[1] . 'w';
				}
			}

			// Adiciona outros tamanhos disponíveis
			foreach ( $srcset_sizes as $srcset_size ) {
				if ( $srcset_size === $size ) {
					continue; // Já adicionado
				}

				if ( isset( $image_meta['sizes'][ $srcset_size ] ) ) {
					$srcset_url = gstore_get_image_url( $attachment_id, $srcset_size );
					$srcset_src = wp_get_attachment_image_src( $attachment_id, $srcset_size );
					if ( $srcset_url && $srcset_src ) {
						$srcset_array[] = esc_url( $srcset_url ) . ' ' . $srcset_src[1] . 'w';
					}
				}
			}

			// Adiciona o tamanho completo (original) se disponível e não for muito grande
			if ( isset( $image_meta['width'] ) && $image_meta['width'] <= 2048 ) {
				$full_url = gstore_get_image_url( $attachment_id, 'full' );
				if ( $full_url ) {
					$srcset_array[] = esc_url( $full_url ) . ' ' . $image_meta['width'] . 'w';
				}
			}

			if ( ! empty( $srcset_array ) ) {
				$default_attr['srcset'] = implode( ', ', $srcset_array );

				// Gera sizes apropriado baseado no tamanho solicitado
				if ( ! isset( $attr['sizes'] ) ) {
					$default_attr['sizes'] = '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw';
				}
			}
		}
	}

	// Width e height para evitar CLS (se disponível)
	if ( ! isset( $attr['width'] ) || ! isset( $attr['height'] ) ) {
		$image_src = wp_get_attachment_image_src( $attachment_id, $size );
		if ( $image_src && isset( $image_src[1] ) && isset( $image_src[2] ) ) {
			if ( ! isset( $attr['width'] ) ) {
				$default_attr['width'] = $image_src[1];
			}
			if ( ! isset( $attr['height'] ) ) {
				$default_attr['height'] = $image_src[2];
			}
		}
	}

	$attr = wp_parse_args( $attr, $default_attr );

	$img_tag = '<img';
	foreach ( $attr as $key => $value ) {
		if ( 'srcset' === $key && is_array( $value ) ) {
			// srcset já foi convertido para string acima
			continue;
		}
		if ( ! empty( $value ) || 'alt' === $key ) {
			$img_tag .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}
	$img_tag .= ' />';

	return $img_tag;
}

/**
 * Adiciona dimensões explícitas (width/height) a todas as imagens renderizadas pelo WordPress.
 *
 * Isso inclui imagens de blocos Gutenberg, the_content, etc.
 * Reduz CLS (Cumulative Layout Shift) melhorando a experiência do usuário.
 *
 * @param array $attr Atributos da imagem
 * @param WP_Post $attachment Objeto do attachment
 * @param string|array $size Tamanho da imagem
 * @return array Atributos modificados
 */
function gstore_add_image_dimensions( $attr, $attachment, $size ) {
	// Se já tem width e height, não precisa adicionar
	if ( isset( $attr['width'] ) && isset( $attr['height'] ) ) {
		return $attr;
	}

	// Obtém informações da imagem
	$image_src = wp_get_attachment_image_src( $attachment->ID, $size );
	if ( ! $image_src ) {
		return $attr;
	}

	// Adiciona width se não existir
	if ( ! isset( $attr['width'] ) && isset( $image_src[1] ) ) {
		$attr['width'] = $image_src[1];
	}

	// Adiciona height se não existir
	if ( ! isset( $attr['height'] ) && isset( $image_src[2] ) ) {
		$attr['height'] = $image_src[2];
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'gstore_add_image_dimensions', 10, 3 );

/**
 * Adiciona dimensões a imagens no conteúdo (the_content).
 *
 * Processa imagens que podem não ter sido renderizadas via wp_get_attachment_image.
 *
 * @param string $content Conteúdo do post
 * @return string Conteúdo modificado
 */
function gstore_add_dimensions_to_content_images( $content ) {
	// Não processa em admin
	if ( is_admin() ) {
		return $content;
	}

	// Procura por tags img sem width ou height
	$content = preg_replace_callback(
		'/<img([^>]*?)(?:\s+(?:width|height)\s*=\s*["\'][^"\']*["\'])?([^>]*?)>/i',
		function( $matches ) {
			$img_tag = $matches[0];
			$attributes = $matches[1] . $matches[2];

			// Se já tem width e height, retorna como está
			if ( preg_match( '/\s+(?:width|height)\s*=\s*["\'][^"\']*["\']/i', $img_tag ) ) {
				return $img_tag;
			}

			// Tenta extrair src para obter attachment ID
			if ( preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', $img_tag, $src_matches ) ) {
				$image_url = $src_matches[1];

				// Tenta obter attachment ID da URL
				$attachment_id = attachment_url_to_postid( $image_url );
				if ( $attachment_id ) {
					$image_src = wp_get_attachment_image_src( $attachment_id, 'full' );
					if ( $image_src && isset( $image_src[1] ) && isset( $image_src[2] ) ) {
						// Adiciona width e height
						$img_tag = str_replace( '<img', '<img width="' . esc_attr( $image_src[1] ) . '" height="' . esc_attr( $image_src[2] ) . '"', $img_tag );
					}
				}
			}

			return $img_tag;
		},
		$content
	);

	return $content;
}
add_filter( 'the_content', 'gstore_add_dimensions_to_content_images', 20 );

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
	$banner_alt = esc_attr( get_option( 'gstore_banner_youtube_alt', 'Banner do YouTube' ) );
	$banner_link = esc_url( get_option( 'gstore_banner_youtube_link', '' ) );

	if ( empty( $banner_url ) ) {
		return '';
	}

	// Cache-busting: adiciona versão à URL para forçar atualização
	$cache_version = absint( get_option( 'gstore_banner_cache_version', 0 ) );
	$banner_url = add_query_arg( 'v', $cache_version, $banner_url );

	$img_tag = sprintf(
		'<img src="%s" alt="%s" />',
		esc_url( $banner_url ),
		$banner_alt
	);

	// Se houver link configurado, envolve a imagem em um link
	if ( ! empty( $banner_link ) ) {
		$img_tag = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			$banner_link,
			$img_tag
		);
	}

	$html = sprintf(
		'<section class="wp-block-group alignfull Gstore-home-section Gstore-home-banner Gstore-home-banner--youtube" aria-label="%s">
			<figure class="wp-block-image alignfull Gstore-home-transition">
				%s
			</figure>
		</section>',
		esc_attr__( 'Banner do YouTube', 'gstore' ),
		$img_tag
	);

	// Remove <br> tags dentro do figure
	$html = preg_replace( '#<br\s*/?>#i', '', $html );

	return function_exists( 'gstore_normalize_home_section_output' )
		? gstore_normalize_home_section_output( $html )
		: $html;
}
add_shortcode( 'gstore_banner_youtube', 'gstore_banner_youtube_shortcode' );

/**
 * Renderiza a faixa de benefícios da Home com textos configuráveis via admin.
 *
 * Uso: [gstore_home_benefits]
 *
 * @return string HTML da faixa de benefícios.
 */
function gstore_home_benefits_shortcode() {
	$defaults = array(
		1 => array(
			'title'    => 'Envio para todo o <strong>Brasil</strong>',
			'subtitle' => 'Despacho garantido em até 48h úteis',
			'icon'     => 'fa-truck-fast',
		),
		2 => array(
			'title'    => '<strong>10%</strong> de desconto à vista',
			'subtitle' => 'Condições especiais no Pix e boleto',
			'icon'     => 'fa-percent',
		),
		3 => array(
			'title'    => 'Parcelamento em até 21x',
			'subtitle' => 'Cartões principais + garantia antifraude',
			'icon'     => 'fa-credit-card',
		),
	);

	$benefits = array();
	foreach ( $defaults as $n => $default ) {
		$benefits[ $n ] = array(
			'title'    => wp_kses_post( get_option( "gstore_benefit_{$n}_title", $default['title'] ) ),
			'subtitle' => esc_html( get_option( "gstore_benefit_{$n}_subtitle", $default['subtitle'] ) ),
			'icon'     => $default['icon'],
		);
	}

	ob_start();
	?>
	<div class="wp-block-group alignfull Gstore-home-benefits">
		<div class="wp-block-group Gstore-home-benefits__inner">
			<?php foreach ( $benefits as $b ) : ?>
			<div class="wp-block-group Gstore-home-benefits__item">
				<span class="Gstore-home-benefits__icon" aria-hidden="true">
					<i class="fa-solid <?php echo esc_attr( $b['icon'] ); ?>"></i>
				</span>
				<div class="wp-block-group Gstore-home-benefits__content">
					<p class="Gstore-home-benefits__title"><?php echo $b['title']; ?></p>
					<p class="Gstore-home-benefits__subtitle"><?php echo $b['subtitle']; ?></p>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<div class="Gstore-home-benefits__slider" data-gstore-benefits-slider data-gstore-benefits-force-autoplay>
			<div class="Gstore-home-benefits__slider-track" data-gstore-benefits-track>
				<?php foreach ( $benefits as $b ) : ?>
				<div class="Gstore-home-benefits__slider-slide" data-gstore-benefits-slide>
					<div class="wp-block-group Gstore-home-benefits__item">
						<span class="Gstore-home-benefits__icon" aria-hidden="true">
							<i class="fa-solid <?php echo esc_attr( $b['icon'] ); ?>"></i>
						</span>
						<div class="wp-block-group Gstore-home-benefits__content">
							<p class="Gstore-home-benefits__title"><?php echo $b['title']; ?></p>
							<p class="Gstore-home-benefits__subtitle"><?php echo $b['subtitle']; ?></p>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<button class="Gstore-home-benefits__slider-control Gstore-home-benefits__slider-control--prev" type="button" aria-label="Benefício anterior" data-gstore-benefits-prev>
				<span aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span>
			</button>
			<button class="Gstore-home-benefits__slider-control Gstore-home-benefits__slider-control--next" type="button" aria-label="Próximo benefício" data-gstore-benefits-next>
				<span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
			</button>
			<div class="Gstore-home-benefits__slider-dots" role="tablist">
				<?php foreach ( $benefits as $n => $b ) :
					$i = $n - 1;
					$active    = ( 0 === $i ) ? ' is-active' : '';
					$selected  = ( 0 === $i ) ? 'true' : 'false';
				?>
				<button class="Gstore-home-benefits__slider-dot<?php echo $active; ?>" type="button" role="tab" aria-label="Mostrar benefício <?php echo $n; ?>" aria-selected="<?php echo $selected; ?>" data-gstore-benefits-dot="<?php echo $i; ?>"></button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gstore_home_benefits', 'gstore_home_benefits_shortcode' );

/**
 * Normaliza o HTML das seções da Home para evitar que wrappers inválidos
 * (<p>, <br> ou declaração XML vazando do DOMDocument) quebrem a montagem
 * quando uma seção dinâmica é posicionada após o banner do YouTube.
 *
 * @param string $html HTML bruto da seção.
 * @return string HTML normalizado.
 */
function gstore_normalize_home_section_output( $html ) {
	if ( ! is_string( $html ) ) {
		return '';
	}

	$html = trim( $html );
	if ( '' === $html ) {
		return '';
	}

	// Remove declarações XML que podem vazar do DOMDocument no meio da Home.
	$html = preg_replace( '#<\?xml[^>]*\?>#i', '', $html );

	if ( function_exists( 'gstore_cleanup_shortcode_paragraphs' ) ) {
		$html = gstore_cleanup_shortcode_paragraphs( $html );
	}

	$html = gstore_remove_br_from_banner_figure( $html );

	if ( class_exists( 'DOMDocument' ) ) {
		libxml_use_internal_errors( true );

		$dom        = new DOMDocument();
		$wrapper_id = 'gstore-home-fragment-root';
		$loaded     = $dom->loadHTML(
			'<?xml encoding="UTF-8"?><div id="' . $wrapper_id . '">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		if ( $loaded ) {
			$xpath      = new DOMXPath( $dom );
			$root_nodes = $xpath->query( '//*[@id="' . $wrapper_id . '"]' );
			$root       = ( $root_nodes instanceof DOMNodeList && $root_nodes->length > 0 ) ? $root_nodes->item( 0 ) : null;

			if ( $root instanceof DOMNode ) {
				$paragraph_nodes = $xpath->query( './/p', $root );
				$paragraphs      = array();

				if ( $paragraph_nodes instanceof DOMNodeList ) {
					foreach ( $paragraph_nodes as $paragraph ) {
						if ( $paragraph instanceof DOMElement ) {
							$paragraphs[] = $paragraph;
						}
					}
				}

				$block_tags = array(
					'article',
					'div',
					'figure',
					'footer',
					'h1',
					'h2',
					'h3',
					'h4',
					'h5',
					'h6',
					'header',
					'nav',
					'ol',
					'section',
					'style',
					'ul',
				);

				foreach ( $paragraphs as $paragraph ) {
					$has_block_child = false;

					foreach ( $block_tags as $tag_name ) {
						if ( $paragraph->getElementsByTagName( $tag_name )->length > 0 ) {
							$has_block_child = true;
							break;
						}
					}

					$text_content = trim( preg_replace( '/\s+/u', ' ', (string) $paragraph->textContent ) );
					$should_unwrap = $has_block_child || '' === $text_content;

					if ( ! $should_unwrap || ! $paragraph->parentNode ) {
						continue;
					}

					while ( $paragraph->firstChild ) {
						$paragraph->parentNode->insertBefore( $paragraph->firstChild, $paragraph );
					}

					$paragraph->parentNode->removeChild( $paragraph );
				}

				$normalized = '';
				foreach ( $root->childNodes as $child ) {
					$normalized .= $dom->saveHTML( $child );
				}

				if ( '' !== trim( $normalized ) ) {
					$html = $normalized;
				}
			}
		}

		libxml_clear_errors();
	}

	$html = preg_replace( '#<\?xml[^>]*\?>#i', '', $html );
	$html = preg_replace( '#<p>\s*</p>#i', '', $html );

	return trim( gstore_resolve_home_relative_urls( $html ) );
}

/**
 * Renderiza as seções da Home na ordem configurada no admin (via plugin),
 * com fallback para a ordem fixa quando o plugin estiver inativo/sem config.
 *
 * Uso: [gstore_home_sections]
 *
 * @return string
 */
function gstore_home_sections_shortcode() {
	$normalize_output = function( $html ) {
		return function_exists( 'gstore_normalize_home_section_output' )
			? gstore_normalize_home_section_output( $html )
			: $html;
	};

	$fallback_render = function() {
		// Helper local: carrega um template part HTML do tema e renderiza os blocos.
		$render_part = function( $template_path ) {
			$markup = gstore_load_template_part( $template_path );
			if ( empty( $markup ) ) {
				return '';
			}
			$rendered = function_exists( 'do_blocks' ) ? do_blocks( $markup ) : $markup;
			// Garante execução de shortcodes que eventualmente permaneçam no output.
			$rendered = do_shortcode( $rendered );
			// Resolve links relativos (href="/...") para instalações em subdiretório.
			return gstore_resolve_home_relative_urls( $rendered );
		};

		$out  = '';
		$out .= $render_part( 'parts/home-lancamentos.html' );
		$out .= $render_part( 'parts/home-promocoes.html' );
		$out .= $render_part( 'parts/home-equipamentos.html' );
		$out .= do_shortcode( '[gstore_banner_youtube]' );
		$out .= $render_part( 'parts/home-blog.html' );
		return $out;
	};

	// Normaliza diferentes formatos de "enabled" sem quebrar a Home.
	$is_enabled = function( $value, $default = true ) {
		if ( null === $value ) {
			return (bool) $default;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (int) $value !== 0;
		}
		if ( is_string( $value ) ) {
			$v = strtolower( trim( $value ) );
			if ( '' === $v ) {
				return (bool) $default;
			}
			if ( in_array( $v, array( '0', 'false', 'no', 'off' ), true ) ) {
				return false;
			}
			if ( in_array( $v, array( '1', 'true', 'yes', 'on' ), true ) ) {
				return true;
			}
		}
		return (bool) $value;
	};

	try {
		$sections = function_exists( 'gstore_get_home_sections_config' )
			? gstore_get_home_sections_config()
			: array();
	} catch ( Throwable $e ) {
		return $normalize_output( $fallback_render() );
	}

	// Fallback: mantém exatamente a ordem antiga quando não houver configuração.
	if ( empty( $sections ) ) {
		return $normalize_output( $fallback_render() );
	}

	// Permite o plugin retornar objeto/estrutura aninhada.
	if ( is_object( $sections ) ) {
		$sections = (array) $sections;
	}
	if ( ! is_array( $sections ) ) {
		return $normalize_output( $fallback_render() );
	}
	if ( isset( $sections['sections'] ) && ( is_array( $sections['sections'] ) || is_object( $sections['sections'] ) ) ) {
		$sections = is_object( $sections['sections'] ) ? (array) $sections['sections'] : $sections['sections'];
	}

	// Se vier como mapa (associativo), mantém só os valores para iterar na ordem.
	$keys = array_keys( $sections );
	$is_list = ( $keys === range( 0, count( $sections ) - 1 ) );
	if ( ! $is_list ) {
		$sections = array_values( $sections );
	}

	$out = '';

	// Helper local: carrega um template part HTML do tema e renderiza os blocos.
	$render_part = function( $template_path ) {
		$markup = gstore_load_template_part( $template_path );
		if ( empty( $markup ) ) {
			return '';
		}
		$rendered = function_exists( 'do_blocks' ) ? do_blocks( $markup ) : $markup;
		// Garante execução de shortcodes que eventualmente permaneçam no output.
		$rendered = do_shortcode( $rendered );
		// Resolve links relativos (href="/...") para instalações em subdiretório.
		return gstore_resolve_home_relative_urls( $rendered );
	};

	foreach ( $sections as $section ) {
		if ( is_object( $section ) ) {
			$section = (array) $section;
		}
		if ( ! is_array( $section ) ) {
			continue;
		}

		// Se "enabled" não vier, assume ligado (não derruba a Home).
		$enabled_value = array_key_exists( 'enabled', $section ) ? $section['enabled'] : null;
		if ( ! $is_enabled( $enabled_value, true ) ) {
			continue;
		}

		$type = isset( $section['type'] ) ? (string) $section['type'] : '';
		// Inferência: se não vier "type", tenta deduzir pelo conteúdo.
		if ( '' === $type ) {
			if ( isset( $section['key'] ) ) {
				$type = 'builtin';
			} elseif ( isset( $section['category_id'] ) ) {
				$type = 'wc_category';
			}
		}

		if ( 'builtin' === $type ) {
			$key = isset( $section['key'] ) ? (string) $section['key'] : '';

			switch ( $key ) {
				case 'lancamentos':
					$out .= $normalize_output( $render_part( 'parts/home-lancamentos.html' ) );
					break;
				case 'promocoes':
					$out .= $normalize_output( $render_part( 'parts/home-promocoes.html' ) );
					break;
				case 'equipamentos_taticos':
					$out .= $normalize_output( $render_part( 'parts/home-equipamentos.html' ) );
					break;
				case 'blog':
					$out .= $normalize_output( $render_part( 'parts/home-blog.html' ) );
					break;
				case 'youtube_banner':
					$out .= $normalize_output( do_shortcode( '[gstore_banner_youtube]' ) );
					break;
				default:
					// Tipo/key desconhecidos não podem matar a Home.
					continue 2;
			}
		} elseif ( 'wc_category' === $type ) {
			$cat_id = (int) ( $section['category_id'] ?? 0 );
			if ( $cat_id > 0 ) {
				$title = (string) ( $section['title'] ?? '' );
				if ( function_exists( 'gstore_render_home_category_section' ) ) {
					$out .= $normalize_output( gstore_render_home_category_section( $cat_id, $title ) );
				}
			}
		} else {
			// Tipo novo no futuro: ignora sem quebrar a Home.
			continue;
		}
	}

	// Se por algum motivo não renderizou nada, cai no fallback (evita Home vazia).
	return '' !== trim( $out ) ? $normalize_output( $out ) : $normalize_output( $fallback_render() );
}
add_shortcode( 'gstore_home_sections', 'gstore_home_sections_shortcode' );

/**
 * Renderiza uma seção de vitrine de produtos por categoria (product_cat) para a Home.
 *
 * @param int    $cat_id ID da categoria (term_id em product_cat).
 * @param string $title  Título opcional (se vazio, usa o nome da categoria).
 * @return string
 */
function gstore_render_home_category_section( $cat_id, $title = '' ) {
	$cat_id = absint( $cat_id );
	if ( $cat_id <= 0 ) {
		return '';
	}

	$term = get_term( $cat_id, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	$term_link = get_term_link( $term );
	if ( is_wp_error( $term_link ) ) {
		$term_link = '';
	}

	$section_title = is_string( $title ) ? trim( $title ) : '';
	if ( '' === $section_title ) {
		$section_title = $term->name;
	}

	$slug = isset( $term->slug ) ? (string) $term->slug : '';
	if ( '' === $slug ) {
		return '';
	}

	$products_shortcode = sprintf(
		'[products limit="8" columns="4" category="%s" stock_status="instock" paginate="false"]',
		esc_attr( $slug )
	);

	$button_block = '';
	if ( ! empty( $term_link ) ) {
		$button_block = sprintf(
			'<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"32px"}}},"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons" style="margin-top:32px">
	<!-- wp:button {"className":"is-style-primary"} -->
	<div class="wp-block-button is-style-primary"><a class="wp-block-button__link wp-element-button" href="%s">%s</a></div>
	<!-- /wp:button -->
</div>
<!-- /wp:buttons -->',
			esc_url( $term_link ),
			esc_html__( 'Ver mais produtos', 'gstore' )
		);
	}

	// Gera markup de blocos para manter o mesmo "padrão" (constrained/wideSize/classes) das seções existentes.
	$block_markup = sprintf(
		'<!-- wp:group {"align":"full","className":"Gstore-home-section Gstore-home-products Gstore-products-shell","style":{"spacing":{"padding":{"top":"48px","bottom":"56px","left":"0px","right":"0px"}}},"layout":{"type":"constrained","wideSize":"1280px"}} -->
<div class="wp-block-group alignfull Gstore-home-section Gstore-home-products Gstore-products-shell" style="padding-top:48px;padding-bottom:56px;padding-left:0px;padding-right:0px">
	<!-- wp:group {"className":"Gstore-home-section__header Gstore-home-section__header--left","layout":{"type":"constrained","contentSize":"1280px"}} -->
	<div class="wp-block-group Gstore-home-section__header Gstore-home-section__header--left">
		<!-- wp:html -->
		<div class="Gstore-home-section__title-wrapper">
			<h2 class="wp-block-heading has-text-align-left has-x-large-font-size">%s</h2>
			<span class="Gstore-home-section__separator" aria-hidden="true"></span>
		</div>
		<!-- /wp:html -->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"Gstore-products-grid","layout":{"type":"default"}} -->
	<div class="wp-block-group Gstore-products-grid">
		<!-- wp:shortcode -->
		%s
		<!-- /wp:shortcode -->
	</div>
	<!-- /wp:group -->
	%s
</div>
<!-- /wp:group -->',
		esc_html( $section_title ),
		$products_shortcode,
		$button_block
	);

	$rendered = function_exists( 'do_blocks' ) ? do_blocks( $block_markup ) : $block_markup;
	$rendered = do_shortcode( $rendered );
	// Resolve links relativos (href="/...") para instalações em subdiretório.
	$rendered = gstore_resolve_home_relative_urls( $rendered );
	$rendered = preg_replace( '#<br\s*/?>#i', '', $rendered );

	return function_exists( 'gstore_normalize_home_section_output' )
		? gstore_normalize_home_section_output( $rendered )
		: $rendered;
}

/**
 * Remove <br> tags de dentro de elementos figure com classe Gstore-home-transition.
 *
 * @param string $content Conteúdo HTML.
 * @return string Conteúdo processado.
 */
function gstore_remove_br_from_banner_figure( $content ) {
	// Remove <br> tags dentro de figure com classe Gstore-home-transition
	$content = preg_replace(
		'#(<figure[^>]*class="[^"]*Gstore-home-transition[^"]*"[^>]*>.*?)(<br\s*/?>)(.*?</figure>)#is',
		'$1$3',
		$content
	);

	return $content;
}
add_filter( 'the_content', 'gstore_remove_br_from_banner_figure', 20 );
add_filter( 'render_block', 'gstore_remove_br_from_banner_figure', 20 );

/**
 * ============================================
 * PÁGINA DE CONFIGURAÇÕES DO TEMA - ADMIN
 * ============================================
 */

/**
 * Compatibilidade: a tela administrativa da Vitrine foi migrada para o plugin.
 */
function gstore_add_theme_settings_page() {
	// Tela migrada para o plugin em Loja -> Vitrine.
}
// A tela administrativa da Vitrine agora e registrada pelo plugin em Loja -> Vitrine.
// Mantemos a funcao e as opcoes abaixo como compatibilidade para o frontend do tema.

/**
 * Define o capability necessário para salvar/alterar opções do grupo gstore_settings via options.php.
 *
 * @return string
 */
function gstore_settings_option_page_capability() {
	return 'manage_woocommerce';
}
add_filter( 'option_page_capability_gstore_settings', 'gstore_settings_option_page_capability' );

/**
 * Registra as opções do tema.
 */
function gstore_register_theme_settings() {
	// Hero Slider - Configurações de quantidade
	register_setting( 'gstore_settings', 'gstore_hero_slides_desktop_count', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 2,
	) );
	register_setting( 'gstore_settings', 'gstore_hero_slides_mobile_count', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 2,
	) );

	// Hero Slider - Slides Desktop (até 10)
	for ( $i = 1; $i <= 10; $i++ ) {
		register_setting( 'gstore_settings', "gstore_hero_desktop_slide_{$i}_id", array(
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 0,
		) );
		register_setting( 'gstore_settings', "gstore_hero_desktop_slide_{$i}_alt", array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		) );
		register_setting( 'gstore_settings', "gstore_hero_desktop_slide_{$i}_link", array(
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default' => '',
		) );
	}

	// Hero Slider - Slides Mobile (até 10)
	for ( $i = 1; $i <= 10; $i++ ) {
		register_setting( 'gstore_settings', "gstore_hero_mobile_slide_{$i}_id", array(
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 0,
		) );
		register_setting( 'gstore_settings', "gstore_hero_mobile_slide_{$i}_alt", array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		) );
		register_setting( 'gstore_settings', "gstore_hero_mobile_slide_{$i}_link", array(
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default' => '',
		) );
	}

	// Banner YouTube
	register_setting( 'gstore_settings', 'gstore_banner_youtube_id', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 0,
	) );
	register_setting( 'gstore_settings', 'gstore_banner_youtube_alt', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => 'Banner do YouTube',
	) );
	register_setting( 'gstore_settings', 'gstore_banner_youtube_link', array(
		'type' => 'string',
		'sanitize_callback' => 'esc_url_raw',
		'default' => '',
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
		'default' => 'Logo da loja',
	) );

	// Cor de Accent para Design Tokens
	register_setting( 'gstore_design_tokens', 'gstore_accent_color', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_hex_color',
		'default' => '#b5a642',
	) );
}
add_action( 'admin_init', 'gstore_register_theme_settings' );

/**
 * Limpa o cache do LiteSpeed (homepage) quando opções de slides/banners da Vitrine são atualizadas.
 * Usa do_action('litespeed_purge', ...) que é seguro mesmo sem o plugin ativo.
 */
function gstore_purge_litespeed_on_slide_change( $old_value, $new_value ) {
	if ( $old_value === $new_value ) {
		return;
	}

	// Incrementa versão do banner para cache-busting nas URLs das imagens
	$version = absint( get_option( 'gstore_banner_cache_version', 0 ) );
	update_option( 'gstore_banner_cache_version', $version + 1, true );

	// Purge LiteSpeed cache — múltiplos métodos para garantir limpeza completa
	do_action( 'litespeed_purge', 'frontpage' );
	do_action( 'litespeed_purge', 'home' );
	do_action( 'litespeed_purge_url', home_url( '/' ) );

	// Limpa object cache do WordPress para as opções de banner
	wp_cache_flush();
}

// Registra o hook para todas as opções de slides desktop, mobile e banner youtube
add_action( 'admin_init', function() {
	$options_to_watch = array( 'gstore_hero_slides_desktop_count', 'gstore_hero_slides_mobile_count', 'gstore_banner_youtube_id', 'gstore_banner_youtube_alt', 'gstore_banner_youtube_link' );
	for ( $i = 1; $i <= 10; $i++ ) {
		$options_to_watch[] = "gstore_hero_desktop_slide_{$i}_id";
		$options_to_watch[] = "gstore_hero_desktop_slide_{$i}_alt";
		$options_to_watch[] = "gstore_hero_desktop_slide_{$i}_link";
		$options_to_watch[] = "gstore_hero_mobile_slide_{$i}_id";
		$options_to_watch[] = "gstore_hero_mobile_slide_{$i}_alt";
		$options_to_watch[] = "gstore_hero_mobile_slide_{$i}_link";
	}
	foreach ( $options_to_watch as $opt ) {
		add_action( "update_option_{$opt}", 'gstore_purge_litespeed_on_slide_change', 10, 2 );
	}
} );

/**
 * Renderiza a página de configurações.
 */
function gstore_render_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	if ( is_admin() ) {
		wp_safe_redirect( admin_url( 'admin.php?page=gstore-vitrine' ) );
		exit;
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
						<?php gstore_render_media_selector( 'gstore_logo_id', 'gstore_logo_alt', get_option( 'gstore_logo_id', 0 ), get_option( 'gstore_logo_alt', 'Logo da loja' ) ); ?>
					</td>
				</tr>
			</table>

			<h2 class="title"><?php _e( 'Hero Slider - Slides da Pag. Inicial', 'gstore' ); ?></h2>
			<p class="description"><?php _e( 'Configure as imagens do slider principal. Arraste os slides para reordenar ou use as setas.', 'gstore' ); ?></p>

			<?php
			$desktop_count = absint( get_option( 'gstore_hero_slides_desktop_count', 2 ) );
			$mobile_count  = absint( get_option( 'gstore_hero_slides_mobile_count', 2 ) );
			?>

			<!-- Hidden fields para armazenar a ordem dos slides -->
			<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
				<input type="hidden" name="gstore_hero_desktop_slide_<?php echo $i; ?>_order" value="<?php echo $i; ?>" class="gstore-slide-order-field" data-device="desktop" data-slot="<?php echo $i; ?>" />
				<input type="hidden" name="gstore_hero_mobile_slide_<?php echo $i; ?>_order" value="<?php echo $i; ?>" class="gstore-slide-order-field" data-device="mobile" data-slot="<?php echo $i; ?>" />
			<?php endfor; ?>

			<style>
				.gstore-hero-manager { margin-top: 20px; max-width: 1000px; }

				/* Tab buttons grandes e com cor diferente por device */
				.gstore-hero-tabs { display: flex; gap: 0; margin-bottom: 0; }
				.gstore-hero-tab-btn {
					display: flex; align-items: center; gap: 8px;
					padding: 14px 28px; border: 2px solid #c3c4c7; border-bottom: none;
					border-radius: 8px 8px 0 0; cursor: pointer;
					font-size: 14px; font-weight: 700; transition: all .2s;
					background: #f6f7f7; color: #50575e; position: relative;
				}
				.gstore-hero-tab-btn .dashicons { font-size: 22px; width: 22px; height: 22px; }
				.gstore-hero-tab-btn:hover { background: #e8e8e8; }
				.gstore-hero-tab-btn.active[data-device="desktop"] {
					background: #e7f3ff; color: #0a4b78; border-color: #2271b1;
					z-index: 2;
				}
				.gstore-hero-tab-btn.active[data-device="mobile"] {
					background: #fef3e7; color: #6e3a00; border-color: #dba617;
					z-index: 2;
				}

				/* Painel do conteúdo - cor muda com o device ativo */
				.gstore-hero-panel {
					border: 2px solid #c3c4c7; border-radius: 0 8px 8px 8px;
					padding: 0; display: none; position: relative; margin-top: -2px;
				}
				.gstore-hero-panel.active { display: block; }
				.gstore-hero-panel[data-device="desktop"] { border-color: #2271b1; }
				.gstore-hero-panel[data-device="mobile"]  { border-color: #dba617; }

				/* Device banner no topo do painel */
				.gstore-hero-panel-header {
					padding: 14px 20px; display: flex; align-items: center; justify-content: space-between;
					gap: 16px; flex-wrap: wrap;
				}
				.gstore-hero-panel[data-device="desktop"] .gstore-hero-panel-header { background: #e7f3ff; }
				.gstore-hero-panel[data-device="mobile"]  .gstore-hero-panel-header { background: #fef3e7; }

				.gstore-hero-device-badge {
					display: inline-flex; align-items: center; gap: 6px;
					padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;
					text-transform: uppercase; letter-spacing: .5px;
				}
				.gstore-hero-panel[data-device="desktop"] .gstore-hero-device-badge {
					background: #2271b1; color: #fff;
				}
				.gstore-hero-panel[data-device="mobile"] .gstore-hero-device-badge {
					background: #dba617; color: #fff;
				}
				.gstore-hero-device-badge .dashicons { font-size: 16px; width: 16px; height: 16px; }

				.gstore-hero-panel-header .gstore-hero-size-hint {
					font-size: 12px; color: #646970; display: flex; align-items: center; gap: 4px;
				}
				.gstore-hero-panel-header .gstore-hero-count-wrap {
					display: flex; align-items: center; gap: 8px;
				}
				.gstore-hero-panel-header .gstore-hero-count-wrap label {
					font-size: 13px; font-weight: 600; white-space: nowrap;
				}
				.gstore-hero-panel-header .gstore-hero-count-wrap select {
					min-width: 100px;
				}

				/* Lista de slides (drag & drop) */
				.gstore-hero-slides-list {
					padding: 16px 20px; display: flex; flex-direction: column; gap: 12px;
				}

				/* Card de cada slide */
				.gstore-hero-slide-card {
					display: grid; grid-template-columns: 40px 130px 1fr auto; gap: 14px;
					align-items: start; padding: 14px; background: #fff; border: 1px solid #dcdcde;
					border-radius: 6px; cursor: grab; transition: box-shadow .15s, opacity .15s;
				}
				.gstore-hero-slide-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
				.gstore-hero-slide-card.dragging { opacity: .4; }
				.gstore-hero-slide-card.drag-over { border-color: #2271b1; box-shadow: 0 0 0 2px rgba(34,113,177,.25); }
				.gstore-hero-panel[data-device="mobile"] .gstore-hero-slide-card.drag-over {
					border-color: #dba617; box-shadow: 0 0 0 2px rgba(219,166,23,.25);
				}

				/* Handle + posicao */
				.gstore-hero-slide-handle {
					display: flex; flex-direction: column; align-items: center; gap: 4px;
					padding-top: 4px;
				}
				.gstore-hero-slide-handle .gstore-drag-icon {
					font-size: 20px; color: #a0a5aa; line-height: 1; cursor: grab;
				}
				.gstore-hero-slide-position {
					background: #f0f0f1; color: #50575e; font-size: 11px; font-weight: 700;
					width: 24px; height: 24px; border-radius: 50%; display: flex;
					align-items: center; justify-content: center;
				}
				.gstore-hero-slide-arrows {
					display: flex; flex-direction: column; gap: 2px;
				}
				.gstore-hero-slide-arrows button {
					background: none; border: 1px solid #dcdcde; border-radius: 3px;
					cursor: pointer; padding: 0; width: 22px; height: 18px;
					display: flex; align-items: center; justify-content: center;
					color: #50575e; font-size: 14px; line-height: 1;
				}
				.gstore-hero-slide-arrows button:hover { background: #f0f0f1; }
				.gstore-hero-slide-arrows button:disabled { opacity: .3; cursor: default; }

				/* Preview da imagem */
				.gstore-hero-slide-preview {
					width: 130px; height: 80px; border: 2px dashed #ccc; border-radius: 4px;
					display: flex; align-items: center; justify-content: center;
					background: #f9f9f9; overflow: hidden; flex-shrink: 0;
				}
				.gstore-hero-slide-preview.has-image { border-color: #2271b1; border-style: solid; }
				.gstore-hero-panel[data-device="mobile"] .gstore-hero-slide-preview.has-image {
					border-color: #dba617;
				}
				.gstore-hero-slide-preview img { width: 100%; height: 100%; object-fit: cover; }
				.gstore-hero-slide-preview .gstore-no-img { font-size: 11px; color: #999; text-align: center; padding: 4px; }

				/* Campos do slide */
				.gstore-hero-slide-fields { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
				.gstore-hero-slide-fields .gstore-field-row {
					display: flex; flex-direction: column; gap: 3px;
				}
				.gstore-hero-slide-fields label { font-size: 11px; font-weight: 600; color: #1d2327; }
				.gstore-hero-slide-fields input[type="text"],
				.gstore-hero-slide-fields input[type="url"] {
					width: 100%; padding: 4px 8px; font-size: 13px;
				}
				.gstore-hero-slide-fields .gstore-slide-btns {
					display: flex; gap: 6px; flex-wrap: wrap; margin-top: 2px;
				}
				.gstore-hero-slide-fields .gstore-slide-btns .button { font-size: 12px; padding: 2px 10px; }
				.gstore-hero-slide-fields .gstore-slide-img-id { font-size: 11px; color: #646970; }

				/* Acao remover (coluna da direita) */
				.gstore-hero-slide-actions {
					display: flex; flex-direction: column; align-items: center; gap: 6px; padding-top: 4px;
				}
				.gstore-hero-slide-actions .button-link-delete { font-size: 11px; }

				@media (max-width: 782px) {
					.gstore-hero-slide-card {
						grid-template-columns: 36px 90px 1fr;
						gap: 10px;
					}
					.gstore-hero-slide-actions { grid-column: 1 / -1; flex-direction: row; justify-content: flex-end; }
					.gstore-hero-tabs { flex-direction: column; }
					.gstore-hero-tab-btn { border-radius: 0; border-bottom: none; }
					.gstore-hero-tab-btn:first-child { border-radius: 8px 8px 0 0; }
					.gstore-hero-panel { border-radius: 0 0 8px 8px; }
				}
			</style>

			<div class="gstore-hero-manager">
				<!-- Tabs -->
				<div class="gstore-hero-tabs">
					<div class="gstore-hero-tab-btn active" data-device="desktop">
						<span class="dashicons dashicons-desktop"></span>
						<?php _e( 'Slides Desktop', 'gstore' ); ?>
						<span style="font-size: 11px; font-weight: 400; opacity: .7;">(<?php echo $desktop_count; ?>)</span>
					</div>
					<div class="gstore-hero-tab-btn" data-device="mobile">
						<span class="dashicons dashicons-smartphone"></span>
						<?php _e( 'Slides Mobile', 'gstore' ); ?>
						<span style="font-size: 11px; font-weight: 400; opacity: .7;">(<?php echo $mobile_count; ?>)</span>
					</div>
				</div>

				<?php foreach ( array( 'desktop', 'mobile' ) as $device ) :
					$count_key   = "gstore_hero_slides_{$device}_count";
					$count_val   = $device === 'desktop' ? $desktop_count : $mobile_count;
					$icon        = $device === 'desktop' ? 'dashicons-desktop' : 'dashicons-smartphone';
					$size_hint   = $device === 'desktop' ? '1920x600px' : '768x900px (vertical)';
					$is_active   = $device === 'desktop' ? ' active' : '';
				?>
				<!-- Painel <?php echo ucfirst( $device ); ?> -->
				<div class="gstore-hero-panel<?php echo $is_active; ?>" data-device="<?php echo $device; ?>">
					<div class="gstore-hero-panel-header">
						<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
							<span class="gstore-hero-device-badge">
								<span class="dashicons <?php echo $icon; ?>"></span>
								<?php echo $device === 'desktop' ? 'Desktop' : 'Mobile'; ?>
							</span>
							<span class="gstore-hero-size-hint">
								<span class="dashicons dashicons-info-outline" style="font-size: 16px; width: 16px; height: 16px;"></span>
								<?php printf( __( 'Tamanho recomendado: %s', 'gstore' ), $size_hint ); ?>
							</span>
						</div>
						<div class="gstore-hero-count-wrap">
							<label for="<?php echo esc_attr( $count_key ); ?>">
								<?php _e( 'Qtd. de slides:', 'gstore' ); ?>
							</label>
							<select id="<?php echo esc_attr( $count_key ); ?>" name="<?php echo esc_attr( $count_key ); ?>" class="gstore-hero-count-select" data-device="<?php echo $device; ?>">
								<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
									<option value="<?php echo $i; ?>" <?php selected( $count_val, $i ); ?>>
										<?php echo $i; ?>
									</option>
								<?php endfor; ?>
							</select>
						</div>
					</div>

					<div class="gstore-hero-slides-list" data-device="<?php echo $device; ?>">
						<?php for ( $i = 1; $i <= 10; $i++ ) :
							$prefix    = "gstore_hero_{$device}_slide_{$i}";
							$slide_id  = absint( get_option( "{$prefix}_id", 0 ) );
							$slide_alt = get_option( "{$prefix}_alt", '' );
							$slide_link = get_option( "{$prefix}_link", '' );
							$is_visible = $i <= $count_val;
							$has_image  = $slide_id > 0;
							$image_url  = $has_image ? wp_get_attachment_image_url( $slide_id, 'medium' ) : '';
						?>
						<div class="gstore-hero-slide-card" data-slot="<?php echo $i; ?>" data-device="<?php echo $device; ?>" style="<?php echo ! $is_visible ? 'display:none;' : ''; ?>" draggable="true">
							<!-- Handle + posicao -->
							<div class="gstore-hero-slide-handle">
								<span class="gstore-drag-icon" title="<?php esc_attr_e( 'Arraste para reordenar', 'gstore' ); ?>">&#x2630;</span>
								<span class="gstore-hero-slide-position"><?php echo $i; ?></span>
								<div class="gstore-hero-slide-arrows">
									<button type="button" class="gstore-slide-move-up" title="<?php esc_attr_e( 'Mover para cima', 'gstore' ); ?>" <?php echo $i === 1 ? 'disabled' : ''; ?>>&uarr;</button>
									<button type="button" class="gstore-slide-move-down" title="<?php esc_attr_e( 'Mover para baixo', 'gstore' ); ?>" <?php echo $i === $count_val ? 'disabled' : ''; ?>>&darr;</button>
								</div>
							</div>

							<!-- Preview -->
							<div class="gstore-hero-slide-preview<?php echo $has_image ? ' has-image' : ''; ?>" id="<?php echo esc_attr( $prefix ); ?>_box_preview" data-input-id="<?php echo esc_attr( $prefix ); ?>_id">
								<?php if ( $image_url ) : ?>
									<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $slide_alt ); ?>" />
								<?php else : ?>
									<span class="gstore-no-img"><?php _e( 'Sem imagem', 'gstore' ); ?></span>
								<?php endif; ?>
							</div>

							<!-- Campos -->
							<div class="gstore-hero-slide-fields">
								<input type="hidden" id="<?php echo esc_attr( $prefix ); ?>_id" name="<?php echo esc_attr( $prefix ); ?>_id" value="<?php echo esc_attr( $slide_id ); ?>" />

								<div class="gstore-slide-btns">
									<button type="button" class="button gstore-select-media" data-input-id="<?php echo esc_attr( $prefix ); ?>_id" data-preview-id="<?php echo esc_attr( $prefix ); ?>_box_preview">
										<?php _e( 'Selecionar Imagem', 'gstore' ); ?>
									</button>
									<button type="button" class="button gstore-remove-media" data-input-id="<?php echo esc_attr( $prefix ); ?>_id" data-preview-id="<?php echo esc_attr( $prefix ); ?>_box_preview" style="<?php echo $has_image ? '' : 'display:none;'; ?>">
										<?php _e( 'Remover', 'gstore' ); ?>
									</button>
									<span class="gstore-slide-img-id">
										ID: <strong><?php echo $slide_id ? $slide_id : '---'; ?></strong>
									</span>
								</div>

								<div class="gstore-field-row">
									<label for="<?php echo esc_attr( $prefix ); ?>_alt"><?php _e( 'Texto Alt', 'gstore' ); ?></label>
									<input type="text" id="<?php echo esc_attr( $prefix ); ?>_alt" name="<?php echo esc_attr( $prefix ); ?>_alt" value="<?php echo esc_attr( $slide_alt ); ?>" placeholder="<?php esc_attr_e( 'Descricao da imagem', 'gstore' ); ?>" />
								</div>

								<div class="gstore-field-row">
									<label for="<?php echo esc_attr( $prefix ); ?>_link"><?php _e( 'Link (opcional)', 'gstore' ); ?></label>
									<input type="url" id="<?php echo esc_attr( $prefix ); ?>_link" name="<?php echo esc_attr( $prefix ); ?>_link" value="<?php echo esc_attr( $slide_link ); ?>" placeholder="https://..." />
								</div>
							</div>
						</div>
						<?php endfor; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

			<script>
			jQuery(document).ready(function($) {
				// === Tabs ===
				$('.gstore-hero-tab-btn').on('click', function() {
					var device = $(this).data('device');
					$('.gstore-hero-tab-btn').removeClass('active');
					$(this).addClass('active');
					$('.gstore-hero-panel').removeClass('active');
					$('.gstore-hero-panel[data-device="' + device + '"]').addClass('active');
				});

				// === Quantidade de slides ===
				$('.gstore-hero-count-select').on('change', function() {
					var device = $(this).data('device');
					var count = parseInt($(this).val());
					var $list = $('.gstore-hero-slides-list[data-device="' + device + '"]');
					$list.find('.gstore-hero-slide-card').each(function() {
						var slot = parseInt($(this).data('slot'));
						$(this).toggle(slot <= count);
					});
					// Atualiza label da tab
					$('.gstore-hero-tab-btn[data-device="' + device + '"] span:last').text('(' + count + ')');
					// Atualiza setas
					updateArrows(device);
				});

				// === Setas mover cima/baixo ===
				$(document).on('click', '.gstore-slide-move-up', function(e) {
					e.stopPropagation();
					var $card = $(this).closest('.gstore-hero-slide-card');
					var $prev = $card.prevAll('.gstore-hero-slide-card:visible').first();
					if ($prev.length) {
						$card.insertBefore($prev);
						syncAfterReorder($card.closest('.gstore-hero-slides-list').data('device'));
					}
				});
				$(document).on('click', '.gstore-slide-move-down', function(e) {
					e.stopPropagation();
					var $card = $(this).closest('.gstore-hero-slide-card');
					var $next = $card.nextAll('.gstore-hero-slide-card:visible').first();
					if ($next.length) {
						$card.insertAfter($next);
						syncAfterReorder($card.closest('.gstore-hero-slides-list').data('device'));
					}
				});

				// === Drag & Drop ===
				var dragSrc = null;

				$(document).on('dragstart', '.gstore-hero-slide-card', function(e) {
					dragSrc = this;
					$(this).addClass('dragging');
					e.originalEvent.dataTransfer.effectAllowed = 'move';
					e.originalEvent.dataTransfer.setData('text/plain', '');
				});
				$(document).on('dragend', '.gstore-hero-slide-card', function() {
					$(this).removeClass('dragging');
					$('.gstore-hero-slide-card').removeClass('drag-over');
					dragSrc = null;
				});
				$(document).on('dragover', '.gstore-hero-slide-card', function(e) {
					e.preventDefault();
					e.originalEvent.dataTransfer.dropEffect = 'move';
					if (this !== dragSrc && $(this).is(':visible')) {
						$('.gstore-hero-slide-card').removeClass('drag-over');
						$(this).addClass('drag-over');
					}
				});
				$(document).on('dragleave', '.gstore-hero-slide-card', function() {
					$(this).removeClass('drag-over');
				});
				$(document).on('drop', '.gstore-hero-slide-card', function(e) {
					e.preventDefault();
					e.stopPropagation();
					if (dragSrc && this !== dragSrc) {
						var $src = $(dragSrc);
						var $dst = $(this);
						// Verifica se mesmo device
						if ($src.closest('.gstore-hero-slides-list').data('device') !== $dst.closest('.gstore-hero-slides-list').data('device')) return;

						var srcRect = dragSrc.getBoundingClientRect();
						var dstRect = this.getBoundingClientRect();
						if (srcRect.top < dstRect.top) {
							$src.insertAfter($dst);
						} else {
							$src.insertBefore($dst);
						}
						syncAfterReorder($src.closest('.gstore-hero-slides-list').data('device'));
					}
					$('.gstore-hero-slide-card').removeClass('drag-over');
				});

				// === Sync apos reordenar ===
				function syncAfterReorder(device) {
					var $list = $('.gstore-hero-slides-list[data-device="' + device + '"]');
					var visibleIdx = 1;

					$list.find('.gstore-hero-slide-card').each(function() {
						var $card = $(this);
						if ($card.is(':visible')) {
							// Atualiza o numero de posicao visual
							$card.find('.gstore-hero-slide-position').text(visibleIdx);

							// Atualiza os name attributes dos campos para o novo slot
							var oldSlot = $card.data('slot');
							var newSlot = visibleIdx;

							if (oldSlot !== newSlot) {
								$card.data('slot', newSlot);
								// Renomeia todos os inputs/selects dentro do card
								$card.find('input, select').each(function() {
									var $input = $(this);
									var name = $input.attr('name');
									var id = $input.attr('id');
									if (name) {
										$input.attr('name', name.replace(/_slide_\d+_/, '_slide_' + newSlot + '_'));
									}
									if (id) {
										$input.attr('id', id.replace(/_slide_\d+_/, '_slide_' + newSlot + '_'));
									}
								});
								// Renomeia labels
								$card.find('label').each(function() {
									var $label = $(this);
									var forAttr = $label.attr('for');
									if (forAttr) {
										$label.attr('for', forAttr.replace(/_slide_\d+_/, '_slide_' + newSlot + '_'));
									}
								});
								// Renomeia preview id e data attributes dos botoes
								$card.find('.gstore-hero-slide-preview').each(function() {
									var $p = $(this);
									var pid = $p.attr('id');
									if (pid) $p.attr('id', pid.replace(/_slide_\d+_/, '_slide_' + newSlot + '_'));
									var did = $p.data('input-id');
									if (did) $p.data('input-id', did.replace(/_slide_\d+_/, '_slide_' + newSlot + '_'));
								});
								$card.find('.gstore-select-media, .gstore-remove-media').each(function() {
									var $b = $(this);
									var di = $b.data('input-id');
									var dp = $b.data('preview-id');
									if (di) $b.data('input-id', di.replace(/_slide_\d+_/, '_slide_' + newSlot + '_'));
									if (dp) $b.data('preview-id', dp.replace(/_slide_\d+_/, '_slide_' + newSlot + '_'));
								});
							}

							visibleIdx++;
						}
					});
					updateArrows(device);
				}

				function updateArrows(device) {
					var $list = $('.gstore-hero-slides-list[data-device="' + device + '"]');
					var $visible = $list.find('.gstore-hero-slide-card:visible');
					$visible.find('.gstore-slide-move-up').prop('disabled', false);
					$visible.find('.gstore-slide-move-down').prop('disabled', false);
					$visible.first().find('.gstore-slide-move-up').prop('disabled', true);
					$visible.last().find('.gstore-slide-move-down').prop('disabled', true);
				}

				// Init arrows
				updateArrows('desktop');
				updateArrows('mobile');
			});
			</script>

			<h2 class="title"><?php _e( 'Banners', 'gstore' ); ?></h2>
			<p class="description"><?php _e( 'Configure os banners exibidos no site.', 'gstore' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="gstore_banner_youtube_id"><?php _e( 'Banner YouTube', 'gstore' ); ?></label>
					</th>
					<td>
						<?php gstore_render_media_selector( 'gstore_banner_youtube_id', 'gstore_banner_youtube_alt', get_option( 'gstore_banner_youtube_id', 0 ), get_option( 'gstore_banner_youtube_alt', 'Banner do YouTube' ) ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gstore_banner_youtube_link"><?php _e( 'Link do Banner', 'gstore' ); ?></label>
					</th>
					<td>
						<input type="url" id="gstore_banner_youtube_link" name="gstore_banner_youtube_link" value="<?php echo esc_attr( get_option( 'gstore_banner_youtube_link', '' ) ); ?>" class="regular-text" placeholder="https://..." />
						<p class="description"><?php _e( 'URL para onde o banner deve redirecionar quando clicado. Deixe em branco se não quiser que o banner seja clicável.', 'gstore' ); ?></p>
					</td>
				</tr>
			</table>

			<?php do_action( 'gstore_vitrine_settings_after_banners' ); ?>

			<?php submit_button( __( 'Salvar Configurações', 'gstore' ) ); ?>
		</form>

		<hr style="margin: 40px 0;" />

		<h2 class="title"><?php _e( 'Informações da Loja (JSON)', 'gstore' ); ?></h2>
		<p class="description">
			<?php _e( 'Gerencie as informações centralizadas da loja (nome, contatos, footer, etc). Útil para migrar configurações entre lojas.', 'gstore' ); ?>
		</p>

		<div class="gstore-store-info-actions" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
			<!-- Exportar -->
			<div class="gstore-export-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; flex: 1; min-width: 280px;">
				<h3 style="margin-top: 0;"><?php _e( 'Exportar Configurações', 'gstore' ); ?></h3>
				<p><?php _e( 'Baixe um arquivo JSON com todas as informações da loja para backup ou migração.', 'gstore' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gstore_export_store_info" />
					<?php wp_nonce_field( 'gstore_export_store_info', 'gstore_export_nonce' ); ?>
					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-download" style="margin-right: 5px; vertical-align: middle;"></span>
						<?php _e( 'Exportar JSON', 'gstore' ); ?>
					</button>
				</form>
			</div>

			<!-- Importar -->
			<div class="gstore-import-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; flex: 1; min-width: 280px;">
				<h3 style="margin-top: 0;"><?php _e( 'Importar Configurações', 'gstore' ); ?></h3>
				<p><?php _e( 'Carregue um arquivo JSON previamente exportado para atualizar as informações da loja.', 'gstore' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="gstore_import_store_info" />
					<?php wp_nonce_field( 'gstore_import_store_info', 'gstore_import_nonce' ); ?>
					<input type="file" name="store_info_file" accept=".json" required style="margin-bottom: 10px; display: block;" />
					<button type="submit" class="button button-secondary">
						<span class="dashicons dashicons-upload" style="margin-right: 5px; vertical-align: middle;"></span>
						<?php _e( 'Importar JSON', 'gstore' ); ?>
					</button>
				</form>
			</div>
		</div>

		<!-- Preview dos dados atuais -->
		<div class="gstore-current-info" style="margin-top: 30px;">
			<h3><?php _e( 'Dados Atuais da Loja', 'gstore' ); ?></h3>
			<p class="description"><?php _e( 'Pré-visualização das informações configuradas no arquivo JSON.', 'gstore' ); ?></p>

			<?php
			$store_info = gstore_store_info();
			$data = $store_info->get_all();
			?>

			<table class="widefat" style="max-width: 800px; margin-top: 15px;">
				<tbody>
					<tr>
						<th style="width: 200px;"><?php _e( 'Nome da Loja', 'gstore' ); ?></th>
						<td><?php echo esc_html( $data['store']['name'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'CNPJ', 'gstore' ); ?></th>
						<td><?php echo esc_html( $data['store']['cnpj'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'WhatsApp', 'gstore' ); ?></th>
						<td><?php echo esc_html( $data['contact']['whatsapp_display'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'Instagram', 'gstore' ); ?></th>
						<td>@<?php echo esc_html( $data['social']['instagram'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'Endereço', 'gstore' ); ?></th>
						<td><?php echo esc_html( gstore_get_address( 'short' ) ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'Horário de Funcionamento', 'gstore' ); ?></th>
						<td><?php echo esc_html( $data['business_hours']['full_text'] ?? '' ); ?></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top: 15px;">
				<a href="<?php echo esc_url( admin_url( 'theme-editor.php?file=store-info.json&theme=' . get_stylesheet() ) ); ?>" class="button button-link" target="_blank">
					<span class="dashicons dashicons-edit" style="margin-right: 5px; vertical-align: middle;"></span>
					<?php _e( 'Editar JSON diretamente', 'gstore' ); ?>
				</a>
				<span class="description" style="margin-left: 10px;"><?php _e( '(Cuidado: edição manual pode quebrar a estrutura)', 'gstore' ); ?></span>
			</p>
		</div>

		<hr style="margin: 40px 0;" />

		<h2 class="title"><?php _e( 'Sincronização GitHub', 'gstore' ); ?></h2>
		<p class="description">
			<?php _e( 'Mantenha os arquivos do tema atualizados diretamente do repositório GitHub.', 'gstore' ); ?>
		</p>

		<div class="gstore-github-sync" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-top: 20px; max-width: 800px;">
			<h3 style="margin-top: 0;"><?php _e( 'Atualizar Tema via Git', 'gstore' ); ?></h3>
			<p><?php _e( 'Este comando irá executar um "git pull" (fetch + reset --hard) para sincronizar os arquivos locais com a versão mais recente do branch principal no GitHub.', 'gstore' ); ?></p>

			<div style="margin-top: 20px;">
				<button type="button" class="button button-primary gstore-theme-git-update" data-nonce="<?php echo esc_attr( wp_create_nonce( 'gstore_theme_git_pull' ) ); ?>">
					<span class="dashicons dashicons-cloud-upload" style="margin-right: 5px; vertical-align: middle;"></span>
					<?php _e( 'Sincronizar Agora', 'gstore' ); ?>
				</button>
				<p class="description" style="margin-top: 10px;">
					<?php _e( 'Atenção: Quaisquer alterações locais não commitadas serão perdidas.', 'gstore' ); ?>
				</p>
			</div>
		</div>
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
 * Renderiza o seletor de mídia com campo de link.
 */
function gstore_render_media_selector_with_link( $input_id, $alt_input_id, $link_input_id, $current_id = 0, $current_alt = '', $current_link = '' ) {
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
			</div>

			<div class="gstore-link-field" style="margin-top: 10px;">
				<label for="<?php echo esc_attr( $link_input_id ); ?>">
					<?php _e( 'Link do Slide (opcional)', 'gstore' ); ?>
				</label>
				<input type="url" id="<?php echo esc_attr( $link_input_id ); ?>" name="<?php echo esc_attr( $link_input_id ); ?>" value="<?php echo esc_attr( $current_link ); ?>" class="regular-text" placeholder="https://..." />
				<p class="description"><?php _e( 'URL para onde o slide deve redirecionar quando clicado.', 'gstore' ); ?></p>
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

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
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
/**
 * Funções para o Hero Slider com suporte a Desktop/Mobile.
 */

/**
 * Obtém a quantidade de slides configurada.
 *
 * @param string $device 'desktop' ou 'mobile'.
 * @return int Quantidade de slides.
 */
function gstore_get_hero_slides_count( $device = 'desktop' ) {
	$option_name = 'gstore_hero_slides_' . $device . '_count';
	return absint( get_option( $option_name, 2 ) );
}

/**
 * Obtém dados de um slide específico.
 *
 * @param string $device 'desktop' ou 'mobile'.
 * @param int    $index  Índice do slide (1-10).
 * @return array Array com 'id', 'alt', 'link', 'url'.
 */
function gstore_get_hero_slide( $device, $index ) {
	$prefix = "gstore_hero_{$device}_slide_{$index}";
	$slide_id = absint( get_option( "{$prefix}_id", 0 ) );

	$data = array(
		'id'   => $slide_id,
		'alt'  => get_option( "{$prefix}_alt", '' ),
		'link' => get_option( "{$prefix}_link", '' ),
		'url'  => '',
	);

	if ( $slide_id > 0 ) {
		$data['url'] = wp_get_attachment_url( $slide_id );
	}

	return $data;
}

/**
 * Obtém todos os slides configurados para um dispositivo.
 *
 * @param string $device 'desktop' ou 'mobile'.
 * @return array Array de slides.
 */
function gstore_get_hero_slides( $device = 'desktop' ) {
	$count = gstore_get_hero_slides_count( $device );
	$slides = array();

	for ( $i = 1; $i <= $count; $i++ ) {
		$slide = gstore_get_hero_slide( $device, $i );
		if ( $slide['id'] > 0 ) {
			$slides[] = $slide;
		}
	}

	return $slides;
}

/**
 * Gera o HTML do Hero Slider com suporte a Desktop/Mobile.
 *
 * @return string HTML do slider.
 */
function gstore_render_hero_slider() {
	$desktop_slides = gstore_get_hero_slides( 'desktop' );
	$mobile_slides = gstore_get_hero_slides( 'mobile' );
	$desktop_total = count( $desktop_slides );
	$mobile_total = count( $mobile_slides );

	// Se não houver slides, retorna vazio
	if ( empty( $desktop_slides ) && empty( $mobile_slides ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="Gstore-hero-slider" data-gstore-hero-slider data-gstore-hero-force-autoplay>
		<!-- Desktop Slides -->
		<div class="Gstore-hero-slider__track Gstore-hero-slider__track--desktop" data-gstore-hero-track>
			<?php
			$is_first = true;
			foreach ( $desktop_slides as $slide ) :
				$img_tag = gstore_get_hero_image_tag( $slide['id'], $slide['alt'], $is_first );
			?>
				<figure class="Gstore-hero-slider__slide" data-gstore-hero-slide>
					<?php if ( ! empty( $slide['link'] ) ) : ?>
						<a href="<?php echo esc_url( $slide['link'] ); ?>">
							<?php echo $img_tag; ?>
						</a>
					<?php else : ?>
						<?php echo $img_tag; ?>
					<?php endif; ?>
				</figure>
			<?php
				$is_first = false;
			endforeach;
			?>
		</div>

		<!-- Mobile Slides -->
		<div class="Gstore-hero-slider__track Gstore-hero-slider__track--mobile" data-gstore-hero-track-mobile>
			<?php
			$is_first = true;
			foreach ( $mobile_slides as $slide ) :
				$img_tag = gstore_get_hero_image_tag( $slide['id'], $slide['alt'], $is_first );
			?>
				<figure class="Gstore-hero-slider__slide" data-gstore-hero-slide-mobile>
					<?php if ( ! empty( $slide['link'] ) ) : ?>
						<a href="<?php echo esc_url( $slide['link'] ); ?>">
							<?php echo $img_tag; ?>
						</a>
					<?php else : ?>
						<?php echo $img_tag; ?>
					<?php endif; ?>
				</figure>
			<?php
				$is_first = false;
			endforeach;
			?>
		</div>

		<?php if ( $desktop_total > 1 || $mobile_total > 1 ) : ?>
			<button class="Gstore-hero-slider__control Gstore-hero-slider__control--prev" type="button" aria-label="Slide anterior" data-gstore-hero-prev>
				<span aria-hidden="true">
					<i class="fa-solid fa-chevron-left"></i>
				</span>
			</button>

			<button class="Gstore-hero-slider__control Gstore-hero-slider__control--next" type="button" aria-label="Próximo slide" data-gstore-hero-next>
				<span aria-hidden="true">
					<i class="fa-solid fa-chevron-right"></i>
				</span>
			</button>

			<!-- Desktop Dots -->
			<?php if ( $desktop_total > 1 ) : ?>
				<div class="Gstore-hero-slider__dots Gstore-hero-slider__dots--desktop" role="tablist" data-gstore-hero-dots-total="<?php echo esc_attr( $desktop_total ); ?>">
					<?php if ( $desktop_total <= 3 ) : ?>
						<?php for ( $i = 0; $i < $desktop_total; $i++ ) : ?>
							<button class="Gstore-hero-slider__dot <?php echo $i === 0 ? 'is-active' : ''; ?>" type="button" role="tab" aria-label="Mostrar slide <?php echo $i + 1; ?>" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" data-gstore-hero-dot="<?php echo $i; ?>"></button>
						<?php endfor; ?>
					<?php else : ?>
						<button class="Gstore-hero-slider__dot is-active" type="button" role="tab" aria-label="Mostrar slide 1" aria-selected="true" data-gstore-hero-dot="0"></button>
						<button class="Gstore-hero-slider__dot" type="button" role="tab" aria-label="Mostrar slides do meio" aria-selected="false" data-gstore-hero-dot="middle"></button>
						<button class="Gstore-hero-slider__dot" type="button" role="tab" aria-label="Mostrar último slide" aria-selected="false" data-gstore-hero-dot="<?php echo esc_attr( $desktop_total - 1 ); ?>"></button>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Mobile Dots -->
			<?php if ( $mobile_total > 1 ) : ?>
				<div class="Gstore-hero-slider__dots Gstore-hero-slider__dots--mobile" role="tablist" data-gstore-hero-dots-total-mobile="<?php echo esc_attr( $mobile_total ); ?>">
					<?php if ( $mobile_total <= 3 ) : ?>
						<?php for ( $i = 0; $i < $mobile_total; $i++ ) : ?>
							<button class="Gstore-hero-slider__dot <?php echo $i === 0 ? 'is-active' : ''; ?>" type="button" role="tab" aria-label="Mostrar slide <?php echo $i + 1; ?>" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" data-gstore-hero-dot-mobile="<?php echo $i; ?>"></button>
						<?php endfor; ?>
					<?php else : ?>
						<button class="Gstore-hero-slider__dot is-active" type="button" role="tab" aria-label="Mostrar slide 1" aria-selected="true" data-gstore-hero-dot-mobile="0"></button>
						<button class="Gstore-hero-slider__dot" type="button" role="tab" aria-label="Mostrar slides do meio" aria-selected="false" data-gstore-hero-dot-mobile="middle"></button>
						<button class="Gstore-hero-slider__dot" type="button" role="tab" aria-label="Mostrar último slide" aria-selected="false" data-gstore-hero-dot-mobile="<?php echo esc_attr( $mobile_total - 1 ); ?>"></button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

// Funções de compatibilidade com o sistema antigo (deprecated)
function gstore_get_hero_slide_1_id() {
	// Tenta obter do novo sistema primeiro
	$slide = gstore_get_hero_slide( 'desktop', 1 );
	return $slide['id'];
}

function gstore_get_hero_slide_2_id() {
	// Tenta obter do novo sistema primeiro
	$slide = gstore_get_hero_slide( 'desktop', 2 );
	return $slide['id'];
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

	// Evita processamento duplicado - se já contém a marca de logo customizada
	if ( strpos( $block_content, 'data-gstore-logo="1"' ) !== false ) {
		return $block_content;
	}

	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();

	if ( $logo_id > 0 ) {
		$logo_url = gstore_get_image_url( $logo_id, 'full' );
		$logo_alt = get_option( 'gstore_logo_alt', 'Logo da loja' );

		// Valida se a URL é válida
		if ( $logo_url && filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
			// Substitui o conteúdo do bloco pela logo configurada
			$home_url = esc_url( home_url( '/' ) );
			$site_name = esc_attr( get_bloginfo( 'name' ) );
			$logo_html = sprintf(
				'<div class="wp-block-site-logo Gstore-header__logo" data-gstore-logo="1" style="grid-area: logo;"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 36px; max-width: 180px; width: auto; height: auto;" loading="eager" /></a></div>',
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
 * Filtro para modificar o bloco site-logo no footer para usar a logo configurada.
 *
 * @param string $block_content Conteúdo do bloco.
 * @param array  $block         Dados do bloco.
 * @return string
 */
function gstore_custom_footer_logo_block( $block_content, $block ) {
	// Verifica se é o bloco site-logo
	if ( empty( $block['blockName'] ) || 'core/site-logo' !== $block['blockName'] ) {
		return $block_content;
	}

	// Verifica se está no footer (pela classe footer-logo)
	$is_in_footer = false;
	if ( isset( $block['attrs']['className'] ) && strpos( $block['attrs']['className'], 'footer-logo' ) !== false ) {
		$is_in_footer = true;
	}

	// Se não está no footer, não modifica
	if ( ! $is_in_footer ) {
		return $block_content;
	}

	// Evita processamento duplicado - se já contém a marca de logo customizada
	if ( strpos( $block_content, 'data-gstore-footer-logo="1"' ) !== false ) {
		return $block_content;
	}

	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();

	if ( $logo_id > 0 ) {
		$logo_url = gstore_get_image_url( $logo_id, 'full' );
		$logo_alt = get_option( 'gstore_logo_alt', 'Logo da loja' );

		// Valida se a URL é válida
		if ( $logo_url && filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
			// Substitui o conteúdo do bloco pela logo configurada
			$home_url = esc_url( home_url( '/' ) );
			$site_name = esc_attr( get_bloginfo( 'name' ) );
			$logo_html = sprintf(
				'<div class="wp-block-site-logo footer-logo" data-gstore-footer-logo="1"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 50px; max-width: 200px; width: auto; height: auto;" loading="lazy" /></a></div>',
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
add_filter( 'render_block', 'gstore_custom_footer_logo_block', 10, 2 );

/**
 * Filtro para modificar o bloco site-title no checkout header para usar a logo configurada.
 *
 * @param string $block_content Conteúdo do bloco.
 * @param array  $block         Dados do bloco.
 * @return string
 */
function gstore_custom_checkout_header_logo_block( $block_content, $block ) {
	// Verifica se é o bloco site-title
	if ( empty( $block['blockName'] ) || 'core/site-title' !== $block['blockName'] ) {
		return $block_content;
	}

	// Verifica se está no checkout header (pela classe Gstore-checkout-header__logo)
	$is_in_checkout_header = false;
	if ( isset( $block['attrs']['className'] ) && strpos( $block['attrs']['className'], 'Gstore-checkout-header__logo' ) !== false ) {
		$is_in_checkout_header = true;
	}

	// Se não está no checkout header, não modifica
	if ( ! $is_in_checkout_header ) {
		return $block_content;
	}

	// Evita processamento duplicado - se já contém a marca de logo customizada
	if ( strpos( $block_content, 'data-gstore-checkout-logo="1"' ) !== false ) {
		return $block_content;
	}

	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();

	if ( $logo_id > 0 ) {
		$logo_url = gstore_get_image_url( $logo_id, 'full' );
		$logo_alt = get_option( 'gstore_logo_alt', 'Logo da loja' );

		// Valida se a URL é válida
		if ( $logo_url && filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
			// Substitui o conteúdo do bloco pela logo configurada
			$home_url = esc_url( home_url( '/' ) );
			$site_name = esc_attr( get_bloginfo( 'name' ) );
			$logo_html = sprintf(
				'<p class="Gstore-checkout-header__logo wp-block-site-title" data-gstore-checkout-logo="1"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 36px; max-width: 180px; width: auto; height: auto;" loading="eager" /></a></p>',
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
add_filter( 'render_block', 'gstore_custom_checkout_header_logo_block', 10, 2 );

/**
 * Filtro para garantir que o logo do tema tenha prioridade sobre o Customizer.
 *
 * Quando há uma logo configurada no tema, desabilita o site logo do Customizer.
 */
add_filter( 'theme_mod_custom_logo', 'gstore_override_customizer_logo', 10, 1 );
function gstore_override_customizer_logo( $logo_id ) {
	$theme_logo_id = gstore_get_logo_id();

	// Se há uma logo configurada no tema, usa ela ao invés do Customizer
	if ( $theme_logo_id > 0 ) {
		return $theme_logo_id;
	}

	return $logo_id;
}

/**
 * Substitui o link de texto da logo pela imagem configurada no header HTML.
 *
 * @param string $content Conteúdo do template part.
 * @return string
 */
function gstore_replace_header_logo_html( $content ) {
	// Evita processamento duplicado - se já contém a marca de logo customizada
	if ( strpos( $content, 'data-gstore-logo="1"' ) !== false ) {
		return $content;
	}

	// Evita processar se já contém uma imagem de logo
	if ( preg_match( '/<a[^>]*class="[^"]*Gstore-header__logo[^"]*"[^>]*>.*?<img[^>]*>/is', $content ) ) {
		return $content;
	}

	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();

	if ( $logo_id <= 0 ) {
		return $content;
	}

	$logo_url = gstore_get_image_url( $logo_id, 'full' );
	$logo_alt = get_option( 'gstore_logo_alt', 'Logo da loja' );

	// Valida se a URL é válida
	if ( ! $logo_url || ! filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
		return $content;
	}

	$home_url = esc_url( home_url( '/' ) );
	$site_name = esc_attr( get_bloginfo( 'name' ) );

	// HTML da logo com imagem (para substituição via regex)
	$logo_html = sprintf(
		'<div class="wp-block-site-logo Gstore-header__logo" data-gstore-logo="1" style="grid-area: logo;"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 36px; max-width: 180px; width: auto; height: auto;" loading="eager" /></a></div>',
		$home_url,
		$site_name,
		esc_url( $logo_url ),
		esc_attr( $logo_alt )
	);

	// Padrão 1: Substitui apenas o bloco site-logo renderizado pelo WordPress
	// Captura: <div class="wp-block-site-logo...">...</div> (bloco completo)
	$pattern1 = '/<div\s+[^>]*class="[^"]*wp-block-site-logo[^"]*"[^>]*>.*?<\/div>/is';
	$replacement1 = '<div class="wp-block-site-logo Gstore-header__logo" data-gstore-logo="1" style="grid-area: logo;"><a href="' . $home_url . '" rel="home" aria-label="' . $site_name . '"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $logo_alt ) . '" style="max-height: 36px; max-width: 180px; width: auto; height: auto;" loading="eager" /></a></div>';
	$content = preg_replace( $pattern1, $replacement1, $content, 1 );

	// Padrão 2: Link com classe Gstore-header__logo (mas sem imagem) - apenas dentro do header content
	// Captura: <a href="/" class="Gstore-header__logo">TEXTO</a> (sem img dentro)
	// Limita a busca apenas dentro do Gstore-header__content para evitar capturar elementos errados
	if ( preg_match( '/(<div[^>]*class="[^"]*Gstore-header__content[^"]*"[^>]*>)(.*?)(<\/div>)/is', $content, $header_content_match ) ) {
		$header_content = $header_content_match[2];
		// Padrão que captura o link completo incluindo elementos filhos (span, etc)
		$pattern2 = '/<a\s+[^>]*class="[^"]*Gstore-header__logo[^"]*"[^>]*>(?!.*?<img)(?:[^<]|<(?!\/a>))*?<\/a>/is';
		$header_content_new = preg_replace( $pattern2, $logo_html, $header_content, 1 );
		if ( $header_content_new !== $header_content ) {
			$content = str_replace( $header_content_match[0], $header_content_match[1] . $header_content_new . $header_content_match[3], $content );
		}
	} else {
		// Padrão 2b: Se não encontrar Gstore-header__content, procura diretamente no header
		// Captura: <a href="/" class="Gstore-header__logo">ARMA<span>STORE</span></a> (sem img dentro)
		// Apenas dentro de elementos com classe Gstore-header para evitar substituir outros links
		if ( strpos( $content, 'Gstore-header' ) !== false ) {
			// Padrão que captura o link completo incluindo elementos filhos (span, etc)
			$pattern2b = '/<a\s+[^>]*class="[^"]*Gstore-header__logo[^"]*"[^>]*>(?!.*?<img)(?:[^<]|<(?!\/a>))*?<\/a>/is';
			$content = preg_replace( $pattern2b, $logo_html, $content, 1 );
		}
	}

	return $content;
}
// Remove o filtro de render_block para evitar conflito com gstore_custom_site_logo_block
// Mantém apenas nos outros filtros
add_filter( 'render_block_core/template-part', 'gstore_replace_header_logo_html', 10, 1 );
add_filter( 'the_content', 'gstore_replace_header_logo_html', 5 );

/**
 * Substitui o texto da logo pela imagem configurada no footer HTML.
 *
 * @param string $content Conteúdo do template part.
 * @return string
 */
function gstore_replace_footer_logo_html( $content ) {
	// Verifica se é o footer (pela classe armastore-footer)
	if ( strpos( $content, 'armastore-footer' ) === false ) {
		return $content;
	}

	// Evita processamento duplicado - se já contém a marca de logo customizada
	if ( strpos( $content, 'data-gstore-footer-logo="1"' ) !== false ) {
		return $content;
	}

	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();

	if ( $logo_id <= 0 ) {
		return $content;
	}

	$logo_url = gstore_get_image_url( $logo_id, 'full' );
	$logo_alt = get_option( 'gstore_logo_alt', 'Logo da loja' );

	// Valida se a URL é válida
	if ( ! $logo_url || ! filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
		return $content;
	}

	$home_url = esc_url( home_url( '/' ) );
	$site_name = esc_attr( get_bloginfo( 'name' ) );

	// HTML da logo com imagem para o footer
	$logo_html = sprintf(
		'<div class="wp-block-site-logo footer-logo" data-gstore-footer-logo="1"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 50px; max-width: 200px; width: auto; height: auto;" loading="lazy" /></a></div>',
		$home_url,
		$site_name,
		esc_url( $logo_url ),
		esc_attr( $logo_alt )
	);

	// Padrão 1: Substitui o bloco site-logo renderizado pelo WordPress no footer
	// Procura especificamente dentro do primeiro footer-column (onde está a logo)
	// Usa uma regex mais específica para capturar apenas o primeiro footer-column dentro do footer-main
	if ( preg_match( '/(<div[^>]*class="[^"]*footer-main[^"]*"[^>]*>)(.*?)(<\/div>)/is', $content, $footer_main_match ) ) {
		$footer_main_content = $footer_main_match[2];

		// Captura o primeiro footer-column
		if ( preg_match( '/(<div[^>]*class="[^"]*footer-column[^"]*"[^>]*>)(.*?)(<\/div>)/is', $footer_main_content, $footer_column_match ) ) {
			$footer_column_content = $footer_column_match[2];

			// Substitui o bloco site-logo se existir
			$pattern1 = '/<div\s+[^>]*class="[^"]*wp-block-site-logo[^"]*footer-logo[^"]*"[^>]*>.*?<\/div>/is';
			$footer_column_content_new = preg_replace( $pattern1, $logo_html, $footer_column_content, 1 );

			// Se houve substituição, atualiza o conteúdo
			if ( $footer_column_content_new !== $footer_column_content ) {
				$footer_main_content_new = str_replace( $footer_column_match[0], $footer_column_match[1] . $footer_column_content_new . $footer_column_match[3], $footer_main_content );
				$content = str_replace( $footer_main_match[0], $footer_main_match[1] . $footer_main_content_new . $footer_main_match[3], $content );
			}
		}
	}

	return $content;
}
add_filter( 'render_block_core/template-part', 'gstore_replace_footer_logo_html', 10, 1 );
add_filter( 'the_content', 'gstore_replace_footer_logo_html', 5 );

/**
 * Gera tag de imagem otimizada para hero com srcset e priorização.
 *
 * @param int    $attachment_id ID da imagem.
 * @param string $alt           Texto alternativo.
 * @param bool   $is_first_slide Se true, adiciona fetchpriority="high" e remove lazy loading.
 * @return string Tag img otimizada.
 */
function gstore_get_hero_image_tag( $attachment_id, $alt = '', $is_first_slide = false ) {
	if ( ! $attachment_id ) {
		return '';
	}

	$image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! $image_meta ) {
		return '';
	}

	// Versão para cache-busting (muda a cada atualização de banner)
	$cache_version = absint( get_option( 'gstore_banner_cache_version', 0 ) );

	// Gera srcset com múltiplos tamanhos
	$srcset_sizes = array( 'medium_large', 'large', 'full' );
	$srcset_array = array();

	// Para hero, queremos tamanhos maiores
	foreach ( $srcset_sizes as $size ) {
		$size_url = gstore_get_image_url( $attachment_id, $size );
		$size_src = wp_get_attachment_image_src( $attachment_id, $size );

		if ( $size_url && $size_src && isset( $size_src[1] ) ) {
			$busted_url = add_query_arg( 'v', $cache_version, $size_url );
			$srcset_array[] = esc_url( $busted_url ) . ' ' . $size_src[1] . 'w';
		}
	}

	// URL principal (usa full como padrão)
	$src_url = gstore_get_image_url( $attachment_id, 'full' );
	if ( empty( $src_url ) ) {
		return '';
	}
	$src_url = add_query_arg( 'v', $cache_version, $src_url );

	// Atributos base
	$attr = array(
		'src' => $src_url,
		'alt' => $alt ? $alt : '',
	);

	// O primeiro slide é o LCP: força o arquivo full para evitar variação/pixelização inicial.
	// Demais slides podem usar srcset normalmente.
	if ( ! $is_first_slide ) {
		$attr['sizes'] = '100vw';
		if ( ! empty( $srcset_array ) ) {
			$attr['srcset'] = implode( ', ', $srcset_array );
		}
	}

	// Primeira imagem do hero: alta prioridade, sem lazy loading
	if ( $is_first_slide ) {
		$attr['fetchpriority'] = 'high';
		$attr['loading'] = 'eager';
		$attr['decoding'] = 'async';
		$attr['class'] = 'skip-lazy';
		$attr['data-no-lazy'] = '1';
		$attr['data-skip-lazy'] = '1';
	} else {
		$attr['loading'] = 'lazy';
		$attr['decoding'] = 'async';
	}

	// Width e height para evitar CLS
	$image_src = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( $image_src && isset( $image_src[1] ) && isset( $image_src[2] ) ) {
		$attr['width'] = $image_src[1];
		$attr['height'] = $image_src[2];
	}

	// Constrói a tag
	$img_tag = '<img';
	foreach ( $attr as $key => $value ) {
		if ( ! empty( $value ) || 'alt' === $key ) {
			$img_tag .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}
	$img_tag .= ' />';

	return $img_tag;
}

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

	// NOVO SISTEMA: Hero Slider dinâmico com Desktop/Mobile
	if ( strpos( $content, '{{gstore_hero_slider}}' ) !== false ) {
		$slider_html = gstore_render_hero_slider();
		$content = str_replace( '{{gstore_hero_slider}}', $slider_html, $content );
	}

	// Placeholders especiais que usam configurações do tema (SISTEMA LEGADO - mantido para compatibilidade)
	// {{gstore_hero_slide_1}}, {{gstore_hero_slide_2}}, {{gstore_banner_youtube}}
	$hero_slide_1_id = gstore_get_hero_slide_1_id();
	$hero_slide_2_id = gstore_get_hero_slide_2_id();
	$banner_youtube_id = gstore_get_banner_youtube_id();

	// Processa hero slides com otimização (srcset + priorização)
	if ( $hero_slide_1_id > 0 ) {
		$hero_slide_1_alt = esc_attr( get_option( 'gstore_hero_slide_1_alt', 'Banner principal da loja' ) );
		$hero_slide_1_tag = gstore_get_hero_image_tag( $hero_slide_1_id, $hero_slide_1_alt, true );

		// Substitui tag img completa que contém o placeholder (flexível com qualquer ordem de atributos)
		$pattern = '/<img\s+[^>]*src=["\']\{\{gstore_hero_slide_1\}\}["\'][^>]*>/is';
		$content = preg_replace( $pattern, $hero_slide_1_tag, $content );

		// Fallback: substitui apenas URL se tag não foi encontrada (para compatibilidade)
		if ( strpos( $content, '{{gstore_hero_slide_1}}' ) !== false ) {
			$hero_slide_1_url = wp_get_attachment_url( $hero_slide_1_id );
			$cache_v = absint( get_option( 'gstore_banner_cache_version', 0 ) );
			$hero_slide_1_url = add_query_arg( 'v', $cache_v, $hero_slide_1_url );
			$content = str_replace( '{{gstore_hero_slide_1}}', $hero_slide_1_url, $content );
		}
	} else {
		$content = str_replace( '{{gstore_hero_slide_1}}', '', $content );
	}

	if ( $hero_slide_2_id > 0 ) {
		$hero_slide_2_alt = esc_attr( get_option( 'gstore_hero_slide_2_alt', 'Banner promocional da loja' ) );
		$hero_slide_2_tag = gstore_get_hero_image_tag( $hero_slide_2_id, $hero_slide_2_alt, false );

		// Substitui tag img completa que contém o placeholder (flexível com qualquer ordem de atributos)
		$pattern = '/<img\s+[^>]*src=["\']\{\{gstore_hero_slide_2\}\}["\'][^>]*>/is';
		$content = preg_replace( $pattern, $hero_slide_2_tag, $content );

		// Fallback: substitui apenas URL se tag não foi encontrada (para compatibilidade)
		if ( strpos( $content, '{{gstore_hero_slide_2}}' ) !== false ) {
			$hero_slide_2_url = wp_get_attachment_url( $hero_slide_2_id );
			$cache_v = absint( get_option( 'gstore_banner_cache_version', 0 ) );
			$hero_slide_2_url = add_query_arg( 'v', $cache_v, $hero_slide_2_url );
			$content = str_replace( '{{gstore_hero_slide_2}}', $hero_slide_2_url, $content );
		}
	} else {
		$content = str_replace( '{{gstore_hero_slide_2}}', '', $content );
	}

	// Banner YouTube (não precisa de srcset, não é LCP)
	if ( $banner_youtube_id > 0 ) {
		$banner_youtube_url = wp_get_attachment_url( $banner_youtube_id );
		$cache_v = absint( get_option( 'gstore_banner_cache_version', 0 ) );
		$banner_youtube_url = add_query_arg( 'v', $cache_v, $banner_youtube_url );
		$banner_youtube_alt = esc_attr( get_option( 'gstore_banner_youtube_alt', 'Banner do YouTube' ) );
		$banner_youtube_link = esc_url( get_option( 'gstore_banner_youtube_link', '' ) );

		$img_tag = sprintf(
			'<img src="%s" alt="%s" />',
			esc_url( $banner_youtube_url ),
			$banner_youtube_alt
		);

		// Se houver link configurado, envolve a imagem em um link
		if ( ! empty( $banner_youtube_link ) ) {
			$img_tag = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				$banner_youtube_link,
				$img_tag
			);
		}

		// Substitui a tag img completa que contém o placeholder
		$pattern = '/<img\s+[^>]*src=["\']\{\{gstore_banner_youtube\}\}["\'][^>]*>/is';
		$content = preg_replace( $pattern, $img_tag, $content );

		// Fallback: substitui apenas URL se tag não foi encontrada (para compatibilidade)
		if ( strpos( $content, '{{gstore_banner_youtube}}' ) !== false ) {
			$content = str_replace( '{{gstore_banner_youtube}}', $banner_youtube_url, $content );
		}
	} else {
		$content = str_replace( '{{gstore_banner_youtube}}', '', $content );
	}

	// Placeholders para textos alternativos (para uso em outros contextos)
	$content = str_replace( '{{gstore_hero_slide_1_alt}}', esc_attr( get_option( 'gstore_hero_slide_1_alt', 'Banner principal da loja' ) ), $content );
	$content = str_replace( '{{gstore_hero_slide_2_alt}}', esc_attr( get_option( 'gstore_hero_slide_2_alt', 'Banner promocional da loja' ) ), $content );
	$content = str_replace( '{{gstore_banner_youtube_alt}}', esc_attr( get_option( 'gstore_banner_youtube_alt', 'Banner do YouTube' ) ), $content );

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
 * Garante que a busca do tema (core/search) sempre envie para /catalogo (produtos).
 *
 * Importante: só altera blocos core/search com classes do tema (Gstore-*),
 * para não afetar outras buscas (ex.: blog, widgets etc.).
 * Mesmo em páginas de categoria geradas (ex.: /clube-de-tiro/), a busca
 * deve ser global no catálogo completo.
 */
add_filter( 'render_block_core/search', 'gstore_search_block_action_to_catalog', 10, 2 );
function gstore_search_block_action_to_catalog( $block_content, $block ) {
	if ( empty( $block_content ) || ! is_string( $block_content ) ) {
		return $block_content;
	}

	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$class = isset( $attrs['className'] ) ? (string) $attrs['className'] : '';

	$target_classes = array(
		'Gstore-header__search',
		'Gstore-nav__search',
		'Gstore-catalog-search',
	);

	$is_theme_search = false;
	foreach ( $target_classes as $needle ) {
		if ( ( $class && strpos( $class, $needle ) !== false ) || strpos( $block_content, $needle ) !== false ) {
			$is_theme_search = true;
			break;
		}
	}

	if ( ! $is_theme_search ) {
		return $block_content;
	}

	$catalog_url = esc_url( gstore_get_catalog_url() );

	// Substitui o action do form.
	$updated = preg_replace(
		'/(<form\b[^>]*\baction=)(["\']).*?\2/i',
		'$1$2' . $catalog_url . '$2',
		$block_content,
		1
	);

	// Fallback: se não existir action, injeta após a abertura do <form ...>.
	if ( $updated === $block_content ) {
		$updated = preg_replace(
			'/(<form\b)([^>]*)(>)/i',
			'$1$2 action="' . $catalog_url . '"$3',
			$block_content,
			1
		);
	}

	// Troca o parâmetro padrão de busca do WP (s) por um parâmetro do catálogo (q),
	// para evitar conflito entre /catalogo e o modo search do WordPress.
	// Mantém o tipo de aspas (simples/duplas).
	$updated = preg_replace(
		'/(<input\b[^>]*\bname=)(["\'])s\2/i',
		'$1$2q$2',
		$updated,
		1
	);

	return $updated;
}

/**
 * Detecta se a pagina atual e um catalogo de categoria principal gerado pelo painel.
 *
 * @return bool
 */
function gstore_is_generated_category_catalog_page() {
	if ( ! function_exists( 'is_page' ) || ! is_page() ) {
		return false;
	}

	$page = get_queried_object();
	if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type ) {
		return false;
	}

	return (bool) get_post_meta( $page->ID, '_gstore_category_catalog_generated', true );
}

/**
 * Redireciona rotas paralelas de categoria principal para o archive nativo do WooCommerce.
 */
add_action( 'template_redirect', 'gstore_redirect_generated_category_catalog_to_native_archive', 0 );
function gstore_redirect_generated_category_catalog_to_native_archive() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	$scope_slug = get_query_var( 'gstore_catalog_scope', '' );
	$scope_slug = is_string( $scope_slug ) ? sanitize_title( $scope_slug ) : '';

	if ( '' === $scope_slug && function_exists( 'gstore_is_generated_category_catalog_page' ) && gstore_is_generated_category_catalog_page() ) {
		$page = get_queried_object();
		if ( $page instanceof WP_Post ) {
			$scope_slug = sanitize_title( (string) get_post_meta( $page->ID, '_gstore_category_catalog_term_slug', true ) );
			if ( '' === $scope_slug ) {
				$scope_slug = sanitize_title( (string) $page->post_name );
			}
		}
	}

	if ( '' === $scope_slug || ! taxonomy_exists( 'product_cat' ) ) {
		return;
	}

	$term = get_term_by( 'slug', $scope_slug, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}

	$target = get_term_link( $term, 'product_cat' );
	if ( is_wp_error( $target ) || ! is_string( $target ) || '' === $target ) {
		return;
	}

	if ( ! empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args = array();
		foreach ( (array) $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$args[ $key ] = array_map(
					static function ( $item ) {
						return sanitize_text_field( wp_unslash( $item ) );
					},
					$value
				);
			} else {
				$args[ $key ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}
		if ( ! empty( $args ) ) {
			$target = add_query_arg( $args, $target );
		}
	}

	wp_safe_redirect( $target, 301 );
	exit;
}

/**
 * Retorna o termo de busca do catalogo aceitando o parametro atual (?q=)
 * e o legado (?s=).
 *
 * @return string
 */
function gstore_get_catalog_search_request_term() {
	$search_term = '';
	if ( isset( $_GET['q'] ) ) {
		$search_term = sanitize_text_field( wp_unslash( $_GET['q'] ) );
	} elseif ( isset( $_GET['s'] ) ) {
		$search_term = sanitize_text_field( wp_unslash( $_GET['s'] ) );
	}

	return trim( $search_term );
}

/**
 * Mapeia rotas raiz "/{categoria-principal}" para o template de catálogo.
 *
 * Exemplo:
 * - /clube-de-tiro -> /catalogo (internamente), com escopo da categoria.
 *
 * Não sobrescreve páginas reais nem slugs reservados do core.
 */
add_filter( 'request', 'gstore_catalog_scope_root_category_route', 5 );
function gstore_catalog_scope_root_category_route( $vars ) {
	if ( is_admin() ) {
		return $vars;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return $vars;
	}

	$pagename = isset( $vars['pagename'] ) ? (string) $vars['pagename'] : '';
	if ( '' === $pagename ) {
		return $vars;
	}
	if ( strpos( $pagename, '/' ) !== false ) {
		return $vars;
	}

	$slug = sanitize_title( $pagename );
	if ( '' === $slug ) {
		return $vars;
	}

	// Nunca sobrescreve páginas publicadas/existentes.
	if ( get_page_by_path( $slug ) ) {
		return $vars;
	}

	$reserved_slugs = array(
		'wp-admin',
		'wp-login',
		'wp-json',
		'feed',
		'comments',
		'search',
		'sitemap.xml',
		'sitemap_index.xml',
		'robots.txt',
	);
	if ( in_array( $slug, $reserved_slugs, true ) ) {
		return $vars;
	}

	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		return $vars;
	}

	// Escopo apenas para categorias principais.
	if ( (int) $term->parent !== 0 ) {
		return $vars;
	}

	$main_category_ids = get_option( 'gstore_main_categories', array() );
	$main_category_ids = array_values(
		array_unique(
			array_filter(
				array_map( 'absint', is_array( $main_category_ids ) ? $main_category_ids : array() )
			)
		)
	);

	// Escopo apenas para categorias marcadas como principais no painel.
	if ( empty( $main_category_ids ) || ! in_array( (int) $term->term_id, $main_category_ids, true ) ) {
		return $vars;
	}

	$vars['pagename'] = 'catalogo';
	$vars['gstore_catalog_scope'] = $slug;

	// Evita conflitos com possíveis matches anteriores.
	unset( $vars['name'], $vars['attachment'], $vars['attachment_id'], $vars['category_name'] );

	return $vars;
}

/**
 * Aplica o termo de busca (?s=) e match de categoria na query do shortcode [products]
 * quando usado no catálogo (/catalogo).
 */
add_filter( 'woocommerce_shortcode_products_query', 'gstore_catalog_apply_search_to_products_shortcode', 25, 3 );
function gstore_catalog_apply_search_to_products_shortcode( $query_args, $attr, $type ) {
	$is_catalog_page = function_exists( 'is_page' ) && is_page( 'catalogo' );
	$is_generated_catalog_page = function_exists( 'gstore_is_generated_category_catalog_page' ) && gstore_is_generated_category_catalog_page();
	if ( ! $is_catalog_page && ! $is_generated_catalog_page ) {
		return $query_args;
	}

	$search_term = gstore_get_catalog_search_request_term();
	if ( $search_term === '' ) {
		return $query_args;
	}

	$product_ids = function_exists( 'gstore_find_relevant_product_ids_for_search' )
		? gstore_find_relevant_product_ids_for_search( $search_term, 80 )
		: array();

	if ( empty( $product_ids ) ) {
		// Forca nenhum resultado em vez de mostrar tudo.
		$query_args['post__in'] = array( 0 );
		return $query_args;
	}

	$query_args['post__in'] = $product_ids;
	$query_args['orderby']  = 'post__in';

	return $query_args;
}

/**
 * Detecta se a pagina atual deve usar regras de ordenacao do catalogo.
 *
 * @return bool
 */
function gstore_is_catalog_context() {
	if ( is_admin() ) {
		return false;
	}

	if ( function_exists( 'is_front_page' ) && is_front_page() ) {
		return false;
	}

	if ( function_exists( 'is_page' ) && is_page( array( 'catalogo', 'loja', 'ofertas', 'marcas', 'categorias-produto', 'categoria-produto' ) ) ) {
		return true;
	}

	if ( function_exists( 'is_page_template' ) && is_page_template( array( 'templates/page-catalogo.html', 'templates/page-loja.html', 'templates/page-ofertas.html' ) ) ) {
		return true;
	}

	if ( function_exists( 'gstore_is_generated_category_catalog_page' ) && gstore_is_generated_category_catalog_page() ) {
		return true;
	}

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return true;
	}

	if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
		return true;
	}

	return false;
}

/**
 * Mantem 5 linhas de 3 produtos no catalogo desktop.
 *
 * @param int $per_page Quantidade original.
 * @return int
 */
function gstore_catalog_products_per_page( $per_page ) {
	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return $per_page;
	}

	return 15;
}
add_filter( 'loop_shop_per_page', 'gstore_catalog_products_per_page', 20 );

/**
 * Aplica o mesmo limite na query principal de produtos.
 *
 * @param WC_Query|WP_Query $query Query de produtos.
 */
function gstore_catalog_apply_products_per_page_to_query( $query ) {
	if ( is_admin() || ! is_object( $query ) || ! method_exists( $query, 'set' ) ) {
		return;
	}

	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return;
	}

	$query->set( 'posts_per_page', 15 );
}
add_action( 'woocommerce_product_query', 'gstore_catalog_apply_products_per_page_to_query', 20 );

/**
 * Aplica 15 produtos tambem em shortcodes [products] usados nas paginas de catalogo.
 *
 * @param array  $query_args Argumentos da query do shortcode.
 * @param array  $attributes Atributos recebidos pelo shortcode.
 * @param string $type       Tipo do shortcode.
 * @return array
 */
function gstore_catalog_apply_products_per_page_to_shortcodes( $query_args, $attributes, $type ) {
	if ( function_exists( 'is_front_page' ) && is_front_page() ) {
		return $query_args;
	}

	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return $query_args;
	}

	$query_args['posts_per_page'] = 15;

	return $query_args;
}
add_filter( 'woocommerce_shortcode_products_query', 'gstore_catalog_apply_products_per_page_to_shortcodes', 20, 3 );

/**
 * Retorna o titulo limpo do arquivo atual de catalogo.
 *
 * @return string
 */
function gstore_get_catalog_archive_title() {
	$title = '';

	if ( function_exists( 'woocommerce_page_title' ) && ( ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) ) ) {
		$title = (string) woocommerce_page_title( false );
	} elseif ( function_exists( 'is_search' ) && is_search() ) {
		$title = get_search_query();
	} elseif ( function_exists( 'is_archive' ) && is_archive() ) {
		$title = get_the_archive_title();
	} elseif ( function_exists( 'get_the_title' ) ) {
		$title = get_the_title();
	}

	$title = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	$title = trim( preg_replace( '/\s+/u', ' ', $title ) );
	$title = (string) preg_replace( '/^\s*(Categoria|Category|Tag|Marca):\s*/i', '', $title );

	return trim( $title );
}

/**
 * Retorna a description completa configurada para o catalogo atual.
 *
 * @return string
 */
function gstore_get_catalog_archive_description_html() {
	$description = '';

	if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$description = (string) term_description( (int) $term->term_id, $term->taxonomy );
		}
	}

	if ( '' === trim( wp_strip_all_tags( $description ) ) && function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
		$shop_page_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_page_id > 0 ) {
			$content = (string) get_post_field( 'post_content', $shop_page_id );
			if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
				$description = apply_filters( 'the_content', $content );
			}
		}
	}

	return trim( (string) $description );
}

/**
 * Conta caracteres com suporte a UTF-8 quando disponivel.
 *
 * @param string $text Texto.
 * @return int
 */
function gstore_catalog_text_length( $text ) {
	$text = (string) $text;

	return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
}

/**
 * Corta no limite de palavras, evitando quebrar uma palavra no mobile.
 *
 * @param string $text  Texto limpo.
 * @param int    $limit Limite de caracteres.
 * @return string
 */
function gstore_catalog_trim_summary_to_words( $text, $limit ) {
	$text  = trim( (string) $text );
	$limit = max( 80, absint( $limit ) );
	$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

	if ( ! is_array( $words ) || empty( $words ) ) {
		return $text;
	}

	$summary = '';
	foreach ( $words as $word ) {
		$candidate = '' === $summary ? $word : $summary . ' ' . $word;
		if ( gstore_catalog_text_length( $candidate ) > $limit ) {
			break;
		}

		$summary = $candidate;
	}

	if ( '' === $summary ) {
		$summary = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit, 'UTF-8' ) : substr( $text, 0, $limit );
	}

	return rtrim( $summary, " \t\n\r\0\x0B,;:-" ) . '...';
}

/**
 * Gera um resumo curto da description para o topo do catalogo.
 *
 * @param string $description_html Description completa em HTML.
 * @param int    $limit            Limite aproximado.
 * @return string
 */
function gstore_get_catalog_archive_summary( $description_html, $limit = 150 ) {
	$text = html_entity_decode( wp_strip_all_tags( (string) $description_html ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

	if ( '' === $text ) {
		return '';
	}

	$limit = max( 80, absint( $limit ) );
	if ( gstore_catalog_text_length( $text ) <= $limit ) {
		return $text;
	}

	$sentences = preg_split( '/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	$summary   = '';

	if ( is_array( $sentences ) ) {
		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );
			if ( '' === $sentence ) {
				continue;
			}

			$candidate = '' === $summary ? $sentence : $summary . ' ' . $sentence;
			if ( gstore_catalog_text_length( $candidate ) <= $limit ) {
				$summary = $candidate;
				continue;
			}

			break;
		}
	}

	if ( '' === $summary || gstore_catalog_text_length( $summary ) > $limit ) {
		return gstore_catalog_trim_summary_to_words( $text, $limit );
	}

	if ( preg_match( '/(\.\.\.|[.!?])$/u', $summary ) ) {
		return $summary;
	}

	return rtrim( $summary, " \t\n\r\0\x0B,;:-" ) . '.';
}

/**
 * Retorna o termo de marca quando o arquivo atual for uma marca de produto.
 *
 * @param mixed $term Termo opcional.
 * @return WP_Term|null
 */
function gstore_get_catalog_archive_brand_term( $term = null ) {
	if ( ! $term instanceof WP_Term ) {
		$term = get_queried_object();
	}

	if ( ! $term instanceof WP_Term ) {
		return null;
	}

	$brand_taxonomies = array( 'product_brand' );
	if ( function_exists( 'gstore_get_footer_brand_taxonomies' ) ) {
		$brand_taxonomies = array_merge( $brand_taxonomies, (array) gstore_get_footer_brand_taxonomies() );
	}

	$brand_taxonomies = array_values( array_unique( array_filter( $brand_taxonomies ) ) );

	return in_array( $term->taxonomy, $brand_taxonomies, true ) ? $term : null;
}

/**
 * Retorna o attachment ID usado como imagem da marca.
 *
 * @param mixed $term Termo opcional.
 * @return int
 */
function gstore_get_catalog_archive_brand_image_id( $term = null ) {
	$term = gstore_get_catalog_archive_brand_term( $term );
	if ( ! $term ) {
		return 0;
	}

	$image_id = absint( get_term_meta( (int) $term->term_id, 'gstore_term_image_id', true ) );
	if ( $image_id <= 0 ) {
		$image_id = absint( get_term_meta( (int) $term->term_id, 'thumbnail_id', true ) );
	}

	if ( $image_id <= 0 || 'attachment' !== get_post_type( $image_id ) || ! wp_attachment_is_image( $image_id ) ) {
		return 0;
	}

	return $image_id;
}

/**
 * Retorna uma imagem de produto da marca para fallback SEO.
 *
 * @param mixed $term Termo opcional.
 * @return int
 */
function gstore_get_catalog_archive_brand_product_image_id( $term = null ) {
	$term = gstore_get_catalog_archive_brand_term( $term );
	if ( ! $term ) {
		return 0;
	}

	static $cache = array();

	$cache_key = $term->taxonomy . ':' . (int) $term->term_id;
	if ( array_key_exists( $cache_key, $cache ) ) {
		return (int) $cache[ $cache_key ];
	}

	$product_ids = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => 16,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy'         => $term->taxonomy,
					'field'            => 'term_id',
					'terms'            => array( (int) $term->term_id ),
					'include_children' => is_taxonomy_hierarchical( $term->taxonomy ),
				),
			),
		)
	);

	$image_id = 0;

	foreach ( array_map( 'absint', (array) $product_ids ) as $product_id ) {
		$candidate_ids = array( absint( get_post_thumbnail_id( $product_id ) ) );
		$gallery       = get_post_meta( $product_id, '_product_image_gallery', true );

		if ( is_string( $gallery ) && '' !== trim( $gallery ) ) {
			$candidate_ids = array_merge(
				$candidate_ids,
				array_map( 'absint', explode( ',', $gallery ) )
			);
		}

		foreach ( array_filter( $candidate_ids ) as $candidate_id ) {
			if ( 'attachment' === get_post_type( $candidate_id ) && wp_attachment_is_image( $candidate_id ) ) {
				$image_id = (int) $candidate_id;
				break 2;
			}
		}
	}

	$cache[ $cache_key ] = $image_id;

	return $image_id;
}

/**
 * Retorna a imagem SEO da marca: logo cadastrada ou produto vinculado.
 *
 * @param mixed $term Termo opcional.
 * @return array<string,mixed>
 */
function gstore_get_catalog_archive_brand_seo_image( $term = null ) {
	$term = gstore_get_catalog_archive_brand_term( $term );
	if ( ! $term ) {
		return array();
	}

	$image_id = gstore_get_catalog_archive_brand_image_id( $term );
	$source   = 'brand';

	if ( $image_id <= 0 ) {
		$image_id = gstore_get_catalog_archive_brand_product_image_id( $term );
		$source   = 'product';
	}

	if ( $image_id <= 0 ) {
		return array();
	}

	$image = wp_get_attachment_image_src( $image_id, 'large' );
	if ( ! is_array( $image ) || empty( $image[0] ) ) {
		return array();
	}

	$store_name = function_exists( 'gstore_get_store_name' ) ? gstore_get_store_name( 'display' ) : get_bloginfo( 'name' );
	$store_name = trim( wp_strip_all_tags( (string) $store_name ) );
	if ( '' === $store_name ) {
		$store_name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
	}

	$alt = sprintf(
		__( 'Produtos %1$s disponíveis na %2$s', 'gstore' ),
		$term->name,
		$store_name
	);

	return array(
		'id'     => $image_id,
		'url'    => esc_url_raw( $image[0] ),
		'width'  => isset( $image[1] ) ? absint( $image[1] ) : 0,
		'height' => isset( $image[2] ) ? absint( $image[2] ) : 0,
		'type'   => (string) get_post_mime_type( $image_id ),
		'alt'    => $alt,
		'source' => $source,
		'term'   => $term,
	);
}

/**
 * Filtra a imagem Open Graph em plugins SEO.
 *
 * @param string $image_url URL original.
 * @return string
 */
function gstore_catalog_brand_archive_seo_image_url( $image_url = '' ) {
	$image = gstore_get_catalog_archive_brand_seo_image();
	return ! empty( $image['url'] ) ? (string) $image['url'] : $image_url;
}
add_filter( 'wpseo_opengraph_image', 'gstore_catalog_brand_archive_seo_image_url', 20 );
add_filter( 'rank_math/opengraph/facebook/image', 'gstore_catalog_brand_archive_seo_image_url', 20 );

/**
 * Filtra o texto alternativo da imagem social quando o plugin disponibilizar filtro.
 *
 * @param string $alt Texto original.
 * @return string
 */
function gstore_catalog_brand_archive_seo_image_alt( $alt = '' ) {
	$image = gstore_get_catalog_archive_brand_seo_image();
	return ! empty( $image['alt'] ) ? (string) $image['alt'] : $alt;
}
add_filter( 'wpseo_opengraph_image_alt', 'gstore_catalog_brand_archive_seo_image_alt', 20 );

/**
 * Imprime metadados de imagem para marcas sem expor a miniatura no topo da pagina.
 */
function gstore_print_catalog_brand_archive_seo_image_meta() {
	$image = gstore_get_catalog_archive_brand_seo_image();
	if ( empty( $image['url'] ) ) {
		return;
	}

	$url    = (string) $image['url'];
	$alt    = ! empty( $image['alt'] ) ? (string) $image['alt'] : '';
	$type   = ! empty( $image['type'] ) ? (string) $image['type'] : '';
	$width  = ! empty( $image['width'] ) ? absint( $image['width'] ) : 0;
	$height = ! empty( $image['height'] ) ? absint( $image['height'] ) : 0;

	echo '<link rel="image_src" href="' . esc_url( $url ) . '" />' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $url ) . '" />' . "\n";
	echo '<meta property="og:image:secure_url" content="' . esc_url( $url ) . '" />' . "\n";
	if ( $type ) {
		echo '<meta property="og:image:type" content="' . esc_attr( $type ) . '" />' . "\n";
	}
	if ( $width > 0 ) {
		echo '<meta property="og:image:width" content="' . esc_attr( (string) $width ) . '" />' . "\n";
	}
	if ( $height > 0 ) {
		echo '<meta property="og:image:height" content="' . esc_attr( (string) $height ) . '" />' . "\n";
	}
	if ( $alt ) {
		echo '<meta property="og:image:alt" content="' . esc_attr( $alt ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'gstore_print_catalog_brand_archive_seo_image_meta', 5 );

/**
 * Adiciona a imagem SEO da marca no schema WebPage do Yoast quando disponivel.
 *
 * @param array $data Dados originais.
 * @return array
 */
function gstore_catalog_brand_archive_schema_image( $data ) {
	$image = gstore_get_catalog_archive_brand_seo_image();
	if ( empty( $image['url'] ) || ! is_array( $data ) ) {
		return $data;
	}

	$data['primaryImageOfPage'] = array(
		'@type'   => 'ImageObject',
		'url'     => (string) $image['url'],
		'caption' => ! empty( $image['alt'] ) ? (string) $image['alt'] : '',
	);

	return $data;
}
add_filter( 'wpseo_schema_webpage', 'gstore_catalog_brand_archive_schema_image', 20 );

/**
 * Schema leve complementar para a imagem SEO da marca.
 */
function gstore_print_catalog_brand_archive_image_schema() {
	$image = gstore_get_catalog_archive_brand_seo_image();
	if ( empty( $image['url'] ) || empty( $image['term'] ) || ! $image['term'] instanceof WP_Term ) {
		return;
	}

	$term = $image['term'];
	$url  = get_term_link( $term, $term->taxonomy );
	if ( is_wp_error( $url ) || ! is_string( $url ) || '' === $url ) {
		$url = '';
	}

	$schema = array(
		'@context'           => 'https://schema.org',
		'@type'              => 'CollectionPage',
		'name'               => gstore_get_catalog_archive_title(),
		'url'                => $url,
		'primaryImageOfPage' => array(
			'@type'   => 'ImageObject',
			'url'     => (string) $image['url'],
			'caption' => ! empty( $image['alt'] ) ? (string) $image['alt'] : '',
		),
		'about'              => array(
			'@type' => 'Brand',
			'name'  => $term->name,
		),
	);

	if ( 'brand' === $image['source'] ) {
		$schema['about']['logo'] = (string) $image['url'];
	}

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
add_action( 'wp_head', 'gstore_print_catalog_brand_archive_image_schema', 31 );

/**
 * Imprime titulo e resumo curto no topo do arquivo de produtos.
 */
function gstore_output_catalog_archive_intro() {
	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return;
	}

	$title       = gstore_get_catalog_archive_title();
	$description = gstore_get_catalog_archive_description_html();
	$summary     = function_exists( 'gstore_get_catalog_archive_summary' )
		? gstore_get_catalog_archive_summary( $description )
		: ( function_exists( 'gstore_get_catalog_brand_archive_summary' ) ? gstore_get_catalog_brand_archive_summary( $description ) : '' );
	$has_content = '' !== $title || '' !== $summary;

	if ( ! $has_content ) {
		return;
	}

	$classes = array( 'woocommerce-products-header', 'Gstore-catalog-archive-intro' );

	echo '<header class="' . esc_attr( implode( ' ', $classes ) ) . '">';
	if ( '' !== $title ) {
		echo '<h1 class="woocommerce-products-header__title page-title Gstore-catalog-archive-title">' . esc_html( $title ) . '</h1>';
	}
	if ( '' !== $summary ) {
		echo '<div class="term-description Gstore-catalog-archive-summary"><p>' . esc_html( $summary ) . '</p></div>';
	}
	echo '</header>';
}

/**
 * Usa o header customizado sem miniatura visual em arquivos de marca.
 */
function gstore_setup_catalog_brand_archive_header() {
	if ( ! gstore_get_catalog_archive_brand_term() ) {
		return;
	}

	remove_action( 'woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10 );
	add_action( 'woocommerce_shop_loop_header', 'gstore_output_catalog_archive_intro', 10 );
}
add_action( 'wp', 'gstore_setup_catalog_brand_archive_header', 20 );

/**
 * Garante o header de marca antes do header padrao do WooCommerce renderizar.
 */
function gstore_output_catalog_brand_archive_intro_early() {
	if ( ! gstore_get_catalog_archive_brand_term() ) {
		return;
	}

	remove_action( 'woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10 );
	remove_action( 'woocommerce_shop_loop_header', 'gstore_output_catalog_archive_intro', 10 );
	gstore_output_catalog_archive_intro();
}
add_action( 'woocommerce_shop_loop_header', 'gstore_output_catalog_brand_archive_intro_early', 1 );

/**
 * Imprime apenas o resumo curto dentro do header padrao do WooCommerce.
 */
function gstore_output_catalog_archive_summary_description() {
	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return;
	}

	$summary = gstore_get_catalog_archive_summary( gstore_get_catalog_archive_description_html() );
	if ( '' === $summary ) {
		return;
	}

	echo '<div class="term-description Gstore-catalog-archive-summary"><p>' . esc_html( $summary ) . '</p></div>';
}

/**
 * Troca a description completa do topo pelo resumo curto.
 */
function gstore_setup_catalog_archive_description() {
	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return;
	}

	remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
	remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );
	add_action( 'woocommerce_archive_description', 'gstore_output_catalog_archive_summary_description', 10 );
}
add_action( 'wp', 'gstore_setup_catalog_archive_description', 20 );

/**
 * Retorna o accordion com a description completa.
 *
 * @return string
 */
function gstore_get_catalog_archive_description_details_html() {
	$description = gstore_get_catalog_archive_description_html();
	if ( '' === trim( wp_strip_all_tags( $description ) ) ) {
		return '';
	}

	$title      = gstore_get_catalog_archive_title();
	$store_name = function_exists( 'gstore_get_store_name' ) ? gstore_get_store_name( 'display' ) : get_bloginfo( 'name' );
	$label      = trim( sprintf( __( 'Sobre %1$s na %2$s', 'gstore' ), $title ? $title : __( 'produtos', 'gstore' ), $store_name ) );

	return '<section class="Gstore-catalog-archive-details" aria-label="' . esc_attr__( 'Conteúdo e informações', 'gstore' ) . '">'
		. '<h2 class="Gstore-catalog-archive-details__heading">' . esc_html__( 'Conteúdo e informações', 'gstore' ) . '</h2>'
		. '<details class="Gstore-catalog-archive-details__item">'
		. '<summary class="Gstore-catalog-archive-details__summary">'
		. '<span class="Gstore-catalog-archive-details__icon" aria-hidden="true"></span>'
		. '<span>' . esc_html( $label ) . '</span>'
		. '</summary>'
		. '<div class="Gstore-catalog-archive-details__content">' . wp_kses_post( $description ) . '</div>'
		. '</details>'
		. '</section>';
}

/**
 * Imprime a description completa no fim do arquivo de produtos.
 */
function gstore_output_catalog_archive_description_details() {
	static $rendered = false;

	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return;
	}

	if ( $rendered ) {
		return;
	}

	$html = gstore_get_catalog_archive_description_details_html();
	if ( '' === $html ) {
		return;
	}

	$rendered = true;

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'woocommerce_after_shop_loop', 'gstore_output_catalog_archive_description_details', 30 );

/**
 * Acrescenta o accordion depois de shortcodes [products] em paginas de catalogo.
 *
 * @param string $output Saida renderizada.
 * @param string $tag    Nome do shortcode.
 * @return string
 */
function gstore_append_catalog_details_after_products_shortcode( $output, $tag ) {
	if ( 'products' !== $tag ) {
		return $output;
	}

	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return $output;
	}

	if ( false !== strpos( (string) $output, 'Gstore-catalog-archive-details' ) ) {
		return $output;
	}

	return $output . gstore_get_catalog_archive_description_details_html();
}
add_filter( 'do_shortcode_tag', 'gstore_append_catalog_details_after_products_shortcode', 20, 2 );

/**
 * Define "popularidade" como ordenacao padrao no catalogo.
 *
 * @param string $default_orderby Ordenacao padrao atual.
 * @return string
 */
add_filter( 'woocommerce_default_catalog_orderby', 'gstore_catalog_default_orderby_popularity', 20 );
function gstore_catalog_default_orderby_popularity( $default_orderby ) {
	if ( ! gstore_is_catalog_context() ) {
		return $default_orderby;
	}

	return 'popularity';
}

/**
 * Marca queries de shortcode no catalogo para priorizar itens com estoque.
 */
add_filter( 'woocommerce_shortcode_products_query', 'gstore_catalog_mark_shortcode_stock_priority', 35, 3 );
function gstore_catalog_mark_shortcode_stock_priority( $query_args, $attr, $type ) {
	if ( ! gstore_is_catalog_context() ) {
		return $query_args;
	}

	$has_catalog_search = function_exists( 'gstore_get_catalog_search_request_term' ) && '' !== gstore_get_catalog_search_request_term();

	$query_args['gstore_instock_first'] = 1;
	if ( ! $has_catalog_search ) {
		$query_args['gstore_featured_first'] = 1;
	}

	return $query_args;
}

/**
 * Marca a query principal do WooCommerce para priorizar itens com estoque.
 */
add_action( 'woocommerce_product_query', 'gstore_catalog_mark_main_query_stock_priority', 30 );
function gstore_catalog_mark_main_query_stock_priority( $query ) {
	if ( ! gstore_is_catalog_context() ) {
		return;
	}

	$has_catalog_search = function_exists( 'gstore_get_catalog_search_request_term' ) && '' !== gstore_get_catalog_search_request_term();

	$query->set( 'gstore_instock_first', 1 );
	if ( ! $has_catalog_search ) {
		$query->set( 'gstore_featured_first', 1 );
	}
}

/**
 * Forca produtos sem estoque para o final, preservando a ordenacao atual como criterio secundario.
 */
add_filter( 'posts_clauses', 'gstore_catalog_order_by_stock_first', 20, 2 );
function gstore_catalog_order_by_stock_first( $clauses, $query ) {
	if ( is_admin() || ! ( $query instanceof WP_Query ) ) {
		return $clauses;
	}

	$apply_stock_priority    = ( 1 === (int) $query->get( 'gstore_instock_first' ) );
	$apply_featured_priority = ( 1 === (int) $query->get( 'gstore_featured_first' ) );

	if ( ! $apply_stock_priority && ! $apply_featured_priority ) {
		return $clauses;
	}

	$post_type = $query->get( 'post_type' );
	if ( is_string( $post_type ) && '' !== $post_type && $post_type !== 'product' ) {
		return $clauses;
	}
	if ( is_array( $post_type ) && ! in_array( 'product', $post_type, true ) ) {
		return $clauses;
	}

	global $wpdb;
	$order_parts = array();

	if ( $apply_featured_priority ) {
		static $featured_term_taxonomy_id = null;
		if ( null === $featured_term_taxonomy_id ) {
			$featured_term_taxonomy_id = 0;
			$featured_term = get_term_by( 'slug', 'featured', 'product_visibility' );
			if ( $featured_term && ! is_wp_error( $featured_term ) && isset( $featured_term->term_taxonomy_id ) ) {
				$featured_term_taxonomy_id = (int) $featured_term->term_taxonomy_id;
			}
		}

		if ( $featured_term_taxonomy_id > 0 ) {
			$featured_alias = 'gstore_featured_rel';
			if ( strpos( $clauses['join'], $featured_alias ) === false ) {
				$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS {$featured_alias}
					ON ({$wpdb->posts}.ID = {$featured_alias}.object_id AND {$featured_alias}.term_taxonomy_id = {$featured_term_taxonomy_id})";
			}

			$featured_sales_alias = 'gstore_featured_sales_meta';
			if ( strpos( $clauses['join'], $featured_sales_alias ) === false ) {
				$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS {$featured_sales_alias}
					ON ({$wpdb->posts}.ID = {$featured_sales_alias}.post_id AND {$featured_sales_alias}.meta_key = 'total_sales')";
			}

			$featured_priority_sql   = "CASE WHEN {$featured_alias}.object_id IS NULL THEN 1 ELSE 0 END";
			$featured_popularity_sql = "CASE WHEN {$featured_alias}.object_id IS NULL THEN -1 ELSE CAST(COALESCE(NULLIF({$featured_sales_alias}.meta_value, ''), '0') AS UNSIGNED) END";
			$featured_title_sql      = "CASE WHEN {$featured_alias}.object_id IS NULL THEN '' ELSE {$wpdb->posts}.post_title END";

			$order_parts[] = $featured_priority_sql . ' ASC';
			$order_parts[] = $featured_popularity_sql . ' DESC';
			$order_parts[] = $featured_title_sql . ' ASC';
		}
	}

	if ( $apply_stock_priority ) {
		$meta_alias = 'gstore_stock_order_meta';
		if ( strpos( $clauses['join'], $meta_alias ) === false ) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS {$meta_alias}
				ON ({$wpdb->posts}.ID = {$meta_alias}.post_id AND {$meta_alias}.meta_key = '_stock_status')";
		}

		$stock_priority_sql = "CASE {$meta_alias}.meta_value
			WHEN 'instock' THEN 0
			WHEN 'onbackorder' THEN 1
			WHEN 'outofstock' THEN 2
			ELSE 1
		END";
		$order_parts[] = $stock_priority_sql . ' ASC';
	}

	$current_orderby = isset( $clauses['orderby'] ) ? trim( (string) $clauses['orderby'] ) : '';
	if ( $current_orderby !== '' ) {
		$order_parts[] = $current_orderby;
	} else {
		$order_parts[] = "{$wpdb->posts}.post_title ASC";
	}
	$clauses['orderby'] = implode( ', ', $order_parts );

	return $clauses;
}

/**
 * Compatibilidade: redireciona /catalogo/?s=... para /catalogo/?q=...
 * para evitar conflito de rotas quando o WP entra em modo search.
 */
add_action( 'template_redirect', 'gstore_catalog_redirect_s_to_q', 0 );
function gstore_catalog_redirect_s_to_q() {
	if ( is_admin() ) {
		return;
	}

	if ( empty( $_GET['s'] ) || isset( $_GET['q'] ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$catalog_path = (string) wp_parse_url( gstore_get_catalog_url(), PHP_URL_PATH );
	$catalog_path = '/' . trim( $catalog_path ? $catalog_path : '/catalogo/', '/' );
	if ( $request_uri === '' || 0 !== stripos( '/' . ltrim( $request_uri, '/' ), $catalog_path ) ) {
		return;
	}

	// Preserva outros params, troca s->q (sanitiza valores escalares).
	$params = array();
	foreach ( (array) $_GET as $k => $v ) {
		if ( $k === 's' ) {
			continue;
		}
		if ( is_array( $v ) ) {
			$params[ $k ] = array_map(
				static function( $item ) {
					return sanitize_text_field( wp_unslash( $item ) );
				},
				$v
			);
		} else {
			$params[ $k ] = sanitize_text_field( wp_unslash( $v ) );
		}
	}
	$params['q'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );

	$target = add_query_arg( $params, gstore_get_catalog_url() );
	wp_safe_redirect( $target, 302 );
	exit;
}

/**
 * Endpoint REST para autocomplete de busca (produtos + categorias).
 */
add_action( 'rest_api_init', 'gstore_register_search_suggest_route' );
function gstore_register_search_suggest_route() {
	register_rest_route(
		'gstore/v1',
		'/search-suggest',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'callback'            => 'gstore_handle_search_suggest',
			'args'                => array(
				'q' => array(
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}

function gstore_handle_search_suggest( WP_REST_Request $request ) {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return new WP_REST_Response(
			array(
				'products'   => array(),
				'categories' => array(),
			),
			200
		);
	}

	$term = (string) $request->get_param( 'q' );
	$term = trim( $term );

	$len = function_exists( 'mb_strlen' ) ? (int) mb_strlen( $term ) : (int) strlen( $term );
	if ( $len < 2 ) {
		return new WP_REST_Response(
			array(
				'products'   => array(),
				'categories' => array(),
			),
			200
		);
	}

	$cache_key = 'gstore_search_suggest_v3_' . md5( strtolower( remove_accents( $term ) ) );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return new WP_REST_Response( $cached, 200 );
	}

	$limit = 8;

	// Produtos
	$product_ids        = function_exists( 'gstore_find_product_ids_by_exact_sku' )
		? gstore_find_product_ids_by_exact_sku( $term, $limit )
		: array();
	$has_exact_sku_hit = ! empty( $product_ids );

	if ( ! $has_exact_sku_hit && function_exists( 'gstore_find_relevant_product_ids_for_search' ) ) {
		$product_ids = gstore_find_relevant_product_ids_for_search( $term, $limit );
	}

	$products = array();
	foreach ( $product_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			continue;
		}

		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';

		$in_stock = $product->is_in_stock();

		if ( ! $in_stock ) {
			$price_html = '<span class="Gstore-search-suggest__out-of-stock">Indisponível</span>';
		} elseif ( gstore_product_hides_price( $product, 'search' ) ) {
			$price_html = gstore_get_hidden_price_mask_html( 'inline' );
		} else {
			$price_html = $product->get_price_html();
		}

		$products[] = array(
			'id'         => $product_id,
			'name'       => $product->get_name(),
			'permalink'  => get_permalink( $product_id ),
			'image'      => $image_url ? $image_url : '',
			'price_html' => $price_html,
			'in_stock'   => $in_stock,
		);
	}

	// Categorias
	$terms = array();
	if ( ! $has_exact_sku_hit ) {
		$terms_a = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => $limit,
				'search'     => $term,
			)
		);
		if ( ! is_wp_error( $terms_a ) && ! empty( $terms_a ) ) {
			$terms = array_merge( $terms, $terms_a );
		}

		$term_no_accents = remove_accents( $term );
		if ( $term_no_accents && $term_no_accents !== $term ) {
			$terms_b = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'number'     => $limit,
					'search'     => $term_no_accents,
				)
			);
			if ( ! is_wp_error( $terms_b ) && ! empty( $terms_b ) ) {
				$terms = array_merge( $terms, $terms_b );
			}
		}
	}

	$seen_term   = array();
	$categories  = array();
	foreach ( $terms as $t ) {
		if ( empty( $t ) || empty( $t->term_id ) ) {
			continue;
		}
		if ( isset( $seen_term[ $t->term_id ] ) ) {
			continue;
		}
		$seen_term[ $t->term_id ] = true;

		if ( function_exists( 'gstore_search_term_matches_catalog_term' ) && ! gstore_search_term_matches_catalog_term( $term, $t ) ) {
			continue;
		}

		$category_url = get_term_link( $t, 'product_cat' );
		if ( is_wp_error( $category_url ) || ! is_string( $category_url ) || '' === $category_url ) {
			$category_url = add_query_arg( array( 'filter_cat[]' => (string) $t->slug ), gstore_get_catalog_url() );
		}

		$categories[] = array(
			'term_id' => (int) $t->term_id,
			'slug'    => (string) $t->slug,
			'name'    => (string) $t->name,
			'url'     => $category_url,
		);
	}

	$payload = array(
		'products'   => $products,
		'categories' => $categories,
	);

	// Cache curto para reduzir carga (autocomplete).
	set_transient( $cache_key, $payload, 60 );

	return new WP_REST_Response( $payload, 200 );
}

/**
 * Busca produtos por SKU com correspondencia estritamente exata.
 *
 * Procura em produtos e variacoes. Quando o SKU pertence a uma variacao,
 * retorna o produto pai para manter compatibilidade com o catalogo.
 *
 * @param string $search_term Termo digitado pelo usuario.
 * @param int    $limit       Maximo de IDs a retornar. Use 0 para sem limite.
 * @return int[] IDs de produtos publicados.
 */
function gstore_find_product_ids_by_exact_sku( $search_term, $limit = 0 ) {
	global $wpdb;

	$sku = trim( (string) $search_term );
	if ( '' === $sku ) {
		return array();
	}

	$limit     = max( 0, (int) $limit );
	$cache_key = 'gstore_exact_sku_' . md5( strtolower( $sku ) . '|' . $limit );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$sql = "
		SELECT posts.ID, posts.post_parent, posts.post_type
		FROM {$wpdb->posts} AS posts
		INNER JOIN {$wpdb->postmeta} AS sku_meta
			ON posts.ID = sku_meta.post_id
		WHERE posts.post_type IN ('product', 'product_variation')
			AND posts.post_status = 'publish'
			AND sku_meta.meta_key = '_sku'
			AND sku_meta.meta_value = %s
		ORDER BY posts.post_type ASC, posts.ID DESC
	";

	if ( $limit > 0 ) {
		$sql .= $wpdb->prepare( ' LIMIT %d', $limit * 3 );
	}

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $sku ) );
	if ( empty( $rows ) ) {
		set_transient( $cache_key, array(), 120 );
		return array();
	}

	$product_ids = array();
	foreach ( $rows as $row ) {
		$post_id   = isset( $row->ID ) ? (int) $row->ID : 0;
		$post_type = isset( $row->post_type ) ? (string) $row->post_type : '';

		if ( $post_id <= 0 ) {
			continue;
		}

		if ( 'product_variation' === $post_type ) {
			$parent_id = isset( $row->post_parent ) ? (int) $row->post_parent : 0;
			if ( $parent_id <= 0 || 'publish' !== get_post_status( $parent_id ) ) {
				continue;
			}
			$post_id = $parent_id;
		}

		$product_ids[] = $post_id;
	}

	$product_ids = array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );
	if ( $limit > 0 && count( $product_ids ) > $limit ) {
		$product_ids = array_slice( $product_ids, 0, $limit );
	}

	set_transient( $cache_key, $product_ids, 120 );

	return $product_ids;
}

/**
 * Normaliza textos usados no ranking da busca.
 *
 * @param string $value Texto bruto.
 * @return string Texto normalizado.
 */
function gstore_normalize_product_search_text( $value ) {
	$text = trim( wp_strip_all_tags( (string) $value ) );
	if ( '' === $text ) {
		return '';
	}

	$text = remove_accents( $text );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );

	$normalized = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
	if ( ! is_string( $normalized ) ) {
		$normalized = preg_replace( '/[^a-z0-9]+/i', ' ', $text );
	}
	if ( ! is_string( $normalized ) ) {
		return trim( $text );
	}

	$normalized = preg_replace( '/\s+/', ' ', $normalized );
	return is_string( $normalized ) ? trim( $normalized ) : '';
}

/**
 * Quebra a busca em termos significativos.
 *
 * @param string $search_term Termo digitado.
 * @return string[]
 */
function gstore_tokenize_product_search_term( $search_term ) {
	$normalized = gstore_normalize_product_search_text( $search_term );
	if ( '' === $normalized ) {
		return array();
	}

	$stopwords = array_fill_keys(
		array( 'a', 'as', 'o', 'os', 'e', 'de', 'da', 'das', 'do', 'dos', 'para', 'por', 'com', 'sem', 'em', 'na', 'nas', 'no', 'nos' ),
		true
	);

	$tokens = array();
	foreach ( explode( ' ', $normalized ) as $token ) {
		$token = trim( $token );
		if ( '' === $token || isset( $stopwords[ $token ] ) ) {
			continue;
		}

		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $token ) : strlen( $token );
		if ( $len < 2 && ! ctype_digit( $token ) ) {
			continue;
		}

		$tokens[ $token ] = true;
	}

	return array_keys( $tokens );
}

/**
 * Verifica se um token aparece em um texto normalizado.
 *
 * @param string $token Token normalizado.
 * @param string $text  Texto normalizado.
 * @return bool
 */
function gstore_search_token_in_text( $token, $text ) {
	$token = (string) $token;
	$text  = (string) $text;
	if ( '' === $token || '' === $text ) {
		return false;
	}

	$token_len = function_exists( 'mb_strlen' ) ? mb_strlen( $token ) : strlen( $token );
	if ( $token_len <= 2 ) {
		return (bool) preg_match( '/(?:^|\s)' . preg_quote( $token, '/' ) . '(?:\s|$)/', $text );
	}

	foreach ( explode( ' ', $text ) as $word ) {
		if ( $word === $token ) {
			return true;
		}
		if ( $token_len >= 4 && 0 === strpos( $word, $token ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Pontua erro pequeno em codigos curtos de modelo, como gxc -> g2c.
 *
 * @param string $needle_word Palavra digitada.
 * @param string $title_word  Palavra do titulo.
 * @return float Similaridade especial ou zero.
 */
function gstore_get_short_model_token_fuzzy_score( $needle_word, $title_word ) {
	$needle_word = (string) $needle_word;
	$title_word  = (string) $title_word;

	if ( '' === $needle_word || '' === $title_word ) {
		return 0;
	}

	if ( ! preg_match( '/^[a-z0-9]{3,5}$/', $needle_word ) || ! preg_match( '/^[a-z0-9]{3,5}$/', $title_word ) ) {
		return 0;
	}

	$needle_len = strlen( $needle_word );
	$title_len  = strlen( $title_word );
	if ( $needle_len !== $title_len || levenshtein( $needle_word, $title_word ) !== 1 ) {
		return 0;
	}

	if ( $needle_len <= 3 ) {
		return ( $needle_word[0] === $title_word[0] && $needle_word[ $needle_len - 1 ] === $title_word[ $title_len - 1 ] ) ? 0.9 : 0;
	}

	return ( $needle_word[0] === $title_word[0] || $needle_word[ $needle_len - 1 ] === $title_word[ $title_len - 1 ] ) ? 0.88 : 0;
}

/**
 * Verifica se todos os tokens aparecem no texto normalizado.
 *
 * @param string[] $tokens Tokens normalizados.
 * @param string   $text   Texto normalizado.
 * @return bool
 */
function gstore_search_all_tokens_in_text( $tokens, $text ) {
	if ( empty( $tokens ) || '' === (string) $text ) {
		return false;
	}

	$compact_tokens = implode( '', array_map( 'strval', $tokens ) );
	$compact_text   = str_replace( ' ', '', (string) $text );
	if ( strlen( $compact_tokens ) >= 3 && false !== strpos( $compact_text, $compact_tokens ) ) {
		return true;
	}

	foreach ( $tokens as $token ) {
		if ( ! gstore_search_token_in_text( $token, $text ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Retorna as taxonomias de produto que ajudam a busca (categoria, tag e marca).
 *
 * @return string[]
 */
function gstore_get_product_search_taxonomies() {
	static $cached_taxonomies = null;

	if ( null !== $cached_taxonomies ) {
		return $cached_taxonomies;
	}

	$taxonomies = array( 'product_cat', 'product_tag' );

	if ( function_exists( 'gstore_get_footer_brand_taxonomies' ) ) {
		$taxonomies = array_merge( $taxonomies, gstore_get_footer_brand_taxonomies() );
	}

	$taxonomies = array_values( array_unique( array_filter( array_map( 'trim', $taxonomies ) ) ) );
	$cached_taxonomies = array_values( array_filter( $taxonomies, 'taxonomy_exists' ) );

	return $cached_taxonomies;
}

/**
 * Texto de taxonomias associado a um produto para ranking de busca.
 *
 * @param int $product_id ID do produto.
 * @return string Texto normalizado.
 */
function gstore_get_product_taxonomy_search_text( $product_id ) {
	static $cache = array();

	$product_id = absint( $product_id );
	if ( $product_id <= 0 ) {
		return '';
	}
	if ( isset( $cache[ $product_id ] ) ) {
		return $cache[ $product_id ];
	}

	$parts = array();
	foreach ( gstore_get_product_search_taxonomies() as $taxonomy ) {
		$terms = get_the_terms( $product_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			if ( empty( $term ) || empty( $term->name ) ) {
				continue;
			}
			$parts[] = (string) $term->name;
			$parts[] = (string) $term->slug;
		}
	}

	$cache[ $product_id ] = gstore_normalize_product_search_text( implode( ' ', $parts ) );
	return $cache[ $product_id ];
}

/**
 * Pontua um produto por sinais de alta precisao: SKU, titulo, slug e taxonomias.
 *
 * @param string $search_term Termo digitado.
 * @param int    $product_id  ID do produto.
 * @return int Pontuacao de relevancia. Zero significa descartar.
 */
function gstore_score_product_for_search( $search_term, $product_id ) {
	$product_id = absint( $product_id );
	$tokens     = gstore_tokenize_product_search_term( $search_term );
	$needle     = gstore_normalize_product_search_text( $search_term );

	if ( $product_id <= 0 || '' === $needle || empty( $tokens ) ) {
		return 0;
	}

	$post = get_post( $product_id );
	if ( ! ( $post instanceof WP_Post ) || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
		return 0;
	}

	$title = gstore_normalize_product_search_text( $post->post_title );
	$slug  = gstore_normalize_product_search_text( $post->post_name );
	$sku   = gstore_normalize_product_search_text( get_post_meta( $product_id, '_sku', true ) );
	$tax   = gstore_get_product_taxonomy_search_text( $product_id );

	$title_sku = trim( $title . ' ' . $slug . ' ' . $sku );
	$full_text = trim( $title_sku . ' ' . $tax );

	if ( '' !== $sku && $sku === $needle ) {
		return 1000;
	}
	if ( $title === $needle || $slug === $needle ) {
		return 930;
	}
	if ( '' !== $title && 0 === strpos( $title, $needle ) ) {
		return 880;
	}
	if ( '' !== $title && false !== strpos( $title, $needle ) ) {
		return 830;
	}
	if ( gstore_search_all_tokens_in_text( $tokens, $title_sku ) ) {
		return 760 + min( 20, count( $tokens ) * 4 );
	}
	if ( count( $tokens ) > 1 && gstore_search_all_tokens_in_text( $tokens, $full_text ) ) {
		return 650 + min( 20, count( $tokens ) * 4 );
	}
	if ( 1 === count( $tokens ) && gstore_search_token_in_text( $tokens[0], $title_sku ) ) {
		return 610;
	}
	if ( 1 === count( $tokens ) && gstore_search_token_in_text( $tokens[0], $tax ) ) {
		return 500;
	}

	return 0;
}

/**
 * Ordena candidatos por relevancia e remove matches fracos.
 *
 * @param string $search_term Termo digitado.
 * @param int[]  $candidate_ids IDs candidatos.
 * @param int    $limit Limite de retorno. Use 0 para sem limite.
 * @return int[]
 */
function gstore_rank_product_ids_by_search_precision( $search_term, $candidate_ids, $limit = 0 ) {
	$seen   = array();
	$scored = array();
	$index  = 0;

	foreach ( (array) $candidate_ids as $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 || isset( $seen[ $product_id ] ) ) {
			continue;
		}
		$seen[ $product_id ] = true;

		$score = gstore_score_product_for_search( $search_term, $product_id );
		if ( $score <= 0 ) {
			continue;
		}

		$scored[] = array(
			'id'    => $product_id,
			'score' => $score,
			'index' => $index,
		);
		$index++;
	}

	usort(
		$scored,
		static function( $left, $right ) {
			if ( $left['score'] !== $right['score'] ) {
				return $right['score'] <=> $left['score'];
			}

			return $left['index'] <=> $right['index'];
		}
	);

	$result = array_map(
		static function( $item ) {
			return (int) $item['id'];
		},
		$scored
	);

	$limit = max( 0, (int) $limit );
	if ( $limit > 0 && count( $result ) > $limit ) {
		$result = array_slice( $result, 0, $limit );
	}

	return $result;
}

/**
 * Busca produtos cujo titulo/slug contem todos os termos relevantes.
 *
 * @param string $search_term Termo digitado.
 * @param int    $limit       Limite de candidatos.
 * @return int[]
 */
function gstore_find_product_ids_by_title_tokens( $search_term, $limit = 200 ) {
	global $wpdb;

	$tokens = gstore_tokenize_product_search_term( $search_term );
	if ( empty( $tokens ) ) {
		return array();
	}

	$where = array(
		"post_type = 'product'",
		"post_status = 'publish'",
	);

	foreach ( $tokens as $token ) {
		$like    = '%' . $wpdb->esc_like( $token ) . '%';
		$where[] = $wpdb->prepare( '(post_title LIKE %s OR post_name LIKE %s)', $like, $like );
	}

	$limit = max( 1, absint( $limit ) );
	$sql   = "
		SELECT ID
		FROM {$wpdb->posts}
		WHERE " . implode( ' AND ', $where ) . '
		ORDER BY post_title ASC
		LIMIT %d
	';

	$ids = $wpdb->get_col( $wpdb->prepare( $sql, $limit ) );
	return array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
}

/**
 * Verifica se um termo de catalogo e forte o suficiente para expandir produtos.
 *
 * @param string  $search_term Termo digitado.
 * @param WP_Term $term Termo da taxonomia.
 * @return bool
 */
function gstore_search_term_matches_catalog_term( $search_term, $term ) {
	if ( ! ( $term instanceof WP_Term ) ) {
		return false;
	}

	$needle = gstore_normalize_product_search_text( $search_term );
	$tokens = gstore_tokenize_product_search_term( $search_term );
	if ( '' === $needle || empty( $tokens ) ) {
		return false;
	}

	$name = gstore_normalize_product_search_text( $term->name );
	$slug = gstore_normalize_product_search_text( $term->slug );
	$text = trim( $name . ' ' . $slug );

	if ( $name === $needle || $slug === $needle ) {
		return true;
	}
	if ( count( $tokens ) > 1 ) {
		return gstore_search_all_tokens_in_text( $tokens, $text );
	}

	$token     = $tokens[0];
	$token_len = function_exists( 'mb_strlen' ) ? mb_strlen( $token ) : strlen( $token );
	if ( $token_len < 4 ) {
		return false;
	}

	foreach ( explode( ' ', $text ) as $word ) {
		if ( 0 === strpos( $word, $token ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Encontra termos de produto/categoria/marca que batem com a busca.
 *
 * @param string $search_term Termo digitado.
 * @param int    $limit Limite por consulta de termos.
 * @return array<string,int[]>
 */
function gstore_find_matching_product_search_terms( $search_term, $limit = 30 ) {
	$taxonomies = gstore_get_product_search_taxonomies();
	if ( empty( $taxonomies ) ) {
		return array();
	}

	$term_sets = array();
	$queries   = array_unique( array_filter( array( $search_term, remove_accents( $search_term ) ) ) );
	foreach ( $queries as $query_term ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => true,
				'number'     => max( 1, absint( $limit ) ),
				'search'     => $query_term,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			if ( ! gstore_search_term_matches_catalog_term( $search_term, $term ) ) {
				continue;
			}
			if ( empty( $term_sets[ $term->taxonomy ] ) ) {
				$term_sets[ $term->taxonomy ] = array();
			}
			$term_sets[ $term->taxonomy ][] = (int) $term->term_id;
		}
	}

	foreach ( $term_sets as $taxonomy => $term_ids ) {
		$term_sets[ $taxonomy ] = array_values( array_unique( array_filter( array_map( 'absint', $term_ids ) ) ) );
		if ( empty( $term_sets[ $taxonomy ] ) ) {
			unset( $term_sets[ $taxonomy ] );
		}
	}

	return $term_sets;
}

/**
 * Retorna IDs de produtos associados aos termos fortes encontrados.
 *
 * @param string $search_term Termo digitado.
 * @param int    $limit Limite de candidatos.
 * @return int[]
 */
function gstore_find_product_ids_by_search_terms( $search_term, $limit = 120 ) {
	$term_sets = gstore_find_matching_product_search_terms( $search_term );
	if ( empty( $term_sets ) ) {
		return array();
	}

	$tax_query = array( 'relation' => 'OR' );
	foreach ( $term_sets as $taxonomy => $term_ids ) {
		$tax_query[] = array(
			'taxonomy'         => $taxonomy,
			'field'            => 'term_id',
			'terms'            => $term_ids,
			'operator'         => 'IN',
			'include_children' => ( 'product_cat' === $taxonomy ),
		);
	}

	$ids = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => max( 1, absint( $limit ) ),
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => $tax_query,
		)
	);

	return array_values( array_unique( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) ) );
}

/**
 * Busca e ranqueia produtos com foco em precisao.
 *
 * @param string $search_term Termo digitado.
 * @param int    $limit Limite final de produtos.
 * @return int[]
 */
function gstore_find_relevant_product_ids_for_search( $search_term, $limit = 80 ) {
	$limit = max( 1, absint( $limit ) );

	$exact_sku_ids = function_exists( 'gstore_find_product_ids_by_exact_sku' )
		? gstore_find_product_ids_by_exact_sku( $search_term, $limit )
		: array();
	if ( ! empty( $exact_sku_ids ) ) {
		return array_slice( $exact_sku_ids, 0, $limit );
	}

	$text_ids = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			's'                      => $search_term,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	$text_ids = is_array( $text_ids ) ? array_map( 'absint', $text_ids ) : array();

	$title_ids = gstore_find_product_ids_by_title_tokens( $search_term, 200 );
	$term_ids  = gstore_find_product_ids_by_search_terms( $search_term, 120 );

	$candidate_ids = array_values( array_unique( array_filter( array_merge( $title_ids, $text_ids, $term_ids ) ) ) );
	$ranked_ids    = gstore_rank_product_ids_by_search_precision( $search_term, $candidate_ids, $limit );

	$fuzzy_floor = empty( $ranked_ids ) ? min( 40, $limit ) : min( 12, $limit );
	if ( count( $ranked_ids ) < $fuzzy_floor && function_exists( 'gstore_fuzzy_search_products' ) ) {
		$fuzzy_ids = gstore_fuzzy_search_products( $search_term, $fuzzy_floor - count( $ranked_ids ), $ranked_ids );
		if ( ! empty( $fuzzy_ids ) ) {
			$ranked_ids = array_values( array_unique( array_merge( $ranked_ids, $fuzzy_ids ) ) );
		}
	}

	if ( count( $ranked_ids ) > $limit ) {
		$ranked_ids = array_slice( $ranked_ids, 0, $limit );
	}

	return $ranked_ids;
}

/**
 * Busca fuzzy de produtos por titulo com cobertura de todos os termos.
 *
 * @param string $search_term Termo digitado pelo usuario.
 * @param int    $limit       Maximo de IDs a retornar.
 * @param array  $exclude_ids IDs a excluir.
 * @return int[] IDs de produtos ordenados por similaridade.
 */
function gstore_fuzzy_search_products( $search_term, $limit = 20, $exclude_ids = array() ) {
	global $wpdb;

	$needle       = gstore_normalize_product_search_text( $search_term );
	$needle_words = gstore_tokenize_product_search_term( $search_term );
	$needle_len   = function_exists( 'mb_strlen' ) ? mb_strlen( $needle ) : strlen( $needle );
	$limit        = max( 0, absint( $limit ) );

	if ( $needle === '' || $needle_len < 2 || $limit <= 0 || empty( $needle_words ) ) {
		return array();
	}

	$exclude_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $exclude_ids ) ) ) );
	$cache_key   = 'gstore_fuzzy_v3_' . md5( $needle . '_' . implode( ',', $exclude_ids ) . '_' . $limit );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	// Busca todos os titulos de produtos publicados.
	$products = $wpdb->get_results(
		"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
	);

	if ( empty( $products ) ) {
		return array();
	}

	$exclude_map = array_flip( $exclude_ids );
	$scored      = array();

	foreach ( $products as $product ) {
		if ( isset( $exclude_map[ (int) $product->ID ] ) ) {
			continue;
		}

		$title = gstore_normalize_product_search_text( $product->post_title );

		// Pula se ja eh match exato por substring (WP_Query ja encontra).
		if ( strpos( $title, $needle ) !== false ) {
			continue;
		}

		$title_words = preg_split( '/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY );
		if ( empty( $title_words ) ) {
			continue;
		}

		$total_score = 0;
		$matched_all = true;

		foreach ( $needle_words as $nw ) {
			$nw_len = function_exists( 'mb_strlen' ) ? mb_strlen( $nw ) : strlen( $nw );
			if ( $nw_len < 2 ) {
				continue;
			}

			$best_score = 0;
			foreach ( $title_words as $tw ) {
				$tw_len = function_exists( 'mb_strlen' ) ? mb_strlen( $tw ) : strlen( $tw );
				if ( $tw_len < 2 ) {
					continue;
				}

				if ( $tw === $nw ) {
					$best_score = 1;
					break;
				}

				if ( function_exists( 'gstore_get_short_model_token_fuzzy_score' ) ) {
					$best_score = max( $best_score, gstore_get_short_model_token_fuzzy_score( $nw, $tw ) );
				}

				if ( $nw_len >= 4 && 0 === strpos( $tw, $nw ) ) {
					$best_score = max( $best_score, 0.9 );
				} elseif ( $nw_len >= 4 && $tw_len >= 4 && false !== strpos( $tw, $nw ) ) {
					$best_score = max( $best_score, 0.84 );
				}

				$lev  = levenshtein( $nw, $tw );
				$mlen = max( $nw_len, $tw_len );
				if ( $mlen > 0 ) {
					$sim = 1 - ( $lev / $mlen );
					$best_score = max( $best_score, $sim );
				}
			}

			$threshold = $nw_len <= 3 ? 0.86 : 0.72;
			if ( $best_score < $threshold ) {
				$matched_all = false;
				break;
			}

			$total_score += $best_score;
		}

		if ( ! $matched_all ) {
			continue;
		}

		$avg_score = $total_score / max( 1, count( $needle_words ) );
		$scored[]  = array(
			'id'    => (int) $product->ID,
			'score' => $avg_score,
		);
	}

	usort( $scored, function ( $a, $b ) {
		if ( $a['score'] === $b['score'] ) {
			return $a['id'] <=> $b['id'];
		}

		return $b['score'] <=> $a['score'];
	} );

	$result = array();
	$count  = min( count( $scored ), $limit );
	for ( $i = 0; $i < $count; $i++ ) {
		$result[] = $scored[ $i ]['id'];
	}

	set_transient( $cache_key, $result, 120 );

	return $result;
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

	// Processa placeholders de informações da loja (store-info.json)
	if ( function_exists( 'gstore_process_store_info_placeholders' ) ) {
		$buffer = gstore_process_store_info_placeholders( $buffer );
	}

	// Processa placeholders de imagens no output final
	$buffer = gstore_process_image_placeholders( $buffer );

	// Substitui a logo no header se configurada
	$buffer = gstore_replace_header_logo_html( $buffer );

	// Remove classe Gstore-cart-shell do wrapper do WooCommerce para evitar conflitos
	// O bloco page-content-wrapper adiciona classes que geram padding indesejado
	$buffer = gstore_strip_cart_shell_from_wc_wrapper( $buffer );

	$buffer = gstore_replace_catalog_dynamic_breadcrumb_html( $buffer );

	$buffer = gstore_replace_catalog_brand_archive_header_html( $buffer );

	$buffer = gstore_normalize_catalog_archive_description_output( $buffer );

	return $buffer;
}

/**
 * Fallback final para o header de marcas quando o template em bloco renderiza o header padrao.
 *
 * @param string $html HTML final.
 * @return string
 */
function gstore_replace_catalog_brand_archive_header_html( $html ) {
	if ( ! is_string( $html ) || '' === $html || false === strpos( $html, 'woocommerce-products-header' ) ) {
		return $html;
	}

	if ( false !== strpos( $html, 'Gstore-catalog-archive-intro' ) ) {
		return $html;
	}

	if ( ! gstore_get_catalog_archive_brand_term() ) {
		return $html;
	}

	$title       = gstore_get_catalog_archive_title();
	$description = gstore_get_catalog_archive_description_html();
	$summary     = function_exists( 'gstore_get_catalog_archive_summary' )
		? gstore_get_catalog_archive_summary( $description )
		: ( function_exists( 'gstore_get_catalog_brand_archive_summary' ) ? gstore_get_catalog_brand_archive_summary( $description ) : '' );

	if ( '' === $title && '' === $summary ) {
		return $html;
	}

	$classes = array( 'woocommerce-products-header', 'Gstore-catalog-archive-intro' );

	$replacement = '<header class="' . esc_attr( implode( ' ', $classes ) ) . '">';
	if ( '' !== $title ) {
		$replacement .= '<h1 class="woocommerce-products-header__title page-title Gstore-catalog-archive-title">' . esc_html( $title ) . '</h1>';
	}
	if ( '' !== $summary ) {
		$replacement .= '<div class="term-description Gstore-catalog-archive-summary"><p>' . esc_html( $summary ) . '</p></div>';
	}
	$replacement .= '</header>';

	return (string) preg_replace(
		'#<header\b(?=[^>]*class="[^"]*\bwoocommerce-products-header\b[^"]*")[^>]*>.*?</header>#is',
		$replacement,
		$html,
		1
	);
}

/**
 * Fallback final para garantir que archives de produto mostrem resumo no topo.
 *
 * @param string $html HTML final.
 * @return string
 */
function gstore_normalize_catalog_archive_description_output( $html ) {
	if ( ! is_string( $html ) || '' === $html || false === strpos( $html, 'woocommerce-products-header' ) ) {
		return $html;
	}

	if ( function_exists( 'gstore_is_catalog_context' ) && ! gstore_is_catalog_context() ) {
		return $html;
	}

	$summary = gstore_get_catalog_archive_summary( gstore_get_catalog_archive_description_html() );
	if ( '' === $summary ) {
		return $html;
	}

	$summary_html = '<div class="term-description Gstore-catalog-archive-summary"><p>' . esc_html( $summary ) . '</p></div>';

	if ( false !== strpos( $html, 'Gstore-catalog-archive-summary' ) ) {
		return $html;
	}

	return (string) preg_replace(
		'#<div\b([^>]*)\bclass=(["\'])([^"\']*\bterm-description\b[^"\']*)\2([^>]*)>.*?</div>#is',
		$summary_html,
		$html,
		1
	);
}

/**
 * Monta breadcrumb hierarquico para archives nativos de product_cat.
 *
 * @return string
 */
function gstore_get_product_category_archive_breadcrumb_html() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return '';
	}

	$term = get_queried_object();
	if ( ! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy ) {
		return '';
	}

	$items = array(
		array(
			'label' => __( 'Início', 'gstore' ),
			'url'   => home_url( '/' ),
		),
		array(
			'label' => __( 'Catálogo', 'gstore' ),
			'url'   => function_exists( 'gstore_get_catalog_url' ) ? gstore_get_catalog_url() : home_url( '/catalogo/' ),
		),
	);

	$ancestor_ids = array_reverse( array_map( 'absint', get_ancestors( (int) $term->term_id, 'product_cat', 'taxonomy' ) ) );
	foreach ( $ancestor_ids as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, 'product_cat' );
		if ( ! $ancestor instanceof WP_Term || is_wp_error( $ancestor ) ) {
			continue;
		}

		$link = get_term_link( $ancestor, 'product_cat' );
		if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
			continue;
		}

		$items[] = array(
			'label' => $ancestor->name,
			'url'   => $link,
		);
	}

	$items[] = array(
		'label' => $term->name,
		'url'   => '',
	);

	$html = '<nav class="Gstore-breadcrumb Gstore-breadcrumb--dynamic" aria-label="' . esc_attr__( 'Navegação', 'gstore' ) . '">';
	$last = count( $items ) - 1;
	foreach ( $items as $index => $item ) {
		if ( $index > 0 ) {
			$html .= '<span class="Gstore-breadcrumb__separator">/</span>';
		}

		if ( $index === $last || empty( $item['url'] ) ) {
			$html .= '<span class="Gstore-breadcrumb__current Gstore-breadcrumb__current-term">' . esc_html( $item['label'] ) . '</span>';
			continue;
		}

		$html .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
	}
	$html .= '</nav>';

	return $html;
}

/**
 * Substitui o breadcrumb estatico do template de categoria pelo breadcrumb hierarquico.
 *
 * @param string $html HTML final.
 * @return string
 */
function gstore_replace_catalog_dynamic_breadcrumb_html( $html ) {
	if ( ! is_string( $html ) || false === strpos( $html, 'Gstore-breadcrumb--dynamic' ) ) {
		return $html;
	}

	$breadcrumb = gstore_get_product_category_archive_breadcrumb_html();
	if ( '' === $breadcrumb ) {
		return $html;
	}

	return (string) preg_replace(
		'#<nav\b[^>]*\bGstore-breadcrumb--dynamic\b[^>]*>.*?</nav>#is',
		$breadcrumb,
		$html,
		1
	);
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
			'description' => 'Página principal da loja WooCommerce com layout de catálogo. (REDIRECIONADA PARA CATÁLOGO)',
			'wc_option'   => 'woocommerce_shop_page_id',
			'redirect_to' => 'catalogo',
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
		'sobre-nos' => array(
			'title'       => 'Sobre nós',
			'slug'        => 'sobre-nos',
			'template'    => 'page-sobre-nos',
			'content'     => '',
			'description' => 'Página institucional com dados da empresa, compra legal, documentação, prazos e envio.',
			'wc_option'   => null,
		),
		'como-comprar-arma' => array(
			'title'       => 'Passos para Compra de Arma',
			'slug'        => 'como-comprar-arma',
			'template'    => 'page-como-comprar-arma',
			'content'     => '',
			'description' => 'Página com informações sobre o processo de compra de armas.',
			'wc_option'   => null,
		),
		'informativo' => array(
			'title'       => 'Informativo',
			'slug'        => 'informativo',
			'template'    => 'page-informativo',
			'content'     => '',
			'description' => 'Página de pós-venda com documentos, etapas e informações de envio.',
			'wc_option'   => null,
		),
		'blog' => array(
			'title'       => 'Blog',
			'slug'        => 'blog',
			'template'    => 'page-blog',
			'content'     => '',
			'description' => 'Página do blog com artigos e notícias.',
			'wc_option'   => null,
		),
		'politica-de-privacidade' => array(
			'title'       => 'Política de Privacidade',
			'slug'        => 'politica-de-privacidade',
			'template'    => 'page-politica-de-privacidade',
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
			'template'    => 'page-blog',
			'content'     => '',
			'description' => 'Página do blog com artigos e notícias.',
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
 * Adiciona submenu para visualizar Design Tokens.
 */
function gstore_add_design_tokens_submenu() {
	add_submenu_page(
		'gstore-setup',
		__( 'Design Tokens', 'gstore' ),
		__( 'Design Tokens', 'gstore' ),
		'manage_options',
		'gstore-design-tokens',
		'gstore_render_design_tokens_page'
	);
}
add_action( 'admin_menu', 'gstore_add_design_tokens_submenu' );

/**
 * Renderiza a página de Design Tokens.
 */
function gstore_render_design_tokens_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Obtém a cor de accent persistida no banco, que é a fonte da verdade.
	$accent_color      = gstore_ensure_persisted_accent_color();
	$tokens_file_color = gstore_get_accent_color_from_tokens_file();

	// Lê o arquivo de tokens
	$tokens_file = get_theme_file_path( 'assets/css/tokens.css' );
	$tokens_content = file_exists( $tokens_file ) ? file_get_contents( $tokens_file ) : '';

	// Extrai as cores do arquivo
	$colors = gstore_extract_colors_from_tokens( $tokens_content );

	// Gera preview dos tokens derivados
	$derived_tokens = gstore_generate_accent_tokens( $accent_color );

	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'Design Tokens - GStore', 'gstore' ) ); ?></h1>
		<p class="description"><?php echo esc_html( __( 'Visualize todos os tokens de cor, tipografia, espaçamento e outros tokens de design do tema.', 'gstore' ) ); ?></p>

		<!-- Seletor de Cor de Accent -->
		<div class="gstore-accent-selector" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h2 style="margin-top: 0;"><?php echo esc_html( __( 'Cor de Accent', 'gstore' ) ); ?></h2>
			<p class="description"><?php echo esc_html( __( 'Escolha a cor de accent principal. Os tokens derivados (hover, dark, light, transparências) serão gerados automaticamente.', 'gstore' ) ); ?></p>
			<div class="gstore-accent-protection">
				<strong><?php echo esc_html( __( 'Proteção contra atualização via Git ativa.', 'gstore' ) ); ?></strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: saved color, 2: tokens file color. */
						__( 'A cor salva no banco é %1$s. Cor atual em tokens.css: %2$s. Se uma atualização sobrescrever o arquivo, o tema reaplica a cor salva automaticamente.', 'gstore' ),
						strtoupper( $accent_color ),
						$tokens_file_color ? strtoupper( $tokens_file_color ) : __( 'não encontrada', 'gstore' )
					)
				);
				?>
			</div>

			<form id="gstore-accent-color-form" method="post" action="">
				<?php wp_nonce_field( 'gstore_save_accent_color', 'gstore_accent_color_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="gstore_accent_color"><?php echo esc_html( __( 'Cor de Accent', 'gstore' ) ); ?></label>
						</th>
						<td>
							<input
								type="color"
								id="gstore_accent_color"
								name="gstore_accent_color"
								value="<?php echo esc_attr( $accent_color ); ?>"
								style="width: 80px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;"
							/>
							<input
								type="text"
								id="gstore_accent_color_text"
								value="<?php echo esc_attr( $accent_color ); ?>"
								pattern="^#[0-9A-Fa-f]{6}$"
								style="width: 100px; margin-left: 10px; padding: 5px;"
								placeholder="#b5a642"
							/>
							<p class="description"><?php echo esc_html( __( 'Digite ou selecione uma cor em formato hexadecimal.', 'gstore' ) ); ?></p>
							<label class="gstore-accent-confirm" for="gstore_accent_confirm">
								<input type="checkbox" id="gstore_accent_confirm" name="gstore_accent_confirm" value="1" />
								<?php echo esc_html( __( 'Confirmo que quero substituir a cor salva do site.', 'gstore' ) ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit gstore-accent-actions">
					<button type="submit" class="button button-primary button-hero" id="gstore-save-accent-color">
						<?php echo esc_html( __( 'Salvar Cor e Atualizar Tokens', 'gstore' ) ); ?>
					</button>
					<span class="spinner" id="gstore-accent-color-spinner" style="float: none; margin-left: 10px;"></span>
				</p>
				<div id="gstore-accent-color-message" style="margin-top: 10px;"></div>

				<!-- Preview dos Tokens Derivados -->
				<div class="gstore-derived-tokens-preview" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
					<h3 style="margin-top: 0;"><?php echo esc_html( __( 'Preview dos Tokens Derivados', 'gstore' ) ); ?></h3>
					<div class="gstore-color-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
						<?php foreach ( $derived_tokens as $token_name => $token_value ) : ?>
							<div class="gstore-color-item" style="border: 1px solid #ddd; border-radius: 4px; overflow: hidden; background: #fff;">
								<div class="gstore-color-preview" style="width: 100%; height: 60px; display: flex; align-items: center; justify-content: center; font-weight: 600; background-color: <?php echo esc_attr( $token_value ); ?>; color: <?php echo esc_attr( gstore_get_contrast_color( $token_value ) ); ?>;">
									<?php echo esc_html( $token_value ); ?>
								</div>
								<div class="gstore-color-info" style="padding: 12px;">
									<strong style="display: block; margin-bottom: 5px; font-size: 13px; color: #1d2327;">
										--gstore-color-accent<?php echo $token_name !== 'accent' ? '-' . esc_html( $token_name ) : ''; ?>
									</strong>
									<code style="background: #f6f7f7; padding: 3px 6px; border-radius: 3px; font-size: 12px; color: #2271b1;">
										<?php echo esc_html( $token_value ); ?>
									</code>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</form>
		</div>

		<div class="gstore-tokens-container" style="margin-top: 20px;">
			<?php gstore_render_color_tokens( $colors ); ?>
		</div>
	</div>

	<style>
		.gstore-tokens-container {
			display: grid;
			gap: 20px;
		}
		.gstore-token-section {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			padding: 20px;
			box-shadow: 0 1px 1px rgba(0,0,0,.04);
		}
		.gstore-token-section h2 {
			margin-top: 0;
			padding-bottom: 10px;
			border-bottom: 2px solid #f0f0f1;
		}
		.gstore-color-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
			gap: 15px;
			margin-top: 15px;
		}
		.gstore-color-item {
			border: 1px solid #ddd;
			border-radius: 4px;
			overflow: hidden;
			background: #fff;
		}
		.gstore-color-preview {
			width: 100%;
			height: 80px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: 600;
			position: relative;
		}
		.gstore-color-info {
			padding: 12px;
		}
		.gstore-color-info strong {
			display: block;
			margin-bottom: 5px;
			font-size: 13px;
			color: #1d2327;
		}
		.gstore-color-info code {
			background: #f6f7f7;
			padding: 3px 6px;
			border-radius: 3px;
			font-size: 12px;
			color: #2271b1;
			cursor: pointer;
		}
		.gstore-color-info code:hover {
			background: #e5e5e5;
		}
		.gstore-color-value {
			font-size: 12px;
			color: #646970;
			margin-top: 5px;
		}
		.gstore-accent-actions {
			display: flex;
			align-items: center;
			gap: 10px;
			margin: 18px 0 0;
			padding: 14px 0 0;
			border-top: 1px solid #f0f0f1;
		}
		.gstore-accent-actions .button {
			display: inline-flex !important;
			align-items: center;
			justify-content: center;
			min-height: 40px;
			visibility: visible !important;
			opacity: 1 !important;
		}
		.gstore-accent-protection {
			margin: 14px 0 0;
			padding: 12px 14px;
			border-left: 4px solid #b5a642;
			background: rgba(181, 166, 66, 0.10);
			color: #1d2327;
		}
		.gstore-accent-protection strong {
			display: block;
			margin-bottom: 4px;
		}
		.gstore-accent-confirm {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			margin-top: 10px;
			font-weight: 600;
		}
		.gstore-token-copy-message {
			position: fixed;
			top: 32px;
			right: 20px;
			background: #00a32a;
			color: #fff;
			padding: 10px 15px;
			border-radius: 4px;
			box-shadow: 0 2px 5px rgba(0,0,0,0.2);
			z-index: 100000;
			display: none;
		}
		.gstore-token-copy-message.show {
			display: block;
			animation: slideIn 0.3s ease;
		}
		@keyframes slideIn {
			from {
				transform: translateX(100%);
				opacity: 0;
			}
			to {
				transform: translateX(0);
				opacity: 1;
			}
		}
	</style>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const colorCodes = document.querySelectorAll('.gstore-color-info code');
			const copyMessage = document.createElement('div');
			copyMessage.className = 'gstore-token-copy-message';
			copyMessage.textContent = 'Token copiado!';
			document.body.appendChild(copyMessage);

			colorCodes.forEach(code => {
				code.addEventListener('click', function() {
					const text = this.textContent;
					navigator.clipboard.writeText(text).then(() => {
						copyMessage.classList.add('show');
						setTimeout(() => {
							copyMessage.classList.remove('show');
						}, 2000);
					});
				});
			});

			// Sincroniza o seletor de cor com o input de texto
			const colorPicker = document.getElementById('gstore_accent_color');
			const colorText = document.getElementById('gstore_accent_color_text');
			const confirmChange = document.getElementById('gstore_accent_confirm');
			const savedAccentColor = '<?php echo esc_js( strtolower( $accent_color ) ); ?>';

			if (colorPicker && colorText) {
				// Atualiza o texto quando o seletor muda
				colorPicker.addEventListener('input', function() {
					colorText.value = this.value.toUpperCase();
					updateDerivedTokensPreview();
				});

				// Atualiza o seletor quando o texto muda
				colorText.addEventListener('input', function() {
					const value = this.value.trim();
					if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
						colorPicker.value = value;
						updateDerivedTokensPreview();
					}
				});

				// Valida o formato quando o campo perde o foco
				colorText.addEventListener('blur', function() {
					const value = this.value.trim();
					if (!/^#[0-9A-Fa-f]{6}$/.test(value)) {
						this.value = colorPicker.value.toUpperCase();
					}
				});
			}

			// Atualiza o preview dos tokens derivados
			function updateDerivedTokensPreview() {
				const color = colorPicker.value;
				const previews = document.querySelectorAll('.gstore-derived-tokens-preview .gstore-color-preview');
				const codes = document.querySelectorAll('.gstore-derived-tokens-preview .gstore-color-info code');

				// Faz requisição AJAX para obter os tokens derivados
				const formData = new FormData();
				formData.append('action', 'gstore_get_derived_tokens');
				formData.append('accent_color', color);
				formData.append('nonce', '<?php echo wp_create_nonce( 'gstore_get_derived_tokens' ); ?>');

				fetch(ajaxurl, {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success && data.data) {
						const tokens = data.data;
						const tokenNames = ['accent', 'accent-hover', 'accent-dark', 'accent-light', 'accent-08', 'accent-10', 'accent-12', 'accent-15', 'accent-20'];

						previews.forEach((preview, index) => {
							if (tokens[tokenNames[index]]) {
								const tokenValue = tokens[tokenNames[index]];
								preview.style.backgroundColor = tokenValue;
								preview.textContent = tokenValue;

								// Atualiza cor do texto baseado no contraste
								// Para rgba, extrai os valores RGB
								let rgb = null;
								if (tokenValue.startsWith('rgba')) {
									const rgbaMatch = tokenValue.match(/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/);
									if (rgbaMatch) {
										rgb = {
											r: parseInt(rgbaMatch[1]),
											g: parseInt(rgbaMatch[2]),
											b: parseInt(rgbaMatch[3])
										};
									}
								} else {
									rgb = hexToRgb(tokenValue);
								}

								if (rgb) {
									const luminance = (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255;
									preview.style.color = luminance > 0.5 ? '#000' : '#fff';
								} else {
									preview.style.color = '#000';
								}
							}
						});

						codes.forEach((code, index) => {
							if (tokens[tokenNames[index]]) {
								code.textContent = tokens[tokenNames[index]];
							}
						});
					}
				})
				.catch(error => {
					console.error('Erro ao atualizar preview:', error);
				});
			}

			// Função auxiliar para converter hex para RGB
			function hexToRgb(hex) {
				const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
				return result ? {
					r: parseInt(result[1], 16),
					g: parseInt(result[2], 16),
					b: parseInt(result[3], 16)
				} : null;
			}

			// Submissão do formulário via AJAX
			const form = document.getElementById('gstore-accent-color-form');
			if (form) {
				form.addEventListener('submit', function(e) {
					e.preventDefault();

					const submitButton = document.getElementById('gstore-save-accent-color');
					const spinner = document.getElementById('gstore-accent-color-spinner');
					const message = document.getElementById('gstore-accent-color-message');
					const selectedColor = colorPicker ? colorPicker.value.trim().toLowerCase() : '';

					if (selectedColor && selectedColor !== savedAccentColor && confirmChange && !confirmChange.checked) {
						message.innerHTML = '<div class="notice notice-warning is-dismissible"><p>Marque a confirmação para substituir a cor salva do site.</p></div>';
						return;
					}

					submitButton.disabled = true;
					spinner.classList.add('is-active');
					message.innerHTML = '';

					const formData = new FormData(form);
					formData.append('action', 'gstore_save_accent_color');

					fetch(ajaxurl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						spinner.classList.remove('is-active');
						submitButton.disabled = false;

						if (data.success) {
							message.innerHTML = '<div class="notice notice-success is-dismissible"><p>' + data.data.message + '</p></div>';
							// Recarrega a página após 1 segundo para mostrar os tokens atualizados
							setTimeout(() => {
								window.location.reload();
							}, 1000);
						} else {
							message.innerHTML = '<div class="notice notice-error is-dismissible"><p>' + (data.data && data.data.message ? data.data.message : 'Erro ao salvar a cor.') + '</p></div>';
						}
					})
					.catch(error => {
						spinner.classList.remove('is-active');
						submitButton.disabled = false;
						message.innerHTML = '<div class="notice notice-error is-dismissible"><p>Erro ao salvar a cor. Tente novamente.</p></div>';
						console.error('Erro:', error);
					});
				});
			}
		});
	</script>
	<?php
}

/**
 * Endpoint AJAX para obter tokens derivados.
 */
function gstore_ajax_get_derived_tokens() {
	check_ajax_referer( 'gstore_get_derived_tokens', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}

	$accent_color = isset( $_POST['accent_color'] ) ? sanitize_hex_color( $_POST['accent_color'] ) : '#b5a642';

	if ( ! $accent_color ) {
		wp_send_json_error( array( 'message' => __( 'Cor inválida.', 'gstore' ) ) );
	}

	$tokens = gstore_generate_accent_tokens( $accent_color );

	wp_send_json_success( $tokens );
}
add_action( 'wp_ajax_gstore_get_derived_tokens', 'gstore_ajax_get_derived_tokens' );

/**
 * Endpoint AJAX para salvar a cor de accent e atualizar tokens.
 */
function gstore_ajax_save_accent_color() {
	check_ajax_referer( 'gstore_save_accent_color', 'gstore_accent_color_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}

	$accent_color = isset( $_POST['gstore_accent_color'] ) ? sanitize_hex_color( $_POST['gstore_accent_color'] ) : '';

	if ( ! $accent_color ) {
		wp_send_json_error( array( 'message' => __( 'Cor inválida. Por favor, selecione uma cor válida.', 'gstore' ) ) );
	}

	$current_accent_color = gstore_get_effective_accent_color();
	$confirmed_change     = ! empty( $_POST['gstore_accent_confirm'] );

	if ( strtolower( $accent_color ) !== strtolower( $current_accent_color ) && ! $confirmed_change ) {
		wp_send_json_error( array( 'message' => __( 'Confirme a troca para substituir a cor salva do site.', 'gstore' ) ) );
	}

	// Salva a opção
	gstore_persist_accent_design_token_overrides( $accent_color );
	gstore_sync_store_info_accent_color( $accent_color );

	// Atualiza o arquivo tokens.css
	$result = gstore_update_accent_tokens_in_file( $accent_color );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	// Atualiza o timestamp para forçar recarregamento do CSS
	update_option( 'gstore_tokens_last_updated', time() );

	// Limpa cache do WordPress se disponível
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	wp_send_json_success( array(
		'message' => __( 'Cor de accent salva e tokens atualizados com sucesso!', 'gstore' ),
		'tokens' => gstore_generate_accent_tokens( $accent_color ),
		'file_updated' => true
	) );
}
add_action( 'wp_ajax_gstore_save_accent_color', 'gstore_ajax_save_accent_color' );

/**
 * Extrai cores do conteúdo do arquivo de tokens.
 */
function gstore_extract_colors_from_tokens( $content ) {
	$colors = array(
		'fundos' => array(),
		'textos' => array(),
		'acentos' => array(),
		'bordas' => array(),
		'ratings' => array(),
		'transparencia' => array(),
		'estados' => array(),
	);

	// Primeiro, cria um mapa de todas as variáveis para resolver referências
	$var_map = array();
	preg_match_all('/--gstore-color-([^:]+):\s*([^;]+);/', $content, $all_matches, PREG_SET_ORDER);
	foreach ( $all_matches as $match ) {
		$var_name = '--gstore-color-' . trim( $match[1] );
		$var_value = trim( $match[2] );
		$var_map[ $var_name ] = $var_value;
	}

	// Padrão para encontrar variáveis CSS
	preg_match_all('/--gstore-color-([^:]+):\s*([^;]+);/', $content, $matches, PREG_SET_ORDER);

	foreach ( $matches as $match ) {
		$name = trim( $match[1] );
		$value = trim( $match[2] );

		// Resolve variáveis CSS se for uma referência
		$resolved_value = gstore_resolve_css_variable( $value, $var_map );

		// Categoriza as cores
		if ( strpos( $name, 'bg-' ) === 0 ) {
			$colors['fundos'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, 'text-' ) === 0 ) {
			$colors['textos'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, 'accent' ) !== false || strpos( $name, 'success' ) !== false || strpos( $name, 'error' ) !== false || strpos( $name, 'warning' ) !== false ) {
			if ( strpos( $name, '-' ) !== false && ( strpos( $name, '-10' ) !== false || strpos( $name, '-12' ) !== false || strpos( $name, '-15' ) !== false || strpos( $name, '-20' ) !== false || strpos( $name, '-08' ) !== false ) ) {
				$colors['transparencia'][] = array(
					'name' => '--gstore-color-' . $name,
					'value' => $value,
					'resolved' => $resolved_value,
				);
			} else {
				$colors['acentos'][] = array(
					'name' => '--gstore-color-' . $name,
					'value' => $value,
					'resolved' => $resolved_value,
				);
			}
		} elseif ( strpos( $name, 'border' ) !== false ) {
			$colors['bordas'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, 'rating' ) !== false ) {
			$colors['ratings'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, 'white' ) !== false || strpos( $name, 'black' ) !== false ) {
			$colors['transparencia'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, '-bg' ) !== false || strpos( $name, '-border' ) !== false || strpos( $name, '-text' ) !== false ) {
			$colors['estados'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		}
	}

	return $colors;
}

/**
 * Resolve uma variável CSS para seu valor final.
 */
function gstore_resolve_css_variable( $value, $var_map, $depth = 0 ) {
	// Limita a profundidade para evitar loops infinitos
	if ( $depth > 10 ) {
		return $value;
	}

	// Se não é uma variável, retorna o valor
	if ( strpos( $value, 'var(' ) !== 0 ) {
		return $value;
	}

	// Extrai o nome da variável
	preg_match( '/var\(([^)]+)\)/', $value, $matches );
	if ( empty( $matches[1] ) ) {
		return $value;
	}

	$var_name = trim( $matches[1] );

	// Se a variável existe no mapa, resolve recursivamente
	if ( isset( $var_map[ $var_name ] ) ) {
		return gstore_resolve_css_variable( $var_map[ $var_name ], $var_map, $depth + 1 );
	}

	return $value;
}

/**
 * Renderiza os tokens de cor na página.
 */
function gstore_render_color_tokens( $colors ) {
	$sections = array(
		'fundos' => __( 'Fundos', 'gstore' ),
		'textos' => __( 'Textos', 'gstore' ),
		'acentos' => __( 'Acentos e Estados', 'gstore' ),
		'bordas' => __( 'Bordas', 'gstore' ),
		'ratings' => __( 'Ratings', 'gstore' ),
		'transparencia' => __( 'Cores com Transparência', 'gstore' ),
		'estados' => __( 'Estados Específicos', 'gstore' ),
	);

	foreach ( $sections as $key => $title ) {
		if ( empty( $colors[ $key ] ) ) {
			continue;
		}

		?>
		<div class="gstore-token-section">
			<h2><?php echo esc_html( $title ); ?></h2>
			<div class="gstore-color-grid">
				<?php foreach ( $colors[ $key ] as $color ) :
					$color_value = isset( $color['resolved'] ) && $color['resolved'] !== $color['value'] ? $color['resolved'] : $color['value'];
					$display_value = $color_value;

					// Se ainda for uma variável não resolvida, tenta usar o valor original
					if ( strpos( $color_value, 'var(' ) === 0 ) {
						$display_value = $color['value'];
						$color_value = '#f0f0f0'; // Cor padrão para variáveis não resolvidas
					}

					// Determina se o texto deve ser claro ou escuro
					$text_color = gstore_get_contrast_color( $color_value );
				?>
					<div class="gstore-color-item">
						<div class="gstore-color-preview" style="background-color: <?php echo esc_attr( $color_value ); ?>; color: <?php echo esc_attr( $text_color ); ?>;">
							<?php echo esc_html( $display_value ); ?>
						</div>
						<div class="gstore-color-info">
							<strong><?php echo esc_html( str_replace( '--gstore-color-', '', $color['name'] ) ); ?></strong>
							<code><?php echo esc_html( $color['name'] ); ?></code>
							<div class="gstore-color-value"><?php echo esc_html( $color['value'] ); ?></div>
							<?php if ( isset( $color['resolved'] ) && $color['resolved'] !== $color['value'] && strpos( $color['value'], 'var(' ) === 0 ) : ?>
								<div class="gstore-color-value" style="color: #2271b1; margin-top: 3px;">
									→ <?php echo esc_html( $color['resolved'] ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

/**
 * Determina a cor do texto baseado no contraste do fundo.
 */
function gstore_get_contrast_color( $color ) {
	// Se for rgba ou variável, retorna preto por padrão
	if ( strpos( $color, 'rgba' ) === 0 || strpos( $color, 'var(' ) === 0 ) {
		// Para rgba, tenta extrair a opacidade
		if ( preg_match( '/rgba\([^)]+,\s*([\d.]+)\)/', $color, $matches ) ) {
			$opacity = floatval( $matches[1] );
			// Se a opacidade for muito baixa, usa texto escuro
			return $opacity < 0.5 ? '#000' : '#fff';
		}
		return '#000';
	}

	// Remove # se existir
	$color = ltrim( $color, '#' );

	// Se não for hex válido, retorna preto
	if ( ! preg_match( '/^[0-9a-fA-F]{3,6}$/', $color ) ) {
		return '#000';
	}

	// Converte hex para RGB
	if ( strlen( $color ) === 3 ) {
		$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
	}

	$r = hexdec( substr( $color, 0, 2 ) );
	$g = hexdec( substr( $color, 2, 2 ) );
	$b = hexdec( substr( $color, 4, 2 ) );

	// Calcula luminância relativa
	$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;

	// Retorna branco para fundos escuros, preto para fundos claros
	return $luminance > 0.5 ? '#000' : '#fff';
}

/**
 * Converte cor hex para RGB.
 *
 * @param string $hex Cor em formato hex (#RRGGBB ou RRGGBB).
 * @return array|false Array com r, g, b ou false se inválido.
 */
function gstore_hex_to_rgb( $hex ) {
	$hex = ltrim( $hex, '#' );

	// Se não for hex válido, retorna false
	if ( ! preg_match( '/^[0-9a-fA-F]{3,6}$/', $hex ) ) {
		return false;
	}

	// Converte hex curto para completo
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	return array(
		'r' => hexdec( substr( $hex, 0, 2 ) ),
		'g' => hexdec( substr( $hex, 2, 2 ) ),
		'b' => hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Converte RGB para hex.
 *
 * @param int $r Valor R (0-255).
 * @param int $g Valor G (0-255).
 * @param int $b Valor B (0-255).
 * @return string Cor em formato hex (#RRGGBB).
 */
function gstore_rgb_to_hex( $r, $g, $b ) {
	$r = max( 0, min( 255, intval( $r ) ) );
	$g = max( 0, min( 255, intval( $g ) ) );
	$b = max( 0, min( 255, intval( $b ) ) );
	return sprintf( '#%02x%02x%02x', $r, $g, $b );
}

/**
 * Escurece uma cor hex.
 *
 * @param string $hex Cor em formato hex.
 * @param float $percent Porcentagem para escurecer (0-100).
 * @return string Cor escurecida em hex.
 */
function gstore_darken_color( $hex, $percent = 15 ) {
	$rgb = gstore_hex_to_rgb( $hex );
	if ( ! $rgb ) {
		return $hex;
	}

	$percent = max( 0, min( 100, floatval( $percent ) ) );
	$factor = 1 - ( $percent / 100 );

	return gstore_rgb_to_hex(
		$rgb['r'] * $factor,
		$rgb['g'] * $factor,
		$rgb['b'] * $factor
	);
}

/**
 * Clareia uma cor hex.
 *
 * @param string $hex Cor em formato hex.
 * @param float $percent Porcentagem para clarear (0-100).
 * @return string Cor clareada em hex.
 */
function gstore_lighten_color( $hex, $percent = 15 ) {
	$rgb = gstore_hex_to_rgb( $hex );
	if ( ! $rgb ) {
		return $hex;
	}

	$percent = max( 0, min( 100, floatval( $percent ) ) );
	$factor = 1 + ( $percent / 100 );

	return gstore_rgb_to_hex(
		min( 255, $rgb['r'] * $factor ),
		min( 255, $rgb['g'] * $factor ),
		min( 255, $rgb['b'] * $factor )
	);
}

/**
 * Converte cor hex para rgba.
 *
 * @param string $hex Cor em formato hex.
 * @param float $opacity Opacidade (0-1).
 * @return string Cor em formato rgba.
 */
function gstore_hex_to_rgba( $hex, $opacity = 1 ) {
	$rgb = gstore_hex_to_rgb( $hex );
	if ( ! $rgb ) {
		return $hex;
	}

	$opacity = max( 0, min( 1, floatval( $opacity ) ) );
	return sprintf( 'rgba(%d, %d, %d, %.2f)', $rgb['r'], $rgb['g'], $rgb['b'], $opacity );
}

/**
 * Gera tokens derivados baseados na cor de accent.
 *
 * @param string $accent_color Cor de accent em hex.
 * @return array Array com todos os tokens derivados.
 */
function gstore_generate_accent_tokens( $accent_color ) {
	$rgb = gstore_hex_to_rgb( $accent_color );
	if ( ! $rgb ) {
		return array();
	}

	return array(
		'accent' => $accent_color,
		'accent-hover' => gstore_darken_color( $accent_color, 12 ),
		'accent-dark' => gstore_darken_color( $accent_color, 25 ),
		'accent-light' => gstore_lighten_color( $accent_color, 10 ),
		'accent-08' => gstore_hex_to_rgba( $accent_color, 0.08 ),
		'accent-10' => gstore_hex_to_rgba( $accent_color, 0.1 ),
		'accent-12' => gstore_hex_to_rgba( $accent_color, 0.12 ),
		'accent-15' => gstore_hex_to_rgba( $accent_color, 0.15 ),
		'accent-20' => gstore_hex_to_rgba( $accent_color, 0.2 ),
	);
}

/**
 * Atualiza o arquivo tokens.css com a nova cor de accent e tokens derivados.
 * Também atualiza valores de fallback em outros arquivos CSS.
 *
 * @param string $accent_color Cor de accent em hex.
 * @return bool|WP_Error True em sucesso, WP_Error em erro.
 */
function gstore_update_accent_tokens_in_file( $accent_color ) {
	$tokens = gstore_generate_accent_tokens( $accent_color );

	// Mapeia os nomes dos tokens para os padrões no arquivo
	$token_map = gstore_get_accent_token_css_var_map();

	// 1. Atualiza tokens.css
	$tokens_file = get_theme_file_path( 'assets/css/tokens.css' );
	if ( file_exists( $tokens_file ) && is_writable( $tokens_file ) ) {
		$content = file_get_contents( $tokens_file );

		foreach ( $token_map as $token_key => $token_var ) {
			$token_value = $tokens[ $token_key ];
			$pattern = '/([\t\s]*)(' . preg_quote( $token_var, '/' ) . '):\s*[^;]+;/';

			if ( preg_match( $pattern, $content ) ) {
				$content = preg_replace(
					$pattern,
					'$1$2: ' . $token_value . ';',
					$content
				);
			} else {
				$pattern_flex = '/' . preg_quote( $token_var, '/' ) . ':\s*[^;]+;/';
				if ( preg_match( $pattern_flex, $content ) ) {
					$content = preg_replace(
						$pattern_flex,
						$token_var . ': ' . $token_value . ';',
						$content
					);
				}
			}
		}

		file_put_contents( $tokens_file, $content );
	}

	// 2. Atualiza valores de fallback em checkout-steps.css
	$checkout_steps_file = get_theme_file_path( 'assets/css/checkout-steps.css' );
	if ( file_exists( $checkout_steps_file ) && is_writable( $checkout_steps_file ) ) {
		$content = file_get_contents( $checkout_steps_file );

		// Atualiza --gstore-brass com novo fallback
		$content = preg_replace(
			'/(--gstore-brass):\s*var\(--gstore-color-accent,\s*[^)]+\);/',
			'$1: var(--gstore-color-accent, ' . $tokens['accent'] . ');',
			$content
		);

		// Atualiza --gstore-brass-dark com novo fallback
		$content = preg_replace(
			'/(--gstore-brass-dark):\s*var\(--gstore-color-accent-dark,\s*[^)]+\);/',
			'$1: var(--gstore-color-accent-dark, ' . $tokens['accent-dark'] . ');',
			$content
		);

		file_put_contents( $checkout_steps_file, $content );
	}

	// 3. Atualiza valores hardcoded em style.css
	$style_file = get_theme_file_path( 'style.css' );
	if ( file_exists( $style_file ) && is_writable( $style_file ) ) {
		$content = file_get_contents( $style_file );

		// Atualiza valores hardcoded de accent (em qualquer lugar do arquivo)
		$content = preg_replace(
			'/(--gstore-color-accent):\s*#[0-9a-fA-F]{6};/',
			'$1: ' . $tokens['accent'] . ';',
			$content
		);

		$content = preg_replace(
			'/(--gstore-color-accent-hover):\s*#[0-9a-fA-F]{6};/',
			'$1: ' . $tokens['accent-hover'] . ';',
			$content
		);

		// Atualiza todos os fallbacks de accent no arquivo
		$content = preg_replace(
			'/var\(--gstore-color-accent,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent, ' . $tokens['accent'] . ')',
			$content
		);

		$content = preg_replace(
			'/var\(--gstore-color-accent-hover,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent-hover, ' . $tokens['accent-hover'] . ')',
			$content
		);

		$content = preg_replace(
			'/var\(--gstore-color-accent-light,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent-light, ' . $tokens['accent-light'] . ')',
			$content
		);

		file_put_contents( $style_file, $content );
	}

	// 4. Atualiza valores de fallback em my-account.css
	$my_account_file = get_theme_file_path( 'assets/css/my-account.css' );
	if ( file_exists( $my_account_file ) && is_writable( $my_account_file ) ) {
		$content = file_get_contents( $my_account_file );

		// Atualiza todos os fallbacks de accent
		$content = preg_replace(
			'/var\(--gstore-color-accent,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent, ' . $tokens['accent'] . ')',
			$content
		);

		$content = preg_replace(
			'/var\(--gstore-color-accent-hover,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent-hover, ' . $tokens['accent-hover'] . ')',
			$content
		);

		$content = preg_replace(
			'/var\(--gstore-color-accent-dark,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent-dark, ' . $tokens['accent-dark'] . ')',
			$content
		);

		file_put_contents( $my_account_file, $content );
	}

	// 5. Atualiza valores de fallback em header.css
	$header_file = get_theme_file_path( 'assets/css/layouts/header.css' );
	if ( file_exists( $header_file ) && is_writable( $header_file ) ) {
		$content = file_get_contents( $header_file );

		// Atualiza todos os fallbacks de accent
		$content = preg_replace(
			'/var\(--gstore-color-accent,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent, ' . $tokens['accent'] . ')',
			$content
		);

		file_put_contents( $header_file, $content );
	}

	return true;
}

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
			// Força o uso do template page-blog.html para a página de posts
			if ( ! empty( $page_config['template'] ) ) {
				update_post_meta( $page_id, '_wp_page_template', $page_config['template'] );
			}
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
 * Força o uso do template page-blog.html quando for a página de posts do blog.
 *
 * Quando uma página é definida como "posts_page", o WordPress usa templates de arquivo
 * (home.html, archive.html) ao invés de templates de página. Este filtro corrige isso.
 *
 * IMPORTANTE: Em Block Themes, não devemos retornar o caminho do arquivo HTML diretamente
 * via template_include, pois isso impede o processamento dos blocos. Em vez disso, usamos
 * o filtro block_template_loader para forçar o template correto.
 *
 * @param string $template Template atual.
 * @return string Template a ser usado.
 */
function gstore_force_blog_page_template( $template ) {
	// Verifica se é a página de posts ou se está acessando /blog
	if ( is_home() && ! is_front_page() ) {
		$blog_page_id = get_option( 'page_for_posts' );

		if ( $blog_page_id ) {
			$blog_page = get_post( $blog_page_id );

			// Verifica se a página do blog tem o template page-blog
			if ( $blog_page && 'blog' === $blog_page->post_name ) {
				$page_template = get_page_template_slug( $blog_page_id );

				// Se o template for page-blog, força o uso dele
				if ( 'page-blog' === $page_template || 'page-blog.html' === $page_template ) {
					// Em Block Themes, não retornamos o caminho do arquivo diretamente
					// porque isso impede o processamento dos blocos. Em vez disso, deixamos
					// o WordPress usar o sistema de Block Templates nativo.
					// O template será carregado automaticamente pelo WordPress se existir.
					// Não retornamos nada aqui - deixamos o WordPress usar o sistema nativo
				}
			}
		}
	}

	// Também verifica se está acessando diretamente a página /blog
	if ( is_page( 'blog' ) ) {
		$blog_page = get_page_by_path( 'blog' );

		if ( $blog_page ) {
			$page_template = get_page_template_slug( $blog_page->ID );

			// Se o template for page-blog, força o uso dele
			if ( 'page-blog' === $page_template || 'page-blog.html' === $page_template ) {
				// Em Block Themes, não retornamos o caminho do arquivo diretamente
				// porque isso impede o processamento dos blocos. Em vez disso, deixamos
				// o WordPress usar o sistema de Block Templates nativo.
				// Não retornamos nada aqui - deixamos o WordPress usar o sistema nativo
			}
		}
	}

	return $template;
}
add_filter( 'template_include', 'gstore_force_blog_page_template', 99 );

/**
 * Força o uso do template page-blog.html via Block Template API quando for a página de posts.
 *
 * Este filtro funciona em conjunto com gstore_force_blog_page_template para garantir
 * que o WordPress use o template correto e processe os blocos adequadamente.
 *
 * @param WP_Block_Template|null $template Template atual.
 * @return WP_Block_Template|null Template a ser usado.
 */
function gstore_force_blog_block_template( $template ) {
	// Verifica se é a página de posts
	if ( is_home() && ! is_front_page() ) {
		$blog_page_id = get_option( 'page_for_posts' );

		if ( $blog_page_id ) {
			$blog_page = get_post( $blog_page_id );

			if ( $blog_page && 'blog' === $blog_page->post_name ) {
				$page_template = get_page_template_slug( $blog_page_id );

				// Se o template for page-blog, força o uso dele via Block Template API
				if ( 'page-blog' === $page_template || 'page-blog.html' === $page_template ) {
					if ( function_exists( 'get_block_template' ) ) {
						$block_template = get_block_template( get_stylesheet() . '//page-blog', 'wp_template' );

						if ( $block_template ) {
							return $block_template;
						}
					}
				}
			}
		}
	}

	return $template;
}
add_filter( 'block_template_loader', 'gstore_force_blog_block_template', 10, 1 );

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

	var rules = {$rules_json};
	var results = { passed: [], failed: [], notFound: [] };
	var isMobile = window.innerWidth <= 900;
	var isDesktop = window.innerWidth > 900;

	Object.keys(rules).forEach(function(key) {
		var rule = rules[key];
		var shouldCheck = (rule.viewport === 'mobile' && isMobile) ||
		                  (rule.viewport === 'desktop' && isDesktop) ||
		                  !rule.viewport;

		if (!shouldCheck) {
			return;
		}

		var element = document.querySelector(rule.selector);

		if (!element) {
			results.notFound.push(rule);
			return;
		}

		var computedStyle = window.getComputedStyle(element);
		var actualValue = computedStyle.getPropertyValue(rule.property);

		if (actualValue.trim() === rule.expected) {
			results.passed.push(rule);
		} else {
			results.failed.push({ rule: rule, actual: actualValue });
		}
	});

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
				Gstore CSS Diagnostics
			</h3>
			<button onclick="document.getElementById('gstore-diagnostics-panel').remove();" style="
				background: none;
				border: none;
				color: #f0f0f1;
				cursor: pointer;
				padding: 4px;
				display: flex;
				align-items: center;
				justify-content: center;
			">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
			</button>
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

			</div>
		</div>
	</div>

	<script>
	var gstoreDiagRules = <?php echo wp_json_encode( $rules ); ?>;

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
				statusIcon = 'â“';
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
					statusIcon = 'âŒ';
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
 * Reseta o template customizado do blog para usar o arquivo do tema.
 */
function gstore_reset_blog_template() {
	global $wpdb;


	$deleted_count = 0;
	$errors = array();

	// Busca todos os templates relacionados ao blog no banco de dados
	// Usa query direta para garantir que encontre todos, independente de status ou meta
	$template_ids = array();

	// Busca por page-blog
	$found = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		WHERE post_type = 'wp_template'
		AND post_name = %s
		AND post_status != 'trash'",
		'page-blog'
	) );
	$template_ids = array_merge( $template_ids, $found );

	// Busca por archive (pode ser usado se a página Blog está configurada como página de posts)
	$found = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		WHERE post_type = 'wp_template'
		AND post_name = %s
		AND post_status != 'trash'",
		'archive'
	) );
	$template_ids = array_merge( $template_ids, $found );

	// Busca por qualquer template que contenha 'blog' no nome ou título
	$found = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		WHERE post_type = 'wp_template'
		AND (post_name LIKE %s OR post_title LIKE %s)
		AND post_status != 'trash'",
		'%blog%',
		'%Blog%'
	) );
	$template_ids = array_merge( $template_ids, $found );

	// Remove duplicatas
	$template_ids = array_unique( $template_ids );

	foreach ( $template_ids as $template_id ) {
		// Verifica se o post ainda existe
		$template_post = get_post( $template_id );

		if ( ! $template_post || $template_post->post_type !== 'wp_template' ) {
			continue;
		}

		// Deleta o template (qualquer wp_template salvo no banco é uma customização)
		$deleted = wp_delete_post( $template_id, true );

		if ( $deleted && ! is_wp_error( $deleted ) ) {
			$deleted_count++;
		} else {
			$error_msg = is_wp_error( $deleted ) ? $deleted->get_error_message() : __( 'Erro desconhecido', 'gstore' );
			$errors[] = sprintf( __( 'Template ID %d: %s', 'gstore' ), $template_id, $error_msg );
		}
	}

	// Limpa cache de templates e posts
	wp_cache_flush();
	clean_post_cache( 0 );

	// Força o WordPress a recarregar os templates
	if ( function_exists( 'wp_get_theme' ) ) {
		$theme = wp_get_theme();
		delete_transient( 'wp_get_theme' );
	}

	if ( $deleted_count > 0 ) {
		$message = sprintf(
			_n(
				'%d template customizado removido com sucesso! A página do blog agora usará o template do tema. Recarregue a página do blog para ver as mudanças.',
				'%d templates customizados removidos com sucesso! A página do blog agora usará o template do tema. Recarregue a página do blog para ver as mudanças.',
				$deleted_count,
				'gstore'
			),
			$deleted_count
		);

		if ( ! empty( $errors ) ) {
			$message .= ' ' . __( 'Avisos:', 'gstore' ) . ' ' . implode( ', ', $errors );
		}

		return array(
			'success' => true,
			'message' => $message,
		);
	} else {
		$message = __( 'Nenhum template customizado encontrado no banco de dados. A página do blog já está usando o template do tema. Se o problema persistir, pode ser cache do navegador.', 'gstore' );

		if ( ! empty( $errors ) ) {
			$message .= ' ' . __( 'Avisos:', 'gstore' ) . ' ' . implode( ', ', $errors );
		}

		return array(
			'success' => true,
			'message' => $message,
		);
	}
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
	} elseif ( 'reset_blog_template' === $action_type ) {
		$result = gstore_reset_blog_template();
		wp_send_json( $result );
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

				<div class="gstore-setup-card">
					<h4><?php _e( 'Resetar template do Blog', 'gstore' ); ?></h4>
					<p><?php _e( 'Remove customizações do template do blog salvas no banco de dados, fazendo a página usar o template do tema novamente. Útil quando header/footer não aparecem.', 'gstore' ); ?></p>
					<button type="button" class="button button-secondary gstore-utility-action" data-action="reset_blog_template" data-loading-text="<?php esc_attr_e( 'Resetando template...', 'gstore' ); ?>">
						<span class="dashicons dashicons-update-alt"></span>
						<?php _e( 'Resetar template do Blog', 'gstore' ); ?>
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

/**
 * ==========================================
 * GSTORE CART FIX - CENTRALIZAÇÃO FORÇADA
 * ==========================================
 *
 * Remove estilos conflitantes do WooCommerce e adiciona
 * CSS/JS inline de alta prioridade para garantir que o
 * carrinho fique centralizado corretamente.
 */

/**
 * Remove estilos padrão do WooCommerce na página do carrinho
 * que interferem na centralização.
 */
add_action( 'wp_enqueue_scripts', function() {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		// Remove estilos do WooCommerce que interferem
		wp_dequeue_style( 'woocommerce-layout' );
		wp_dequeue_style( 'woocommerce-smallscreen' );
		wp_dequeue_style( 'wc-blocks-style' );
		wp_dequeue_style( 'wc-blocks-vendors-style' );
	}
}, 100 );

/**
 * Adiciona CSS inline de alta prioridade para forçar centralização do carrinho.
 */
add_action( 'wp_head', function() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	?>
	<style id="gstore-cart-fix">
	/* ============================================
	   GSTORE CART FIX - CENTRALIZAÇÃO FORÇADA
	   ============================================ */

	/* Reset variáveis do WordPress */
	body.woocommerce-cart {
		--wp--style--root--padding-left: 0 !important;
		--wp--style--root--padding-right: 0 !important;
		--wp--style--global--content-size: 100% !important;
		--wp--style--global--wide-size: 100% !important;
	}

	/* Esconde título duplicado */
	body.woocommerce-cart .wp-block-post-title {
		display: none !important;
	}

	/* Reset do main e wrappers */
	body.woocommerce-cart main,
	body.woocommerce-cart .wp-site-blocks > main,
	body.woocommerce-cart .entry-content,
	body.woocommerce-cart .wp-block-post-content {
		width: 100% !important;
		padding: 0 !important;
		margin: 0 !important;
		background: #fff !important;
		display: flex !important;
		justify-content: center !important;
		max-width: none !important;
	}

	/* Espaçamento para entry-content quando carrinho está vazio */
	body.woocommerce-cart .entry-content:has(.cart-empty),
	body.woocommerce-cart .wp-block-post-content:has(.cart-empty),
	body.woocommerce-cart .entry-content:has(.return-to-shop),
	body.woocommerce-cart .wp-block-post-content:has(.return-to-shop) {
		padding: 48px 20px !important;
		margin: 0 auto !important;
		max-width: 1280px !important;
		box-sizing: border-box !important;
		display: flex !important;
		flex-direction: column !important;
		align-items: center !important;
		justify-content: center !important;
		min-height: 400px !important;
	}

	/* Reset is-layout-constrained - NÃO afeta o container */
	body.woocommerce-cart .is-layout-constrained > *:not(.Gstore-cart-container),
	body.woocommerce-cart .wp-block-group-is-layout-constrained > *:not(.Gstore-cart-container) {
		max-width: none !important;
		margin-left: 0 !important;
		margin-right: 0 !important;
	}

	/* Main da página do carrinho */
	body.woocommerce-cart main.Gstore-cart-page,
	body.woocommerce-cart main[data-page="cart"],
	body.woocommerce-cart .gstore-cart-page {
		display: block !important;
		width: 100% !important;
		max-width: none !important;
		margin: 0 !important;
		padding: 0 !important;
		background: #fff !important;
	}

	/* SHELL - ocupa 100% da largura */
	body.woocommerce-cart .Gstore-cart-shell,
	body.woocommerce-cart section.Gstore-cart-shell {
		display: block !important;
		width: 100% !important;
		max-width: none !important;
		margin: 0 !important;
		background: #fff !important;
		box-sizing: border-box !important;
	}

	/* CONTAINER - centralizado a 1280px */
	body.woocommerce-cart .Gstore-cart-container,
	body.woocommerce-cart div.Gstore-cart-container,
	body.woocommerce-cart .Gstore-cart-shell .Gstore-cart-container,
	body.woocommerce-cart section.Gstore-cart-shell div.Gstore-cart-container {
		display: flex !important;
		flex-direction: column !important;
		gap: 32px !important;
		width: 100% !important;
		max-width: 1280px !important;
		margin-left: auto !important;
		margin-right: auto !important;
		padding-left: 20px !important;
		padding-right: 20px !important;
		box-sizing: border-box !important;
	}

	body.woocommerce-cart main .woocommerce {
		max-width: 1280px !important;
	}
	</style>
	<?php
}, 9999 );

/**
 * JavaScript para remover classes problemáticas do WordPress no DOM.
 */
add_action( 'wp_footer', function() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	?>
	<script id="gstore-cart-fix-js">
	(function() {
		'use strict';

		// Classes problemáticas do WordPress que adicionam max-width
		const badClasses = [
			'is-layout-constrained',
			'wp-block-group-is-layout-constrained',
			'wp-block-post-content-is-layout-constrained'
		];

		function cleanCartClasses() {
			// Remove classes do main
			const main = document.querySelector('main.Gstore-cart-page, main[data-page="cart"], main.gstore-cart-page');
			if (main) {
				badClasses.forEach(cls => main.classList.remove(cls));
			}

			// Remove classes do entry-content
			const entryContent = document.querySelector('.entry-content');
			if (entryContent) {
				badClasses.forEach(cls => entryContent.classList.remove(cls));
			}

			// Remove classes do wp-block-post-content
			const postContent = document.querySelector('.wp-block-post-content');
			if (postContent) {
				badClasses.forEach(cls => postContent.classList.remove(cls));
			}

			// Log para debug
			console.log('[Gstore Cart Fix] Classes removidas com sucesso');
		}

		// Executa imediatamente
		cleanCartClasses();

		// Executa após DOM ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', cleanCartClasses);
		}

		// Executa após load completo (para scripts que adicionam classes depois)
		window.addEventListener('load', cleanCartClasses);
	})();
	</script>
	<?php
}, 9999 );

// ============================================
// FUNÇÕES HELPER PARA INFORMAÇÕES DA LOJA
// ============================================

/**
 * Retorna partes do nome atual do site para fallbacks white-label.
 *
 * @return array{name: string, display: string, highlight: string}
 */
function gstore_get_current_site_name_parts() {
	$site_name = trim( wp_strip_all_tags( (string) get_option( 'blogname', '' ) ) );
	if ( '' === $site_name ) {
		$site_name = 'Minha Loja';
	}

	$highlight = $site_name;
	$parts     = preg_split( '/\s+/', $site_name );
	if ( is_array( $parts ) ) {
		$parts = array_values( array_filter( $parts, 'strlen' ) );
		if ( ! empty( $parts ) ) {
			$highlight = (string) end( $parts );
		}
	}

	if ( function_exists( 'mb_strtoupper' ) ) {
		$highlight = mb_strtoupper( $highlight, 'UTF-8' );
	} else {
		$highlight = strtoupper( $highlight );
	}

	return array(
		'name'      => $site_name,
		'display'   => $site_name,
		'highlight' => $highlight,
	);
}

/**
 * Obtém o nome da loja.
 *
 * @param string $format 'name', 'display' ou 'highlight'.
 * @return string
 */
function gstore_get_store_name( $format = 'name' ) {
	$store_info      = gstore_store_info();
	$site_name_parts = gstore_get_current_site_name_parts();
	$store_info_key  = 'store.name';
	$fallback_key    = 'name';

	switch ( $format ) {
		case 'display':
			$store_info_key = 'store.display_name';
			$fallback_key   = 'display';
			break;
		case 'highlight':
			$store_info_key = 'store.name_highlight';
			$fallback_key   = 'highlight';
			break;
	}

	$value = trim( (string) $store_info->get_value( $store_info_key, '' ) );
	if ( '' === $value ) {
		return $site_name_parts[ $fallback_key ];
	}

	return $value;
}

/**
 * Obtém o CNPJ da loja.
 *
 * @return string
 */
function gstore_get_cnpj() {
	return trim( (string) gstore_store_info()->get_value( 'store.cnpj', '' ) );
}

/**
 * Obtém o número do WhatsApp.
 *
 * @param string $format 'raw' ou 'display'.
 * @return string
 */
function gstore_get_whatsapp( $format = 'raw' ) {
	$store_info = gstore_store_info();

	if ( 'display' === $format ) {
		return trim( (string) $store_info->get_value( 'contact.whatsapp_display', '' ) );
	}

	return trim( (string) $store_info->get_value( 'contact.whatsapp', '' ) );
}

/**
 * Gera link do WhatsApp com mensagem opcional.
 *
 * @param string $message Mensagem pré-preenchida (opcional).
 * @return string URL do WhatsApp.
 */
function gstore_get_whatsapp_link( $message = '' ) {
	$whatsapp = gstore_get_whatsapp( 'raw' );
	if ( empty( $whatsapp ) ) {
		return '';
	}

	$url = 'https://wa.me/' . $whatsapp;

	if ( ! empty( $message ) ) {
		$url .= '?text=' . rawurlencode( $message );
	}

	return $url;
}

/**
 * Obtem o e-mail de atendimento configurado nas informacoes da loja.
 *
 * @return string
 */
function gstore_get_store_email() {
	$email = trim( (string) gstore_store_info()->get_value( 'contact.email', '' ) );
	$email = sanitize_email( $email );

	if ( is_email( $email ) ) {
		return $email;
	}

	$admin_email = sanitize_email( (string) get_option( 'admin_email', '' ) );

	return is_email( $admin_email ) ? $admin_email : '';
}

/**
 * Gera o link mailto do e-mail de atendimento da loja.
 *
 * @return string
 */
function gstore_get_store_email_link() {
	$email = gstore_get_store_email();

	return '' !== $email ? 'mailto:' . $email : '';
}

/**
 * Obtém o telefone da loja.
 *
 * @param string $format 'raw' ou 'display'.
 * @return string
 */
function gstore_get_phone( $format = 'display' ) {
	$store_info = gstore_store_info();

	if ( 'raw' === $format ) {
		return trim( (string) $store_info->get_value( 'contact.phone_raw', '' ) );
	}

	return trim( (string) $store_info->get_value( 'contact.phone', '' ) );
}

/**
 * Obtém informações de uma rede social.
 *
 * @param string $network Nome da rede (instagram, facebook, youtube, telegram_group).
 * @return string Username/ID da rede social.
 */
function gstore_get_social( $network ) {
	return gstore_store_info()->get_value( 'social.' . $network, '' );
}

/**
 * Gera link para rede social.
 *
 * @param string $network Nome da rede (instagram, facebook, youtube, telegram).
 * @return string URL completa da rede social.
 */
function gstore_get_social_link( $network ) {
	$username = gstore_get_social( $network );

	if ( empty( $username ) ) {
		return '';
	}

	switch ( $network ) {
		case 'instagram':
		case 'instagram_alt':
			return 'https://www.instagram.com/' . $username . '/';
		case 'facebook':
			return 'https://www.facebook.com/profile.php?id=' . $username;
		case 'youtube':
			return 'https://www.youtube.com/' . $username;
		case 'telegram':
		case 'telegram_group':
			return 'https://t.me/' . $username;
		case 'twitter':
			return 'https://twitter.com/' . $username;
		case 'tiktok':
			return 'https://www.tiktok.com/@' . $username;
		default:
			return '';
	}
}

/**
 * Obtém o username do Telegram a partir do JSON.
 *
 * Prioriza o campo de contato e usa o grupo social como fallback.
 *
 * @return string
 */
function gstore_get_telegram_username() {
	$username = gstore_store_info()->get_value( 'contact.telegram', '' );

	if ( empty( $username ) ) {
		$username = gstore_get_social( 'telegram_group' );
	}

	$username = trim( (string) $username );
	$username = preg_replace( '#^(https?://)?(www\.)?(t\.me|telegram\.me)/#i', '', $username );

	return ltrim( $username, '@/ ' );
}

/**
 * Gera o link do Telegram a partir do username configurado.
 *
 * @return string
 */
function gstore_get_telegram_link() {
	$username = gstore_get_telegram_username();

	if ( empty( $username ) ) {
		return '';
	}

	return 'https://t.me/' . $username;
}

/**
 * Obtém o endereço formatado.
 *
 * @param string $format 'full', 'street', 'city_state', ou 'short'.
 * @return string
 */
function gstore_get_address( $format = 'full' ) {
	$store_info = gstore_store_info();
	$address = $store_info->get_value( 'address' );

	if ( ! is_array( $address ) ) {
		return '';
	}

	switch ( $format ) {
		case 'street':
			return trim( (string) ( $address['street'] ?? '' ) );
		case 'city_state':
			return implode( ' - ', array_filter( array(
				trim( (string) ( $address['city'] ?? '' ) ),
				trim( (string) ( $address['state'] ?? '' ) ),
			), 'strlen' ) );
		case 'short':
			$city_state = implode( ' - ', array_filter( array(
				trim( (string) ( $address['city'] ?? '' ) ),
				trim( (string) ( $address['state'] ?? '' ) ),
			), 'strlen' ) );

			return implode( ' - ', array_filter( array(
				trim( (string) ( $address['street'] ?? '' ) ),
				trim( (string) ( $address['neighborhood'] ?? '' ) ),
				! empty( $address['zipcode'] ) ? 'CEP: ' . trim( (string) $address['zipcode'] ) : '',
				$city_state,
			), 'strlen' ) );
		case 'full':
		default:
			$line_1 = implode( ' - ', array_filter( array(
				trim( (string) ( $address['street'] ?? '' ) ),
				trim( (string) ( $address['neighborhood'] ?? '' ) ),
			), 'strlen' ) );
			$line_2 = implode( ' - ', array_filter( array(
				! empty( $address['zipcode'] ) ? 'CEP: ' . trim( (string) $address['zipcode'] ) : '',
				trim( (string) ( $address['city'] ?? '' ) ),
				trim( (string) ( $address['state'] ?? '' ) ),
			), 'strlen' ) );

			return implode( "\n", array_filter( array( $line_1, $line_2 ), 'strlen' ) );
	}
}

/**
 * Obtém URL do Google Maps.
 *
 * @return string
 */
function gstore_get_maps_url() {
	return gstore_store_info()->get_value( 'address.maps_url', '' );
}

/**
 * Obtém horário de funcionamento.
 *
 * @param string $format 'full', 'weekdays', 'saturday', ou 'support'.
 * @return string
 */
function gstore_get_business_hours( $format = 'full' ) {
	$store_info = gstore_store_info();

	switch ( $format ) {
		case 'weekdays':
			return $store_info->get_value( 'business_hours.weekdays', '' );
		case 'saturday':
			return $store_info->get_value( 'business_hours.saturday', '' );
		case 'support':
			return $store_info->get_value( 'business_hours.support_hours', '' );
		case 'full':
		default:
			return $store_info->get_value( 'business_hours.full_text', '' );
	}
}

/**
 * Obtém informações do footer.
 *
 * @param string $key Chave específica (about_paragraphs, newsletter, etc).
 * @return mixed
 */
function gstore_get_footer_info( $key = '' ) {
	$store_info = gstore_store_info();

	if ( empty( $key ) ) {
		return $store_info->get_value( 'footer' );
	}

	return $store_info->get_value( 'footer.' . $key );
}

/**
 * Obtém a linha principal de contato do rodapé sem separadores vazios.
 *
 * @return string
 */
function gstore_get_footer_contact_line() {
	return implode( ' | ', array_filter( array(
		trim( (string) gstore_get_store_name() ),
		trim( (string) gstore_get_whatsapp( 'display' ) ),
	), 'strlen' ) );
}

/**
 * Obtém a linha de horário do rodapé somente quando houver horário configurado.
 *
 * @return string
 */
function gstore_get_footer_business_hours_line() {
	$business_hours = trim( (string) gstore_get_business_hours() );

	return '' !== $business_hours ? 'Horário de atendimento: ' . $business_hours : '';
}

/**
 * Obtém a linha legal do rodapé sem rótulos vazios.
 *
 * @return string
 */
function gstore_get_footer_legal_line() {
	$store_name = trim( (string) gstore_get_store_name() );
	$cnpj       = trim( (string) gstore_get_cnpj() );
	$address    = trim( (string) gstore_get_address( 'short' ) );

	return implode( ' | ', array_filter( array(
		$store_name,
		'' !== $cnpj ? 'CNPJ: ' . $cnpj : '',
		$address,
	), 'strlen' ) );
}

/**
 * Normaliza textos curtos usados no ranking de categorias e marcas do footer.
 *
 * @param string $value Texto a normalizar.
 * @return string
 */
function gstore_normalize_footer_discovery_text( $value ) {
	$value = wp_strip_all_tags( (string) $value );

	if ( function_exists( 'remove_accents' ) ) {
		$value = remove_accents( $value );
	}

	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $value, 'UTF-8' );
	}

	return strtolower( $value );
}

/**
 * Lista de palavras que indicam categorias comercialmente fortes no footer.
 *
 * @return array
 */
function gstore_get_footer_category_keyword_weights() {
	$weights = array(
		'fuzil'       => 62,
		'fuzis'       => 62,
		'pistola'     => 58,
		'pistolas'    => 58,
		'carabina'    => 54,
		'carabinas'   => 54,
		'pcp'         => 48,
		'airsoft'     => 46,
		'arma'        => 40,
		'armas'       => 40,
		'municao'     => 38,
		'municoes'    => 38,
		'luneta'      => 36,
		'lunetas'     => 36,
		'pressao'     => 34,
		'chumbinho'   => 32,
		'chumbinhos'  => 32,
		'chumbo'      => 30,
		'co2'         => 30,
		'coldre'      => 28,
		'coldres'     => 28,
		'carregador'  => 26,
		'carregadores' => 26,
		'tatico'      => 24,
		'tatica'      => 24,
		'militar'     => 24,
		'militares'   => 24,
		'acessorio'   => 22,
		'acessorios'  => 22,
		'capa'        => 18,
		'capas'       => 18,
		'case'        => 18,
		'cases'       => 18,
	);

	return apply_filters( 'gstore_footer_category_keyword_weights', $weights );
}

/**
 * Lista de marcas que recebem reforço quando existirem como termos no catálogo.
 *
 * @return array
 */
function gstore_get_footer_brand_keyword_weights() {
	$weights = array(
		'rossi'   => 34,
		'cbc'     => 34,
		'hatsan'  => 32,
		'artemis' => 30,
		'umarex'  => 30,
		'gamo'    => 30,
		'beeman'  => 28,
		'qgk'     => 28,
		'taurus'  => 26,
		'beretta' => 24,
		'glock'   => 24,
		'cz'      => 22,
		'ruger'   => 22,
		'walther' => 22,
	);

	return apply_filters( 'gstore_footer_brand_keyword_weights', $weights );
}

/**
 * Detecta taxonomias de marcas usadas pelo WooCommerce ou plugins de marca.
 *
 * @return array
 */
function gstore_get_footer_brand_taxonomies() {
	$candidates = array(
		'product_brand',
		'product_brands',
		'pwb-brand',
		'yith_product_brand',
		'berocket_brand',
		'pa_marca',
		'pa_marcas',
		'pa_brand',
		'pa_fabricante',
		'pa_manufacturer',
	);

	if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
		$attributes = wc_get_attribute_taxonomies();

		if ( is_array( $attributes ) ) {
			foreach ( $attributes as $attribute ) {
				$name  = isset( $attribute->attribute_name ) ? (string) $attribute->attribute_name : '';
				$label = isset( $attribute->attribute_label ) ? (string) $attribute->attribute_label : '';

				if ( '' === $name ) {
					continue;
				}

				$haystack = gstore_normalize_footer_discovery_text( $name . ' ' . $label );
				if ( false === strpos( $haystack, 'marca' ) && false === strpos( $haystack, 'brand' ) && false === strpos( $haystack, 'fabricante' ) && false === strpos( $haystack, 'manufacturer' ) ) {
					continue;
				}

				$candidates[] = function_exists( 'wc_attribute_taxonomy_name' ) ? wc_attribute_taxonomy_name( $name ) : 'pa_' . $name;
			}
		}
	}

	$candidates = apply_filters( 'gstore_footer_brand_taxonomies', $candidates );
	$taxonomies = array();

	foreach ( array_unique( array_filter( array_map( 'trim', (array) $candidates ) ) ) as $taxonomy ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			$taxonomies[] = $taxonomy;
		}
	}

	return $taxonomies;
}

/**
 * Pontua um termo por volume de produtos e força comercial do nome.
 *
 * @param WP_Term $term            Termo de categoria ou marca.
 * @param array   $keyword_weights Palavras fortes e pesos.
 * @return float
 */
function gstore_get_footer_discovery_term_score( $term, $keyword_weights ) {
	$count = isset( $term->count ) ? max( 0, (int) $term->count ) : 0;
	$score = min( 70, log( $count + 1 ) * 14 );

	$haystack = gstore_normalize_footer_discovery_text( ( $term->name ?? '' ) . ' ' . ( $term->slug ?? '' ) );
	foreach ( (array) $keyword_weights as $keyword => $weight ) {
		$keyword = gstore_normalize_footer_discovery_text( (string) $keyword );

		if ( '' !== $keyword && false !== strpos( $haystack, $keyword ) ) {
			$score += (float) $weight;
		}
	}

	if ( isset( $term->taxonomy ) && 'product_cat' === $term->taxonomy ) {
		$score += empty( $term->parent ) ? 6 : 2;
	}

	return $score;
}

/**
 * Resolve a URL mais adequada para um termo exibido no texto do footer.
 *
 * @param WP_Term $term Termo de categoria ou marca.
 * @return string
 */
function gstore_get_footer_discovery_term_link( $term ) {
	$link = get_term_link( $term );
	if ( is_wp_error( $link ) ) {
		return '';
	}

	return (string) $link;
}

/**
 * Busca termos ranqueados para a seção de categorias e marcas do footer.
 *
 * @param array|string $taxonomies      Taxonomias a pesquisar.
 * @param int          $limit           Limite de termos.
 * @param array        $keyword_weights Palavras fortes e pesos.
 * @param array        $excluded_slugs  Slugs que não devem aparecer.
 * @return array
 */
function gstore_get_footer_discovery_ranked_terms( $taxonomies, $limit, $keyword_weights, $excluded_slugs = array() ) {
	$limit = max( 1, absint( $limit ) );
	$items = array();

	foreach ( array_unique( array_filter( (array) $taxonomies ) ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			if ( empty( $term->name ) || empty( $term->slug ) || ( isset( $term->count ) && (int) $term->count < 1 ) ) {
				continue;
			}

			$slug = sanitize_title( (string) $term->slug );
			if ( in_array( $slug, $excluded_slugs, true ) ) {
				continue;
			}

			$link = gstore_get_footer_discovery_term_link( $term );
			if ( '' === $link ) {
				continue;
			}

			$label = trim( wp_strip_all_tags( (string) $term->name ) );
			if ( '' === $label ) {
				continue;
			}

			$key = sanitize_title( gstore_normalize_footer_discovery_text( $label ) );
			$item = array(
				'label' => $label,
				'html'  => sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $label ) ),
				'score' => gstore_get_footer_discovery_term_score( $term, $keyword_weights ),
				'count' => isset( $term->count ) ? (int) $term->count : 0,
			);

			if ( ! isset( $items[ $key ] ) || $item['score'] > $items[ $key ]['score'] ) {
				$items[ $key ] = $item;
			}
		}
	}

	uasort(
		$items,
		function ( $a, $b ) {
			if ( $a['score'] === $b['score'] ) {
				if ( $a['count'] === $b['count'] ) {
					return strcasecmp( $a['label'], $b['label'] );
				}

				return ( $a['count'] < $b['count'] ) ? 1 : -1;
			}

			return ( $a['score'] < $b['score'] ) ? 1 : -1;
		}
	);

	return array_slice( array_values( $items ), 0, $limit );
}

/**
 * Junta links em português, usando vírgulas e "e" antes do último item.
 *
 * @param array $items Itens com chave html.
 * @return string
 */
function gstore_join_footer_discovery_links( $items ) {
	$links = array();

	foreach ( (array) $items as $item ) {
		if ( ! empty( $item['html'] ) ) {
			$links[] = $item['html'];
		}
	}

	$count = count( $links );
	if ( 0 === $count ) {
		return '';
	}
	if ( 1 === $count ) {
		return $links[0];
	}
	if ( 2 === $count ) {
		return $links[0] . ' e ' . $links[1];
	}

	$last = array_pop( $links );
	return implode( ', ', $links ) . ' e ' . $last;
}

/**
 * Monta o texto dinâmico de categorias e marcas para o footer.
 *
 * @return string HTML seguro.
 */
function gstore_get_footer_category_brand_summary() {
	if ( ! apply_filters( 'gstore_footer_discovery_enabled', true ) ) {
		return '';
	}

	$store_name = trim( (string) gstore_get_store_name( 'display' ) );
	if ( '' === $store_name ) {
		$store_name = get_bloginfo( 'name' );
	}

	$category_limit = max( 1, absint( apply_filters( 'gstore_footer_discovery_category_limit', 15 ) ) );
	$brand_limit    = max( 1, absint( apply_filters( 'gstore_footer_discovery_brand_limit', 10 ) ) );

	$categories = taxonomy_exists( 'product_cat' )
		? gstore_get_footer_discovery_ranked_terms(
			'product_cat',
			$category_limit,
			gstore_get_footer_category_keyword_weights(),
			array( 'sem-categoria', 'uncategorized' )
		)
		: array();

	$brands = gstore_get_footer_discovery_ranked_terms(
		gstore_get_footer_brand_taxonomies(),
		$brand_limit,
		gstore_get_footer_brand_keyword_weights()
	);

	$category_links = gstore_join_footer_discovery_links( $categories );
	$brand_links    = gstore_join_footer_discovery_links( $brands );
	$sentences      = array(
		sprintf(
			'Na %s, você encontra produtos selecionados para tiro esportivo, airsoft, caça, defesa e lazer, com atendimento especializado e foco em compra segura.',
			esc_html( $store_name )
		),
	);

	if ( '' !== $category_links && '' !== $brand_links ) {
		$sentences[] = 'Trabalhamos com ' . $category_links . ', reunindo marcas reconhecidas como ' . $brand_links . ' para atender diferentes perfis de uso.';
	} elseif ( '' !== $category_links ) {
		$sentences[] = 'Trabalhamos com ' . $category_links . ' para atender diferentes perfis de uso.';
	} elseif ( '' !== $brand_links ) {
		$sentences[] = 'Trabalhamos com marcas reconhecidas como ' . $brand_links . ' para atender diferentes perfis de uso.';
	}

	return implode( ' ', $sentences );
}

/**
 * Obtém o texto de copyright com ano atual.
 *
 * @return string
 */
function gstore_get_copyright() {
	$template = trim( (string) gstore_store_info()->get_value( 'footer.copyright_text', '' ) );
	if ( '' === $template ) {
		$template = 'Copyright © {year} {store_name}. Todos os direitos reservados.';
	}

	if ( false === strpos( $template, '{year}' ) ) {
		$template = preg_replace( '/20\d{2}/', '{year}', $template, 1 );
	}
	if ( false === strpos( $template, '{store_name}' ) ) {
		$template = 'Copyright © {year} {store_name}. Todos os direitos reservados.';
	}
	if ( false === strpos( $template, '{year}' ) || false === strpos( $template, '{store_name}' ) ) {
		$template = 'Copyright © {year} {store_name}. Todos os direitos reservados.';
	}

	$replacements = array(
		'{year}'       => date( 'Y' ),
		'{store_name}' => gstore_get_store_name(),
	);

	return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
}

/**
 * Obtém meta informações do site.
 *
 * @param string $key 'description', 'keywords', ou 'og_image'.
 * @return string
 */
function gstore_get_meta( $key ) {
	return gstore_store_info()->get_value( 'meta.' . $key, '' );
}

/**
 * Obtém cor de branding.
 *
 * @param string $key 'accent_color', 'primary_color'.
 * @return string
 */
function gstore_get_brand_color( $key ) {
	return gstore_store_info()->get_value( 'branding.' . $key, '' );
}

/**
 * Obtém o ano de fundação da loja.
 *
 * @return string
 */
function gstore_get_founded_year() {
	return gstore_store_info()->get_value( 'store.founded_year', '' );
}

// ============================================
// HANDLERS DE EXPORTAÇÃO/IMPORTAÇÃO
// ============================================

/**
 * Handler para exportar informações da loja como JSON.
 */
function gstore_handle_export_store_info() {
	// Verifica permissões
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( __( 'Você não tem permissão para realizar esta ação.', 'gstore' ) );
	}

	// Verifica nonce
	if ( ! isset( $_POST['gstore_export_nonce'] ) || ! wp_verify_nonce( $_POST['gstore_export_nonce'], 'gstore_export_store_info' ) ) {
		wp_die( __( 'Verificação de segurança falhou.', 'gstore' ) );
	}

	$store_info = gstore_store_info();
	$json_content = $store_info->export_json();

	// Define headers para download
	$filename = 'store-info-' . sanitize_file_name( gstore_get_store_name() ) . '-' . date( 'Y-m-d' ) . '.json';

	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . strlen( $json_content ) );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	echo $json_content;
	exit;
}
add_action( 'admin_post_gstore_export_store_info', 'gstore_handle_export_store_info' );

/**
 * Handler para importar informações da loja de um arquivo JSON.
 */
function gstore_handle_import_store_info() {
	// Verifica permissões
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( __( 'Você não tem permissão para realizar esta ação.', 'gstore' ) );
	}

	// Verifica nonce
	if ( ! isset( $_POST['gstore_import_nonce'] ) || ! wp_verify_nonce( $_POST['gstore_import_nonce'], 'gstore_import_store_info' ) ) {
		wp_die( __( 'Verificação de segurança falhou.', 'gstore' ) );
	}

	// Verifica se arquivo foi enviado
	if ( ! isset( $_FILES['store_info_file'] ) || $_FILES['store_info_file']['error'] !== UPLOAD_ERR_OK ) {
		$error_message = __( 'Erro ao enviar o arquivo.', 'gstore' );

		if ( isset( $_FILES['store_info_file']['error'] ) ) {
			switch ( $_FILES['store_info_file']['error'] ) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$error_message = __( 'O arquivo é muito grande.', 'gstore' );
					break;
				case UPLOAD_ERR_NO_FILE:
					$error_message = __( 'Nenhum arquivo foi selecionado.', 'gstore' );
					break;
			}
		}

		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( $error_message ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}

	// Lê o conteúdo do arquivo primeiro (validação por conteúdo, não apenas extensão)
	$json_content = file_get_contents( $_FILES['store_info_file']['tmp_name'] );

	if ( empty( $json_content ) ) {
		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( __( 'O arquivo está vazio.', 'gstore' ) ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}

	// Remove BOM (Byte Order Mark) se presente
	$json_content = preg_replace( '/^\xEF\xBB\xBF/', '', $json_content );

	// Remove espaços em branco no início e fim
	$json_content = trim( $json_content );

	// Valida o JSON antes de importar
	$json_data = json_decode( $json_content, true );

	if ( json_last_error() !== JSON_ERROR_NONE ) {
		// Mensagem de erro específica do JSON
		$json_error_messages = array(
			JSON_ERROR_DEPTH          => __( 'Profundidade máxima da pilha excedida.', 'gstore' ),
			JSON_ERROR_STATE_MISMATCH => __( 'JSON inválido ou malformado.', 'gstore' ),
			JSON_ERROR_CTRL_CHAR      => __( 'Caractere de controle encontrado, possivelmente codificado incorretamente.', 'gstore' ),
			JSON_ERROR_SYNTAX         => __( 'Erro de sintaxe JSON. Verifique vírgulas, chaves e aspas.', 'gstore' ),
			JSON_ERROR_UTF8           => __( 'Caracteres UTF-8 malformados, possivelmente codificação incorreta.', 'gstore' ),
		);

		$error_message = isset( $json_error_messages[ json_last_error() ] )
			? $json_error_messages[ json_last_error() ]
			: __( 'Erro desconhecido ao processar JSON.', 'gstore' );

		$error_message .= ' ' . sprintf( __( 'Detalhes: %s', 'gstore' ), json_last_error_msg() );

		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( $error_message ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}

	// Tenta importar (passa o array já decodificado para evitar decodificar duas vezes)
	$store_info = gstore_store_info();

	if ( ! is_array( $json_data ) ) {
		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( sprintf(
				__( 'Erro: JSON decodificado não é um array. Tipo: %s', 'gstore' ),
				gettype( $json_data )
			) ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}

	$result = $store_info->import_json( $json_data );

	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();

		// Adiciona informações de debug se for erro de estrutura
		if ( $result->get_error_code() === 'invalid_structure' ) {
			$secoes_encontradas = array_keys( $json_data );
			$error_message .= ' | Seções no JSON: ' . implode( ', ', $secoes_encontradas );
		}

		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( $error_message ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}

	// Sucesso
	wp_redirect( add_query_arg( array(
		'page'    => 'gstore-settings',
		'message' => 'import_success',
	), admin_url( 'themes.php' ) ) );
	exit;
}
add_action( 'admin_post_gstore_import_store_info', 'gstore_handle_import_store_info' );

/**
 * Exibe mensagens de sucesso/erro para importação.
 */
function gstore_store_info_admin_notices() {
	$screen = get_current_screen();

	if ( ! $screen || $screen->id !== 'appearance_page_gstore-settings' ) {
		return;
	}

	if ( isset( $_GET['message'] ) ) {
		if ( $_GET['message'] === 'import_success' ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Informações da loja importadas com sucesso!', 'gstore' ); ?></p>
			</div>
			<?php
		} elseif ( $_GET['message'] === 'import_error' && isset( $_GET['error'] ) ) {
			$error_message = urldecode( $_GET['error'] );
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?php _e( 'Erro ao importar JSON:', 'gstore' ); ?></strong></p>
				<p><?php echo esc_html( $error_message ); ?></p>
				<p style="margin-top: 10px;">
					<strong><?php _e( 'Dicas:', 'gstore' ); ?></strong>
					<ul style="margin-left: 20px; margin-top: 5px;">
						<li><?php _e( 'Verifique se o arquivo é um JSON válido (use um validador online se necessário)', 'gstore' ); ?></li>
						<li><?php _e( 'Certifique-se de que todas as seções obrigatórias estão presentes', 'gstore' ); ?></li>
						<li><?php _e( 'Verifique vírgulas, chaves { } e colchetes [ ]', 'gstore' ); ?></li>
						<li><?php _e( 'Certifique-se de que todas as strings estão entre aspas duplas', 'gstore' ); ?></li>
					</ul>
				</p>
			</div>
			<?php
		}
	}
}
add_action( 'admin_notices', 'gstore_store_info_admin_notices' );

// ============================================
// PROCESSAMENTO DE PLACEHOLDERS NOS TEMPLATES
// ============================================

/**
 * Processa placeholders de informações da loja no conteúdo.
 *
 * Substitui placeholders como {{store_name}}, {{whatsapp_link}}, etc.
 *
 * @param string $content Conteúdo a processar.
 * @return string Conteúdo processado.
 */
function gstore_process_store_info_placeholders( $content ) {
	if ( empty( $content ) || strpos( $content, '{{' ) === false ) {
		return $content;
	}

	// Resolve o link principal de contato (header/footer): se configurado usa o do JSON, senão usa o WhatsApp.
	$contact_primary_link = gstore_store_info()->get_value( 'contact.contact_primary_link', '' );
	if ( empty( $contact_primary_link ) ) {
		$contact_primary_link = gstore_get_whatsapp_link();
	}
	$email      = gstore_get_store_email();
	$phone_raw  = trim( (string) gstore_get_phone( 'raw' ) );
	$email_link = gstore_get_store_email_link();
	$phone_link = '' !== $phone_raw ? 'tel:+' . preg_replace( '/\D/', '', $phone_raw ) : '';
	$footer_category_brand_summary = false !== strpos( $content, '{{footer_category_brand_summary}}' ) ? gstore_get_footer_category_brand_summary() : '';

	// Lista de placeholders e seus valores
	$placeholders = array(
		// Store
		'{{store_name}}'          => gstore_get_store_name(),
		'{{store_display_name}}'  => gstore_get_store_name( 'display' ),
		'{{store_name_highlight}}' => gstore_get_store_name( 'highlight' ),
		'{{store_slogan}}'        => gstore_store_info()->get_value( 'store.slogan', '' ),
		'{{cnpj}}'                => gstore_get_cnpj(),
		'{{founded_year}}'        => gstore_get_founded_year(),

		// Contact
		'{{email}}'               => $email,
		'{{email_link}}'          => $email_link,
		'{{phone}}'               => gstore_get_phone(),
		'{{phone_raw}}'           => gstore_get_phone( 'raw' ),
		'{{whatsapp}}'            => gstore_get_whatsapp(),
		'{{whatsapp_display}}'    => gstore_get_whatsapp( 'display' ),
		'{{whatsapp_link}}'       => gstore_get_whatsapp_link(),
		'{{whatsapp_link_hello}}' => gstore_get_whatsapp_link( 'Olá ' . gstore_get_store_name( 'display' ) . '!' ),
		'{{whatsapp_link_rastreio}}' => gstore_get_whatsapp_link( 'Olá ' . gstore_get_store_name( 'display' ) . '! Gostaria de rastrear meu pedido.' ),
		'{{whatsapp_link_troca}}' => gstore_get_whatsapp_link( 'Olá ' . gstore_get_store_name( 'display' ) . '! Gostaria de solicitar uma troca ou devolução.' ),

		// Contact Labels
		'{{whatsapp_label}}'  => gstore_store_info()->get_value( 'contact.whatsapp_label', 'WhatsApp' ),
		'{{telegram_label}}'  => gstore_store_info()->get_value( 'contact.telegram_label', 'Telegram' ),
		'{{instagram_label}}' => gstore_store_info()->get_value( 'contact.instagram_label', 'Instagram' ),

		// Phone link (tel:)
		'{{phone_link}}'      => $phone_link,

		// Contact primary link (header/footer): usa o valor do JSON ou fallback para whatsapp_link
		'{{contact_primary_link}}' => $contact_primary_link,
		'{{privacy_policy_url}}'   => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : home_url( '/politica-de-privacidade/' ),
		'{{terms_of_use_url}}'     => ( (int) get_option( 'woocommerce_terms_page_id', 0 ) > 0 ) ? get_permalink( (int) get_option( 'woocommerce_terms_page_id', 0 ) ) : home_url( '/termos-de-uso/' ),

		// Social
		'{{instagram}}'           => gstore_get_social( 'instagram' ),
		'{{instagram_link}}'      => gstore_get_social_link( 'instagram' ),
		'{{facebook_link}}'       => gstore_get_social_link( 'facebook' ),
		'{{youtube_link}}'        => gstore_get_social_link( 'youtube' ),
		'{{telegram}}'            => gstore_get_telegram_username(),
		'{{telegram_link}}'       => gstore_get_telegram_link(),

		// Address
		'{{address_street}}'      => gstore_get_address( 'street' ),
		'{{address_full}}'        => gstore_get_address( 'full' ),
		'{{address_short}}'       => gstore_get_address( 'short' ),
		'{{address_city_state}}'  => gstore_get_address( 'city_state' ),
		'{{maps_url}}'            => gstore_get_maps_url(),

		// Business hours
		'{{business_hours}}'      => gstore_get_business_hours(),
		'{{support_hours}}'       => gstore_get_business_hours( 'support' ),

		// Footer
		'{{copyright}}'           => gstore_get_copyright(),
		'{{footer_contact_line}}' => gstore_get_footer_contact_line(),
		'{{footer_business_hours_line}}' => gstore_get_footer_business_hours_line(),
		'{{footer_legal_line}}'   => gstore_get_footer_legal_line(),
		'{{footer_category_brand_summary}}' => $footer_category_brand_summary,

		// Meta
		'{{meta_description}}'    => gstore_get_meta( 'description' ),

		// Branding
		'{{accent_color}}'        => gstore_get_brand_color( 'accent_color' ),

		// Dynamic
		'{{year}}'                => date( 'Y' ),
	);

	// Processa parágrafos do footer (array)
	$footer_paragraphs = gstore_get_footer_info( 'about_paragraphs' );
	if ( is_array( $footer_paragraphs ) ) {
		foreach ( $footer_paragraphs as $index => $paragraph ) {
			$placeholders['{{footer_paragraph_' . ( $index + 1 ) . '}}'] = $paragraph;
		}
	}

	// Substitui os placeholders
	$content = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
	$content = preg_replace( '/<a\b(?=[^>]*class="[^"]*\bGstore-top-bar__link\b[^"]*")[^>]*href=""[^>]*>.*?<\/a>\s*/is', '', $content );
	$content = preg_replace( '/<div\s+class="contact-item">\s*<i\b[^>]*><\/i>\s*<a\b[^>]*href=""[^>]*>.*?<\/a>\s*<\/div>\s*/is', '', $content );
	$content = preg_replace( '/<div\s+class="contact-item">\s*<i\b[^>]*><\/i>\s*<\/div>\s*/is', '', $content );
	$content = preg_replace( '/<a\b[^>]*href=""[^>]*>\s*<i\b[^>]*fa-brands[^>]*><\/i>\s*<\/a>\s*/is', '', $content );

	return $content;
}

/**
 * Adiciona o processamento de placeholders ao output buffer existente.
 *
 * Este filtro é aplicado ao output final da página.
 *
 * @param string $content Conteúdo HTML.
 * @return string Conteúdo processado.
 */
function gstore_add_store_info_to_output_processing( $content ) {
	return gstore_process_store_info_placeholders( $content );
}
add_filter( 'gstore_process_final_output', 'gstore_add_store_info_to_output_processing', 5 );

/**
 * Processa placeholders em blocos HTML.
 */
add_filter( 'render_block_core/html', function( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_store_info_placeholders( $block_content );
	}
	return $block_content;
}, 15, 2 );

/**
 * Processa placeholders em template parts.
 */
add_filter( 'render_block_core/template-part', function( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_store_info_placeholders( $block_content );
	}
	return $block_content;
}, 15, 2 );

/**
 * Resolve links relativos à raiz (href="/path") para a URL completa do site.
 * Necessário quando o WordPress está instalado em um subdiretório (ex.: dominio.com/subdir/).
 *
 * @param string $content Conteúdo HTML.
 * @return string Conteúdo com href="/..." substituídos por href="{home_url}/...".
 */
function gstore_resolve_home_relative_urls( $content ) {
	if ( empty( $content ) || strpos( $content, 'href="/' ) === false ) {
		return $content;
	}
	$content = preg_replace_callback(
		'/href="\/([^"]*)"/',
		function ( $m ) {
			return 'href="' . esc_url( home_url( '/' . $m[1] ) ) . '"';
		},
		$content
	);
	return $content;
}

add_filter( 'render_block_core/template-part', 'gstore_resolve_home_relative_urls', 20, 1 );
add_filter( 'render_block_core/html', 'gstore_resolve_home_relative_urls', 20, 1 );
add_filter( 'the_content', 'gstore_resolve_home_relative_urls', 25 );

// ============================================
// MIGRAÇÃO E INICIALIZAÇÃO AUTOMÁTICA
// ============================================

/**
 * Retorna uma pontuação de indício de mojibake para um texto.
 *
 * @param string $value Texto para validação.
 * @return int
 */
function gstore_get_mojibake_score( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return 0;
	}

	$patterns = array(
		'/\x{00C3}[\x{0080}-\x{00BF}]/u',
		'/\x{00C2}[\x{0080}-\x{00BF}]/u',
		'/\x{00E2}\x{20AC}/u',
		'/\x{00F0}\x{0178}/u',
		'/\x{00EF}\x{00BF}/u',
	);

	$score = 0;
	foreach ( $patterns as $pattern ) {
		if ( preg_match_all( $pattern, $value, $matches ) ) {
			$score += count( $matches[0] );
		}
	}

	return $score;
}

/**
 * Tenta converter texto corrompido por encoding para UTF-8 correto.
 *
 * @param string $value Texto original.
 * @return string
 */
function gstore_fix_mojibake_text( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return $value;
	}

	$current = $value;
	$score   = gstore_get_mojibake_score( $current );

	if ( 0 === $score ) {
		return $current;
	}

	for ( $i = 0; $i < 4; $i++ ) {
		$candidates = array();

		if ( function_exists( 'mb_convert_encoding' ) ) {
			$candidate = @mb_convert_encoding( $current, 'UTF-8', 'ISO-8859-1' );
			if ( is_string( $candidate ) && '' !== $candidate ) {
				$candidates[] = $candidate;
			}
		}

		if ( function_exists( 'iconv' ) ) {
			$latin1_candidate = @iconv( 'ISO-8859-1', 'UTF-8//IGNORE', $current );
			if ( is_string( $latin1_candidate ) && '' !== $latin1_candidate ) {
				$candidates[] = $latin1_candidate;
			}

			$cp1252_candidate = @iconv( 'Windows-1252', 'UTF-8//IGNORE', $current );
			if ( is_string( $cp1252_candidate ) && '' !== $cp1252_candidate ) {
				$candidates[] = $cp1252_candidate;
			}
		}

		if ( empty( $candidates ) ) {
			break;
		}

		$best       = $current;
		$best_score = $score;

		foreach ( array_unique( $candidates ) as $candidate ) {
			$candidate_score = gstore_get_mojibake_score( $candidate );
			if ( $candidate_score < $best_score ) {
				$best       = $candidate;
				$best_score = $candidate_score;
			}
		}

		if ( $best === $current ) {
			break;
		}

		$current = $best;
		$score   = $best_score;

		if ( 0 === $score ) {
			break;
		}
	}

	return $current;
}

/**
 * Corrige recursivamente valores string de arrays/opções.
 *
 * @param mixed $value Valor atual.
 * @param bool  $changed Referência para sinalizar alteração.
 * @return mixed
 */
function gstore_fix_mojibake_recursive( $value, &$changed = false ) {
	if ( is_string( $value ) ) {
		$fixed = gstore_fix_mojibake_text( $value );
		if ( $fixed !== $value ) {
			$changed = true;
		}
		return $fixed;
	}

	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = gstore_fix_mojibake_recursive( $item, $changed );
		}
	}

	return $value;
}

/**
 * Migra opções conhecidas de texto para corrigir encoding legado.
 *
 * @return int Quantidade de opções atualizadas.
 */
function gstore_migrate_mojibake_options() {
	$options = array(
		'gstore_logo_alt',
		'gstore_banner_youtube_alt',
	);

	for ( $i = 1; $i <= 10; $i++ ) {
		$options[] = "gstore_hero_desktop_slide_{$i}_alt";
		$options[] = "gstore_hero_mobile_slide_{$i}_alt";
	}

	$updated = 0;

	foreach ( $options as $option_name ) {
		$current = get_option( $option_name, null );
		if ( null === $current ) {
			continue;
		}

		$changed = false;
		$fixed   = gstore_fix_mojibake_recursive( $current, $changed );

		if ( $changed ) {
			update_option( $option_name, $fixed );
			$updated++;
		}
	}

	return $updated;
}

/**
 * Migra theme mods textuais conhecidos para corrigir encoding legado.
 *
 * @return int Quantidade de theme mods atualizados.
 */
function gstore_migrate_mojibake_theme_mods() {
	$mods = array(
		'gstore_contract_terms_content',
	);

	$updated = 0;

	foreach ( $mods as $mod_name ) {
		$current = get_theme_mod( $mod_name, null );
		if ( null === $current ) {
			continue;
		}

		$changed = false;
		$fixed   = gstore_fix_mojibake_recursive( $current, $changed );

		if ( $changed ) {
			set_theme_mod( $mod_name, $fixed );
			$updated++;
		}
	}

	return $updated;
}

/**
 * Migra conteúdos/títulos de páginas padrão criadas pelo Setup.
 *
 * @return int Quantidade de páginas atualizadas.
 */
function gstore_migrate_mojibake_required_pages() {
	$pages = gstore_get_required_pages();
	$seen  = array();
	$count = 0;

	foreach ( $pages as $page_config ) {
		if ( empty( $page_config['slug'] ) ) {
			continue;
		}

		$page = gstore_get_page_by_slug( $page_config['slug'] );
		if ( ! $page || in_array( (int) $page->ID, $seen, true ) ) {
			continue;
		}

		$seen[] = (int) $page->ID;

		$current_title   = (string) get_post_field( 'post_title', $page->ID, 'raw' );
		$current_content = (string) get_post_field( 'post_content', $page->ID, 'raw' );
		$current_excerpt = (string) get_post_field( 'post_excerpt', $page->ID, 'raw' );

		$new_title   = gstore_fix_mojibake_text( $current_title );
		$new_content = gstore_fix_mojibake_text( $current_content );
		$new_excerpt = gstore_fix_mojibake_text( $current_excerpt );

		if ( $new_title === $current_title && $new_content === $current_content && $new_excerpt === $current_excerpt ) {
			continue;
		}

		$result = wp_update_post(
			array(
				'ID'           => (int) $page->ID,
				'post_title'   => $new_title,
				'post_content' => $new_content,
				'post_excerpt' => $new_excerpt,
			),
			true
		);

		if ( ! is_wp_error( $result ) ) {
			$count++;
		}
	}

	return $count;
}

/**
 * Executa migração idempotente para corrigir textos com encoding legado.
 */
function gstore_run_encoding_migration() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$version         = '2026-03-08-encoding-admin-loja-v1';
	$stored_version  = get_option( 'gstore_encoding_migration_version', '' );
	if ( $stored_version === $version ) {
		return;
	}

	$report = array(
		'version'           => $version,
		'ran_at'            => current_time( 'mysql' ),
		'options_updated'   => gstore_migrate_mojibake_options(),
		'theme_mods_updated' => gstore_migrate_mojibake_theme_mods(),
		'pages_updated'     => gstore_migrate_mojibake_required_pages(),
	);

	update_option( 'gstore_encoding_migration_report', $report, false );
	update_option( 'gstore_encoding_migration_version', $version, false );
}
add_action( 'admin_init', 'gstore_run_encoding_migration', 12 );

/**
 * Inicializa o arquivo JSON com dados padrão se não existir.
 *
 * Executado automaticamente na primeira visita ao admin.
 */
function gstore_maybe_init_store_info_json() {
	// Só executa no admin e se o usuário tiver permissões
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Verifica se já foi inicializado
	$initialized = get_option( 'gstore_store_info_initialized', false );
	if ( $initialized ) {
		return;
	}

	$store_info = gstore_store_info();

	// Se o arquivo JSON não existe, cria com valores padrão
	if ( ! $store_info->json_exists() ) {
		$store_info->create_default_json();
	}

	// Marca como inicializado
	update_option( 'gstore_store_info_initialized', true );
}
add_action( 'admin_init', 'gstore_maybe_init_store_info_json' );

// ============================================
// MODAL DE VERIFICAÇÃO DE IDADE (+18)
// ============================================

/**
 * Exibe o modal de verificação de idade no frontend.
 *
 * O modal aparece na primeira visita do usuário e guarda
 * a confirmação no localStorage por 30 dias.
 */
function gstore_age_verification_modal() {
	// Não exibe no admin
	if ( is_admin() ) {
		return;
	}
	?>
	<!-- Modal de Verificacao de Idade -->
	<div id="gstore-age-modal" class="gstore-age-modal" aria-hidden="true" role="dialog" aria-labelledby="gstore-age-title" aria-describedby="gstore-age-desc">
		<div class="gstore-age-modal__overlay"></div>
		<div class="gstore-age-modal__content">
			<button class="gstore-age-modal__close" aria-label="<?php esc_attr_e( 'Fechar modal', 'gstore' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M18 6L6 18M6 6l12 12"/>
				</svg>
			</button>
			<div class="gstore-age-modal__icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
					<path d="M12 8v4"/>
					<path d="M12 16h.01"/>
				</svg>
			</div>
			<h2 id="gstore-age-title" class="gstore-age-modal__title">Verifica&#231;&#227;o de Idade</h2>
			<p id="gstore-age-desc" class="gstore-age-modal__text">
				Este site cont&eacute;m produtos destinados exclusivamente para maiores de 18 anos.
			</p>
			<p class="gstore-age-modal__question">Voc&#234; tem 18 anos ou mais?</p>
			<div class="gstore-age-modal__actions">
				<button type="button" id="gstore-age-confirm" class="gstore-age-modal__btn gstore-age-modal__btn--confirm">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="20 6 9 17 4 12"/>
					</svg>
					Sim, tenho 18+
				</button>
				<button type="button" id="gstore-age-deny" class="gstore-age-modal__btn gstore-age-modal__btn--deny">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"/>
						<line x1="6" y1="6" x2="18" y2="18"/>
					</svg>
					N&#227;o, sou menor
				</button>
			</div>
			<p class="gstore-age-modal__disclaimer">
				Ao confirmar, voc&#234; declara estar ciente de que &#233; proibida a venda de produtos para menores de 18 anos.
			</p>
		</div>
	</div>

	<style id="gstore-age-modal-styles">
		/* Modal de Verificacao de Idade */
		.gstore-age-modal {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			z-index: 999999;
			display: flex;
			align-items: center;
			justify-content: center;
			opacity: 0;
			visibility: hidden;
			pointer-events: none;
			transition: opacity 0.4s ease, visibility 0.4s ease;
		}

		.gstore-age-modal[aria-hidden="false"] {
			opacity: 1;
			visibility: visible;
			pointer-events: auto;
		}

		/* Defensivo mobile: evita filhos invisíveis capturarem toque quando fechado */
		.gstore-age-modal[aria-hidden="true"] * {
			pointer-events: none !important;
		}

		.gstore-age-modal__overlay {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: linear-gradient(135deg, rgba(0, 0, 0, 0.92) 0%, rgba(20, 20, 30, 0.95) 100%);
			backdrop-filter: blur(8px);
			-webkit-backdrop-filter: blur(8px);
		}

		.gstore-age-modal__content {
			position: relative;
			background: linear-gradient(180deg, #1a1a1a 0%, #0d0d0d 100%);
			border: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 20px;
			box-shadow:
				0 25px 50px -12px rgba(0, 0, 0, 0.8),
				0 0 0 1px rgba(255, 255, 255, 0.05),
				inset 0 1px 0 0 rgba(255, 255, 255, 0.1);
			max-width: 440px;
			width: 90%;
			padding: 48px 40px;
			text-align: center;
			transform: scale(0.9) translateY(20px);
			opacity: 0;
			transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.4s ease;
			z-index: 1000000;
		}

		.gstore-age-modal[aria-hidden="false"] .gstore-age-modal__content {
			transform: scale(1) translateY(0);
			opacity: 1;
		}

		.gstore-age-modal__close {
			position: absolute;
			top: 16px;
			right: 16px;
			background: transparent;
			border: none;
			color: rgba(255, 255, 255, 0.6);
			cursor: pointer;
			padding: 8px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			transition: color 0.2s ease, background-color 0.2s ease;
			border-radius: 4px;
			z-index: 1;
		}

		.gstore-age-modal__close:hover,
		.gstore-age-modal__close:focus {
			color: rgba(255, 255, 255, 1);
			background-color: rgba(255, 255, 255, 0.1);
			outline: none;
		}

		.gstore-age-modal__close svg {
			width: 20px;
			height: 20px;
		}

		.gstore-age-modal__icon {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 88px;
			height: 88px;
			margin: 0 auto 24px;
			background: linear-gradient(135deg, rgba(220, 38, 38, 0.2) 0%, rgba(239, 68, 68, 0.1) 100%);
			border-radius: 50%;
			color: #ef4444;
			animation: gstore-age-pulse 2s ease-in-out infinite;
		}

		@keyframes gstore-age-pulse {
			0%, 100% {
				box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.3);
			}
			50% {
				box-shadow: 0 0 0 15px rgba(239, 68, 68, 0);
			}
		}

		.gstore-age-modal__title {
			margin: 0 0 12px;
			font-size: 28px;
			font-weight: 700;
			color: #ffffff;
			letter-spacing: -0.02em;
			line-height: 1.2;
		}

		.gstore-age-modal__text {
			margin: 0 0 8px;
			font-size: 15px;
			line-height: 1.6;
			color: rgba(255, 255, 255, 0.7);
		}

		.gstore-age-modal__question {
			margin: 24px 0;
			font-size: 18px;
			font-weight: 600;
			color: #ffffff;
		}

		.gstore-age-modal__actions {
			display: flex;
			flex-direction: column;
			gap: 12px;
			margin-bottom: 24px;
		}

		.gstore-age-modal__btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 10px;
			padding: 16px 32px;
			font-size: 16px;
			font-weight: 600;
			border: none;
			border-radius: 12px;
			cursor: pointer;
			transition: all 0.2s ease;
			text-transform: none;
			letter-spacing: 0;
		}

		.gstore-age-modal__btn--confirm {
			background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
			color: #ffffff;
			box-shadow: 0 4px 14px 0 rgba(34, 197, 94, 0.4);
		}

		.gstore-age-modal__btn--confirm:hover {
			background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
			transform: translateY(-2px);
			box-shadow: 0 6px 20px 0 rgba(34, 197, 94, 0.5);
		}

		.gstore-age-modal__btn--confirm:active {
			transform: translateY(0);
		}

		.gstore-age-modal__btn--deny {
			background: rgba(255, 255, 255, 0.05);
			color: rgba(255, 255, 255, 0.7);
			border: 1px solid rgba(255, 255, 255, 0.1);
		}

		.gstore-age-modal__btn--deny:hover {
			background: rgba(239, 68, 68, 0.1);
			color: #ef4444;
			border-color: rgba(239, 68, 68, 0.3);
		}

		.gstore-age-modal__disclaimer {
			margin: 0;
			font-size: 12px;
			line-height: 1.5;
			color: rgba(255, 255, 255, 0.4);
		}

		/* Tela de bloqueio para menores */
		.gstore-age-modal--blocked .gstore-age-modal__content {
			padding: 60px 40px;
		}

		.gstore-age-modal__blocked-icon {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 100px;
			height: 100px;
			margin: 0 auto 28px;
			background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.1) 100%);
			border-radius: 50%;
			color: #ef4444;
		}

		.gstore-age-modal__blocked-title {
			margin: 0 0 16px;
			font-size: 24px;
			font-weight: 700;
			color: #ffffff;
		}

		.gstore-age-modal__blocked-text {
			margin: 0;
			font-size: 15px;
			line-height: 1.7;
			color: rgba(255, 255, 255, 0.6);
		}

		/* Responsividade */
		@media (max-width: 480px) {
			.gstore-age-modal__content {
				width: 94%;
				padding: 36px 24px;
				border-radius: 16px;
			}

			.gstore-age-modal__icon {
				width: 72px;
				height: 72px;
				margin-bottom: 20px;
			}

			.gstore-age-modal__icon svg {
				width: 48px;
				height: 48px;
			}

			.gstore-age-modal__title {
				font-size: 22px;
			}

			.gstore-age-modal__text {
				font-size: 14px;
			}

			.gstore-age-modal__question {
				font-size: 16px;
				margin: 20px 0;
			}

			.gstore-age-modal__btn {
				padding: 14px 24px;
				font-size: 15px;
			}

			.gstore-age-modal--blocked .gstore-age-modal__content {
				padding: 40px 24px;
			}

			.gstore-age-modal__close {
				top: 12px;
				right: 12px;
			}
		}

		/* Animação de entrada */
		@keyframes gstore-age-fadeIn {
			from {
				opacity: 0;
			}
			to {
				opacity: 1;
			}
		}

		/* Previne scroll do body quando modal está aberto */
		body.gstore-age-modal-open {
			overflow: hidden;
		}
	</style>

	<script id="gstore-age-modal-script">
	(function() {
		'use strict';

		var STORAGE_KEY = 'gstore_age_verified';
		var STORAGE_DURATION = 30 * 24 * 60 * 60 * 1000; // 30 dias em ms

		function isVerified() {
			try {
				var stored = localStorage.getItem(STORAGE_KEY);
				if (!stored) return false;

				var data = JSON.parse(stored);
				var now = new Date().getTime();

				// Verifica se ainda esta valido
				if (data.verified && data.expires > now) {
					return true;
				}

				// Expirou, remove
				localStorage.removeItem(STORAGE_KEY);
				return false;
			} catch (e) {
				return false;
			}
		}

		function setVerified() {
			try {
				var data = {
					verified: true,
					expires: new Date().getTime() + STORAGE_DURATION
				};
				localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
			} catch (e) {
				// localStorage nao disponivel
			}
		}

		function showModal() {
			var modal = document.getElementById('gstore-age-modal');
			if (modal) {
				document.body.classList.add('gstore-age-modal-open');
				modal.setAttribute('aria-hidden', 'false');
			}
		}

		function hideModal() {
			var modal = document.getElementById('gstore-age-modal');
			if (modal) {
				document.body.classList.remove('gstore-age-modal-open');
				modal.setAttribute('aria-hidden', 'true');
			}
		}

		function showBlockedScreen() {
			var modal = document.getElementById('gstore-age-modal');
			if (!modal) return;

			modal.classList.add('gstore-age-modal--blocked');
			var content = modal.querySelector('.gstore-age-modal__content');

			if (content) {
				content.innerHTML =
					'<div class="gstore-age-modal__blocked-icon">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' +
							'<circle cx="12" cy="12" r="10"/>' +
							'<line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>' +
						'</svg>' +
					'</div>' +
					'<h2 class="gstore-age-modal__blocked-title">Acesso Restrito</h2>' +
					'<p class="gstore-age-modal__blocked-text">' +
						'Desculpe, este site &eacute; destinado apenas para maiores de 18 anos.<br><br>' +
						'Voc&ecirc; ser&aacute; redirecionado para o Google em alguns segundos...' +
					'</p>';
			}

			// Redireciona apos 5 segundos
			setTimeout(function() {
				window.location.href = 'https://www.google.com';
			}, 5000);
		}

		function init() {
			// Se já verificado, não mostra o modal
			if (isVerified()) {
				var modal = document.getElementById('gstore-age-modal');
				if (modal) {
					modal.remove();
				}
				return;
			}

			// Mostra o modal
			showModal();

			// Event listeners
			var modal = document.getElementById('gstore-age-modal');
			var confirmBtn = document.getElementById('gstore-age-confirm');
			var denyBtn = document.getElementById('gstore-age-deny');
			var closeBtn = modal ? modal.querySelector('.gstore-age-modal__close') : null;

			if (closeBtn) {
				closeBtn.addEventListener('click', function() {
					// Ao fechar sem responder, redireciona para fora do site
					window.location.href = 'https://www.google.com';
				});
			}

			if (confirmBtn) {
				confirmBtn.addEventListener('click', function() {
					setVerified();
					hideModal();

					// Remove o modal apos a animacao
					setTimeout(function() {
						var modal = document.getElementById('gstore-age-modal');
						if (modal) modal.remove();
					}, 500);
				});
			}

			if (denyBtn) {
				denyBtn.addEventListener('click', function() {
					showBlockedScreen();
				});
			}
		}

		// Inicializa quando o DOM estiver pronto
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'gstore_age_verification_modal', 999 );

/**
 * Fallback do menu mobile no footer para uso pelo JS quando o drawer não existir no template.
 */
function gstore_render_mobile_menu_fallback() {
	if ( is_admin() ) {
		return;
	}

	$theme_location = '';
	if ( has_nav_menu( 'gstore_mobile' ) ) {
		$theme_location = 'gstore_mobile';
	} elseif ( has_nav_menu( 'gstore_desktop' ) ) {
		$theme_location = 'gstore_desktop';
	}

	if ( ! $theme_location ) {
		return;
	}

	$walker = class_exists( 'Gstore_Nav_Menu_Walker' ) ? new Gstore_Nav_Menu_Walker() : '';
	$menu_html = wp_nav_menu(
		array(
			'theme_location' => $theme_location,
			'menu_class'     => 'wp-block-navigation__container',
			'container'      => false,
			'echo'           => false,
			'fallback_cb'    => false,
			'walker'         => $walker,
		)
	);

	if ( empty( $menu_html ) ) {
		return;
	}

	echo '<div id="gstore-mobile-menu-fallback" style="display:none;">';
	echo '<nav class="wp-block-navigation Gstore-nav Gstore-nav--mobile" aria-label="' . esc_attr__( 'Menu principal', 'gstore' ) . '">';
	echo $menu_html;
	echo '</nav></div>';
}
add_action( 'wp_footer', 'gstore_render_mobile_menu_fallback', 5 );

/**
 * Customiza a exibição do endereço na página Minha Conta para incluir rótulos.
 */
add_filter( 'woocommerce_my_account_my_address_formatted_address', 'gstore_custom_my_account_address_labels', 10, 3 );
function gstore_custom_my_account_address_labels( $address, $customer_id, $name_type ) {
    $first_name = $address['first_name'] ?? '';
    $last_name  = $address['last_name'] ?? '';
    $address_1  = $address['address_1'] ?? '';
    $address_2  = $address['address_2'] ?? '';
    $city       = $address['city'] ?? '';
    $state      = $address['state'] ?? '';
    $postcode   = $address['postcode'] ?? '';

    // Campos extras do tema
    $number       = get_user_meta( $customer_id, $name_type . '_number', true );
    $neighborhood = get_user_meta( $customer_id, $name_type . '_neighborhood', true );

    $new_address = array();

    // Nome: Nome Completo
    if ( $first_name || $last_name ) {
        $new_address['first_name'] = 'Nome: ' . trim( $first_name . ' ' . $last_name );
        $new_address['last_name']  = '';
    }

    // Endereço: Rua, Numero - Complemento
    if ( $address_1 ) {
        $addr = 'Endereço: ' . $address_1;
        if ( $number ) {
            $addr .= ', ' . $number;
        }
        if ( $address_2 ) {
            $addr .= ' - ' . $address_2;
        }
        $new_address['address_1'] = $addr;
        $new_address['address_2'] = '';
    }

    // Bairro (apenas se preenchido)
    if ( $neighborhood ) {
        $new_address['neighborhood'] = 'Bairro: ' . $neighborhood;
    } else {
        $new_address['neighborhood'] = '';
    }

    if ( $city ) {
        $new_address['city'] = 'Cidade: ' . $city;
    }

    if ( $state ) {
        $new_address['state'] = 'Estado: ' . $state;
    }

    if ( $postcode ) {
        $new_address['postcode'] = 'CEP: ' . $postcode;
    }

    // Não exibe o país na página de conta se for BR
    $new_address['country'] = (isset($address['country']) && $address['country'] !== 'BR') ? $address['country'] : '';

    return $new_address;
}

/**
 * Define o formato de endereço para o Brasil para incluir o bairro e garantir a ordem.
 * Nota: Isso afeta apenas quando wc_get_formatted_address é chamado,
 * mas como alteramos os valores apenas na Minha Conta, o impacto visual é controlado.
 */
add_filter( 'woocommerce_localisation_address_formats', 'gstore_br_address_format_with_neighborhood' );
function gstore_br_address_format_with_neighborhood( $formats ) {
    $formats['BR'] = "{first_name}\n{address_1}\n{neighborhood}\n{city}\n{state}\n{postcode}";
    return $formats;
}

/**
 * Adiciona suporte ao placeholder {neighborhood} no formato de endereço.
 */
add_filter( 'woocommerce_formatted_address_replacements', 'gstore_add_neighborhood_replacement', 10, 2 );
function gstore_add_neighborhood_replacement( $replacements, $args ) {
    if ( ! isset( $replacements['{neighborhood}'] ) ) {
        $replacements['{neighborhood}'] = $args['neighborhood'] ?? '';
    }
    return $replacements;
}

// Ferramentas administrativas (thumbnails + updater via git) movidas para o plugin gstore-core.

// Endpoint de debug log movido para inc/class-gstore-debug-logger.php
// Os logs são salvos em: wp-content/uploads/gstore-debug-logs/debug.log

/**
 * Garante que o botão .Gstore-header__menu-toggle sempre tenha a estrutura interna
 * com o ícone hamburger (3 linhas) + texto MENU.
 *
 * Necessário porque o Editor de Site do WordPress pode limpar o HTML interno do
 * <button> ao salvar customizações, transformando-o em <button>MENU</button>.
 */
add_filter( 'render_block_core/template-part', 'gstore_fix_menu_toggle_structure', 25, 1 );
function gstore_fix_menu_toggle_structure( $content ) {
	if ( empty( $content ) || strpos( $content, 'Gstore-header__menu-toggle' ) === false ) {
		return $content;
	}

	$correct_inner =
		'<span class="Gstore-header__menu-icon" aria-hidden="true">' .
			'<span class="Gstore-header__menu-line"></span>' .
			'<span class="Gstore-header__menu-line"></span>' .
			'<span class="Gstore-header__menu-line"></span>' .
		'</span>' .
		'<span class="Gstore-header__menu-text">MENU</span>';

	$pattern = '/<button([^>]*class="[^"]*Gstore-header__menu-toggle[^"]*"[^>]*)>(.*?)<\/button>/is';

	if ( ! preg_match( $pattern, $content, $match ) ) {
		return $content;
	}

	if ( strpos( $match[2], 'Gstore-header__menu-icon' ) !== false &&
	     substr_count( $match[2], 'Gstore-header__menu-line' ) >= 3 ) {
		return $content;
	}

	$fixed_button = '<button' . $match[1] . '>' . $correct_inner . '</button>';
	$content = str_replace( $match[0], $fixed_button, $content );

	return $content;
}

/**
 * Retorna os IDs de termos de um produto, sempre normalizados.
 */
function gstore_related_products_get_term_ids( $product_id, $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	static $term_ids_cache = array();

	$product_id = absint( $product_id );
	$cache_key  = $product_id . '|' . $taxonomy;

	if ( isset( $term_ids_cache[ $cache_key ] ) ) {
		return $term_ids_cache[ $cache_key ];
	}

	$term_ids = wp_get_post_terms( absint( $product_id ), $taxonomy, array( 'fields' => 'ids' ) );
	if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
		$term_ids_cache[ $cache_key ] = array();
		return array();
	}

	$term_ids_cache[ $cache_key ] = array_values( array_unique( array_map( 'absint', $term_ids ) ) );

	return $term_ids_cache[ $cache_key ];
}

/**
 * Considera como categorias especificas as folhas entre as categorias do produto.
 */
function gstore_related_products_get_specific_category_ids( $category_ids ) {
	$category_ids = array_values( array_unique( array_map( 'absint', (array) $category_ids ) ) );
	if ( empty( $category_ids ) ) {
		return array();
	}

	$specific_ids = array();

	foreach ( $category_ids as $category_id ) {
		$is_parent_of_selected_category = false;

		foreach ( $category_ids as $possible_child_id ) {
			if ( $category_id === $possible_child_id ) {
				continue;
			}

			$ancestor_ids = array_map( 'absint', get_ancestors( $possible_child_id, 'product_cat', 'taxonomy' ) );
			if ( in_array( $category_id, $ancestor_ids, true ) ) {
				$is_parent_of_selected_category = true;
				break;
			}
		}

		if ( ! $is_parent_of_selected_category ) {
			$specific_ids[] = $category_id;
		}
	}

	return ! empty( $specific_ids ) ? $specific_ids : $category_ids;
}

/**
 * Retorna categorias mais amplas: pais/ancestrais das categorias especificas.
 */
function gstore_related_products_get_broader_category_ids( $category_ids, $specific_category_ids ) {
	$broader_ids           = array_diff( array_map( 'absint', (array) $category_ids ), array_map( 'absint', (array) $specific_category_ids ) );
	$specific_category_ids = array_values( array_unique( array_map( 'absint', (array) $specific_category_ids ) ) );

	foreach ( $specific_category_ids as $category_id ) {
		$broader_ids = array_merge(
			$broader_ids,
			array_map( 'absint', get_ancestors( $category_id, 'product_cat', 'taxonomy' ) )
		);
	}

	$broader_ids = array_diff( array_unique( array_map( 'absint', $broader_ids ) ), $specific_category_ids );

	return array_values( $broader_ids );
}

/**
 * Expande categorias diretas com seus ancestrais para pontuar produtos em categorias irmas.
 */
function gstore_related_products_get_category_lineage_ids( $category_ids ) {
	$lineage_ids = array_values( array_unique( array_map( 'absint', (array) $category_ids ) ) );

	foreach ( $category_ids as $category_id ) {
		$lineage_ids = array_merge(
			$lineage_ids,
			array_map( 'absint', get_ancestors( absint( $category_id ), 'product_cat', 'taxonomy' ) )
		);
	}

	return array_values( array_unique( array_map( 'absint', $lineage_ids ) ) );
}

/**
 * Busca candidatos extras para permitir fallback real: subcategoria > categoria > tags.
 */
function gstore_related_products_query_candidate_ids( $taxonomy, $term_ids, $exclude_ids, $limit, $include_children = false ) {
	$term_ids = array_values( array_filter( array_unique( array_map( 'absint', (array) $term_ids ) ) ) );
	if ( empty( $term_ids ) || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => max( 1, absint( $limit ) ),
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post__not_in'           => array_values( array_unique( array_map( 'absint', (array) $exclude_ids ) ) ),
			'orderby'                => 'rand',
			'tax_query'              => array(
				array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => $term_ids,
					'include_children' => (bool) $include_children,
				),
			),
		)
	);

	$ids = array_map( 'absint', $query->posts );
	wp_reset_postdata();

	return $ids;
}

/**
 * Popularidade geral do produto, baseada no total de vendas do WooCommerce.
 */
function gstore_related_products_get_popularity_score( $product_id ) {
	$total_sales = max( 0, (int) get_post_meta( absint( $product_id ), 'total_sales', true ) );
	if ( $total_sales <= 0 ) {
		return 0.0;
	}

	return min( 12.0, log( $total_sales + 1 ) / log( 2 ) );
}

/**
 * Ordena por popularidade com variacao: populares aparecem mais, mas a vitrine renova.
 */
function gstore_related_products_order_by_popularity( $product_ids ) {
	$product_ids = array_values( array_unique( array_map( 'absint', (array) $product_ids ) ) );
	if ( empty( $product_ids ) ) {
		return array();
	}

	$sort_keys = array();
	foreach ( $product_ids as $product_id ) {
		$weight = 1 + gstore_related_products_get_popularity_score( $product_id );
		$random = max( 0.000001, wp_rand( 1, 1000000 ) / 1000000 );

		$sort_keys[ $product_id ] = pow( $random, 1 / $weight );
	}

	arsort( $sort_keys, SORT_NUMERIC );

	return array_map( 'absint', array_keys( $sort_keys ) );
}

/**
 * Adiciona IDs unicos ao resultado ate preencher a quantidade desejada.
 */
function gstore_related_products_append_unique_ids( $result, $product_ids, $limit ) {
	foreach ( (array) $product_ids as $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || in_array( $product_id, $result, true ) ) {
			continue;
		}

		$result[] = $product_id;

		if ( count( $result ) >= $limit ) {
			break;
		}
	}

	return $result;
}

/**
 * Monta os primeiros slots sem deixar a subcategoria dominar 100% quando ha opcoes.
 */
function gstore_related_products_build_priority_mix( $specific_ids, $category_ids, $tag_ids, $fallback_ids, $limit ) {
	$limit = max( 1, absint( $limit ) );

	$specific_ids = gstore_related_products_order_by_popularity( $specific_ids );
	$category_ids = gstore_related_products_order_by_popularity( $category_ids );
	$tag_ids      = gstore_related_products_order_by_popularity( $tag_ids );
	$fallback_ids = gstore_related_products_order_by_popularity( $fallback_ids );

	$specific_target = max( 1, (int) ceil( $limit * 0.5 ) );
	$category_target = max( 1, (int) floor( $limit * 0.25 ) );
	$tag_target      = max( 1, $limit - $specific_target - $category_target );

	$result = array();
	$result = gstore_related_products_append_unique_ids( $result, $specific_ids, min( $limit, $specific_target ) );
	$result = gstore_related_products_append_unique_ids( $result, $category_ids, min( $limit, count( $result ) + $category_target ) );
	$result = gstore_related_products_append_unique_ids( $result, $tag_ids, min( $limit, count( $result ) + $tag_target ) );

	if ( count( $result ) < $limit ) {
		$result = gstore_related_products_append_unique_ids( $result, $specific_ids, $limit );
	}

	if ( count( $result ) < $limit ) {
		$result = gstore_related_products_append_unique_ids( $result, $category_ids, $limit );
	}

	if ( count( $result ) < $limit ) {
		$result = gstore_related_products_append_unique_ids( $result, $tag_ids, $limit );
	}

	if ( count( $result ) < $limit ) {
		$result = gstore_related_products_append_unique_ids( $result, $fallback_ids, $limit );
	}

	$remaining = array_merge( $specific_ids, $category_ids, $tag_ids, $fallback_ids );

	return array_merge(
		$result,
		array_values( array_diff( array_unique( array_map( 'absint', $remaining ) ), $result ) )
	);
}

/**
 * Rank dos relacionados: subcategoria > categoria > tags; popularidade ordena cada grupo.
 * Produtos sem estoque seguem como fallback no final.
 */
function gstore_related_products_rank_by_relevance( $related_posts, $product_id, $args ) {
	$product_id    = absint( $product_id );
	$related_posts = array_map( 'absint', (array) $related_posts );
	$limit         = isset( $args['limit'] ) ? max( 1, absint( $args['limit'] ) ) : 4;
	$exclude_ids   = isset( $args['excluded_ids'] ) ? array_map( 'absint', (array) $args['excluded_ids'] ) : array();
	$exclude_ids[] = $product_id;
	$exclude_ids   = array_values( array_unique( array_filter( $exclude_ids ) ) );

	$category_ids          = gstore_related_products_get_term_ids( $product_id, 'product_cat' );
	$specific_category_ids = gstore_related_products_get_specific_category_ids( $category_ids );
	$broader_category_ids  = gstore_related_products_get_broader_category_ids( $category_ids, $specific_category_ids );
	$tag_ids               = gstore_related_products_get_term_ids( $product_id, 'product_tag' );

	if ( empty( $related_posts ) && empty( $specific_category_ids ) && empty( $broader_category_ids ) && empty( $tag_ids ) ) {
		return $related_posts;
	}

	$query_limit   = max( 48, $limit * 16 );
	$candidate_ids = array_merge(
		$related_posts,
		gstore_related_products_query_candidate_ids( 'product_cat', $specific_category_ids, $exclude_ids, $query_limit, true ),
		gstore_related_products_query_candidate_ids( 'product_cat', $broader_category_ids, $exclude_ids, $query_limit, true ),
		gstore_related_products_query_candidate_ids( 'product_tag', $tag_ids, $exclude_ids, $query_limit, false )
	);
	$candidate_ids = array_values( array_diff( array_unique( array_map( 'absint', $candidate_ids ) ), $exclude_ids ) );

	$in_stock_specific = array();
	$in_stock_category = array();
	$in_stock_tags     = array();
	$in_stock_fallback = array();
	$out_of_stock      = array();

	foreach ( $candidate_ids as $candidate_id ) {
		$candidate = wc_get_product( $candidate_id );
		if ( ! $candidate || ! wc_products_array_filter_visible( $candidate ) ) {
			continue;
		}

		$candidate_category_ids = gstore_related_products_get_term_ids( $candidate_id, 'product_cat' );
		$candidate_lineage_ids  = gstore_related_products_get_category_lineage_ids( $candidate_category_ids );
		$candidate_tag_ids      = gstore_related_products_get_term_ids( $candidate_id, 'product_tag' );

		if ( ! $candidate->is_in_stock() ) {
			$out_of_stock[] = $candidate_id;
			continue;
		}

		if ( array_intersect( $candidate_lineage_ids, $specific_category_ids ) ) {
			$in_stock_specific[] = $candidate_id;
		} elseif ( array_intersect( $candidate_lineage_ids, $broader_category_ids ) ) {
			$in_stock_category[] = $candidate_id;
		} elseif ( array_intersect( $candidate_tag_ids, $tag_ids ) ) {
			$in_stock_tags[] = $candidate_id;
		} else {
			$in_stock_fallback[] = $candidate_id;
		}
	}

	return array_merge(
		gstore_related_products_build_priority_mix( $in_stock_specific, $in_stock_category, $in_stock_tags, $in_stock_fallback, $limit ),
		gstore_related_products_order_by_popularity( $out_of_stock )
	);
}
add_filter( 'woocommerce_related_products', 'gstore_related_products_rank_by_relevance', 10, 3 );

// Impede o WooCommerce de embaralhar os IDs apos nosso ranking ponderado.
add_filter( 'woocommerce_product_related_posts_shuffle', '__return_false' );

// Impede o wc_products_array_orderby de re-embaralhar na renderizacao.
// 'none' e um case explicito no WooCommerce que faz break sem sort; 'asc' evita array_reverse.
add_filter( 'woocommerce_output_related_products_args', function( $args ) {
	$args['orderby'] = 'none';
	$args['order']   = 'asc';
	return $args;
} );

/* ═══════════════════════════════════════════════
 * FULFILLMENT — Helpers para o template view-order.php
 * ═══════════════════════════════════════════════ */

/**
 * Retorna a etapa de fulfillment do pedido.
 *
 * @param WC_Order $order
 * @return string Slug da etapa ou string vazia.
 */
function gstore_get_order_fulfillment_stage( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}
	$stage = $order->get_meta( '_gstore_fulfillment_stage' );
	if ( is_string( $stage ) && '' !== $stage ) {
		return $stage;
	}

	// Auto-inicializa pedidos existentes via Service (plugin) se disponível.
	if ( class_exists( '\GStore\Services\Fulfillment_Service' ) ) {
		return \GStore\Services\Fulfillment_Service::initialize_existing_order( $order );
	}

	// Fallback: computa etapa a partir do status WC sem salvar.
	return gstore_fulfillment_compute_stage_from_wc_status( $order );
}

/**
 * Fallback: computa etapa a partir do status WooCommerce (sem plugin).
 *
 * @param WC_Order $order
 * @return string
 */
function gstore_fulfillment_compute_stage_from_wc_status( $order ) {
	$wc_status = $order->get_status();
	$map = array(
		'pending'    => 'processando_pagamento',
		'on-hold'    => 'processando_pagamento',
		'failed'     => 'processando_pagamento',
		'processing' => 'aguardando_documentacao',
		'completed'  => 'enviado',
		'cancelled'  => 'processando_pagamento',
		'refunded'   => 'processando_pagamento',
	);

	return isset( $map[ $wc_status ] ) ? $map[ $wc_status ] : 'processando_pagamento';
}

/**
 * Retorna os documentos de fulfillment do pedido.
 *
 * @param WC_Order $order
 * @return array
 */
function gstore_get_order_fulfillment_documents( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return array();
	}
	$raw = $order->get_meta( '_gstore_fulfillment_documents' );
	if ( is_string( $raw ) && '' !== $raw ) {
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
	}
	return array();
}

/**
 * Retorna o perfil de documentação do pedido.
 *
 * @param WC_Order $order
 * @return string 'arma', 'municao', 'arma_municao' ou 'none'.
 */
function gstore_get_order_doc_profile( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return 'none';
	}
	$profile = $order->get_meta( '_gstore_fulfillment_doc_profile' );
	return ( is_string( $profile ) && '' !== $profile ) ? $profile : 'none';
}

/**
 * Retorna a lista de documentos requeridos para o pedido.
 *
 * @param WC_Order $order
 * @return array<int, array{key:string, label:string, optional?:bool}>
 */
function gstore_get_order_required_documents( $order ) {
	$profile = gstore_get_order_doc_profile( $order );

	$requirements = array(
		'arma'   => array(
			array( 'key' => 'autorizacao_compra', 'label' => 'Autorização de Compra' ),
		),
		'municao' => array(
			array( 'key' => 'rg_cpf_cnh',  'label' => 'RG / CPF ou CNH' ),
			array( 'key' => 'craf',         'label' => 'CRAF (mesmo calibre, válido)' ),
			array( 'key' => 'cr',           'label' => 'CR (apenas CAC)', 'optional' => true ),
		),
	);

	$result = array();
	if ( 'arma' === $profile || 'arma_municao' === $profile ) {
		$result = array_merge( $result, $requirements['arma'] );
	}
	if ( 'municao' === $profile || 'arma_municao' === $profile ) {
		$result = array_merge( $result, $requirements['municao'] );
	}

	return $result;
}

/**
 * Troca a imagem destacada do template single pelo banner 3:1 do artigo.
 *
 * O filtro fica limitado ao bloco com classe Gstore-blog-single-image em posts,
 * mantendo cards, produtos e outras imagens usando os fluxos atuais.
 *
 * @param string $block_content HTML renderizado pelo WordPress.
 * @param array  $block         Dados do bloco.
 * @return string
 */
function gstore_render_blog_single_banner_image( $block_content, $block ) {
	if ( is_admin() || ! is_singular( 'post' ) || ! is_array( $block ) ) {
		return $block_content;
	}

	$class_name = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	if ( false === strpos( $class_name, 'Gstore-blog-single-image' ) ) {
		return $block_content;
	}

	$post_id = get_queried_object_id();
	if ( $post_id <= 0 || 'post' !== get_post_type( $post_id ) ) {
		return $block_content;
	}

	$banner_image_id = absint( get_post_meta( $post_id, '_gstore_blog_banner_image_id', true ) );
	if ( $banner_image_id <= 0 || ! wp_attachment_is_image( $banner_image_id ) ) {
		return $block_content;
	}

	$alt = trim( (string) get_post_meta( $banner_image_id, '_wp_attachment_image_alt', true ) );
	if ( '' === $alt ) {
		$alt = get_the_title( $post_id );
	}

	$image = wp_get_attachment_image(
		$banner_image_id,
		'full',
		false,
		array(
			'class' => 'wp-post-image',
			'alt'   => $alt,
		)
	);

	if ( ! $image ) {
		return $block_content;
	}

	$classes = trim( 'wp-block-post-featured-image ' . $class_name . ' Gstore-blog-single-image--banner' );

	return sprintf(
		'<figure class="%s">%s</figure>',
		esc_attr( $classes ),
		$image
	);
}
add_filter( 'render_block_core/post-featured-image', 'gstore_render_blog_single_banner_image', 10, 2 );

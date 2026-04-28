<?php
/**
 * Navegação do header: locais desktop/mobile e substituição do bloco Navigation.
 *
 * Quando há menu atribuído aos locais gstore_desktop / gstore_mobile (via Loja > Navegação),
 * o output do bloco core/navigation é substituído por wp_nav_menu com markup compatível ao CSS do tema.
 *
 * @package Gstore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker que gera markup compatível com o CSS do header (.wp-block-navigation__container, etc).
 */
class Gstore_Nav_Menu_Walker extends Walker_Nav_Menu {

	/**
	 * Início da lista.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param stdClass $args   An object of wp_nav_menu() arguments.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
			$t = '';
			$n = '';
		} else {
			$t = "\t";
			$n = "\n";
		}
		$indent = str_repeat( $t, $depth );
		$class  = ( 0 === $depth ) ? 'wp-block-navigation__container' : 'wp-block-navigation__container is-responsive submenu-container';
		$output .= "{$n}{$indent}<ul class=\"" . esc_attr( $class ) . "\">{$n}";
	}

	/**
	 * Início do item.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param WP_Post  $item   Menu item data object.
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param stdClass $args   An object of wp_nav_menu() arguments.
	 * @param int      $id     Current item ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
			$t = '';
			$n = '';
		} else {
			$t = "\t";
			$n = "\n";
		}
		$indent = ( $depth ) ? str_repeat( $t, $depth ) : '';

		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'wp-block-navigation-item menu-item-' . $item->ID;
		if ( in_array( 'current-menu-item', $classes, true ) ) {
			$classes[] = 'current-menu-item';
		}

		$class_names = implode( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );

		$output .= $indent . '<li class="' . esc_attr( $class_names ) . '">';

		$atts           = array();
		$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
		$atts['target'] = ! empty( $item->target ) ? $item->target : '';
		$atts['rel']    = ! empty( $item->xfn ) ? $item->xfn : '';
		$atts['href']   = ! empty( $item->url ) ? $item->url : '';
		$atts['class']  = 'wp-block-navigation-item__content';

		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		// Avoid `the_title` here: WooCommerce endpoint title filters can leak the current
		// My Account endpoint label into header menu items (e.g. "Downloads", "Pedidos (página 2)").
		if ( isset( $item->post_title ) && '' !== $item->post_title ) {
			$item_title = $item->post_title;
		} else {
			$item_title = isset( $item->title ) ? $item->title : '';
		}

		$item_output  = ( $args->before ?? '' );
		$item_output .= '<a' . $attributes . '>';
		$item_output .= ( $args->link_before ?? '' ) . $item_title . ( $args->link_after ?? '' );
		$item_output .= '</a>';
		$item_output .= ( $args->after ?? '' );

		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}
}

/**
 * Substitui o output do bloco core/navigation pelos menus dos locais gstore_desktop / gstore_mobile quando atribuídos.
 *
 * @param string $block_content Conteúdo renderizado do bloco.
 * @param array  $block        Bloco parseado.
 * @return string
 */
function gstore_lock_header_menu_titles( $items, $args ) {
	$theme_location = isset( $args->theme_location ) ? (string) $args->theme_location : '';

	if ( ! in_array( $theme_location, array( 'gstore_desktop', 'gstore_mobile' ), true ) ) {
		return $items;
	}

	foreach ( $items as $item ) {
		if ( empty( $item->ID ) ) {
			continue;
		}

		$stored_title = get_post_field( 'post_title', (int) $item->ID, 'raw' );
		$stored_title = is_string( $stored_title ) ? trim( $stored_title ) : '';

		if ( '' !== $stored_title ) {
			$item->post_title = $stored_title;
			$item->title      = $stored_title;
			continue;
		}

		if ( isset( $item->type, $item->object_id ) && 'post_type' === $item->type && ! empty( $item->object_id ) ) {
			$object_title = get_post_field( 'post_title', (int) $item->object_id, 'raw' );
			if ( is_string( $object_title ) && '' !== trim( $object_title ) ) {
				$item->title = trim( $object_title );
			}
		}
	}

	return $items;
}
add_filter( 'wp_nav_menu_objects', 'gstore_lock_header_menu_titles', 999, 2 );

/**
 * Normaliza valores de filter_cat vindos de querystrings.
 *
 * @param mixed $value Valor bruto do parametro.
 * @return array<int, string>
 */
function gstore_nav_menu_normalize_filter_cat_slugs( $value ) {
	$slugs = array();
	$stack = is_array( $value ) ? $value : array( $value );

	while ( ! empty( $stack ) ) {
		$current = array_shift( $stack );

		if ( is_array( $current ) ) {
			foreach ( $current as $nested ) {
				$stack[] = $nested;
			}
			continue;
		}

		if ( ! is_scalar( $current ) ) {
			continue;
		}

		$slug = sanitize_title( wp_unslash( (string) $current ) );
		if ( '' !== $slug ) {
			$slugs[] = $slug;
		}
	}

	$slugs = array_values( array_unique( $slugs ) );
	sort( $slugs );

	return $slugs;
}

/**
 * Extrai filter_cat de um array de query params.
 *
 * @param array $query_args Query params.
 * @return array<int, string>
 */
function gstore_nav_menu_get_filter_cat_slugs_from_query_args( $query_args ) {
	if ( ! is_array( $query_args ) ) {
		return array();
	}

	$values = array();

	if ( isset( $query_args['filter_cat'] ) ) {
		$values[] = $query_args['filter_cat'];
	}

	if ( isset( $query_args['filter_cat[]'] ) ) {
		$values[] = $query_args['filter_cat[]'];
	}

	return gstore_nav_menu_normalize_filter_cat_slugs( $values );
}

/**
 * Extrai filter_cat de uma URL de item de menu.
 *
 * @param string $url URL do item.
 * @return array<int, string>
 */
function gstore_nav_menu_get_filter_cat_slugs_from_url( $url ) {
	$url = is_string( $url ) ? html_entity_decode( trim( $url ), ENT_QUOTES, 'UTF-8' ) : '';
	if ( '' === $url ) {
		return array();
	}

	$parts = wp_parse_url( $url );
	if ( false === $parts || empty( $parts['query'] ) ) {
		return array();
	}

	$query_args = array();
	wp_parse_str( (string) $parts['query'], $query_args );

	return gstore_nav_menu_get_filter_cat_slugs_from_query_args( $query_args );
}

/**
 * Compara listas de slugs ja normalizadas.
 *
 * @param array $left  Lista A.
 * @param array $right Lista B.
 * @return bool
 */
function gstore_nav_menu_filter_cat_slugs_match( $left, $right ) {
	return ! empty( $left ) && ! empty( $right ) && $left === $right;
}

/**
 * Retorna os slugs filtrados da pagina de catalogo atual.
 *
 * @return array<int, string>
 */
function gstore_nav_menu_get_current_catalog_filter_cat_slugs() {
	static $current_slugs = null;

	if ( null !== $current_slugs ) {
		return $current_slugs;
	}

	$current_slugs = gstore_nav_menu_get_filter_cat_slugs_from_query_args( $_GET );
	if ( empty( $current_slugs ) ) {
		return $current_slugs;
	}

	if ( ! function_exists( 'gstore_normalize_internal_site_path' ) || ! function_exists( 'gstore_get_catalog_url' ) ) {
		return $current_slugs;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
	$request_path = gstore_normalize_internal_site_path( $request_uri );
	$catalog_path = gstore_normalize_internal_site_path( gstore_get_catalog_url() );

	if ( '' === $request_path || '' === $catalog_path || $request_path !== $catalog_path ) {
		$current_slugs = array();
	}

	return $current_slugs;
}

/**
 * Em URLs do catalogo filtrado, deixa ativo apenas o item que representa o filtro clicado.
 *
 * @param array $items Itens do menu.
 * @param object $args Argumentos do wp_nav_menu.
 * @return array
 */
function gstore_prefer_filtered_catalog_nav_menu_item( $items, $args ) {
	$theme_location = isset( $args->theme_location ) ? (string) $args->theme_location : '';

	if ( ! in_array( $theme_location, array( 'gstore_desktop', 'gstore_mobile' ), true ) ) {
		return $items;
	}

	$current_filter_slugs = gstore_nav_menu_get_current_catalog_filter_cat_slugs();
	if ( empty( $current_filter_slugs ) ) {
		return $items;
	}

	$catalog_path      = function_exists( 'gstore_normalize_internal_site_path' ) && function_exists( 'gstore_get_catalog_url' )
		? gstore_normalize_internal_site_path( gstore_get_catalog_url() )
		: '';
	$active_item_ids   = array();
	$current_classes   = array(
		'current-menu-item',
		'current_page_item',
		'current-menu-parent',
		'current_page_parent',
		'current-menu-ancestor',
		'current_page_ancestor',
	);

	foreach ( $items as $item ) {
		if ( empty( $item->url ) || empty( $item->ID ) ) {
			continue;
		}

		if ( '' !== $catalog_path && function_exists( 'gstore_normalize_internal_site_path' ) ) {
			$item_path = gstore_normalize_internal_site_path( (string) $item->url );
			if ( $item_path !== $catalog_path ) {
				continue;
			}
		}

		$item_filter_slugs = gstore_nav_menu_get_filter_cat_slugs_from_url( (string) $item->url );
		if ( gstore_nav_menu_filter_cat_slugs_match( $item_filter_slugs, $current_filter_slugs ) ) {
			$active_item_ids[] = (int) $item->ID;
		}
	}

	if ( empty( $active_item_ids ) ) {
		return $items;
	}

	foreach ( $items as $item ) {
		$classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes = array_values( array_diff( $classes, $current_classes ) );

		if ( ! empty( $item->ID ) && in_array( (int) $item->ID, $active_item_ids, true ) ) {
			$classes[] = 'current-menu-item';
		}

		$item->classes = array_values( array_unique( array_filter( $classes ) ) );
	}

	return $items;
}
add_filter( 'wp_nav_menu_objects', 'gstore_prefer_filtered_catalog_nav_menu_item', 1000, 2 );

function gstore_render_navigation_block_by_location( $block_content, $block ) {
	if ( ! isset( $block['blockName'] ) || 'core/navigation' !== $block['blockName'] ) {
		return $block_content;
	}

	$class_name = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	$has_mobile_class = ( false !== strpos( $class_name, 'Gstore-nav--mobile' ) ) || ( false !== strpos( $block_content, 'Gstore-nav--mobile' ) );
	$has_desktop_class = ( false !== strpos( $class_name, 'Gstore-nav' ) )
		|| ( false !== strpos( $block_content, 'Gstore-nav__menu' ) )
		|| ( false !== strpos( $block_content, 'Gstore-nav' ) );
	$is_mobile  = $has_mobile_class;
	$is_desktop = $has_desktop_class;

	if ( $is_mobile ) {
		$theme_location = 'gstore_mobile';
		// Se não houver menu atribuído ao local mobile, usa o do desktop para o drawer ter conteúdo.
		if ( ! has_nav_menu( $theme_location ) && has_nav_menu( 'gstore_desktop' ) ) {
			$theme_location = 'gstore_desktop';
		}
	} elseif ( $is_desktop ) {
		$theme_location = 'gstore_desktop';
	} else {
		return $block_content;
	}

	if ( ! has_nav_menu( $theme_location ) ) {
		return $block_content;
	}

	$nav_class = 'wp-block-navigation Gstore-nav';
	if ( $is_mobile ) {
		$nav_class .= ' Gstore-nav--mobile';
	}

	$menu_html = wp_nav_menu(
		array(
			'theme_location' => $theme_location,
			'menu_class'     => 'wp-block-navigation__container',
			'container'      => false,
			'echo'           => false,
			'fallback_cb'   => false,
			'walker'         => new Gstore_Nav_Menu_Walker(),
		)
	);

	if ( empty( $menu_html ) ) {
		return $block_content;
	}

	return '<nav class="' . esc_attr( $nav_class ) . '" aria-label="' . esc_attr__( 'Menu principal', 'gstore' ) . '">' . $menu_html . '</nav>';
}

if ( ! class_exists( 'GStore\\Frontend\\Navigation_Menu_Renderer' ) ) {
	add_filter( 'render_block', 'gstore_render_navigation_block_by_location', 10, 2 );
}

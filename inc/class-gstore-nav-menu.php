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
		$item_title   = isset( $item->title ) ? $item->title : '';
		$item_title   = apply_filters( 'nav_menu_item_title', $item_title, $item, $args, $depth );

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

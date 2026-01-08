<?php
/**
 * GStore Category Filter
 * 
 * Implementa um filtro de categorias estilo marketplace (árvore multi-select).
 * 
 * @package Gstore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GStore_Category_Filter {

	/**
	 * Instância única da classe.
	 */
	private static $instance = null;

	/**
	 * Slugs das categorias selecionadas na URL.
	 */
	private $selected_slugs = [];

	/**
	 * Construtor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Obtém a instância da classe.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Inicializa os hooks.
	 */
	private function init_hooks() {
		// Intercepta a renderização do bloco de categorias
		add_filter( 'render_block', [ $this, 'intercept_category_block' ], 10, 2 );

		// Aplica o filtro na query de produtos (para o shortcode [products])
		add_filter( 'woocommerce_shortcode_products_query', [ $this, 'apply_category_filter' ], 20, 3 );
		
		// Também aplica na query principal da loja se necessário
		add_action( 'woocommerce_product_query', [ $this, 'modify_main_product_query' ] );

		// Carrega os slugs selecionados da URL
		$this->load_selected_slugs();
	}

	/**
	 * Carrega os slugs selecionados da querystring filter_cat[].
	 */
	private function load_selected_slugs() {
		$filter_cat = isset( $_GET['filter_cat'] ) ? (array) $_GET['filter_cat'] : [];
		$this->selected_slugs = array_map( 'sanitize_title', $filter_cat );
	}

	/**
	 * Intercepta o bloco de categorias do WooCommerce para substituir pelo nosso HTML customizado.
	 */
	public function intercept_category_block( $block_content, $block ) {
		if ( ! isset( $block['blockName'] ) || 'woocommerce/product-categories' !== $block['blockName'] ) {
			return $block_content;
		}

		return $this->render_filter_html();
	}

	/**
	 * Renderiza o HTML completo do filtro.
	 */
	public function render_filter_html() {
		$categories = $this->get_category_tree();
		
		ob_start();
		?>
		<div class="gstore-category-filter" id="gstore-category-filter">
			<div class="gstore-category-filter__search-wrapper">
				<input type="text" class="gstore-category-filter__search" placeholder="Buscar categoria..." aria-label="Buscar categoria">
				<svg class="gstore-category-filter__search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
				</svg>
			</div>

			<div class="gstore-category-filter__chips" id="gstore-category-filter-chips">
				<!-- Chips serão inseridos via JS -->
			</div>

			<div class="gstore-category-filter__tree-container">
				<ul class="gstore-category-filter__tree">
					<?php $this->render_tree_level( $categories ); ?>
				</ul>
			</div>

			<div class="gstore-category-filter__actions">
				<button type="button" class="gstore-category-filter__btn-clear" id="gstore-filter-clear">Limpar</button>
				<button type="button" class="gstore-category-filter__btn-apply" id="gstore-filter-apply">Aplicar</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Busca e organiza as categorias em árvore.
	 */
	private function get_category_tree() {
		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$tree = [];
		$term_map = [];

		foreach ( $terms as $term ) {
			$term_map[ $term->term_id ] = (object) [
				'id'       => $term->term_id,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'parent'   => $term->parent,
				'count'    => $term->count,
				'children' => [],
			];
		}

		foreach ( $term_map as $id => $node ) {
			if ( $node->parent && isset( $term_map[ $node->parent ] ) ) {
				$term_map[ $node->parent ]->children[] = $node;
			} else {
				$tree[] = $node;
			}
		}

		return $tree;
	}

	/**
	 * Renderiza um nível da árvore recursivamente.
	 */
	private function render_tree_level( $nodes, $level = 0 ) {
		foreach ( $nodes as $node ) {
			$has_children = ! empty( $node->children );
			$is_selected = in_array( $node->slug, $this->selected_slugs );
			
			echo '<li class="gstore-category-filter__item" data-id="' . esc_attr( $node->id ) . '" data-slug="' . esc_attr( $node->slug ) . '" data-level="' . esc_attr( $level ) . '">';
			
			echo '<div class="gstore-category-filter__node">';
			
			// Chevron para expandir
			if ( $has_children ) {
				echo '<button type="button" class="gstore-category-filter__expand" aria-label="Expandir/Recolher">';
				echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>';
				echo '</button>';
			} else {
				echo '<span class="gstore-category-filter__expand-spacer"></span>';
			}

			// Checkbox
			echo '<label class="gstore-category-filter__label">';
			echo '<input type="checkbox" class="gstore-category-filter__checkbox" value="' . esc_attr( $node->slug ) . '" ' . checked( $is_selected, true, false ) . ' data-name="' . esc_attr( $node->name ) . '">';
			echo '<span class="gstore-category-filter__name">' . esc_html( $node->name ) . '</span>';
			echo '<span class="gstore-category-filter__count">' . esc_html( $node->count ) . '</span>';
			echo '</label>';
			
			echo '</div>'; // .gstore-category-filter__node

			if ( $has_children ) {
				echo '<ul class="gstore-category-filter__children">';
				$this->render_tree_level( $node->children, $level + 1 );
				echo '</ul>';
			}

			echo '</li>';
		}
	}

	/**
	 * Aplica o filtro na query dos produtos.
	 */
	public function apply_category_filter( $query_args, $attr, $type ) {
		if ( empty( $this->selected_slugs ) ) {
			return $query_args;
		}

		if ( ! isset( $query_args['tax_query'] ) ) {
			$query_args['tax_query'] = [];
		}

		$query_args['tax_query'][] = [
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $this->selected_slugs,
			'operator' => 'IN',
			'include_children' => true,
		];

		return $query_args;
	}

	/**
	 * Modifica a query principal para incluir as categorias selecionadas.
	 */
	public function modify_main_product_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( empty( $this->selected_slugs ) ) {
			return;
		}

		$tax_query = $query->get( 'tax_query' );
		if ( ! is_array( $tax_query ) ) {
			$tax_query = [];
		}

		$tax_query[] = [
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $this->selected_slugs,
			'operator' => 'IN',
			'include_children' => true,
		];

		$query->set( 'tax_query', $tax_query );
	}
}

// Inicializa a classe
GStore_Category_Filter::get_instance();

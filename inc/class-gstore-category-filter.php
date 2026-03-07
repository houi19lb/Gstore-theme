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
	 * Meta key da ordem manual das categorias (WooCommerce).
	 */
	private const CATEGORY_ORDER_META_KEY = 'order';

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
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );

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
	 * Registra query vars usadas pelo catálogo.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'gstore_catalog_scope';
		return $vars;
	}

	/**
	 * Carrega os slugs selecionados da querystring filter_cat[].
	 */
	private function load_selected_slugs() {
		$filter_cat = isset( $_GET['filter_cat'] ) ? (array) $_GET['filter_cat'] : [];
		$this->selected_slugs = array_map( 'sanitize_title', $filter_cat );
	}

	/**
	 * Retorna o termo de escopo, quando existir e for válido.
	 *
	 * @return \WP_Term|null
	 */
	private function get_scope_term() {
		$skip_main_category_validation = false;

		$scope_slug = get_query_var( 'gstore_catalog_scope', '' );
		if ( '' === $scope_slug && isset( $_GET['gstore_catalog_scope'] ) ) {
			$scope_slug = sanitize_text_field( wp_unslash( $_GET['gstore_catalog_scope'] ) );
		}
		$scope_slug = is_string( $scope_slug ) ? sanitize_title( $scope_slug ) : '';

		// Fallback: paginas de catalogo geradas para categoria principal.
		if ( '' === $scope_slug && is_page() ) {
			$page = get_queried_object();
			if ( $page instanceof \WP_Post && 'page' === $page->post_type ) {
				$is_generated = (bool) get_post_meta( $page->ID, '_gstore_category_catalog_generated', true );
				if ( $is_generated ) {
					$scope_slug = sanitize_title(
						(string) get_post_meta( $page->ID, '_gstore_category_catalog_term_slug', true )
					);
					$skip_main_category_validation = true;
				}
			}
		}

		// Fallback adicional: pagina com template de catalogo cujo slug bate com categoria raiz.
		if ( '' === $scope_slug && is_page() ) {
			$page = get_queried_object();
			if ( $page instanceof \WP_Post && 'page' === $page->post_type ) {
				$template = (string) get_page_template_slug( $page->ID );
				$is_catalog_template = ( '' !== $template && strpos( $template, 'page-catalogo' ) !== false );
				if ( $is_catalog_template && ! empty( $page->post_name ) ) {
					$scope_slug = sanitize_title( $page->post_name );
					$skip_main_category_validation = true;
				}
			}
		}

		if ( '' === $scope_slug ) {
			return null;
		}

		$term = get_term_by( 'slug', $scope_slug, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		// Escopo de catálogo por categoria principal (pai).
		if ( (int) $term->parent !== 0 ) {
			return null;
		}

		$main_category_ids = get_option( 'gstore_main_categories', array() );
		$main_category_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', is_array( $main_category_ids ) ? $main_category_ids : array() )
				)
			)
		);

		if ( ! $skip_main_category_validation && ( empty( $main_category_ids ) || ! in_array( (int) $term->term_id, $main_category_ids, true ) ) ) {
			return null;
		}

		return $term;
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
		$scope_term = $this->get_scope_term();
		$full_catalog_url = home_url( '/catalogo/' );
		
		ob_start();
		?>
		<div class="gstore-category-filter" id="gstore-category-filter" <?php echo $scope_term ? 'data-full-catalog-url="' . esc_url( $full_catalog_url ) . '"' : ''; ?>>
			<div class="gstore-category-filter__search-wrapper">
				<input type="text" class="gstore-category-filter__search" placeholder="Buscar categoria..." aria-label="Buscar categoria">
				<svg class="gstore-category-filter__search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
				</svg>
			</div>

			<?php if ( $scope_term ) : ?>
				<div class="gstore-category-filter__scope">
					<span class="gstore-category-filter__scope-label">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: category name */
								__( 'Categoria atual: %s', 'gstore' ),
								$scope_term->name
							)
						);
						?>
					</span>
					<a class="gstore-category-filter__scope-link" href="<?php echo esc_url( $full_catalog_url ); ?>">
						<?php esc_html_e( 'Ver catalogo completo', 'gstore' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="gstore-category-filter__chips" id="gstore-category-filter-chips">
				<!-- Chips serão inseridos via JS -->
			</div>

			<div class="gstore-category-filter__tree-container">
				<ul class="gstore-category-filter__tree">
					<?php $this->render_tree_level( $categories ); ?>
				</ul>
			</div>

			<div class="gstore-category-filter__actions">
				<?php if ( $scope_term ) : ?>
					<a class="gstore-category-filter__btn-full-catalog" href="<?php echo esc_url( $full_catalog_url ); ?>">Ver catálogo completo</a>
				<?php endif; ?>
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
		$scope_term = $this->get_scope_term();
		$terms = array();
		if ( $scope_term ) {
			$terms = $this->get_terms_for_scoped_products( $scope_term );
		}
		if ( empty( $terms ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
				)
			);
		}

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$term_ids = array_values(
			array_filter(
				array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) )
			)
		);
		if ( ! empty( $term_ids ) ) {
			update_meta_cache( 'term', $term_ids );
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
				'sort_order' => $this->get_term_sort_order( $term->term_id ),
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

		if ( $scope_term ) {
			$scope_slug = (string) $scope_term->slug;
			$tree = array_values(
				array_filter(
					$tree,
					static function( $node ) use ( $scope_slug ) {
						return isset( $node->slug ) && (string) $node->slug !== $scope_slug;
					}
				)
			);
			foreach ( $term_map as $node ) {
				if ( ! empty( $node->children ) ) {
					$node->children = array_values(
						array_filter(
							$node->children,
							static function( $child ) use ( $scope_slug ) {
								return isset( $child->slug ) && (string) $child->slug !== $scope_slug;
							}
						)
					);
				}
			}
		}

		$this->sort_tree_nodes( $tree );

		return $tree;
	}

	/**
	 * Retorna IDs de produtos no escopo da categoria principal (incluindo filhas).
	 *
	 * @param \WP_Term $scope_term Termo de escopo.
	 * @return int[]
	 */
	private function get_scoped_product_ids( $scope_term ) {
		$scope_id = (int) $scope_term->term_id;
		if ( $scope_id <= 0 ) {
			return array();
		}

		$scope_term_ids = array_merge(
			array( $scope_id ),
			array_map( 'absint', get_term_children( $scope_id, 'product_cat' ) )
		);
		$scope_term_ids = array_values( array_unique( array_filter( $scope_term_ids ) ) );
		if ( empty( $scope_term_ids ) ) {
			return array();
		}

		$product_ids = get_objects_in_term( $scope_term_ids, 'product_cat' );
		if ( is_wp_error( $product_ids ) || empty( $product_ids ) ) {
			return array();
		}

		$product_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $product_ids )
				)
			)
		);

		return $product_ids;
	}

	/**
	 * Filtra os IDs para produtos publicados.
	 *
	 * @param int[] $product_ids IDs.
	 * @return int[]
	 */
	private function filter_published_product_ids( $product_ids ) {
		if ( empty( $product_ids ) ) {
			return array();
		}

		$published_ids = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'post__in'               => $product_ids,
				'posts_per_page'         => -1,
				'orderby'                => 'post__in',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array_values(
			array_unique(
				array_filter(
					array_map( 'absint', is_array( $published_ids ) ? $published_ids : array() )
				)
			)
		);
	}

	/**
	 * Retorna os IDs de termos selecionados (categoria extra no filtro).
	 *
	 * @return int[]
	 */
	private function get_selected_term_ids() {
		if ( empty( $this->selected_slugs ) ) {
			return array();
		}

		$ids = array();
		foreach ( $this->selected_slugs as $slug ) {
			$term = get_term_by( 'slug', sanitize_title( $slug ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Retorna slugs selecionados normalizados para uso em tax_query.
	 *
	 * @return string[]
	 */
	private function get_selected_slugs_for_query() {
		if ( empty( $this->selected_slugs ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_title', $this->selected_slugs )
				)
			)
		);
	}

	/**
	 * Calcula contexto de produtos/contagens para o escopo atual (scope + selecao em OR).
	 *
	 * @param \WP_Term $scope_term Termo de escopo.
	 * @return array{product_ids:int[],counts:array<int,int>}
	 */
	private function get_scoped_context_data( $scope_term ) {
		$scope_product_ids = $this->filter_published_product_ids( $this->get_scoped_product_ids( $scope_term ) );
		if ( empty( $scope_product_ids ) ) {
			return array( 'product_ids' => array(), 'counts' => array() );
		}

		$relations = wp_get_object_terms(
			$scope_product_ids,
			'product_cat',
			array(
				'fields'  => 'all_with_object_id',
				'orderby' => 'none',
			)
		);
		if ( is_wp_error( $relations ) || empty( $relations ) ) {
			return array( 'product_ids' => array(), 'counts' => array() );
		}

		$product_terms = array();
		foreach ( $relations as $row ) {
			$pid = isset( $row->object_id ) ? (int) $row->object_id : 0;
			$tid = isset( $row->term_id ) ? (int) $row->term_id : 0;
			if ( $pid <= 0 || $tid <= 0 ) {
				continue;
			}
			if ( ! isset( $product_terms[ $pid ] ) ) {
				$product_terms[ $pid ] = array();
			}
			$product_terms[ $pid ][ $tid ] = true;
		}

		$selected_term_ids = $this->get_selected_term_ids();
		$selected_term_pool = array();
		foreach ( $selected_term_ids as $selected_term_id ) {
			$selected_term_pool = array_merge(
				$selected_term_pool,
				array( (int) $selected_term_id ),
				array_map( 'absint', get_term_children( (int) $selected_term_id, 'product_cat' ) )
			);
		}
		$selected_term_pool = array_values( array_unique( array_filter( $selected_term_pool ) ) );

		$context_product_ids = array();
		foreach ( $scope_product_ids as $pid ) {
			$assigned = isset( $product_terms[ $pid ] ) ? array_map( 'intval', array_keys( $product_terms[ $pid ] ) ) : array();
			if ( empty( $assigned ) ) {
				continue;
			}

			if ( ! empty( $selected_term_pool ) && empty( array_intersect( $assigned, $selected_term_pool ) ) ) {
				continue;
			}

			$context_product_ids[] = (int) $pid;
		}

		$context_product_ids = array_values( array_unique( array_filter( $context_product_ids ) ) );
		if ( empty( $context_product_ids ) ) {
			return array( 'product_ids' => array(), 'counts' => array() );
		}

		$counts = array();
		$ancestor_cache = array();
		foreach ( $context_product_ids as $pid ) {
			$assigned = isset( $product_terms[ $pid ] ) ? array_map( 'intval', array_keys( $product_terms[ $pid ] ) ) : array();
			if ( empty( $assigned ) ) {
				continue;
			}

			$expanded = array();
			foreach ( $assigned as $term_id ) {
				$expanded[ $term_id ] = true;
				if ( ! isset( $ancestor_cache[ $term_id ] ) ) {
					$ancestor_cache[ $term_id ] = array_map( 'absint', get_ancestors( $term_id, 'product_cat', 'taxonomy' ) );
				}
				foreach ( $ancestor_cache[ $term_id ] as $ancestor_id ) {
					$expanded[ (int) $ancestor_id ] = true;
				}
			}

			foreach ( array_keys( $expanded ) as $expanded_term_id ) {
				$expanded_term_id = (int) $expanded_term_id;
				if ( ! isset( $counts[ $expanded_term_id ] ) ) {
					$counts[ $expanded_term_id ] = 0;
				}
				$counts[ $expanded_term_id ]++;
			}
		}

		return array(
			'product_ids' => $context_product_ids,
			'counts'      => $counts,
		);
	}

	/**
	 * Carrega categorias presentes no contexto do escopo e aplica contagem real do contexto.
	 *
	 * @param \WP_Term $scope_term Termo de escopo.
	 * @return \WP_Term[]
	 */
	private function get_terms_for_scoped_products( $scope_term ) {
		$context = $this->get_scoped_context_data( $scope_term );
		$counts = isset( $context['counts'] ) && is_array( $context['counts'] ) ? $context['counts'] : array();
		$term_ids = array_values( array_map( 'intval', array_keys( $counts ) ) );
		if ( empty( $term_ids ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'include'    => $term_ids,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			$tid = (int) $term->term_id;
			$term->count = isset( $counts[ $tid ] ) ? (int) $counts[ $tid ] : 0;
			if ( $term->count > 0 ) {
				$out[] = $term;
			}
		}

		return $out;
	}

	/**
	 * Obtém o número de ordenação manual da categoria.
	 *
	 * @param int $term_id ID da categoria.
	 * @return int
	 */
	private function get_term_sort_order( $term_id ) {
		$term_id = absint( $term_id );
		if ( $term_id <= 0 ) {
			return 0;
		}

		return (int) get_term_meta( $term_id, self::CATEGORY_ORDER_META_KEY, true );
	}

	/**
	 * Ordena a árvore recursivamente por número de ordenação e, em empate, por nome.
	 *
	 * @param array<int,object> $nodes Nós da árvore (passagem por referência).
	 * @return void
	 */
	private function sort_tree_nodes( &$nodes ) {
		if ( empty( $nodes ) || ! is_array( $nodes ) ) {
			return;
		}

		usort(
			$nodes,
			static function( $left, $right ) {
				$left_order  = isset( $left->sort_order ) ? (int) $left->sort_order : 0;
				$right_order = isset( $right->sort_order ) ? (int) $right->sort_order : 0;

				if ( $left_order !== $right_order ) {
					return $left_order <=> $right_order;
				}

				return strcasecmp( (string) ( $left->name ?? '' ), (string) ( $right->name ?? '' ) );
			}
		);

		foreach ( $nodes as $node ) {
			if ( ! empty( $node->children ) ) {
				$this->sort_tree_nodes( $node->children );
			}
		}
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
		$scope_term = $this->get_scope_term();
		$has_scope = (bool) $scope_term;
		$selected_slugs = $this->get_selected_slugs_for_query();
		$has_selected = ! empty( $selected_slugs );

		if ( ! $has_scope && ! $has_selected ) {
			return $query_args;
		}

		if ( ! isset( $query_args['tax_query'] ) ) {
			$query_args['tax_query'] = [];
		}

		if ( $has_scope ) {
			$query_args['tax_query'][] = [
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => [ (int) $scope_term->term_id ],
				'operator'         => 'IN',
				'include_children' => true,
			];
		}

		if ( $has_selected ) {
			$query_args['tax_query'][] = [
				'taxonomy'         => 'product_cat',
				'field'            => 'slug',
				'terms'            => $selected_slugs,
				'operator'         => 'IN',
				'include_children' => true,
			];
		}

		return $query_args;
	}

	/**
	 * Modifica a query principal para incluir as categorias selecionadas.
	 */
	public function modify_main_product_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$scope_term = $this->get_scope_term();
		$has_scope = (bool) $scope_term;
		$selected_slugs = $this->get_selected_slugs_for_query();
		$has_selected = ! empty( $selected_slugs );

		if ( ! $has_scope && ! $has_selected ) {
			return;
		}

		$tax_query = $query->get( 'tax_query' );
		if ( ! is_array( $tax_query ) ) {
			$tax_query = [];
		}

		if ( $has_scope ) {
			$tax_query[] = [
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => [ (int) $scope_term->term_id ],
				'operator'         => 'IN',
				'include_children' => true,
			];
		}

		if ( $has_selected ) {
			$tax_query[] = [
				'taxonomy'         => 'product_cat',
				'field'            => 'slug',
				'terms'            => $selected_slugs,
				'operator'         => 'IN',
				'include_children' => true,
			];
		}

		$query->set( 'tax_query', $tax_query );
	}
}

// Inicializa a classe
GStore_Category_Filter::get_instance();

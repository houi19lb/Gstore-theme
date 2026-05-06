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
		$is_archive_scope = false;

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

		if ( '' === $scope_slug && function_exists( 'is_product_category' ) && is_product_category() ) {
			$queried_term = get_queried_object();
			if ( $queried_term instanceof \WP_Term && 'product_cat' === $queried_term->taxonomy ) {
				$scope_slug = sanitize_title( (string) $queried_term->slug );
				$skip_main_category_validation = true;
				$is_archive_scope = true;
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
		if ( ! $is_archive_scope && (int) $term->parent !== 0 ) {
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
		$categories       = $this->get_category_tree();
		$scope_term       = $this->get_scope_term();
		$is_ofertas       = $this->is_ofertas_page();
		$has_scope        = $scope_term || $is_ofertas;
		$full_catalog_url = function_exists( 'gstore_get_catalog_url' ) ? gstore_get_catalog_url() : home_url( '/catalogo/' );
		$context_nav      = $scope_term ? $this->get_context_category_navigation( $scope_term ) : array( 'title' => '', 'nodes' => array() );
		$excluded_root_id = $scope_term ? $this->get_root_term_id( $scope_term ) : 0;
		$global_categories = $excluded_root_id > 0 ? $this->filter_tree_excluding_root( $categories, $excluded_root_id ) : $categories;

		ob_start();
		?>
		<div class="gstore-category-filter" id="gstore-category-filter" <?php echo $has_scope ? 'data-full-catalog-url="' . esc_url( $full_catalog_url ) . '"' : ''; ?>>
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
			<?php elseif ( $is_ofertas ) : ?>
				<div class="gstore-category-filter__scope">
					<span class="gstore-category-filter__scope-label">
						<?php esc_html_e( 'Mostrando: Ofertas', 'gstore' ); ?>
					</span>
					<a class="gstore-category-filter__scope-link" href="<?php echo esc_url( $full_catalog_url ); ?>">
						<?php esc_html_e( 'Ver catalogo completo', 'gstore' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="gstore-category-filter__chips" id="gstore-category-filter-chips">
				<!-- Chips serão inseridos via JS -->
			</div>

			<?php if ( ! empty( $context_nav['nodes'] ) ) : ?>
				<div class="gstore-category-filter__nav-section gstore-category-filter__nav-section--context">
					<div class="gstore-category-filter__section-title"><?php echo esc_html( $context_nav['title'] ); ?></div>
					<div class="gstore-category-filter__tree-container gstore-category-filter__tree-container--context">
						<ul class="gstore-category-filter__tree gstore-category-filter__tree--context">
							<?php $this->render_tree_level( $context_nav['nodes'] ); ?>
						</ul>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $global_categories ) ) : ?>
				<div class="gstore-category-filter__nav-section gstore-category-filter__nav-section--global">
					<div class="gstore-category-filter__section-title">
						<?php echo esc_html( $scope_term ? __( 'Outras categorias', 'gstore' ) : __( 'Todas as categorias', 'gstore' ) ); ?>
					</div>
					<div class="gstore-category-filter__tree-container">
						<ul class="gstore-category-filter__tree">
							<?php $this->render_tree_level( $global_categories ); ?>
						</ul>
					</div>
				</div>
			<?php endif; ?>

			<div class="gstore-category-filter__actions">
				<?php if ( $has_scope ) : ?>
					<a class="gstore-category-filter__btn-full-catalog" href="<?php echo esc_url( $full_catalog_url ); ?>">Ver catálogo completo</a>
				<?php endif; ?>
				<button type="button" class="gstore-category-filter__btn-clear" id="gstore-filter-clear">Limpar</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Verifica se está na página de ofertas.
	 *
	 * @return bool
	 */
	private function is_ofertas_page() {
		if ( ! function_exists( 'is_page' ) || ! is_page() ) {
			return false;
		}
		$page = get_queried_object();
		if ( ! $page instanceof \WP_Post ) {
			return false;
		}
		return 'ofertas' === $page->post_name;
	}

	/**
	 * Retorna IDs de produtos em oferta (publicados).
	 *
	 * @return int[]
	 */
	private function get_sale_product_ids() {
		if ( ! function_exists( 'wc_get_product_ids_on_sale' ) ) {
			return array();
		}
		$sale_ids = wc_get_product_ids_on_sale();
		if ( empty( $sale_ids ) ) {
			return array();
		}
		return $this->filter_published_product_ids( array_map( 'absint', $sale_ids ) );
	}

	/**
	 * Calcula contexto de produtos/contagens para produtos em oferta (espelha get_scoped_context_data).
	 *
	 * @return array{product_ids:int[],counts:array<int,int>}
	 */
	private function get_sale_context_data() {
		$sale_product_ids = $this->get_sale_product_ids();
		if ( empty( $sale_product_ids ) ) {
			return array( 'product_ids' => array(), 'counts' => array() );
		}

		$relations = wp_get_object_terms(
			$sale_product_ids,
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

		$selected_term_ids  = $this->get_selected_term_ids();
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
		foreach ( $sale_product_ids as $pid ) {
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

		$counts         = array();
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
	 * Carrega categorias presentes nos produtos em oferta e aplica contagem real do contexto.
	 *
	 * @return \WP_Term[]
	 */
	private function get_terms_for_sale_products() {
		$context  = $this->get_sale_context_data();
		$counts   = isset( $context['counts'] ) && is_array( $context['counts'] ) ? $context['counts'] : array();
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
			$tid         = (int) $term->term_id;
			$term->count = isset( $counts[ $tid ] ) ? (int) $counts[ $tid ] : 0;
			if ( $term->count > 0 ) {
				$out[] = $term;
			}
		}

		return $out;
	}

	/**
	 * Busca e organiza as categorias em árvore.
	 */
	private function get_category_tree() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'exclude'    => $this->get_navigation_excluded_term_ids(),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		return $this->build_category_tree_from_terms( $terms );
	}

	/**
	 * Monta uma arvore de categorias a partir de termos carregados.
	 *
	 * @param \WP_Term[] $terms Termos.
	 * @return array<int,object>
	 */
	private function build_category_tree_from_terms( $terms ) {
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
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$term_map[ $term->term_id ] = (object) [
				'id'       => $term->term_id,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'parent'   => $term->parent,
				'count'    => $term->count,
				'url'      => $this->get_term_archive_url( $term ),
				'is_current' => $this->is_current_term( $term ),
				'is_current_ancestor' => $this->is_current_term_ancestor( $term ),
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

		$this->sort_tree_nodes( $tree );

		return $tree;
	}

	/**
	 * Retorna URL limpa do archive nativo da categoria.
	 *
	 * @param \WP_Term $term Termo.
	 * @return string
	 */
	private function get_term_archive_url( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		$link = get_term_link( $term, 'product_cat' );
		if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
			return '';
		}

		return $link;
	}

	/**
	 * Retorna o termo da categoria atual quando a tela e um archive product_cat.
	 *
	 * @return \WP_Term|null
	 */
	private function get_current_product_category_term() {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return null;
		}

		$term = get_queried_object();
		return $term instanceof \WP_Term && 'product_cat' === $term->taxonomy ? $term : null;
	}

	/**
	 * Indica se um termo e a categoria atual.
	 *
	 * @param \WP_Term $term Termo.
	 * @return bool
	 */
	private function is_current_term( $term ) {
		$current = $this->get_current_product_category_term();
		return $current instanceof \WP_Term && $term instanceof \WP_Term && (int) $current->term_id === (int) $term->term_id;
	}

	/**
	 * Indica se um termo e ancestral da categoria atual.
	 *
	 * @param \WP_Term $term Termo.
	 * @return bool
	 */
	private function is_current_term_ancestor( $term ) {
		$current = $this->get_current_product_category_term();
		if ( ! $current instanceof \WP_Term || ! $term instanceof \WP_Term || (int) $current->term_id === (int) $term->term_id ) {
			return false;
		}

		return in_array( (int) $term->term_id, array_map( 'absint', get_ancestors( (int) $current->term_id, 'product_cat', 'taxonomy' ) ), true );
	}

	/**
	 * Retorna o ID do termo raiz de uma categoria.
	 *
	 * @param \WP_Term $term Termo.
	 * @return int
	 */
	private function get_root_term_id( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return 0;
		}

		$ancestors = array_map( 'absint', get_ancestors( (int) $term->term_id, 'product_cat', 'taxonomy' ) );
		if ( empty( $ancestors ) ) {
			return (int) $term->term_id;
		}

		return (int) end( $ancestors );
	}

	/**
	 * Remove da arvore global a familia exibida no bloco contextual.
	 *
	 * @param array<int,object> $nodes       Nos.
	 * @param int               $excluded_id ID raiz excluido.
	 * @return array<int,object>
	 */
	private function filter_tree_excluding_root( $nodes, $excluded_id ) {
		$excluded_id = absint( $excluded_id );
		if ( $excluded_id <= 0 || empty( $nodes ) ) {
			return $nodes;
		}

		$out = array();
		foreach ( $nodes as $node ) {
			if ( isset( $node->id ) && (int) $node->id === $excluded_id ) {
				continue;
			}
			$out[] = $node;
		}

		return $out;
	}

	/**
	 * Monta o bloco contextual: filhas da categoria atual ou irmas quando ja estiver em uma filha.
	 *
	 * @param \WP_Term $scope_term Termo atual.
	 * @return array{title:string,nodes:array<int,object>}
	 */
	private function get_context_category_navigation( $scope_term ) {
		if ( ! $scope_term instanceof \WP_Term ) {
			return array( 'title' => '', 'nodes' => array() );
		}

		$parent_for_list = (int) $scope_term->term_id;
		$title_term      = $scope_term;

		$direct_children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => (int) $scope_term->term_id,
				'hide_empty' => true,
				'exclude'    => $this->get_navigation_excluded_term_ids(),
			)
		);

		if ( ( is_wp_error( $direct_children ) || empty( $direct_children ) ) && (int) $scope_term->parent > 0 ) {
			$parent_term = get_term( (int) $scope_term->parent, 'product_cat' );
			if ( $parent_term instanceof \WP_Term && ! is_wp_error( $parent_term ) ) {
				$parent_for_list = (int) $parent_term->term_id;
				$title_term      = $parent_term;
			}
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'child_of'   => $parent_for_list,
				'hide_empty' => true,
				'exclude'    => $this->get_navigation_excluded_term_ids(),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array( 'title' => '', 'nodes' => array() );
		}

		return array(
			'title' => sprintf(
				/* translators: %s: category name */
				__( 'Subcategorias de %s', 'gstore' ),
				$title_term->name
			),
			'nodes' => $this->build_category_tree_from_terms( $terms ),
		);
	}

	/**
	 * Termos que nao devem aparecer na navegacao lateral de categorias.
	 *
	 * @return int[]
	 */
	private function get_navigation_excluded_term_ids() {
		static $excluded_ids = null;
		if ( null !== $excluded_ids ) {
			return $excluded_ids;
		}

		$blocked_slugs = apply_filters(
			'gstore_noindex_product_term_slugs',
			array( 'sem-categoria', 'uncategorized', 'diversos', 'diversas' )
		);
		$blocked_slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) $blocked_slugs ) ) ) );
		if ( empty( $blocked_slugs ) ) {
			$excluded_ids = array();
			return $excluded_ids;
		}

		$term_ids = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'slug'       => $blocked_slugs,
				'fields'     => 'ids',
			)
		);

		$excluded_ids = is_wp_error( $term_ids )
			? array()
			: array_values( array_unique( array_filter( array_map( 'absint', (array) $term_ids ) ) ) );

		return $excluded_ids;
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
			$item_classes = array( 'gstore-category-filter__item' );
			if ( ! empty( $node->is_current ) ) {
				$item_classes[] = 'is-current';
			}
			if ( ! empty( $node->is_current_ancestor ) ) {
				$item_classes[] = 'is-current-ancestor';
			}
			
			echo '<li class="' . esc_attr( implode( ' ', $item_classes ) ) . '" data-id="' . esc_attr( $node->id ) . '" data-slug="' . esc_attr( $node->slug ) . '" data-level="' . esc_attr( $level ) . '">';
			
			echo '<div class="gstore-category-filter__node">';
			
			// Chevron para expandir
			if ( $has_children ) {
				echo '<button type="button" class="gstore-category-filter__expand" aria-label="Expandir/Recolher">';
				echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>';
				echo '</button>';
			} else {
				echo '<span class="gstore-category-filter__expand-spacer"></span>';
			}

			echo '<a class="gstore-category-filter__label gstore-category-filter__link" href="' . esc_url( $node->url ) . '"' . ( ! empty( $node->is_current ) ? ' aria-current="page"' : '' ) . '>';
			echo '<span class="gstore-category-filter__name">' . esc_html( $node->name ) . '</span>';
			echo '<span class="gstore-category-filter__count">' . esc_html( $node->count ) . '</span>';
			echo '</a>';
			
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

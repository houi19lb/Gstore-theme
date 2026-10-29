<?php
/**
 * Real theme callbacks + optional real plugin category selector; synthetic data.
 * Run: php tests/catalog-breadcrumbs.php [functions.php] [plugin-root]
 */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', __DIR__ . '/' );
$source = file_get_contents( $argv[1] ?? dirname( __DIR__ ) . '/functions.php' );
$checks = 0; $terms = array(); $relations = array(); $post_meta = array(); $term_meta = array();
$main_categories = array( 99, 20, 10 ); $brand_products = array(); $term_queries = 0; $bulk_queries = 0; $meta_batches = 0; $metadata_reads = 0; $metadata_cache = array();
function check( $ok, $message ) { ++$GLOBALS['checks']; if ( ! $ok ) { throw new RuntimeException( $message ); } }
function load_callback( $name ) {
	$start = strpos( $GLOBALS['source'], 'function ' . $name . '(' );
	check( false !== $start, 'Missing callback: ' . $name );
	$end = strpos( $GLOBALS['source'], "\n}", $start );
	eval( substr( $GLOBALS['source'], $start, $end + 2 - $start ) );
}
function absint( $v ) { return abs( (int) $v ); }
function __( $s, ...$args ) { return $s; }
function sanitize_title( $s ) { return strtolower( str_replace( ' ', '-', $s ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function esc_url_raw( $s ) { return $s; }
function home_url( $s = '' ) { return 'https://example.test' . $s; }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function gstore_get_catalog_url() { return home_url( '/catalogo/' ); }
function apply_filters( $hook, $v, ...$args ) { return $v; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function taxonomy_exists( $s ) { return in_array( $s, array( 'product_cat', 'product_brand' ), true ); }
function is_taxonomy_hierarchical( $s ) { return 'product_cat' === $s; }
function gstore_theme_get_public_product_post_statuses() { return array( 'publish' ); }
function get_option( $key, $default = false ) { return 'gstore_main_categories' === $key ? $GLOBALS['main_categories'] : $default; }
function get_term( $id, $taxonomy ) { $t = $GLOBALS['terms'][$id] ?? null; return $t && $t->taxonomy === $taxonomy ? $t : null; }
function get_term_link( $term, ...$args ) { return home_url( ( 'product_brand' === $term->taxonomy ? '/marca/' : '/categoria/' ) . $term->slug . '/' ); }
function get_ancestors( $id, ...$args ) { $out = array(); while ( $id = ( $GLOBALS['terms'][$id]->parent ?? 0 ) ) { $out[] = $id; } return $out; }
function get_term_children( $id, ...$args ) { return array_keys( array_filter( $GLOBALS['terms'], static fn( $t ) => in_array( $id, get_ancestors( $t->term_id ), true ) ) ); }
function get_term_meta( $id, $key, $single = false ) { return $GLOBALS['term_meta'][$id][$key] ?? ''; }
function get_terms( $args ) {
	$terms = array_filter( $GLOBALS['terms'], static fn( $t ) => 'product_cat' === $t->taxonomy );
	if ( isset( $args['include'] ) ) { $terms = array_intersect_key( $terms, array_flip( $args['include'] ) ); }
	if ( isset( $args['parent'] ) ) { $terms = array_filter( $terms, static fn( $t ) => $t->parent === $args['parent'] ); }
	$terms = array_values( $terms ); return 'ids' === ( $args['fields'] ?? '' ) ? array_column( $terms, 'term_id' ) : $terms;
}
function get_posts( $args ) {
	check( false === $args['suppress_filters'], 'Brand selection must retain public product query filters.' );
	check( array( 'publish' ) === $args['post_status'], 'Public status scope unchanged.' );
	return $GLOBALS['brand_products'][ $args['tax_query'][0]['terms'][0] ] ?? array();
}
function update_meta_cache( $type, $ids ) { ++$GLOBALS['meta_batches']; foreach ( $ids as $id ) { $GLOBALS['metadata_cache'][$id] = true; } }
function get_post_meta( $id, $key, $single = false ) { if ( empty( $GLOBALS['metadata_cache'][$id] ) ) { ++$GLOBALS['metadata_reads']; $GLOBALS['metadata_cache'][$id] = true; } return $GLOBALS['post_meta'][$id][$key] ?? ''; }
function wp_get_post_terms( $id, $taxonomy, $args = array() ) { return wp_get_object_terms( array( $id ), $taxonomy, $args ); }
function wp_get_object_terms( $ids, $taxonomy, $args = array() ) {
	++$GLOBALS['term_queries'];
	$bulk = 'all_with_object_id' === ( $args['fields'] ?? '' );
	if ( $bulk ) { ++$GLOBALS['bulk_queries']; if ( ! empty( $GLOBALS['fail_bulk'] ) ) { return new WP_Error(); } }
	$out = array();
	foreach ( (array) $ids as $id ) {
		foreach ( $GLOBALS['relations'][$id] ?? array() as $term_id ) {
			$t = clone $GLOBALS['terms'][$term_id];
			if ( in_array( 12, array_merge( array( $term_id ), get_ancestors( $term_id ) ), true ) ) { continue; }
			if ( $bulk && empty( $GLOBALS['strip_object_id'] ) ) { $t->object_id = $id; }
			$out[] = $t;
		}
	}
	usort( $out, static fn( $a, $b ) => strcasecmp( $a->name, $b->name ) ); return $out;
}
class WP_Error {}
class WP_Term {
	public $term_id; public $name; public $slug; public $parent; public $taxonomy; public $object_id;
	public function __construct( $id, $name, $parent = 0, $taxonomy = 'product_cat' ) { $this->term_id = $id; $this->name = $name; $this->slug = sanitize_title( $name ); $this->parent = $parent; $this->taxonomy = $taxonomy; unset( $this->object_id ); }
}
class WP_Query { public $found_posts = 0; }
if ( isset( $argv[2] ) ) { require $argv[2] . '/includes/Services/class-primary-category-service.php'; }
foreach ( array( 'gstore_get_catalog_archive_brand_term', 'gstore_get_main_catalog_category_ids_for_breadcrumb', 'gstore_get_brand_breadcrumb_excluded_main_category_slugs', 'gstore_filter_brand_breadcrumb_main_category_ids', 'gstore_get_product_category_scope_ids_for_breadcrumb', 'gstore_resolve_main_catalog_category_id_for_breadcrumb', 'gstore_count_brand_products_in_categories_for_breadcrumb', 'gstore_get_brand_published_product_ids_for_breadcrumb', 'gstore_get_product_main_catalog_category_id_for_breadcrumb', 'gstore_get_brand_primary_category_scores_from_products_for_breadcrumb', 'gstore_get_brand_primary_product_category_for_breadcrumb', 'gstore_get_product_term_archive_url_for_breadcrumb', 'gstore_get_catalog_term_breadcrumb_schema' ) as $name ) { load_callback( $name ); }
$terms = array( 10 => new WP_Term( 10, 'Pistolas' ), 11 => new WP_Term( 11, 'Compactas', 10 ), 12 => new WP_Term( 12, 'Oculta' ), 13 => new WP_Term( 13, 'Filha-oculta', 12 ), 20 => new WP_Term( 20, 'Municao' ), 21 => new WP_Term( 21, '9mm', 20 ), 99 => new WP_Term( 99, 'Ofertas' ) );
$relations = array( 101 => array( 10, 11 ), 102 => array( 20, 21 ), 103 => array( 10, 11 ), 104 => array( 20 ), 105 => array( 13 ), 106 => array( 99 ) );
$post_meta[103]['_gstore_primary_product_cat'] = 20;
$relations[201] = $relations[101]; $relations[202] = $relations[102]; $relations[204] = $relations[104];
$relations[301] = $relations[101]; $relations[302] = $relations[102]; $relations[304] = $relations[104];
$large_brand_ids = range( 500, 599 );
foreach ( $large_brand_ids as $id ) { $relations[$id] = $id < 570 ? array( 10, 11 ) : array( 20, 21 ); }
$scenarios = array(
	// Separate brands model independently scoped requests, without sharing static keys.
	'public' => array( 'ids' => array( 101, 102, 104, 105, 106 ), 'primary' => 20 ),
	'athlete' => array( 'ids' => array( 101, 102, 103, 104, 105, 106 ), 'primary' => 20 ),
	'tie' => array( 'ids' => array( 101, 102 ), 'primary' => 20 ),
	'manual' => array( 'ids' => array( 103 ), 'primary' => isset( $argv[2] ) ? 20 : 10 ),
	'empty' => array( 'ids' => array(), 'primary' => 0 ),
	'query-error-fallback' => array( 'ids' => array( 201, 202, 204 ), 'primary' => 20 ),
	'missing-association-fallback' => array( 'ids' => array( 301, 302, 304 ), 'primary' => 20 ),
	'large-brand' => array( 'ids' => $large_brand_ids, 'primary' => 10 ),
);
$schemas = array(); $brand_id = 1000;
foreach ( $scenarios as $name => $scenario ) {
	$brand = new WP_Term( ++$brand_id, 'Brand-' . $name, 0, 'product_brand' ); $terms[$brand_id] = $brand;
	$brand_products[$brand_id] = $scenario['ids']; $fail_bulk = 'query-error-fallback' === $name;
	$strip_object_id = 'missing-association-fallback' === $name;
	$primary = gstore_get_brand_primary_product_category_for_breadcrumb( $brand );
	check( ( $primary->term_id ?? 0 ) === $scenario['primary'], "$name primary category is unchanged" );
	$url = get_term_link( $brand ); $schema = gstore_get_catalog_term_breadcrumb_schema( $brand, $url );
	$items = $schema['itemListElement'];
	check( 'BreadcrumbList' === $schema['@type'] && $url . '#breadcrumb' === $schema['@id'], "$name schema identity/canonical URL" );
	check( array( 'Inicio', 'Catalogo' ) === array_slice( array_column( $items, 'name' ), 0, 2 ), "$name leading breadcrumb items" );
	check( $url === end( $items )['item'], "$name canonical brand URL preserved" );
	check( range( 1, count( $items ) ) === array_column( $items, 'position' ), "$name positions remain consecutive" );
	check( false === strpos( json_encode( $schema ), 'oculta' ), "$name hidden family does not enter breadcrumbs" );
	$schemas[$name] = $schema;
}
$fail_bulk = false;
$category_schema = gstore_get_catalog_term_breadcrumb_schema( $terms[21], get_term_link( $terms[21] ) );
check( array( 'Inicio', 'Catalogo', 'Municao', '9mm' ) === array_column( $category_schema['itemListElement'], 'name' ), 'Category hierarchy/schema unchanged.' );
$schemas['category'] = $category_schema;
check( array() === gstore_get_catalog_term_breadcrumb_schema( null, '' ), 'Invalid terms do not emit schema.' );
echo json_encode( array( 'checks' => $checks, 'schemas_sha256' => hash( 'sha256', json_encode( $schemas ) ), 'term_queries' => $term_queries, 'bulk_queries' => $bulk_queries, 'metadata_batches' => $meta_batches, 'uncached_metadata_reads' => $metadata_reads, 'plugin_loaded' => isset( $argv[2] ) ) ), "\n";

<?php
/** Exercise the real theme entry points with and without the new plugin API. */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );

$mode = $argv[1] ?? 'absent';
if ( 'current' === $mode ) {
	require $argv[2];
} elseif ( 'legacy' === $mode ) {
	class Gstore_Slow_Request_Diagnostics {}
}
$wpdb = (object) array( 'num_queries' => 0 );
$cache = array();
$writes = array();
$reads = 0;
$rank_reads = 0;
$posts = array( 'one', 'two' );

function check( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
function absint( $value ) { return abs( (int) $value ); }
function get_transient( $key ) {
	++$GLOBALS['reads'];
	++$GLOBALS['wpdb']->num_queries;
	if ( isset( $GLOBALS['read_error'] ) ) { throw $GLOBALS['read_error']; }
	return $GLOBALS['cache'][ $key ] ?? false;
}
function set_transient( $key, $value, $ttl ) {
	$GLOBALS['writes'][] = array( $key, $value, $ttl );
	$GLOBALS['cache'][ $key ] = $value;
}
function gstore_related_products_get_term_ids( ...$args ) { ++$GLOBALS['rank_reads']; return array(); }
function gstore_related_products_get_specific_category_ids( ...$args ) { return array(); }
function gstore_related_products_get_broader_category_ids( ...$args ) { return array(); }
function gstore_related_products_query_candidate_ids( ...$args ) { return array(); }
function gstore_related_products_build_priority_mix( $specific, $category, $tags, $fallback, $limit ) { return array_slice( $fallback, 0, $limit ); }
function gstore_related_products_order_by_popularity( $ids ) { return $ids; }
function _prime_post_caches( ...$args ) {}
function gstore_related_products_get_category_lineage_ids( ...$args ) { return array(); }
function wc_get_product( $id ) {
	return new class( $id ) {
		public function __construct( public $id ) {}
		public function is_in_stock() { return 9 !== $this->id; }
	};
}
function wc_products_array_filter_visible( $product ) { return 8 !== $product->id; }
function get_header( $name ) { echo "header:$name;"; }
function get_footer( $name ) { echo "footer:$name;"; }
function do_action( $name ) { if ( 'woocommerce_shop_loop' === $name ) { echo 'hook;'; } }
function woocommerce_product_loop() { return true; }
function woocommerce_product_loop_start() { echo 'start;'; }
function woocommerce_product_loop_end() { echo 'end;'; }
function have_posts() { return ! empty( $GLOBALS['posts'] ); }
function the_post() { $GLOBALS['current'] = array_shift( $GLOBALS['posts'] ); }
function wc_get_template_part( $part, $name ) { ++$GLOBALS['wpdb']->num_queries; echo $GLOBALS['current'] . ";$part:$name;"; }

$theme = dirname( __DIR__ );
$source = file_get_contents( $theme . '/functions.php' );
$start = strpos( $source, 'function gstore_related_products_rank_by_relevance(' );
$end = strpos( $source, "\n}", $start );
eval( substr( $source, $start, $end + 2 - $start ) );
check( array( 2, 9 ) === gstore_related_products_rank_by_relevance( array( 1, 2, 8, 9 ), 1, array( 'limit' => 2 ) ), 'Ranking changed stock, visibility or exclusion.' );
$rank_reads_before_hit = $rank_reads;
check( array( 2, 9 ) === gstore_related_products_rank_by_relevance( array( 4 ), 1, array( 'limit' => 2 ) ), 'Hit ignored the cached ranking.' );
check( 2 === $reads && $rank_reads === $rank_reads_before_hit && array( array( 'gstore_related_rank_1_2', array( 2, 9 ), 6 * HOUR_IN_SECONDS ) ) === $writes, 'Cache key, TTL or invocation count changed.' );
check( array() === gstore_related_products_rank_by_relevance( array(), 5, array() ) && 1 === count( $writes ), 'Empty ranking changed result or wrote a transient.' );
$read_error = new RuntimeException( 'fixture' );
try {
	gstore_related_products_rank_by_relevance( array( 4 ), 7, array() );
	throw new RuntimeException( 'Cache read exception swallowed.' );
} catch ( RuntimeException $caught ) {
	check( $caught === $read_error, 'Cache read exception replaced.' );
}
ob_start();
require $theme . '/woocommerce/archive-product.php';
$html = ob_get_clean();
check( 'header:shop;<div class="Gstore-products-shell"><div class="Gstore-products-grid">start;hook;one;content:product;hook;two;content:product;end;</div></div>footer:shop;' === $html, 'Template markup, hook order or card count changed.' );

if ( 'current' === $mode ) {
	$rows = ( new ReflectionProperty( Gstore_Slow_Request_Diagnostics::class, 'catalog_sections' ) )->getValue();
	check( 4 === $rows['related_cache_read']['calls'] && 1 === $rows['related_cache_read']['cache_hits'] && 1 === $rows['related_cache_read']['interrupted'], 'Cache hit/miss/exception coverage incorrect.' );
	check( 2 === $rows['related_rank']['calls'] && 0 === $rows['related_rank']['interrupted'], 'Ranking must only run on misses, including the empty result.' );
	check( 2 === $rows['category_cards']['cards'] && 2 === $rows['category_cards']['db_queries'], 'Card measurements missed renders or counted extra queries.' );
}
echo "Catalog diagnostics $mode OK\n";

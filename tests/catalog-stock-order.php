<?php
/**
 * Regression checks for the actual theme callbacks and generated SQL.
 * Run: php -d extension=pdo_sqlite tests/catalog-stock-order.php
 * Uses an in-memory SQLite fixture; never connects to a store database.
 */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

$source = file_get_contents( $argv[1] ?? dirname( __DIR__ ) . '/functions.php' );
$checks = 0;
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$GLOBALS['checks'];
}
function load_callback( $name ) {
	$source = $GLOBALS['source'];
	$start  = strpos( $source, 'function ' . $name . '(' );
	check( false !== $start, 'Missing callback: ' . $name );
	$end = strpos( $source, "\n}", $start );
	eval( substr( $source, $start, $end + 2 - $start ) );
}

$state = array( 'page' => 'catalogo', 'admin' => false, 'legacy_featured' => false, 'orderby' => '', 'search' => '', 'home_queries' => array() );
function is_admin() { return $GLOBALS['state']['admin']; }
function is_front_page() { return 'home' === $GLOBALS['state']['page']; }
function is_page( $pages ) { return in_array( $GLOBALS['state']['page'], (array) $pages, true ); }
function is_page_template( $templates ) { return false; }
function is_shop() { return 'shop' === $GLOBALS['state']['page']; }
function is_product_taxonomy() { return in_array( $GLOBALS['state']['page'], array( 'category', 'brand' ), true ); }
function apply_filters( $name, $value ) { return $GLOBALS['state']['legacy_featured']; }
function gstore_catalog_has_requested_orderby() { return '' !== $GLOBALS['state']['orderby']; }
function gstore_get_catalog_search_request_term() { return $GLOBALS['state']['search']; }
function absint( $value ) { return abs( (int) $value ); }
function get_term_by( ...$args ) { return (object) array( 'term_taxonomy_id' => 10 ); }
function is_wp_error( $value ) { return false; }
function wc_get_product( $id ) {
	return isset( $GLOBALS['product_states'][ $id ] ) ? new class( $id ) {
		private $id;
		public function __construct( $id ) { $this->id = $id; }
		public function get_stock_status() { return $GLOBALS['product_states'][ $this->id ]; }
	} : false;
}
class WP_Query {
	public $vars;
	public $posts = array();
	public function __construct( $args = array() ) {
		$this->vars = $args;
		if ( isset( $args['meta_query'] ) ) {
			$GLOBALS['state']['home_queries'][] = $args;
			$filter = end( $args['meta_query'] );
			$this->posts = array_slice( $GLOBALS['home_ids'][ $filter['value'] ] ?? array(), 0, $args['posts_per_page'] );
		}
	}
	public function get( $key ) { return $this->vars[ $key ] ?? ''; }
	public function set( $key, $value ) { $this->vars[ $key ] = $value; }
}

foreach ( array( 'gstore_is_catalog_context', 'gstore_catalog_custom_sql_order_enabled', 'gstore_catalog_mark_shortcode_stock_priority', 'gstore_catalog_mark_main_query_stock_priority', 'gstore_catalog_order_by_stock_first', 'gstore_filter_home_products_by_stock' ) as $callback ) {
	load_callback( $callback );
}
// This fails against the pre-fix callbacks, before the new helper is required.
if ( false !== strpos( $source, 'function gstore_catalog_get_unavailable_campaign_ids(' ) ) {
	load_callback( 'gstore_catalog_get_unavailable_campaign_ids' );
}
$marked = gstore_catalog_mark_shortcode_stock_priority( array( 'post_type' => 'product' ), array(), 'products' );
check( 1 === ( $marked['gstore_instock_first'] ?? 0 ), 'Default catalog must prioritize stock even when legacy SQL ordering is disabled.' );
check( empty( $marked['gstore_featured_first'] ), 'Do not silently enable catalog featured ordering.' );
check( array() === gstore_catalog_get_unavailable_campaign_ids(), 'Theme works without the flash sale plugin.' );

class StockTestCampaign {
	public static $instance;
	public $campaign = array( 'active' => true, 'items' => array( array( 'product_id' => 3 ), array( 'product_id' => 6 ), array( 'product_id' => 999 ) ) );
	public static function get_instance() { return self::$instance; }
	public function get_campaign() { return $this->campaign; }
	public function is_campaign_active( $campaign ) { return $campaign['active']; }
}
class_alias( StockTestCampaign::class, 'GStore\\Services\\Flash_Sale_Service' );
check( array() === gstore_catalog_get_unavailable_campaign_ids(), 'Missing plugin instance is safe.' );
StockTestCampaign::$instance = new StockTestCampaign();
$product_states = array( 3 => 'outofstock', 6 => 'instock' );
check( array( 3 ) === gstore_catalog_get_unavailable_campaign_ids(), 'Effective exhausted campaign item is included.' );
StockTestCampaign::$instance->campaign['active'] = false;
check( array() === gstore_catalog_get_unavailable_campaign_ids(), 'Ended/future campaign does not demote products.' );
StockTestCampaign::$instance->campaign['active'] = true;

foreach ( array( 'catalogo', 'loja', 'ofertas', 'ofertas-relampago', 'shop', 'category', 'brand' ) as $page ) {
	$state['page'] = $page;
	foreach ( array( '', 'popularity', 'rating', 'date', 'price', 'price-desc' ) as $order ) {
		$state['orderby'] = $order;
		$args = array( 'post_type' => 'product', 'posts_per_page' => 15, 'paged' => 2, 'post__in' => array( 3, 6, 7 ) );
		$marked = gstore_catalog_mark_shortcode_stock_priority( $args, array(), 'products' );
		check( 1 === $marked['gstore_instock_first'], $page . '/' . $order . ': shortcode stock flag' );
		check( array( 3 ) === $marked['gstore_unavailable_campaign_ids'], 'Campaign state participates in shortcode cache arguments.' );
		check( $args === array_intersect_key( $marked, $args ), 'Pagination and product scope preserved.' );
		$query = new WP_Query( $args );
		gstore_catalog_mark_main_query_stock_priority( $query );
		check( 1 === $query->get( 'gstore_instock_first' ), $page . '/' . $order . ': main-query stock flag' );
	}
}
$state['page'] = 'catalogo';
$state['legacy_featured'] = true;
$state['orderby'] = 'price';
check( empty( gstore_catalog_mark_shortcode_stock_priority( array(), array(), 'products' )['gstore_featured_first'] ), 'Explicit ordering disables optional featured override.' );
$state['orderby'] = '';
$state['search'] = 'matching term';
check( empty( gstore_catalog_mark_shortcode_stock_priority( array(), array(), 'products' )['gstore_featured_first'] ), 'Search relevance is retained within stock groups.' );
$state['search'] = '';
$state['legacy_featured'] = false;

foreach ( array( 'checkout', 'cart', 'blog', 'product', 'home' ) as $page ) {
	$state['page'] = $page;
	$args = array( 'post_type' => 'product' );
	check( $args === gstore_catalog_mark_shortcode_stock_priority( $args, array(), 'products' ), 'Catalog callback excludes ' . $page );
}

// Execute the real ORDER BY, JOIN and pagination against an isolated fixture.
$db = new PDO( 'sqlite::memory:' );
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$db->sqliteCreateFunction( 'FIELD', static function ( $value, ...$ids ) {
	$index = array_search( $value, $ids );
	return false === $index ? 0 : $index + 1;
}, -1 );
$db->exec( 'CREATE TABLE shop_posts (ID INTEGER PRIMARY KEY, post_title TEXT, post_type TEXT, post_status TEXT, post_date TEXT)' );
$db->exec( 'CREATE TABLE shop_wc_product_meta_lookup (product_id INTEGER PRIMARY KEY, stock_status TEXT, total_sales INTEGER, min_price REAL, max_price REAL, average_rating REAL, rating_count INTEGER)' );
$db->exec( 'CREATE TABLE shop_term_relationships (object_id INTEGER, term_taxonomy_id INTEGER, PRIMARY KEY (object_id, term_taxonomy_id))' );
$insert_post = $db->prepare( 'INSERT INTO shop_posts VALUES (?, ?, ?, ?, ?)' );
$insert_lookup = $db->prepare( 'INSERT INTO shop_wc_product_meta_lookup VALUES (?, ?, ?, ?, ?, ?, ?)' );
for ( $id = 1; $id <= 30; ++$id ) {
	$insert_post->execute( array( $id, sprintf( 'Product %02d', $id ), 'product', 2 === $id ? 'draft' : 'publish', sprintf( '2026-09-%02d', $id ) ) );
	if ( 5 === $id ) { continue; } // Missing lookup must not hide the product.
	$status = 4 === $id ? 'onbackorder' : ( 1 === $id || $id > 25 ? 'outofstock' : 'instock' );
	$insert_lookup->execute( array( $id, $status, 1000 - $id, $id, $id + 0.5, 5 - $id / 100, 100 - $id ) );
}
$db->exec( 'INSERT INTO shop_term_relationships VALUES (1, 10), (2, 10), (3, 10), (8, 10)' );
$wpdb = (object) array( 'prefix' => 'shop_', 'posts' => 'shop_posts', 'postmeta' => 'shop_postmeta', 'term_relationships' => 'shop_term_relationships' );
$base = array( 'join' => '', 'orderby' => 'shop_posts.ID ASC', 'where' => "shop_posts.post_type = 'product' AND shop_posts.ID <> 30", 'limits' => 'LIMIT 15 OFFSET 0' );
function result_ids( $clauses ) {
	$sql = 'SELECT shop_posts.ID FROM shop_posts ' . $clauses['join'] . ' WHERE ' . $clauses['where'] . ' ORDER BY ' . $clauses['orderby'] . ' ' . $clauses['limits'];
	return array_map( 'intval', $GLOBALS['db']->query( $sql )->fetchAll( PDO::FETCH_COLUMN ) );
}
$native_join = ' LEFT JOIN shop_wc_product_meta_lookup wc_product_meta_lookup ON shop_posts.ID = wc_product_meta_lookup.product_id ';
$orders = array(
	'popularity' => array( 'wc_product_meta_lookup.total_sales DESC, wc_product_meta_lookup.product_id DESC', true ),
	'rating' => array( 'wc_product_meta_lookup.average_rating DESC, wc_product_meta_lookup.rating_count DESC, wc_product_meta_lookup.product_id DESC', true ),
	'price' => array( 'wc_product_meta_lookup.min_price ASC, wc_product_meta_lookup.product_id ASC', true ),
	'price-desc' => array( 'wc_product_meta_lookup.max_price DESC, wc_product_meta_lookup.product_id DESC', false ),
	'date' => array( 'shop_posts.post_date DESC, shop_posts.ID DESC', false ),
	'manual' => array( 'FIELD(shop_posts.ID,25,24,23,22,21,20,19,18,17,16,15,14,13,12,11,10,9,8,7,6) DESC', true ),
);
$state['page'] = 'catalogo';
$query = new WP_Query( array( 'post_type' => 'product', 'gstore_instock_first' => 1, 'gstore_unavailable_campaign_ids' => array( 3 ) ) );
foreach ( $orders as $name => list( $order, $ascending ) ) {
	$clauses = $base;
	$clauses['orderby'] = $order;
	if ( str_contains( $order, 'wc_product_meta_lookup.' ) ) { $clauses['join'] = $native_join; }
	$filtered = gstore_catalog_order_by_stock_first( $clauses, $query );
	check( $clauses['where'] === $filtered['where'] && $clauses['limits'] === $filtered['limits'], $name . ': filters/limits intact' );
	check( ! str_contains( $filtered['join'], 'postmeta' ), $name . ': no postmeta joins' );
	if ( $clauses['join'] ) { check( $native_join === $filtered['join'], $name . ': native lookup join is reused' ); }
	$expected = $ascending ? range( 6, 25 ) : range( 25, 6 );
	check( array_slice( $expected, 0, 15 ) === result_ids( $filtered ), $name . ': first page contains available products in chosen order' );
	$filtered['limits'] = 'LIMIT 15 OFFSET 15';
	check( array_slice( $expected, 15 ) === array_slice( result_ids( $filtered ), 0, 5 ), $name . ': remaining available items lead the second page' );
	$filtered['limits'] = '';
	$all = result_ids( $filtered );
	check( 29 === count( $all ) && 29 === count( array_unique( $all ) ), $name . ': no loss/duplication, including a missing lookup row' );
	check( array_search( 4, $all ) < array_search( 1, $all ), $name . ': backorders precede unavailable' );
	check( array_search( 2, $all ) >= 22 && array_search( 3, $all ) >= 22, $name . ': drafts and exhausted campaign follow available/backorders' );
}

$featured_query = new WP_Query( array_merge( $query->vars, array( 'gstore_featured_first' => 1 ) ) );
$featured = gstore_catalog_order_by_stock_first( $base, $featured_query );
$first = result_ids( $featured );
check( 8 === $first[0] && ! array_intersect( array( 1, 2, 3 ), $first ), 'Featured unavailable products cannot precede available products.' );
check( ! str_contains( $featured['join'], 'postmeta' ), 'Featured sales use lookup too.' );
$again = gstore_catalog_order_by_stock_first( $featured, $featured_query );
check( $featured['join'] === $again['join'], 'Repeated filter does not duplicate joins.' );

// Availability transitions must be visible on the next query; no new long-lived cache.
$db->exec( "UPDATE shop_wc_product_meta_lookup SET stock_status = 'outofstock' WHERE product_id = 6" );
$product_states[3] = 'instock';
$after = gstore_catalog_mark_shortcode_stock_priority( array( 'post_type' => 'product' ), array(), 'products' );
check( array() === $after['gstore_unavailable_campaign_ids'], 'Restored campaign stock changes query/cache arguments.' );
$updated = gstore_catalog_order_by_stock_first( $base, new WP_Query( $after ) );
$updated['limits'] = '';
$ids = result_ids( $updated );
check( 3 === $ids[0] && array_search( 6, $ids ) > array_search( 25, $ids ), 'Restock and stock exhaustion change global order.' );
$db->exec( "UPDATE shop_wc_product_meta_lookup SET stock_status = 'instock' WHERE product_id = 6" );

// Home fallback keeps its size and places its available IDs first.
$state['page'] = 'home';
$home_ids = array( 'instock' => array( 6, 7 ), 'outofstock' => array( 1, 26 ) );
$home = gstore_filter_home_products_by_stock( array( 'post_type' => 'product', 'posts_per_page' => 8 ) );
check( array( 6, 7, 1, 26 ) === $home['post__in'], 'Home fallback selection preserved.' );
check( 1 === $home['gstore_instock_first'], 'Home render also prioritizes stock.' );
foreach ( $state['home_queries'] as $precheck ) {
	check( empty( $precheck['gstore_instock_first'] ) && empty( $precheck['gstore_featured_first'] ), 'Home prechecks do not inherit sorting joins.' );
}
$home_base = $base;
$home_base['where'] = 'shop_posts.ID IN (6,7,1,26)';
$home_base['orderby'] = 'FIELD(shop_posts.ID,6,7,1,26)';
check( array( 6, 7, 1, 26 ) === result_ids( gstore_catalog_order_by_stock_first( $home_base, new WP_Query( $home ) ) ), 'Home featured fallback stays behind available items.' );

$state['admin'] = true;
check( $base === gstore_catalog_order_by_stock_first( $base, $query ), 'Admin queries unchanged.' );
$state['admin'] = false;
check( $base === gstore_catalog_order_by_stock_first( $base, new WP_Query( array( 'post_type' => 'product' ) ) ), 'Unmarked related/internal queries unchanged.' );
check( $base === gstore_catalog_order_by_stock_first( $base, new WP_Query( array( 'post_type' => 'post', 'gstore_instock_first' => 1 ) ) ), 'Blog queries unchanged.' );
echo 'PASS: ' . $checks . ' assertions; catalog/category/brand, six orderings, pagination, featured, home, campaign and stock transitions.' . PHP_EOL;

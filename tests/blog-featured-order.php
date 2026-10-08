<?php
/** Run with: php tests/blog-featured-order.php (no WordPress connection). */
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
function load_blog_callback( $name ) {
	$source = $GLOBALS['source'];
	$start  = strpos( $source, 'function ' . $name . '(' );
	if ( false === $start ) {
		throw new RuntimeException( "Missing callback: {$name}" );
	}
	$end = strpos( $source, "\n}", $start );
	eval( substr( $source, $start, $end + 2 - $start ) );
}
function check_blog( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function is_admin() {
	return $GLOBALS['admin'];
}
class WP_Query {
	public $vars;
	public $context;
	public function __construct( $context, $vars = array() ) {
		$this->context = $context;
		$this->vars = $vars;
	}
	public function is_main_query() { return true; }
	public function is_home() { return 'home' === $this->context; }
	public function is_category() { return 'category' === $this->context; }
	public function is_tag() { return 'tag' === $this->context; }
	public function is_author() { return 'author' === $this->context; }
	public function is_date() { return 'date' === $this->context; }
	public function get( $key ) { return $this->vars[ $key ] ?? null; }
	public function set( $key, $value ) { $this->vars[ $key ] = $value; }
}
class WP_Block {
	public $parsed_block;
	public function __construct( $class_name ) {
		$this->parsed_block = array( 'attrs' => array( 'className' => $class_name ) );
	}
}
class Blog_Wpdb {
	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public function prepare( $sql, $key ) {
		return str_replace( '%s', "'" . $key . "'", $sql );
	}
}

$admin = false;
$wpdb  = new Blog_Wpdb();
load_blog_callback( 'gstore_blog_featured_main_query' );
load_blog_callback( 'gstore_blog_featured_block_query' );
load_blog_callback( 'gstore_blog_featured_posts_orderby' );

foreach ( array( 'home', 'category', 'tag', 'author', 'date' ) as $context ) {
	$query = new WP_Query( $context );
	gstore_blog_featured_main_query( $query );
	check_blog( true === $query->get( 'gstore_featured_articles_first' ), "Missing blog flag for {$context}" );
	check_blog( true === $query->get( 'ignore_sticky_posts' ), "Sticky posts could override {$context} order" );
}
$product = new WP_Query( 'home', array( 'post_type' => 'product' ) );
gstore_blog_featured_main_query( $product );
check_blog( null === $product->get( 'gstore_featured_articles_first' ), 'Product order changed' );

foreach ( array( 'parts/home-blog.html', 'templates/home.html', 'templates/page-blog.html' ) as $template ) {
	$markup = file_get_contents( dirname( __DIR__ ) . '/' . $template );
	check_blog( 1 === preg_match( '/<!-- wp:post-template (\{[^\n]*\}) -->/', $markup, $matches ), "Missing post template in {$template}" );
	$attrs = json_decode( $matches[1], true );
	$block_query = gstore_blog_featured_block_query( array( 'post_type' => 'post' ), new WP_Block( $attrs['className'] ?? '' ) );
	check_blog( true === ( $block_query['gstore_featured_articles_first'] ?? false ), "Featured order missing in {$template}" );
}
check_blog( array() === gstore_blog_featured_block_query( array(), new WP_Block( 'Gstore-home-blog__query' ) ), 'Parent query class should not trigger the post template filter' );
check_blog( array() === gstore_blog_featured_block_query( array(), new WP_Block( 'other-query' ) ), 'Unrelated block changed' );

$orderby = gstore_blog_featured_posts_orderby( 'original', new WP_Query( 'home', array( 'gstore_featured_articles_first' => true ) ) );
check_blog( str_starts_with( $orderby, 'COALESCE(' ), 'Featured rank must be first' );
check_blog( str_contains( $orderby, "'_gstore_blog_featured_at'" ), 'Activation metadata missing from order' );
check_blog( str_contains( $orderby, 'wp_posts.post_date DESC, wp_posts.ID DESC' ), 'Regular posts need stable date order' );
check_blog( 'original' === gstore_blog_featured_posts_orderby( 'original', $product ), 'Product order changed' );

echo "Blog featured query checks passed.\n";

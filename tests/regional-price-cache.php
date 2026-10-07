<?php
namespace GStore\Services {
    class Regional_Pricing_Service {
        public static $enabled = false;
        public static $state = 'SP';
        public static function enabled() { return self::$enabled; }
        public static function instance() { return new self(); }
        public function current_state() { return self::$state; }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    $options = array();
    function add_action(...$args) {}
    function add_filter(...$args) {}
    function get_option($key,$default='') { return $GLOBALS['options'][$key] ?? $default; }
    function update_option($key,$value,...$args) { $GLOBALS['options'][$key] = $value; }
    function wp_json_encode($value) { return json_encode($value); }
    function wp_generate_uuid4() { return 'synthetic-cache-revision'; }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    require __DIR__ . '/../inc/gstore-regional-pricing.php';
    use GStore\Services\Regional_Pricing_Service as Pricing;
    check(gstore_regional_price_cache_key('search')==='search', 'disabled uses previous cache');
    Pricing::$enabled=true;
    $sp=gstore_regional_price_cache_key('search');
    Pricing::$state='PR';
    check($sp!==gstore_regional_price_cache_key('search'), 'UFs must not share cached prices');
    Pricing::$state='SP';
    check($sp===gstore_regional_price_cache_key('search'), 'same UF reuses cache');
    gstore_regional_invalidate_display_prices();
    check($sp!==gstore_regional_price_cache_key('search'), 'stock/product update invalidates fragment');
    $sp=gstore_regional_price_cache_key('search');
    $options['gstore_regional_pricing_revision']='changed-price-table';
    check($sp!==gstore_regional_price_cache_key('search'), 'regional rule update invalidates fragment');
    Pricing::$enabled=false;
    check(gstore_regional_price_cache_key('search')==='search', 'disabled restores general cache key');
    echo "Regional fragments: 6 assertions passed.\n";
}

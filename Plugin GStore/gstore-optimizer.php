<?php
/**
 * Plugin Name: GStore Optimizer
 * Plugin URI: https://armastore.com.br
 * Description: Plugin de otimização de performance para o tema GStore. Implementa conversão WebP, cache headers, minificação de assets e lazy loading avançado.
 * Version: 1.0.0
 * Author: GStore Team
 * Author URI: https://armastore.com.br
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gstore-optimizer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * 
 * @package GStore_Optimizer
 */

// Previne acesso direto
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constantes do plugin
define( 'GSTORE_OPTIMIZER_VERSION', '1.0.0' );
define( 'GSTORE_OPTIMIZER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSTORE_OPTIMIZER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GSTORE_OPTIMIZER_PLUGIN_FILE', __FILE__ );

/**
 * Classe principal do plugin GStore Optimizer
 */
class GStore_Optimizer {

	/**
	 * Instância única do plugin (Singleton)
	 *
	 * @var GStore_Optimizer
	 */
	private static $instance = null;

	/**
	 * Instância do otimizador de imagens
	 *
	 * @var GStore_Image_Optimizer
	 */
	public $image_optimizer;

	/**
	 * Instância do gerenciador de cache headers
	 *
	 * @var GStore_Cache_Headers
	 */
	public $cache_headers;

	/**
	 * Instância do otimizador de assets
	 *
	 * @var GStore_Asset_Optimizer
	 */
	public $asset_optimizer;

	/**
	 * Instância do lazy loading avançado
	 *
	 * @var GStore_Lazy_Loading
	 */
	public $lazy_loading;

	/**
	 * Instância do otimizador de scripts
	 *
	 * @var GStore_Script_Optimizer
	 */
	public $script_optimizer;

	/**
	 * Retorna instância única do plugin (Singleton)
	 *
	 * @return GStore_Optimizer
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor privado (Singleton)
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init();
	}

	/**
	 * Instância da classe admin
	 *
	 * @var GStore_Optimizer_Admin
	 */
	public $admin;

	/**
	 * Carrega dependências do plugin
	 */
	private function load_dependencies() {
		$includes_dir = GSTORE_OPTIMIZER_PLUGIN_DIR . 'includes/';
		
		// Carrega classes na ordem correta
		if ( file_exists( $includes_dir . 'class-image-optimizer.php' ) ) {
			require_once $includes_dir . 'class-image-optimizer.php';
		}
		if ( file_exists( $includes_dir . 'class-cache-headers.php' ) ) {
			require_once $includes_dir . 'class-cache-headers.php';
		}
		if ( file_exists( $includes_dir . 'class-asset-optimizer.php' ) ) {
			require_once $includes_dir . 'class-asset-optimizer.php';
		}
		if ( file_exists( $includes_dir . 'class-lazy-loading.php' ) ) {
			require_once $includes_dir . 'class-lazy-loading.php';
		}
		if ( file_exists( $includes_dir . 'class-script-optimizer.php' ) ) {
			require_once $includes_dir . 'class-script-optimizer.php';
		}
		if ( file_exists( $includes_dir . 'class-admin.php' ) ) {
			require_once $includes_dir . 'class-admin.php';
		}
	}

	/**
	 * Inicializa o plugin
	 */
	private function init() {
		// Verifica se as classes foram carregadas corretamente
		if ( ! class_exists( 'GStore_Image_Optimizer' ) || 
		     ! class_exists( 'GStore_Asset_Optimizer' ) || 
		     ! class_exists( 'GStore_Lazy_Loading' ) || 
		     ! class_exists( 'GStore_Script_Optimizer' ) ) {
			return; // Não inicializa se classes não foram carregadas
		}
		
		// Inicializa interface de administração
		if ( is_admin() && class_exists( 'GStore_Optimizer_Admin' ) ) {
			$this->admin = new GStore_Optimizer_Admin();
		}

		// Verifica se está em modo de desenvolvimento
		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$is_script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

		// Inicializa otimizador de imagens (sempre ativo, mas pode ser desabilitado via filtro ou opção)
		if ( $this->is_feature_enabled( 'webp_conversion' ) ) {
			$this->image_optimizer = new GStore_Image_Optimizer();
		}

		// Inicializa cache headers (apenas em produção)
		if ( ! $is_debug && $this->is_feature_enabled( 'cache_headers' ) && class_exists( 'GStore_Cache_Headers' ) ) {
			$this->cache_headers = new GStore_Cache_Headers();
		}

		// Inicializa minificação (apenas em produção)
		if ( ! $is_script_debug && $this->is_feature_enabled( 'minify_assets' ) ) {
			$this->asset_optimizer = new GStore_Asset_Optimizer();
		}

		// Inicializa lazy loading avançado (sempre ativo, mas pode ser desabilitado via filtro ou opção)
		if ( $this->is_feature_enabled( 'lazy_loading' ) ) {
			$this->lazy_loading = new GStore_Lazy_Loading();
		}

		// Inicializa otimizador de scripts (sempre ativo, mas pode ser desabilitado via filtro ou opção)
		if ( $this->is_feature_enabled( 'script_optimization' ) ) {
			$this->script_optimizer = new GStore_Script_Optimizer();
		}

		// Hooks de ativação/desativação
		register_activation_hook( GSTORE_OPTIMIZER_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( GSTORE_OPTIMIZER_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Verifica se uma feature está habilitada
	 *
	 * @param string $feature Nome da feature
	 * @return bool
	 */
	private function is_feature_enabled( $feature ) {
		// Mapeia nomes de features para opções
		$option_map = array(
			'webp_conversion'     => 'gstore_optimizer_webp_conversion',
			'cache_headers'       => 'gstore_optimizer_cache_headers',
			'minify_assets'       => 'gstore_optimizer_minify_assets',
			'lazy_loading'        => 'gstore_optimizer_lazy_loading',
			'script_optimization' => 'gstore_optimizer_script_optimization',
		);

		$defaults = array(
			'webp_conversion'     => true,
			'cache_headers'       => true,
			'minify_assets'       => true,
			'lazy_loading'        => true,
			'script_optimization' => true,
		);

		// Verifica opção do banco de dados primeiro
		if ( isset( $option_map[ $feature ] ) ) {
			$option_value = get_option( $option_map[ $feature ], $defaults[ $feature ] ?? true );
			$enabled = (bool) $option_value;
		} else {
			$enabled = $defaults[ $feature ] ?? true;
		}

		// Permite filtro para sobrescrever
		$enabled = apply_filters( "gstore_optimizer_enable_{$feature}", $enabled );
		return (bool) $enabled;
	}

	/**
	 * Ativação do plugin
	 */
	public function activate() {
		// Cria diretório para cache de imagens WebP se não existir
		$upload_dir = wp_upload_dir();
		$webp_dir = $upload_dir['basedir'] . '/gstore-webp';
		
		if ( ! file_exists( $webp_dir ) ) {
			wp_mkdir_p( $webp_dir );
			// Cria arquivo .htaccess para proteger o diretório
			file_put_contents( $webp_dir . '/.htaccess', "Options -Indexes\n" );
		}

		// Cria diretório para cache de assets minificados se não existir
		$cache_dir = $upload_dir['basedir'] . '/gstore-optimizer-cache';
		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
			// Cria arquivo .htaccess para proteger o diretório
			file_put_contents( $cache_dir . '/.htaccess', "Options -Indexes\n" );
		}

		// Limpa cache de transients relacionados
		delete_transient( 'gstore_optimizer_webp_stats' );
	}

	/**
	 * Desativação do plugin
	 */
	public function deactivate() {
		// Limpa cache de transients
		delete_transient( 'gstore_optimizer_webp_stats' );
	}
}

/**
 * Inicializa o plugin
 */
function gstore_optimizer_init() {
	return GStore_Optimizer::get_instance();
}

// Inicializa o plugin após plugins carregados
add_action( 'plugins_loaded', 'gstore_optimizer_init', 10 );


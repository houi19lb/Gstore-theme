<?php
/**
 * Classe para otimização de scripts JavaScript
 *
 * @package GStore_Optimizer
 */

// Previne acesso direto
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe GStore_Script_Optimizer
 */
class GStore_Script_Optimizer {

	/**
	 * Construtor
	 */
	public function __construct() {
		// Aplica defer/async em scripts não críticos
		add_filter( 'script_loader_tag', array( $this, 'optimize_script_loading' ), 10, 3 );
		
		// Adiciona script para passive event listeners
		add_action( 'wp_footer', array( $this, 'add_passive_listeners_script' ), 1 );
		
		// Remove scripts legacy desnecessários (via filtro)
		add_action( 'wp_enqueue_scripts', array( $this, 'remove_legacy_scripts' ), 100 );
	}

	/**
	 * Otimiza carregamento de scripts (defer/async)
	 *
	 * @param string $tag Tag HTML do script
	 * @param string $handle Handle do script
	 * @param string $src URL do script
	 * @return string
	 */
	public function optimize_script_loading( $tag, $handle, $src ) {
		// Não aplica em desenvolvimento
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return $tag;
		}
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return $tag;
		}

		// Se já tem defer ou async, não modifica
		if ( strpos( $tag, ' defer' ) !== false || strpos( $tag, ' async' ) !== false ) {
			return $tag;
		}

		// Scripts que podem ser deferidos (não críticos para renderização inicial)
		$defer_scripts = array(
			'gstore-home-benefits',
			'gstore-home-products-carousel',
			'gstore-product-card',
			'gstore-my-account',
			'woocommerce-cart-fragments', // Pode ser deferido se não estiver na página de carrinho
			'wc-cart-fragments',
		);

		// Scripts que podem usar async (não dependem de outros e não são críticos)
		$async_scripts = array(
			'gstore-analytics', // Se houver
		);

		// Aplica defer
		if ( in_array( $handle, $defer_scripts, true ) ) {
			// Verifica contexto - alguns scripts só devem ser deferidos em certas páginas
			if ( 'woocommerce-cart-fragments' === $handle || 'wc-cart-fragments' === $handle ) {
				// Não defer na página de carrinho ou checkout
				if ( function_exists( 'is_cart' ) && function_exists( 'is_checkout' ) ) {
					if ( is_cart() || is_checkout() ) {
						return $tag;
					}
				}
			}

			// Adiciona defer antes do fechamento da tag
			if ( strpos( $tag, '></script>' ) !== false ) {
				$tag = str_replace( '></script>', ' defer></script>', $tag );
			} elseif ( strpos( $tag, '/>' ) !== false ) {
				$tag = str_replace( '/>', ' defer />', $tag );
			}
		}

		// Aplica async
		if ( in_array( $handle, $async_scripts, true ) ) {
			if ( strpos( $tag, '></script>' ) !== false ) {
				$tag = str_replace( '></script>', ' async></script>', $tag );
			} elseif ( strpos( $tag, '/>' ) !== false ) {
				$tag = str_replace( '/>', ' async />', $tag );
			}
		}

		return $tag;
	}

	/**
	 * Adiciona script para passive event listeners
	 * 
	 * Melhora performance de scroll e touch events adicionando passive: true
	 */
	public function add_passive_listeners_script() {
		?>
		<script>
		(function() {
			// Wrapper para adicionar passive listeners automaticamente
			var originalAddEventListener = EventTarget.prototype.addEventListener;
			
			EventTarget.prototype.addEventListener = function(type, listener, options) {
				// Eventos que se beneficiam de passive listeners
				var passiveEvents = ['touchstart', 'touchmove', 'touchend', 'touchcancel', 'wheel', 'mousewheel'];
				
				if (passiveEvents.indexOf(type) !== -1) {
					// Se options é um objeto, adiciona passive
					if (typeof options === 'object' && options !== null) {
						if (options.passive === undefined) {
							options.passive = true;
						}
					} else if (options === undefined || options === false) {
						// Se options não foi passado ou é false, cria objeto com passive
						options = { passive: true };
					}
				}
				
				return originalAddEventListener.call(this, type, listener, options);
			};
		})();
		</script>
		<?php
	}

	/**
	 * Remove scripts legacy desnecessários
	 */
	public function remove_legacy_scripts() {
		// Remove polyfills desnecessários para navegadores modernos
		// Verifica User-Agent para determinar se é navegador moderno
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return;
		}

		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		
		// Navegadores modernos não precisam de muitos polyfills
		$is_modern_browser = (
			strpos( $user_agent, 'Chrome/' ) !== false ||
			strpos( $user_agent, 'Firefox/' ) !== false ||
			strpos( $user_agent, 'Safari/' ) !== false ||
			strpos( $user_agent, 'Edge/' ) !== false
		);

		if ( $is_modern_browser ) {
			// Lista de scripts legacy que podem ser removidos
			$legacy_scripts = apply_filters( 'gstore_optimizer_legacy_scripts', array(
				// Adicione handles de scripts legacy aqui se necessário
				// Exemplo: 'ie8-polyfill', 'old-browser-support'
			) );

			foreach ( $legacy_scripts as $script_handle ) {
				wp_deregister_script( $script_handle );
				wp_dequeue_script( $script_handle );
			}
		}
	}
}

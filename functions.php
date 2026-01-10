<?php
/**
 * Funções principais do child theme Gstore.
 *
 * @package Gstore
 * 
 * ============================================
 * CONFIGURAÇÃO DO WOOCOMMERCE
 * ============================================
 * Sistema: Blocos Gutenberg (Product Collection)
 * Versão WooCommerce: 9.4.0+
 * Verificado em: 2025-11-15
 * 
 * IMPORTANTE:
 * - Este projeto usa BLOCOS do WooCommerce, não loop clássico
 * - Páginas criadas no Editor de Blocos (Gutenberg)
 * - Templates PHP clássicos (content-product.php) NÃO são usados
 * - Customizações de produtos via CSS (.wc-block-*)
 * - Estilos críticos inline via wp_head (linhas 140-224)
 * 
 * ARQUIVOS RELEVANTES:
 * - style.css (linhas 473-671) - Estilos para blocos
 * - functions.php (linhas 140-224) - Estilos críticos inline
 * - BLOCOS-WOOCOMMERCE.md - Documentação completa
 * ============================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configurações iniciais do tema filho.
 */
function gstore_after_setup_theme() {
	load_child_theme_textdomain( 'gstore', get_stylesheet_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );

	// WooCommerce.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	
	// Tamanho de imagem específico para banners (alta qualidade, sem crop)
	// Usa dimensões grandes mas sem forçar crop, permitindo que a imagem original seja usada
	add_image_size( 'gstore-banner-full', 2560, 1440, false );
}
add_action( 'after_setup_theme', 'gstore_after_setup_theme' );

/**
 * Desativa o crop das imagens de produto do WooCommerce.
 * Garante que as miniaturas mantenham a proporção original.
 */

// 1. Forçar configuração de crop no WooCommerce (banco de dados)
function gstore_set_woocommerce_image_settings() {
	// Desativa crop para thumbnails (usa 'uncropped' que significa sem corte)
	if ( get_option( 'woocommerce_thumbnail_cropping' ) !== 'uncropped' ) {
		update_option( 'woocommerce_thumbnail_cropping', 'uncropped' );
	}
}
add_action( 'after_setup_theme', 'gstore_set_woocommerce_image_settings', 20 );

// 2. Filtro para woocommerce_thumbnail (loop de produtos)
function gstore_thumbnail_size( $size ) {
	return array(
		'width'  => 300,
		'height' => 9999,
		'crop'   => false,
	);
}
add_filter( 'woocommerce_get_image_size_thumbnail', 'gstore_thumbnail_size' );

// 3. Filtro para woocommerce_gallery_thumbnail (galeria de produto)
function gstore_gallery_thumbnail_size( $size ) {
	return array(
		'width'  => 100,
		'height' => 9999,
		'crop'   => false,
	);
}
add_filter( 'woocommerce_get_image_size_gallery_thumbnail', 'gstore_gallery_thumbnail_size' );

// 4. Filtro para woocommerce_single (imagem principal do produto)
function gstore_single_size( $size ) {
	return array(
		'width'  => 600,
		'height' => 9999,
		'crop'   => false,
	);
}
add_filter( 'woocommerce_get_image_size_single', 'gstore_single_size' );

// 5. Atualizar opções do banco de dados (executa apenas uma vez para garantir robustez)
function gstore_force_woocommerce_image_options() {
	// Executa apenas uma vez (ou quando necessário)
	if ( get_option( 'gstore_image_options_set' ) === 'yes' ) {
		return;
	}
	
	// Opção principal de crop
	update_option( 'woocommerce_thumbnail_cropping', 'uncropped' );
	
	// Opções de tamanho (formato antigo, ainda respeitado)
	update_option( 'woocommerce_thumbnail_image_width', 300 );
	update_option( 'woocommerce_single_image_width', 600 );
	
	// Marca como configurado
	update_option( 'gstore_image_options_set', 'yes' );
}
add_action( 'init', 'gstore_force_woocommerce_image_options' );

/**
 * Adiciona resource hints (preconnect, dns-prefetch) para melhorar performance.
 * 
 * Adiciona preconnect para CDNs e recursos externos para reduzir latência
 * na primeira conexão. Isso pode economizar ~300ms no tempo de carregamento.
 */
function gstore_add_resource_hints() {
	// Preconnect para FontAwesome CDN (prioridade alta)
	echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
	
	// DNS-prefetch para outras origens externas (menos crítico)
	echo '<link rel="dns-prefetch" href="https://upload.wikimedia.org">' . "\n";
	echo '<link rel="dns-prefetch" href="https://secure.gravatar.com">' . "\n";
}
add_action( 'wp_head', 'gstore_add_resource_hints', 1 );

/**
 * Adiciona font-display: swap para FontAwesome para melhorar FCP.
 * 
 * Isso garante que o texto seja visível imediatamente enquanto as fontes carregam,
 * evitando FOIT (Flash of Invisible Text) e melhorando a percepção de velocidade.
 */
function gstore_fontawesome_font_display() {
	?>
	<style id="gstore-fontawesome-font-display">
		/* Force font-display: swap for FontAwesome webfonts */
		@font-face {
			font-family: 'Font Awesome 6 Brands';
			font-style: normal;
			font-weight: 400;
			font-display: swap;
		}
		@font-face {
			font-family: 'Font Awesome 6 Free';
			font-style: normal;
			font-weight: 400;
			font-display: swap;
		}
		@font-face {
			font-family: 'Font Awesome 6 Free';
			font-style: normal;
			font-weight: 900;
			font-display: swap;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'gstore_fontawesome_font_display', 2 );

/**
 * Adiciona preload para Font Awesome CSS para melhorar performance.
 * 
 * Preload permite que o navegador baixe o recurso com prioridade alta
 * sem bloquear a renderização inicial.
 */
function gstore_preload_fontawesome() {
	?>
	<link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
	<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>
	<?php
}
add_action( 'wp_head', 'gstore_preload_fontawesome', 1 );

/**
 * Inline CSS crítico acima da dobra (header e hero básico).
 * 
 * Isso reduz render blocking ao colocar estilos essenciais diretamente no HTML,
 * permitindo que o header e hero sejam renderizados imediatamente.
 */
function gstore_inline_critical_css() {
	// CSS crítico mínimo para renderização inicial do header e hero
	$critical_css = '
		/* Reset header */
		header.Gstore-header-shell,
		.Gstore-header-shell {
			width: 100% !important;
			max-width: none !important;
			margin: 0 !important;
			padding: 0 !important;
		}
		
		/* Top bar básico */
		.Gstore-top-bar {
			background-color: #0a0a0a;
			color: #fff;
			font-size: 14px;
		}
		
		.Gstore-top-bar__inner {
			max-width: 1280px;
			margin: 0 auto;
			padding: 4px 20px;
			display: flex;
			flex-wrap: wrap;
			justify-content: space-between;
			align-items: center;
			gap: 16px;
		}
		
		.Gstore-top-bar__link {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			color: #fff;
			text-decoration: none;
		}
		
		/* Header principal básico */
		.Gstore-header {
			background-color: #0a0a0a;
		}
		
		.Gstore-header__inner {
			max-width: 1280px;
			margin: 0 auto;
			padding: 6px 20px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 16px;
		}
		
		.Gstore-header__logo {
			color: #fff;
			text-decoration: none;
			font-weight: bold;
			font-size: 20px;
		}
		
		/* Hero slider básico */
		.Gstore-hero-slider {
			position: relative;
			width: 100%;
			overflow: hidden;
		}
		
		.Gstore-hero-slider__track {
			display: flex;
			transition: transform 0.5s ease;
		}
		
		.Gstore-hero-slider__slide {
			min-width: 100%;
			margin: 0;
			padding: 0;
		}
		
		.Gstore-hero-slider__slide img {
			width: 100%;
			height: auto;
			display: block;
		}
	';
	
	// Minifica o CSS crítico (remove espaços extras)
	$critical_css = preg_replace( '/\s+/', ' ', $critical_css );
	$critical_css = str_replace( array( '; ', ' {', '{ ', ' }', '} ', ': ' ), array( ';', '{', '{', '}', '}', ':' ), $critical_css );
	$critical_css = trim( $critical_css );
	
	?>
	<style id="gstore-critical-css">
		<?php echo $critical_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</style>
	<?php
}
add_action( 'wp_head', 'gstore_inline_critical_css', 3 );

/**
 * Adiciona preload para recursos críticos (imagens hero, fontes, etc).
 * 
 * Preload ajuda o navegador a priorizar recursos críticos,
 * melhorando LCP e FCP.
 */
function gstore_add_preload_resources() {
	// Preload da primeira imagem do hero (se disponível)
	if ( function_exists( 'gstore_get_hero_slide_1_id' ) ) {
		$hero_slide_1_id = gstore_get_hero_slide_1_id();
		if ( $hero_slide_1_id > 0 ) {
			$hero_url = wp_get_attachment_url( $hero_slide_1_id );
			if ( $hero_url ) {
				// Verifica se existe versão WebP
				$webp_url = str_replace( array( '.jpg', '.jpeg', '.png' ), '.webp', $hero_url );
				if ( file_exists( str_replace( home_url(), ABSPATH . '../', $webp_url ) ) ) {
					$hero_url = $webp_url;
				}
				echo '<link rel="preload" as="image" href="' . esc_url( $hero_url ) . '" fetchpriority="high">' . "\n";
			}
		}
	}

	// Preload de fontes críticas (se necessário)
	$critical_fonts = apply_filters( 'gstore_critical_fonts', array() );
	foreach ( $critical_fonts as $font_url ) {
		echo '<link rel="preload" as="font" href="' . esc_url( $font_url ) . '" crossorigin>' . "\n";
	}

	// Preload do CSS crítico do tema pai (se necessário)
	if ( is_front_page() ) {
		$parent_theme = wp_get_theme( 'twentytwentyfive' );
		if ( $parent_theme->exists() ) {
			$parent_css = get_template_directory_uri() . '/style.css';
			echo '<link rel="preload" as="style" href="' . esc_url( $parent_css ) . '">' . "\n";
		}
	}
}
add_action( 'wp_head', 'gstore_add_preload_resources', 1 );

/**
 * Expandida lista de CSS não crítico que pode ser deferido.
 * 
 * Adiciona mais CSS à lista de defer, incluindo layouts e componentes
 * que não são necessários para renderização inicial.
 */
function gstore_defer_non_critical_css( $tag, $handle, $href, $media ) {
	// Não aplica em modo de desenvolvimento para facilitar debug
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return $tag;
	}
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		return $tag;
	}

	// Lista expandida de CSS não crítico que pode ser deferido
	$non_critical_css = array(
		// Font Awesome - não crítico para renderização inicial (ícones podem carregar depois)
		'gstore-fontawesome',
		
		// CSS de páginas específicas
		'gstore-my-account-css',
		'gstore-como-comprar-arma-css',
		'gstore-notices-css',
		
		// CSS de layouts que não estão acima da dobra
		'gstore-header-css',      // Já inlinado como crítico, pode defer o resto
		
		// CSS de componentes não críticos
		'gstore-product-card-css', // Não está acima da dobra na home
		
		// CSS do WooCommerce que não é crítico
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'woocommerce-blocktheme',
		'wc-blocks-style',
		'wc-blocks-editor-style',
		'blocks-mini-cart-css',
		'blocks-customer-account-css',
		'blocks-packages-style-css',
		'blocks-mini-cart-contents-css',
		'woocommerce-general',    // CSS geral do WooCommerce
		'woocommerce-inline',     // CSS inline do WooCommerce
		
		// CSS de layouts específicos
		'gstore-home-css',         // Pode defer se não for home ou se hero já foi renderizado
	);

	// CSS de home que só deve ser deferido se não for a home page
	if ( 'gstore-home-css' === $handle && is_front_page() ) {
		// Não defer na home, mas pode ser otimizado de outra forma
		return $tag;
	}

	// CSS de header - defer apenas partes não críticas (já tem inline crítico)
	if ( 'gstore-header-css' === $handle ) {
		// Defer apenas se não for a primeira carga (já tem inline)
		// Na prática, pode defer pois já temos CSS crítico inline
	}

	// Verifica se é CSS não crítico
	if ( ! in_array( $handle, $non_critical_css, true ) ) {
		return $tag;
	}

	// Verifica se já tem defer/preload
	if ( strpos( $tag, 'onload=' ) !== false || strpos( $tag, 'media="print"' ) !== false ) {
		return $tag;
	}

	// Aplica técnica de defer usando JavaScript
	// Troca media para print e depois muda para all quando carregar
	// Suporta tanto aspas simples quanto duplas
	if ( strpos( $tag, "media='" ) !== false ) {
		$deferred_tag = str_replace(
			"media='{$media}'",
			"media='print' onload=\"this.media='{$media}'\"",
			$tag
		);
		$noscript_tag = str_replace( "media='print'", "media='{$media}'", $tag );
	} else {
		$deferred_tag = str_replace(
			'media="' . $media . '"',
			'media="print" onload="this.media=\'' . $media . '\'"',
			$tag
		);
		$noscript_tag = str_replace( 'media="print"', 'media="' . $media . '"', $tag );
	}
	
	// Adiciona noscript fallback para browsers sem JS
	$deferred_tag .= '<noscript>' . $noscript_tag . '</noscript>';

	return $deferred_tag;
}
add_filter( 'style_loader_tag', 'gstore_defer_non_critical_css', 10, 4 );

/**
 * Otimiza carregamento de scripts para reduzir main-thread work.
 * 
 * Aplica defer/async quando apropriado, especialmente para scripts
 * que não são necessários para renderização inicial.
 */
function gstore_optimize_script_loading( $tag, $handle, $src ) {
	// Não aplica em modo de desenvolvimento
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return $tag;
	}
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		return $tag;
	}

	// Scripts que podem ser deferidos (não críticos para renderização inicial)
	$defer_scripts = array(
		'gstore-home-benefits',        // Não crítico para primeira renderização
		'gstore-home-products-carousel', // Carrossel pode carregar depois
		'gstore-product-card',          // Não crítico acima da dobra
		'gstore-my-account',            // Página específica
	);

	// Scripts que podem usar async (não dependem de outros)
	$async_scripts = array();

	// Aplica defer
	if ( in_array( $handle, $defer_scripts, true ) ) {
		// Verifica se já tem defer ou async
		if ( strpos( $tag, ' defer' ) === false && strpos( $tag, ' async' ) === false ) {
			$tag = str_replace( ' src', ' defer src', $tag );
		}
	}

	// Aplica async
	if ( in_array( $handle, $async_scripts, true ) ) {
		if ( strpos( $tag, ' defer' ) === false && strpos( $tag, ' async' ) === false ) {
			$tag = str_replace( ' src', ' async src', $tag );
		}
	}

	return $tag;
}
add_filter( 'script_loader_tag', 'gstore_optimize_script_loading', 10, 3 );

/**
 * Otimiza back/forward cache (bfcache) removendo barreiras.
 * 
 * Garante que a página pode ser restaurada do bfcache corretamente
 * e evita listeners que bloqueiam essa funcionalidade.
 */
function gstore_fix_back_forward_cache() {
	?>
	<script id="gstore-bfcache-fix">
	(function() {
		'use strict';
		
		// Detecta quando a página é restaurada do bfcache
		window.addEventListener('pageshow', function(event) {
			if (event.persisted) {
				// Página restaurada do bfcache - pode precisar re-inicializar alguns recursos
				// Mas não força reload completo
				
				// Se houver scripts que precisam reinicializar, podem escutar este evento
				window.dispatchEvent(new CustomEvent('gstore:bfcache:restore'));
			}
		});
		
		// Evita uso de beforeunload quando possível (bloqueia bfcache)
		// Intercepta tentativas de adicionar beforeunload apenas em produção
		if (!window.location.hostname.includes('localhost') && !window.location.hostname.includes('127.0.0.1')) {
			var originalAddEventListener = window.addEventListener;
			window.addEventListener = function(type, listener, options) {
				// Bloqueia beforeunload que impede bfcache (exceto se realmente necessário)
				if (type === 'beforeunload' && typeof listener === 'function') {
					// Permite apenas se for realmente necessário (ex: formulário com dados não salvos)
					// Na prática, não bloqueamos, mas logamos para debug
					if (window.console && console.warn) {
						console.warn('[Gstore Performance] beforeunload listener detectado - pode afetar bfcache');
					}
				}
				return originalAddEventListener.call(this, type, listener, options);
			};
		}
		
		// Garante que não há referências a objetos que podem impedir bfcache
		// Remove referências circulares comuns
		if ('requestIdleCallback' in window) {
			requestIdleCallback(function() {
				// Limpa timers órfãos que podem impedir bfcache
				// Não faz nada agressivo, apenas garante limpeza
			}, { timeout: 1000 });
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'gstore_fix_back_forward_cache', 1 );

/**
 * Remove preload automático do WordPress para o style.css do tema filho.
 * 
 * O WordPress 5.8+ adiciona automaticamente um preload para o stylesheet do tema,
 * mas isso pode causar avisos no console se o CSS não for usado imediatamente.
 * Como o CSS está sendo enfileirado corretamente, o preload não é necessário.
 */
function gstore_remove_automatic_stylesheet_preload( $hints, $relation_type ) {
	// Remove apenas preload do stylesheet do tema filho
	if ( 'preload' === $relation_type ) {
		$stylesheet_uri = get_stylesheet_uri();
		foreach ( $hints as $key => $hint ) {
			if ( isset( $hint['href'] ) && $hint['href'] === $stylesheet_uri ) {
				unset( $hints[ $key ] );
			}
		}
	}
	return $hints;
}
add_filter( 'wp_resource_hints', 'gstore_remove_automatic_stylesheet_preload', 10, 2 );

/**
 * Previne conflitos de múltiplas instâncias do React e problemas de acessibilidade.
 * 
 * 1. O erro "Failed to execute 'removeChild'" geralmente ocorre quando há
 *    múltiplas instâncias do React ou conflitos entre React e outras bibliotecas.
 * 
 * 2. O problema de aria-hidden ocorre quando o WooCommerce define aria-hidden="true"
 *    no wp-site-blocks enquanto o botão do mini-cart ainda tem foco.
 */
function gstore_prevent_react_conflicts() {
	?>
	<script id="gstore-react-conflict-fix">
	(function() {
		'use strict';
		
		// Previne erros de removeChild do React de forma segura
		// Apenas intercepta chamadas que falhariam
		var originalRemoveChild = Node.prototype.removeChild;
		Node.prototype.removeChild = function(child) {
			// Verifica se child é válido
			if (!child) {
				return child;
			}
			
			try {
				// Verifica se o nó ainda está no DOM e se é realmente filho
				if (this.contains && this.contains(child)) {
					// Verifica se o nó ainda tem um parent (pode ter sido removido por outro processo)
					if (child.parentNode === this) {
						return originalRemoveChild.call(this, child);
					} else if (child.parentNode) {
						// O nó tem um parent diferente, não é filho deste nó
						// Retorna sem erro
						return child;
					} else {
						// O nó não tem parent, já foi removido
						// Retorna sem erro
						return child;
					}
				}
				// Se não for filho, retorna o nó sem erro
				return child;
			} catch (e) {
				// Se houver erro, apenas retorna o nó sem lançar exceção
				// Isso previne que o erro quebre a renderização do React
				if (window.location.hostname.includes('localhost') || window.location.hostname.includes('127.0.0.1')) {
					console.warn('[Gstore] Erro ao remover nó (prevenido):', e.message);
				}
				return child;
			}
		};
		
		// Corrige problema de aria-hidden no mini-cart
		// Quando o drawer do mini-cart é aberto, o WooCommerce define aria-hidden="true"
		// no wp-site-blocks, mas o botão do mini-cart ainda pode ter foco
		function fixMiniCartAriaHidden() {
			var wpSiteBlocks = document.querySelector('.wp-site-blocks');
			var miniCartButton = document.querySelector('.wc-block-mini-cart__button');
			var miniCartDrawer = document.querySelector('.wc-block-mini-cart__drawer');
			
			if (!wpSiteBlocks || !miniCartButton || !miniCartDrawer) {
				return;
			}
			
			// Intercepta quando o WooCommerce tenta definir aria-hidden="true"
			// Verifica se há elementos focáveis antes de aplicar
			var originalSetAttribute = Element.prototype.setAttribute;
			Element.prototype.setAttribute = function(name, value) {
				// Se está tentando definir aria-hidden="true" no wp-site-blocks
				if (name === 'aria-hidden' && value === 'true' && this === wpSiteBlocks) {
					var activeElement = document.activeElement;
					
					// Verifica se há um elemento focável dentro do wp-site-blocks
					// que não está dentro do drawer do mini-cart
					var focusableElements = wpSiteBlocks.querySelectorAll(
						'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
					);
					
					var hasFocusedElement = false;
					for (var i = 0; i < focusableElements.length; i++) {
						var el = focusableElements[i];
						// Ignora elementos dentro do drawer (eles devem estar hidden)
						if (!el.closest('.wc-block-mini-cart__drawer')) {
							if (el === activeElement || el.contains(activeElement)) {
								hasFocusedElement = true;
								break;
							}
						}
					}
					
					// Se há um elemento focável, não aplica aria-hidden no wp-site-blocks
					// Aplica apenas no conteúdo principal (não no header)
					if (hasFocusedElement) {
						var mainContent = document.querySelector('main:not(.Gstore-header)');
						if (mainContent && !mainContent.closest('.wc-block-mini-cart__drawer')) {
							mainContent.setAttribute('aria-hidden', 'true');
						}
						// Não aplica no wp-site-blocks
						return;
					}
				}
				
				// Para outros casos, usa o comportamento padrão
				return originalSetAttribute.call(this, name, value);
			};
			
			// Observa mudanças no drawer do mini-cart para limpar aria-hidden quando fechar
			var drawerObserver = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
						var isDrawerOpen = miniCartDrawer.classList.contains('is-open');
						
						// Quando o drawer fecha, remove aria-hidden do conteúdo principal
						if (!isDrawerOpen) {
							var mainContent = document.querySelector('main[aria-hidden="true"]');
							if (mainContent) {
								mainContent.removeAttribute('aria-hidden');
							}
							// Também remove do wp-site-blocks se estiver definido
							if (wpSiteBlocks.getAttribute('aria-hidden') === 'true') {
								wpSiteBlocks.removeAttribute('aria-hidden');
							}
						}
					}
				});
			});
			
			drawerObserver.observe(miniCartDrawer, {
				attributes: true,
				attributeFilter: ['class']
			});
		}
		
		// Inicializa quando o DOM estiver pronto
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fixMiniCartAriaHidden);
		} else {
			fixMiniCartAriaHidden();
		}
		
		// Também tenta após um pequeno delay para garantir que o WooCommerce carregou
		setTimeout(fixMiniCartAriaHidden, 1000);
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'gstore_prevent_react_conflicts', 999 );

/**
 * Enfileira estilos do tema pai e do child theme.
 * 
 * Nova estrutura modular:
 * 1. Sistema de tokens e base (gstore-main.css)
 * 2. Style.css legado (compatibilidade)
 * 3. Estilos específicos de página (cart, checkout, etc.)
 */
function gstore_enqueue_styles() {
	$parent_handle = 'twentytwentyfive-style';
	$parent_theme  = wp_get_theme( 'twentytwentyfive' );
	$theme_version = wp_get_theme()->get( 'Version' );
	
	// Obtém timestamp da última atualização dos tokens para forçar recarregamento
	$tokens_version = get_option( 'gstore_tokens_last_updated', time() );
	$gstore_version = $theme_version . '.' . $tokens_version;

	// Tema pai
	wp_enqueue_style(
		$parent_handle,
		get_template_directory_uri() . '/style.css',
		array(),
		$parent_theme->get( 'Version' )
	);

	// Font Awesome - Carregado de forma não bloqueante
	// Usa técnica de media="print" + onload para evitar render blocking
	wp_enqueue_style(
		'gstore-fontawesome',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
		array(),
		'6.5.1'
	);

	// Sistema modular Gstore (tokens, base, utilities, components, layouts)
	// Usa versão com timestamp para forçar recarregamento quando tokens são atualizados
	wp_enqueue_style(
		'gstore-main',
		get_theme_file_uri( 'assets/css/gstore-main.css' ),
		array( $parent_handle, 'gstore-fontawesome' ),
		$gstore_version
	);

	// Style.css principal (contém estilos legados que ainda não foram migrados)
	wp_enqueue_style(
		'gstore-style',
		get_stylesheet_uri(),
		array( 'gstore-main' ),
		$theme_version
	);

	// Header CSS - carregado por último para ter prioridade sobre estilos legados
	wp_enqueue_style(
		'gstore-header-css',
		get_theme_file_uri( 'assets/css/layouts/header.css' ),
		array( 'gstore-style' ),
		$theme_version
	);

	// CSS da página de minha conta (login/registro)
	if ( class_exists( 'WooCommerce' ) && function_exists( 'is_account_page' ) && is_account_page() ) {
		wp_enqueue_style(
			'gstore-my-account-css',
			get_theme_file_uri( 'assets/css/my-account.css' ),
			array( 'gstore-style' ),
			$theme_version
		);
	}

	// CSS da página de como comprar arma
	if ( is_page( 'como-comprar-arma' ) ) {
		wp_enqueue_style(
			'gstore-como-comprar-arma-css',
			get_theme_file_uri( 'assets/css/como-comprar-arma.css' ),
			array( 'gstore-style' ),
			$theme_version
		);
	}

	// CSS de Notificações e Modais
	wp_enqueue_style(
		'gstore-notices-css',
		get_theme_file_uri( 'assets/css/components/notices.css' ),
		array( 'gstore-main' ),
		$theme_version
	);

	// CSS do Toast de Adicionar ao Carrinho
	wp_enqueue_style(
		'gstore-add-to-cart-toast-css',
		get_theme_file_uri( 'assets/css/components/add-to-cart-toast.css' ),
		array( 'gstore-main' ),
		$theme_version
	);

	// Filtro de Categorias Marketplace
	wp_enqueue_style(
		'gstore-category-filter',
		get_theme_file_uri( 'assets/css/category-filter.css' ),
		array( 'gstore-style' ),
		$theme_version
	);

	// Filtro de Categorias Marketplace - JS
	wp_enqueue_script(
		'gstore-category-filter-js',
		get_theme_file_uri( 'assets/js/category-filter.js' ),
		array(),
		$theme_version,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'gstore_enqueue_styles' );

/**
 * Adiciona cabeçalhos de política de permissões para permitir pagamentos e clipboard.
 * Isso resolve o erro: Permissions policy violation: payment is not allowed in this document.
 */
function gstore_add_permissions_policy_header() {
	if ( ! is_admin() ) {
		// Permite pagamentos e área de transferência (para Pix) no documento e iframes.
		header( 'Permissions-Policy: payment=*, clipboard-write=*' );
	}
}
add_action( 'send_headers', 'gstore_add_permissions_policy_header' );

/**
 * Enfileira scripts customizados.
 */
function gstore_enqueue_scripts() {
	wp_enqueue_script(
		'gstore-header',
		get_theme_file_uri( 'assets/js/header.js' ),
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	// Passa URLs dinâmicas do WooCommerce para o JavaScript
	// Isso corrige os links hardcoded nos templates FSE quando o idioma muda
	if ( class_exists( 'WooCommerce' ) ) {
		$myaccount_url = wc_get_page_permalink( 'myaccount' );
		wp_localize_script(
			'gstore-header',
			'gstoreAccountUrls',
			array(
				'myAccount' => $myaccount_url ? $myaccount_url : home_url( '/minha-conta/' ),
				'orders'    => $myaccount_url ? wc_get_endpoint_url( 'orders', '', $myaccount_url ) : home_url( '/minha-conta/orders/' ),
			)
		);
	}

	if ( is_front_page() ) {
		wp_enqueue_script(
			'gstore-home-hero',
			get_theme_file_uri( 'assets/js/home-hero.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);
		wp_enqueue_script(
			'gstore-home-benefits',
			get_theme_file_uri( 'assets/js/home-benefits.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);

		wp_enqueue_script(
			'gstore-home-products-carousel',
			get_theme_file_uri( 'assets/js/home-products-carousel.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);
	}

	// Script para posts únicos do blog
	if ( is_single() && get_post_type() === 'post' ) {
		wp_enqueue_script(
			'gstore-blog-single',
			get_theme_file_uri( 'assets/js/blog-single.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);
	}

	// Script dos cards de produto
	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_script(
			'gstore-product-card',
			get_theme_file_uri( 'assets/js/product-card.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_script(
				'gstore-single-product',
				get_theme_file_uri( 'assets/js/single-product.js' ),
				array(),
				wp_get_theme()->get( 'Version' ),
				true
			);
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			wp_enqueue_script(
				'gstore-cart',
				get_theme_file_uri( 'assets/js/cart.js' ),
				array(),
				wp_get_theme()->get( 'Version' ),
				true
			);
		}

		// Script da página de minha conta (login/registro)
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_enqueue_script(
				'gstore-my-account',
				get_theme_file_uri( 'assets/js/my-account.js' ),
				array(),
				wp_get_theme()->get( 'Version' ),
				true
			);
		}

		// Script da página de catálogo (filtros retráteis mobile)
		// Carrega se for qualquer página de catálogo ou se tiver a classe Gstore-catalog-shell
		$is_catalog_page = false;
		if ( function_exists( 'is_page' ) ) {
			// Páginas estáticas de catálogo
			$catalog_pages = array( 'catalogo', 'ofertas' );
			$catalog_templates = array( 'page-catalogo', 'page-ofertas' );
			
			$is_catalog_page = is_page( $catalog_pages );
			
			// Verifica também pelo template
			if ( ! $is_catalog_page && is_page() ) {
				$template = get_page_template_slug();
				foreach ( $catalog_templates as $tpl ) {
					if ( $template === $tpl || $template === $tpl . '.html' ) {
						$is_catalog_page = true;
						break;
					}
				}
			}
		}
		
		// Também verifica se é uma página de shop/archive do WooCommerce
		if ( ! $is_catalog_page && function_exists( 'is_shop' ) ) {
			$is_catalog_page = is_shop() || is_product_category() || is_product_tag();
		}
		
		if ( $is_catalog_page ) {
		/*
		wp_enqueue_script(
			'gstore-catalog-filters',
			get_theme_file_uri( 'assets/js/catalog-filters.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);
		*/
	}
	
	// #region agent log - Script para árvore de categorias carregado sempre para debug
	/*
	wp_enqueue_script(
		'gstore-catalog-categories-tree',
		get_theme_file_uri( 'assets/js/catalog-categories-tree.js' ),
		array(),
		(string) @filemtime( get_theme_file_path( 'assets/js/catalog-categories-tree.js' ) ),
		true
	);
	*/
	// #endregion

		// Script para sincronização do Mini Cart Block (versão simplificada)
		// Dependências: wc-settings (fornece storeApiNonce), wp-data (fornece wp.data store)
		wp_enqueue_script(
			'gstore-mini-cart-fix',
			get_theme_file_uri( 'assets/js/mini-cart-fix.js' ),
			array( 'jquery', 'wc-settings', 'wp-data' ),
			'2.0.0',
			true
		);

		// Script para gerenciar avisos do WooCommerce (slide-in e auto-dismiss)
		wp_enqueue_script(
			'gstore-notices',
			get_theme_file_uri( 'assets/js/notices.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);

		// Toast de Adicionar ao Carrinho (substitui link "Ver carrinho" por modal)
		wp_enqueue_script(
			'gstore-add-to-cart-toast',
			get_theme_file_uri( 'assets/js/add-to-cart-toast.js' ),
			array( 'jquery' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		// Localizar nonce e configurações como fallback para o mini-cart fix
		wp_localize_script(
			'gstore-mini-cart-fix',
			'gstoreMiniCart',
			array(
				'storeApiNonce' => wp_create_nonce( 'wc_store_api' ),
				'cartEndpoint'  => rest_url( 'wc/store/v1/cart' ),
				'debug'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
			)
		);
	}

	// Localizar script para AJAX do WooCommerce
	if ( class_exists( 'WooCommerce' ) ) {
		wp_localize_script(
			'gstore-header',
			'gstore_wc',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'cart_url'   => wc_get_cart_url(),
				'cart_count' => WC()->cart->get_cart_contents_count(),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'gstore_enqueue_scripts' );

/**
 * Atualiza o fragmento do carrinho para refletir mudanças em tempo real.
 *
 * @param array $fragments Fragmentos de carrinho.
 * @return array
 */
function gstore_cart_count_fragments( $fragments ) {
	$cart_count = WC()->cart->get_cart_contents_count();
	
	ob_start();
	?>
	<span class="Gstore-cart-count" aria-label="<?php echo esc_attr( sprintf( _n( '%d item no carrinho', '%d itens no carrinho', $cart_count, 'gstore' ), $cart_count ) ); ?>">
		<?php echo esc_html( $cart_count ); ?>
	</span>
	<?php
	$fragments['.Gstore-cart-count'] = ob_get_clean();
	
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'gstore_cart_count_fragments' );

/**
 * Garante que o AJAX add to cart está habilitado e configurado corretamente.
 * 
 * Por padrão, o WooCommerce já habilita AJAX, mas esta função garante
 * que não foi desabilitado por outros plugins ou configurações.
 */
function gstore_ensure_ajax_add_to_cart_enabled() {
	// Garante que o AJAX add to cart está habilitado
	if ( class_exists( 'WooCommerce' ) ) {
		// O WooCommerce já habilita AJAX por padrão via get_option('woocommerce_enable_ajax_add_to_cart')
		// Mas vamos garantir que está ativo
		if ( 'yes' !== get_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' ) ) {
			update_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' );
		}
	}
}
add_action( 'init', 'gstore_ensure_ajax_add_to_cart_enabled', 5 );

/**
 * Melhora os fragmentos do carrinho para incluir mais elementos do mini-cart.
 * 
 * Adiciona fragmentos adicionais para garantir que o mini-cart seja atualizado
 * corretamente após adicionar OU remover produtos do carrinho.
 * 
 * IMPORTANTE: Este filtro é chamado tanto em added_to_cart quanto em removed_from_cart.
 */
function gstore_enhance_cart_fragments( $fragments ) {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $fragments;
	}

	$cart = WC()->cart;
	
	if ( ! $cart ) {
		return $fragments;
	}

	$cart_count = $cart->get_cart_contents_count();

	// Adiciona fragmento para o badge do mini-cart block (se existir na página)
	ob_start();
	?>
	<span class="wc-block-mini-cart__badge">
		<?php echo esc_html( $cart_count ); ?>
	</span>
	<?php
	$fragments['.wc-block-mini-cart__badge'] = ob_get_clean();

	// Adiciona fragmento para o contador customizado do tema
	
	ob_start();
	?>
	<span class="Gstore-cart-count" aria-label="<?php echo esc_attr( sprintf( _n( '%d item no carrinho', '%d itens no carrinho', $cart_count, 'gstore' ), $cart_count ) ); ?>">
		<?php echo esc_html( $cart_count ); ?>
	</span>
	<?php
	$fragments['.Gstore-cart-count'] = ob_get_clean();

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'gstore_enhance_cart_fragments', 20 );

/**
 * Garante que os fragmentos sejam atualizados também na remoção de produtos.
 * 
 * O WooCommerce usa o mesmo filtro para adição e remoção, mas esta função
 * garante que os eventos sejam disparados corretamente e que os fragmentos
 * sejam sempre retornados, especialmente em ambientes de produção com cache.
 */
function gstore_ensure_removal_fragments() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Hook específico para garantir fragmentos após remoção
	add_action( 'woocommerce_cart_item_removed', function( $cart_item_key, $cart ) {
		// Força atualização dos fragmentos após remoção
		// O WooCommerce já faz isso automaticamente, mas garantimos que funcione
		// Em produção, pode haver problemas de timing ou cache
		do_action( 'gstore_cart_item_removed', $cart_item_key, $cart );
	}, 10, 2 );

	// Garante que fragmentos sejam sempre retornados mesmo se o filtro padrão falhar
	add_filter( 'woocommerce_add_to_cart_fragments', function( $fragments ) {
		// Verifica se estamos em uma requisição de remoção
		// O WooCommerce não diferencia claramente, então sempre garantimos fragmentos atualizados
		if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
			return $fragments;
		}

		// Força atualização dos fragmentos do mini-cart mesmo se não foram incluídos
		$cart_count = WC()->cart->get_cart_contents_count();
		
		// Garante que o fragmento do badge sempre existe e está atualizado
		ob_start();
		?>
		<span class="wc-block-mini-cart__badge">
			<?php echo esc_html( $cart_count ); ?>
		</span>
		<?php
		$fragments['.wc-block-mini-cart__badge'] = ob_get_clean();

		// Garante que o fragmento customizado sempre existe e está atualizado
		ob_start();
		?>
		<span class="Gstore-cart-count" aria-label="<?php echo esc_attr( sprintf( _n( '%d item no carrinho', '%d itens no carrinho', $cart_count, 'gstore' ), $cart_count ) ); ?>">
			<?php echo esc_html( $cart_count ); ?>
		</span>
		<?php
		$fragments['.Gstore-cart-count'] = ob_get_clean();

		return $fragments;
	}, 30 ); // Prioridade alta para garantir que seja executado após outros filtros
}
add_action( 'init', 'gstore_ensure_removal_fragments', 15 );

/**
 * Garante que os eventos WooCommerce sejam disparados corretamente.
 * 
 * Adiciona suporte adicional para garantir que o evento added_to_cart
 * seja sempre disparado, mesmo em casos edge.
 */
function gstore_ensure_cart_events() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Garante que o WooCommerce não está redirecionando após add to cart
	// (isso desabilita o AJAX)
	add_filter( 'woocommerce_add_to_cart_redirect', '__return_false' );
}
add_action( 'init', 'gstore_ensure_cart_events', 10 );

/**
 * "Comprar agora" (produto único): redireciona para o checkout após adicionar ao carrinho.
 *
 * Implementado via botão submit no template do produto único (name="gstore_buy_now").
 */
function gstore_buy_now_redirect_to_checkout( $url ) {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_checkout_url' ) ) {
		return $url;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_REQUEST['gstore_buy_now'] ) ) {
		return wc_get_checkout_url();
	}

	return $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'gstore_buy_now_redirect_to_checkout', 20 );

/**
 * Adiciona headers HTTP para evitar cache em requisições AJAX do carrinho.
 * 
 * Isso é crítico em ambientes de produção onde cache pode causar
 * problemas de sincronização entre o carrinho e o mini-cart.
 */
function gstore_prevent_cart_ajax_cache() {
	// Só adiciona headers em requisições AJAX relacionadas ao carrinho
	if ( ! wp_doing_ajax() && ! isset( $_REQUEST['wc-ajax'] ) ) {
		return;
	}

	$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
	$wc_ajax = isset( $_REQUEST['wc-ajax'] ) ? $_REQUEST['wc-ajax'] : '';
	$is_cart_action = (
		strpos( $action, 'cart' ) !== false ||
		strpos( $action, 'woocommerce' ) !== false ||
		strpos( $wc_ajax, 'cart' ) !== false ||
		strpos( $wc_ajax, 'remove' ) !== false ||
		strpos( $wc_ajax, 'update' ) !== false ||
		isset( $_REQUEST['wc-ajax'] )
	);

	if ( $is_cart_action && ! headers_sent() ) {
		// Headers para evitar cache em requisições AJAX do carrinho
		// Crítico em produção com CDN/cache
		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-Accel-Buffering: no' ); // Nginx buffering
		header( 'Vary: Cookie' ); // Garante que cache varia por cookie/sessão
		
		// Garante que sessões sejam mantidas
		// Força o uso de cookies para sessões
		ini_set( 'session.use_cookies', '1' );
		ini_set( 'session.use_only_cookies', '1' );
		
		// Adiciona header para evitar cache em proxies/CDN
		header( 'X-Cache-Control: no-cache' );
	}
}
add_action( 'wp_ajax_woocommerce_add_to_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_nopriv_woocommerce_add_to_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_woocommerce_remove_from_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_nopriv_woocommerce_remove_from_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_woocommerce_update_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wp_ajax_nopriv_woocommerce_update_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wc_ajax_add_to_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wc_ajax_remove_from_cart', 'gstore_prevent_cart_ajax_cache', 1 );
add_action( 'wc_ajax_update_cart', 'gstore_prevent_cart_ajax_cache', 1 );

/**
 * Garante que fragmentos sejam sempre retornados após remoção de item.
 * 
 * Hook específico para wc_ajax_remove_from_cart para garantir que fragmentos
 * sejam sempre incluídos na resposta, mesmo em ambientes com cache ou problemas de timing.
 */
function gstore_force_fragments_on_removal() {
	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return;
	}

	// Força atualização dos fragmentos após processar remoção
	add_filter( 'woocommerce_add_to_cart_fragments', function( $fragments ) {
		$cart_count = WC()->cart->get_cart_contents_count();
		
		// Sempre garante que os fragmentos críticos existam
		ob_start();
		?>
		<span class="wc-block-mini-cart__badge">
			<?php echo esc_html( $cart_count ); ?>
		</span>
		<?php
		$fragments['.wc-block-mini-cart__badge'] = ob_get_clean();

		ob_start();
		?>
		<span class="Gstore-cart-count" aria-label="<?php echo esc_attr( sprintf( _n( '%d item no carrinho', '%d itens no carrinho', $cart_count, 'gstore' ), $cart_count ) ); ?>">
			<?php echo esc_html( $cart_count ); ?>
		</span>
		<?php
		$fragments['.Gstore-cart-count'] = ob_get_clean();

		return $fragments;
	}, 999 ); // Prioridade muito alta para garantir execução
}
add_action( 'wc_ajax_remove_from_cart', 'gstore_force_fragments_on_removal', 5 );

/**
 * Remove o breadcrumb padrão do WooCommerce.
 */
function gstore_remove_default_breadcrumb() {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}
add_action( 'init', 'gstore_remove_default_breadcrumb' );

/**
 * Remove o texto de privacidade do formulário de registro.
 * O texto será exibido em um modal ao invés de diretamente no formulário.
 */
function gstore_remove_registration_privacy_text() {
	remove_action( 'woocommerce_register_form', 'wc_registration_privacy_policy_text', 20 );
}
add_action( 'init', 'gstore_remove_registration_privacy_text' );

/**
 * Remove a tag "Oferta" (onsale badge) da página de produto único.
 */
function gstore_remove_sale_flash_on_single_product() {
	if ( function_exists( 'is_product' ) && is_product() ) {
		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
	}
}
add_action( 'wp', 'gstore_remove_sale_flash_on_single_product' );

/**
 * Força o uso do template customizado de produto (Gstore).
 * Remove esta função depois que os cards estiverem funcionando.
 *
 * @param string $template      Caminho do template.
 * @param string $template_name Nome do template.
 * @param string $template_path Caminho base dos templates.
 * @return string
 */
function gstore_force_custom_product_template( $template, $template_name, $template_path ) {
	if ( 'content-product.php' === $template_name ) {
		$custom_template = get_theme_file_path( 'woocommerce/content-product.php' );
		if ( file_exists( $custom_template ) ) {
			return $custom_template;
		}
	}
	return $template;
}
add_filter( 'woocommerce_locate_template', 'gstore_force_custom_product_template', 10, 3 );

/**
 * Força exibição de estrelas mesmo sem avaliações nos blocos.
 *
 * @param string $html HTML do bloco de avaliação.
 * @param array  $attributes Atributos do bloco.
 * @param object $product Produto WooCommerce.
 * @return string
 */
function gstore_always_show_rating_stars( $html, $attributes, $product ) {
	if ( empty( $html ) ) {
		// Se não há avaliações, gerar HTML de estrelas vazias
		$html = '<div class="wc-block-components-product-rating">';
		$html .= '<div class="wc-block-components-product-rating__stars">';
		$html .= '<span>★★★★★</span>';
		$html .= '</div>';
		$html .= '</div>';
	}
	return $html;
}
add_filter( 'render_block_woocommerce/product-rating', 'gstore_always_show_rating_stars', 10, 3 );

/**
 * ============================================
 * CÁLCULO DE PARCELAS COM JUROS (BLU)
 * ============================================
 * Taxa padrão: 2.99% a.m. (configurável via filtro)
 * Fórmula: PMT = PV * [i * (1 + i)^n] / [(1 + i)^n - 1]
 */

/**
 * Calcula o valor da parcela com juros compostos.
 *
 * @param float $price         Valor total do produto (presente).
 * @param int   $installments  Número de parcelas.
 * @param float $rate          Taxa de juros mensal (decimal). Padrão: 0.0299 (2.99% a.m.).
 * @return float Valor da parcela.
 */
function gstore_calculate_installment_with_interest( $price, $installments = 12, $rate = null ) {
	if ( $price <= 0 || $installments <= 0 ) {
		return 0;
	}

	// Taxa de juros configurável via filtro. Padrão: 2.99% a.m.
	if ( null === $rate ) {
		$rate = apply_filters( 'gstore_blu_installment_rate', 0.0299 );
	}

	// Se taxa for 0, retorna simples divisão (sem juros)
	if ( $rate <= 0 ) {
		return $price / $installments;
	}

	// Fórmula de parcela com juros compostos (Price/PGTO)
	// PMT = PV * [i * (1 + i)^n] / [(1 + i)^n - 1]
	$factor   = pow( 1 + $rate, $installments );
	$pmt      = $price * ( $rate * $factor ) / ( $factor - 1 );

	return $pmt;
}

/**
 * Retorna a taxa de juros atual da Blu.
 *
 * @return float Taxa em decimal (ex: 0.0299 = 2.99%).
 */
function gstore_get_blu_installment_rate() {
	return apply_filters( 'gstore_blu_installment_rate', 0.0299 );
}

/**
 * Retorna a taxa de juros atual da Blu em percentual.
 *
 * @return string Taxa formatada (ex: "2,99%").
 */
function gstore_get_blu_installment_rate_percent() {
	$rate = gstore_get_blu_installment_rate();
	return number_format( $rate * 100, 2, ',', '.' ) . '%';
}

/**
 * Adiciona informações de pagamento ao bloco de preço.
 *
 * @param string $html HTML do bloco de preço.
 * @param array  $block_content Conteúdo do bloco.
 * @param object $block Objeto do bloco.
 * @return string
 */
function gstore_add_payment_info_to_price( $html, $block_content, $block ) {
	// Verifica se é o bloco de preço
	if ( empty( $html ) || strpos( $html, 'woocommerce-Price-amount' ) === false ) {
		return $html;
	}
	
	// Verifica se já tem as classes customizadas (evita duplicação)
	if ( strpos( $html, 'Gstore-payment-label' ) !== false ) {
		return $html;
	}
	
	// Tenta obter o produto do contexto
	$product = null;
	if ( isset( $block->context['postId'] ) ) {
		$product = wc_get_product( $block->context['postId'] );
	}
	
	// Se não conseguir pelo contexto, tenta pegar o produto global
	if ( ! $product ) {
		global $product;
	}
	
	// Calcula o valor da parcela COM JUROS (taxa Blu)
	$installment_value = 0;
	$installment_text_content = 'ou em até 21x no cartão';
	
	if ( $product && is_a( $product, 'WC_Product' ) ) {
		$price_value = floatval( $product->get_price() );
		if ( $price_value > 0 ) {
			// Usa função de cálculo com juros compostos
			$installment_value = gstore_calculate_installment_with_interest( $price_value, 21 );
			$installment_text_content = 'ou em até 21x de ' . wc_price( $installment_value );
		}
	}
	
	// Cria os elementos de pagamento
	$payment_label = '<div class="Gstore-payment-label">À VISTA NO PIX</div>';
	$installment_text = '<div class="Gstore-installment-text">' . $installment_text_content . '</div>';
	
	// Encontra a div interna com a classe wc-block-components-product-price
	if ( strpos( $html, 'wc-block-components-product-price' ) !== false ) {
		// Adiciona o label antes do preço (logo após a abertura da div interna)
		$html = preg_replace(
			'/(<div[^>]*class="[^"]*wc-block-components-product-price[^"]*"[^>]*>\s*)/',
			'$1' . $payment_label,
			$html,
			1
		);
		
		// Adiciona o texto de parcelamento antes do fechamento das divs
		$html = preg_replace(
			'/(\s*<\/div>\s*<\/div>\s*)$/',
			$installment_text . '$1',
			$html,
			1
		);
	}
	
	return $html;
}
add_filter( 'render_block_woocommerce/product-price', 'gstore_add_payment_info_to_price', 10, 3 );

/**
 * Filtra produtos na home page para exibir apenas produtos em estoque.
 * Aplica tanto para queries principais quanto para shortcodes.
 *
 * @param array $query_args Argumentos da query do WooCommerce.
 * @return array
 */
function gstore_filter_home_products_by_stock( $query_args ) {
	// Apenas na home page
	if ( ! is_front_page() ) {
		return $query_args;
	}

	// Força apenas produtos em estoque
	if ( ! isset( $query_args['meta_query'] ) || ! is_array( $query_args['meta_query'] ) ) {
		$query_args['meta_query'] = array();
	}

	// Adiciona filtro de estoque (mesmo que já tenha sido especificado no shortcode)
	$query_args['meta_query'][] = array(
		'key'     => '_stock_status',
		'value'   => 'instock',
		'compare' => '=',
	);

	return $query_args;
}
add_filter( 'woocommerce_shortcode_products_query', 'gstore_filter_home_products_by_stock', 10, 1 );

/**
 * Remove a formatação automática de parágrafos do WordPress.
 */
function gstore_disable_wpautop() {
	$filters = array(
		'the_content',
		'the_excerpt',
		'widget_text_content',
		'comment_text',
		'term_description',
		'woocommerce_short_description',
	);

	foreach ( $filters as $filter ) {
		remove_filter( $filter, 'wpautop' );
		remove_filter( $filter, 'shortcode_unautop' );
	}
}
add_action( 'init', 'gstore_disable_wpautop', 9 );

/**
 * Remove as tags <p> adicionadas automaticamente dentro dos cards personalizados.
 *
 * @param string $html HTML que contém os cards.
 * @return string
 */
function gstore_cleanup_shortcode_paragraphs( $html ) {
	if ( false === strpos( $html, 'Gstore-product-card__inner' ) ) {
		return $html;
	}

	if ( class_exists( 'DOMDocument' ) ) {
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();

		$has_mb_convert   = function_exists( 'mb_convert_encoding' );
		$needs_unwrap_div = ! $has_mb_convert;
		$content          = $has_mb_convert
			? mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' )
			: '<div>' . $html . '</div>';

		$dom->loadHTML(
			$content,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		$xpath      = new DOMXPath( $dom );
		$paragraphs = $xpath->query( '//*[contains(@class,"Gstore-product-card__inner")]//p' );

		foreach ( $paragraphs as $paragraph ) {
			while ( $paragraph->firstChild ) {
				$paragraph->parentNode->insertBefore( $paragraph->firstChild, $paragraph );
			}
			$paragraph->parentNode->removeChild( $paragraph );
		}

		$line_breaks = $xpath->query( '//*[contains(@class,"Gstore-product-card__inner")]//br' );

		foreach ( $line_breaks as $line_break ) {
			$class_attribute = $line_break->attributes ? $line_break->attributes->getNamedItem( 'class' ) : null;
			$should_keep     = $class_attribute && false !== strpos( $class_attribute->nodeValue, 'Gstore-keep-br' );

			if ( $should_keep ) {
				continue;
			}

			$line_break->parentNode->removeChild( $line_break );
		}

		$clean_html = $dom->saveHTML();

		if ( $needs_unwrap_div ) {
			$clean_html = preg_replace( '#^<div>(.*)</div>$#s', '$1', $clean_html );
		}

		libxml_clear_errors();

		return $clean_html;
	}

	$cleaned = preg_replace_callback(
		'#(<li[^>]*class="[^"]*Gstore-product-card[^"]*"[^>]*>)(.*?)(</li>)#si',
		static function ( $matches ) {
			$inner = preg_replace( '#</?p[^>]*>#i', '', $matches[2] );
			$inner = preg_replace_callback(
				'#<br[^>]*>#i',
				static function ( $br_matches ) {
					return false === stripos( $br_matches[0], 'Gstore-keep-br' ) ? '' : $br_matches[0];
				},
				$inner
			);
			return $matches[1] . $inner . $matches[3];
		},
		$html
	);

	return null === $cleaned ? $html : $cleaned;
}

/**
 * Garante que shortcodes de produtos não insiram <p> extras nos cards Gstore.
 *
 * @param string $output HTML gerado pelo shortcode.
 * @param string $tag    Nome do shortcode.
 * @return string
 */
function gstore_filter_products_shortcode_output( $output, $tag ) {
	$target_shortcodes = array(
		'products',
		'best_selling_products',
		'featured_products',
		'product_attribute',
		'product_categories',
		'product_category',
		'recent_products',
		'sale_products',
		'top_rated_products',
	);

	if ( ! in_array( $tag, $target_shortcodes, true ) ) {
		return $output;
	}

	return gstore_cleanup_shortcode_paragraphs( $output );
}
add_filter( 'do_shortcode_tag', 'gstore_filter_products_shortcode_output', 20, 2 );

/**
 * Remove os parágrafos extras também quando o conteúdo completo é renderizado.
 *
 * @param string $content Conteúdo da página/post.
 * @return string
 */
function gstore_cleanup_content_paragraphs( $content ) {
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return $content;
	}

	return gstore_cleanup_shortcode_paragraphs( $content );
}
add_filter( 'the_content', 'gstore_cleanup_content_paragraphs', 20 );

/**
 * Garante que o bloco de checkout esteja presente quando o conteúdo estiver vazio.
 *
 * @param string $content Conteúdo original da página.
 * @return string
 */
function gstore_ensure_checkout_block( $content ) {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $content;
	}

	if ( function_exists( 'has_shortcode' ) && has_shortcode( $content, 'woocommerce_checkout' ) ) {
		return $content;
	}

	$has_checkout_block = function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $content );

	if ( $has_checkout_block ) {
		return $content;
	}

	// Verifica se o shortcode já foi processado (procura pela classe do form)
	if ( false !== strpos( $content, 'woocommerce-checkout' ) ) {
		return $content;
	}

	$fallback_block = '[woocommerce_checkout]';

	return $content . do_shortcode( $fallback_block );
}
add_filter( 'the_content', 'gstore_ensure_checkout_block', 9 );

/**
 * Substitui o Checkout em bloco pelo shortcode clássico para ativar o campo CPF.
 *
 * @param string $content Conteúdo original da página.
 * @return string
 */
function gstore_force_classic_checkout_shortcode( $content ) {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $content;
	}

	if ( has_shortcode( $content, 'woocommerce_checkout' ) ) {
		return $content;
	}

	$contains_checkout_block = function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $content );

	if ( $contains_checkout_block ) {
		return do_shortcode( '[woocommerce_checkout]' );
	}

	return $content;
}
add_filter( 'the_content', 'gstore_force_classic_checkout_shortcode', 5 );

/**
 * Envolve o bloco de resumo do checkout com o card customizado da Gstore.
 *
 * @param string $block_content Conteúdo original do bloco.
 * @param array  $block         Dados do bloco.
 * @return string
 */
function gstore_wrap_checkout_order_summary_block( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'woocommerce/checkout-order-summary-block' !== $block['blockName'] ) {
		return $block_content;
	}

	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $block_content;
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		return $block_content;
	}

	$cart = WC()->cart;

	if ( ! $cart ) {
		return $block_content;
	}

	$totals          = $cart->get_totals();
	$raw_total       = isset( $totals['total'] ) ? (float) $totals['total'] : 0;
	$formatted_total = wc_price( $raw_total );
	$items_count     = max( 0, $cart->get_cart_contents_count() );
	$items_meta      = sprintf(
		_n( 'Inclui %d item. Frete e descontos detalhados abaixo.', 'Inclui %d itens. Frete e descontos detalhados abaixo.', $items_count, 'gstore' ),
		$items_count
	);

	ob_start();
	?>
	<div class="Gstore-order-summary-card" aria-label="<?php esc_attr_e( 'Resumo do pedido', 'gstore' ); ?>">
		<header class="Gstore-order-summary-card__header">
			<div>
				<span class="Gstore-order-summary-card__eyebrow"><?php esc_html_e( 'Resumo do pedido', 'gstore' ); ?></span>
				<h2 class="Gstore-order-summary-card__title"><?php esc_html_e( 'Revise antes de finalizar', 'gstore' ); ?></h2>
				<p class="Gstore-order-summary-card__description"><?php esc_html_e( 'Confira itens, valores e opções de envio antes de concluir.', 'gstore' ); ?></p>
			</div>

			<div class="Gstore-order-summary-card__total" aria-live="polite">
				<span class="Gstore-order-summary-card__total-label"><?php esc_html_e( 'Total do pedido', 'gstore' ); ?></span>
				<span class="Gstore-order-summary-card__total-amount"><?php echo wp_kses_post( $formatted_total ); ?></span>
				<span class="Gstore-order-summary-card__total-meta"><?php echo esc_html( $items_meta ); ?></span>
			</div>
		</header>

		<div class="Gstore-order-summary-card__content">
			<?php echo $block_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="Gstore-order-summary-card__assurance-grid" aria-label="<?php esc_attr_e( 'Garantias CAC Armas', 'gstore' ); ?>">
			<div class="Gstore-order-summary-card__assurance-card">
				<i class="fa-solid fa-headset" aria-hidden="true"></i>
				<div>
					<strong><?php esc_html_e( 'Atendimento dedicado', 'gstore' ); ?></strong>
					<span><?php esc_html_e( 'Equipe pronta para ajudar em cada etapa.', 'gstore' ); ?></span>
				</div>
			</div>
			<div class="Gstore-order-summary-card__assurance-card">
				<i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
				<div>
					<strong><?php esc_html_e( 'Compra segura', 'gstore' ); ?></strong>
					<span><?php esc_html_e( 'Pagamento protegido com criptografia.', 'gstore' ); ?></span>
				</div>
			</div>
			<div class="Gstore-order-summary-card__assurance-card">
				<i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
				<div>
					<strong><?php esc_html_e( 'Envio rastreado', 'gstore' ); ?></strong>
					<span><?php esc_html_e( 'Acompanhe o pedido em tempo real.', 'gstore' ); ?></span>
				</div>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}
add_filter( 'render_block', 'gstore_wrap_checkout_order_summary_block', 10, 2 );

/**
 * Substitui o Carrinho em bloco pelo shortcode clássico para ativar o layout Gstore.
 *
 * @param string $content Conteúdo original da página.
 * @return string
 */
function gstore_force_classic_cart_shortcode( $content ) {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return $content;
	}

	if ( has_shortcode( $content, 'woocommerce_cart' ) ) {
		return $content;
	}

	$contains_cart_block  = function_exists( 'has_block' ) && has_block( 'woocommerce/cart', $content );
	$contains_cart_markup = false !== stripos( $content, 'wp-block-woocommerce-cart' ) || false !== stripos( $content, 'wp-block-woocommerce-filled-cart-block' );

	if ( ! $contains_cart_block && ! $contains_cart_markup ) {
		$stripped_content = trim( wp_strip_all_tags( $content ) );

		if ( '' === $stripped_content ) {
			return do_shortcode( '[woocommerce_cart]' );
		}

		return $content;
	}

	return do_shortcode( '[woocommerce_cart]' );
}
add_filter( 'the_content', 'gstore_force_classic_cart_shortcode', 5 );

/**
 * Remove o título da página do carrinho (evita duplicação com o header customizado).
 * 
 * O WordPress Block Theme renderiza automaticamente um h1.wp-block-post-title
 * que não queremos na página do carrinho, pois já temos nosso header customizado.
 */
function gstore_remove_cart_page_title( $title, $id = null ) {
	// Só remove se estiver na página do carrinho
	if ( function_exists( 'is_cart' ) && is_cart() && in_the_loop() && is_main_query() ) {
		return '';
	}
	return $title;
}
add_filter( 'the_title', 'gstore_remove_cart_page_title', 10, 2 );

/**
 * Adiciona classe ao body para página do carrinho com template PHP.
 */
function gstore_cart_body_class( $classes ) {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		$classes[] = 'gstore-cart-template';
	}
	return $classes;
}
add_filter( 'body_class', 'gstore_cart_body_class' );

/**
 * Garante que a mensagem de carrinho vazio seja sempre exibida quando o carrinho estiver vazio.
 */
function gstore_ensure_empty_cart_message() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}

	if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
		return;
	}

	// Se o carrinho estiver vazio, garante que a mensagem seja exibida
	if ( WC()->cart->is_empty() ) {
		// Remove qualquer filtro que possa estar escondendo a mensagem
		add_filter( 'woocommerce_cart_is_empty', '__return_true', 999 );
		
		// Garante que os avisos sejam exibidos
		if ( function_exists( 'woocommerce_output_all_notices' ) ) {
			// A mensagem de carrinho vazio já é exibida pelo WooCommerce automaticamente
			// Mas garantimos que ela não seja escondida
			add_filter( 'woocommerce_output_all_notices', function( $output ) {
				if ( WC()->cart->is_empty() ) {
					// Se não houver mensagem de carrinho vazio, adiciona uma
					if ( false === strpos( $output, 'cart-empty' ) ) {
						$empty_message = '<p class="cart-empty woocommerce-info">' . esc_html__( 'Seu carrinho está vazio.', 'woocommerce' ) . '</p>';
						return $empty_message . $output;
					}
				}
				return $output;
			}, 5 );
		}
	}
}
add_action( 'wp', 'gstore_ensure_empty_cart_message', 5 );

/**
 * Remove o wrapper de notices quando não há notices para exibir.
 * Evita espaços vazios na página quando não há mensagens.
 *
 * @param string $output HTML dos notices.
 * @return string
 */
function gstore_hide_empty_notices_wrapper( $output ) {
	// Se o output estiver vazio ou contiver apenas o wrapper vazio, retorna string vazia
	if ( empty( trim( $output ) ) ) {
		return '';
	}

	// Remove o wrapper se não contiver nenhum notice real
	// Verifica se há mensagens, erros ou informações
	if ( 
		false === strpos( $output, 'woocommerce-message' ) &&
		false === strpos( $output, 'woocommerce-error' ) &&
		false === strpos( $output, 'woocommerce-info' ) &&
		false === strpos( $output, 'wc-block-components-notice' )
	) {
		return '';
	}

	return $output;
}
add_filter( 'woocommerce_output_all_notices', 'gstore_hide_empty_notices_wrapper', 999 );

/**
 * Gera script de debug para analisar estrutura HTML do carrinho.
 */
function gstore_get_cart_debug_script() {
	return <<<'SCRIPT'
(function() {
    console.clear();
    console.log('%c🛒 DIAGNÓSTICO DO CARRINHO GSTORE', 'font-size: 20px; font-weight: bold; color: #c9a43a;');
    console.log('');
    
    // Função para mostrar computed styles
    function getStyles(el, props) {
        if (!el) return 'ELEMENTO NÃO ENCONTRADO';
        const cs = getComputedStyle(el);
        return props.map(p => `${p}: ${cs[p]}`).join(', ');
    }
    
    // Função para mostrar árvore de elementos
    function showTree(el, depth = 0) {
        if (!el || depth > 6) return;
        const indent = '  '.repeat(depth);
        const tag = el.tagName?.toLowerCase() || 'text';
        const classes = el.className ? `.${el.className.split(' ').join('.')}` : '';
        const id = el.id ? `#${el.id}` : '';
        console.log(`${indent}${tag}${id}${classes}`);
    }
    
    console.log('%c📐 ESTRUTURA HTML:', 'font-size: 14px; font-weight: bold; color: #fff; background: #333; padding: 5px;');
    
    // Encontra elementos chave
    const body = document.body;
    const wpSiteBlocks = document.querySelector('.wp-site-blocks');
    const main = document.querySelector('main');
    const cartPage = document.querySelector('.gstore-cart-page, .Gstore-cart-page');
    const cartShell = document.querySelector('.Gstore-cart-shell');
    const cartContainer = document.querySelector('.Gstore-cart-container');
    const cartHeader = document.querySelector('.Gstore-cart-header');
    
    console.log('%cBody classes:', 'color: #86efac;', body.className);
    console.log('');
    
    // Mostra hierarquia
    console.log('%c🌳 HIERARQUIA DE ELEMENTOS:', 'font-size: 14px; font-weight: bold; color: #fff; background: #333; padding: 5px;');
    
    if (cartContainer) {
        let el = cartContainer;
        let path = [];
        while (el && el !== document.body) {
            const tag = el.tagName.toLowerCase();
            const cls = el.className ? '.' + el.className.split(' ').slice(0, 2).join('.') : '';
            path.unshift(`${tag}${cls}`);
            el = el.parentElement;
        }
        console.log('Caminho até .Gstore-cart-container:');
        path.forEach((p, i) => console.log('  '.repeat(i) + '└─ ' + p));
    }
    
    console.log('');
    console.log('%c📏 ESTILOS COMPUTADOS:', 'font-size: 14px; font-weight: bold; color: #fff; background: #333; padding: 5px;');
    
    const propsToCheck = ['width', 'maxWidth', 'marginLeft', 'marginRight', 'paddingLeft', 'paddingRight'];
    
    const elements = {
        'body': body,
        '.wp-site-blocks': wpSiteBlocks,
        'main': main,
        '.gstore-cart-page': cartPage,
        '.Gstore-cart-shell': cartShell,
        '.Gstore-cart-container': cartContainer,
        '.Gstore-cart-header': cartHeader
    };
    
    Object.entries(elements).forEach(([name, el]) => {
        if (el) {
            const cs = getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            console.log(`%c${name}:`, 'color: #fbbf24; font-weight: bold;');
            console.log(`  Largura real: ${rect.width}px`);
            console.log(`  max-width: ${cs.maxWidth}`);
            console.log(`  width: ${cs.width}`);
            console.log(`  margin: ${cs.marginTop} ${cs.marginRight} ${cs.marginBottom} ${cs.marginLeft}`);
            console.log(`  padding: ${cs.paddingTop} ${cs.paddingRight} ${cs.paddingBottom} ${cs.paddingLeft}`);
            console.log('');
        } else {
            console.log(`%c${name}: NÃO ENCONTRADO`, 'color: #f87171;');
        }
    });
    
    console.log('%c🔍 PROBLEMA DE CENTRALIZAÇÃO:', 'font-size: 14px; font-weight: bold; color: #fff; background: #dc2626; padding: 5px;');
    
    if (cartContainer) {
        const cs = getComputedStyle(cartContainer);
        const rect = cartContainer.getBoundingClientRect();
        const parentRect = cartContainer.parentElement.getBoundingClientRect();
        
        console.log(`Container largura: ${rect.width}px`);
        console.log(`Container max-width computado: ${cs.maxWidth}`);
        console.log(`Container margin-left: ${cs.marginLeft}`);
        console.log(`Container margin-right: ${cs.marginRight}`);
        console.log(`Parent largura: ${parentRect.width}px`);
        console.log(`Espaço à esquerda: ${rect.left}px`);
        console.log(`Espaço à direita: ${window.innerWidth - rect.right}px`);
        
        if (cs.marginLeft === '0px' && cs.marginRight === '0px') {
            console.log('%c⚠️ MARGIN AUTO NÃO ESTÁ FUNCIONANDO!', 'color: #f87171; font-weight: bold;');
            console.log('O container tem margin 0 em vez de auto. Alguma regra CSS está sobrescrevendo.');
        }
        
        if (rect.left < 50) {
            console.log('%c⚠️ CONTAINER ESTÁ COLADO À ESQUERDA!', 'color: #f87171; font-weight: bold;');
        }
    }
    
    // Verifica regras CSS que podem estar causando problema
    console.log('');
    console.log('%c🎨 VERIFICANDO REGRAS CSS:', 'font-size: 14px; font-weight: bold; color: #fff; background: #333; padding: 5px;');
    
    if (cartContainer) {
        // Tenta encontrar a regra que está aplicando margin
        const sheets = document.styleSheets;
        let foundRules = [];
        
        for (let sheet of sheets) {
            try {
                const rules = sheet.cssRules || sheet.rules;
                for (let rule of rules) {
                    if (rule.selectorText && rule.selectorText.includes('Gstore-cart')) {
                        if (rule.style.marginLeft || rule.style.marginRight || rule.style.margin) {
                            foundRules.push({
                                selector: rule.selectorText,
                                margin: rule.style.margin || `L:${rule.style.marginLeft} R:${rule.style.marginRight}`,
                                source: sheet.href || 'inline'
                            });
                        }
                    }
                }
            } catch(e) {}
        }
        
        if (foundRules.length > 0) {
            console.log('Regras CSS que afetam margin do carrinho:');
            foundRules.forEach(r => {
                console.log(`  ${r.selector}: margin ${r.margin}`);
                console.log(`    Fonte: ${r.source}`);
            });
        }
    }
    
    console.log('');
    console.log('%c✅ FIM DO DIAGNÓSTICO', 'font-size: 14px; font-weight: bold; color: #86efac;');
})();
SCRIPT;
}

/**
 * Renderiza overlay de debug na página do carrinho.
 */
function gstore_render_cart_debug_overlay() {
	if ( ! isset( $_GET['gstore_cart_debug'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	?>
	<div id="gstore-cart-debug-overlay" style="
		position: fixed;
		top: 32px;
		right: 20px;
		width: 400px;
		max-height: 80vh;
		background: #1d2327;
		color: #f0f0f1;
		border-radius: 8px;
		box-shadow: 0 10px 40px rgba(0,0,0,0.5);
		z-index: 999999;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		font-size: 13px;
		overflow: hidden;
	">
		<div style="background: #c9a43a; color: #000; padding: 12px 16px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
			<span>🛒 Debug do Carrinho</span>
			<button onclick="this.closest('#gstore-cart-debug-overlay').remove()" style="background: none; border: none; cursor: pointer; color: #000; display: flex; align-items: center; justify-content: center; padding: 4px;">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
			</button>
		</div>
		<div id="gstore-debug-content" style="padding: 16px; max-height: calc(80vh - 50px); overflow-y: auto;">
			<p style="margin: 0;">Carregando...</p>
		</div>
	</div>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const content = document.getElementById('gstore-debug-content');
		let html = '';
		
		function addSection(title) {
			html += `<h4 style="color: #c9a43a; margin: 16px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #3c434a;">${title}</h4>`;
		}
		
		function addRow(label, value, isError = false) {
			const color = isError ? '#f87171' : '#86efac';
			html += `<div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #3c434a;">
				<span style="color: #9ca3af;">${label}</span>
				<span style="color: ${color}; font-family: monospace;">${value}</span>
			</div>`;
		}
		
		// Body classes
		addSection('Body Classes');
		const bodyClasses = document.body.className.split(' ').filter(c => c.includes('cart') || c.includes('gstore') || c.includes('woocommerce'));
		html += `<code style="display: block; background: #000; padding: 8px; border-radius: 4px; word-break: break-all; font-size: 11px;">${bodyClasses.join(' ')}</code>`;
		
		// Elementos e seus estilos
		const elements = [
			{ name: '.Gstore-cart-shell', el: document.querySelector('.Gstore-cart-shell') },
			{ name: '.Gstore-cart-container', el: document.querySelector('.Gstore-cart-container') },
			{ name: '.Gstore-cart-header', el: document.querySelector('.Gstore-cart-header') },
		];
		
		elements.forEach(({name, el}) => {
			addSection(name);
			if (el) {
				const cs = getComputedStyle(el);
				const rect = el.getBoundingClientRect();
				addRow('Largura real', `${Math.round(rect.width)}px`);
				addRow('max-width', cs.maxWidth);
				addRow('margin-left', cs.marginLeft, cs.marginLeft === '0px');
				addRow('margin-right', cs.marginRight, cs.marginRight === '0px');
				addRow('padding-left', cs.paddingLeft);
				addRow('padding-right', cs.paddingRight);
				addRow('Posição X', `${Math.round(rect.left)}px`);
			} else {
				html += `<p style="color: #f87171;">Elemento não encontrado!</p>`;
			}
		});
		
		// Diagnóstico
		addSection('🔍 Diagnóstico');
		const container = document.querySelector('.Gstore-cart-container');
		if (container) {
			const cs = getComputedStyle(container);
			const rect = container.getBoundingClientRect();
			const isCentered = Math.abs((window.innerWidth - rect.width) / 2 - rect.left) < 50;
			
			if (isCentered) {
				html += `<p style="color: #86efac;">✅ Container está centralizado!</p>`;
			} else {
				html += `<p style="color: #f87171;">❌ Container NÃO está centralizado!</p>`;
				html += `<p style="color: #9ca3af; font-size: 12px;">Espaço esquerda: ${Math.round(rect.left)}px</p>`;
				html += `<p style="color: #9ca3af; font-size: 12px;">Espaço direita: ${Math.round(window.innerWidth - rect.right)}px</p>`;
				
				if (cs.marginLeft === '0px') {
					html += `<p style="color: #fbbf24;">⚠️ margin-left está 0px (deveria ser auto)</p>`;
				}
			}
		}
		
		// Hierarquia
		addSection('🌳 Hierarquia HTML');
		if (container) {
			let el = container;
			let path = [];
			while (el && el !== document.body) {
				const tag = el.tagName.toLowerCase();
				const cls = el.className ? '.' + el.className.split(' ')[0] : '';
				path.unshift(`${tag}${cls}`);
				el = el.parentElement;
			}
			html += `<code style="display: block; background: #000; padding: 8px; border-radius: 4px; font-size: 10px; line-height: 1.6;">`;
			path.forEach((p, i) => {
				html += `${'&nbsp;&nbsp;'.repeat(i)}└─ ${p}<br>`;
			});
			html += `</code>`;
		}
		
		content.innerHTML = html;
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'gstore_render_cart_debug_overlay' );

/**
 * Adiciona estilos críticos inline para garantir que os cards apareçam.
 * Funciona tanto com blocos quanto com loop clássico.
 */
function gstore_critical_product_styles() {
	if ( ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_search() ) {
		return;
	}
	?>
	<style id="Gstore-critical-styles">
		.Gstore-products-shell {
			background: #ffffff !important;
			padding: clamp(24px, 4vw, 64px) clamp(16px, 4vw, 48px);
		}

		.Gstore-products-shell .Gstore-products-grid {
			max-width: 1400px;
			margin: 0 auto;
			padding-left: var(--gstore-container-padding-inline, 20px);
			padding-right: var(--gstore-container-padding-inline, 20px);
		}

		.Gstore-products-grid ul.products,
		.Gstore-products-grid .wc-block-product-template,
		.Gstore-products-grid ul.wc-block-product-template {
			display: grid !important;
			grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
			gap: 24px !important;
			list-style: none !important;
			padding: 0 !important;
			margin: 0 !important;
			width: 100%;
		}

		.Gstore-products-grid .wc-block-product,
		.Gstore-products-grid li.wc-block-product,
		.Gstore-product-card {
			background: #fff !important;
			border-radius: 4px !important;
			border: 1px solid #e0e0e0 !important;
			display: flex !important;
			flex-direction: column !important;
			overflow: hidden !important;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
			transition: all 0.2s ease !important;
			position: relative !important;
			padding: 0 !important;
			margin: 0 !important;
			width: auto !important;
		}

		.Gstore-products-grid .wc-block-product:hover,
		.Gstore-product-card:hover {
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
			transform: translateY(-2px) !important;
		}

		.Gstore-products-grid .wc-block-product .has-text-align-center,
		.Gstore-products-grid .wc-block-product [data-text-align="center"],
		.Gstore-products-grid .wc-block-product .wp-block-post-title,
		.Gstore-products-grid .wc-block-product .wp-block-woocommerce-product-price,
		.Gstore-products-grid .wc-block-product .wc-block-components-product-price {
			text-align: left !important;
		}

		.Gstore-product-card__inner {
			background: #fff !important;
			border-radius: 4px !important;
			border: 1px solid #e0e0e0 !important;
			display: flex !important;
			flex-direction: column !important;
			overflow: hidden !important;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
			transition: all 0.2s ease !important;
			height: 100% !important;
		}

		.Gstore-products-grid ul.products li.product {
			margin: 0 !important;
			padding: 0 !important;
			width: auto !important;
			float: none !important;
		}

		@media (max-width: 1024px) {
			.Gstore-products-grid ul.products,
			.Gstore-products-grid .wc-block-product-template,
			.Gstore-products-grid ul.wc-block-product-template {
				grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
			}
		}

		@media (max-width: 640px) {
			.Gstore-products-grid ul.products,
			.Gstore-products-grid .wc-block-product-template,
			.Gstore-products-grid ul.wc-block-product-template {
				grid-template-columns: 1fr !important;
				gap: 16px !important;
			}

			.Gstore-product-card__inner,
			.Gstore-products-grid .wc-block-product {
				min-height: auto !important;
			}
		}
	</style>
	<?php
}
add_action( 'wp_head', 'gstore_critical_product_styles', 999 );

/**
 * Estilos e bibliotecas específicos do checkout.
 */
function gstore_enqueue_checkout_assets() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$theme_version = wp_get_theme()->get( 'Version' );

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		// CSS do checkout base
		wp_enqueue_style(
			'gstore-checkout',
			get_theme_file_uri( 'assets/css/checkout.css' ),
			array( 'gstore-style', 'gstore-fontawesome' ),
			$theme_version
		);

		// CSS do checkout em 3 etapas
		wp_enqueue_style(
			'gstore-checkout-steps',
			get_theme_file_uri( 'assets/css/checkout-steps.css' ),
			array( 'gstore-checkout' ),
			$theme_version
		);

		wp_enqueue_script(
			'gstore-checkout-cleanup',
			get_theme_file_uri( 'assets/js/checkout-cleanup.js' ),
			array(),
			$theme_version,
			true
		);

		wp_enqueue_script(
			'gstore-checkout-gestalt',
			get_theme_file_uri( 'assets/js/checkout-gestalt.js' ),
			array( 'jquery' ),
			$theme_version,
			true
		);

		// JavaScript do checkout em 3 etapas
		wp_enqueue_script(
			'gstore-checkout-steps',
			get_theme_file_uri( 'assets/js/checkout-steps.js' ),
			array( 'jquery' ),
			$theme_version,
			true
		);

		// Fornece nonce correto para processar checkout (fallback do JS quando o input não existir no DOM).
		wp_localize_script(
			'gstore-checkout-steps',
			'gstoreCheckout',
			array(
				'processCheckoutNonce' => wp_create_nonce( 'woocommerce-process_checkout' ),
			)
		);

		// CSS do Pix
		wp_enqueue_style(
			'gstore-checkout-pix',
			get_theme_file_uri( 'assets/css/checkout-pix.css' ),
			array( 'gstore-checkout' ),
			$theme_version
		);

		// JavaScript do Pix
		wp_enqueue_script(
			'gstore-checkout-pix',
			get_theme_file_uri( 'assets/js/checkout-pix.js' ),
			array( 'jquery' ),
			$theme_version,
			true
		);

		// Localiza script do Pix
		wp_localize_script(
			'gstore-checkout-pix',
			'gstorePix',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gstore_pix_nonce' ),
			)
		);
	}

	if ( function_exists( 'is_cart' ) && is_cart() ) {
		wp_enqueue_style(
			'gstore-cart',
			get_theme_file_uri( 'assets/css/cart.css' ),
			array( 'gstore-style', 'gstore-fontawesome' ),
			$theme_version
		);
	}

	// CSS do Pix também na página de obrigado e visualização do pedido
	if ( ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) || ( function_exists( 'is_view_order_page' ) && is_view_order_page() ) ) {
		wp_enqueue_style(
			'gstore-checkout-pix',
			get_theme_file_uri( 'assets/css/checkout-pix.css' ),
			array( 'gstore-style' ),
			$theme_version
		);

		// JavaScript do Pix para página de obrigado e visualização do pedido
		wp_enqueue_script(
			'gstore-checkout-pix',
			get_theme_file_uri( 'assets/js/checkout-pix.js' ),
			array( 'jquery' ),
			$theme_version,
			true
		);

		// Localiza script do Pix
		wp_localize_script(
			'gstore-checkout-pix',
			'gstorePix',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gstore_pix_nonce' ),
			)
		);
	}
    
    // Script de Auto-fill do CEP
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        wp_enqueue_script(
            'gstore-cep-autofill',
            get_theme_file_uri( 'assets/js/cep-autofill.js' ),
            array( 'jquery' ),
            $theme_version,
            true
        );
    }

	// Calculador de Frete - Página de Produto Único e Checkout
	if ( ( function_exists( 'is_product' ) && is_product() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
		// CSS do calculador
		wp_enqueue_style(
			'gstore-shipping-calculator',
			get_theme_file_uri( 'assets/css/shipping-calculator.css' ),
			array( 'gstore-style' ),
			$theme_version
		);

		// JavaScript do calculador
		wp_enqueue_script(
			'gstore-shipping-calculator',
			get_theme_file_uri( 'assets/js/shipping-calculator.js' ),
			array( 'jquery' ),
			$theme_version,
			true
		);

		// Localiza script do calculador
		global $product;
		$product_id = 0;
		if ( is_product() && $product ) {
			$product_id = $product->get_id();
		}

		wp_localize_script(
			'gstore-shipping-calculator',
			'gstoreShippingCalculator',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'gstore_shipping_calculator' ),
				'productId'  => $product_id,
				'i18n'       => array(
					'calculate'        => __( 'Calcular frete', 'gstore' ),
					'calculating'      => __( 'Calculando...', 'gstore' ),
					'invalidCep'       => __( 'CEP inválido. Por favor, informe um CEP válido com 8 dígitos.', 'gstore' ),
					'error'            => __( 'Erro ao calcular frete. Tente novamente.', 'gstore' ),
					'frete'            => __( 'Frete', 'gstore' ),
					'region'           => __( 'Região', 'gstore' ),
					'estimatedDelivery' => __( 'Prazo estimado', 'gstore' ),
					'days'             => __( 'dias úteis', 'gstore' ),
				),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'gstore_enqueue_checkout_assets', 40 );

/**
 * Move o texto de privacidade para baixo do botão de finalizar compra.
 */
function gstore_move_privacy_policy_text() {
    remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
    add_action( 'woocommerce_review_order_after_submit', 'wc_checkout_privacy_policy_text', 20 );
}
add_action( 'init', 'gstore_move_privacy_policy_text' );

/**
 * Customiza os campos do checkout:
 * - Remove o campo de país
 * - Move o CEP para o topo
 */
function gstore_customize_checkout_fields( $fields ) {
    // Remover país
    unset( $fields['billing']['billing_country'] );
    unset( $fields['shipping']['shipping_country'] );

    // Verificar se o gateway Blu está disponível
    $blu_gateway_available = false;
    $payment_gateways = array();
    if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
        $payment_gateways = WC()->payment_gateways()->payment_gateways();
        if ( isset( $payment_gateways['blu_checkout'] ) && $payment_gateways['blu_checkout']->is_available() ) {
            $blu_gateway_available = true;
        }
    }

	// Detecta método selecionado (usa sessão do WooCommerce)
	$selected_payment_method = '';
	if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC()->session ) {
		$selected_payment_method = (string) WC()->session->get( 'chosen_payment_method', '' );
	}

    // Se gateway Blu está disponível, torna campos de endereço opcionais para pré-checkout
    // MAS: Se o método selecionado for blu_pix, mantém campos obrigatórios (checkout completo)
    if ( $blu_gateway_available && $selected_payment_method !== 'blu_pix' ) {
        // Campos de endereço tornam-se opcionais
        $address_fields = array(
            'billing_postcode',
            'billing_address_1',
            'billing_number',
            'billing_address_2',
            'billing_neighborhood',
            'billing_city',
            'billing_state',
        );

        foreach ( $address_fields as $field_key ) {
            if ( isset( $fields['billing'][ $field_key ] ) ) {
                $fields['billing'][ $field_key ]['required'] = false;
            }
        }

        // Campos de nome e CPF também opcionais (serão coletados na Blu)
        if ( isset( $fields['billing']['billing_first_name'] ) ) {
            $fields['billing']['billing_first_name']['required'] = false;
        }
        if ( isset( $fields['billing']['billing_last_name'] ) ) {
            $fields['billing']['billing_last_name']['required'] = false;
        }
        if ( isset( $fields['billing']['billing_cpf'] ) ) {
            $fields['billing']['billing_cpf']['required'] = false;
        }

        // Mantém apenas email e telefone como obrigatórios no pré-checkout
        // (WooCommerce já define email como obrigatório por padrão)
    } else if ( $selected_payment_method === 'blu_pix' ) {
        // Para PIX, todos os campos principais são obrigatórios
        // Apenas o complemento (billing_address_2) permanece opcional
        
        // Campos obrigatórios para PIX
        $required_fields = array(
            'billing_first_name',    // Nome
            'billing_last_name',     // Sobrenome
            'billing_cpf',           // CPF
            'billing_phone',         // Telefone
            'billing_address_1',     // Endereço
            'billing_city',          // Cidade
            'billing_state',         // Estado
            'billing_postcode',      // CEP
        );

        foreach ( $required_fields as $field_key ) {
            if ( isset( $fields['billing'][ $field_key ] ) ) {
                $fields['billing'][ $field_key ]['required'] = true;
            }
        }

        // Garantir que o complemento (billing_address_2) permanece opcional
        if ( isset( $fields['billing']['billing_address_2'] ) ) {
            $fields['billing']['billing_address_2']['required'] = false;
        }
    }

    // Reordenar CEP para o topo da seção de endereço (prioridade 45, logo após CPF que é 35)
    if ( isset( $fields['billing']['billing_postcode'] ) ) {
        $fields['billing']['billing_postcode']['priority'] = 45;
        $fields['billing']['billing_postcode']['class'] = array('form-row-wide', 'address-field');
        $fields['billing']['billing_postcode']['clear'] = true;
    }
    
    if ( isset( $fields['shipping']['shipping_postcode'] ) ) {
        $fields['shipping']['shipping_postcode']['priority'] = 45;
        $fields['shipping']['shipping_postcode']['class'] = array('form-row-wide', 'address-field');
        $fields['shipping']['shipping_postcode']['clear'] = true;
    }

    // Ajustar prioridade do endereço para vir depois do CEP
    if ( isset( $fields['billing']['billing_address_1'] ) ) {
        $fields['billing']['billing_address_1']['priority'] = 50;
    }
    
    if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
        $fields['shipping']['shipping_address_1']['priority'] = 50;
    }

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'gstore_customize_checkout_fields', 1000 );

/**
 * ================================
 * BLU: Parcelas fixas + Taxa (admin)
 * ================================
 */

/**
 * Captura estado do checkout (método + parcelas) a cada update_order_review e armazena na sessão.
 *
 * @param string $post_data Querystring enviada pelo WooCommerce.
 */
function gstore_blu_capture_checkout_state( $post_data ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$parsed = array();
	parse_str( (string) $post_data, $parsed );

	if ( isset( $parsed['payment_method'] ) ) {
		$method = sanitize_text_field( wp_unslash( $parsed['payment_method'] ) );
		if ( $method ) {
			WC()->session->set( 'chosen_payment_method', $method );
		}
	}

	if ( isset( $parsed['gstore_blu_installments'] ) ) {
		$installments = absint( $parsed['gstore_blu_installments'] );
		if ( $installments < 1 ) {
			$installments = 1;
		}
		WC()->session->set( 'gstore_blu_installments', $installments );
	}
}
add_action( 'woocommerce_checkout_update_order_review', 'gstore_blu_capture_checkout_state', 10, 1 );

/**
 * Recupera parcelas selecionadas na sessão (fallback 1).
 *
 * @return int
 */
function gstore_blu_get_selected_installments() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return 1;
	}
	$value = absint( WC()->session->get( 'gstore_blu_installments', 1 ) );
	return $value >= 1 ? $value : 1;
}

/**
 * Renderiza seletor de parcelas do cartão (pré-checkout) quando Blu Checkout estiver disponível.
 */
function gstore_blu_render_installments_selector() {
	if ( ! function_exists( 'WC' ) ) {
		return;
	}

	$gateway = function_exists( 'gstore_blu_get_gateway_instance' ) ? gstore_blu_get_gateway_instance() : null;
	if ( ! $gateway || ! method_exists( $gateway, 'allow_customer_installments' ) ) {
		return;
	}

	if ( ! $gateway->is_available() ) {
		return;
	}

	$allow = $gateway->allow_customer_installments();
	$max   = method_exists( $gateway, 'get_max_installments' ) ? (int) $gateway->get_max_installments() : 21;
	$max   = max( 1, min( 21, $max ) );

	$selected = gstore_blu_get_selected_installments();
	$selected = max( 1, min( $max, $selected ) );

	?>
	<div class="Gstore-blu-installments" data-allow="<?php echo esc_attr( $allow ? '1' : '0' ); ?>" data-max="<?php echo esc_attr( (string) $max ); ?>">
		<input type="hidden" name="gstore_blu_installments" id="gstore_blu_installments" value="<?php echo esc_attr( (string) $selected ); ?>" />
		<?php if ( $allow ) : ?>
			<div class="Gstore-blu-installments__row">
				<label for="gstore_blu_installments_select" class="Gstore-blu-installments__label">
					<?php esc_html_e( 'Parcelamento no cartão', 'gstore' ); ?>
				</label>
				<select id="gstore_blu_installments_select" class="Gstore-blu-installments__select">
					<?php for ( $i = 1; $i <= $max; $i++ ) : ?>
						<option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( $selected, $i ); ?>>
							<?php echo esc_html( sprintf( _n( '%sx', '%sx', $i, 'gstore' ), $i ) ); ?>
						</option>
					<?php endfor; ?>
				</select>
			</div>
			<p class="Gstore-blu-installments__hint">
				<?php esc_html_e( 'Ao gerar o link de pagamento, as parcelas ficam fixas. Para alterar, volte ao carrinho.', 'gstore' ); ?>
			</p>
			<p class="Gstore-blu-installments__preview" aria-live="polite"></p>
		<?php else : ?>
			<p class="Gstore-blu-installments__hint">
				<?php esc_html_e( 'As parcelas estão fixas para este pagamento.', 'gstore' ); ?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'woocommerce_review_order_before_payment', 'gstore_blu_render_installments_selector', 12 );

/**
 * Adiciona fee de parcelamento (configurável no gateway Blu) quando Cartão Blu estiver selecionado.
 *
 * @param WC_Cart $cart Carrinho.
 */
function gstore_blu_add_installment_fee( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	if ( ! $cart instanceof WC_Cart || ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$chosen_method = (string) WC()->session->get( 'chosen_payment_method', '' );
	if ( 'blu_checkout' !== $chosen_method ) {
		// Em alguns fluxos (AJAX update_order_review / UI custom), o método vem no POST.
		$posted_method = '';
		if ( isset( $_POST['payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$posted_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$parsed = array();
			parse_str( (string) wp_unslash( $_POST['post_data'] ), $parsed ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $parsed['payment_method'] ) ) {
				$posted_method = sanitize_text_field( (string) $parsed['payment_method'] );
			}
		}

		if ( 'blu_checkout' !== $posted_method ) {
			return;
		}
		$chosen_method = 'blu_checkout';
	}

	$gateway = function_exists( 'gstore_blu_get_gateway_instance' ) ? gstore_blu_get_gateway_instance() : null;
	if ( ! $gateway || ! method_exists( $gateway, 'get_installment_fee_config' ) ) {
		return;
	}

	$installments = gstore_blu_get_selected_installments();
	$config       = (array) $gateway->get_installment_fee_config();

	$min_installments = isset( $config['min_installments'] ) ? absint( $config['min_installments'] ) : 2;
	$min_installments = max( 1, $min_installments );
	if ( $installments < $min_installments ) {
		return;
	}

	$mode     = isset( $config['mode'] ) ? (string) $config['mode'] : 'none';
	$strategy = isset( $config['strategy'] ) ? (string) $config['strategy'] : 'flat';
	$percent  = isset( $config['percent'] ) ? (float) $config['percent'] : 0.0;
	$fixed    = isset( $config['fixed'] ) ? (float) $config['fixed'] : 0.0;

	if ( 'none' === $mode ) {
		return;
	}

	// Base: total de produtos + frete - descontos (sem incluir fees anteriores)
	$cart_contents_total = (float) $cart->get_cart_contents_total();
	$shipping_total      = (float) $cart->get_shipping_total();
	$discount_total      = (float) $cart->get_discount_total();

	// Neste tema, o frete pode entrar como "fee" (ex.: label "Frete") e não como shipping_total.
	$shipping_fee_total = 0.0;
	if ( $shipping_total <= 0 ) {
		foreach ( $cart->get_fees() as $fee ) {
			$fee_name = isset( $fee->name ) ? (string) $fee->name : '';
			if ( '' === $fee_name ) {
				continue;
			}

			// Ignora a própria taxa de parcelamento.
			if ( false !== stripos( $fee_name, 'taxa de parcelamento' ) ) {
				continue;
			}

			// Heurística: fee de frete normalmente é "Frete" (ou contém "frete").
			if ( 'frete' === sanitize_title( $fee_name ) || false !== stripos( $fee_name, 'frete' ) ) {
				$shipping_fee_total = isset( $fee->total ) ? (float) $fee->total : 0.0;
				break;
			}
		}
	}

	$base = $cart_contents_total + ( $shipping_total > 0 ? $shipping_total : $shipping_fee_total ) - $discount_total;
	if ( $base < 0 ) {
		$base = 0;
	}

	$fee_value = 0.0;

	// Resolve percent/fixed conforme estratégia
	$resolved_percent = $percent;
	$resolved_fixed   = $fixed;

	if ( 'progressive' === $strategy ) {
		$prog = isset( $config['progressive'] ) && is_array( $config['progressive'] ) ? $config['progressive'] : array();
		$base_percent = isset( $prog['base_percent'] ) ? (float) $prog['base_percent'] : 0.0;
		$step_percent = isset( $prog['step_percent'] ) ? (float) $prog['step_percent'] : 0.0;
		$base_fixed   = isset( $prog['base_fixed'] ) ? (float) $prog['base_fixed'] : 0.0;
		$step_fixed   = isset( $prog['step_fixed'] ) ? (float) $prog['step_fixed'] : 0.0;

		$steps = max( 0, $installments - $min_installments );
		$resolved_percent = $base_percent + ( $step_percent * $steps );
		$resolved_fixed   = $base_fixed + ( $step_fixed * $steps );
	} elseif ( 'table' === $strategy ) {
		$rules_text = isset( $config['rules'] ) ? (string) $config['rules'] : '';
		$lines = preg_split( "/\r\n|\n|\r/", $rules_text );
		$rules = array(); // installments => [percent, fixed]

		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}
				// Remove comentários
				$line = preg_replace( '/\s*#.*$/', '', $line );
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}

				// Formatos:
				// 2=3.5
				// 3=4%+2.90
				// 6=+9.90
				if ( preg_match( '/^(\d+)\s*=\s*(.+)$/', $line, $m ) ) {
					$n = absint( $m[1] );
					$expr = trim( (string) $m[2] );
					if ( $n < 1 || $n > 21 || '' === $expr ) {
						continue;
					}

					$p = 0.0;
					$f = 0.0;

					// only fixed: +9.90 or 9.90? (we require + for fixed-only to avoid ambiguity)
					if ( preg_match( '/^\+\s*([0-9]+(?:[.,][0-9]+)?)$/', $expr, $mm ) ) {
						$f = (float) str_replace( ',', '.', $mm[1] );
					} else {
						// percent or percent+fixed
						if ( preg_match( '/^([0-9]+(?:[.,][0-9]+)?)\s*%?\s*(?:\+\s*([0-9]+(?:[.,][0-9]+)?))?$/', $expr, $mm ) ) {
							$p = (float) str_replace( ',', '.', $mm[1] );
							if ( isset( $mm[2] ) && '' !== $mm[2] ) {
								$f = (float) str_replace( ',', '.', $mm[2] );
							}
						}
					}

					$rules[ $n ] = array( 'percent' => $p, 'fixed' => $f );
				}
			}
		}

		// Regra exata; se não existir, usa a mais próxima abaixo; se não, zero.
		if ( isset( $rules[ $installments ] ) ) {
			$resolved_percent = (float) $rules[ $installments ]['percent'];
			$resolved_fixed   = (float) $rules[ $installments ]['fixed'];
		} else {
			$best = 0;
			foreach ( array_keys( $rules ) as $n ) {
				$n = (int) $n;
				if ( $n <= $installments && $n > $best ) {
					$best = $n;
				}
			}
			if ( $best > 0 ) {
				$resolved_percent = (float) $rules[ $best ]['percent'];
				$resolved_fixed   = (float) $rules[ $best ]['fixed'];
			} else {
				$resolved_percent = 0.0;
				$resolved_fixed   = 0.0;
			}
		}
	}

	if ( 'percent' === $mode || 'percent_plus_fixed' === $mode ) {
		if ( $resolved_percent > 0 ) {
			$fee_value += ( $base * ( $resolved_percent / 100 ) );
		}
	}
	if ( 'fixed' === $mode || 'percent_plus_fixed' === $mode ) {
		if ( $resolved_fixed > 0 ) {
			$fee_value += $resolved_fixed;
		}
	}

	$fee_value = round( $fee_value, wc_get_price_decimals() );
	if ( $fee_value <= 0 ) {
		return;
	}

	$cart->add_fee( __( 'Taxa de parcelamento', 'gstore' ), $fee_value, false, '' );
}
add_action( 'woocommerce_cart_calculate_fees', 'gstore_blu_add_installment_fee', 30, 1 );

/**
 * Valida parcelas quando o cliente escolhe Cartão Blu.
 */
function gstore_blu_validate_installments() {
	if ( empty( $_POST['payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( 'blu_checkout' !== $method ) {
		return;
	}

	$gateway = function_exists( 'gstore_blu_get_gateway_instance' ) ? gstore_blu_get_gateway_instance() : null;
	if ( ! $gateway || ! method_exists( $gateway, 'allow_customer_installments' ) ) {
		return;
	}

	$max = method_exists( $gateway, 'get_max_installments' ) ? (int) $gateway->get_max_installments() : 21;
	$max = max( 1, min( 21, $max ) );

	$installments = isset( $_POST['gstore_blu_installments'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? absint( $_POST['gstore_blu_installments'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: 1;

	if ( $installments < 1 || $installments > $max ) {
		wc_add_notice( __( 'Selecione um número válido de parcelas para continuar.', 'gstore' ), 'error' );
	}
}
add_action( 'woocommerce_checkout_process', 'gstore_blu_validate_installments', 12 );

/**
 * Salva parcelas e taxa no pedido (meta) para travar no payload da Blu.
 *
 * @param int $order_id Order ID.
 */
function gstore_blu_save_installments_meta( $order_id ) {
	if ( ! $order_id || ! function_exists( 'WC' ) ) {
		return;
	}

	$method = get_post_meta( $order_id, '_payment_method', true );
	if ( 'blu_checkout' !== $method ) {
		return;
	}

	$gateway = function_exists( 'gstore_blu_get_gateway_instance' ) ? gstore_blu_get_gateway_instance() : null;
	$max     = ( $gateway && method_exists( $gateway, 'get_max_installments' ) ) ? (int) $gateway->get_max_installments() : 21;
	$max     = max( 1, min( 21, $max ) );

	$installments = isset( $_POST['gstore_blu_installments'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? absint( $_POST['gstore_blu_installments'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: gstore_blu_get_selected_installments();

	$installments = max( 1, min( $max, $installments ) );

	update_post_meta( $order_id, '_gstore_blu_installments', (string) $installments );

	// Persiste o valor da fee (se existir) para auditoria
	$fee_value = 0.0;
	if ( WC()->cart ) {
		foreach ( WC()->cart->get_fees() as $fee ) {
			if ( isset( $fee->name ) && stripos( (string) $fee->name, 'taxa de parcelamento' ) !== false ) {
				$fee_value = (float) $fee->total;
				break;
			}
		}
	}
	if ( $fee_value > 0 ) {
		update_post_meta( $order_id, '_gstore_blu_installment_fee', (string) $fee_value );
	}
}
add_action( 'woocommerce_checkout_update_order_meta', 'gstore_blu_save_installments_meta', 20 );

/**
 * Desabilita validação de CEP quando o campo não é obrigatório no pré-checkout
 */
function gstore_validate_postcode_optional( $valid, $postcode, $country ) {
	// Limpa o CEP para verificar se está vazio
	$postcode_clean = preg_replace( '/[^0-9]/', '', $postcode );
	
	// Se o CEP está vazio e a validação falhou, verifica se o campo é obrigatório
	if ( ! $valid && empty( $postcode_clean ) && class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
		// Verifica se o gateway Blu está disponível
		$blu_gateway_available = false;
		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $payment_gateways['blu_checkout'] ) && $payment_gateways['blu_checkout']->is_available() ) {
			$blu_gateway_available = true;
		}
		
		// Se o gateway Blu está disponível, verifica se o CEP é obrigatório
		if ( $blu_gateway_available ) {
			// Obtém os campos do checkout
			$checkout_fields = WC()->checkout()->get_checkout_fields();
			
			// Verifica se o campo billing_postcode existe e se é obrigatório
			if ( isset( $checkout_fields['billing']['billing_postcode'] ) ) {
				$is_required = isset( $checkout_fields['billing']['billing_postcode']['required'] ) && 
				               $checkout_fields['billing']['billing_postcode']['required'];
				
				// Se o campo não é obrigatório e está vazio, considera válido
				if ( ! $is_required ) {
					return true;
				}
			}
		}
	}
	
	return $valid;
}
add_filter( 'woocommerce_validate_postcode', 'gstore_validate_postcode_optional', 999, 3 );

/**
 * Verifica se as páginas essenciais existem na inicialização.
 * 
 * Nota: Use o menu "Setup Gstore" para criar todas as páginas de uma vez.
 * Esta função apenas cria páginas essenciais do WooCommerce se não existirem.
 */
function gstore_check_essential_pages() {
	// Só roda no admin para evitar sobrecarga no frontend
	if ( ! is_admin() ) {
		return;
	}
	
	// Só verifica uma vez por sessão usando transient
	$checked = get_transient( 'gstore_pages_checked' );
	if ( $checked ) {
		return;
	}
	
	// Verifica se a página de catálogo existe (para compatibilidade com versões anteriores)
	$catalog_page = get_page_by_path( 'catalogo' );
	if ( ! $catalog_page ) {
		wp_insert_post( array(
			'post_title'   => 'Catálogo',
			'post_name'    => 'catalogo',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		) );
	}
	
	// Define transient para não verificar novamente por 1 hora
	set_transient( 'gstore_pages_checked', true, HOUR_IN_SECONDS );
}
add_action( 'admin_init', 'gstore_check_essential_pages' );

/**
 * Gateway Blu (Link de Pagamento).
 */
if ( class_exists( 'WooCommerce' ) && class_exists( 'WC_Payment_Gateway' ) ) {
	require_once get_theme_file_path( 'inc/class-gstore-blu-payment-gateway.php' );
	
	// Carrega gateway Pix apenas se o arquivo existir
	$pix_gateway_file = get_theme_file_path( 'inc/class-gstore-blu-pix-gateway.php' );
	if ( file_exists( $pix_gateway_file ) ) {
		// Força invalidação do OPcache para garantir código atualizado
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $pix_gateway_file, true );
		}
		require_once $pix_gateway_file;
	}
}

/**
 * Correção temporária: Corrige o status do Pix via JavaScript.
 * O status 'processed' significa apenas que a cobrança foi criada, não que foi paga.
 * Apenas 'paid' deve mostrar "Pagamento aprovado".
 */
add_action( 'wp_footer', function() {
	if ( ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'view-order' ) ) {
		return;
	}
	?>
	<script>
	(function() {
		// Corrige o status do Pix - apenas 'paid' deve mostrar como aprovado
		var statusMeta = document.querySelector('.pix-box__meta--muted');
		var statusBadge = document.querySelector('.pix-box__status');
		var pixBox = document.querySelector('.pix-box');
		
		if (statusMeta && statusBadge && pixBox) {
			var statusText = statusMeta.textContent || '';
			var match = statusText.match(/Status:\s*(\w+)/i);
			if (match) {
				var realStatus = match[1].toLowerCase();
				// Apenas 'paid' é pagamento aprovado
				if (realStatus !== 'paid') {
					statusBadge.textContent = 'AGUARDANDO PAGAMENTO';
					pixBox.classList.remove('pix-box--processed');
					pixBox.classList.add('pix-box--pending');
				}
			}
		}
	})();
	</script>
	<?php
}, 999 );

/**
 * Filtro para deixar apenas a Blu como gateway (Opcional/Solicitado).
 */
require_once get_theme_file_path( 'inc/blu-filter.php' );

/**
 * Filtro de Categorias Marketplace.
 */
require_once get_theme_file_path( 'inc/class-gstore-category-filter.php' );

/**
 * Gerenciador de informações da loja (JSON centralizado).
 */
require_once get_theme_file_path( 'inc/class-gstore-store-info.php' );

/**
 * API Visualizer (React + Mermaid) - shortcode e enqueue dos assets.
 */
require_once get_theme_file_path( 'inc/api-visualizer.php' );

/**
 * Método de envio customizado Gstore.
 */
if ( class_exists( 'WooCommerce' ) && class_exists( 'WC_Shipping_Method' ) ) {
	require_once get_theme_file_path( 'inc/class-gstore-shipping-method.php' );
	require_once get_theme_file_path( 'inc/class-gstore-shipping-admin.php' );

	/**
	 * Registra o método de envio customizado.
	 *
	 * @param array $methods Métodos de envio.
	 * @return array
	 */
	function gstore_add_shipping_method( $methods ) {
		$methods['gstore_custom_shipping'] = 'Gstore_Shipping_Method';
		return $methods;
	}
	add_filter( 'woocommerce_shipping_methods', 'gstore_add_shipping_method' );

	/**
	 * Inicializa a classe admin de configuração de frete.
	 */
	function gstore_init_shipping_admin() {
		if ( class_exists( 'Gstore_Shipping_Admin' ) ) {
			new Gstore_Shipping_Admin();
		}
	}
	add_action( 'init', 'gstore_init_shipping_admin' );

	/**
	 * Injeta o frete customizado Gstore nas taxas de envio do pacote.
	 * Isso garante que o frete apareça mesmo sem zona de envio configurada.
	 *
	 * @param array $rates Taxas de envio disponíveis.
	 * @param array $package Pacote de produtos.
	 * @return array Taxas de envio modificadas.
	 */
	function gstore_inject_shipping_rate( $rates, $package ) {
		// Verifica se já existe uma taxa do Gstore
		foreach ( $rates as $rate ) {
			if ( strpos( $rate->get_method_id(), 'gstore_custom_shipping' ) !== false ) {
				return $rates; // Já existe, não precisa injetar
			}
		}

		// Obtém o CEP do pacote de destino
		$postcode = '';
		$state    = '';
		
		if ( isset( $package['destination']['postcode'] ) ) {
			$postcode = preg_replace( '/[^0-9]/', '', $package['destination']['postcode'] );
		}
		if ( isset( $package['destination']['state'] ) ) {
			$state = strtoupper( $package['destination']['state'] );
		}

		// Se não tem CEP nem estado, não calcula
		if ( empty( $postcode ) && empty( $state ) ) {
			return $rates;
		}

		// Verifica se há produtos no pacote
		if ( empty( $package['contents'] ) ) {
			return $rates;
		}

		// Instancia o método de envio
		$shipping_method = new Gstore_Shipping_Method();

		// Calcula o peso tático
		$total_weight = $shipping_method->calculate_tactical_weight_public( $package['contents'] );

		if ( $total_weight <= 0 ) {
			return $rates;
		}

		// Identifica a região
		$region = gstore_get_shipping_region( $state, $postcode );

		// Obtém o custo do frete
		$shipping_cost = $shipping_method->get_shipping_cost_public( $total_weight, $region );

		if ( $shipping_cost === false ) {
			return $rates;
		}

		// Cria a taxa de envio
		$rate = new WC_Shipping_Rate(
			'gstore_custom_shipping',
			__( 'Frete Gstore', 'gstore' ),
			$shipping_cost,
			array(),
			'gstore_custom_shipping'
		);

		// Adiciona metadados úteis
		$rate->add_meta_data( 'region', $region );
		$rate->add_meta_data( 'weight', $total_weight );

		// Adiciona a taxa ao início do array
		$rates = array( 'gstore_custom_shipping' => $rate ) + $rates;

		return $rates;
	}
	add_filter( 'woocommerce_package_rates', 'gstore_inject_shipping_rate', 10, 2 );

	/**
	 * Força o WooCommerce a recalcular o frete quando o CEP muda.
	 * Limpa o cache de shipping quando necessário.
	 *
	 * @param string $post_data Dados do formulário de checkout.
	 */
	function gstore_clear_shipping_cache_on_cep_change( $post_data ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		// Parse dos dados do formulário
		$posted = array();
		if ( ! empty( $post_data ) ) {
			parse_str( $post_data, $posted );
		}

		// Obtém o CEP do POST
		$postcode = '';
		if ( isset( $posted['billing_postcode'] ) ) {
			$postcode = preg_replace( '/[^0-9]/', '', $posted['billing_postcode'] );
		}

		// Se tem CEP válido, atualiza a sessão
		if ( strlen( $postcode ) === 8 ) {
			// Identifica o estado
			$state = gstore_get_state_from_postcode( $postcode );
			
			// Atualiza o cliente
			if ( WC()->customer ) {
				WC()->customer->set_billing_postcode( $postcode );
				WC()->customer->set_shipping_postcode( $postcode );
				WC()->customer->set_billing_country( 'BR' );
				WC()->customer->set_shipping_country( 'BR' );
				
				if ( ! empty( $state ) ) {
					WC()->customer->set_billing_state( $state );
					WC()->customer->set_shipping_state( $state );
				}
				
				WC()->customer->set_calculated_shipping( true );
			}
			
			// Define o método de envio como selecionado
			WC()->session->set( 'chosen_shipping_methods', array( 'gstore_custom_shipping' ) );
		}

		// Limpa cache de shipping rates para forçar recálculo
		$packages = WC()->cart->get_shipping_packages();
		foreach ( $packages as $key => $package ) {
			WC()->session->set( 'shipping_for_package_' . $key, false );
		}
	}
	add_action( 'woocommerce_checkout_update_order_review', 'gstore_clear_shipping_cache_on_cep_change' );

	/**
	 * Seleciona automaticamente o método de envio Gstore quando disponível.
	 * Isso garante que o frete seja incluído no total do pedido.
	 *
	 * @param string $default Método padrão.
	 * @param array  $rates Taxas disponíveis.
	 * @param string $chosen_method Método escolhido atual.
	 * @return string Método selecionado.
	 */
	function gstore_auto_select_shipping_method( $default, $rates, $chosen_method ) {
		// Se já tem um método escolhido que existe nas rates, mantém
		if ( ! empty( $chosen_method ) && isset( $rates[ $chosen_method ] ) ) {
			return $chosen_method;
		}

		// Se o método Gstore está disponível, seleciona automaticamente
		if ( isset( $rates['gstore_custom_shipping'] ) ) {
			return 'gstore_custom_shipping';
		}

		return $default;
	}
	add_filter( 'woocommerce_shipping_chosen_method', 'gstore_auto_select_shipping_method', 10, 3 );

	/**
	 * Garante que o método de envio Gstore seja pré-selecionado na sessão.
	 * Executado após o cálculo do shipping.
	 */
	function gstore_ensure_shipping_method_selected() {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );
		
		// Se não tem método escolhido ou está vazio
		if ( empty( $chosen_methods ) || empty( $chosen_methods[0] ) ) {
			// Obtém as taxas de shipping disponíveis
			$packages = WC()->shipping()->get_packages();
			
			if ( ! empty( $packages ) ) {
				foreach ( $packages as $i => $package ) {
					if ( isset( $package['rates']['gstore_custom_shipping'] ) ) {
						$chosen_methods[ $i ] = 'gstore_custom_shipping';
					}
				}
				
				if ( ! empty( $chosen_methods ) ) {
					WC()->session->set( 'chosen_shipping_methods', $chosen_methods );
				}
			}
		}
	}
	add_action( 'woocommerce_after_calculate_totals', 'gstore_ensure_shipping_method_selected', 20 );

	/**
	 * Adiciona o frete Gstore diretamente como taxa no carrinho.
	 * Isso contorna a necessidade de zonas de envio configuradas.
	 *
	 * @param WC_Cart $cart Instância do carrinho.
	 */
	function gstore_add_shipping_as_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Obtém o CEP da sessão do cliente
		if ( ! WC()->customer ) {
			return;
		}

		$postcode = WC()->customer->get_shipping_postcode();
		if ( empty( $postcode ) ) {
			$postcode = WC()->customer->get_billing_postcode();
		}

		// Limpa o CEP
		$postcode = preg_replace( '/[^0-9]/', '', $postcode );

		// Só calcula se tem CEP válido
		if ( strlen( $postcode ) !== 8 ) {
			return;
		}

		// Verifica se já existe uma taxa de frete (evita duplicar mesmo com múltiplos calculate_totals no mesmo request)
		$fees = $cart->get_fees();
		foreach ( $fees as $fee ) {
			$fee_id   = isset( $fee->id ) ? (string) $fee->id : '';
			$fee_name = isset( $fee->name ) ? (string) $fee->name : '';

			// O WooCommerce costuma gerar id a partir do nome (ex: "Frete" -> "frete").
			// Também aceitamos match por nome, porque alguns ambientes não expõem id consistentemente.
			if ( '' !== $fee_id && 'frete' === sanitize_title( $fee_id ) ) {
				return;
			}
			if ( '' !== $fee_name && false !== stripos( $fee_name, 'frete' ) ) {
				return;
			}
		}

		// Identifica a região
		$region = gstore_get_shipping_region( '', $postcode );

		// Calcula o peso tático
		$shipping_method = new Gstore_Shipping_Method();
		$cart_contents = $cart->get_cart();
		
		if ( empty( $cart_contents ) ) {
			return;
		}

		$total_weight = $shipping_method->calculate_tactical_weight_public( $cart_contents );

		if ( $total_weight <= 0 ) {
			return;
		}

		// Obtém o custo do frete
		$shipping_cost = $shipping_method->get_shipping_cost_public( $total_weight, $region );

		if ( $shipping_cost === false || $shipping_cost <= 0 ) {
			return;
		}

		// Adiciona o frete como taxa
		$cart->add_fee( __( 'Frete', 'gstore' ), $shipping_cost, false );
	}
	add_action( 'woocommerce_cart_calculate_fees', 'gstore_add_shipping_as_fee', 20 );
}

/**
 * Identifica a região de envio baseado no estado ou CEP.
 *
 * @param string $state Estado (UF) ou vazio.
 * @param string $postcode CEP ou vazio.
 * @return string Região: 'sul', 'resto_brasil', 'rio_janeiro'.
 */
function gstore_get_shipping_region( $state = '', $postcode = '' ) {
	// Limpa o estado
	$state = strtoupper( trim( $state ) );

	// Se tem estado, identifica diretamente
	if ( ! empty( $state ) ) {
		// Rio de Janeiro
		if ( $state === 'RJ' ) {
			return 'rio_janeiro';
		}

		// Região Sul
		if ( in_array( $state, array( 'RS', 'SC', 'PR' ), true ) ) {
			return 'sul';
		}

		// Demais estados
		return 'resto_brasil';
	}

	// Se não tem estado, tenta identificar pelo CEP
	if ( ! empty( $postcode ) ) {
		$postcode = preg_replace( '/[^0-9]/', '', $postcode );
		$first_digits = substr( $postcode, 0, 2 );

		// CEPs do Rio de Janeiro (20xxx-xxx a 23xxx-xxx)
		if ( $first_digits >= '20' && $first_digits <= '23' ) {
			return 'rio_janeiro';
		}

		// CEPs do Sul
		// RS: 90xxx-xxx a 96xxx-xxx
		// SC: 88xxx-xxx a 89xxx-xxx
		// PR: 80xxx-xxx a 83xxx-xxx
		if ( ( $first_digits >= '90' && $first_digits <= '96' ) ||
			 ( $first_digits >= '88' && $first_digits <= '89' ) ||
			 ( $first_digits >= '80' && $first_digits <= '83' ) ) {
			return 'sul';
		}
	}

	// Padrão: resto do Brasil
	return 'resto_brasil';
}

/**
 * Endpoint AJAX para buscar dados do Pix.
 */
function gstore_get_pix_data_ajax() {
	check_ajax_referer( 'gstore_pix_nonce', 'nonce' );

	$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => __( 'ID do pedido não informado.', 'gstore' ) ) );
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		wp_send_json_error( array( 'message' => __( 'Pedido não encontrado.', 'gstore' ) ) );
		return;
	}

	// Verifica se o pedido é do gateway Pix
	if ( $order->get_payment_method() !== 'blu_pix' ) {
		wp_send_json_error( array( 'message' => __( 'Este pedido não foi pago via Pix.', 'gstore' ) ) );
		return;
	}

	// Busca dados do Pix
	$transaction_token = $order->get_meta( Gstore_Blu_Pix_Gateway::META_TRANSACTION_TOKEN );
	$qr_code_base64    = $order->get_meta( Gstore_Blu_Pix_Gateway::META_QR_CODE_BASE64 );
	$emv               = $order->get_meta( Gstore_Blu_Pix_Gateway::META_EMV );
	$status            = $order->get_meta( Gstore_Blu_Pix_Gateway::META_STATUS );
	$expires_at        = $order->get_meta( Gstore_Blu_Pix_Gateway::META_EXPIRES_AT );

	// Se não tem QR Code ou EMV, tenta consultar na Blu
	if ( ( empty( $qr_code_base64 ) || empty( $emv ) ) && ! empty( $transaction_token ) ) {
		$gateway = gstore_blu_pix_get_gateway_instance();
		if ( $gateway ) {
			$response = $gateway->consult_pix( $transaction_token );
			if ( ! is_wp_error( $response ) ) {
				$qr_code_base64 = $response['qr_code_base64'] ?? $qr_code_base64;
				$emv            = $response['emv'] ?? $emv;
				$status         = $response['status'] ?? $status;
				$expires_at     = $response['expires_at'] ?? $expires_at;

				// Atualiza metadados
				$order->update_meta_data( Gstore_Blu_Pix_Gateway::META_QR_CODE_BASE64, $qr_code_base64 );
				$order->update_meta_data( Gstore_Blu_Pix_Gateway::META_EMV, $emv );
				$order->update_meta_data( Gstore_Blu_Pix_Gateway::META_STATUS, $status );
				$order->update_meta_data( Gstore_Blu_Pix_Gateway::META_EXPIRES_AT, $expires_at );
				$order->save();
			}
		}
	}

	wp_send_json_success(
		array(
			'transaction_token' => $transaction_token,
			'qr_code_base64'    => $qr_code_base64,
			'emv'               => $emv,
			'status'            => $status,
			'expires_at'        => $expires_at,
		)
	);
}
add_action( 'wp_ajax_gstore_get_pix_data', 'gstore_get_pix_data_ajax' );
add_action( 'wp_ajax_nopriv_gstore_get_pix_data', 'gstore_get_pix_data_ajax' );

/**
 * Obtém o estado (UF) a partir do CEP.
 *
 * @param string $postcode CEP (apenas números).
 * @return string Estado (UF) ou vazio se não identificado.
 */
function gstore_get_state_from_postcode( $postcode ) {
	$postcode = preg_replace( '/[^0-9]/', '', $postcode );
	if ( strlen( $postcode ) < 2 ) {
		return '';
	}

	$first_digit = substr( $postcode, 0, 1 );
	$first_two   = substr( $postcode, 0, 2 );

	// Mapeamento de CEP para Estado
	// Referência: https://www.correios.com.br/
	$cep_ranges = array(
		// São Paulo
		array( 'start' => '01', 'end' => '19', 'state' => 'SP' ),
		// Rio de Janeiro
		array( 'start' => '20', 'end' => '28', 'state' => 'RJ' ),
		// Espírito Santo
		array( 'start' => '29', 'end' => '29', 'state' => 'ES' ),
		// Minas Gerais
		array( 'start' => '30', 'end' => '39', 'state' => 'MG' ),
		// Bahia
		array( 'start' => '40', 'end' => '48', 'state' => 'BA' ),
		// Sergipe
		array( 'start' => '49', 'end' => '49', 'state' => 'SE' ),
		// Pernambuco
		array( 'start' => '50', 'end' => '56', 'state' => 'PE' ),
		// Alagoas
		array( 'start' => '57', 'end' => '57', 'state' => 'AL' ),
		// Paraíba
		array( 'start' => '58', 'end' => '58', 'state' => 'PB' ),
		// Rio Grande do Norte
		array( 'start' => '59', 'end' => '59', 'state' => 'RN' ),
		// Ceará
		array( 'start' => '60', 'end' => '63', 'state' => 'CE' ),
		// Piauí
		array( 'start' => '64', 'end' => '64', 'state' => 'PI' ),
		// Maranhão
		array( 'start' => '65', 'end' => '65', 'state' => 'MA' ),
		// Pará
		array( 'start' => '66', 'end' => '68', 'state' => 'PA' ),
		// Amapá (68900-68999)
		// Amazonas
		array( 'start' => '69', 'end' => '69', 'state' => 'AM' ),
		// Goiás e Tocantins
		array( 'start' => '70', 'end' => '72', 'state' => 'DF' ), // Distrito Federal (70000-72799)
		array( 'start' => '73', 'end' => '76', 'state' => 'GO' ), // Goiás
		array( 'start' => '77', 'end' => '77', 'state' => 'TO' ), // Tocantins
		// Mato Grosso
		array( 'start' => '78', 'end' => '78', 'state' => 'MT' ),
		// Mato Grosso do Sul
		array( 'start' => '79', 'end' => '79', 'state' => 'MS' ),
		// Paraná
		array( 'start' => '80', 'end' => '87', 'state' => 'PR' ),
		// Santa Catarina
		array( 'start' => '88', 'end' => '89', 'state' => 'SC' ),
		// Rio Grande do Sul
		array( 'start' => '90', 'end' => '99', 'state' => 'RS' ),
	);

	foreach ( $cep_ranges as $range ) {
		if ( $first_two >= $range['start'] && $first_two <= $range['end'] ) {
			// Tratamento especial para Amapá (68900-68999)
			if ( $first_two === '68' && substr( $postcode, 0, 3 ) >= '689' ) {
				return 'AP';
			}
			// Tratamento especial para Acre (69900-69999)
			if ( $first_two === '69' && substr( $postcode, 0, 3 ) >= '699' ) {
				return 'AC';
			}
			// Tratamento especial para Roraima (69300-69389)
			if ( $first_two === '69' && substr( $postcode, 0, 3 ) >= '693' && substr( $postcode, 0, 3 ) <= '693' ) {
				return 'RR';
			}
			// Tratamento especial para Rondônia (76800-76999)
			if ( $first_two === '76' && substr( $postcode, 0, 3 ) >= '768' ) {
				return 'RO';
			}
			return $range['state'];
		}
	}

	return '';
}

/**
 * Endpoint AJAX para calcular frete.
 */
function gstore_calculate_shipping_ajax() {
	check_ajax_referer( 'gstore_shipping_calculator', 'nonce' );

	$postcode = isset( $_POST['postcode'] ) ? sanitize_text_field( $_POST['postcode'] ) : '';
	$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
	$quantity = isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 1;

	// Limpa o CEP
	$postcode = preg_replace( '/[^0-9]/', '', $postcode );

	if ( empty( $postcode ) || strlen( $postcode ) !== 8 ) {
		wp_send_json_error( array( 'message' => __( 'CEP inválido. Por favor, informe um CEP válido com 8 dígitos.', 'gstore' ) ) );
		return;
	}

	// Identifica a região e o estado
	$region = gstore_get_shipping_region( '', $postcode );
	$state  = gstore_get_state_from_postcode( $postcode );

	// Labels das regiões
	$region_labels = array(
		'sul'          => __( 'Região Sul', 'gstore' ),
		'resto_brasil' => __( 'Resto do Brasil', 'gstore' ),
		'rio_janeiro'  => __( 'Rio de Janeiro', 'gstore' ),
	);

	$region_label = isset( $region_labels[ $region ] ) ? $region_labels[ $region ] : $region;

	// Calcula o peso
	$total_weight = 0;

	$shipping_method = new Gstore_Shipping_Method();

	if ( $product_id > 0 ) {
		// Cálculo para produto único
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Produto não encontrado.', 'gstore' ) ) );
			return;
		}

		// Verifica se é arma
		$is_weapon = $shipping_method->is_weapon_product( $product );

		if ( $is_weapon ) {
			$total_weight = 100 * $quantity;
		} else {
			$product_weight = $product->get_weight();
			if ( $product_weight ) {
				$total_weight = floatval( $product_weight ) * $quantity;
			}
		}
	} else {
		// Cálculo para carrinho (checkout)
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Carrinho não disponível.', 'gstore' ) ) );
			return;
		}

		$cart_contents = WC()->cart->get_cart();
		if ( empty( $cart_contents ) ) {
			wp_send_json_error( array( 'message' => __( 'Carrinho vazio.', 'gstore' ) ) );
			return;
		}

		$total_weight = $shipping_method->calculate_tactical_weight_public( $cart_contents );
	}

	if ( $total_weight <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Não foi possível calcular o peso dos produtos.', 'gstore' ) ) );
		return;
	}

	// Obtém o custo do frete
	$shipping_cost = $shipping_method->get_shipping_cost_public( $total_weight, $region );

	if ( $shipping_cost === false ) {
		wp_send_json_error( array( 'message' => __( 'Não foi possível calcular o frete para esta região. Entre em contato conosco.', 'gstore' ) ) );
		return;
	}

	// ===== SINCRONIZA CEP COM SESSÃO DO WOOCOMMERCE =====
	// Isso permite que o WooCommerce calcule o frete oficialmente
	if ( function_exists( 'WC' ) && WC()->customer ) {
		// Salva o CEP na sessão do cliente
		WC()->customer->set_billing_postcode( $postcode );
		WC()->customer->set_shipping_postcode( $postcode );
		
		// Salva o estado identificado
		if ( ! empty( $state ) ) {
			WC()->customer->set_billing_state( $state );
			WC()->customer->set_shipping_state( $state );
		}
		
		// Define o país como Brasil
		WC()->customer->set_billing_country( 'BR' );
		WC()->customer->set_shipping_country( 'BR' );
		
		// Marca que o frete foi calculado
		WC()->customer->set_calculated_shipping( true );
		
		// Salva as alterações na sessão
		WC()->customer->save();
	}
	
	// ===== DEFINE O MÉTODO DE ENVIO COMO SELECIONADO =====
	// Isso é ESSENCIAL para que o frete seja incluído no total
	if ( function_exists( 'WC' ) && WC()->session ) {
		// Define o método de envio Gstore como o escolhido
		WC()->session->set( 'chosen_shipping_methods', array( 'gstore_custom_shipping' ) );
	}
	
	// Força o recálculo do frete e totais do carrinho
	if ( function_exists( 'WC' ) && WC()->cart ) {
		// Limpa cache de shipping rates
		WC()->session->set( 'shipping_for_package_0', false );
		
		// Recalcula shipping e totais
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
	}

	// Estima prazo de entrega (dias úteis)
	$estimated_days = 7; // Padrão
	if ( $region === 'rio_janeiro' ) {
		$estimated_days = 5;
	} elseif ( $region === 'sul' ) {
		$estimated_days = 6;
	}

	wp_send_json_success(
		array(
			'cost'           => floatval( $shipping_cost ),
			'cost_formatted' => wc_price( $shipping_cost ),
			'region'          => $region,
			'region_label'    => $region_label,
			'estimated_days'  => $estimated_days,
			'weight'          => $total_weight,
			'state'           => $state,
		)
	);
}
add_action( 'wp_ajax_gstore_calculate_shipping', 'gstore_calculate_shipping_ajax' );
add_action( 'wp_ajax_nopriv_gstore_calculate_shipping', 'gstore_calculate_shipping_ajax' );

/**
 * Registra o suporte a Blocos para o Gateway Blu.
 */
add_action( 'woocommerce_blocks_payment_method_type_registration', 'gstore_blu_register_payment_method_type' );
function gstore_blu_register_payment_method_type( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {

	// Verifica se a classe AbstractPaymentMethodType está disponível
	if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		// Loga o erro para debug apenas se WP_DEBUG estiver ativo
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'GSTORE BLU: AbstractPaymentMethodType class not available. WooCommerce Blocks may not be installed or version incompatible.',
					array( 'source' => 'gstore-blu-blocks' )
				);
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log( 'GSTORE BLU: AbstractPaymentMethodType class not available. WooCommerce Blocks may not be installed or version incompatible.' );
			}
		}
		return;
	}

	try {
		// Inclui o arquivo da classe de integração com blocos
		$blocks_file = get_theme_file_path( 'inc/class-gstore-blu-payment-gateway-blocks.php' );
		
		if ( ! file_exists( $blocks_file ) ) {
			throw new Exception( 'Blocks integration file not found: ' . $blocks_file );
		}

		require_once $blocks_file;

		// Verifica se a classe foi carregada corretamente
		if ( ! class_exists( 'Gstore_Blu_Payment_Gateway_Blocks' ) ) {
			throw new Exception( 'Gstore_Blu_Payment_Gateway_Blocks class not loaded after including file' );
		}

		// Registra a integração
		$payment_method_registry->register( new Gstore_Blu_Payment_Gateway_Blocks() );

		// Loga sucesso apenas se debug estiver ativo
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				'GSTORE BLU: Blocks integration registered successfully',
				array( 'source' => 'gstore-blu-blocks' )
			);
		}

	} catch ( Exception $e ) {
		// Loga o erro apenas se WP_DEBUG estiver ativo
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'GSTORE BLU: Error registering blocks integration: ' . $e->getMessage(),
					array(
						'source' => 'gstore-blu-blocks',
						'trace'  => $e->getTraceAsString(),
					)
				);
			} elseif ( function_exists( 'error_log' ) ) {
				error_log( 'GSTORE BLU: Error registering blocks integration: ' . $e->getMessage() );
			}
		}
	}
}



/**
 * Adiciona campo CPF no checkout e salva no pedido.
 */
function gstore_add_cpf_field( $fields ) {
    // Se for o filtro woocommerce_billing_fields, o array é direto
    // Se for woocommerce_checkout_fields, tem 'billing'
    
    if ( isset( $fields['billing'] ) ) {
        $fields['billing']['billing_cpf'] = array(
            'label'       => __('CPF', 'gstore'),
            'placeholder' => _x('000.000.000-00', 'placeholder', 'gstore'),
            'required'    => true,
            'class'       => array('form-row-wide', 'address-field'),
            'clear'       => true,
            'priority'    => 35,
        );
    } else {
        $fields['billing_cpf'] = array(
            'label'       => __('CPF', 'gstore'),
            'placeholder' => _x('000.000.000-00', 'placeholder', 'gstore'),
            'required'    => true,
            'class'       => array('form-row-wide', 'address-field'),
            'clear'       => true,
            'priority'    => 35,
        );
    }
    
    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'gstore_add_cpf_field', 20 );
add_filter( 'woocommerce_billing_fields', 'gstore_add_cpf_field', 20 );

function gstore_save_cpf_field( $order_id ) {
    // Verifica nonce do checkout (segurança)
    if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) || 
         ! wp_verify_nonce( $_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout' ) ) {
        return;
    }

    if ( ! empty( $_POST['billing_cpf'] ) ) {
        // Sanitiza o campo antes de processar
        $cpf = sanitize_text_field( $_POST['billing_cpf'] );
        // Remove tudo que não é número
        $cpf = preg_replace( '/[^0-9]/', '', $cpf );
        // Salva apenas se tiver conteúdo válido
        if ( ! empty( $cpf ) ) {
            update_post_meta( $order_id, 'billing_cpf', $cpf );
            update_post_meta( $order_id, '_billing_cpf', $cpf );
        }
    }
}
add_action( 'woocommerce_checkout_update_order_meta', 'gstore_save_cpf_field' );

/**
 * Exibe o CPF no painel de administração do pedido.
 */
function gstore_display_cpf_admin_order_data( $order ) {
    $cpf = $order->get_meta( 'billing_cpf' );
    if ( $cpf ) {
        echo '<p><strong>' . __('CPF', 'gstore') . ':</strong> ' . esc_html( $cpf ) . '</p>';
    }
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'gstore_display_cpf_admin_order_data', 10, 1 );

/**
 * Retorna o título amigável do método de pagamento a partir do ID do gateway.
 *
 * @param string $payment_method ID do gateway (ex: blu_pix, blu_checkout, cod).
 * @return string
 */
function gstore_get_payment_method_title_by_id( $payment_method ) {
	$payment_method = (string) $payment_method;
	if ( '' === $payment_method ) {
		return '';
	}

	// Overrides do tema (para manter o texto consistente no resumo).
	$overrides = array(
		'blu_pix'      => __( 'Pix', 'gstore' ),
		'blu_checkout' => __( 'Cartão', 'gstore' ),
	);
	if ( isset( $overrides[ $payment_method ] ) ) {
		return (string) $overrides[ $payment_method ];
	}

	if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
		return '';
	}

	$payment_method_title = '';

	// Preferir gateways disponíveis no checkout (já respeitam país/moeda/regras).
	$available = WC()->payment_gateways()->get_available_payment_gateways();
	if ( isset( $available[ $payment_method ] ) && is_object( $available[ $payment_method ] ) ) {
		$title = '';
		if ( method_exists( $available[ $payment_method ], 'get_title' ) ) {
			$title = (string) $available[ $payment_method ]->get_title();
		} elseif ( isset( $available[ $payment_method ]->title ) ) {
			$title = (string) $available[ $payment_method ]->title;
		}
		$payment_method_title = wp_strip_all_tags( $title );
	} else {
		// Fallback: gateway registrado (pode não estar "available" ainda em alguns estados).
		$all_gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $all_gateways[ $payment_method ] ) && is_object( $all_gateways[ $payment_method ] ) ) {
			$title = '';
			if ( method_exists( $all_gateways[ $payment_method ], 'get_title' ) ) {
				$title = (string) $all_gateways[ $payment_method ]->get_title();
			} elseif ( isset( $all_gateways[ $payment_method ]->title ) ) {
				$title = (string) $all_gateways[ $payment_method ]->title;
			}
			$payment_method_title = wp_strip_all_tags( $title );
		}
	}

	return (string) $payment_method_title;
}

/**
 * Exibe o método de pagamento selecionado no resumo (tabela) do checkout clássico.
 */
function gstore_checkout_review_order_payment_method_row() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$method = (string) WC()->session->get( 'chosen_payment_method', '' );
	$title  = gstore_get_payment_method_title_by_id( $method );

	if ( '' === $title ) {
		return;
	}

	?>
	<tr class="gstore-review-order-payment-method">
		<th><?php esc_html_e( 'Pagamento', 'gstore' ); ?></th>
		<td data-title="<?php esc_attr_e( 'Pagamento', 'gstore' ); ?>"><?php echo esc_html( $title ); ?></td>
	</tr>
	<?php
}
add_action( 'woocommerce_review_order_before_order_total', 'gstore_checkout_review_order_payment_method_row', 9 );

/**
 * Endpoint AJAX para obter o resumo do carrinho no checkout de 3 etapas.
 */
function gstore_get_cart_summary_ajax() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( array( 'message' => 'WooCommerce não está ativo.' ) );
	}

	$cart = WC()->cart;

	if ( ! $cart ) {
		wp_send_json_error( array( 'message' => 'Carrinho não encontrado.' ) );
	}

	// Força recálculo para garantir valores atualizados (frete/fees/taxa de parcelas)
	if ( WC()->customer && WC()->customer->get_shipping_postcode() ) {
		// Garante que o método de envio está selecionado
		if ( WC()->session ) {
			$chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );
			if ( empty( $chosen_methods ) || empty( $chosen_methods[0] ) ) {
				WC()->session->set( 'chosen_shipping_methods', array( 'gstore_custom_shipping' ) );
			}
		}
		$cart->calculate_shipping();
	}
	$cart->calculate_totals();

	$items = array();
	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		$product = $cart_item['data'];
		$items[] = array(
			'key'      => $cart_item_key,
			'name'     => $product->get_name(),
			'quantity' => $cart_item['quantity'],
			'subtotal' => wc_price( $cart_item['line_subtotal'] ),
			'image'    => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
		);
	}

	$totals = $cart->get_totals();

	// Verifica se há frete nas fees (taxa de frete Gstore)
	$shipping_fee = null;
	$fees = $cart->get_fees();
	$other_fees = array();
	foreach ( $fees as $fee ) {
		if ( stripos( $fee->name, 'frete' ) !== false || $fee->id === 'gstore-shipping-fee' ) {
			$shipping_fee = $fee->total;
			break;
		}
	}
	foreach ( $fees as $fee ) {
		// Exclui fee de frete do array de taxas extras
		if ( stripos( $fee->name, 'frete' ) !== false || $fee->id === 'gstore-shipping-fee' ) {
			continue;
		}
		$other_fees[] = array(
			'label' => (string) $fee->name,
			'total' => wc_price( (float) $fee->total ),
		);
	}

	// Usa shipping_total se disponível, senão usa a fee de frete
	$shipping_value = $totals['shipping_total'] > 0 ? $totals['shipping_total'] : $shipping_fee;

	$payment_method       = ( WC()->session ? (string) WC()->session->get( 'chosen_payment_method', '' ) : '' );
	$payment_method_title = gstore_get_payment_method_title_by_id( $payment_method );

	$response = array(
		'items_count' => $cart->get_cart_contents_count(),
		'items'       => $items,
		'payment_method'       => $payment_method,
		'payment_method_title' => $payment_method_title,
		'total'       => wc_price( $totals['total'] ),
		'totals'      => array(
			'subtotal' => wc_price( $totals['subtotal'] ),
			'shipping' => $shipping_value > 0 ? wc_price( $shipping_value ) : null,
			'discount' => $totals['discount_total'] > 0 ? wc_price( $totals['discount_total'] ) : null,
			'fees'     => ! empty( $other_fees ) ? $other_fees : null,
		),
		'installments' => array(
			'selected' => gstore_blu_get_selected_installments(),
			'per_installment' => wc_price(
				max( 0, (float) $totals['total'] ) / max( 1, gstore_blu_get_selected_installments() )
			),
		),
	);

	wp_send_json_success( $response );
}
add_action( 'wp_ajax_gstore_get_cart_summary', 'gstore_get_cart_summary_ajax' );
add_action( 'wp_ajax_nopriv_gstore_get_cart_summary', 'gstore_get_cart_summary_ajax' );

/**
 * AJAX: retorna simulação de valores por parcela (considerando taxa progressiva/tabela).
 */
function gstore_parse_money_to_float( $value ) {
	if ( is_int( $value ) || is_float( $value ) ) {
		return (float) $value;
	}

	if ( is_bool( $value ) || null === $value ) {
		return 0.0;
	}

	if ( is_string( $value ) ) {
		$str = html_entity_decode( wp_strip_all_tags( $value ) );
		$str = preg_replace( '/[^0-9,\.\-]/', '', $str );
		$str = trim( (string) $str );

		if ( '' === $str || '-' === $str ) {
			return 0.0;
		}

		// pt-BR: normalmente "1.234,56"
		if ( false !== strpos( $str, ',' ) && false !== strpos( $str, '.' ) ) {
			$str = str_replace( '.', '', $str );
			$str = str_replace( ',', '.', $str );
		} elseif ( false !== strpos( $str, ',' ) ) {
			$str = str_replace( ',', '.', $str );
		}

		return (float) $str;
	}

	return 0.0;
}

function gstore_blu_get_installment_quotes_ajax() {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
		wp_send_json_error( array( 'message' => 'WooCommerce não está ativo.' ) );
	}

	$cart = WC()->cart;
	if ( ! $cart || $cart->is_empty() ) {
		wp_send_json_error( array( 'message' => 'Carrinho vazio.' ) );
	}

	if ( ! WC()->session ) {
		wp_send_json_error( array( 'message' => 'Sessão não disponível.' ) );
	}

	$gateway = function_exists( 'gstore_blu_get_gateway_instance' ) ? gstore_blu_get_gateway_instance() : null;
	$max     = $gateway && method_exists( $gateway, 'get_max_installments' ) ? (int) $gateway->get_max_installments() : 21;
	$max     = max( 1, min( 21, $max ) );

	$requested_max = isset( $_POST['max'] ) ? absint( $_POST['max'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $requested_max > 0 ) {
		$max = max( 1, min( 21, $requested_max, $max ) );
	}

	$orig_installments = absint( WC()->session->get( 'gstore_blu_installments', 1 ) );
	$orig_method       = (string) WC()->session->get( 'chosen_payment_method', '' );

	// Força método cartão Blu durante simulação para aplicar fee
	WC()->session->set( 'chosen_payment_method', 'blu_checkout' );

	$quotes = array();
	for ( $i = 1; $i <= $max; $i++ ) {
		WC()->session->set( 'gstore_blu_installments', $i );
		$cart->calculate_totals();
		$totals = $cart->get_totals();

		// Total "raw" mais confiável (pode vir como string/HTML dependendo de hooks/plugins)
		$total = 0.0;
		if ( isset( $totals['total'] ) ) {
			$total = gstore_parse_money_to_float( $totals['total'] );
		}
		if ( $total <= 0 && isset( $cart->total ) ) {
			$total = gstore_parse_money_to_float( $cart->total );
		}
		if ( $total <= 0 && method_exists( $cart, 'get_total' ) ) {
			// Alguns ambientes retornam string formatada mesmo com context 'edit'
			$total = gstore_parse_money_to_float( $cart->get_total( 'edit' ) );
			if ( $total <= 0 ) {
				$total = gstore_parse_money_to_float( $cart->get_total() );
			}
		}

		// Calcula o valor da parcela COM juros compostos (exceto para 1x que não tem juros)
		if ( $i === 1 ) {
			$per_installment = max( 0, $total );
			$total_with_interest = $total;
		} else {
			// Usa função de cálculo com juros compostos (fórmula Price/PGTO)
			$per_installment = gstore_calculate_installment_with_interest( $total, $i );
			$total_with_interest = $per_installment * $i;
		}

		$total_html = wc_price( $total_with_interest );
		$per_html   = wc_price( $per_installment );
		$total_text = html_entity_decode( wp_strip_all_tags( $total_html ) );
		$per_text   = html_entity_decode( wp_strip_all_tags( $per_html ) );

		$quotes[ (string) $i ] = array(
			'installments'         => $i,
			'total'                => $total_html,
			'per_installment'      => $per_html,
			'total_text'           => $total_text,
			'per_installment_text' => $per_text,
			'total_raw'            => $total_with_interest,
			'per_installment_raw'  => $per_installment,
			'original_total'       => $total, // Valor base sem juros para referência
		);
	}

	// Restaura sessão
	WC()->session->set( 'gstore_blu_installments', $orig_installments >= 1 ? $orig_installments : 1 );
	WC()->session->set( 'chosen_payment_method', $orig_method );
	$cart->calculate_totals();

	wp_send_json_success(
		array(
			'max'     => $max,
			'quotes'  => $quotes,
			'current' => gstore_blu_get_selected_installments(),
		)
	);
}
add_action( 'wp_ajax_gstore_blu_get_installment_quotes', 'gstore_blu_get_installment_quotes_ajax' );
add_action( 'wp_ajax_nopriv_gstore_blu_get_installment_quotes', 'gstore_blu_get_installment_quotes_ajax' );

/**
 * ============================================
 * PÁGINA MINHA CONTA - CUSTOMIZAÇÕES
 * ============================================
 */

/**
 * Sobrescreve o template de navegação do WooCommerce.
 */
function gstore_custom_account_navigation() {
	wc_get_template( 'myaccount/navigation.php' );
}
remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation' );
add_action( 'woocommerce_account_navigation', 'gstore_custom_account_navigation' );

/**
 * Renomeia os itens do menu da conta.
 *
 * @param array $items Itens do menu.
 * @return array
 */
function gstore_rename_account_menu_items( $items ) {
	$items['dashboard']       = __( 'Painel', 'gstore' );
	$items['orders']          = __( 'Pedidos', 'gstore' );
	$items['downloads']       = __( 'Downloads', 'gstore' );
	$items['edit-address']    = __( 'Endereços', 'gstore' );
	$items['edit-account']    = __( 'Meus Dados', 'gstore' );
	$items['customer-logout'] = __( 'Sair', 'gstore' );
	
	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'gstore_rename_account_menu_items' );

/**
 * Adiciona classe body customizada para a página minha conta.
 *
 * @param array $classes Classes do body.
 * @return array
 */
function gstore_myaccount_body_class( $classes ) {
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$classes[] = 'gstore-myaccount-page';
	}
	return $classes;
}
add_filter( 'body_class', 'gstore_myaccount_body_class' );

/**
 * Força o navegador e eventuais caches de hospedagem a não armazenarem
 * a página "Minha Conta". Isso evita que o formulário de cadastro use
 * nonces expirados (comuns em hosts que ativam cache para visitantes).
 */
function gstore_disable_account_page_cache() {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( function_exists( 'wc_nocache_headers' ) ) {
		wc_nocache_headers();
	} else {
		nocache_headers();
	}
}
add_action( 'template_redirect', 'gstore_disable_account_page_cache', 0 );

/**
 * Redireciona a página "Loja" para "Catálogo".
 * 
 * Redireciona qualquer acesso à página /loja para /catalogo,
 * incluindo a página de arquivo do WooCommerce.
 */
function gstore_redirect_loja_to_catalogo() {
	// Verifica se é a página "loja" pelo slug
	if ( is_page( 'loja' ) ) {
		$catalogo_url = home_url( '/catalogo' );
		wp_safe_redirect( $catalogo_url, 301 );
		exit;
	}
	
	// Verifica se é a página de shop do WooCommerce configurada como "loja"
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$shop_page_id = wc_get_page_id( 'shop' );
		if ( $shop_page_id ) {
			$shop_page = get_post( $shop_page_id );
			if ( $shop_page && 'loja' === $shop_page->post_name ) {
				$catalogo_url = home_url( '/catalogo' );
				wp_safe_redirect( $catalogo_url, 301 );
				exit;
			}
		}
	}
}
add_action( 'template_redirect', 'gstore_redirect_loja_to_catalogo', 1 );

/**
 * Altera a URL do botão "Return to shop" para apontar para o catálogo.
 * 
 * @param string $url URL original do botão.
 * @return string URL do catálogo.
 */
function gstore_return_to_shop_url( $url ) {
	$catalogo_page = get_page_by_path( 'catalogo' );
	if ( $catalogo_page ) {
		return get_permalink( $catalogo_page->ID );
	}
	return home_url( '/catalogo' );
}
add_filter( 'woocommerce_return_to_shop_redirect', 'gstore_return_to_shop_url' );

/**
 * Altera o texto do botão "Return to shop" para "Retornar para o catálogo".
 * 
 * @param string $text Texto original do botão.
 * @return string Novo texto.
 */
function gstore_return_to_shop_text( $text ) {
	return __( 'Retornar para o catálogo', 'gstore' );
}
add_filter( 'woocommerce_return_to_shop_text', 'gstore_return_to_shop_text' );

/**
 * Exibe uma mensagem amigável quando o nonce do formulário expira.
 * Sem isso o WooCommerce simplesmente ignora o POST e nada acontece.
 */
function gstore_handle_expired_register_nonce() {
	if ( empty( $_POST['register'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	if ( ! function_exists( 'wc_add_notice' ) ) {
		return;
	}

	$nonce_value = '';

	if ( isset( $_POST['woocommerce-register-nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce_value = sanitize_text_field( wp_unslash( $_POST['woocommerce-register-nonce'] ) );
	} elseif ( isset( $_POST['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce_value = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
	}

	if ( $nonce_value && wp_verify_nonce( $nonce_value, 'woocommerce-register' ) ) {
		return;
	}

	wc_add_notice(
		__( 'Não conseguimos validar sua sessão de cadastro. Atualize a página e tente novamente.', 'gstore' ),
		'error'
	);
}
add_action( 'wp_loaded', 'gstore_handle_expired_register_nonce', 5 );

/**
 * Remove o wrapper padrão do WooCommerce na página minha conta
 * para usarmos nosso próprio layout.
 */
function gstore_remove_myaccount_wrapper() {
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	}
}
add_action( 'wp', 'gstore_remove_myaccount_wrapper' );

/**
 * Retorna o ícone SVG para cada endpoint do menu da conta.
 *
 * @param string $endpoint Endpoint do menu.
 * @return string SVG do ícone.
 */
function gstore_get_myaccount_icon( $endpoint ) {
	$icons = array(
		'dashboard'       => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
		'orders'          => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>',
		'downloads'       => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>',
		'edit-address'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
		'edit-account'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
		'customer-logout' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
		'payment-methods' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>',
	);

	$default_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>';

	return isset( $icons[ $endpoint ] ) ? $icons[ $endpoint ] : $default_icon;
}

/**
 * ============================================
 * FUNÇÕES HELPER PARA IMAGENS DA BIBLIOTECA
 * ============================================
 */

/**
 * Aumenta a qualidade das imagens JPEG para melhor qualidade em produção.
 * 
 * O WordPress usa qualidade 82 por padrão. Aumentamos para 92 para banners.
 * Isso garante que imagens novas e redimensionadas tenham qualidade máxima.
 * 
 * NOTA: Imagens já carregadas precisarão ser regeneradas para aplicar a nova qualidade.
 * Use um plugin como "Regenerate Thumbnails" ou faça upload novamente das imagens.
 * 
 * @param int    $quality Qualidade atual (82 padrão).
 * @param string $mime_type Tipo MIME da imagem.
 * @return int Nova qualidade.
 */
function gstore_increase_jpeg_quality( $quality, $mime_type ) {
	if ( 'image/jpeg' === $mime_type ) {
		// Aumenta a qualidade para 92 (qualidade alta, ainda com compressão)
		// 92 é um bom equilíbrio entre qualidade e tamanho de arquivo
		return 92;
	}
	
	// Para WebP e PNG, mantém a qualidade padrão
	return $quality;
}
add_filter( 'jpeg_quality', 'gstore_increase_jpeg_quality', 10, 2 );
add_filter( 'wp_editor_set_quality', 'gstore_increase_jpeg_quality', 10, 2 );

/**
 * Retorna a URL de uma imagem da biblioteca de mídia pelo ID.
 *
 * @param int    $attachment_id ID da imagem na biblioteca.
 * @param string $size          Tamanho da imagem (thumbnail, medium, large, full, etc.).
 * @return string URL da imagem ou string vazia se não encontrada.
 */
function gstore_get_image_url( $attachment_id, $size = 'full' ) {
	if ( ! $attachment_id ) {
		return '';
	}

	// Valida se o attachment existe
	$attachment = get_post( $attachment_id );
	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return '';
	}

	// Valida se é uma imagem
	$mime_type = get_post_mime_type( $attachment_id );
	if ( ! $mime_type || strpos( $mime_type, 'image/' ) !== 0 ) {
		return '';
	}

	// Para banners (tamanho 'full'), garante que sempre use a imagem original
	if ( 'full' === $size ) {
		$image_url = wp_get_attachment_image_url( $attachment_id, 'full' );
		// Se não encontrar, tenta pegar a URL do arquivo original diretamente
		if ( ! $image_url ) {
			$image_url = wp_get_attachment_url( $attachment_id );
		}
	} else {
		$image_url = wp_get_attachment_image_url( $attachment_id, $size );
	}

	return $image_url ? $image_url : '';
}

/**
 * Retorna a tag <img> completa de uma imagem da biblioteca.
 *
 * @param int    $attachment_id ID da imagem na biblioteca.
 * @param string $size          Tamanho da imagem.
 * @param string $alt           Texto alternativo (opcional, usa o alt da mídia se não fornecido).
 * @param array  $attr          Atributos adicionais para a tag img.
 * @param bool   $use_srcset    Se true, gera srcset e sizes para imagens responsivas (padrão: true).
 * @return string Tag <img> completa ou string vazia.
 */
function gstore_get_image_tag( $attachment_id, $size = 'full', $alt = '', $attr = array(), $use_srcset = true ) {
	if ( ! $attachment_id ) {
		return '';
	}

	// Se alt não foi fornecido, tenta pegar da mídia
	if ( empty( $alt ) ) {
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	}

	// URL principal
	$src_url = gstore_get_image_url( $attachment_id, $size );
	if ( empty( $src_url ) ) {
		return '';
	}

	$default_attr = array(
		'src'      => $src_url,
		'alt'      => $alt ? $alt : '',
		'loading'  => 'lazy',
		'decoding' => 'async',
	);

	// Gera srcset e sizes se solicitado e se não for 'full' (full usa imagem original)
	if ( $use_srcset && 'full' !== $size ) {
		$image_meta = wp_get_attachment_metadata( $attachment_id );
		if ( $image_meta && isset( $image_meta['sizes'] ) ) {
			// Tamanhos disponíveis para srcset
			$srcset_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large' );
			$srcset_array = array();

			// Adiciona o tamanho solicitado primeiro
			$current_size_url = gstore_get_image_url( $attachment_id, $size );
			if ( $current_size_url ) {
				$current_size_meta = wp_get_attachment_image_src( $attachment_id, $size );
				if ( $current_size_meta ) {
					$srcset_array[] = esc_url( $current_size_url ) . ' ' . $current_size_meta[1] . 'w';
				}
			}

			// Adiciona outros tamanhos disponíveis
			foreach ( $srcset_sizes as $srcset_size ) {
				if ( $srcset_size === $size ) {
					continue; // Já adicionado
				}

				if ( isset( $image_meta['sizes'][ $srcset_size ] ) ) {
					$srcset_url = gstore_get_image_url( $attachment_id, $srcset_size );
					$srcset_src = wp_get_attachment_image_src( $attachment_id, $srcset_size );
					if ( $srcset_url && $srcset_src ) {
						$srcset_array[] = esc_url( $srcset_url ) . ' ' . $srcset_src[1] . 'w';
					}
				}
			}

			// Adiciona o tamanho completo (original) se disponível e não for muito grande
			if ( isset( $image_meta['width'] ) && $image_meta['width'] <= 2048 ) {
				$full_url = gstore_get_image_url( $attachment_id, 'full' );
				if ( $full_url ) {
					$srcset_array[] = esc_url( $full_url ) . ' ' . $image_meta['width'] . 'w';
				}
			}

			if ( ! empty( $srcset_array ) ) {
				$default_attr['srcset'] = implode( ', ', $srcset_array );
				
				// Gera sizes apropriado baseado no tamanho solicitado
				if ( ! isset( $attr['sizes'] ) ) {
					$default_attr['sizes'] = '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw';
				}
			}
		}
	}

	// Width e height para evitar CLS (se disponível)
	if ( ! isset( $attr['width'] ) || ! isset( $attr['height'] ) ) {
		$image_src = wp_get_attachment_image_src( $attachment_id, $size );
		if ( $image_src && isset( $image_src[1] ) && isset( $image_src[2] ) ) {
			if ( ! isset( $attr['width'] ) ) {
				$default_attr['width'] = $image_src[1];
			}
			if ( ! isset( $attr['height'] ) ) {
				$default_attr['height'] = $image_src[2];
			}
		}
	}

	$attr = wp_parse_args( $attr, $default_attr );

	$img_tag = '<img';
	foreach ( $attr as $key => $value ) {
		if ( 'srcset' === $key && is_array( $value ) ) {
			// srcset já foi convertido para string acima
			continue;
		}
		if ( ! empty( $value ) || 'alt' === $key ) {
			$img_tag .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}
	$img_tag .= ' />';

	return $img_tag;
}

/**
 * Adiciona dimensões explícitas (width/height) a todas as imagens renderizadas pelo WordPress.
 * 
 * Isso inclui imagens de blocos Gutenberg, the_content, etc.
 * Reduz CLS (Cumulative Layout Shift) melhorando a experiência do usuário.
 * 
 * @param array $attr Atributos da imagem
 * @param WP_Post $attachment Objeto do attachment
 * @param string|array $size Tamanho da imagem
 * @return array Atributos modificados
 */
function gstore_add_image_dimensions( $attr, $attachment, $size ) {
	// Se já tem width e height, não precisa adicionar
	if ( isset( $attr['width'] ) && isset( $attr['height'] ) ) {
		return $attr;
	}

	// Obtém informações da imagem
	$image_src = wp_get_attachment_image_src( $attachment->ID, $size );
	if ( ! $image_src ) {
		return $attr;
	}

	// Adiciona width se não existir
	if ( ! isset( $attr['width'] ) && isset( $image_src[1] ) ) {
		$attr['width'] = $image_src[1];
	}

	// Adiciona height se não existir
	if ( ! isset( $attr['height'] ) && isset( $image_src[2] ) ) {
		$attr['height'] = $image_src[2];
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'gstore_add_image_dimensions', 10, 3 );

/**
 * Adiciona dimensões a imagens no conteúdo (the_content).
 * 
 * Processa imagens que podem não ter sido renderizadas via wp_get_attachment_image.
 * 
 * @param string $content Conteúdo do post
 * @return string Conteúdo modificado
 */
function gstore_add_dimensions_to_content_images( $content ) {
	// Não processa em admin
	if ( is_admin() ) {
		return $content;
	}

	// Procura por tags img sem width ou height
	$content = preg_replace_callback(
		'/<img([^>]*?)(?:\s+(?:width|height)\s*=\s*["\'][^"\']*["\'])?([^>]*?)>/i',
		function( $matches ) {
			$img_tag = $matches[0];
			$attributes = $matches[1] . $matches[2];

			// Se já tem width e height, retorna como está
			if ( preg_match( '/\s+(?:width|height)\s*=\s*["\'][^"\']*["\']/i', $img_tag ) ) {
				return $img_tag;
			}

			// Tenta extrair src para obter attachment ID
			if ( preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', $img_tag, $src_matches ) ) {
				$image_url = $src_matches[1];
				
				// Tenta obter attachment ID da URL
				$attachment_id = attachment_url_to_postid( $image_url );
				if ( $attachment_id ) {
					$image_src = wp_get_attachment_image_src( $attachment_id, 'full' );
					if ( $image_src && isset( $image_src[1] ) && isset( $image_src[2] ) ) {
						// Adiciona width e height
						$img_tag = str_replace( '<img', '<img width="' . esc_attr( $image_src[1] ) . '" height="' . esc_attr( $image_src[2] ) . '"', $img_tag );
					}
				}
			}

			return $img_tag;
		},
		$content
	);

	return $content;
}
add_filter( 'the_content', 'gstore_add_dimensions_to_content_images', 20 );

/**
 * Shortcode para retornar URL de imagem da biblioteca.
 * 
 * Uso: [gstore_image_url id="123" size="full"]
 * 
 * @param array $atts Atributos do shortcode.
 * @return string URL da imagem.
 */
function gstore_image_url_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'   => 0,
			'size' => 'full',
		),
		$atts,
		'gstore_image_url'
	);

	$attachment_id = absint( $atts['id'] );
	if ( ! $attachment_id ) {
		return '';
	}

	return esc_url( gstore_get_image_url( $attachment_id, $atts['size'] ) );
}
add_shortcode( 'gstore_image_url', 'gstore_image_url_shortcode' );

/**
 * Shortcode para retornar tag <img> completa da biblioteca.
 * 
 * Uso: [gstore_image id="123" size="full" alt="Descrição"]
 * 
 * @param array $atts Atributos do shortcode.
 * @return string Tag <img> completa.
 */
function gstore_image_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'   => 0,
			'size' => 'full',
			'alt'  => '',
		),
		$atts,
		'gstore_image'
	);

	$attachment_id = absint( $atts['id'] );
	if ( ! $attachment_id ) {
		return '';
	}

	return gstore_get_image_tag( $attachment_id, $atts['size'], $atts['alt'] );
}
add_shortcode( 'gstore_image', 'gstore_image_shortcode' );

/**
 * Shortcode para renderizar o banner do YouTube condicionalmente.
 * Só exibe se o banner estiver configurado em Configurações Gstore.
 *
 * Uso: [gstore_banner_youtube]
 *
 * @return string HTML do banner ou string vazia se não configurado.
 */
function gstore_banner_youtube_shortcode() {
	$banner_id = gstore_get_banner_youtube_id();
	
	if ( $banner_id <= 0 ) {
		return ''; // Não exibe nada se não estiver configurado
	}
	
	$banner_url = wp_get_attachment_url( $banner_id );
	$banner_alt = esc_attr( get_option( 'gstore_banner_youtube_alt', 'Conheça o conteúdo da CAC Armas no YouTube' ) );
	$banner_link = esc_url( get_option( 'gstore_banner_youtube_link', '' ) );
	
	if ( empty( $banner_url ) ) {
		return '';
	}
	
	$img_tag = sprintf(
		'<img src="%s" alt="%s" />',
		esc_url( $banner_url ),
		$banner_alt
	);
	
	// Se houver link configurado, envolve a imagem em um link
	if ( ! empty( $banner_link ) ) {
		$img_tag = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			$banner_link,
			$img_tag
		);
	}
	
	$html = sprintf(
		'<figure class="wp-block-image alignfull Gstore-home-transition">
			%s
		</figure>',
		$img_tag
	);
	
	// Remove <br> tags dentro do figure
	$html = preg_replace( '#<br\s*/?>#i', '', $html );
	
	return $html;
}
add_shortcode( 'gstore_banner_youtube', 'gstore_banner_youtube_shortcode' );

/**
 * Remove <br> tags de dentro de elementos figure com classe Gstore-home-transition.
 *
 * @param string $content Conteúdo HTML.
 * @return string Conteúdo processado.
 */
function gstore_remove_br_from_banner_figure( $content ) {
	// Remove <br> tags dentro de figure com classe Gstore-home-transition
	$content = preg_replace(
		'#(<figure[^>]*class="[^"]*Gstore-home-transition[^"]*"[^>]*>.*?)(<br\s*/?>)(.*?</figure>)#is',
		'$1$3',
		$content
	);
	
	return $content;
}
add_filter( 'the_content', 'gstore_remove_br_from_banner_figure', 20 );
add_filter( 'render_block', 'gstore_remove_br_from_banner_figure', 20 );

/**
 * ============================================
 * PÁGINA DE CONFIGURAÇÕES DO TEMA - ADMIN
 * ============================================
 */

/**
 * Adiciona página de configurações do tema no menu do admin.
 */
function gstore_add_theme_settings_page() {
	add_theme_page(
		__( 'Configurações do Tema Gstore', 'gstore' ),
		__( 'Configurações Gstore', 'gstore' ),
		'manage_options',
		'gstore-settings',
		'gstore_render_settings_page'
	);
}
add_action( 'admin_menu', 'gstore_add_theme_settings_page' );

/**
 * Registra as opções do tema.
 */
function gstore_register_theme_settings() {
	// Hero Slider - Configurações de quantidade
	register_setting( 'gstore_settings', 'gstore_hero_slides_desktop_count', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 2,
	) );
	register_setting( 'gstore_settings', 'gstore_hero_slides_mobile_count', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 2,
	) );
	
	// Hero Slider - Slides Desktop (até 10)
	for ( $i = 1; $i <= 10; $i++ ) {
		register_setting( 'gstore_settings', "gstore_hero_desktop_slide_{$i}_id", array(
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 0,
		) );
		register_setting( 'gstore_settings', "gstore_hero_desktop_slide_{$i}_alt", array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		) );
		register_setting( 'gstore_settings', "gstore_hero_desktop_slide_{$i}_link", array(
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default' => '',
		) );
	}
	
	// Hero Slider - Slides Mobile (até 10)
	for ( $i = 1; $i <= 10; $i++ ) {
		register_setting( 'gstore_settings', "gstore_hero_mobile_slide_{$i}_id", array(
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 0,
		) );
		register_setting( 'gstore_settings', "gstore_hero_mobile_slide_{$i}_alt", array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		) );
		register_setting( 'gstore_settings', "gstore_hero_mobile_slide_{$i}_link", array(
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default' => '',
		) );
	}
	
	// Banner YouTube
	register_setting( 'gstore_settings', 'gstore_banner_youtube_id', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 0,
	) );
	register_setting( 'gstore_settings', 'gstore_banner_youtube_alt', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => 'Conheça o conteúdo da CAC Armas no YouTube',
	) );
	register_setting( 'gstore_settings', 'gstore_banner_youtube_link', array(
		'type' => 'string',
		'sanitize_callback' => 'esc_url_raw',
		'default' => '',
	) );
	
	// Logo do Site
	register_setting( 'gstore_settings', 'gstore_logo_id', array(
		'type' => 'integer',
		'sanitize_callback' => 'absint',
		'default' => 0,
	) );
	register_setting( 'gstore_settings', 'gstore_logo_alt', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default' => 'Logo CAC Armas',
	) );
	
	// Cor de Accent para Design Tokens
	register_setting( 'gstore_design_tokens', 'gstore_accent_color', array(
		'type' => 'string',
		'sanitize_callback' => 'sanitize_hex_color',
		'default' => '#b5a642',
	) );
}
add_action( 'admin_init', 'gstore_register_theme_settings' );

/**
 * Renderiza a página de configurações.
 */
function gstore_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// Verifica se o formulário foi submetido
	if ( isset( $_GET['settings-updated'] ) ) {
		add_settings_error( 'gstore_messages', 'gstore_message', __( 'Configurações salvas com sucesso!', 'gstore' ), 'updated' );
	}
	
	settings_errors( 'gstore_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><?php _e( 'Configure as imagens do tema Gstore. Selecione as imagens da biblioteca de mídia do WordPress.', 'gstore' ); ?></p>
		
		<form action="options.php" method="post">
			<?php
			settings_fields( 'gstore_settings' );
			do_settings_sections( 'gstore_settings' );
			?>
			
			<h2 class="title"><?php _e( 'Logo do Site', 'gstore' ); ?></h2>
			<p class="description"><?php _e( 'Configure a logo que será exibida no header do site. Se não houver logo configurada, será exibido o título do site.', 'gstore' ); ?></p>
			
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="gstore_logo_id"><?php _e( 'Logo', 'gstore' ); ?></label>
					</th>
					<td>
						<?php gstore_render_media_selector( 'gstore_logo_id', 'gstore_logo_alt', get_option( 'gstore_logo_id', 0 ), get_option( 'gstore_logo_alt', 'Logo CAC Armas' ) ); ?>
					</td>
				</tr>
			</table>
			
			<h2 class="title"><?php _e( 'Hero Slider - Slides da Página Inicial', 'gstore' ); ?></h2>
			<p class="description"><?php _e( 'Configure as imagens do slider principal da página inicial. Os slides de Desktop e Mobile são configurados separadamente para otimização de performance e experiência do usuário.', 'gstore' ); ?></p>
			
			<!-- Configuração de Quantidade de Slides -->
			<div class="gstore-slider-config" style="display: flex; gap: 30px; margin: 20px 0; flex-wrap: wrap;">
				<div style="flex: 1; min-width: 200px;">
					<label for="gstore_hero_slides_desktop_count" style="display: block; font-weight: 600; margin-bottom: 8px;">
						<span class="dashicons dashicons-desktop" style="vertical-align: middle;"></span>
						<?php _e( 'Quantidade de Slides Desktop', 'gstore' ); ?>
					</label>
					<select id="gstore_hero_slides_desktop_count" name="gstore_hero_slides_desktop_count" class="regular-text">
						<?php $desktop_count = absint( get_option( 'gstore_hero_slides_desktop_count', 2 ) ); ?>
						<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
							<option value="<?php echo $i; ?>" <?php selected( $desktop_count, $i ); ?>>
								<?php echo $i; ?> <?php echo $i === 1 ? 'slide' : 'slides'; ?>
							</option>
						<?php endfor; ?>
					</select>
				</div>
				<div style="flex: 1; min-width: 200px;">
					<label for="gstore_hero_slides_mobile_count" style="display: block; font-weight: 600; margin-bottom: 8px;">
						<span class="dashicons dashicons-smartphone" style="vertical-align: middle;"></span>
						<?php _e( 'Quantidade de Slides Mobile', 'gstore' ); ?>
					</label>
					<select id="gstore_hero_slides_mobile_count" name="gstore_hero_slides_mobile_count" class="regular-text">
						<?php $mobile_count = absint( get_option( 'gstore_hero_slides_mobile_count', 2 ) ); ?>
						<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
							<option value="<?php echo $i; ?>" <?php selected( $mobile_count, $i ); ?>>
								<?php echo $i; ?> <?php echo $i === 1 ? 'slide' : 'slides'; ?>
							</option>
						<?php endfor; ?>
					</select>
				</div>
			</div>
			
			<!-- Tabs para Desktop/Mobile -->
			<div class="gstore-slider-tabs" style="margin-top: 30px;">
				<nav class="nav-tab-wrapper">
					<a href="#gstore-desktop-slides" class="nav-tab nav-tab-active" data-tab="desktop">
						<span class="dashicons dashicons-desktop" style="vertical-align: middle;"></span>
						<?php _e( 'Slides Desktop', 'gstore' ); ?>
					</a>
					<a href="#gstore-mobile-slides" class="nav-tab" data-tab="mobile">
						<span class="dashicons dashicons-smartphone" style="vertical-align: middle;"></span>
						<?php _e( 'Slides Mobile', 'gstore' ); ?>
					</a>
				</nav>
				
				<!-- Desktop Slides -->
				<div id="gstore-desktop-slides" class="gstore-tab-content" style="background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 20px;">
					<p class="description" style="margin-top: 0;">
						<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
						<?php _e( 'Imagens recomendadas: 1920x600px ou proporção similar para desktop.', 'gstore' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<?php for ( $i = 1; $i <= 10; $i++ ) : 
							$slide_id = get_option( "gstore_hero_desktop_slide_{$i}_id", 0 );
							$slide_alt = get_option( "gstore_hero_desktop_slide_{$i}_alt", '' );
							$slide_link = get_option( "gstore_hero_desktop_slide_{$i}_link", '' );
							$is_visible = $i <= $desktop_count;
						?>
							<tr class="gstore-desktop-slide-row" data-slide="<?php echo $i; ?>" style="<?php echo ! $is_visible ? 'display: none;' : ''; ?>">
								<th scope="row">
									<label for="gstore_hero_desktop_slide_<?php echo $i; ?>_id">
										<?php printf( __( 'Slide %d', 'gstore' ), $i ); ?>
									</label>
								</th>
								<td>
									<?php gstore_render_media_selector_with_link( 
										"gstore_hero_desktop_slide_{$i}_id", 
										"gstore_hero_desktop_slide_{$i}_alt",
										"gstore_hero_desktop_slide_{$i}_link",
										$slide_id, 
										$slide_alt,
										$slide_link
									); ?>
								</td>
							</tr>
						<?php endfor; ?>
					</table>
				</div>
				
				<!-- Mobile Slides -->
				<div id="gstore-mobile-slides" class="gstore-tab-content" style="background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 20px; display: none;">
					<p class="description" style="margin-top: 0;">
						<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
						<?php _e( 'Imagens recomendadas: 768x900px ou proporção vertical para mobile.', 'gstore' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<?php for ( $i = 1; $i <= 10; $i++ ) : 
							$slide_id = get_option( "gstore_hero_mobile_slide_{$i}_id", 0 );
							$slide_alt = get_option( "gstore_hero_mobile_slide_{$i}_alt", '' );
							$slide_link = get_option( "gstore_hero_mobile_slide_{$i}_link", '' );
							$is_visible = $i <= $mobile_count;
						?>
							<tr class="gstore-mobile-slide-row" data-slide="<?php echo $i; ?>" style="<?php echo ! $is_visible ? 'display: none;' : ''; ?>">
								<th scope="row">
									<label for="gstore_hero_mobile_slide_<?php echo $i; ?>_id">
										<?php printf( __( 'Slide %d', 'gstore' ), $i ); ?>
									</label>
								</th>
								<td>
									<?php gstore_render_media_selector_with_link( 
										"gstore_hero_mobile_slide_{$i}_id", 
										"gstore_hero_mobile_slide_{$i}_alt",
										"gstore_hero_mobile_slide_{$i}_link",
										$slide_id, 
										$slide_alt,
										$slide_link
									); ?>
								</td>
							</tr>
						<?php endfor; ?>
					</table>
				</div>
			</div>
			
			<script>
			jQuery(document).ready(function($) {
				// Tabs Desktop/Mobile
				$('.gstore-slider-tabs .nav-tab').on('click', function(e) {
					e.preventDefault();
					var tab = $(this).data('tab');
					$('.gstore-slider-tabs .nav-tab').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active');
					$('.gstore-tab-content').hide();
					$('#gstore-' + tab + '-slides').show();
				});
				
				// Mostra/esconde slides baseado na quantidade selecionada
				$('#gstore_hero_slides_desktop_count').on('change', function() {
					var count = parseInt($(this).val());
					$('.gstore-desktop-slide-row').each(function() {
						var slideNum = parseInt($(this).data('slide'));
						$(this).toggle(slideNum <= count);
					});
				});
				
				$('#gstore_hero_slides_mobile_count').on('change', function() {
					var count = parseInt($(this).val());
					$('.gstore-mobile-slide-row').each(function() {
						var slideNum = parseInt($(this).data('slide'));
						$(this).toggle(slideNum <= count);
					});
				});
			});
			</script>
			
			<h2 class="title"><?php _e( 'Banners', 'gstore' ); ?></h2>
			<p class="description"><?php _e( 'Configure os banners exibidos no site.', 'gstore' ); ?></p>
			
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="gstore_banner_youtube_id"><?php _e( 'Banner YouTube', 'gstore' ); ?></label>
					</th>
					<td>
						<?php gstore_render_media_selector( 'gstore_banner_youtube_id', 'gstore_banner_youtube_alt', get_option( 'gstore_banner_youtube_id', 0 ), get_option( 'gstore_banner_youtube_alt', 'Conheça o conteúdo da CAC Armas no YouTube' ) ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gstore_banner_youtube_link"><?php _e( 'Link do Banner', 'gstore' ); ?></label>
					</th>
					<td>
						<input type="url" id="gstore_banner_youtube_link" name="gstore_banner_youtube_link" value="<?php echo esc_attr( get_option( 'gstore_banner_youtube_link', '' ) ); ?>" class="regular-text" placeholder="https://..." />
						<p class="description"><?php _e( 'URL para onde o banner deve redirecionar quando clicado. Deixe em branco se não quiser que o banner seja clicável.', 'gstore' ); ?></p>
					</td>
				</tr>
			</table>
			
			<?php submit_button( __( 'Salvar Configurações', 'gstore' ) ); ?>
		</form>
		
		<hr style="margin: 40px 0;" />
		
		<h2 class="title"><?php _e( 'Informações da Loja (JSON)', 'gstore' ); ?></h2>
		<p class="description">
			<?php _e( 'Gerencie as informações centralizadas da loja (nome, contatos, footer, etc). Útil para migrar configurações entre lojas.', 'gstore' ); ?>
		</p>
		
		<div class="gstore-store-info-actions" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
			<!-- Exportar -->
			<div class="gstore-export-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; flex: 1; min-width: 280px;">
				<h3 style="margin-top: 0;"><?php _e( 'Exportar Configurações', 'gstore' ); ?></h3>
				<p><?php _e( 'Baixe um arquivo JSON com todas as informações da loja para backup ou migração.', 'gstore' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gstore_export_store_info" />
					<?php wp_nonce_field( 'gstore_export_store_info', 'gstore_export_nonce' ); ?>
					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-download" style="margin-right: 5px; vertical-align: middle;"></span>
						<?php _e( 'Exportar JSON', 'gstore' ); ?>
					</button>
				</form>
			</div>
			
			<!-- Importar -->
			<div class="gstore-import-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; flex: 1; min-width: 280px;">
				<h3 style="margin-top: 0;"><?php _e( 'Importar Configurações', 'gstore' ); ?></h3>
				<p><?php _e( 'Carregue um arquivo JSON previamente exportado para atualizar as informações da loja.', 'gstore' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="gstore_import_store_info" />
					<?php wp_nonce_field( 'gstore_import_store_info', 'gstore_import_nonce' ); ?>
					<input type="file" name="store_info_file" accept=".json" required style="margin-bottom: 10px; display: block;" />
					<button type="submit" class="button button-secondary">
						<span class="dashicons dashicons-upload" style="margin-right: 5px; vertical-align: middle;"></span>
						<?php _e( 'Importar JSON', 'gstore' ); ?>
					</button>
				</form>
			</div>
		</div>
		
		<!-- Preview dos dados atuais -->
		<div class="gstore-current-info" style="margin-top: 30px;">
			<h3><?php _e( 'Dados Atuais da Loja', 'gstore' ); ?></h3>
			<p class="description"><?php _e( 'Pré-visualização das informações configuradas no arquivo JSON.', 'gstore' ); ?></p>
			
			<?php
			$store_info = gstore_store_info();
			$data = $store_info->get_all();
			?>
			
			<table class="widefat" style="max-width: 800px; margin-top: 15px;">
				<tbody>
					<tr>
						<th style="width: 200px;"><?php _e( 'Nome da Loja', 'gstore' ); ?></th>
						<td><?php echo esc_html( $data['store']['name'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'CNPJ', 'gstore' ); ?></th>
						<td><?php echo esc_html( $data['store']['cnpj'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'WhatsApp', 'gstore' ); ?></th>
						<td><?php echo esc_html( $data['contact']['whatsapp_display'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'Instagram', 'gstore' ); ?></th>
						<td>@<?php echo esc_html( $data['social']['instagram'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'Endereço', 'gstore' ); ?></th>
						<td><?php echo esc_html( gstore_get_address( 'short' ) ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'Horário de Funcionamento', 'gstore' ); ?></th>
						<td><?php echo esc_html( $data['business_hours']['full_text'] ?? '' ); ?></td>
					</tr>
				</tbody>
			</table>
			
			<p style="margin-top: 15px;">
				<a href="<?php echo esc_url( admin_url( 'theme-editor.php?file=store-info.json&theme=' . get_stylesheet() ) ); ?>" class="button button-link" target="_blank">
					<span class="dashicons dashicons-edit" style="margin-right: 5px; vertical-align: middle;"></span>
					<?php _e( 'Editar JSON diretamente', 'gstore' ); ?>
				</a>
				<span class="description" style="margin-left: 10px;"><?php _e( '(Cuidado: edição manual pode quebrar a estrutura)', 'gstore' ); ?></span>
			</p>
		</div>

		<hr style="margin: 40px 0;" />

		<h2 class="title"><?php _e( 'Sincronização GitHub', 'gstore' ); ?></h2>
		<p class="description">
			<?php _e( 'Mantenha os arquivos do tema atualizados diretamente do repositório GitHub.', 'gstore' ); ?>
		</p>

		<div class="gstore-github-sync" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-top: 20px; max-width: 800px;">
			<h3 style="margin-top: 0;"><?php _e( 'Atualizar Tema via Git', 'gstore' ); ?></h3>
			<p><?php _e( 'Este comando irá executar um "git pull" (fetch + reset --hard) para sincronizar os arquivos locais com a versão mais recente do branch principal no GitHub.', 'gstore' ); ?></p>
			
			<div style="margin-top: 20px;">
				<button type="button" class="button button-primary gstore-theme-git-update" data-nonce="<?php echo esc_attr( wp_create_nonce( 'gstore_theme_git_pull' ) ); ?>">
					<span class="dashicons dashicons-cloud-upload" style="margin-right: 5px; vertical-align: middle;"></span>
					<?php _e( 'Sincronizar Agora', 'gstore' ); ?>
				</button>
				<p class="description" style="margin-top: 10px;">
					<?php _e( 'Atenção: Quaisquer alterações locais não commitadas serão perdidas.', 'gstore' ); ?>
				</p>
			</div>
		</div>
	</div>
	
	<style>
		.gstore-media-selector {
			display: flex;
			align-items: flex-start;
			gap: 15px;
			margin-bottom: 10px;
		}
		.gstore-media-preview {
			width: 150px;
			height: 150px;
			border: 2px dashed #ccc;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #f9f9f9;
			border-radius: 4px;
		}
		.gstore-media-preview img {
			max-width: 100%;
			max-height: 100%;
			object-fit: contain;
		}
		.gstore-media-preview.has-image {
			border-color: #2271b1;
			border-style: solid;
		}
		.gstore-media-controls {
			flex: 1;
		}
		.gstore-media-controls input[type="hidden"] {
			display: none;
		}
		.gstore-media-controls .button {
			margin-right: 5px;
		}
		.gstore-media-controls .description {
			margin-top: 8px;
			font-style: italic;
			color: #646970;
		}
		.gstore-alt-field {
			margin-top: 10px;
		}
		.gstore-alt-field label {
			display: block;
			margin-bottom: 5px;
			font-weight: 600;
		}
		.gstore-alt-field input[type="text"] {
			width: 100%;
			max-width: 500px;
		}
	</style>
	
	<script>
	jQuery(document).ready(function($) {
		// Abre o seletor de mídia
		$(document).on('click', '.gstore-select-media', function(e) {
			e.preventDefault();
			
			var button = $(this);
			var inputId = button.data('input-id');
			var previewId = button.data('preview-id');
			var input = $('#' + inputId);
			var preview = $('#' + previewId);
			
			var mediaUploader = wp.media({
				title: 'Selecione uma imagem',
				button: {
					text: 'Usar esta imagem'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});
			
			mediaUploader.on('select', function() {
				var attachment = mediaUploader.state().get('selection').first().toJSON();
				input.val(attachment.id);
				preview.html('<img src="' + attachment.url + '" alt="' + attachment.alt + '" />');
				preview.addClass('has-image');
				preview.closest('.gstore-media-selector').find('.gstore-remove-media').show();
			});
			
			mediaUploader.open();
		});
		
		// Remove a imagem selecionada
		$(document).on('click', '.gstore-remove-media', function(e) {
			e.preventDefault();
			
			var button = $(this);
			var inputId = button.data('input-id');
			var previewId = button.data('preview-id');
			var input = $('#' + inputId);
			var preview = $('#' + previewId);
			
			input.val(0);
			preview.html('<span style="color: #999;">Nenhuma imagem selecionada</span>');
			preview.removeClass('has-image');
			button.hide();
		});
		
		// Carrega previews existentes ao carregar a página
		$('.gstore-media-preview').each(function() {
			var preview = $(this);
			var inputId = preview.data('input-id');
			var input = $('#' + inputId);
			var imageId = input.val();
			
			if (imageId && imageId != '0') {
				$.ajax({
					url: gstoreSettings.ajax_url,
					type: 'POST',
					data: {
						action: 'gstore_get_image_data',
						image_id: imageId,
						nonce: gstoreSettings.nonce
					},
					success: function(response) {
						if (response.success && response.data.url) {
							preview.html('<img src="' + response.data.url + '" alt="' + (response.data.alt || '') + '" />');
							preview.addClass('has-image');
							preview.closest('.gstore-media-selector').find('.gstore-remove-media').show();
						}
					}
				});
			}
		});
	});
	</script>
	<?php
}

/**
 * Renderiza o seletor de mídia.
 */
function gstore_render_media_selector( $input_id, $alt_input_id, $current_id = 0, $current_alt = '' ) {
	$preview_id = $input_id . '_preview';
	$has_image = $current_id > 0;
	?>
	<div class="gstore-media-selector">
		<div id="<?php echo esc_attr( $preview_id ); ?>" class="gstore-media-preview" data-input-id="<?php echo esc_attr( $input_id ); ?>">
			<?php if ( $has_image ) : ?>
				<?php
				$image_url = wp_get_attachment_image_url( $current_id, 'thumbnail' );
				if ( $image_url ) :
					?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $current_alt ); ?>" />
				<?php else : ?>
					<span style="color: #999;">Imagem não encontrada</span>
				<?php endif; ?>
			<?php else : ?>
				<span style="color: #999;">Nenhuma imagem selecionada</span>
			<?php endif; ?>
		</div>
		<div class="gstore-media-controls">
			<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( $current_id ); ?>" />
			<button type="button" class="button gstore-select-media" data-input-id="<?php echo esc_attr( $input_id ); ?>" data-preview-id="<?php echo esc_attr( $preview_id ); ?>">
				<?php _e( 'Selecionar Imagem', 'gstore' ); ?>
			</button>
			<button type="button" class="button gstore-remove-media" data-input-id="<?php echo esc_attr( $input_id ); ?>" data-preview-id="<?php echo esc_attr( $preview_id ); ?>" style="<?php echo $has_image ? '' : 'display: none;'; ?>">
				<?php _e( 'Remover', 'gstore' ); ?>
			</button>
			<p class="description">
				<?php _e( 'ID da imagem:', 'gstore' ); ?> <strong><?php echo esc_html( $current_id ? $current_id : 'Nenhuma' ); ?></strong>
			</p>
			
			<div class="gstore-alt-field">
				<label for="<?php echo esc_attr( $alt_input_id ); ?>">
					<?php _e( 'Texto Alternativo (Alt)', 'gstore' ); ?>
				</label>
				<input type="text" id="<?php echo esc_attr( $alt_input_id ); ?>" name="<?php echo esc_attr( $alt_input_id ); ?>" value="<?php echo esc_attr( $current_alt ); ?>" class="regular-text" />
				<p class="description"><?php _e( 'Descrição da imagem para acessibilidade e SEO.', 'gstore' ); ?></p>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Renderiza o seletor de mídia com campo de link.
 */
function gstore_render_media_selector_with_link( $input_id, $alt_input_id, $link_input_id, $current_id = 0, $current_alt = '', $current_link = '' ) {
	$preview_id = $input_id . '_preview';
	$has_image = $current_id > 0;
	?>
	<div class="gstore-media-selector">
		<div id="<?php echo esc_attr( $preview_id ); ?>" class="gstore-media-preview" data-input-id="<?php echo esc_attr( $input_id ); ?>">
			<?php if ( $has_image ) : ?>
				<?php
				$image_url = wp_get_attachment_image_url( $current_id, 'thumbnail' );
				if ( $image_url ) :
					?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $current_alt ); ?>" />
				<?php else : ?>
					<span style="color: #999;">Imagem não encontrada</span>
				<?php endif; ?>
			<?php else : ?>
				<span style="color: #999;">Nenhuma imagem selecionada</span>
			<?php endif; ?>
		</div>
		<div class="gstore-media-controls">
			<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( $current_id ); ?>" />
			<button type="button" class="button gstore-select-media" data-input-id="<?php echo esc_attr( $input_id ); ?>" data-preview-id="<?php echo esc_attr( $preview_id ); ?>">
				<?php _e( 'Selecionar Imagem', 'gstore' ); ?>
			</button>
			<button type="button" class="button gstore-remove-media" data-input-id="<?php echo esc_attr( $input_id ); ?>" data-preview-id="<?php echo esc_attr( $preview_id ); ?>" style="<?php echo $has_image ? '' : 'display: none;'; ?>">
				<?php _e( 'Remover', 'gstore' ); ?>
			</button>
			<p class="description">
				<?php _e( 'ID da imagem:', 'gstore' ); ?> <strong><?php echo esc_html( $current_id ? $current_id : 'Nenhuma' ); ?></strong>
			</p>
			
			<div class="gstore-alt-field">
				<label for="<?php echo esc_attr( $alt_input_id ); ?>">
					<?php _e( 'Texto Alternativo (Alt)', 'gstore' ); ?>
				</label>
				<input type="text" id="<?php echo esc_attr( $alt_input_id ); ?>" name="<?php echo esc_attr( $alt_input_id ); ?>" value="<?php echo esc_attr( $current_alt ); ?>" class="regular-text" />
			</div>
			
			<div class="gstore-link-field" style="margin-top: 10px;">
				<label for="<?php echo esc_attr( $link_input_id ); ?>">
					<?php _e( 'Link do Slide (opcional)', 'gstore' ); ?>
				</label>
				<input type="url" id="<?php echo esc_attr( $link_input_id ); ?>" name="<?php echo esc_attr( $link_input_id ); ?>" value="<?php echo esc_attr( $current_link ); ?>" class="regular-text" placeholder="https://..." />
				<p class="description"><?php _e( 'URL para onde o slide deve redirecionar quando clicado.', 'gstore' ); ?></p>
			</div>
		</div>
	</div>
	<?php
}

/**
 * AJAX: Retorna dados de uma imagem.
 */
function gstore_ajax_get_image_data() {
	check_ajax_referer( 'gstore_ajax', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}
	
	$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
	
	if ( ! $image_id ) {
		wp_send_json_error( array( 'message' => __( 'ID da imagem não fornecido.', 'gstore' ) ) );
	}
	
	$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
	$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
	
	if ( ! $image_url ) {
		wp_send_json_error( array( 'message' => __( 'Imagem não encontrada.', 'gstore' ) ) );
	}
	
	wp_send_json_success( array(
		'url' => $image_url,
		'alt' => $image_alt,
		'id'  => $image_id,
	) );
}
add_action( 'wp_ajax_gstore_get_image_data', 'gstore_ajax_get_image_data' );

/**
 * Enfileira scripts e estilos necessários na página de configurações.
 */
function gstore_enqueue_settings_assets( $hook ) {
	if ( 'appearance_page_gstore-settings' !== $hook ) {
		return;
	}
	
	wp_enqueue_media();
	wp_enqueue_script( 'jquery' );
	
	// Localiza script para AJAX
	wp_localize_script( 'jquery', 'gstoreSettings', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'gstore_ajax' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'gstore_enqueue_settings_assets' );

/**
 * Funções helper para obter IDs configurados.
 */
/**
 * Funções para o Hero Slider com suporte a Desktop/Mobile.
 */

/**
 * Obtém a quantidade de slides configurada.
 *
 * @param string $device 'desktop' ou 'mobile'.
 * @return int Quantidade de slides.
 */
function gstore_get_hero_slides_count( $device = 'desktop' ) {
	$option_name = 'gstore_hero_slides_' . $device . '_count';
	return absint( get_option( $option_name, 2 ) );
}

/**
 * Obtém dados de um slide específico.
 *
 * @param string $device 'desktop' ou 'mobile'.
 * @param int    $index  Índice do slide (1-10).
 * @return array Array com 'id', 'alt', 'link', 'url'.
 */
function gstore_get_hero_slide( $device, $index ) {
	$prefix = "gstore_hero_{$device}_slide_{$index}";
	$slide_id = absint( get_option( "{$prefix}_id", 0 ) );
	
	$data = array(
		'id'   => $slide_id,
		'alt'  => get_option( "{$prefix}_alt", '' ),
		'link' => get_option( "{$prefix}_link", '' ),
		'url'  => '',
	);
	
	if ( $slide_id > 0 ) {
		$data['url'] = wp_get_attachment_url( $slide_id );
	}
	
	return $data;
}

/**
 * Obtém todos os slides configurados para um dispositivo.
 *
 * @param string $device 'desktop' ou 'mobile'.
 * @return array Array de slides.
 */
function gstore_get_hero_slides( $device = 'desktop' ) {
	$count = gstore_get_hero_slides_count( $device );
	$slides = array();
	
	for ( $i = 1; $i <= $count; $i++ ) {
		$slide = gstore_get_hero_slide( $device, $i );
		if ( $slide['id'] > 0 ) {
			$slides[] = $slide;
		}
	}
	
	return $slides;
}

/**
 * Gera o HTML do Hero Slider com suporte a Desktop/Mobile.
 *
 * @return string HTML do slider.
 */
function gstore_render_hero_slider() {
	$desktop_slides = gstore_get_hero_slides( 'desktop' );
	$mobile_slides = gstore_get_hero_slides( 'mobile' );
	
	// Se não houver slides, retorna vazio
	if ( empty( $desktop_slides ) && empty( $mobile_slides ) ) {
		return '';
	}
	
	ob_start();
	?>
	<div class="Gstore-hero-slider" data-gstore-hero-slider>
		<!-- Desktop Slides -->
		<div class="Gstore-hero-slider__track Gstore-hero-slider__track--desktop" data-gstore-hero-track>
			<?php 
			$is_first = true;
			foreach ( $desktop_slides as $slide ) : 
				$img_tag = gstore_get_hero_image_tag( $slide['id'], $slide['alt'], $is_first );
			?>
				<figure class="Gstore-hero-slider__slide" data-gstore-hero-slide>
					<?php if ( ! empty( $slide['link'] ) ) : ?>
						<a href="<?php echo esc_url( $slide['link'] ); ?>">
							<?php echo $img_tag; ?>
						</a>
					<?php else : ?>
						<?php echo $img_tag; ?>
					<?php endif; ?>
				</figure>
			<?php 
				$is_first = false;
			endforeach; 
			?>
		</div>
		
		<!-- Mobile Slides -->
		<div class="Gstore-hero-slider__track Gstore-hero-slider__track--mobile" data-gstore-hero-track-mobile>
			<?php 
			$is_first = true;
			foreach ( $mobile_slides as $slide ) : 
				$img_tag = gstore_get_hero_image_tag( $slide['id'], $slide['alt'], $is_first );
			?>
				<figure class="Gstore-hero-slider__slide" data-gstore-hero-slide-mobile>
					<?php if ( ! empty( $slide['link'] ) ) : ?>
						<a href="<?php echo esc_url( $slide['link'] ); ?>">
							<?php echo $img_tag; ?>
						</a>
					<?php else : ?>
						<?php echo $img_tag; ?>
					<?php endif; ?>
				</figure>
			<?php 
				$is_first = false;
			endforeach; 
			?>
		</div>
		
		<?php if ( count( $desktop_slides ) > 1 || count( $mobile_slides ) > 1 ) : ?>
			<button class="Gstore-hero-slider__control Gstore-hero-slider__control--prev" type="button" aria-label="Slide anterior" data-gstore-hero-prev>
				<span aria-hidden="true">
					<i class="fa-solid fa-chevron-left"></i>
				</span>
			</button>
			
			<button class="Gstore-hero-slider__control Gstore-hero-slider__control--next" type="button" aria-label="Próximo slide" data-gstore-hero-next>
				<span aria-hidden="true">
					<i class="fa-solid fa-chevron-right"></i>
				</span>
			</button>
			
			<!-- Desktop Dots -->
			<?php if ( count( $desktop_slides ) > 1 ) : ?>
				<div class="Gstore-hero-slider__dots Gstore-hero-slider__dots--desktop" role="tablist">
					<?php for ( $i = 0; $i < count( $desktop_slides ); $i++ ) : ?>
						<button class="Gstore-hero-slider__dot <?php echo $i === 0 ? 'is-active' : ''; ?>" type="button" role="tab" aria-label="Mostrar slide <?php echo $i + 1; ?>" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" data-gstore-hero-dot="<?php echo $i; ?>"></button>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
			
			<!-- Mobile Dots -->
			<?php if ( count( $mobile_slides ) > 1 ) : ?>
				<div class="Gstore-hero-slider__dots Gstore-hero-slider__dots--mobile" role="tablist">
					<?php for ( $i = 0; $i < count( $mobile_slides ); $i++ ) : ?>
						<button class="Gstore-hero-slider__dot <?php echo $i === 0 ? 'is-active' : ''; ?>" type="button" role="tab" aria-label="Mostrar slide <?php echo $i + 1; ?>" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" data-gstore-hero-dot-mobile="<?php echo $i; ?>"></button>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

// Funções de compatibilidade com o sistema antigo (deprecated)
function gstore_get_hero_slide_1_id() {
	// Tenta obter do novo sistema primeiro
	$slide = gstore_get_hero_slide( 'desktop', 1 );
	return $slide['id'];
}

function gstore_get_hero_slide_2_id() {
	// Tenta obter do novo sistema primeiro
	$slide = gstore_get_hero_slide( 'desktop', 2 );
	return $slide['id'];
}

function gstore_get_banner_youtube_id() {
	return absint( get_option( 'gstore_banner_youtube_id', 0 ) );
}

function gstore_get_logo_id() {
	return absint( get_option( 'gstore_logo_id', 0 ) );
}

/**
 * Filtro para modificar o bloco site-logo para usar a logo configurada.
 * 
 * @param string $block_content Conteúdo do bloco.
 * @param array  $block         Dados do bloco.
 * @return string
 */
function gstore_custom_site_logo_block( $block_content, $block ) {
	// Verifica se é o bloco site-logo
	if ( empty( $block['blockName'] ) || 'core/site-logo' !== $block['blockName'] ) {
		return $block_content;
	}
	
	// Verifica se está no header (pela classe ou contexto)
	$is_in_header = false;
	if ( isset( $block['attrs']['className'] ) && strpos( $block['attrs']['className'], 'Gstore-header__logo' ) !== false ) {
		$is_in_header = true;
	}
	
	// Se não está no header, não modifica
	if ( ! $is_in_header ) {
		return $block_content;
	}
	
	// Evita processamento duplicado - se já contém a marca de logo customizada
	if ( strpos( $block_content, 'data-gstore-logo="1"' ) !== false ) {
		return $block_content;
	}
	
	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();
	
	if ( $logo_id > 0 ) {
		$logo_url = gstore_get_image_url( $logo_id, 'full' );
		$logo_alt = get_option( 'gstore_logo_alt', 'Logo CAC Armas' );
		
		// Valida se a URL é válida
		if ( $logo_url && filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
			// Substitui o conteúdo do bloco pela logo configurada
			$home_url = esc_url( home_url( '/' ) );
			$site_name = esc_attr( get_bloginfo( 'name' ) );
			$logo_html = sprintf(
				'<div class="wp-block-site-logo Gstore-header__logo" data-gstore-logo="1" style="grid-area: logo;"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 36px; max-width: 180px; width: auto; height: auto;" loading="eager" /></a></div>',
				$home_url,
				$site_name,
				esc_url( $logo_url ),
				esc_attr( $logo_alt )
			);
			
			return $logo_html;
		}
	}
	
	return $block_content;
}
add_filter( 'render_block', 'gstore_custom_site_logo_block', 10, 2 );

/**
 * Filtro para modificar o bloco site-logo no footer para usar a logo configurada.
 * 
 * @param string $block_content Conteúdo do bloco.
 * @param array  $block         Dados do bloco.
 * @return string
 */
function gstore_custom_footer_logo_block( $block_content, $block ) {
	// Verifica se é o bloco site-logo
	if ( empty( $block['blockName'] ) || 'core/site-logo' !== $block['blockName'] ) {
		return $block_content;
	}
	
	// Verifica se está no footer (pela classe footer-logo)
	$is_in_footer = false;
	if ( isset( $block['attrs']['className'] ) && strpos( $block['attrs']['className'], 'footer-logo' ) !== false ) {
		$is_in_footer = true;
	}
	
	// Se não está no footer, não modifica
	if ( ! $is_in_footer ) {
		return $block_content;
	}
	
	// Evita processamento duplicado - se já contém a marca de logo customizada
	if ( strpos( $block_content, 'data-gstore-footer-logo="1"' ) !== false ) {
		return $block_content;
	}
	
	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();
	
	if ( $logo_id > 0 ) {
		$logo_url = gstore_get_image_url( $logo_id, 'full' );
		$logo_alt = get_option( 'gstore_logo_alt', 'Logo CAC Armas' );
		
		// Valida se a URL é válida
		if ( $logo_url && filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
			// Substitui o conteúdo do bloco pela logo configurada
			$home_url = esc_url( home_url( '/' ) );
			$site_name = esc_attr( get_bloginfo( 'name' ) );
			$logo_html = sprintf(
				'<div class="wp-block-site-logo footer-logo" data-gstore-footer-logo="1"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 50px; max-width: 200px; width: auto; height: auto;" loading="lazy" /></a></div>',
				$home_url,
				$site_name,
				esc_url( $logo_url ),
				esc_attr( $logo_alt )
			);
			
			return $logo_html;
		}
	}
	
	return $block_content;
}
add_filter( 'render_block', 'gstore_custom_footer_logo_block', 10, 2 );

/**
 * Filtro para garantir que o logo do tema tenha prioridade sobre o Customizer.
 * 
 * Quando há uma logo configurada no tema, desabilita o site logo do Customizer.
 */
add_filter( 'theme_mod_custom_logo', 'gstore_override_customizer_logo', 10, 1 );
function gstore_override_customizer_logo( $logo_id ) {
	$theme_logo_id = gstore_get_logo_id();
	
	// Se há uma logo configurada no tema, usa ela ao invés do Customizer
	if ( $theme_logo_id > 0 ) {
		return $theme_logo_id;
	}
	
	return $logo_id;
}

/**
 * Substitui o link de texto da logo pela imagem configurada no header HTML.
 * 
 * @param string $content Conteúdo do template part.
 * @return string
 */
function gstore_replace_header_logo_html( $content ) {
	// Evita processamento duplicado - se já contém a marca de logo customizada
	if ( strpos( $content, 'data-gstore-logo="1"' ) !== false ) {
		return $content;
	}
	
	// Evita processar se já contém uma imagem de logo
	if ( preg_match( '/<a[^>]*class="[^"]*Gstore-header__logo[^"]*"[^>]*>.*?<img[^>]*>/is', $content ) ) {
		return $content;
	}
	
	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();
	
	if ( $logo_id <= 0 ) {
		return $content;
	}
	
	$logo_url = gstore_get_image_url( $logo_id, 'full' );
	$logo_alt = get_option( 'gstore_logo_alt', 'Logo CAC Armas' );
	
	// Valida se a URL é válida
	if ( ! $logo_url || ! filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
		return $content;
	}
	
	$home_url = esc_url( home_url( '/' ) );
	$site_name = esc_attr( get_bloginfo( 'name' ) );
	
	// HTML da logo com imagem (para substituição via regex)
	$logo_html = sprintf(
		'<div class="wp-block-site-logo Gstore-header__logo" data-gstore-logo="1" style="grid-area: logo;"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 36px; max-width: 180px; width: auto; height: auto;" loading="eager" /></a></div>',
		$home_url,
		$site_name,
		esc_url( $logo_url ),
		esc_attr( $logo_alt )
	);
	
	// Padrão 1: Substitui apenas o bloco site-logo renderizado pelo WordPress
	// Captura: <div class="wp-block-site-logo...">...</div> (bloco completo)
	$pattern1 = '/<div\s+[^>]*class="[^"]*wp-block-site-logo[^"]*"[^>]*>.*?<\/div>/is';
	$replacement1 = '<div class="wp-block-site-logo Gstore-header__logo" data-gstore-logo="1" style="grid-area: logo;"><a href="' . $home_url . '" rel="home" aria-label="' . $site_name . '"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $logo_alt ) . '" style="max-height: 36px; max-width: 180px; width: auto; height: auto;" loading="eager" /></a></div>';
	$content = preg_replace( $pattern1, $replacement1, $content, 1 );
	
	// Padrão 2: Link com classe Gstore-header__logo (mas sem imagem) - apenas dentro do header content
	// Captura: <a href="/" class="Gstore-header__logo">TEXTO</a> (sem img dentro)
	// Limita a busca apenas dentro do Gstore-header__content para evitar capturar elementos errados
	if ( preg_match( '/(<div[^>]*class="[^"]*Gstore-header__content[^"]*"[^>]*>)(.*?)(<\/div>)/is', $content, $header_content_match ) ) {
		$header_content = $header_content_match[2];
		// Padrão que captura o link completo incluindo elementos filhos (span, etc)
		$pattern2 = '/<a\s+[^>]*class="[^"]*Gstore-header__logo[^"]*"[^>]*>(?!.*?<img)(?:[^<]|<(?!\/a>))*?<\/a>/is';
		$header_content_new = preg_replace( $pattern2, $logo_html, $header_content, 1 );
		if ( $header_content_new !== $header_content ) {
			$content = str_replace( $header_content_match[0], $header_content_match[1] . $header_content_new . $header_content_match[3], $content );
		}
	} else {
		// Padrão 2b: Se não encontrar Gstore-header__content, procura diretamente no header
		// Captura: <a href="/" class="Gstore-header__logo">ARMA<span>STORE</span></a> (sem img dentro)
		// Apenas dentro de elementos com classe Gstore-header para evitar substituir outros links
		if ( strpos( $content, 'Gstore-header' ) !== false ) {
			// Padrão que captura o link completo incluindo elementos filhos (span, etc)
			$pattern2b = '/<a\s+[^>]*class="[^"]*Gstore-header__logo[^"]*"[^>]*>(?!.*?<img)(?:[^<]|<(?!\/a>))*?<\/a>/is';
			$content = preg_replace( $pattern2b, $logo_html, $content, 1 );
		}
	}
	
	return $content;
}
// Remove o filtro de render_block para evitar conflito com gstore_custom_site_logo_block
// Mantém apenas nos outros filtros
add_filter( 'render_block_core/template-part', 'gstore_replace_header_logo_html', 10, 1 );
add_filter( 'the_content', 'gstore_replace_header_logo_html', 5 );

/**
 * Substitui o texto da logo pela imagem configurada no footer HTML.
 * 
 * @param string $content Conteúdo do template part.
 * @return string
 */
function gstore_replace_footer_logo_html( $content ) {
	// Verifica se é o footer (pela classe armastore-footer)
	if ( strpos( $content, 'armastore-footer' ) === false ) {
		return $content;
	}
	
	// Evita processamento duplicado - se já contém a marca de logo customizada
	if ( strpos( $content, 'data-gstore-footer-logo="1"' ) !== false ) {
		return $content;
	}
	
	// Obtém a logo configurada
	$logo_id = gstore_get_logo_id();
	
	if ( $logo_id <= 0 ) {
		return $content;
	}
	
	$logo_url = gstore_get_image_url( $logo_id, 'full' );
	$logo_alt = get_option( 'gstore_logo_alt', 'Logo CAC Armas' );
	
	// Valida se a URL é válida
	if ( ! $logo_url || ! filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
		return $content;
	}
	
	$home_url = esc_url( home_url( '/' ) );
	$site_name = esc_attr( get_bloginfo( 'name' ) );
	
	// HTML da logo com imagem para o footer
	$logo_html = sprintf(
		'<div class="wp-block-site-logo footer-logo" data-gstore-footer-logo="1"><a href="%s" rel="home" aria-label="%s"><img src="%s" alt="%s" style="max-height: 50px; max-width: 200px; width: auto; height: auto;" loading="lazy" /></a></div>',
		$home_url,
		$site_name,
		esc_url( $logo_url ),
		esc_attr( $logo_alt )
	);
	
	// Padrão 1: Substitui o bloco site-logo renderizado pelo WordPress no footer
	// Procura especificamente dentro do primeiro footer-column (onde está a logo)
	// Usa uma regex mais específica para capturar apenas o primeiro footer-column dentro do footer-main
	if ( preg_match( '/(<div[^>]*class="[^"]*footer-main[^"]*"[^>]*>)(.*?)(<\/div>)/is', $content, $footer_main_match ) ) {
		$footer_main_content = $footer_main_match[2];
		
		// Captura o primeiro footer-column
		if ( preg_match( '/(<div[^>]*class="[^"]*footer-column[^"]*"[^>]*>)(.*?)(<\/div>)/is', $footer_main_content, $footer_column_match ) ) {
			$footer_column_content = $footer_column_match[2];
			
			// Substitui o bloco site-logo se existir
			$pattern1 = '/<div\s+[^>]*class="[^"]*wp-block-site-logo[^"]*footer-logo[^"]*"[^>]*>.*?<\/div>/is';
			$footer_column_content_new = preg_replace( $pattern1, $logo_html, $footer_column_content, 1 );
			
			// Se não encontrou o bloco site-logo, tenta substituir um h3 com texto "CAC" no início
			if ( $footer_column_content_new === $footer_column_content ) {
				$pattern2 = '/<h3[^>]*>CAC\s*<span[^>]*>.*?<\/span><\/h3>/is';
				$footer_column_content_new = preg_replace( $pattern2, $logo_html, $footer_column_content, 1 );
			}
			
			// Se houve substituição, atualiza o conteúdo
			if ( $footer_column_content_new !== $footer_column_content ) {
				$footer_main_content_new = str_replace( $footer_column_match[0], $footer_column_match[1] . $footer_column_content_new . $footer_column_match[3], $footer_main_content );
				$content = str_replace( $footer_main_match[0], $footer_main_match[1] . $footer_main_content_new . $footer_main_match[3], $content );
			}
		}
	}
	
	return $content;
}
add_filter( 'render_block_core/template-part', 'gstore_replace_footer_logo_html', 10, 1 );
add_filter( 'the_content', 'gstore_replace_footer_logo_html', 5 );

/**
 * Gera tag de imagem otimizada para hero com srcset e priorização.
 * 
 * @param int    $attachment_id ID da imagem.
 * @param string $alt           Texto alternativo.
 * @param bool   $is_first_slide Se true, adiciona fetchpriority="high" e remove lazy loading.
 * @return string Tag img otimizada.
 */
function gstore_get_hero_image_tag( $attachment_id, $alt = '', $is_first_slide = false ) {
	if ( ! $attachment_id ) {
		return '';
	}

	$image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! $image_meta ) {
		return '';
	}

	// Gera srcset com múltiplos tamanhos
	$srcset_sizes = array( 'medium_large', 'large', 'full' );
	$srcset_array = array();

	// Para hero, queremos tamanhos maiores
	foreach ( $srcset_sizes as $size ) {
		$size_url = gstore_get_image_url( $attachment_id, $size );
		$size_src = wp_get_attachment_image_src( $attachment_id, $size );
		
		if ( $size_url && $size_src && isset( $size_src[1] ) ) {
			$srcset_array[] = esc_url( $size_url ) . ' ' . $size_src[1] . 'w';
		}
	}

	// URL principal (usa full como padrão)
	$src_url = gstore_get_image_url( $attachment_id, 'full' );
	if ( empty( $src_url ) ) {
		return '';
	}

	// Atributos base
	$attr = array(
		'src'     => $src_url,
		'alt'     => $alt ? $alt : '',
		'sizes'   => '100vw',
	);

	// Adiciona srcset se disponível
	if ( ! empty( $srcset_array ) ) {
		$attr['srcset'] = implode( ', ', $srcset_array );
	}

	// Primeira imagem do hero: alta prioridade, sem lazy loading
	if ( $is_first_slide ) {
		$attr['fetchpriority'] = 'high';
		$attr['loading'] = 'eager';
		$attr['decoding'] = 'sync';
	} else {
		$attr['loading'] = 'lazy';
		$attr['decoding'] = 'async';
	}

	// Width e height para evitar CLS
	$image_src = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( $image_src && isset( $image_src[1] ) && isset( $image_src[2] ) ) {
		$attr['width'] = $image_src[1];
		$attr['height'] = $image_src[2];
	}

	// Constrói a tag
	$img_tag = '<img';
	foreach ( $attr as $key => $value ) {
		if ( ! empty( $value ) || 'alt' === $key ) {
			$img_tag .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}
	$img_tag .= ' />';

	return $img_tag;
}

/**
 * Processa placeholders de imagens nos templates HTML.
 * 
 * Substitui placeholders como {{gstore_image:123}} por URLs reais da biblioteca.
 * 
 * @param string $content Conteúdo do template.
 * @return string Conteúdo processado.
 */
function gstore_process_image_placeholders( $content ) {
	if ( empty( $content ) ) {
		return $content;
	}
	
	// NOVO SISTEMA: Hero Slider dinâmico com Desktop/Mobile
	if ( strpos( $content, '{{gstore_hero_slider}}' ) !== false ) {
		$slider_html = gstore_render_hero_slider();
		$content = str_replace( '{{gstore_hero_slider}}', $slider_html, $content );
	}
	
	// Placeholders especiais que usam configurações do tema (SISTEMA LEGADO - mantido para compatibilidade)
	// {{gstore_hero_slide_1}}, {{gstore_hero_slide_2}}, {{gstore_banner_youtube}}
	$hero_slide_1_id = gstore_get_hero_slide_1_id();
	$hero_slide_2_id = gstore_get_hero_slide_2_id();
	$banner_youtube_id = gstore_get_banner_youtube_id();
	
	// Processa hero slides com otimização (srcset + priorização)
	if ( $hero_slide_1_id > 0 ) {
		$hero_slide_1_alt = esc_attr( get_option( 'gstore_hero_slide_1_alt', 'Campanha Excedente Black Week CAC Armas' ) );
		$hero_slide_1_tag = gstore_get_hero_image_tag( $hero_slide_1_id, $hero_slide_1_alt, true );
		
		// Substitui tag img completa que contém o placeholder (flexível com qualquer ordem de atributos)
		$pattern = '/<img\s+[^>]*src=["\']\{\{gstore_hero_slide_1\}\}["\'][^>]*>/is';
		$content = preg_replace( $pattern, $hero_slide_1_tag, $content );
		
		// Fallback: substitui apenas URL se tag não foi encontrada (para compatibilidade)
		if ( strpos( $content, '{{gstore_hero_slide_1}}' ) !== false ) {
			$hero_slide_1_url = wp_get_attachment_url( $hero_slide_1_id );
			$content = str_replace( '{{gstore_hero_slide_1}}', $hero_slide_1_url, $content );
		}
	} else {
		$content = str_replace( '{{gstore_hero_slide_1}}', '', $content );
	}
	
	if ( $hero_slide_2_id > 0 ) {
		$hero_slide_2_alt = esc_attr( get_option( 'gstore_hero_slide_2_alt', 'Produtos da Black Week com a mesma condição CAC Armas' ) );
		$hero_slide_2_tag = gstore_get_hero_image_tag( $hero_slide_2_id, $hero_slide_2_alt, false );
		
		// Substitui tag img completa que contém o placeholder (flexível com qualquer ordem de atributos)
		$pattern = '/<img\s+[^>]*src=["\']\{\{gstore_hero_slide_2\}\}["\'][^>]*>/is';
		$content = preg_replace( $pattern, $hero_slide_2_tag, $content );
		
		// Fallback: substitui apenas URL se tag não foi encontrada (para compatibilidade)
		if ( strpos( $content, '{{gstore_hero_slide_2}}' ) !== false ) {
			$hero_slide_2_url = wp_get_attachment_url( $hero_slide_2_id );
			$content = str_replace( '{{gstore_hero_slide_2}}', $hero_slide_2_url, $content );
		}
	} else {
		$content = str_replace( '{{gstore_hero_slide_2}}', '', $content );
	}
	
	// Banner YouTube (não precisa de srcset, não é LCP)
	if ( $banner_youtube_id > 0 ) {
		$banner_youtube_url = wp_get_attachment_url( $banner_youtube_id );
		$banner_youtube_alt = esc_attr( get_option( 'gstore_banner_youtube_alt', 'Conheça o conteúdo da CAC Armas no YouTube' ) );
		$banner_youtube_link = esc_url( get_option( 'gstore_banner_youtube_link', '' ) );
		
		$img_tag = sprintf(
			'<img src="%s" alt="%s" />',
			esc_url( $banner_youtube_url ),
			$banner_youtube_alt
		);
		
		// Se houver link configurado, envolve a imagem em um link
		if ( ! empty( $banner_youtube_link ) ) {
			$img_tag = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				$banner_youtube_link,
				$img_tag
			);
		}
		
		// Substitui a tag img completa que contém o placeholder
		$pattern = '/<img\s+[^>]*src=["\']\{\{gstore_banner_youtube\}\}["\'][^>]*>/is';
		$content = preg_replace( $pattern, $img_tag, $content );
		
		// Fallback: substitui apenas URL se tag não foi encontrada (para compatibilidade)
		if ( strpos( $content, '{{gstore_banner_youtube}}' ) !== false ) {
			$content = str_replace( '{{gstore_banner_youtube}}', $banner_youtube_url, $content );
		}
	} else {
		$content = str_replace( '{{gstore_banner_youtube}}', '', $content );
	}
	
	// Placeholders para textos alternativos (para uso em outros contextos)
	$content = str_replace( '{{gstore_hero_slide_1_alt}}', esc_attr( get_option( 'gstore_hero_slide_1_alt', 'Campanha Excedente Black Week CAC Armas' ) ), $content );
	$content = str_replace( '{{gstore_hero_slide_2_alt}}', esc_attr( get_option( 'gstore_hero_slide_2_alt', 'Produtos da Black Week com a mesma condição CAC Armas' ) ), $content );
	$content = str_replace( '{{gstore_banner_youtube_alt}}', esc_attr( get_option( 'gstore_banner_youtube_alt', 'Conheça o conteúdo da CAC Armas no YouTube' ) ), $content );
	
	// Padrão: {{gstore_image:ID:size}} para URL apenas
	$pattern = '/\{\{gstore_image:(\d+)(?::([^}]+))?\}\}/';
	
	$content = preg_replace_callback(
		$pattern,
		function( $matches ) {
			$attachment_id = absint( $matches[1] );
			$size          = isset( $matches[2] ) && ! empty( $matches[2] ) ? $matches[2] : 'full';
			
			if ( ! $attachment_id ) {
				return '';
			}
			
			$url = gstore_get_image_url( $attachment_id, $size );
			return $url ? esc_url( $url ) : '';
		},
		$content
	);
	
	// Padrão: {{gstore_image_tag:ID:size:alt}} para tag completa
	$pattern_tag = '/\{\{gstore_image_tag:(\d+)(?::([^:}]+))?(?::([^}]+))?\}\}/';
	
	$content = preg_replace_callback(
		$pattern_tag,
		function( $matches ) {
			$attachment_id = absint( $matches[1] );
			$size          = isset( $matches[2] ) && ! empty( $matches[2] ) ? $matches[2] : 'full';
			$alt           = isset( $matches[3] ) ? $matches[3] : '';
			
			if ( ! $attachment_id ) {
				return '';
			}
			
			return gstore_get_image_tag( $attachment_id, $size, $alt );
		},
		$content
	);
	
	return $content;
}

/**
 * Filtro para processar placeholders em conteúdo de posts/páginas.
 */
add_filter( 'the_content', 'gstore_process_image_placeholders', 5 );
add_filter( 'widget_text', 'gstore_process_image_placeholders', 5 );

/**
 * Processa template parts HTML carregando e substituindo placeholders.
 * 
 * Esta função pode ser usada para processar templates HTML manualmente.
 * 
 * @param string $template_path Caminho do template part.
 * @return string Conteúdo processado.
 */
function gstore_load_template_part( $template_path ) {
	$template_file = get_theme_file_path( $template_path );
	
	if ( ! file_exists( $template_file ) ) {
		return '';
	}
	
	ob_start();
	include $template_file;
	$content = ob_get_clean();
	
	return gstore_process_image_placeholders( $content );
}

/**
 * Filtro para processar blocos HTML customizados do Gutenberg.
 * 
 * Processa placeholders quando blocos HTML são renderizados.
 */
add_filter( 'render_block_core/html', 'gstore_process_block_html', 10, 2 );
function gstore_process_block_html( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_image_placeholders( $block_content );
	}
	return $block_content;
}

/**
 * Filtro para processar template parts quando renderizados.
 * 
 * Processa placeholders em template parts HTML do Gutenberg.
 */
add_filter( 'render_block_core/template-part', 'gstore_process_template_part_block', 10, 2 );
function gstore_process_template_part_block( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_image_placeholders( $block_content );
	}
	return $block_content;
}

/**
 * Filtro para processar blocos de imagem do Gutenberg.
 * 
 * Processa placeholders em blocos de imagem.
 */
add_filter( 'render_block_core/image', 'gstore_process_image_block', 10, 2 );
function gstore_process_image_block( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_image_placeholders( $block_content );
	}
	return $block_content;
}

/**
 * Filtro para processar todo o conteúdo renderizado.
 * 
 * Processa placeholders em qualquer conteúdo renderizado pelo WordPress.
 */
add_filter( 'render_block', 'gstore_process_all_blocks', 10, 2 );
function gstore_process_all_blocks( $block_content, $block ) {
	if ( ! empty( $block_content ) && is_string( $block_content ) ) {
		// Verifica se contém placeholders antes de processar
		if ( strpos( $block_content, '{{gstore_' ) !== false ) {
			$block_content = gstore_process_image_placeholders( $block_content );
		}
	}
	return $block_content;
}

/**
 * Processa o output final da página para garantir que placeholders sejam substituídos.
 * 
 * Este é um filtro de último recurso que processa todo o HTML antes de ser enviado ao navegador.
 */
add_action( 'template_redirect', 'gstore_start_output_buffer', 1 );
function gstore_start_output_buffer() {
	if ( ! is_admin() ) {
		ob_start( 'gstore_process_final_output' );
	}
}

/**
 * Processa o output final e para o buffer.
 */
add_action( 'shutdown', 'gstore_end_output_buffer', 0 );
function gstore_end_output_buffer() {
	if ( ! is_admin() && ob_get_level() > 0 ) {
		ob_end_flush();
	}
}

/**
 * Processa o output final da página.
 * 
 * @param string $buffer Conteúdo HTML da página.
 * @return string Conteúdo processado.
 */
function gstore_process_final_output( $buffer ) {
	if ( empty( $buffer ) ) {
		return $buffer;
	}
	
	// Processa placeholders de informações da loja (store-info.json)
	if ( function_exists( 'gstore_process_store_info_placeholders' ) ) {
		$buffer = gstore_process_store_info_placeholders( $buffer );
	}
	
	// Processa placeholders de imagens no output final
	$buffer = gstore_process_image_placeholders( $buffer );
	
	// Substitui a logo no header se configurada
	$buffer = gstore_replace_header_logo_html( $buffer );
	
	// Remove classe Gstore-cart-shell do wrapper do WooCommerce para evitar conflitos
	// O bloco page-content-wrapper adiciona classes que geram padding indesejado
	$buffer = gstore_strip_cart_shell_from_wc_wrapper( $buffer );
	
	return $buffer;
}

/**
 * Remove classes problemáticas do wrapper do WooCommerce na página do carrinho.
 *
 * O bloco woocommerce/page-content-wrapper e entry-content adicionam classes como
 * is-layout-constrained e wp-block-group-is-layout-constrained que aplicam
 * max-width: 720px e padding indesejado. Esta função remove essas classes
 * para permitir que o layout do carrinho use 1280px.
 *
 * NOTA: Apenas o carrinho precisa dessa remoção. As páginas de
 * atendimento e produto único mantêm suas classes para estilização.
 *
 * @param string $html HTML da página.
 * @return string HTML processado.
 */
function gstore_strip_cart_shell_from_wc_wrapper( $html ) {
	if ( empty( $html ) || strpos( $html, 'data-page="cart"' ) === false ) {
		return $html;
	}
	
	// Remove a classe Gstore-cart-shell do main que tem data-block-name="woocommerce/page-content-wrapper"
	$html = preg_replace(
		'/(<main[^>]*data-block-name="woocommerce\/page-content-wrapper"[^>]*class="[^"]*)\bGstore-cart-shell\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	// Remove classes is-layout-constrained do main wrapper do carrinho
	$html = preg_replace(
		'/(<main[^>]*data-page="cart"[^>]*class="[^"]*)\bis-layout-constrained\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	// Remove classes wp-block-group-is-layout-constrained do main wrapper do carrinho
	$html = preg_replace(
		'/(<main[^>]*data-page="cart"[^>]*class="[^"]*)\bwp-block-group-is-layout-constrained\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	// Remove classes is-layout-constrained do entry-content wrapper
	$html = preg_replace(
		'/(<div[^>]*class="[^"]*entry-content[^"]*)\bis-layout-constrained\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	// Remove classes wp-block-post-content-is-layout-constrained do entry-content wrapper
	$html = preg_replace(
		'/(<div[^>]*class="[^"]*entry-content[^"]*)\bwp-block-post-content-is-layout-constrained\b([^"]*")/i',
		'$1$2',
		$html
	);
	
	return $html;
}

/**
 * ============================================
 * SETUP DO TEMA - CRIAÇÃO AUTOMÁTICA DE PÁGINAS
 * ============================================
 * 
 * Sistema que permite criar todas as páginas necessárias
 * para o funcionamento do tema Gstore com um clique.
 */

/**
 * Retorna a lista de páginas que o tema precisa.
 * 
 * @return array Lista de páginas com configurações.
 */
function gstore_get_required_pages() {
	return array(
		'home' => array(
			'title'       => 'Home',
			'slug'        => 'home',
			'template'    => 'page-home',
			'content'     => '',
			'description' => 'Página inicial da loja com hero, benefícios, lançamentos e promoções.',
			'set_as'      => 'front_page',
		),
		'catalogo' => array(
			'title'       => 'Catálogo',
			'slug'        => 'catalogo',
			'template'    => 'page-catalogo',
			'content'     => '',
			'description' => 'Página de catálogo com filtros e lista de produtos.',
			'wc_option'   => null,
		),
		'loja' => array(
			'title'       => 'Loja',
			'slug'        => 'loja',
			'template'    => 'page-loja',
			'content'     => '',
			'description' => 'Página principal da loja WooCommerce com layout de catálogo. (REDIRECIONADA PARA CATÁLOGO)',
			'wc_option'   => 'woocommerce_shop_page_id',
			'redirect_to' => 'catalogo',
		),
		'ofertas' => array(
			'title'       => 'Ofertas',
			'slug'        => 'ofertas',
			'template'    => 'page-ofertas',
			'content'     => '',
			'description' => 'Página de produtos em promoção.',
			'wc_option'   => null,
		),
		'carrinho' => array(
			'title'       => 'Carrinho',
			'slug'        => 'carrinho',
			'template'    => 'page-carrinho', // Template de blocos HTML (sem .html)
			'content'     => '', // Conteúdo vazio - o template de blocos renderiza tudo
			'description' => 'Página do carrinho de compras.',
			'wc_option'   => 'woocommerce_cart_page_id',
		),
		'finalizar-compra' => array(
			'title'       => 'Finalizar Compra',
			'slug'        => 'finalizar-compra',
			'template'    => 'page-checkout',
			'content'     => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
			'description' => 'Página de checkout com formulário de endereço e pagamento.',
			'wc_option'   => 'woocommerce_checkout_page_id',
		),
		'minha-conta' => array(
			'title'       => 'Minha Conta',
			'slug'        => 'minha-conta',
			'template'    => '',
			'content'     => '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->',
			'description' => 'Área do cliente para gerenciar pedidos, endereços e dados.',
			'wc_option'   => 'woocommerce_myaccount_page_id',
		),
		'atendimento' => array(
			'title'       => 'Atendimento',
			'slug'        => 'atendimento',
			'template'    => 'page-atendimento',
			'content'     => '',
			'description' => 'Central de atendimento com todos os canais de contato.',
			'wc_option'   => null,
		),
		'como-comprar-arma' => array(
			'title'       => 'Passos para Compra de Arma',
			'slug'        => 'como-comprar-arma',
			'template'    => 'page-como-comprar-arma',
			'content'     => '',
			'description' => 'Página com informações sobre o processo de compra de armas.',
			'wc_option'   => null,
		),
		'blog' => array(
			'title'       => 'Blog',
			'slug'        => 'blog',
			'template'    => 'page-blog',
			'content'     => '',
			'description' => 'Página do blog com artigos e notícias.',
			'wc_option'   => null,
		),
		'politica-de-privacidade' => array(
			'title'       => 'Política de Privacidade',
			'slug'        => 'politica-de-privacidade',
			'template'    => '',
			'content'     => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Política de Privacidade</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Esta página descreve como coletamos, usamos e protegemos suas informações pessoais.</p><!-- /wp:paragraph -->',
			'description' => 'Página com a política de privacidade da loja.',
			'wc_option'   => null,
			'wp_option'   => 'wp_page_for_privacy_policy',
		),
		'termos-de-uso' => array(
			'title'       => 'Termos de Uso',
			'slug'        => 'termos-de-uso',
			'template'    => '',
			'content'     => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Termos de Uso</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Ao utilizar nosso site, você concorda com os termos e condições descritos nesta página.</p><!-- /wp:paragraph -->',
			'description' => 'Página com os termos e condições de uso.',
			'wc_option'   => 'woocommerce_terms_page_id',
		),
		'blog' => array(
			'title'       => 'Blog',
			'slug'        => 'blog',
			'template'    => 'page-blog',
			'content'     => '',
			'description' => 'Página do blog com artigos e notícias.',
			'set_as'      => 'posts_page',
		),
	);
}

/**
 * Adiciona menu de Setup do Tema no admin.
 */
function gstore_add_setup_menu() {
	add_menu_page(
		__( 'Setup Gstore', 'gstore' ),
		__( 'Setup Gstore', 'gstore' ),
		'manage_options',
		'gstore-setup',
		'gstore_render_setup_page',
		'dashicons-store',
		59
	);
}
add_action( 'admin_menu', 'gstore_add_setup_menu' );

/**
 * Adiciona submenu para visualizar Design Tokens.
 */
function gstore_add_design_tokens_submenu() {
	add_submenu_page(
		'gstore-setup',
		__( 'Design Tokens', 'gstore' ),
		__( 'Design Tokens', 'gstore' ),
		'manage_options',
		'gstore-design-tokens',
		'gstore_render_design_tokens_page'
	);
}
add_action( 'admin_menu', 'gstore_add_design_tokens_submenu' );

/**
 * Renderiza a página de Design Tokens.
 */
function gstore_render_design_tokens_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// Obtém a cor de accent salva ou usa a padrão
	$accent_color = get_option( 'gstore_accent_color', '#b5a642' );
	
	// Lê o arquivo de tokens
	$tokens_file = get_theme_file_path( 'assets/css/tokens.css' );
	$tokens_content = file_exists( $tokens_file ) ? file_get_contents( $tokens_file ) : '';
	
	// Extrai as cores do arquivo
	$colors = gstore_extract_colors_from_tokens( $tokens_content );
	
	// Gera preview dos tokens derivados
	$derived_tokens = gstore_generate_accent_tokens( $accent_color );
	
	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'Design Tokens - GStore', 'gstore' ) ); ?></h1>
		<p class="description"><?php echo esc_html( __( 'Visualize todos os tokens de cor, tipografia, espaçamento e outros tokens de design do tema.', 'gstore' ) ); ?></p>
		
		<!-- Seletor de Cor de Accent -->
		<div class="gstore-accent-selector" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h2 style="margin-top: 0;"><?php echo esc_html( __( 'Cor de Accent', 'gstore' ) ); ?></h2>
			<p class="description"><?php echo esc_html( __( 'Escolha a cor de accent principal. Os tokens derivados (hover, dark, light, transparências) serão gerados automaticamente.', 'gstore' ) ); ?></p>
			
			<form id="gstore-accent-color-form" method="post" action="">
				<?php wp_nonce_field( 'gstore_save_accent_color', 'gstore_accent_color_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="gstore_accent_color"><?php echo esc_html( __( 'Cor de Accent', 'gstore' ) ); ?></label>
						</th>
						<td>
							<input 
								type="color" 
								id="gstore_accent_color" 
								name="gstore_accent_color" 
								value="<?php echo esc_attr( $accent_color ); ?>" 
								style="width: 80px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;"
							/>
							<input 
								type="text" 
								id="gstore_accent_color_text" 
								value="<?php echo esc_attr( $accent_color ); ?>" 
								pattern="^#[0-9A-Fa-f]{6}$" 
								style="width: 100px; margin-left: 10px; padding: 5px;"
								placeholder="#b5a642"
							/>
							<p class="description"><?php echo esc_html( __( 'Digite ou selecione uma cor em formato hexadecimal.', 'gstore' ) ); ?></p>
						</td>
					</tr>
				</table>
				
				<!-- Preview dos Tokens Derivados -->
				<div class="gstore-derived-tokens-preview" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
					<h3 style="margin-top: 0;"><?php echo esc_html( __( 'Preview dos Tokens Derivados', 'gstore' ) ); ?></h3>
					<div class="gstore-color-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
						<?php foreach ( $derived_tokens as $token_name => $token_value ) : ?>
							<div class="gstore-color-item" style="border: 1px solid #ddd; border-radius: 4px; overflow: hidden; background: #fff;">
								<div class="gstore-color-preview" style="width: 100%; height: 60px; display: flex; align-items: center; justify-content: center; font-weight: 600; background-color: <?php echo esc_attr( $token_value ); ?>; color: <?php echo esc_attr( gstore_get_contrast_color( $token_value ) ); ?>;">
									<?php echo esc_html( $token_value ); ?>
								</div>
								<div class="gstore-color-info" style="padding: 12px;">
									<strong style="display: block; margin-bottom: 5px; font-size: 13px; color: #1d2327;">
										--gstore-color-accent<?php echo $token_name !== 'accent' ? '-' . esc_html( $token_name ) : ''; ?>
									</strong>
									<code style="background: #f6f7f7; padding: 3px 6px; border-radius: 3px; font-size: 12px; color: #2271b1;">
										<?php echo esc_html( $token_value ); ?>
									</code>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				
				<p class="submit">
					<button type="submit" class="button button-primary" id="gstore-save-accent-color">
						<?php echo esc_html( __( 'Salvar Cor e Atualizar Tokens', 'gstore' ) ); ?>
					</button>
					<span class="spinner" id="gstore-accent-color-spinner" style="float: none; margin-left: 10px;"></span>
				</p>
				<div id="gstore-accent-color-message" style="margin-top: 10px;"></div>
			</form>
		</div>
		
		<div class="gstore-tokens-container" style="margin-top: 20px;">
			<?php gstore_render_color_tokens( $colors ); ?>
		</div>
	</div>
	
	<style>
		.gstore-tokens-container {
			display: grid;
			gap: 20px;
		}
		.gstore-token-section {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			padding: 20px;
			box-shadow: 0 1px 1px rgba(0,0,0,.04);
		}
		.gstore-token-section h2 {
			margin-top: 0;
			padding-bottom: 10px;
			border-bottom: 2px solid #f0f0f1;
		}
		.gstore-color-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
			gap: 15px;
			margin-top: 15px;
		}
		.gstore-color-item {
			border: 1px solid #ddd;
			border-radius: 4px;
			overflow: hidden;
			background: #fff;
		}
		.gstore-color-preview {
			width: 100%;
			height: 80px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: 600;
			position: relative;
		}
		.gstore-color-info {
			padding: 12px;
		}
		.gstore-color-info strong {
			display: block;
			margin-bottom: 5px;
			font-size: 13px;
			color: #1d2327;
		}
		.gstore-color-info code {
			background: #f6f7f7;
			padding: 3px 6px;
			border-radius: 3px;
			font-size: 12px;
			color: #2271b1;
			cursor: pointer;
		}
		.gstore-color-info code:hover {
			background: #e5e5e5;
		}
		.gstore-color-value {
			font-size: 12px;
			color: #646970;
			margin-top: 5px;
		}
		.gstore-token-copy-message {
			position: fixed;
			top: 32px;
			right: 20px;
			background: #00a32a;
			color: #fff;
			padding: 10px 15px;
			border-radius: 4px;
			box-shadow: 0 2px 5px rgba(0,0,0,0.2);
			z-index: 100000;
			display: none;
		}
		.gstore-token-copy-message.show {
			display: block;
			animation: slideIn 0.3s ease;
		}
		@keyframes slideIn {
			from {
				transform: translateX(100%);
				opacity: 0;
			}
			to {
				transform: translateX(0);
				opacity: 1;
			}
		}
	</style>
	
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const colorCodes = document.querySelectorAll('.gstore-color-info code');
			const copyMessage = document.createElement('div');
			copyMessage.className = 'gstore-token-copy-message';
			copyMessage.textContent = 'Token copiado!';
			document.body.appendChild(copyMessage);
			
			colorCodes.forEach(code => {
				code.addEventListener('click', function() {
					const text = this.textContent;
					navigator.clipboard.writeText(text).then(() => {
						copyMessage.classList.add('show');
						setTimeout(() => {
							copyMessage.classList.remove('show');
						}, 2000);
					});
				});
			});
			
			// Sincroniza o seletor de cor com o input de texto
			const colorPicker = document.getElementById('gstore_accent_color');
			const colorText = document.getElementById('gstore_accent_color_text');
			
			if (colorPicker && colorText) {
				// Atualiza o texto quando o seletor muda
				colorPicker.addEventListener('input', function() {
					colorText.value = this.value.toUpperCase();
					updateDerivedTokensPreview();
				});
				
				// Atualiza o seletor quando o texto muda
				colorText.addEventListener('input', function() {
					const value = this.value.trim();
					if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
						colorPicker.value = value;
						updateDerivedTokensPreview();
					}
				});
				
				// Valida o formato quando o campo perde o foco
				colorText.addEventListener('blur', function() {
					const value = this.value.trim();
					if (!/^#[0-9A-Fa-f]{6}$/.test(value)) {
						this.value = colorPicker.value.toUpperCase();
					}
				});
			}
			
			// Atualiza o preview dos tokens derivados
			function updateDerivedTokensPreview() {
				const color = colorPicker.value;
				const previews = document.querySelectorAll('.gstore-derived-tokens-preview .gstore-color-preview');
				const codes = document.querySelectorAll('.gstore-derived-tokens-preview .gstore-color-info code');
				
				// Faz requisição AJAX para obter os tokens derivados
				const formData = new FormData();
				formData.append('action', 'gstore_get_derived_tokens');
				formData.append('accent_color', color);
				formData.append('nonce', '<?php echo wp_create_nonce( 'gstore_get_derived_tokens' ); ?>');
				
				fetch(ajaxurl, {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success && data.data) {
						const tokens = data.data;
						const tokenNames = ['accent', 'accent-hover', 'accent-dark', 'accent-light', 'accent-08', 'accent-10', 'accent-12', 'accent-15', 'accent-20'];
						
						previews.forEach((preview, index) => {
							if (tokens[tokenNames[index]]) {
								const tokenValue = tokens[tokenNames[index]];
								preview.style.backgroundColor = tokenValue;
								preview.textContent = tokenValue;
								
								// Atualiza cor do texto baseado no contraste
								// Para rgba, extrai os valores RGB
								let rgb = null;
								if (tokenValue.startsWith('rgba')) {
									const rgbaMatch = tokenValue.match(/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/);
									if (rgbaMatch) {
										rgb = {
											r: parseInt(rgbaMatch[1]),
											g: parseInt(rgbaMatch[2]),
											b: parseInt(rgbaMatch[3])
										};
									}
								} else {
									rgb = hexToRgb(tokenValue);
								}
								
								if (rgb) {
									const luminance = (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255;
									preview.style.color = luminance > 0.5 ? '#000' : '#fff';
								} else {
									preview.style.color = '#000';
								}
							}
						});
						
						codes.forEach((code, index) => {
							if (tokens[tokenNames[index]]) {
								code.textContent = tokens[tokenNames[index]];
							}
						});
					}
				})
				.catch(error => {
					console.error('Erro ao atualizar preview:', error);
				});
			}
			
			// Função auxiliar para converter hex para RGB
			function hexToRgb(hex) {
				const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
				return result ? {
					r: parseInt(result[1], 16),
					g: parseInt(result[2], 16),
					b: parseInt(result[3], 16)
				} : null;
			}
			
			// Submissão do formulário via AJAX
			const form = document.getElementById('gstore-accent-color-form');
			if (form) {
				form.addEventListener('submit', function(e) {
					e.preventDefault();
					
					const submitButton = document.getElementById('gstore-save-accent-color');
					const spinner = document.getElementById('gstore-accent-color-spinner');
					const message = document.getElementById('gstore-accent-color-message');
					
					submitButton.disabled = true;
					spinner.classList.add('is-active');
					message.innerHTML = '';
					
					const formData = new FormData(form);
					formData.append('action', 'gstore_save_accent_color');
					
					fetch(ajaxurl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						spinner.classList.remove('is-active');
						submitButton.disabled = false;
						
						if (data.success) {
							message.innerHTML = '<div class="notice notice-success is-dismissible"><p>' + data.data.message + '</p></div>';
							// Recarrega a página após 1 segundo para mostrar os tokens atualizados
							setTimeout(() => {
								window.location.reload();
							}, 1000);
						} else {
							message.innerHTML = '<div class="notice notice-error is-dismissible"><p>' + (data.data && data.data.message ? data.data.message : 'Erro ao salvar a cor.') + '</p></div>';
						}
					})
					.catch(error => {
						spinner.classList.remove('is-active');
						submitButton.disabled = false;
						message.innerHTML = '<div class="notice notice-error is-dismissible"><p>Erro ao salvar a cor. Tente novamente.</p></div>';
						console.error('Erro:', error);
					});
				});
			}
		});
	</script>
	<?php
}

/**
 * Endpoint AJAX para obter tokens derivados.
 */
function gstore_ajax_get_derived_tokens() {
	check_ajax_referer( 'gstore_get_derived_tokens', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}
	
	$accent_color = isset( $_POST['accent_color'] ) ? sanitize_hex_color( $_POST['accent_color'] ) : '#b5a642';
	
	if ( ! $accent_color ) {
		wp_send_json_error( array( 'message' => __( 'Cor inválida.', 'gstore' ) ) );
	}
	
	$tokens = gstore_generate_accent_tokens( $accent_color );
	
	wp_send_json_success( $tokens );
}
add_action( 'wp_ajax_gstore_get_derived_tokens', 'gstore_ajax_get_derived_tokens' );

/**
 * Endpoint AJAX para salvar a cor de accent e atualizar tokens.
 */
function gstore_ajax_save_accent_color() {
	check_ajax_referer( 'gstore_save_accent_color', 'gstore_accent_color_nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}
	
	$accent_color = isset( $_POST['gstore_accent_color'] ) ? sanitize_hex_color( $_POST['gstore_accent_color'] ) : '';
	
	if ( ! $accent_color ) {
		wp_send_json_error( array( 'message' => __( 'Cor inválida. Por favor, selecione uma cor válida.', 'gstore' ) ) );
	}
	
	// Salva a opção
	update_option( 'gstore_accent_color', $accent_color );
	
	// Atualiza o arquivo tokens.css
	$result = gstore_update_accent_tokens_in_file( $accent_color );
	
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	
	// Atualiza o timestamp para forçar recarregamento do CSS
	update_option( 'gstore_tokens_last_updated', time() );
	
	// Limpa cache do WordPress se disponível
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
	
	wp_send_json_success( array( 
		'message' => __( 'Cor de accent salva e tokens atualizados com sucesso!', 'gstore' ),
		'tokens' => gstore_generate_accent_tokens( $accent_color ),
		'file_updated' => true
	) );
}
add_action( 'wp_ajax_gstore_save_accent_color', 'gstore_ajax_save_accent_color' );

/**
 * Extrai cores do conteúdo do arquivo de tokens.
 */
function gstore_extract_colors_from_tokens( $content ) {
	$colors = array(
		'fundos' => array(),
		'textos' => array(),
		'acentos' => array(),
		'bordas' => array(),
		'ratings' => array(),
		'transparencia' => array(),
		'estados' => array(),
	);
	
	// Primeiro, cria um mapa de todas as variáveis para resolver referências
	$var_map = array();
	preg_match_all('/--gstore-color-([^:]+):\s*([^;]+);/', $content, $all_matches, PREG_SET_ORDER);
	foreach ( $all_matches as $match ) {
		$var_name = '--gstore-color-' . trim( $match[1] );
		$var_value = trim( $match[2] );
		$var_map[ $var_name ] = $var_value;
	}
	
	// Padrão para encontrar variáveis CSS
	preg_match_all('/--gstore-color-([^:]+):\s*([^;]+);/', $content, $matches, PREG_SET_ORDER);
	
	foreach ( $matches as $match ) {
		$name = trim( $match[1] );
		$value = trim( $match[2] );
		
		// Resolve variáveis CSS se for uma referência
		$resolved_value = gstore_resolve_css_variable( $value, $var_map );
		
		// Categoriza as cores
		if ( strpos( $name, 'bg-' ) === 0 ) {
			$colors['fundos'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, 'text-' ) === 0 ) {
			$colors['textos'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, 'accent' ) !== false || strpos( $name, 'success' ) !== false || strpos( $name, 'error' ) !== false || strpos( $name, 'warning' ) !== false ) {
			if ( strpos( $name, '-' ) !== false && ( strpos( $name, '-10' ) !== false || strpos( $name, '-12' ) !== false || strpos( $name, '-15' ) !== false || strpos( $name, '-20' ) !== false || strpos( $name, '-08' ) !== false ) ) {
				$colors['transparencia'][] = array(
					'name' => '--gstore-color-' . $name,
					'value' => $value,
					'resolved' => $resolved_value,
				);
			} else {
				$colors['acentos'][] = array(
					'name' => '--gstore-color-' . $name,
					'value' => $value,
					'resolved' => $resolved_value,
				);
			}
		} elseif ( strpos( $name, 'border' ) !== false ) {
			$colors['bordas'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, 'rating' ) !== false ) {
			$colors['ratings'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, 'white' ) !== false || strpos( $name, 'black' ) !== false ) {
			$colors['transparencia'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		} elseif ( strpos( $name, '-bg' ) !== false || strpos( $name, '-border' ) !== false || strpos( $name, '-text' ) !== false ) {
			$colors['estados'][] = array(
				'name' => '--gstore-color-' . $name,
				'value' => $value,
				'resolved' => $resolved_value,
			);
		}
	}
	
	return $colors;
}

/**
 * Resolve uma variável CSS para seu valor final.
 */
function gstore_resolve_css_variable( $value, $var_map, $depth = 0 ) {
	// Limita a profundidade para evitar loops infinitos
	if ( $depth > 10 ) {
		return $value;
	}
	
	// Se não é uma variável, retorna o valor
	if ( strpos( $value, 'var(' ) !== 0 ) {
		return $value;
	}
	
	// Extrai o nome da variável
	preg_match( '/var\(([^)]+)\)/', $value, $matches );
	if ( empty( $matches[1] ) ) {
		return $value;
	}
	
	$var_name = trim( $matches[1] );
	
	// Se a variável existe no mapa, resolve recursivamente
	if ( isset( $var_map[ $var_name ] ) ) {
		return gstore_resolve_css_variable( $var_map[ $var_name ], $var_map, $depth + 1 );
	}
	
	return $value;
}

/**
 * Renderiza os tokens de cor na página.
 */
function gstore_render_color_tokens( $colors ) {
	$sections = array(
		'fundos' => __( 'Fundos', 'gstore' ),
		'textos' => __( 'Textos', 'gstore' ),
		'acentos' => __( 'Acentos e Estados', 'gstore' ),
		'bordas' => __( 'Bordas', 'gstore' ),
		'ratings' => __( 'Ratings', 'gstore' ),
		'transparencia' => __( 'Cores com Transparência', 'gstore' ),
		'estados' => __( 'Estados Específicos', 'gstore' ),
	);
	
	foreach ( $sections as $key => $title ) {
		if ( empty( $colors[ $key ] ) ) {
			continue;
		}
		
		?>
		<div class="gstore-token-section">
			<h2><?php echo esc_html( $title ); ?></h2>
			<div class="gstore-color-grid">
				<?php foreach ( $colors[ $key ] as $color ) : 
					$color_value = isset( $color['resolved'] ) && $color['resolved'] !== $color['value'] ? $color['resolved'] : $color['value'];
					$display_value = $color_value;
					
					// Se ainda for uma variável não resolvida, tenta usar o valor original
					if ( strpos( $color_value, 'var(' ) === 0 ) {
						$display_value = $color['value'];
						$color_value = '#f0f0f0'; // Cor padrão para variáveis não resolvidas
					}
					
					// Determina se o texto deve ser claro ou escuro
					$text_color = gstore_get_contrast_color( $color_value );
				?>
					<div class="gstore-color-item">
						<div class="gstore-color-preview" style="background-color: <?php echo esc_attr( $color_value ); ?>; color: <?php echo esc_attr( $text_color ); ?>;">
							<?php echo esc_html( $display_value ); ?>
						</div>
						<div class="gstore-color-info">
							<strong><?php echo esc_html( str_replace( '--gstore-color-', '', $color['name'] ) ); ?></strong>
							<code><?php echo esc_html( $color['name'] ); ?></code>
							<div class="gstore-color-value"><?php echo esc_html( $color['value'] ); ?></div>
							<?php if ( isset( $color['resolved'] ) && $color['resolved'] !== $color['value'] && strpos( $color['value'], 'var(' ) === 0 ) : ?>
								<div class="gstore-color-value" style="color: #2271b1; margin-top: 3px;">
									→ <?php echo esc_html( $color['resolved'] ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

/**
 * Determina a cor do texto baseado no contraste do fundo.
 */
function gstore_get_contrast_color( $color ) {
	// Se for rgba ou variável, retorna preto por padrão
	if ( strpos( $color, 'rgba' ) === 0 || strpos( $color, 'var(' ) === 0 ) {
		// Para rgba, tenta extrair a opacidade
		if ( preg_match( '/rgba\([^)]+,\s*([\d.]+)\)/', $color, $matches ) ) {
			$opacity = floatval( $matches[1] );
			// Se a opacidade for muito baixa, usa texto escuro
			return $opacity < 0.5 ? '#000' : '#fff';
		}
		return '#000';
	}
	
	// Remove # se existir
	$color = ltrim( $color, '#' );
	
	// Se não for hex válido, retorna preto
	if ( ! preg_match( '/^[0-9a-fA-F]{3,6}$/', $color ) ) {
		return '#000';
	}
	
	// Converte hex para RGB
	if ( strlen( $color ) === 3 ) {
		$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
	}
	
	$r = hexdec( substr( $color, 0, 2 ) );
	$g = hexdec( substr( $color, 2, 2 ) );
	$b = hexdec( substr( $color, 4, 2 ) );
	
	// Calcula luminância relativa
	$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
	
	// Retorna branco para fundos escuros, preto para fundos claros
	return $luminance > 0.5 ? '#000' : '#fff';
}

/**
 * Converte cor hex para RGB.
 * 
 * @param string $hex Cor em formato hex (#RRGGBB ou RRGGBB).
 * @return array|false Array com r, g, b ou false se inválido.
 */
function gstore_hex_to_rgb( $hex ) {
	$hex = ltrim( $hex, '#' );
	
	// Se não for hex válido, retorna false
	if ( ! preg_match( '/^[0-9a-fA-F]{3,6}$/', $hex ) ) {
		return false;
	}
	
	// Converte hex curto para completo
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	
	return array(
		'r' => hexdec( substr( $hex, 0, 2 ) ),
		'g' => hexdec( substr( $hex, 2, 2 ) ),
		'b' => hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Converte RGB para hex.
 * 
 * @param int $r Valor R (0-255).
 * @param int $g Valor G (0-255).
 * @param int $b Valor B (0-255).
 * @return string Cor em formato hex (#RRGGBB).
 */
function gstore_rgb_to_hex( $r, $g, $b ) {
	$r = max( 0, min( 255, intval( $r ) ) );
	$g = max( 0, min( 255, intval( $g ) ) );
	$b = max( 0, min( 255, intval( $b ) ) );
	return sprintf( '#%02x%02x%02x', $r, $g, $b );
}

/**
 * Escurece uma cor hex.
 * 
 * @param string $hex Cor em formato hex.
 * @param float $percent Porcentagem para escurecer (0-100).
 * @return string Cor escurecida em hex.
 */
function gstore_darken_color( $hex, $percent = 15 ) {
	$rgb = gstore_hex_to_rgb( $hex );
	if ( ! $rgb ) {
		return $hex;
	}
	
	$percent = max( 0, min( 100, floatval( $percent ) ) );
	$factor = 1 - ( $percent / 100 );
	
	return gstore_rgb_to_hex(
		$rgb['r'] * $factor,
		$rgb['g'] * $factor,
		$rgb['b'] * $factor
	);
}

/**
 * Clareia uma cor hex.
 * 
 * @param string $hex Cor em formato hex.
 * @param float $percent Porcentagem para clarear (0-100).
 * @return string Cor clareada em hex.
 */
function gstore_lighten_color( $hex, $percent = 15 ) {
	$rgb = gstore_hex_to_rgb( $hex );
	if ( ! $rgb ) {
		return $hex;
	}
	
	$percent = max( 0, min( 100, floatval( $percent ) ) );
	$factor = 1 + ( $percent / 100 );
	
	return gstore_rgb_to_hex(
		min( 255, $rgb['r'] * $factor ),
		min( 255, $rgb['g'] * $factor ),
		min( 255, $rgb['b'] * $factor )
	);
}

/**
 * Converte cor hex para rgba.
 * 
 * @param string $hex Cor em formato hex.
 * @param float $opacity Opacidade (0-1).
 * @return string Cor em formato rgba.
 */
function gstore_hex_to_rgba( $hex, $opacity = 1 ) {
	$rgb = gstore_hex_to_rgb( $hex );
	if ( ! $rgb ) {
		return $hex;
	}
	
	$opacity = max( 0, min( 1, floatval( $opacity ) ) );
	return sprintf( 'rgba(%d, %d, %d, %.2f)', $rgb['r'], $rgb['g'], $rgb['b'], $opacity );
}

/**
 * Gera tokens derivados baseados na cor de accent.
 * 
 * @param string $accent_color Cor de accent em hex.
 * @return array Array com todos os tokens derivados.
 */
function gstore_generate_accent_tokens( $accent_color ) {
	$rgb = gstore_hex_to_rgb( $accent_color );
	if ( ! $rgb ) {
		return array();
	}
	
	return array(
		'accent' => $accent_color,
		'accent-hover' => gstore_darken_color( $accent_color, 12 ),
		'accent-dark' => gstore_darken_color( $accent_color, 25 ),
		'accent-light' => gstore_lighten_color( $accent_color, 10 ),
		'accent-08' => gstore_hex_to_rgba( $accent_color, 0.08 ),
		'accent-10' => gstore_hex_to_rgba( $accent_color, 0.1 ),
		'accent-12' => gstore_hex_to_rgba( $accent_color, 0.12 ),
		'accent-15' => gstore_hex_to_rgba( $accent_color, 0.15 ),
		'accent-20' => gstore_hex_to_rgba( $accent_color, 0.2 ),
	);
}

/**
 * Atualiza o arquivo tokens.css com a nova cor de accent e tokens derivados.
 * Também atualiza valores de fallback em outros arquivos CSS.
 * 
 * @param string $accent_color Cor de accent em hex.
 * @return bool|WP_Error True em sucesso, WP_Error em erro.
 */
function gstore_update_accent_tokens_in_file( $accent_color ) {
	$tokens = gstore_generate_accent_tokens( $accent_color );
	
	// Mapeia os nomes dos tokens para os padrões no arquivo
	$token_map = array(
		'accent' => '--gstore-color-accent',
		'accent-hover' => '--gstore-color-accent-hover',
		'accent-dark' => '--gstore-color-accent-dark',
		'accent-light' => '--gstore-color-accent-light',
		'accent-08' => '--gstore-color-accent-08',
		'accent-10' => '--gstore-color-accent-10',
		'accent-12' => '--gstore-color-accent-12',
		'accent-15' => '--gstore-color-accent-15',
		'accent-20' => '--gstore-color-accent-20',
	);
	
	// 1. Atualiza tokens.css
	$tokens_file = get_theme_file_path( 'assets/css/tokens.css' );
	if ( file_exists( $tokens_file ) && is_writable( $tokens_file ) ) {
		$content = file_get_contents( $tokens_file );
		
		foreach ( $token_map as $token_key => $token_var ) {
			$token_value = $tokens[ $token_key ];
			$pattern = '/([\t\s]*)(' . preg_quote( $token_var, '/' ) . '):\s*[^;]+;/';
			
			if ( preg_match( $pattern, $content ) ) {
				$content = preg_replace(
					$pattern,
					'$1$2: ' . $token_value . ';',
					$content
				);
			} else {
				$pattern_flex = '/' . preg_quote( $token_var, '/' ) . ':\s*[^;]+;/';
				if ( preg_match( $pattern_flex, $content ) ) {
					$content = preg_replace(
						$pattern_flex,
						$token_var . ': ' . $token_value . ';',
						$content
					);
				}
			}
		}
		
		file_put_contents( $tokens_file, $content );
	}
	
	// 2. Atualiza valores de fallback em checkout-steps.css
	$checkout_steps_file = get_theme_file_path( 'assets/css/checkout-steps.css' );
	if ( file_exists( $checkout_steps_file ) && is_writable( $checkout_steps_file ) ) {
		$content = file_get_contents( $checkout_steps_file );
		
		// Atualiza --gstore-brass com novo fallback
		$content = preg_replace(
			'/(--gstore-brass):\s*var\(--gstore-color-accent,\s*[^)]+\);/',
			'$1: var(--gstore-color-accent, ' . $tokens['accent'] . ');',
			$content
		);
		
		// Atualiza --gstore-brass-dark com novo fallback
		$content = preg_replace(
			'/(--gstore-brass-dark):\s*var\(--gstore-color-accent-dark,\s*[^)]+\);/',
			'$1: var(--gstore-color-accent-dark, ' . $tokens['accent-dark'] . ');',
			$content
		);
		
		file_put_contents( $checkout_steps_file, $content );
	}
	
	// 3. Atualiza valores hardcoded em style.css
	$style_file = get_theme_file_path( 'style.css' );
	if ( file_exists( $style_file ) && is_writable( $style_file ) ) {
		$content = file_get_contents( $style_file );
		
		// Atualiza valores hardcoded de accent (em qualquer lugar do arquivo)
		$content = preg_replace(
			'/(--gstore-color-accent):\s*#[0-9a-fA-F]{6};/',
			'$1: ' . $tokens['accent'] . ';',
			$content
		);
		
		$content = preg_replace(
			'/(--gstore-color-accent-hover):\s*#[0-9a-fA-F]{6};/',
			'$1: ' . $tokens['accent-hover'] . ';',
			$content
		);
		
		// Atualiza todos os fallbacks de accent no arquivo
		$content = preg_replace(
			'/var\(--gstore-color-accent,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent, ' . $tokens['accent'] . ')',
			$content
		);
		
		$content = preg_replace(
			'/var\(--gstore-color-accent-hover,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent-hover, ' . $tokens['accent-hover'] . ')',
			$content
		);
		
		$content = preg_replace(
			'/var\(--gstore-color-accent-light,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent-light, ' . $tokens['accent-light'] . ')',
			$content
		);
		
		file_put_contents( $style_file, $content );
	}
	
	// 4. Atualiza valores de fallback em my-account.css
	$my_account_file = get_theme_file_path( 'assets/css/my-account.css' );
	if ( file_exists( $my_account_file ) && is_writable( $my_account_file ) ) {
		$content = file_get_contents( $my_account_file );
		
		// Atualiza todos os fallbacks de accent
		$content = preg_replace(
			'/var\(--gstore-color-accent,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent, ' . $tokens['accent'] . ')',
			$content
		);
		
		$content = preg_replace(
			'/var\(--gstore-color-accent-hover,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent-hover, ' . $tokens['accent-hover'] . ')',
			$content
		);
		
		$content = preg_replace(
			'/var\(--gstore-color-accent-dark,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent-dark, ' . $tokens['accent-dark'] . ')',
			$content
		);
		
		file_put_contents( $my_account_file, $content );
	}
	
	// 5. Atualiza valores de fallback em header.css
	$header_file = get_theme_file_path( 'assets/css/layouts/header.css' );
	if ( file_exists( $header_file ) && is_writable( $header_file ) ) {
		$content = file_get_contents( $header_file );
		
		// Atualiza todos os fallbacks de accent
		$content = preg_replace(
			'/var\(--gstore-color-accent,\s*#[0-9a-fA-F]{6}\)/',
			'var(--gstore-color-accent, ' . $tokens['accent'] . ')',
			$content
		);
		
		file_put_contents( $header_file, $content );
	}
	
	return true;
}

/**
 * Verifica se uma página existe pelo slug.
 * 
 * @param string $slug Slug da página.
 * @return WP_Post|null Post encontrado ou null.
 */
function gstore_get_page_by_slug( $slug ) {
	$page = get_page_by_path( $slug );
	
	if ( ! $page ) {
		// Tenta encontrar com query mais específica
		$pages = get_posts( array(
			'name'        => $slug,
			'post_type'   => 'page',
			'post_status' => array( 'publish', 'draft', 'private' ),
			'numberposts' => 1,
		) );
		
		$page = ! empty( $pages ) ? $pages[0] : null;
	}
	
	return $page;
}

/**
 * Cria uma página do tema.
 * 
 * @param string $page_key Chave da página na lista de páginas.
 * @param bool   $force    Se true, recria a página mesmo se já existir.
 * @return array Resultado da operação.
 */
function gstore_create_page( $page_key, $force = false ) {
	$pages = gstore_get_required_pages();
	
	if ( ! isset( $pages[ $page_key ] ) ) {
		return array(
			'success' => false,
			'message' => __( 'Página não encontrada nas configurações.', 'gstore' ),
		);
	}
	
	$page_config = $pages[ $page_key ];
	$existing_page = gstore_get_page_by_slug( $page_config['slug'] );
	
	// Se a página já existe e não é forçado, apenas retorna sucesso
	if ( $existing_page && ! $force ) {
		return array(
			'success' => true,
			'message' => __( 'Página já existe.', 'gstore' ),
			'page_id' => $existing_page->ID,
			'action'  => 'exists',
		);
	}
	
	// Se força recriação, deleta a existente
	if ( $existing_page && $force ) {
		wp_delete_post( $existing_page->ID, true );
	}
	
	// Prepara os dados da nova página
	$page_data = array(
		'post_title'   => $page_config['title'],
		'post_name'    => $page_config['slug'],
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $page_config['content'],
	);
	
	// Insere a página
	$page_id = wp_insert_post( $page_data );
	
	if ( is_wp_error( $page_id ) ) {
		return array(
			'success' => false,
			'message' => $page_id->get_error_message(),
		);
	}
	
	// Define o template se especificado
	if ( ! empty( $page_config['template'] ) ) {
		update_post_meta( $page_id, '_wp_page_template', $page_config['template'] );
	}
	
	// Configura opções do WooCommerce
	if ( ! empty( $page_config['wc_option'] ) && class_exists( 'WooCommerce' ) ) {
		update_option( $page_config['wc_option'], $page_id );
	}
	
	// Configura opções do WordPress
	if ( ! empty( $page_config['wp_option'] ) ) {
		update_option( $page_config['wp_option'], $page_id );
	}
	
	// Define como página inicial ou de posts
	if ( ! empty( $page_config['set_as'] ) ) {
		if ( 'front_page' === $page_config['set_as'] ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $page_id );
		} elseif ( 'posts_page' === $page_config['set_as'] ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_for_posts', $page_id );
			// Força o uso do template page-blog.html para a página de posts
			if ( ! empty( $page_config['template'] ) ) {
				update_post_meta( $page_id, '_wp_page_template', $page_config['template'] );
			}
		}
	}
	
	return array(
		'success' => true,
		'message' => __( 'Página criada com sucesso!', 'gstore' ),
		'page_id' => $page_id,
		'action'  => 'created',
	);
}

/**
 * Força o uso do template page-blog.html quando for a página de posts do blog.
 * 
 * Quando uma página é definida como "posts_page", o WordPress usa templates de arquivo
 * (home.html, archive.html) ao invés de templates de página. Este filtro corrige isso.
 * 
 * IMPORTANTE: Em Block Themes, não devemos retornar o caminho do arquivo HTML diretamente
 * via template_include, pois isso impede o processamento dos blocos. Em vez disso, usamos
 * o filtro block_template_loader para forçar o template correto.
 * 
 * @param string $template Template atual.
 * @return string Template a ser usado.
 */
function gstore_force_blog_page_template( $template ) {
	// Verifica se é a página de posts ou se está acessando /blog
	if ( is_home() && ! is_front_page() ) {
		$blog_page_id = get_option( 'page_for_posts' );
		
		if ( $blog_page_id ) {
			$blog_page = get_post( $blog_page_id );
			
			// Verifica se a página do blog tem o template page-blog
			if ( $blog_page && 'blog' === $blog_page->post_name ) {
				$page_template = get_page_template_slug( $blog_page_id );
				
				// Se o template for page-blog, força o uso dele
				if ( 'page-blog' === $page_template || 'page-blog.html' === $page_template ) {
					// Em Block Themes, não retornamos o caminho do arquivo diretamente
					// porque isso impede o processamento dos blocos. Em vez disso, deixamos
					// o WordPress usar o sistema de Block Templates nativo.
					// O template será carregado automaticamente pelo WordPress se existir.
					// Não retornamos nada aqui - deixamos o WordPress usar o sistema nativo
				}
			}
		}
	}
	
	// Também verifica se está acessando diretamente a página /blog
	if ( is_page( 'blog' ) ) {
		$blog_page = get_page_by_path( 'blog' );
		
		if ( $blog_page ) {
			$page_template = get_page_template_slug( $blog_page->ID );
			
			// Se o template for page-blog, força o uso dele
			if ( 'page-blog' === $page_template || 'page-blog.html' === $page_template ) {
				// Em Block Themes, não retornamos o caminho do arquivo diretamente
				// porque isso impede o processamento dos blocos. Em vez disso, deixamos
				// o WordPress usar o sistema de Block Templates nativo.
				// Não retornamos nada aqui - deixamos o WordPress usar o sistema nativo
			}
		}
	}
	
	return $template;
}
add_filter( 'template_include', 'gstore_force_blog_page_template', 99 );

/**
 * Força o uso do template page-blog.html via Block Template API quando for a página de posts.
 * 
 * Este filtro funciona em conjunto com gstore_force_blog_page_template para garantir
 * que o WordPress use o template correto e processe os blocos adequadamente.
 * 
 * @param WP_Block_Template|null $template Template atual.
 * @return WP_Block_Template|null Template a ser usado.
 */
function gstore_force_blog_block_template( $template ) {
	// Verifica se é a página de posts
	if ( is_home() && ! is_front_page() ) {
		$blog_page_id = get_option( 'page_for_posts' );
		
		if ( $blog_page_id ) {
			$blog_page = get_post( $blog_page_id );
			
			if ( $blog_page && 'blog' === $blog_page->post_name ) {
				$page_template = get_page_template_slug( $blog_page_id );
				
				// Se o template for page-blog, força o uso dele via Block Template API
				if ( 'page-blog' === $page_template || 'page-blog.html' === $page_template ) {
					if ( function_exists( 'get_block_template' ) ) {
						$block_template = get_block_template( get_stylesheet() . '//page-blog', 'wp_template' );
						
						if ( $block_template ) {
							return $block_template;
						}
					}
				}
			}
		}
	}
	
	return $template;
}
add_filter( 'block_template_loader', 'gstore_force_blog_block_template', 10, 1 );

/**
 * Cria todas as páginas do tema.
 * 
 * @param bool $force Se true, recria todas as páginas.
 * @return array Resultados das operações.
 */
function gstore_create_all_pages( $force = false ) {
	$pages = gstore_get_required_pages();
	$results = array();
	
	foreach ( $pages as $page_key => $page_config ) {
		$results[ $page_key ] = gstore_create_page( $page_key, $force );
	}
	
	return $results;
}

/**
 * Executa diagnóstico dos assets críticos do tema.
 *
 * @return array Resultado da verificação.
 */
function gstore_run_asset_diagnostics() {
	$assets = array(
		'assets/css/gstore-main.css',
		'assets/css/layouts/header.css',
		'assets/js/header.js',
		'assets/js/home-hero.js',
		'assets/js/home-benefits.js',
		'assets/js/home-products-carousel.js',
	);
	$missing = array();

	foreach ( $assets as $asset_path ) {
		if ( ! file_exists( get_theme_file_path( $asset_path ) ) ) {
			$missing[] = $asset_path;
		}
	}

	if ( ! empty( $missing ) ) {
		return array(
			'success' => false,
			'message' => __( 'Encontramos arquivos ausentes. Reenvie o tema ou copie novamente a pasta de assets.', 'gstore' ),
			'missing' => $missing,
		);
	}

	return array(
		'success' => true,
		'message' => __( 'Todos os arquivos críticos estão presentes. O layout mobile será aplicado automaticamente.', 'gstore' ),
	);
}

/**
 * ============================================
 * DIAGNÓSTICO DE CSS - VERIFICAÇÃO EM PRODUÇÃO
 * ============================================
 * 
 * Sistema que verifica se regras CSS críticas estão
 * sendo aplicadas corretamente no frontend.
 */

/**
 * Retorna as regras CSS críticas que devem ser verificadas.
 * 
 * @return array Lista de regras com seletores e propriedades esperadas.
 */
function gstore_get_css_diagnostic_rules() {
	return array(
		'benefits_slider_controls_hidden' => array(
			'name'        => 'Setas do carrossel de benefícios (mobile)',
			'selector'    => '.Gstore-home-benefits__slider-control--prev',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'mobile',
			'description' => 'As setas de navegação devem estar ocultas no mobile (@media max-width: 900px)',
			'css_file'    => 'style.css',
			'css_line'    => '~2320',
		),
		'benefits_slider_controls_next_hidden' => array(
			'name'        => 'Seta próximo do carrossel (mobile)',
			'selector'    => '.Gstore-home-benefits__slider-control--next',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'mobile',
			'description' => 'A seta próximo deve estar oculta no mobile',
			'css_file'    => 'style.css',
			'css_line'    => '~2320',
		),
		'benefits_slider_dots_hidden' => array(
			'name'        => 'Dots do carrossel de benefícios (mobile)',
			'selector'    => '.Gstore-home-benefits__slider-dots',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'mobile',
			'description' => 'Os dots de navegação devem estar ocultos no mobile',
			'css_file'    => 'style.css',
			'css_line'    => '~2324',
		),
		'benefits_slider_visible' => array(
			'name'        => 'Slider de benefícios visível (mobile)',
			'selector'    => '.Gstore-home-benefits__slider',
			'property'    => 'display',
			'expected'    => 'block',
			'viewport'    => 'mobile',
			'description' => 'O slider deve estar visível no mobile',
			'css_file'    => 'style.css',
			'css_line'    => '~2307',
		),
		'benefits_inner_hidden' => array(
			'name'        => 'Grid de benefícios oculto (mobile)',
			'selector'    => '.Gstore-home-benefits__inner',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'mobile',
			'description' => 'O grid desktop de benefícios deve estar oculto no mobile',
			'css_file'    => 'style.css',
			'css_line'    => '~2303',
		),
		'header_mobile_menu_hidden' => array(
			'name'        => 'Menu mobile oculto (desktop)',
			'selector'    => '.Gstore-header__mobile-menu-toggle',
			'property'    => 'display',
			'expected'    => 'none',
			'viewport'    => 'desktop',
			'description' => 'O botão de menu mobile deve estar oculto no desktop',
			'css_file'    => 'assets/css/layouts/header.css',
			'css_line'    => 'varies',
		),
	);
}

/**
 * Gera o script JavaScript de diagnóstico para rodar no frontend.
 * 
 * @return string Código JavaScript para diagnóstico.
 */
function gstore_generate_css_diagnostics_script() {
	$rules = gstore_get_css_diagnostic_rules();
	$rules_json = wp_json_encode( $rules );
	
	$script = <<<JAVASCRIPT
(function() {
	'use strict';
	
	console.log('%c🔍 Gstore CSS Diagnostics', 'font-size: 16px; font-weight: bold; color: #2271b1;');
	console.log('Verificando regras CSS críticas...');
	console.log('');
	
	var rules = {$rules_json};
	var results = { passed: [], failed: [], notFound: [] };
	var isMobile = window.innerWidth <= 900;
	var isDesktop = window.innerWidth > 900;
	
	console.log('Viewport atual: ' + (isMobile ? 'Mobile (' + window.innerWidth + 'px)' : 'Desktop (' + window.innerWidth + 'px)'));
	console.log('');
	
	Object.keys(rules).forEach(function(key) {
		var rule = rules[key];
		var shouldCheck = (rule.viewport === 'mobile' && isMobile) || 
		                  (rule.viewport === 'desktop' && isDesktop) ||
		                  !rule.viewport;
		
		if (!shouldCheck) {
			console.log('%c⏭️ ' + rule.name + ' - Ignorado (viewport diferente)', 'color: #666;');
			return;
		}
		
		var element = document.querySelector(rule.selector);
		
		if (!element) {
			results.notFound.push(rule);
			console.log('%c❓ ' + rule.name + ' - Elemento não encontrado: ' + rule.selector, 'color: #dba617;');
			return;
		}
		
		var computedStyle = window.getComputedStyle(element);
		var actualValue = computedStyle.getPropertyValue(rule.property);
		
		if (actualValue.trim() === rule.expected) {
			results.passed.push(rule);
			console.log('%c✅ ' + rule.name + ' - OK (' + rule.property + ': ' + actualValue + ')', 'color: #00a32a;');
		} else {
			results.failed.push({ rule: rule, actual: actualValue });
			console.log('%c❌ ' + rule.name + ' - FALHOU', 'color: #d63638; font-weight: bold;');
			console.log('   Esperado: ' + rule.property + ': ' + rule.expected);
			console.log('   Atual: ' + rule.property + ': ' + actualValue);
			console.log('   Arquivo: ' + rule.css_file + ' (linha ' + rule.css_line + ')');
			console.log('   ' + rule.description);
		}
	});
	
	console.log('');
	console.log('%c📊 Resumo do Diagnóstico', 'font-size: 14px; font-weight: bold;');
	console.log('✅ Passou: ' + results.passed.length);
	console.log('❌ Falhou: ' + results.failed.length);
	console.log('❓ Não encontrado: ' + results.notFound.length);
	
	if (results.failed.length > 0) {
		console.log('');
		console.log('%c⚠️ Possíveis causas:', 'font-weight: bold; color: #dba617;');
		console.log('1. Cache do navegador - Limpe o cache e recarregue');
		console.log('2. Cache do servidor - Limpe cache do plugin de cache (LiteSpeed, WP Super Cache, etc.)');
		console.log('3. CDN com cache - Faça purge do cache da CDN');
		console.log('4. CSS não atualizado - Verifique se o deploy foi feito corretamente');
		console.log('5. Plugin conflitante - Desative plugins de otimização CSS temporariamente');
		console.log('');
		console.log('%c💡 Dica: Compare a versão do style.css local vs produção', 'color: #2271b1;');
	}
	
	// Retorna os resultados para uso programático
	return results;
})();
JAVASCRIPT;

	return $script;
}

/**
 * Adiciona painel de diagnóstico no frontend via query parameter.
 * 
 * Acesse: ?gstore_diagnostics=1 para ver o painel visual.
 */
function gstore_frontend_diagnostics_panel() {
	if ( ! isset( $_GET['gstore_diagnostics'] ) || '1' !== $_GET['gstore_diagnostics'] ) {
		return;
	}
	
	// Apenas administradores podem acessar
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	$rules = gstore_get_css_diagnostic_rules();
	?>
	<div id="gstore-diagnostics-panel" style="
		position: fixed;
		bottom: 20px;
		right: 20px;
		width: 400px;
		max-height: 80vh;
		overflow-y: auto;
		background: #1d2327;
		color: #f0f0f1;
		border-radius: 8px;
		box-shadow: 0 4px 20px rgba(0,0,0,0.3);
		z-index: 999999;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
		font-size: 13px;
	">
		<div style="
			padding: 16px;
			border-bottom: 1px solid #3c434a;
			display: flex;
			justify-content: space-between;
			align-items: center;
		">
			<h3 style="margin: 0; font-size: 14px; display: flex; align-items: center; gap: 8px;">
				🔍 Gstore CSS Diagnostics
			</h3>
			<button onclick="document.getElementById('gstore-diagnostics-panel').remove();" style="
				background: none;
				border: none;
				color: #f0f0f1;
				cursor: pointer;
				padding: 4px;
				display: flex;
				align-items: center;
				justify-content: center;
			">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
			</button>
		</div>
		
		<div style="padding: 16px;">
			<div id="gstore-diag-viewport" style="
				background: #2c3338;
				padding: 10px;
				border-radius: 4px;
				margin-bottom: 16px;
				text-align: center;
			">
				Viewport: <strong id="gstore-diag-width"></strong>
				<span id="gstore-diag-mode" style="
					display: inline-block;
					padding: 2px 8px;
					border-radius: 3px;
					margin-left: 8px;
					font-size: 11px;
					font-weight: 600;
				"></span>
			</div>
			
			<div id="gstore-diag-results"></div>
			
			<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #3c434a;">
				<button onclick="gstoreRunDiagnostics();" style="
					width: 100%;
					padding: 10px;
					background: #2271b1;
					color: #fff;
					border: none;
					border-radius: 4px;
					cursor: pointer;
					font-weight: 500;
				">🔄 Executar Diagnóstico</button>
				
				<button onclick="console.log(gstoreGetDiagnosticsScript());" style="
					width: 100%;
					padding: 10px;
					margin-top: 8px;
					background: #3c434a;
					color: #f0f0f1;
					border: none;
					border-radius: 4px;
					cursor: pointer;
					font-weight: 500;
				">📋 Ver Script no Console</button>
			</div>
		</div>
	</div>
	
	<script>
	var gstoreDiagRules = <?php echo wp_json_encode( $rules ); ?>;
	
	function gstoreGetDiagnosticsScript() {
		return <?php echo wp_json_encode( gstore_generate_css_diagnostics_script() ); ?>;
	}
	
	function gstoreRunDiagnostics() {
		var resultsContainer = document.getElementById('gstore-diag-results');
		var widthEl = document.getElementById('gstore-diag-width');
		var modeEl = document.getElementById('gstore-diag-mode');
		var isMobile = window.innerWidth <= 900;
		
		widthEl.textContent = window.innerWidth + 'px';
		modeEl.textContent = isMobile ? 'MOBILE' : 'DESKTOP';
		modeEl.style.background = isMobile ? '#00a32a' : '#2271b1';
		
		var html = '';
		var passed = 0, failed = 0, notFound = 0;
		
		Object.keys(gstoreDiagRules).forEach(function(key) {
			var rule = gstoreDiagRules[key];
			var shouldCheck = (rule.viewport === 'mobile' && isMobile) || 
			                  (rule.viewport === 'desktop' && !isMobile) ||
			                  !rule.viewport;
			
			if (!shouldCheck) {
				return;
			}
			
			var element = document.querySelector(rule.selector);
			var status, statusColor, statusIcon;
			
			if (!element) {
				status = 'Elemento não encontrado';
				statusColor = '#dba617';
				statusIcon = '❓';
				notFound++;
			} else {
				var computedStyle = window.getComputedStyle(element);
				var actualValue = computedStyle.getPropertyValue(rule.property).trim();
				
				if (actualValue === rule.expected) {
					status = rule.property + ': ' + actualValue;
					statusColor = '#00a32a';
					statusIcon = '✅';
					passed++;
				} else {
					status = 'Esperado: ' + rule.expected + ' | Atual: ' + actualValue;
					statusColor = '#d63638';
					statusIcon = '❌';
					failed++;
				}
			}
			
			html += '<div style="background: #2c3338; padding: 12px; border-radius: 4px; margin-bottom: 8px; border-left: 3px solid ' + statusColor + ';">';
			html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
			html += '<strong>' + statusIcon + ' ' + rule.name + '</strong>';
			html += '</div>';
			html += '<div style="font-size: 11px; color: #a7aaad; margin-top: 6px;">' + status + '</div>';
			html += '<div style="font-size: 10px; color: #72777c; margin-top: 4px;">' + rule.selector + '</div>';
			html += '</div>';
		});
		
		// Resumo
		html = '<div style="display: flex; gap: 10px; margin-bottom: 16px;">' +
			'<div style="flex: 1; text-align: center; padding: 8px; background: #2c3338; border-radius: 4px;">' +
			'<div style="font-size: 20px; color: #00a32a;">' + passed + '</div>' +
			'<div style="font-size: 10px; color: #a7aaad;">Passou</div></div>' +
			'<div style="flex: 1; text-align: center; padding: 8px; background: #2c3338; border-radius: 4px;">' +
			'<div style="font-size: 20px; color: #d63638;">' + failed + '</div>' +
			'<div style="font-size: 10px; color: #a7aaad;">Falhou</div></div>' +
			'<div style="flex: 1; text-align: center; padding: 8px; background: #2c3338; border-radius: 4px;">' +
			'<div style="font-size: 20px; color: #dba617;">' + notFound + '</div>' +
			'<div style="font-size: 10px; color: #a7aaad;">N/A</div></div>' +
			'</div>' + html;
		
		resultsContainer.innerHTML = html;
	}
	
	// Executa automaticamente ao carregar
	document.addEventListener('DOMContentLoaded', gstoreRunDiagnostics);
	window.addEventListener('resize', gstoreRunDiagnostics);
	</script>
	<?php
}
add_action( 'wp_footer', 'gstore_frontend_diagnostics_panel', 9999 );

/**
 * AJAX: Retorna o script de diagnóstico para copiar.
 */
function gstore_ajax_get_diagnostics_script() {
	check_ajax_referer( 'gstore_setup_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}
	
	$script = gstore_generate_css_diagnostics_script();
	
	wp_send_json_success( array(
		'script' => $script,
		'rules'  => gstore_get_css_diagnostic_rules(),
	) );
}
add_action( 'wp_ajax_gstore_get_diagnostics_script', 'gstore_ajax_get_diagnostics_script' );

/**
 * Sincroniza as opções do WooCommerce/WordPress com as páginas atuais.
 *
 * @return array Resultado da sincronização.
 */
function gstore_sync_required_page_options() {
	$pages   = gstore_get_required_pages();
	$updates = array(
		'wc' => 0,
		'wp' => 0,
	);
	$front_page = null;
	$posts_page = null;

	foreach ( $pages as $page_config ) {
		$page = gstore_get_page_by_slug( $page_config['slug'] );

		if ( ! $page ) {
			continue;
		}

		if ( ! empty( $page_config['wc_option'] ) ) {
			update_option( $page_config['wc_option'], $page->ID );
			$updates['wc']++;
		}

		if ( ! empty( $page_config['wp_option'] ) ) {
			update_option( $page_config['wp_option'], $page->ID );
			$updates['wp']++;
		}

		if ( ! empty( $page_config['set_as'] ) ) {
			if ( 'front_page' === $page_config['set_as'] ) {
				$front_page = $page->ID;
			} elseif ( 'posts_page' === $page_config['set_as'] ) {
				$posts_page = $page->ID;
			}
		}
	}

	if ( $front_page ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page );
	}

	if ( $posts_page ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $posts_page );
	}

	$message = sprintf(
		__( '%1$d integrações WooCommerce e %2$d ajustes WordPress sincronizados.', 'gstore' ),
		$updates['wc'],
		$updates['wp']
	);

	return array(
		'success' => true,
		'message' => $message,
		'updates' => $updates,
	);
}

/**
 * Regrava as regras de permalink.
 *
 * @return array Resultado da operação.
 */
function gstore_flush_permalink_rules() {
	flush_rewrite_rules();

	return array(
		'success' => true,
		'message' => __( 'Links permanentes regenerados com sucesso.', 'gstore' ),
	);
}

/**
 * Reseta o template customizado do blog para usar o arquivo do tema.
 */
function gstore_reset_blog_template() {
	global $wpdb;
	
	
	$deleted_count = 0;
	$errors = array();
	
	// Busca todos os templates relacionados ao blog no banco de dados
	// Usa query direta para garantir que encontre todos, independente de status ou meta
	$template_ids = array();
	
	// Busca por page-blog
	$found = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} 
		WHERE post_type = 'wp_template' 
		AND post_name = %s 
		AND post_status != 'trash'",
		'page-blog'
	) );
	$template_ids = array_merge( $template_ids, $found );
	
	// Busca por archive (pode ser usado se a página Blog está configurada como página de posts)
	$found = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} 
		WHERE post_type = 'wp_template' 
		AND post_name = %s 
		AND post_status != 'trash'",
		'archive'
	) );
	$template_ids = array_merge( $template_ids, $found );
	
	// Busca por qualquer template que contenha 'blog' no nome ou título
	$found = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} 
		WHERE post_type = 'wp_template' 
		AND (post_name LIKE %s OR post_title LIKE %s)
		AND post_status != 'trash'",
		'%blog%',
		'%Blog%'
	) );
	$template_ids = array_merge( $template_ids, $found );
	
	// Remove duplicatas
	$template_ids = array_unique( $template_ids );
	
	foreach ( $template_ids as $template_id ) {
		// Verifica se o post ainda existe
		$template_post = get_post( $template_id );
		
		if ( ! $template_post || $template_post->post_type !== 'wp_template' ) {
			continue;
		}
		
		// Deleta o template (qualquer wp_template salvo no banco é uma customização)
		$deleted = wp_delete_post( $template_id, true );
		
		if ( $deleted && ! is_wp_error( $deleted ) ) {
			$deleted_count++;
		} else {
			$error_msg = is_wp_error( $deleted ) ? $deleted->get_error_message() : __( 'Erro desconhecido', 'gstore' );
			$errors[] = sprintf( __( 'Template ID %d: %s', 'gstore' ), $template_id, $error_msg );
		}
	}
	
	// Limpa cache de templates e posts
	wp_cache_flush();
	clean_post_cache( 0 );
	
	// Força o WordPress a recarregar os templates
	if ( function_exists( 'wp_get_theme' ) ) {
		$theme = wp_get_theme();
		delete_transient( 'wp_get_theme' );
	}
	
	if ( $deleted_count > 0 ) {
		$message = sprintf(
			_n(
				'%d template customizado removido com sucesso! A página do blog agora usará o template do tema. Recarregue a página do blog para ver as mudanças.',
				'%d templates customizados removidos com sucesso! A página do blog agora usará o template do tema. Recarregue a página do blog para ver as mudanças.',
				$deleted_count,
				'gstore'
			),
			$deleted_count
		);
		
		if ( ! empty( $errors ) ) {
			$message .= ' ' . __( 'Avisos:', 'gstore' ) . ' ' . implode( ', ', $errors );
		}
		
		return array(
			'success' => true,
			'message' => $message,
		);
	} else {
		$message = __( 'Nenhum template customizado encontrado no banco de dados. A página do blog já está usando o template do tema. Se o problema persistir, pode ser cache do navegador.', 'gstore' );
		
		if ( ! empty( $errors ) ) {
			$message .= ' ' . __( 'Avisos:', 'gstore' ) . ' ' . implode( ', ', $errors );
		}
		
		return array(
			'success' => true,
			'message' => $message,
		);
	}
}

/**
 * Processa ações AJAX do setup.
 */
function gstore_ajax_setup_action() {
	check_ajax_referer( 'gstore_setup_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gstore' ) ) );
	}
	
	$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
	$page_key = isset( $_POST['page_key'] ) ? sanitize_text_field( $_POST['page_key'] ) : '';
	$force = isset( $_POST['force'] ) && 'true' === $_POST['force'];
	
	if ( 'create_single' === $action_type && ! empty( $page_key ) ) {
		$result = gstore_create_page( $page_key, $force );
		wp_send_json( $result );
	} elseif ( 'create_all' === $action_type ) {
		$results = gstore_create_all_pages( $force );
		$success_count = 0;
		$created_count = 0;
		
		foreach ( $results as $result ) {
			if ( $result['success'] ) {
				$success_count++;
				if ( 'created' === $result['action'] ) {
					$created_count++;
				}
			}
		}
		
		wp_send_json( array(
			'success' => true,
			'message' => sprintf(
				__( '%d páginas processadas, %d criadas.', 'gstore' ),
				$success_count,
				$created_count
			),
			'results' => $results,
		) );
	} elseif ( 'sync_assets' === $action_type ) {
		$result = gstore_run_asset_diagnostics();
		wp_send_json( $result );
	} elseif ( 'sync_pages' === $action_type ) {
		$result = gstore_sync_required_page_options();
		wp_send_json( $result );
	} elseif ( 'flush_permalinks' === $action_type ) {
		$result = gstore_flush_permalink_rules();
		wp_send_json( $result );
	} elseif ( 'get_css_diagnostics' === $action_type ) {
		$script = gstore_generate_css_diagnostics_script();
		$frontend_url = add_query_arg( 'gstore_diagnostics', '1', home_url( '/' ) );
		wp_send_json_success( array(
			'script'       => $script,
			'rules'        => gstore_get_css_diagnostic_rules(),
			'frontend_url' => $frontend_url,
			'message'      => __( 'Script de diagnóstico gerado! Cole no console do navegador em produção.', 'gstore' ),
		) );
	} elseif ( 'reset_blog_template' === $action_type ) {
		$result = gstore_reset_blog_template();
		wp_send_json( $result );
	} else {
		wp_send_json_error( array( 'message' => __( 'Ação inválida.', 'gstore' ) ) );
	}
}
add_action( 'wp_ajax_gstore_setup_action', 'gstore_ajax_setup_action' );

/**
 * Renderiza a página de setup do tema.
 */
function gstore_render_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	$pages = gstore_get_required_pages();
	?>
	<div class="wrap gstore-setup-wrap">
		<h1>
			<span class="dashicons dashicons-store" style="font-size: 30px; margin-right: 10px;"></span>
			<?php _e( 'Setup do Tema Gstore', 'gstore' ); ?>
		</h1>
		
		<div class="gstore-setup-intro">
			<p><?php _e( 'Esta ferramenta cria automaticamente todas as páginas necessárias para o funcionamento do tema Gstore. Cada página será configurada com o template correto e integrada com o WooCommerce.', 'gstore' ); ?></p>
		</div>
		
		<div class="gstore-setup-actions">
			<button type="button" id="gstore-create-all" class="button button-primary button-hero">
				<span class="dashicons dashicons-welcome-add-page"></span>
				<?php _e( 'Criar Todas as Páginas', 'gstore' ); ?>
			</button>
			
			<button type="button" id="gstore-recreate-all" class="button button-secondary">
				<span class="dashicons dashicons-update"></span>
				<?php _e( 'Recriar Todas (Sobrescrever)', 'gstore' ); ?>
			</button>
		</div>
		
		<div class="gstore-setup-status" id="gstore-setup-status" style="display: none;">
			<div class="gstore-setup-status__content">
				<span class="spinner is-active"></span>
				<span class="gstore-setup-status__message"></span>
			</div>
		</div>
		
		<table class="wp-list-table widefat fixed striped gstore-pages-table">
			<thead>
				<tr>
					<th class="column-status" style="width: 80px;"><?php _e( 'Status', 'gstore' ); ?></th>
					<th class="column-title"><?php _e( 'Página', 'gstore' ); ?></th>
					<th class="column-template" style="width: 160px;"><?php _e( 'Template', 'gstore' ); ?></th>
					<th class="column-description"><?php _e( 'Descrição', 'gstore' ); ?></th>
					<th class="column-actions" style="width: 200px;"><?php _e( 'Ações', 'gstore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pages as $page_key => $page_config ) : 
					$existing_page = gstore_get_page_by_slug( $page_config['slug'] );
					$status = $existing_page ? 'exists' : 'missing';
					$status_class = $existing_page ? 'gstore-status--success' : 'gstore-status--warning';
					$status_icon = $existing_page ? 'yes-alt' : 'warning';
					$status_text = $existing_page ? __( 'Existe', 'gstore' ) : __( 'Não existe', 'gstore' );
				?>
				<tr id="gstore-page-row-<?php echo esc_attr( $page_key ); ?>" data-page-key="<?php echo esc_attr( $page_key ); ?>">
					<td class="column-status">
						<span class="gstore-status <?php echo esc_attr( $status_class ); ?>">
							<span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>"></span>
							<?php echo esc_html( $status_text ); ?>
						</span>
					</td>
					<td class="column-title">
						<strong><?php echo esc_html( $page_config['title'] ); ?></strong>
						<div class="row-actions">
							<span class="slug">/<code><?php echo esc_html( $page_config['slug'] ); ?></code></span>
							<?php if ( $existing_page ) : ?>
								| <a href="<?php echo esc_url( get_permalink( $existing_page->ID ) ); ?>" target="_blank"><?php _e( 'Ver', 'gstore' ); ?></a>
								| <a href="<?php echo esc_url( get_edit_post_link( $existing_page->ID ) ); ?>"><?php _e( 'Editar', 'gstore' ); ?></a>
							<?php endif; ?>
						</div>
					</td>
					<td class="column-template">
						<?php if ( ! empty( $page_config['template'] ) ) : ?>
							<code><?php echo esc_html( $page_config['template'] ); ?></code>
						<?php else : ?>
							<span class="gstore-muted"><?php _e( 'Padrão', 'gstore' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="column-description">
						<?php echo esc_html( $page_config['description'] ); ?>
						<?php if ( ! empty( $page_config['wc_option'] ) ) : ?>
							<br><small class="gstore-badge gstore-badge--wc">WooCommerce</small>
						<?php endif; ?>
						<?php if ( ! empty( $page_config['set_as'] ) ) : ?>
							<br><small class="gstore-badge gstore-badge--wp">
								<?php 
								if ( 'front_page' === $page_config['set_as'] ) {
									_e( 'Página Inicial', 'gstore' );
								} elseif ( 'posts_page' === $page_config['set_as'] ) {
									_e( 'Página de Posts', 'gstore' );
								}
								?>
							</small>
						<?php endif; ?>
					</td>
					<td class="column-actions">
						<?php if ( $existing_page ) : ?>
							<button type="button" class="button gstore-recreate-page" data-page-key="<?php echo esc_attr( $page_key ); ?>">
								<span class="dashicons dashicons-update"></span>
								<?php _e( 'Recriar', 'gstore' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-primary gstore-create-page" data-page-key="<?php echo esc_attr( $page_key ); ?>">
								<span class="dashicons dashicons-plus-alt"></span>
								<?php _e( 'Criar', 'gstore' ); ?>
							</button>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<div class="gstore-setup-info">
			<h3><span class="dashicons dashicons-info"></span> <?php _e( 'Informações', 'gstore' ); ?></h3>
			<ul>
				<li><?php _e( '<strong>Criar:</strong> Cria a página apenas se ela não existir.', 'gstore' ); ?></li>
				<li><?php _e( '<strong>Recriar:</strong> Remove a página existente e cria uma nova com as configurações padrão do tema.', 'gstore' ); ?></li>
				<li><?php _e( '<strong>Templates:</strong> Páginas com template específico usam layouts customizados do tema Gstore.', 'gstore' ); ?></li>
				<li><?php _e( '<strong>WooCommerce:</strong> Páginas marcadas com WooCommerce são automaticamente configuradas nas opções da loja.', 'gstore' ); ?></li>
			</ul>
		</div>

		<div class="gstore-setup-utilities">
			<h3><span class="dashicons dashicons-hammer"></span> <?php _e( 'Automatizações úteis', 'gstore' ); ?></h3>
			<p><?php _e( 'Execute correções rápidas após instalar o tema ou migrar o site.', 'gstore' ); ?></p>

			<div class="gstore-setup-utilities__grid">
				<div class="gstore-setup-card">
					<h4><?php _e( 'Verificar assets críticos', 'gstore' ); ?></h4>
					<p><?php _e( 'Confere se os arquivos CSS e JS do carrossel e do header estão disponíveis mesmo em child themes.', 'gstore' ); ?></p>
					<button type="button" class="button button-primary gstore-utility-action" data-action="sync_assets" data-loading-text="<?php esc_attr_e( 'Verificando assets...', 'gstore' ); ?>">
						<span class="dashicons dashicons-admin-appearance"></span>
						<?php _e( 'Executar verificação', 'gstore' ); ?>
					</button>
				</div>

				<div class="gstore-setup-card">
					<h4><?php _e( 'Sincronizar páginas do WooCommerce', 'gstore' ); ?></h4>
					<p><?php _e( 'Reatribui carrinho, checkout, minha conta e páginas estáticas nas opções oficiais.', 'gstore' ); ?></p>
					<button type="button" class="button button-secondary gstore-utility-action" data-action="sync_pages" data-loading-text="<?php esc_attr_e( 'Sincronizando páginas...', 'gstore' ); ?>">
						<span class="dashicons dashicons-update-alt"></span>
						<?php _e( 'Sincronizar páginas', 'gstore' ); ?>
					</button>
				</div>

				<div class="gstore-setup-card">
					<h4><?php _e( 'Regravar links permanentes', 'gstore' ); ?></h4>
					<p><?php _e( 'Executa o flush das regras de permalink para resolver erros 404 após migrações.', 'gstore' ); ?></p>
					<button type="button" class="button gstore-utility-action" data-action="flush_permalinks" data-loading-text="<?php esc_attr_e( 'Regravando links...', 'gstore' ); ?>">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php _e( 'Regravar links', 'gstore' ); ?>
					</button>
				</div>

				<div class="gstore-setup-card">
					<h4><?php _e( 'Resetar template do Blog', 'gstore' ); ?></h4>
					<p><?php _e( 'Remove customizações do template do blog salvas no banco de dados, fazendo a página usar o template do tema novamente. Útil quando header/footer não aparecem.', 'gstore' ); ?></p>
					<button type="button" class="button button-secondary gstore-utility-action" data-action="reset_blog_template" data-loading-text="<?php esc_attr_e( 'Resetando template...', 'gstore' ); ?>">
						<span class="dashicons dashicons-update-alt"></span>
						<?php _e( 'Resetar template do Blog', 'gstore' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="gstore-setup-diagnostics">
			<h3><span class="dashicons dashicons-visibility"></span> <?php _e( 'Diagnóstico de CSS em Produção', 'gstore' ); ?></h3>
			<p><?php _e( 'Verifique se as regras CSS críticas estão sendo aplicadas corretamente no frontend. Útil para identificar problemas de cache ou deploy.', 'gstore' ); ?></p>
			
			<div class="gstore-setup-diagnostics__actions">
				<button type="button" id="gstore-open-frontend-diag" class="button button-primary">
					<span class="dashicons dashicons-external"></span>
					<?php _e( 'Abrir Diagnóstico Visual', 'gstore' ); ?>
				</button>
				
				<button type="button" id="gstore-copy-diag-script" class="button">
					<span class="dashicons dashicons-clipboard"></span>
					<?php _e( 'Copiar Script para Console', 'gstore' ); ?>
				</button>
			</div>
			
			<div id="gstore-diag-script-container" style="display: none; margin-top: 16px;">
				<p class="description"><?php _e( 'Cole este script no console do navegador (F12) em produção:', 'gstore' ); ?></p>
				<textarea id="gstore-diag-script-textarea" readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px; background: #1d2327; color: #f0f0f1; border: 1px solid #3c434a; border-radius: 4px; padding: 12px;"></textarea>
			</div>
			
			<div class="gstore-setup-diagnostics__rules" style="margin-top: 20px;">
				<h4><?php _e( 'Regras CSS Monitoradas', 'gstore' ); ?></h4>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 30%;"><?php _e( 'Regra', 'gstore' ); ?></th>
							<th style="width: 25%;"><?php _e( 'Seletor', 'gstore' ); ?></th>
							<th style="width: 20%;"><?php _e( 'Propriedade Esperada', 'gstore' ); ?></th>
							<th style="width: 10%;"><?php _e( 'Viewport', 'gstore' ); ?></th>
							<th style="width: 15%;"><?php _e( 'Arquivo', 'gstore' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php 
						$rules = gstore_get_css_diagnostic_rules();
						foreach ( $rules as $key => $rule ) : 
						?>
						<tr>
							<td><strong><?php echo esc_html( $rule['name'] ); ?></strong></td>
							<td><code style="font-size: 11px;"><?php echo esc_html( $rule['selector'] ); ?></code></td>
							<td><code><?php echo esc_html( $rule['property'] . ': ' . $rule['expected'] ); ?></code></td>
							<td>
								<span class="gstore-badge gstore-badge--<?php echo 'mobile' === $rule['viewport'] ? 'wc' : 'wp'; ?>">
									<?php echo esc_html( ucfirst( $rule['viewport'] ) ); ?>
								</span>
							</td>
							<td><code style="font-size: 10px;"><?php echo esc_html( $rule['css_file'] ); ?></code></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- DIAGNÓSTICO DE ESTRUTURA DO CARRINHO -->
		<div class="gstore-setup-diagnostics" style="margin-top: 30px; border-top: 2px solid #c9a43a; padding-top: 20px;">
			<h3><span class="dashicons dashicons-code-standards"></span> <?php _e( 'Diagnóstico de Estrutura do Carrinho', 'gstore' ); ?></h3>
			<p><?php _e( 'Analise a estrutura HTML da página do carrinho para identificar problemas de layout. Clique no botão e depois vá para a página do carrinho.', 'gstore' ); ?></p>
			
			<?php 
			$cart_page = gstore_get_page_by_slug( 'carrinho' );
			$cart_url = $cart_page ? get_permalink( $cart_page->ID ) : wc_get_cart_url();
			?>
			
			<div class="gstore-setup-diagnostics__actions" style="margin-bottom: 20px;">
				<a href="<?php echo esc_url( add_query_arg( 'gstore_cart_debug', '1', $cart_url ) ); ?>" target="_blank" class="button button-primary">
					<span class="dashicons dashicons-visibility"></span>
					<?php _e( 'Abrir Carrinho com Diagnóstico', 'gstore' ); ?>
				</a>
				
				<button type="button" id="gstore-copy-cart-debug-script" class="button">
					<span class="dashicons dashicons-clipboard"></span>
					<?php _e( 'Copiar Script de Debug', 'gstore' ); ?>
				</button>
			</div>
			
			<div id="gstore-cart-debug-script" style="background: #1d2327; padding: 15px; border-radius: 6px; margin-top: 15px;">
				<p style="color: #f0f0f1; margin: 0 0 10px; font-size: 13px;"><strong>Cole este código no Console do navegador (F12) na página do carrinho:</strong></p>
				<pre style="color: #86efac; font-size: 12px; white-space: pre-wrap; word-break: break-all; margin: 0; max-height: 400px; overflow: auto;"><?php echo esc_html( gstore_get_cart_debug_script() ); ?></pre>
			</div>
		</div>
	</div>
	
	<style>
		.gstore-setup-wrap {
			max-width: 1200px;
		}
		.gstore-setup-wrap h1 {
			display: flex;
			align-items: center;
			margin-bottom: 20px;
		}
		.gstore-setup-intro {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-left: 4px solid #2271b1;
			padding: 12px 20px;
			margin-bottom: 20px;
		}
		.gstore-setup-intro p {
			margin: 0;
			font-size: 14px;
		}
		.gstore-setup-actions {
			margin-bottom: 20px;
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
		}
		.gstore-setup-actions .button-hero {
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.gstore-setup-actions .button-hero .dashicons {
			font-size: 24px;
		}
		.gstore-setup-status {
			background: #f0f6fc;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 15px 20px;
			margin-bottom: 20px;
		}
		.gstore-setup-status__content {
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.gstore-setup-status__content .spinner {
			float: none;
			margin: 0;
		}
		.gstore-setup-status--success {
			background: #d1e7dd;
			border-color: #badbcc;
		}
		.gstore-setup-status--error {
			background: #f8d7da;
			border-color: #f5c2c7;
		}
		.gstore-pages-table {
			margin-bottom: 20px;
		}
		.gstore-pages-table .dashicons {
			font-size: 18px;
			width: 18px;
			height: 18px;
			vertical-align: middle;
		}
		.gstore-pages-table .button .dashicons {
			margin-right: 4px;
		}
		.gstore-status {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			font-size: 12px;
			font-weight: 500;
		}
		.gstore-status--success {
			color: #00a32a;
		}
		.gstore-status--warning {
			color: #dba617;
		}
		.gstore-muted {
			color: #646970;
		}
		.gstore-badge {
			display: inline-block;
			padding: 2px 6px;
			border-radius: 3px;
			font-size: 11px;
			font-weight: 500;
			margin-top: 4px;
		}
		.gstore-badge--wc {
			background: #7f54b3;
			color: #fff;
		}
		.gstore-badge--wp {
			background: #2271b1;
			color: #fff;
		}
		.gstore-setup-info {
			background: #f6f7f7;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 15px 20px;
		}
		.gstore-setup-info h3 {
			margin: 0 0 10px;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.gstore-setup-info ul {
			margin: 0;
			padding-left: 20px;
		}
		.gstore-setup-info li {
			margin-bottom: 5px;
		}
		.gstore-setup-utilities {
			margin-top: 25px;
			padding: 20px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
		}
		.gstore-setup-utilities__grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 16px;
			margin-top: 16px;
		}
		.gstore-setup-card {
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			padding: 16px;
			display: flex;
			flex-direction: column;
			gap: 12px;
		}
		.gstore-setup-card h4 {
			margin: 0;
		}
		.gstore-utility-action .dashicons {
			margin-right: 4px;
		}
		.gstore-utility-action.is-busy {
			opacity: 0.6;
			pointer-events: none;
		}
		.row-actions .slug {
			color: #646970;
		}
		.gstore-row-updating {
			opacity: 0.6;
			pointer-events: none;
		}
		.gstore-setup-diagnostics {
			margin-top: 25px;
			padding: 20px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
		}
		.gstore-setup-diagnostics h3 {
			display: flex;
			align-items: center;
			gap: 8px;
			margin: 0 0 10px;
		}
		.gstore-setup-diagnostics__actions {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
			margin-top: 16px;
		}
		.gstore-setup-diagnostics__actions .button {
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.gstore-setup-diagnostics__rules {
			background: #f6f7f7;
			padding: 16px;
			border-radius: 4px;
		}
		.gstore-setup-diagnostics__rules h4 {
			margin: 0 0 12px;
		}
		.gstore-setup-diagnostics__rules table code {
			background: rgba(0,0,0,0.05);
			padding: 2px 6px;
			border-radius: 3px;
		}
		.gstore-copy-success {
			background: #00a32a !important;
			border-color: #00a32a !important;
			color: #fff !important;
		}
	</style>
	
	<script>
	jQuery(document).ready(function($) {
		var nonce = '<?php echo wp_create_nonce( 'gstore_setup_nonce' ); ?>';
		var defaultSuccessMessage = '<?php echo esc_js( __( 'Ação concluída.', 'gstore' ) ); ?>';
		var defaultLoadingMessage = '<?php echo esc_js( __( 'Executando ação...', 'gstore' ) ); ?>';
		
		function showStatus(message, type) {
			var $status = $('#gstore-setup-status');
			$status.removeClass('gstore-setup-status--success gstore-setup-status--error');
			
			if (type === 'success') {
				$status.addClass('gstore-setup-status--success');
				$status.find('.spinner').removeClass('is-active');
			} else if (type === 'error') {
				$status.addClass('gstore-setup-status--error');
				$status.find('.spinner').removeClass('is-active');
			} else {
				$status.find('.spinner').addClass('is-active');
			}
			
			$status.find('.gstore-setup-status__message').text(message);
			$status.show();
		}
		
		function updateRowStatus($row, success) {
			var $statusCell = $row.find('.column-status');
			var $actionsCell = $row.find('.column-actions');
			var pageKey = $row.data('page-key');
			
			if (success) {
				$statusCell.html('<span class="gstore-status gstore-status--success"><span class="dashicons dashicons-yes-alt"></span> Existe</span>');
				$actionsCell.html('<button type="button" class="button gstore-recreate-page" data-page-key="' + pageKey + '"><span class="dashicons dashicons-update"></span> Recriar</button>');
			}
			
			$row.removeClass('gstore-row-updating');
		}
		
		// Criar página individual
		$(document).on('click', '.gstore-create-page, .gstore-recreate-page', function() {
			var $btn = $(this);
			var pageKey = $btn.data('page-key');
			var $row = $('#gstore-page-row-' + pageKey);
			var force = $btn.hasClass('gstore-recreate-page');
			
			$row.addClass('gstore-row-updating');
			showStatus(force ? 'Recriando página...' : 'Criando página...', 'loading');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'gstore_setup_action',
					action_type: 'create_single',
					page_key: pageKey,
					force: force ? 'true' : 'false',
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						showStatus(response.message, 'success');
						updateRowStatus($row, true);
					} else {
						showStatus(response.message || 'Erro ao criar página.', 'error');
						$row.removeClass('gstore-row-updating');
					}
				},
				error: function() {
					showStatus('Erro de conexão.', 'error');
					$row.removeClass('gstore-row-updating');
				}
			});
		});
		
		// Criar todas as páginas
		$('#gstore-create-all').on('click', function() {
			createAllPages(false);
		});
		
		// Recriar todas as páginas
		$('#gstore-recreate-all').on('click', function() {
			if (confirm('Tem certeza? Isso irá SOBRESCREVER todas as páginas existentes com o conteúdo padrão do tema.')) {
				createAllPages(true);
			}
		});
		
		function createAllPages(force) {
			var $rows = $('.gstore-pages-table tbody tr');
			$rows.addClass('gstore-row-updating');
			showStatus('Criando páginas...', 'loading');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'gstore_setup_action',
					action_type: 'create_all',
					force: force ? 'true' : 'false',
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						showStatus(response.message, 'success');
						
						// Atualiza o status de cada linha
						$.each(response.results, function(pageKey, result) {
							var $row = $('#gstore-page-row-' + pageKey);
							if (result.success) {
								updateRowStatus($row, true);
							} else {
								$row.removeClass('gstore-row-updating');
							}
						});
					} else {
						showStatus(response.message || 'Erro ao criar páginas.', 'error');
						$rows.removeClass('gstore-row-updating');
					}
				},
				error: function() {
					showStatus('Erro de conexão.', 'error');
					$rows.removeClass('gstore-row-updating');
				}
			});
		}

		// Utilidades extras
		$(document).on('click', '.gstore-utility-action', function() {
			var $btn = $(this);
			var actionType = $btn.data('action');
			var loadingText = $btn.data('loading-text') || defaultLoadingMessage;

			if (!actionType || $btn.hasClass('is-busy')) {
				return;
			}

			$btn.addClass('is-busy').prop('disabled', true);
			showStatus(loadingText, 'loading');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'gstore_setup_action',
					action_type: actionType,
					nonce: nonce
				},
				success: function(response) {
					var message = response.message || defaultSuccessMessage;

					if (response.success) {
						showStatus(message, 'success');
					} else {
						if (response.missing && response.missing.length) {
							message += ' (' + response.missing.join(', ') + ')';
						}
						showStatus(message, 'error');
					}
				},
				error: function() {
					showStatus('Erro de conexão.', 'error');
				},
				complete: function() {
					$btn.removeClass('is-busy').prop('disabled', false);
				}
			});
		});

		// Diagnóstico CSS - Abrir no frontend
		$('#gstore-open-frontend-diag').on('click', function() {
			var frontendUrl = '<?php echo esc_js( add_query_arg( 'gstore_diagnostics', '1', home_url( '/' ) ) ); ?>';
			window.open(frontendUrl, '_blank');
		});

		// Diagnóstico CSS - Copiar script para console
		$('#gstore-copy-diag-script').on('click', function() {
			var $btn = $(this);
			var $container = $('#gstore-diag-script-container');
			var $textarea = $('#gstore-diag-script-textarea');

			if ($container.is(':visible') && $textarea.val()) {
				// Se já está visível e tem conteúdo, apenas copia
				copyToClipboard($textarea.val(), $btn);
				return;
			}

			$btn.addClass('is-busy').prop('disabled', true);
			showStatus('Gerando script de diagnóstico...', 'loading');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'gstore_setup_action',
					action_type: 'get_css_diagnostics',
					nonce: nonce
				},
				success: function(response) {
					if (response.success && response.data.script) {
						$textarea.val(response.data.script);
						$container.slideDown();
						showStatus(response.data.message, 'success');
						copyToClipboard(response.data.script, $btn);
					} else {
						showStatus('Erro ao gerar script.', 'error');
					}
				},
				error: function() {
					showStatus('Erro de conexão.', 'error');
				},
				complete: function() {
					$btn.removeClass('is-busy').prop('disabled', false);
				}
			});
		});

		function copyToClipboard(text, $btn) {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					showCopySuccess($btn);
				}).catch(function() {
					fallbackCopy(text, $btn);
				});
			} else {
				fallbackCopy(text, $btn);
			}
		}

		function fallbackCopy(text, $btn) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			document.execCommand('copy');
			$temp.remove();
			showCopySuccess($btn);
		}

		function showCopySuccess($btn) {
			var originalText = $btn.html();
			$btn.addClass('gstore-copy-success').html('<span class="dashicons dashicons-yes"></span> Copiado!');
			setTimeout(function() {
				$btn.removeClass('gstore-copy-success').html(originalText);
			}, 2000);
		}
	});
	</script>
	<?php
}

/**
 * ==========================================
 * GSTORE CART FIX - CENTRALIZAÇÃO FORÇADA
 * ==========================================
 * 
 * Remove estilos conflitantes do WooCommerce e adiciona
 * CSS/JS inline de alta prioridade para garantir que o
 * carrinho fique centralizado corretamente.
 */

/**
 * Remove estilos padrão do WooCommerce na página do carrinho
 * que interferem na centralização.
 */
add_action( 'wp_enqueue_scripts', function() {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		// Remove estilos do WooCommerce que interferem
		wp_dequeue_style( 'woocommerce-layout' );
		wp_dequeue_style( 'woocommerce-smallscreen' );
		wp_dequeue_style( 'wc-blocks-style' );
		wp_dequeue_style( 'wc-blocks-vendors-style' );
	}
}, 100 );

/**
 * Adiciona CSS inline de alta prioridade para forçar centralização do carrinho.
 */
add_action( 'wp_head', function() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	?>
	<style id="gstore-cart-fix">
	/* ============================================
	   GSTORE CART FIX - CENTRALIZAÇÃO FORÇADA
	   ============================================ */
	
	/* Reset variáveis do WordPress */
	body.woocommerce-cart {
		--wp--style--root--padding-left: 0 !important;
		--wp--style--root--padding-right: 0 !important;
		--wp--style--global--content-size: 100% !important;
		--wp--style--global--wide-size: 100% !important;
	}
	
	/* Esconde título duplicado */
	body.woocommerce-cart .wp-block-post-title {
		display: none !important;
	}
	
	/* Reset do main e wrappers */
	body.woocommerce-cart main,
	body.woocommerce-cart .wp-site-blocks > main,
	body.woocommerce-cart .entry-content,
	body.woocommerce-cart .wp-block-post-content {
		width: 100% !important;
		padding: 0 !important;
		margin: 0 !important;
		background: #fff !important;
		display: flex !important;
		justify-content: center !important;
		max-width: none !important;
	}
	
	/* Espaçamento para entry-content quando carrinho está vazio */
	body.woocommerce-cart .entry-content:has(.cart-empty),
	body.woocommerce-cart .wp-block-post-content:has(.cart-empty),
	body.woocommerce-cart .entry-content:has(.return-to-shop),
	body.woocommerce-cart .wp-block-post-content:has(.return-to-shop) {
		padding: 48px 20px !important;
		margin: 0 auto !important;
		max-width: 1280px !important;
		box-sizing: border-box !important;
		display: flex !important;
		flex-direction: column !important;
		align-items: center !important;
		justify-content: center !important;
		min-height: 400px !important;
	}
	
	/* Reset is-layout-constrained - NÃO afeta o container */
	body.woocommerce-cart .is-layout-constrained > *:not(.Gstore-cart-container),
	body.woocommerce-cart .wp-block-group-is-layout-constrained > *:not(.Gstore-cart-container) {
		max-width: none !important;
		margin-left: 0 !important;
		margin-right: 0 !important;
	}
	
	/* Main da página do carrinho */
	body.woocommerce-cart main.Gstore-cart-page,
	body.woocommerce-cart main[data-page="cart"],
	body.woocommerce-cart .gstore-cart-page {
		display: block !important;
		width: 100% !important;
		max-width: none !important;
		margin: 0 !important;
		padding: 0 !important;
		background: #fff !important;
	}
	
	/* SHELL - ocupa 100% da largura */
	body.woocommerce-cart .Gstore-cart-shell,
	body.woocommerce-cart section.Gstore-cart-shell {
		display: block !important;
		width: 100% !important;
		max-width: none !important;
		margin: 0 !important;
		background: #fff !important;
		box-sizing: border-box !important;
	}
	
	/* CONTAINER - centralizado a 1280px */
	body.woocommerce-cart .Gstore-cart-container,
	body.woocommerce-cart div.Gstore-cart-container,
	body.woocommerce-cart .Gstore-cart-shell .Gstore-cart-container,
	body.woocommerce-cart section.Gstore-cart-shell div.Gstore-cart-container {
		display: flex !important;
		flex-direction: column !important;
		gap: 32px !important;
		width: 100% !important;
		max-width: 1280px !important;
		margin-left: auto !important;
		margin-right: auto !important;
		padding-left: 20px !important;
		padding-right: 20px !important;
		box-sizing: border-box !important;
	}
	
	body.woocommerce-cart main .woocommerce {
		max-width: 1280px !important;
	}
	</style>
	<?php
}, 9999 );

/**
 * JavaScript para remover classes problemáticas do WordPress no DOM.
 */
add_action( 'wp_footer', function() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}
	?>
	<script id="gstore-cart-fix-js">
	(function() {
		'use strict';
		
		// Classes problemáticas do WordPress que adicionam max-width
		const badClasses = [
			'is-layout-constrained',
			'wp-block-group-is-layout-constrained',
			'wp-block-post-content-is-layout-constrained'
		];
		
		function cleanCartClasses() {
			// Remove classes do main
			const main = document.querySelector('main.Gstore-cart-page, main[data-page="cart"], main.gstore-cart-page');
			if (main) {
				badClasses.forEach(cls => main.classList.remove(cls));
			}
			
			// Remove classes do entry-content
			const entryContent = document.querySelector('.entry-content');
			if (entryContent) {
				badClasses.forEach(cls => entryContent.classList.remove(cls));
			}
			
			// Remove classes do wp-block-post-content
			const postContent = document.querySelector('.wp-block-post-content');
			if (postContent) {
				badClasses.forEach(cls => postContent.classList.remove(cls));
			}
			
			// Log para debug
			console.log('[Gstore Cart Fix] Classes removidas com sucesso');
		}
		
		// Executa imediatamente
		cleanCartClasses();
		
		// Executa após DOM ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', cleanCartClasses);
		}
		
		// Executa após load completo (para scripts que adicionam classes depois)
		window.addEventListener('load', cleanCartClasses);
	})();
	</script>
	<?php
}, 9999 );

// ============================================
// FUNÇÕES HELPER PARA INFORMAÇÕES DA LOJA
// ============================================

/**
 * Obtém o nome da loja.
 *
 * @param string $format 'name' (CAC ARMAS), 'display' (CAC Armas), ou 'highlight' (ARMAS).
 * @return string
 */
function gstore_get_store_name( $format = 'name' ) {
	$store_info = gstore_store_info();
	
	switch ( $format ) {
		case 'display':
			return $store_info->get_value( 'store.display_name', 'CAC Armas' );
		case 'highlight':
			return $store_info->get_value( 'store.name_highlight', 'ARMAS' );
		default:
			return $store_info->get_value( 'store.name', 'CAC ARMAS' );
	}
}

/**
 * Obtém o CNPJ da loja.
 *
 * @return string
 */
function gstore_get_cnpj() {
	return gstore_store_info()->get_value( 'store.cnpj', '' );
}

/**
 * Obtém o número do WhatsApp.
 *
 * @param string $format 'raw' (556296635633) ou 'display' (+55 62 9663-5633).
 * @return string
 */
function gstore_get_whatsapp( $format = 'raw' ) {
	$store_info = gstore_store_info();
	
	if ( 'display' === $format ) {
		return $store_info->get_value( 'contact.whatsapp_display', '+55 62 9663-5633' );
	}
	
	return $store_info->get_value( 'contact.whatsapp', '556296635633' );
}

/**
 * Gera link do WhatsApp com mensagem opcional.
 *
 * @param string $message Mensagem pré-preenchida (opcional).
 * @return string URL do WhatsApp.
 */
function gstore_get_whatsapp_link( $message = '' ) {
	$whatsapp = gstore_get_whatsapp( 'raw' );
	$url = 'https://wa.me/' . $whatsapp;
	
	if ( ! empty( $message ) ) {
		$url .= '?text=' . rawurlencode( $message );
	}
	
	return $url;
}

/**
 * Obtém o telefone da loja.
 *
 * @param string $format 'raw' ou 'display'.
 * @return string
 */
function gstore_get_phone( $format = 'display' ) {
	$store_info = gstore_store_info();
	
	if ( 'raw' === $format ) {
		return $store_info->get_value( 'contact.phone_raw', '' );
	}
	
	return $store_info->get_value( 'contact.phone', '' );
}

/**
 * Obtém informações de uma rede social.
 *
 * @param string $network Nome da rede (instagram, facebook, youtube, telegram_group).
 * @return string Username/ID da rede social.
 */
function gstore_get_social( $network ) {
	return gstore_store_info()->get_value( 'social.' . $network, '' );
}

/**
 * Gera link para rede social.
 *
 * @param string $network Nome da rede (instagram, facebook, youtube, telegram).
 * @return string URL completa da rede social.
 */
function gstore_get_social_link( $network ) {
	$username = gstore_get_social( $network );
	
	if ( empty( $username ) ) {
		return '';
	}
	
	switch ( $network ) {
		case 'instagram':
		case 'instagram_alt':
			return 'https://www.instagram.com/' . $username . '/';
		case 'facebook':
			return 'https://www.facebook.com/profile.php?id=' . $username;
		case 'youtube':
			return 'https://www.youtube.com/' . $username;
		case 'telegram':
		case 'telegram_group':
			return 'https://t.me/' . $username;
		case 'twitter':
			return 'https://twitter.com/' . $username;
		case 'tiktok':
			return 'https://www.tiktok.com/@' . $username;
		default:
			return '';
	}
}

/**
 * Obtém o endereço formatado.
 *
 * @param string $format 'full', 'street', 'city_state', ou 'short'.
 * @return string
 */
function gstore_get_address( $format = 'full' ) {
	$store_info = gstore_store_info();
	$address = $store_info->get_value( 'address' );
	
	if ( ! is_array( $address ) ) {
		return '';
	}
	
	switch ( $format ) {
		case 'street':
			return $address['street'] ?? '';
		case 'city_state':
			return sprintf( '%s - %s', $address['city'] ?? '', $address['state'] ?? '' );
		case 'short':
			return sprintf(
				'%s - %s - CEP: %s - %s - %s',
				$address['street'] ?? '',
				$address['neighborhood'] ?? '',
				$address['zipcode'] ?? '',
				$address['city'] ?? '',
				$address['state'] ?? ''
			);
		case 'full':
		default:
			return sprintf(
				"%s - %s\nCEP: %s - %s - %s",
				$address['street'] ?? '',
				$address['neighborhood'] ?? '',
				$address['zipcode'] ?? '',
				$address['city'] ?? '',
				$address['state'] ?? ''
			);
	}
}

/**
 * Obtém URL do Google Maps.
 *
 * @return string
 */
function gstore_get_maps_url() {
	return gstore_store_info()->get_value( 'address.maps_url', '' );
}

/**
 * Obtém horário de funcionamento.
 *
 * @param string $format 'full', 'weekdays', 'saturday', ou 'support'.
 * @return string
 */
function gstore_get_business_hours( $format = 'full' ) {
	$store_info = gstore_store_info();
	
	switch ( $format ) {
		case 'weekdays':
			return $store_info->get_value( 'business_hours.weekdays', '' );
		case 'saturday':
			return $store_info->get_value( 'business_hours.saturday', '' );
		case 'support':
			return $store_info->get_value( 'business_hours.support_hours', '' );
		case 'full':
		default:
			return $store_info->get_value( 'business_hours.full_text', '' );
	}
}

/**
 * Obtém informações do footer.
 *
 * @param string $key Chave específica (about_paragraphs, newsletter, etc).
 * @return mixed
 */
function gstore_get_footer_info( $key = '' ) {
	$store_info = gstore_store_info();
	
	if ( empty( $key ) ) {
		return $store_info->get_value( 'footer' );
	}
	
	return $store_info->get_value( 'footer.' . $key );
}

/**
 * Obtém o texto de copyright com ano atual.
 *
 * @return string
 */
function gstore_get_copyright() {
	$template = gstore_store_info()->get_value( 'footer.copyright_text', 'Copyright © {year} {store_name}. Todos os direitos reservados.' );
	
	$replacements = array(
		'{year}'       => date( 'Y' ),
		'{store_name}' => gstore_get_store_name(),
	);
	
	return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
}

/**
 * Obtém meta informações do site.
 *
 * @param string $key 'description', 'keywords', ou 'og_image'.
 * @return string
 */
function gstore_get_meta( $key ) {
	return gstore_store_info()->get_value( 'meta.' . $key, '' );
}

/**
 * Obtém cor de branding.
 *
 * @param string $key 'accent_color', 'primary_color'.
 * @return string
 */
function gstore_get_brand_color( $key ) {
	return gstore_store_info()->get_value( 'branding.' . $key, '' );
}

/**
 * Obtém o ano de fundação da loja.
 *
 * @return string
 */
function gstore_get_founded_year() {
	return gstore_store_info()->get_value( 'store.founded_year', '' );
}

// ============================================
// HANDLERS DE EXPORTAÇÃO/IMPORTAÇÃO
// ============================================

/**
 * Handler para exportar informações da loja como JSON.
 */
function gstore_handle_export_store_info() {
	// Verifica permissões
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Você não tem permissão para realizar esta ação.', 'gstore' ) );
	}
	
	// Verifica nonce
	if ( ! isset( $_POST['gstore_export_nonce'] ) || ! wp_verify_nonce( $_POST['gstore_export_nonce'], 'gstore_export_store_info' ) ) {
		wp_die( __( 'Verificação de segurança falhou.', 'gstore' ) );
	}
	
	$store_info = gstore_store_info();
	$json_content = $store_info->export_json();
	
	// Define headers para download
	$filename = 'store-info-' . sanitize_file_name( gstore_get_store_name() ) . '-' . date( 'Y-m-d' ) . '.json';
	
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . strlen( $json_content ) );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	
	echo $json_content;
	exit;
}
add_action( 'admin_post_gstore_export_store_info', 'gstore_handle_export_store_info' );

/**
 * Handler para importar informações da loja de um arquivo JSON.
 */
function gstore_handle_import_store_info() {
	// Verifica permissões
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Você não tem permissão para realizar esta ação.', 'gstore' ) );
	}
	
	// Verifica nonce
	if ( ! isset( $_POST['gstore_import_nonce'] ) || ! wp_verify_nonce( $_POST['gstore_import_nonce'], 'gstore_import_store_info' ) ) {
		wp_die( __( 'Verificação de segurança falhou.', 'gstore' ) );
	}
	
	// Verifica se arquivo foi enviado
	if ( ! isset( $_FILES['store_info_file'] ) || $_FILES['store_info_file']['error'] !== UPLOAD_ERR_OK ) {
		$error_message = __( 'Erro ao enviar o arquivo.', 'gstore' );
		
		if ( isset( $_FILES['store_info_file']['error'] ) ) {
			switch ( $_FILES['store_info_file']['error'] ) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$error_message = __( 'O arquivo é muito grande.', 'gstore' );
					break;
				case UPLOAD_ERR_NO_FILE:
					$error_message = __( 'Nenhum arquivo foi selecionado.', 'gstore' );
					break;
			}
		}
		
		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( $error_message ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}
	
	// Lê o conteúdo do arquivo primeiro (validação por conteúdo, não apenas extensão)
	$json_content = file_get_contents( $_FILES['store_info_file']['tmp_name'] );
	
	if ( empty( $json_content ) ) {
		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( __( 'O arquivo está vazio.', 'gstore' ) ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}
	
	// Remove BOM (Byte Order Mark) se presente
	$json_content = preg_replace( '/^\xEF\xBB\xBF/', '', $json_content );
	
	// Remove espaços em branco no início e fim
	$json_content = trim( $json_content );
	
	// Valida o JSON antes de importar
	$json_data = json_decode( $json_content, true );
	
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		// Mensagem de erro específica do JSON
		$json_error_messages = array(
			JSON_ERROR_DEPTH          => __( 'Profundidade máxima da pilha excedida.', 'gstore' ),
			JSON_ERROR_STATE_MISMATCH => __( 'JSON inválido ou malformado.', 'gstore' ),
			JSON_ERROR_CTRL_CHAR      => __( 'Caractere de controle encontrado, possivelmente codificado incorretamente.', 'gstore' ),
			JSON_ERROR_SYNTAX         => __( 'Erro de sintaxe JSON. Verifique vírgulas, chaves e aspas.', 'gstore' ),
			JSON_ERROR_UTF8           => __( 'Caracteres UTF-8 malformados, possivelmente codificação incorreta.', 'gstore' ),
		);
		
		$error_message = isset( $json_error_messages[ json_last_error() ] ) 
			? $json_error_messages[ json_last_error() ] 
			: __( 'Erro desconhecido ao processar JSON.', 'gstore' );
		
		$error_message .= ' ' . sprintf( __( 'Detalhes: %s', 'gstore' ), json_last_error_msg() );
		
		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( $error_message ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}
	
	// Tenta importar (passa o array já decodificado para evitar decodificar duas vezes)
	$store_info = gstore_store_info();
	
	// Debug: verifica se o JSON foi decodificado corretamente
	if ( ! is_array( $json_data ) ) {
		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( sprintf( 
				__( 'Erro: JSON decodificado não é um array. Tipo: %s', 'gstore' ), 
				gettype( $json_data ) 
			) ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}
	
	$result = $store_info->import_json( $json_data );
	
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		
		// Adiciona informações de debug se for erro de estrutura
		if ( $result->get_error_code() === 'invalid_structure' ) {
			$secoes_encontradas = array_keys( $json_data );
			$error_message .= ' | Seções no JSON: ' . implode( ', ', $secoes_encontradas );
		}
		
		wp_redirect( add_query_arg( array(
			'page'    => 'gstore-settings',
			'message' => 'import_error',
			'error'   => urlencode( $error_message ),
		), admin_url( 'themes.php' ) ) );
		exit;
	}
	
	// Sucesso
	wp_redirect( add_query_arg( array(
		'page'    => 'gstore-settings',
		'message' => 'import_success',
	), admin_url( 'themes.php' ) ) );
	exit;
}
add_action( 'admin_post_gstore_import_store_info', 'gstore_handle_import_store_info' );

/**
 * Exibe mensagens de sucesso/erro para importação.
 */
function gstore_store_info_admin_notices() {
	$screen = get_current_screen();
	
	if ( ! $screen || $screen->id !== 'appearance_page_gstore-settings' ) {
		return;
	}
	
	if ( isset( $_GET['message'] ) ) {
		if ( $_GET['message'] === 'import_success' ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Informações da loja importadas com sucesso!', 'gstore' ); ?></p>
			</div>
			<?php
		} elseif ( $_GET['message'] === 'import_error' && isset( $_GET['error'] ) ) {
			$error_message = urldecode( $_GET['error'] );
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?php _e( 'Erro ao importar JSON:', 'gstore' ); ?></strong></p>
				<p><?php echo esc_html( $error_message ); ?></p>
				<p style="margin-top: 10px;">
					<strong><?php _e( 'Dicas:', 'gstore' ); ?></strong>
					<ul style="margin-left: 20px; margin-top: 5px;">
						<li><?php _e( 'Verifique se o arquivo é um JSON válido (use um validador online se necessário)', 'gstore' ); ?></li>
						<li><?php _e( 'Certifique-se de que todas as seções obrigatórias estão presentes', 'gstore' ); ?></li>
						<li><?php _e( 'Verifique vírgulas, chaves { } e colchetes [ ]', 'gstore' ); ?></li>
						<li><?php _e( 'Certifique-se de que todas as strings estão entre aspas duplas', 'gstore' ); ?></li>
					</ul>
				</p>
			</div>
			<?php
		}
	}
}
add_action( 'admin_notices', 'gstore_store_info_admin_notices' );

// ============================================
// PROCESSAMENTO DE PLACEHOLDERS NOS TEMPLATES
// ============================================

/**
 * Processa placeholders de informações da loja no conteúdo.
 *
 * Substitui placeholders como {{store_name}}, {{whatsapp_link}}, etc.
 *
 * @param string $content Conteúdo a processar.
 * @return string Conteúdo processado.
 */
function gstore_process_store_info_placeholders( $content ) {
	if ( empty( $content ) || strpos( $content, '{{' ) === false ) {
		return $content;
	}
	
	// Lista de placeholders e seus valores
	$placeholders = array(
		// Store
		'{{store_name}}'          => gstore_get_store_name(),
		'{{store_display_name}}'  => gstore_get_store_name( 'display' ),
		'{{store_name_highlight}}' => gstore_get_store_name( 'highlight' ),
		'{{cnpj}}'                => gstore_get_cnpj(),
		'{{founded_year}}'        => gstore_get_founded_year(),
		
		// Contact
		'{{phone}}'               => gstore_get_phone(),
		'{{phone_raw}}'           => gstore_get_phone( 'raw' ),
		'{{whatsapp}}'            => gstore_get_whatsapp(),
		'{{whatsapp_display}}'    => gstore_get_whatsapp( 'display' ),
		'{{whatsapp_link}}'       => gstore_get_whatsapp_link(),
		'{{whatsapp_link_hello}}' => gstore_get_whatsapp_link( 'Olá ' . gstore_get_store_name( 'display' ) . '!' ),
		'{{whatsapp_link_rastreio}}' => gstore_get_whatsapp_link( 'Olá ' . gstore_get_store_name( 'display' ) . '! Gostaria de rastrear meu pedido.' ),
		'{{whatsapp_link_troca}}' => gstore_get_whatsapp_link( 'Olá ' . gstore_get_store_name( 'display' ) . '! Gostaria de solicitar uma troca ou devolução.' ),
		
		// Social
		'{{instagram}}'           => gstore_get_social( 'instagram' ),
		'{{instagram_link}}'      => gstore_get_social_link( 'instagram' ),
		'{{facebook_link}}'       => gstore_get_social_link( 'facebook' ),
		'{{youtube_link}}'        => gstore_get_social_link( 'youtube' ),
		'{{telegram}}'            => gstore_get_social( 'telegram_group' ),
		'{{telegram_link}}'       => gstore_get_social_link( 'telegram_group' ),
		
		// Address
		'{{address_street}}'      => gstore_get_address( 'street' ),
		'{{address_full}}'        => gstore_get_address( 'full' ),
		'{{address_short}}'       => gstore_get_address( 'short' ),
		'{{address_city_state}}'  => gstore_get_address( 'city_state' ),
		'{{maps_url}}'            => gstore_get_maps_url(),
		
		// Business hours
		'{{business_hours}}'      => gstore_get_business_hours(),
		'{{support_hours}}'       => gstore_get_business_hours( 'support' ),
		
		// Footer
		'{{copyright}}'           => gstore_get_copyright(),
		
		// Meta
		'{{meta_description}}'    => gstore_get_meta( 'description' ),
		
		// Branding
		'{{accent_color}}'        => gstore_get_brand_color( 'accent_color' ),
		
		// Dynamic
		'{{year}}'                => date( 'Y' ),
	);
	
	// Processa parágrafos do footer (array)
	$footer_paragraphs = gstore_get_footer_info( 'about_paragraphs' );
	if ( is_array( $footer_paragraphs ) ) {
		foreach ( $footer_paragraphs as $index => $paragraph ) {
			$placeholders['{{footer_paragraph_' . ( $index + 1 ) . '}}'] = $paragraph;
		}
	}
	
	// Substitui os placeholders
	$content = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
	
	return $content;
}

/**
 * Adiciona o processamento de placeholders ao output buffer existente.
 *
 * Este filtro é aplicado ao output final da página.
 *
 * @param string $content Conteúdo HTML.
 * @return string Conteúdo processado.
 */
function gstore_add_store_info_to_output_processing( $content ) {
	return gstore_process_store_info_placeholders( $content );
}
add_filter( 'gstore_process_final_output', 'gstore_add_store_info_to_output_processing', 5 );

/**
 * Processa placeholders em blocos HTML.
 */
add_filter( 'render_block_core/html', function( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_store_info_placeholders( $block_content );
	}
	return $block_content;
}, 15, 2 );

/**
 * Processa placeholders em template parts.
 */
add_filter( 'render_block_core/template-part', function( $block_content, $block ) {
	if ( ! empty( $block_content ) ) {
		$block_content = gstore_process_store_info_placeholders( $block_content );
	}
	return $block_content;
}, 15, 2 );

// ============================================
// MIGRAÇÃO E INICIALIZAÇÃO AUTOMÁTICA
// ============================================

/**
 * Inicializa o arquivo JSON com dados padrão se não existir.
 *
 * Executado automaticamente na primeira visita ao admin.
 */
function gstore_maybe_init_store_info_json() {
	// Só executa no admin e se o usuário tiver permissões
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// Verifica se já foi inicializado
	$initialized = get_option( 'gstore_store_info_initialized', false );
	if ( $initialized ) {
		return;
	}
	
	$store_info = gstore_store_info();
	
	// Se o arquivo JSON não existe, cria com valores padrão
	if ( ! $store_info->json_exists() ) {
		$store_info->create_default_json();
	}
	
	// Marca como inicializado
	update_option( 'gstore_store_info_initialized', true );
}
add_action( 'admin_init', 'gstore_maybe_init_store_info_json' );

// ============================================
// MODAL DE VERIFICAÇÃO DE IDADE (+18)
// ============================================

/**
 * Exibe o modal de verificação de idade no frontend.
 * 
 * O modal aparece na primeira visita do usuário e guarda
 * a confirmação no localStorage por 30 dias.
 */
function gstore_age_verification_modal() {
	// Não exibe no admin
	if ( is_admin() ) {
		return;
	}
	?>
	<!-- Modal de Verificação de Idade -->
	<div id="gstore-age-modal" class="gstore-age-modal" aria-hidden="true" role="dialog" aria-labelledby="gstore-age-title" aria-describedby="gstore-age-desc">
		<div class="gstore-age-modal__overlay"></div>
		<div class="gstore-age-modal__content">
			<button class="gstore-age-modal__close" aria-label="<?php esc_attr_e( 'Fechar modal', 'gstore' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M18 6L6 18M6 6l12 12"/>
				</svg>
			</button>
			<div class="gstore-age-modal__icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
					<path d="M12 8v4"/>
					<path d="M12 16h.01"/>
				</svg>
			</div>
			<h2 id="gstore-age-title" class="gstore-age-modal__title">Verificação de Idade</h2>
			<p id="gstore-age-desc" class="gstore-age-modal__text">
				Este site contém produtos destinados exclusivamente para maiores de 18 anos.
			</p>
			<p class="gstore-age-modal__question">Você tem 18 anos ou mais?</p>
			<div class="gstore-age-modal__actions">
				<button type="button" id="gstore-age-confirm" class="gstore-age-modal__btn gstore-age-modal__btn--confirm">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="20 6 9 17 4 12"/>
					</svg>
					Sim, tenho 18+
				</button>
				<button type="button" id="gstore-age-deny" class="gstore-age-modal__btn gstore-age-modal__btn--deny">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"/>
						<line x1="6" y1="6" x2="18" y2="18"/>
					</svg>
					Não, sou menor
				</button>
			</div>
			<p class="gstore-age-modal__disclaimer">
				Ao confirmar, você declara estar ciente de que é proibida a venda de produtos para menores de 18 anos.
			</p>
		</div>
	</div>

	<style id="gstore-age-modal-styles">
		/* Modal de Verificação de Idade */
		.gstore-age-modal {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			z-index: 999999;
			display: flex;
			align-items: center;
			justify-content: center;
			opacity: 0;
			visibility: hidden;
			pointer-events: none;
			transition: opacity 0.4s ease, visibility 0.4s ease;
		}
		
		.gstore-age-modal[aria-hidden="false"] {
			opacity: 1;
			visibility: visible;
			pointer-events: auto;
		}
		
		.gstore-age-modal__overlay {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: linear-gradient(135deg, rgba(0, 0, 0, 0.92) 0%, rgba(20, 20, 30, 0.95) 100%);
			backdrop-filter: blur(8px);
			-webkit-backdrop-filter: blur(8px);
		}
		
		.gstore-age-modal__content {
			position: relative;
			background: linear-gradient(180deg, #1a1a1a 0%, #0d0d0d 100%);
			border: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 20px;
			box-shadow: 
				0 25px 50px -12px rgba(0, 0, 0, 0.8),
				0 0 0 1px rgba(255, 255, 255, 0.05),
				inset 0 1px 0 0 rgba(255, 255, 255, 0.1);
			max-width: 440px;
			width: 90%;
			padding: 48px 40px;
			text-align: center;
			transform: scale(0.9) translateY(20px);
			opacity: 0;
			transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.4s ease;
			z-index: 1000000;
		}
		
		.gstore-age-modal[aria-hidden="false"] .gstore-age-modal__content {
			transform: scale(1) translateY(0);
			opacity: 1;
		}
		
		.gstore-age-modal__close {
			position: absolute;
			top: 16px;
			right: 16px;
			background: transparent;
			border: none;
			color: rgba(255, 255, 255, 0.6);
			cursor: pointer;
			padding: 8px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			transition: color 0.2s ease, background-color 0.2s ease;
			border-radius: 4px;
			z-index: 1;
		}
		
		.gstore-age-modal__close:hover,
		.gstore-age-modal__close:focus {
			color: rgba(255, 255, 255, 1);
			background-color: rgba(255, 255, 255, 0.1);
			outline: none;
		}
		
		.gstore-age-modal__close svg {
			width: 20px;
			height: 20px;
		}
		
		.gstore-age-modal__icon {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 88px;
			height: 88px;
			margin: 0 auto 24px;
			background: linear-gradient(135deg, rgba(220, 38, 38, 0.2) 0%, rgba(239, 68, 68, 0.1) 100%);
			border-radius: 50%;
			color: #ef4444;
			animation: gstore-age-pulse 2s ease-in-out infinite;
		}
		
		@keyframes gstore-age-pulse {
			0%, 100% {
				box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.3);
			}
			50% {
				box-shadow: 0 0 0 15px rgba(239, 68, 68, 0);
			}
		}
		
		.gstore-age-modal__title {
			margin: 0 0 12px;
			font-size: 28px;
			font-weight: 700;
			color: #ffffff;
			letter-spacing: -0.02em;
			line-height: 1.2;
		}
		
		.gstore-age-modal__text {
			margin: 0 0 8px;
			font-size: 15px;
			line-height: 1.6;
			color: rgba(255, 255, 255, 0.7);
		}
		
		.gstore-age-modal__question {
			margin: 24px 0;
			font-size: 18px;
			font-weight: 600;
			color: #ffffff;
		}
		
		.gstore-age-modal__actions {
			display: flex;
			flex-direction: column;
			gap: 12px;
			margin-bottom: 24px;
		}
		
		.gstore-age-modal__btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 10px;
			padding: 16px 32px;
			font-size: 16px;
			font-weight: 600;
			border: none;
			border-radius: 12px;
			cursor: pointer;
			transition: all 0.2s ease;
			text-transform: none;
			letter-spacing: 0;
		}
		
		.gstore-age-modal__btn--confirm {
			background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
			color: #ffffff;
			box-shadow: 0 4px 14px 0 rgba(34, 197, 94, 0.4);
		}
		
		.gstore-age-modal__btn--confirm:hover {
			background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
			transform: translateY(-2px);
			box-shadow: 0 6px 20px 0 rgba(34, 197, 94, 0.5);
		}
		
		.gstore-age-modal__btn--confirm:active {
			transform: translateY(0);
		}
		
		.gstore-age-modal__btn--deny {
			background: rgba(255, 255, 255, 0.05);
			color: rgba(255, 255, 255, 0.7);
			border: 1px solid rgba(255, 255, 255, 0.1);
		}
		
		.gstore-age-modal__btn--deny:hover {
			background: rgba(239, 68, 68, 0.1);
			color: #ef4444;
			border-color: rgba(239, 68, 68, 0.3);
		}
		
		.gstore-age-modal__disclaimer {
			margin: 0;
			font-size: 12px;
			line-height: 1.5;
			color: rgba(255, 255, 255, 0.4);
		}
		
		/* Tela de bloqueio para menores */
		.gstore-age-modal--blocked .gstore-age-modal__content {
			padding: 60px 40px;
		}
		
		.gstore-age-modal__blocked-icon {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 100px;
			height: 100px;
			margin: 0 auto 28px;
			background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.1) 100%);
			border-radius: 50%;
			color: #ef4444;
		}
		
		.gstore-age-modal__blocked-title {
			margin: 0 0 16px;
			font-size: 24px;
			font-weight: 700;
			color: #ffffff;
		}
		
		.gstore-age-modal__blocked-text {
			margin: 0;
			font-size: 15px;
			line-height: 1.7;
			color: rgba(255, 255, 255, 0.6);
		}
		
		/* Responsividade */
		@media (max-width: 480px) {
			.gstore-age-modal__content {
				width: 94%;
				padding: 36px 24px;
				border-radius: 16px;
			}
			
			.gstore-age-modal__icon {
				width: 72px;
				height: 72px;
				margin-bottom: 20px;
			}
			
			.gstore-age-modal__icon svg {
				width: 48px;
				height: 48px;
			}
			
			.gstore-age-modal__title {
				font-size: 22px;
			}
			
			.gstore-age-modal__text {
				font-size: 14px;
			}
			
			.gstore-age-modal__question {
				font-size: 16px;
				margin: 20px 0;
			}
			
			.gstore-age-modal__btn {
				padding: 14px 24px;
				font-size: 15px;
			}
			
			.gstore-age-modal--blocked .gstore-age-modal__content {
				padding: 40px 24px;
			}
			
			.gstore-age-modal__close {
				top: 12px;
				right: 12px;
			}
		}
		
		/* Animação de entrada */
		@keyframes gstore-age-fadeIn {
			from {
				opacity: 0;
			}
			to {
				opacity: 1;
			}
		}
		
		/* Previne scroll do body quando modal está aberto */
		body.gstore-age-modal-open {
			overflow: hidden;
		}
	</style>

	<script id="gstore-age-modal-script">
	(function() {
		'use strict';
		
		var STORAGE_KEY = 'gstore_age_verified';
		var STORAGE_DURATION = 30 * 24 * 60 * 60 * 1000; // 30 dias em ms
		
		function isVerified() {
			try {
				var stored = localStorage.getItem(STORAGE_KEY);
				if (!stored) return false;
				
				var data = JSON.parse(stored);
				var now = new Date().getTime();
				
				// Verifica se ainda está válido
				if (data.verified && data.expires > now) {
					return true;
				}
				
				// Expirou, remove
				localStorage.removeItem(STORAGE_KEY);
				return false;
			} catch (e) {
				return false;
			}
		}
		
		function setVerified() {
			try {
				var data = {
					verified: true,
					expires: new Date().getTime() + STORAGE_DURATION
				};
				localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
			} catch (e) {
				// localStorage não disponível
			}
		}
		
		function showModal() {
			var modal = document.getElementById('gstore-age-modal');
			if (modal) {
				document.body.classList.add('gstore-age-modal-open');
				modal.setAttribute('aria-hidden', 'false');
			}
		}
		
		function hideModal() {
			var modal = document.getElementById('gstore-age-modal');
			if (modal) {
				document.body.classList.remove('gstore-age-modal-open');
				modal.setAttribute('aria-hidden', 'true');
			}
		}
		
		function showBlockedScreen() {
			var modal = document.getElementById('gstore-age-modal');
			if (!modal) return;
			
			modal.classList.add('gstore-age-modal--blocked');
			var content = modal.querySelector('.gstore-age-modal__content');
			
			if (content) {
				content.innerHTML = 
					'<div class="gstore-age-modal__blocked-icon">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' +
							'<circle cx="12" cy="12" r="10"/>' +
							'<line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>' +
						'</svg>' +
					'</div>' +
					'<h2 class="gstore-age-modal__blocked-title">Acesso Restrito</h2>' +
					'<p class="gstore-age-modal__blocked-text">' +
						'Desculpe, este site é destinado apenas para maiores de 18 anos.<br><br>' +
						'Você será redirecionado para o Google em alguns segundos...' +
					'</p>';
			}
			
			// Redireciona após 5 segundos
			setTimeout(function() {
				window.location.href = 'https://www.google.com';
			}, 5000);
		}
		
		function init() {
			// Se já verificado, não mostra o modal
			if (isVerified()) {
				var modal = document.getElementById('gstore-age-modal');
				if (modal) {
					modal.remove();
				}
				return;
			}
			
			// Mostra o modal
			showModal();
			
			// Event listeners
			var modal = document.getElementById('gstore-age-modal');
			var confirmBtn = document.getElementById('gstore-age-confirm');
			var denyBtn = document.getElementById('gstore-age-deny');
			var closeBtn = modal ? modal.querySelector('.gstore-age-modal__close') : null;
			
			if (closeBtn) {
				closeBtn.addEventListener('click', function() {
					// Ao fechar sem responder, redireciona para fora do site
					window.location.href = 'https://www.google.com';
				});
			}
			
			if (confirmBtn) {
				confirmBtn.addEventListener('click', function() {
					setVerified();
					hideModal();
					
					// Remove o modal após a animação
					setTimeout(function() {
						var modal = document.getElementById('gstore-age-modal');
						if (modal) modal.remove();
					}, 500);
				});
			}
			
			if (denyBtn) {
				denyBtn.addEventListener('click', function() {
					showBlockedScreen();
				});
			}
		}
		
		// Inicializa quando o DOM estiver pronto
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'gstore_age_verification_modal', 999 );

// Inicializa classe de administração (regeneração de thumbnails)
require_once get_template_directory() . '/inc/class-gstore-admin.php';
new Gstore_Admin();

// Atualizador do tema via GitHub (admin).
if ( is_admin() ) {
	require_once get_theme_file_path( 'inc/class-gstore-theme-updater.php' );
	new Gstore_Theme_Git_Updater();
}

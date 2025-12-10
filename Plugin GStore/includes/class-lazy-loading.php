<?php
/**
 * Classe para lazy loading avançado de imagens
 *
 * @package GStore_Optimizer
 */

// Previne acesso direto
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Previne redeclaração da classe
if ( ! class_exists( 'GStore_Lazy_Loading' ) ) {

/**
 * Classe GStore_Lazy_Loading
 */
class GStore_Lazy_Loading {

	/**
	 * Construtor
	 */
	public function __construct() {
		// Adiciona lazy loading a imagens que não têm
		add_filter( 'the_content', array( $this, 'add_lazy_loading_to_images' ), 20 );
		
		// Adiciona lazy loading a imagens de blocos Gutenberg
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_lazy_loading_to_attachment_images' ), 10, 3 );
		
		// Adiciona lazy loading a iframes
		add_filter( 'the_content', array( $this, 'add_lazy_loading_to_iframes' ), 20 );
		
		// Adiciona preload para recursos críticos
		add_action( 'wp_head', array( $this, 'add_preload_resources' ), 1 );
		
		// Adiciona script para lazy loading avançado (imagens, background-images, iframes)
		add_action( 'wp_footer', array( $this, 'add_lazy_loading_script' ), 1 );
	}

	/**
	 * Adiciona lazy loading a imagens no conteúdo
	 *
	 * @param string $content Conteúdo do post
	 * @return string
	 */
	public function add_lazy_loading_to_images( $content ) {
		// Não processa em admin
		if ( is_admin() ) {
			return $content;
		}

		// Verifica se já há imagens com lazy loading
		if ( strpos( $content, 'loading="lazy"' ) !== false || strpos( $content, 'loading="eager"' ) !== false ) {
			// Apenas adiciona a imagens que não têm o atributo
			$content = preg_replace_callback(
				'/<img([^>]*?)(?:\s+loading\s*=\s*["\'](?:lazy|eager)["\'])?([^>]*?)>/i',
				array( $this, 'process_image_tag' ),
				$content
			);
		} else {
			// Adiciona lazy loading a todas as imagens (exceto as primeiras)
			$content = preg_replace_callback(
				'/<img([^>]*?)>/i',
				array( $this, 'process_image_tag' ),
				$content
			);
		}

		return $content;
	}

	/**
	 * Processa tag de imagem individual
	 *
	 * @param array $matches Matches do preg_replace_callback
	 * @return string
	 */
	private function process_image_tag( $matches ) {
		$img_tag = $matches[0];
		$attributes = $matches[1] ?? '';

		// Não adiciona lazy loading se já tiver
		if ( preg_match( '/loading\s*=\s*["\'](?:lazy|eager)["\']/i', $img_tag ) ) {
			return $img_tag;
		}

		// Não adiciona lazy loading se tiver fetchpriority="high"
		if ( preg_match( '/fetchpriority\s*=\s*["\']high["\']/i', $img_tag ) ) {
			return $img_tag;
		}

		// Verifica se imagem está acima da dobra (primeiras 3 imagens)
		static $image_count = 0;
		$image_count++;

		// Primeiras 3 imagens não recebem lazy loading
		if ( $image_count <= 3 ) {
			return $img_tag;
		}

		// Adiciona lazy loading
		$img_tag = str_replace( '<img', '<img loading="lazy"', $img_tag );

		return $img_tag;
	}

	/**
	 * Adiciona preload para recursos críticos
	 */
	public function add_preload_resources() {
		// Preload da primeira imagem do hero (se disponível)
		$hero_image = $this->get_hero_image();
		if ( $hero_image ) {
			echo '<link rel="preload" as="image" href="' . esc_url( $hero_image['url'] ) . '" fetchpriority="high">' . "\n";
		}

		// Preload de fontes críticas (se necessário)
		$critical_fonts = apply_filters( 'gstore_optimizer_critical_fonts', array() );
		foreach ( $critical_fonts as $font_url ) {
			echo '<link rel="preload" as="font" href="' . esc_url( $font_url ) . '" crossorigin>' . "\n";
		}
	}

	/**
	 * Obtém primeira imagem do hero
	 *
	 * @return array|false Array com url e alt ou false
	 */
	private function get_hero_image() {
		// Obtém ID da primeira imagem do hero usando função do tema
		if ( function_exists( 'gstore_get_hero_slide_1_id' ) ) {
			$hero_slide_1_id = gstore_get_hero_slide_1_id();
			
			if ( $hero_slide_1_id > 0 ) {
				$hero_url = wp_get_attachment_url( $hero_slide_1_id );
				$hero_alt = get_option( 'gstore_hero_slide_1_alt', 'Campanha Excedente Black Week CAC Armas' );
				
				if ( $hero_url ) {
					return array(
						'url' => $hero_url,
						'alt' => $hero_alt,
					);
				}
			}
		}

		// Fallback: tenta obter via filtro
		$hero_placeholder = apply_filters( 'gstore_hero_image_placeholder', false );
		if ( $hero_placeholder && isset( $hero_placeholder['url'] ) ) {
			return $hero_placeholder;
		}

		return false;
	}

	/**
	 * Adiciona lazy loading a imagens de attachment (blocos Gutenberg, etc.)
	 *
	 * @param array $attr Atributos da imagem
	 * @param WP_Post $attachment Objeto do attachment
	 * @param string|array $size Tamanho da imagem
	 * @return array
	 */
	public function add_lazy_loading_to_attachment_images( $attr, $attachment, $size ) {
		// Não processa em admin
		if ( is_admin() ) {
			return $attr;
		}

		// Se já tem loading definido, não altera
		if ( isset( $attr['loading'] ) ) {
			return $attr;
		}

		// Não adiciona lazy loading se tiver fetchpriority="high"
		if ( isset( $attr['fetchpriority'] ) && 'high' === $attr['fetchpriority'] ) {
			$attr['loading'] = 'eager';
			return $attr;
		}

		// Verifica se é imagem crítica usando classes CSS
		$is_critical = false;
		if ( isset( $attr['class'] ) ) {
			$classes = explode( ' ', $attr['class'] );
			$critical_classes = array( 'hero-image', 'logo', 'above-fold', 'critical-image' );
			$is_critical = ! empty( array_intersect( $classes, $critical_classes ) );
		}

		// Verifica se imagem está acima da dobra usando detecção inteligente
		if ( ! $is_critical && isset( $attachment->ID ) ) {
			$is_critical = $this->is_image_above_fold( $attachment->ID );
		}

		// Define loading baseado na detecção
		if ( $is_critical ) {
			$attr['loading'] = 'eager';
			$attr['fetchpriority'] = 'high';
		} else {
			$attr['loading'] = 'lazy';
		}

		return $attr;
	}

	/**
	 * Verifica se imagem está acima da dobra (detecção inteligente)
	 *
	 * @param int $attachment_id ID do attachment
	 * @return bool
	 */
	private function is_image_above_fold( $attachment_id ) {
		// Primeiras 3 imagens do conteúdo são consideradas acima da dobra
		static $image_count = 0;
		$image_count++;
		
		if ( $image_count <= 3 ) {
			return true;
		}

		// Verifica se é imagem destacada (featured image)
		if ( has_post_thumbnail() && get_post_thumbnail_id() === $attachment_id ) {
			return true;
		}

		// Verifica se é hero image
		if ( function_exists( 'gstore_get_hero_slide_1_id' ) ) {
			$hero_id = gstore_get_hero_slide_1_id();
			if ( $hero_id === $attachment_id ) {
				return true;
			}
		}

		// Permite filtro para customização
		return apply_filters( 'gstore_optimizer_is_image_above_fold', false, $attachment_id );
	}

	/**
	 * Adiciona lazy loading a iframes (YouTube, etc)
	 *
	 * @param string $content Conteúdo do post
	 * @return string
	 */
	public function add_lazy_loading_to_iframes( $content ) {
		// Não processa em admin
		if ( is_admin() ) {
			return $content;
		}

		// Adiciona loading="lazy" a iframes que não têm
		$content = preg_replace_callback(
			'/<iframe([^>]*?)(?:\s+loading\s*=\s*["\'](?:lazy|eager)["\'])?([^>]*?)>/i',
			function( $matches ) {
				$iframe_tag = $matches[0];

				// Se já tem loading, retorna como está
				if ( preg_match( '/loading\s*=\s*["\'](?:lazy|eager)["\']/i', $iframe_tag ) ) {
					return $iframe_tag;
				}

				// Adiciona loading="lazy"
				$iframe_tag = str_replace( '<iframe', '<iframe loading="lazy"', $iframe_tag );

				return $iframe_tag;
			},
			$content
		);

		return $content;
	}

	/**
	 * Adiciona script para lazy loading de background images e melhorar lazy loading nativo
	 */
	public function add_lazy_loading_script() {
		?>
		<script>
		(function() {
			// Lazy loading para background images
			function lazyLoadBackgroundImages() {
				if ('IntersectionObserver' in window) {
					var bgImageObserver = new IntersectionObserver(function(entries, observer) {
						entries.forEach(function(entry) {
							if (entry.isIntersecting) {
								var element = entry.target;
								var bgImage = element.dataset.bgImage;
								if (bgImage) {
									element.style.backgroundImage = 'url(' + bgImage + ')';
									element.classList.add('lazy-bg-loaded');
									element.removeAttribute('data-bg-image');
									observer.unobserve(element);
								}
							}
						});
					}, {
						rootMargin: '50px' // Carrega 50px antes de entrar no viewport
					});

					// Encontra elementos com data-bg-image
					var bgElements = document.querySelectorAll('[data-bg-image]');
					bgElements.forEach(function(el) {
						bgImageObserver.observe(el);
					});
				}
			}

			// Melhora suporte a lazy loading nativo
			if ('loading' in HTMLImageElement.prototype) {
				// Navegador suporta lazy loading nativo
				var images = document.querySelectorAll('img[loading="lazy"]');
				images.forEach(function(img) {
					if (img.complete) {
						return;
					}
					
					img.addEventListener('load', function() {
						this.classList.add('lazy-loaded');
					});
					
					img.addEventListener('error', function() {
						this.classList.add('lazy-error');
					});
				});

				// Lazy loading de background images
				lazyLoadBackgroundImages();
			} else {
				// Fallback para navegadores sem suporte nativo
				if ('IntersectionObserver' in window) {
					var imageObserver = new IntersectionObserver(function(entries, observer) {
						entries.forEach(function(entry) {
							if (entry.isIntersecting) {
								var img = entry.target;
								if (img.dataset.src) {
									img.src = img.dataset.src;
									img.removeAttribute('data-src');
								}
								img.classList.add('lazy-loaded');
								observer.unobserve(img);
							}
						});
					}, {
						rootMargin: '50px'
					});
					
					var lazyImages = document.querySelectorAll('img[loading="lazy"]');
					lazyImages.forEach(function(img) {
						if (!img.dataset.src && img.src) {
							img.dataset.src = img.src;
							img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E';
						}
						imageObserver.observe(img);
					});

					// Lazy loading de background images
					lazyLoadBackgroundImages();
				}
			}

			// Lazy loading de iframes
			if ('loading' in HTMLIFrameElement.prototype) {
				// Navegador suporta lazy loading nativo para iframes
				var iframes = document.querySelectorAll('iframe[loading="lazy"]');
				iframes.forEach(function(iframe) {
					iframe.addEventListener('load', function() {
						this.classList.add('lazy-loaded');
					});
				});
			} else if ('IntersectionObserver' in window) {
				// Fallback para iframes
				var iframeObserver = new IntersectionObserver(function(entries, observer) {
					entries.forEach(function(entry) {
						if (entry.isIntersecting) {
							var iframe = entry.target;
							if (iframe.dataset.src) {
								iframe.src = iframe.dataset.src;
								iframe.removeAttribute('data-src');
								iframe.classList.add('lazy-loaded');
								observer.unobserve(iframe);
							}
						}
					});
				}, {
					rootMargin: '100px' // Iframes podem ser carregados um pouco antes
				});

				var lazyIframes = document.querySelectorAll('iframe[loading="lazy"]');
				lazyIframes.forEach(function(iframe) {
					if (!iframe.dataset.src && iframe.src) {
						iframe.dataset.src = iframe.src;
						iframe.src = '';
					}
					iframeObserver.observe(iframe);
				});
			}
		})();
		</script>
		<?php
	}
}

} // Fim da verificação class_exists


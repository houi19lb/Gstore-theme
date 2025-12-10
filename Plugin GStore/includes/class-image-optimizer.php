<?php
/**
 * Classe para otimização de imagens e conversão para WebP
 *
 * @package GStore_Optimizer
 */

// Previne acesso direto
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe GStore_Image_Optimizer
 */
class GStore_Image_Optimizer {

	/**
	 * Construtor
	 */
	public function __construct() {
		// Hook para converter imagens no upload (gera WebP e AVIF)
		add_filter( 'wp_handle_upload', array( $this, 'convert_to_modern_formats' ), 10, 2 );
		
		// Hook para gerar WebP e AVIF de tamanhos existentes
		add_action( 'image_make_intermediate_size', array( $this, 'generate_modern_formats' ), 10, 1 );
		
		// Filtro para servir WebP/AVIF quando disponível
		add_filter( 'wp_get_attachment_image_src', array( $this, 'maybe_serve_modern_format' ), 10, 4 );
		
		// Filtro para adicionar srcset com formatos modernos (WebP e AVIF)
		add_filter( 'wp_calculate_image_srcset', array( $this, 'add_modern_formats_to_srcset' ), 10, 5 );
		
		// Filtro para garantir dimensões em imagens
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'ensure_image_dimensions' ), 10, 3 );
	}

	/**
	 * Converte imagem para formatos modernos (WebP e AVIF) após upload
	 *
	 * @param array  $file Array com informações do arquivo
	 * @param string $filename Nome do arquivo
	 * @return array
	 */
	public function convert_to_modern_formats( $file, $filename ) {
		// Verifica se é uma imagem válida
		if ( ! $this->is_image( $file['type'] ) ) {
			return $file;
		}

		// Verifica se o arquivo existe
		if ( ! file_exists( $file['file'] ) ) {
			return $file;
		}

		// Gera versão WebP (se GD disponível)
		if ( function_exists( 'imagewebp' ) ) {
			$this->generate_webp( $file['file'] );
		}

		// Gera versão AVIF (se ImageMagick disponível e suporta AVIF)
		if ( $this->supports_avif() ) {
			$this->generate_avif( $file['file'] );
		}

		return $file;
	}

	/**
	 * Gera versões WebP e AVIF de um tamanho intermediário
	 *
	 * @param array $data Dados do tamanho gerado
	 * @return array
	 */
	public function generate_modern_formats( $data ) {
		if ( ! isset( $data['path'] ) || ! file_exists( $data['path'] ) ) {
			return $data;
		}

		// Gera WebP
		$this->generate_webp( $data['path'] );
		
		// Gera AVIF se suportado
		if ( $this->supports_avif() ) {
			$this->generate_avif( $data['path'] );
		}

		return $data;
	}

	/**
	 * Verifica se o servidor suporta AVIF
	 *
	 * @return bool
	 */
	private function supports_avif() {
		// Verifica se ImageMagick está disponível e suporta AVIF
		if ( ! extension_loaded( 'imagick' ) ) {
			return false;
		}
		
		// Verifica se a classe Imagick existe
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}
		
		try {
			$imagick = new Imagick();
			$formats = $imagick->queryFormats();
			$imagick->clear();
			$imagick->destroy();
			return in_array( 'AVIF', $formats, true );
		} catch ( Exception $e ) {
			// Se houver erro, retorna false
			return false;
		}
	}

	/**
	 * Gera arquivo AVIF a partir de uma imagem
	 *
	 * @param string $image_path Caminho da imagem original
	 * @return bool|string Caminho do AVIF gerado ou false em caso de erro
	 */
	private function generate_avif( $image_path ) {
		// Verifica se já existe AVIF
		$avif_path = $this->get_avif_path( $image_path );
		if ( file_exists( $avif_path ) ) {
			return $avif_path;
		}

		// Usa ImageMagick para gerar AVIF
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return false;
		}

		try {
			$imagick = new Imagick( $image_path );
			$imagick->setImageFormat( 'AVIF' );
			
			// Qualidade AVIF (0-100, padrão 75)
			$quality = get_option( 'gstore_optimizer_avif_quality', 75 );
			$quality = apply_filters( 'gstore_optimizer_avif_quality', $quality );
			$imagick->setImageCompressionQuality( $quality );
			
			// Cria diretório se não existir
			$avif_dir = dirname( $avif_path );
			if ( ! file_exists( $avif_dir ) ) {
				wp_mkdir_p( $avif_dir );
			}
			
			$success = $imagick->writeImage( $avif_path );
			$imagick->clear();
			$imagick->destroy();
			
			if ( $success ) {
				chmod( $avif_path, 0644 );
				return $avif_path;
			}
		} catch ( Exception $e ) {
			// Log erro se WP_DEBUG estiver ativo
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GStore Optimizer: Erro ao gerar AVIF - ' . $e->getMessage() );
			}
		}

		return false;
	}

	/**
	 * Retorna caminho do arquivo AVIF
	 *
	 * @param string $image_path Caminho da imagem original
	 * @return string
	 */
	private function get_avif_path( $image_path ) {
		$pathinfo = pathinfo( $image_path );
		return $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.avif';
	}

	/**
	 * Gera arquivo WebP a partir de uma imagem
	 *
	 * @param string $image_path Caminho da imagem original
	 * @return bool|string Caminho do WebP gerado ou false em caso de erro
	 */
	private function generate_webp( $image_path ) {
		// Verifica se já existe WebP
		$webp_path = $this->get_webp_path( $image_path );
		if ( file_exists( $webp_path ) ) {
			return $webp_path;
		}

		// Obtém informações da imagem
		$image_info = @getimagesize( $image_path );
		if ( ! $image_info ) {
			return false;
		}

		$mime_type = $image_info['mime'];
		$width     = $image_info[0];
		$height    = $image_info[1];

		// Cria imagem resource baseada no tipo
		$image = false;
		switch ( $mime_type ) {
			case 'image/jpeg':
				$image = @imagecreatefromjpeg( $image_path );
				break;
			case 'image/png':
				$image = @imagecreatefrompng( $image_path );
				// Preserva transparência PNG
				imagealphablending( $image, false );
				imagesavealpha( $image, true );
				break;
			case 'image/gif':
				$image = @imagecreatefromgif( $image_path );
				break;
			default:
				return false;
		}

		if ( ! $image ) {
			return false;
		}

		// Cria diretório se não existir
		$webp_dir = dirname( $webp_path );
		if ( ! file_exists( $webp_dir ) ) {
			wp_mkdir_p( $webp_dir );
		}

		// Qualidade WebP (0-100, padrão 80 ou valor da opção)
		$quality = get_option( 'gstore_optimizer_webp_quality', 80 );
		$quality = apply_filters( 'gstore_optimizer_webp_quality', $quality );

		// Gera WebP
		$success = imagewebp( $image, $webp_path, $quality );

		// Libera memória
		imagedestroy( $image );

		if ( $success ) {
			// Define permissões
			chmod( $webp_path, 0644 );
			return $webp_path;
		}

		return false;
	}

	/**
	 * Retorna caminho do arquivo WebP
	 *
	 * @param string $image_path Caminho da imagem original
	 * @return string
	 */
	private function get_webp_path( $image_path ) {
		$pathinfo = pathinfo( $image_path );
		return $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.webp';
	}

	/**
	 * Verifica se o tipo MIME é uma imagem suportada
	 *
	 * @param string $mime_type Tipo MIME
	 * @return bool
	 */
	private function is_image( $mime_type ) {
		$supported = array( 'image/jpeg', 'image/png', 'image/gif' );
		return in_array( $mime_type, $supported, true );
	}

	/**
	 * Serve WebP/AVIF quando disponível e navegador suporta
	 *
	 * @param array|false  $image Array com src, width, height ou false
	 * @param int          $attachment_id ID do attachment
	 * @param string|array $size Tamanho da imagem
	 * @param bool         $icon Se é ícone
	 * @return array|false
	 */
	public function maybe_serve_modern_format( $image, $attachment_id, $size, $icon ) {
		if ( ! $image || ! isset( $image[0] ) ) {
			return $image;
		}

		$original_path = $this->get_attachment_path( $image[0] );
		if ( ! $original_path ) {
			return $image;
		}

		// Prioriza AVIF se disponível e suportado
		if ( $this->browser_supports_avif() ) {
			$avif_path = $this->get_avif_path( $original_path );
			if ( file_exists( $avif_path ) ) {
				$avif_url = $this->get_attachment_url( $avif_path );
				if ( $avif_url ) {
					$image[0] = $avif_url;
					return $image;
				}
			}
		}

		// Fallback para WebP
		if ( $this->browser_supports_webp() ) {
			$webp_path = $this->get_webp_path( $original_path );
			if ( file_exists( $webp_path ) ) {
				$webp_url = $this->get_attachment_url( $webp_path );
				if ( $webp_url ) {
					$image[0] = $webp_url;
				}
			}
		}

		return $image;
	}

	/**
	 * Verifica se navegador suporta AVIF
	 *
	 * @return bool
	 */
	private function browser_supports_avif() {
		if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return false;
		}

		return strpos( $_SERVER['HTTP_ACCEPT'], 'image/avif' ) !== false;
	}

	/**
	 * Adiciona versões WebP e AVIF ao srcset
	 *
	 * @param array  $sources Array de sources
	 * @param array  $size_array Array com width e height
	 * @param string $image_src URL da imagem
	 * @param array  $image_meta Metadados da imagem
	 * @param int    $attachment_id ID do attachment
	 * @return array
	 */
	public function add_modern_formats_to_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		// Verifica se já há formatos modernos no srcset
		$has_modern_format = false;
		foreach ( $sources as $source ) {
			if ( isset( $source['url'] ) && ( strpos( $source['url'], '.webp' ) !== false || strpos( $source['url'], '.avif' ) !== false ) ) {
				$has_modern_format = true;
				break;
			}
		}

		if ( $has_modern_format ) {
			return $sources;
		}

		$modern_sources = array();
		$prefer_avif = $this->browser_supports_avif();
		$prefer_webp = $this->browser_supports_webp();

		foreach ( $sources as $width => $source ) {
			if ( ! isset( $source['url'] ) ) {
				$modern_sources[ $width ] = $source;
				continue;
			}

			$original_path = $this->get_attachment_path( $source['url'] );
			if ( ! $original_path ) {
				$modern_sources[ $width ] = $source;
				continue;
			}

			// Prioriza AVIF se disponível e suportado
			if ( $prefer_avif ) {
				$avif_path = $this->get_avif_path( $original_path );
				if ( file_exists( $avif_path ) ) {
					$avif_url = $this->get_attachment_url( $avif_path );
					if ( $avif_url ) {
						$source['url'] = $avif_url;
						$modern_sources[ $width ] = $source;
						continue;
					}
				}
			}

			// Fallback para WebP
			if ( $prefer_webp ) {
				$webp_path = $this->get_webp_path( $original_path );
				if ( file_exists( $webp_path ) ) {
					$webp_url = $this->get_attachment_url( $webp_path );
					if ( $webp_url ) {
						$source['url'] = $webp_url;
					}
				}
			}

			$modern_sources[ $width ] = $source;
		}

		return ! empty( $modern_sources ) ? $modern_sources : $sources;
	}

	/**
	 * Garante que imagens tenham dimensões (width/height)
	 *
	 * @param array $attr Atributos da imagem
	 * @param WP_Post $attachment Objeto do attachment
	 * @param string|array $size Tamanho da imagem
	 * @return array
	 */
	public function ensure_image_dimensions( $attr, $attachment, $size ) {
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

	/**
	 * Verifica se navegador suporta WebP
	 *
	 * @return bool
	 */
	private function browser_supports_webp() {
		if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return false;
		}

		return strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false;
	}


	/**
	 * Converte URL em caminho do sistema de arquivos
	 *
	 * @param string $url URL da imagem
	 * @return string|false Caminho ou false
	 */
	private function get_attachment_path( $url ) {
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$base_dir   = $upload_dir['basedir'];

		if ( strpos( $url, $base_url ) === 0 ) {
			$relative_path = str_replace( $base_url, '', $url );
			$relative_path = ltrim( $relative_path, '/' );
			return $base_dir . '/' . $relative_path;
		}

		return false;
	}

	/**
	 * Converte caminho do sistema em URL
	 *
	 * @param string $path Caminho do arquivo
	 * @return string|false URL ou false
	 */
	private function get_attachment_url( $path ) {
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$base_dir   = $upload_dir['basedir'];

		if ( strpos( $path, $base_dir ) === 0 ) {
			$relative_path = str_replace( $base_dir, '', $path );
			$relative_path = ltrim( $relative_path, '/' );
			return $base_url . '/' . $relative_path;
		}

		return false;
	}
}


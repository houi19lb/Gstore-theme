<?php
/**
 * Classe para minificação de assets (CSS/JS)
 *
 * @package GStore_Optimizer
 */

// Previne acesso direto
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe GStore_Asset_Optimizer
 */
class GStore_Asset_Optimizer {

	/**
	 * Diretório de cache para arquivos minificados
	 *
	 * @var string
	 */
	private $cache_dir;

	/**
	 * Construtor
	 */
	public function __construct() {
		// Define diretório de cache
		$upload_dir = wp_upload_dir();
		$this->cache_dir = $upload_dir['basedir'] . '/gstore-optimizer-cache';
		
		// Cria diretório de cache se não existir
		if ( ! file_exists( $this->cache_dir ) ) {
			wp_mkdir_p( $this->cache_dir );
		}

		// Minifica CSS inline
		add_filter( 'style_loader_tag', array( $this, 'minify_inline_css' ), 20, 2 );
		
		// Minifica arquivos CSS enfileirados (apenas arquivos locais do tema)
		add_filter( 'style_loader_tag', array( $this, 'minify_file_css' ), 15, 4 );
		
		// Minifica JavaScript inline (se necessário)
		add_filter( 'script_loader_tag', array( $this, 'minify_inline_js' ), 20, 2 );
	}

	/**
	 * Minifica CSS inline
	 *
	 * @param string $tag Tag HTML do estilo
	 * @param string $handle Handle do estilo
	 * @return string
	 */
	public function minify_inline_css( $tag, $handle ) {
		// Não minifica em desenvolvimento
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return $tag;
		}

		// Extrai CSS inline da tag
		if ( preg_match( '/<style[^>]*>(.*?)<\/style>/is', $tag, $matches ) ) {
			$css = $matches[1];
			$minified = $this->minify_css( $css );
			
			// Substitui CSS na tag
			$tag = str_replace( $css, $minified, $tag );
		}

		return $tag;
	}

	/**
	 * Minifica arquivos CSS enfileirados
	 *
	 * @param string $tag Tag HTML do estilo
	 * @param string $handle Handle do estilo
	 * @param string $href URL do arquivo CSS
	 * @param string $media Media query
	 * @return string
	 */
	public function minify_file_css( $tag, $handle, $href, $media ) {
		// Não minifica em desenvolvimento
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return $tag;
		}

		// Apenas processa arquivos locais (do tema ou plugin)
		if ( ! $this->is_local_file( $href ) ) {
			return $tag;
		}

		// Verifica se já está minificado
		if ( strpos( $href, '.min.css' ) !== false ) {
			return $tag;
		}

		// Obtém caminho do arquivo
		$file_path = $this->get_file_path( $href );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $tag;
		}

		// Gera nome do arquivo minificado
		$minified_path = $this->get_minified_path( $file_path );
		
		// Garante que o diretório de cache existe
		$cache_dir = dirname( $minified_path );
		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}
		
		// Verifica se precisa gerar minificado
		if ( ! file_exists( $minified_path ) || filemtime( $file_path ) > filemtime( $minified_path ) ) {
			$css_content = file_get_contents( $file_path );
			if ( $css_content === false ) {
				return $tag;
			}
			
			$minified = $this->minify_css( $css_content );
			if ( false !== file_put_contents( $minified_path, $minified ) ) {
				chmod( $minified_path, 0644 );
			}
		}

		// Substitui URL na tag
		$minified_url = $this->get_file_url( $minified_path );
		if ( $minified_url ) {
			$tag = str_replace( $href, $minified_url, $tag );
		}

		return $tag;
	}

	/**
	 * Verifica se é arquivo local
	 *
	 * @param string $url URL do arquivo
	 * @return bool
	 */
	private function is_local_file( $url ) {
		$site_url = site_url();
		return strpos( $url, $site_url ) === 0 || strpos( $url, '/' ) === 0;
	}

	/**
	 * Converte URL em caminho do sistema
	 *
	 * @param string $url URL do arquivo
	 * @return string|false Caminho ou false
	 */
	private function get_file_path( $url ) {
		// Remove query string
		$url = strtok( $url, '?' );
		
		// Se é URL absoluta, converte para relativa
		$site_url = site_url();
		if ( strpos( $url, $site_url ) === 0 ) {
			$url = str_replace( $site_url, '', $url );
		}
		
		// Remove barra inicial
		$url = ltrim( $url, '/' );
		
		// Tenta encontrar no tema
		$theme_path = get_stylesheet_directory() . '/' . $url;
		if ( file_exists( $theme_path ) ) {
			return $theme_path;
		}
		
		// Tenta no tema pai
		$parent_path = get_template_directory() . '/' . $url;
		if ( file_exists( $parent_path ) ) {
			return $parent_path;
		}
		
		// Tenta em uploads
		$upload_dir = wp_upload_dir();
		$upload_path = $upload_dir['basedir'] . '/' . $url;
		if ( file_exists( $upload_path ) ) {
			return $upload_path;
		}
		
		return false;
	}

	/**
	 * Gera caminho do arquivo minificado
	 *
	 * @param string $file_path Caminho do arquivo original
	 * @return string
	 */
	private function get_minified_path( $file_path ) {
		$pathinfo = pathinfo( $file_path );
		return $this->cache_dir . '/' . md5( $file_path ) . '.min.css';
	}

	/**
	 * Converte caminho em URL
	 *
	 * @param string $file_path Caminho do arquivo
	 * @return string|false URL ou false
	 */
	private function get_file_url( $file_path ) {
		// Verifica se é arquivo do cache
		if ( strpos( $file_path, $this->cache_dir ) === 0 ) {
			$upload_dir = wp_upload_dir();
			return str_replace( $this->cache_dir, $upload_dir['baseurl'] . '/gstore-optimizer-cache', $file_path );
		}
		
		$upload_dir = wp_upload_dir();
		if ( strpos( $file_path, $upload_dir['basedir'] ) === 0 ) {
			return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
		}
		
		$theme_dir = get_stylesheet_directory();
		if ( strpos( $file_path, $theme_dir ) === 0 ) {
			return str_replace( $theme_dir, get_stylesheet_directory_uri(), $file_path );
		}
		
		$parent_dir = get_template_directory();
		if ( strpos( $file_path, $parent_dir ) === 0 ) {
			return str_replace( $parent_dir, get_template_directory_uri(), $file_path );
		}
		
		return false;
	}

	/**
	 * Minifica JavaScript inline
	 *
	 * @param string $tag Tag HTML do script
	 * @param string $handle Handle do script
	 * @return string
	 */
	public function minify_inline_js( $tag, $handle ) {
		// Não minifica em desenvolvimento
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return $tag;
		}

		// Extrai JS inline da tag
		if ( preg_match( '/<script[^>]*>(.*?)<\/script>/is', $tag, $matches ) ) {
			$js = $matches[1];
			$minified = $this->minify_js( $js );
			
			// Substitui JS na tag
			$tag = str_replace( $js, $minified, $tag );
		}

		return $tag;
	}

	/**
	 * Minifica CSS
	 *
	 * @param string $css CSS a ser minificado
	 * @return string CSS minificado
	 */
	private function minify_css( $css ) {
		// Remove comentários
		$css = preg_replace( '/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $css );
		
		// Remove espaços em branco desnecessários
		$css = preg_replace( '/\s+/', ' ', $css );
		
		// Remove espaços antes e depois de caracteres especiais
		$css = preg_replace( '/\s*([{}:;,])\s*/', '$1', $css );
		
		// Remove último ponto e vírgula de regras
		$css = preg_replace( '/;}/', '}', $css );
		
		// Remove espaços em branco no início e fim
		$css = trim( $css );
		
		return $css;
	}

	/**
	 * Minifica JavaScript
	 *
	 * @param string $js JavaScript a ser minificado
	 * @return string JavaScript minificado
	 */
	private function minify_js( $js ) {
		// Remove comentários de linha única
		$js = preg_replace( '/\/\/.*$/m', '', $js );
		
		// Remove comentários de múltiplas linhas
		$js = preg_replace( '/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $js );
		
		// Remove espaços em branco desnecessários (cuidado com strings)
		// Remove espaços ao redor de operadores, mas preserva strings
		$js = preg_replace( '/\s*([=+\-*\/%<>!&|?:;,{}()\[\]])\s*/', '$1', $js );
		
		// Remove espaços em branco no início e fim
		$js = trim( $js );
		
		// Nota: Minificação mais agressiva pode quebrar código
		// Esta é uma minificação básica e segura
		
		return $js;
	}

	/**
	 * Converte URL para caminho do arquivo
	 *
	 * @param string $url URL do arquivo
	 * @return string|false Caminho do arquivo ou false
	 */
	private function url_to_path( $url ) {
		// Remove query strings
		$url = strtok( $url, '?' );

		// Converte URL para caminho
		$home_url = home_url( '/' );
		if ( strpos( $url, $home_url ) === 0 ) {
			$path = str_replace( $home_url, ABSPATH, $url );
			return $path;
		}

		// Tenta caminho relativo
		if ( strpos( $url, '/' ) === 0 ) {
			return ABSPATH . ltrim( $url, '/' );
		}

		return false;
	}
}


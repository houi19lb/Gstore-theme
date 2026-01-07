<?php
/**
 * API Visualizer (React app) integrado ao tema.
 *
 * Uso:
 * - Adicione o shortcode [api_visualizer] em qualquer página/post.
 *
 * Como funciona:
 * - Em produção: enfileira os assets gerados em api-visualizer/dist via manifest.json (Vite).
 * - Em desenvolvimento (fallback): se não houver manifest e WP_DEBUG estiver ligado,
 *   tenta usar o Vite dev server (padrão: http://localhost:3000).
 * - Injeta uma config global window.API_VISUALIZER_CONFIG com URLs do WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retorna o caminho absoluto do manifest do Vite.
 */
function gstore_api_visualizer_manifest_path() {
	// Vite gera por padrão em dist/.vite/manifest.json
	return get_theme_file_path( 'api-visualizer/dist/.vite/manifest.json' );
}

/**
 * Lê e cacheia o manifest do Vite.
 *
 * @return array|null
 */
function gstore_api_visualizer_get_manifest() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$manifest_path = gstore_api_visualizer_manifest_path();
	if ( ! file_exists( $manifest_path ) ) {
		$cache = null;
		return $cache;
	}

	$json = file_get_contents( $manifest_path );
	if ( ! $json ) {
		$cache = null;
		return $cache;
	}

	$decoded = json_decode( $json, true );
	$cache   = is_array( $decoded ) ? $decoded : null;
	return $cache;
}

/**
 * Local URL do Vite dev server (pode ser alterado via filtro).
 *
 * @return string
 */
function gstore_api_visualizer_dev_server_url() {
	$default = 'http://localhost:3000';
	$custom  = apply_filters( 'gstore_api_visualizer_dev_server_url', $default );
	return is_string( $custom ) && $custom ? rtrim( $custom, '/' ) : $default;
}

/**
 * Enfileira os assets do app (produção via manifest; dev via Vite).
 */
function gstore_api_visualizer_enqueue_assets() {
	$handle = 'gstore-api-visualizer';

	$manifest = gstore_api_visualizer_get_manifest();

	// Config compartilhada com o app
	$config = array(
		'homeUrl'        => home_url( '/' ),
		'siteUrl'        => site_url( '/' ),
		'restUrl'        => rest_url(),
		'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
		'themeUrl'       => get_stylesheet_directory_uri(),
		// Dica: arquivo de exemplo (copiado para dist pelo Vite build)
		'defaultSpecUrl' => get_theme_file_uri( 'api-visualizer/dist/example-api.json' ),
		'debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
	);

	// Produção (manifest)
	if ( $manifest ) {
		// Entrada principal: preferimos index.html; se não existir, pega o primeiro "isEntry"
		$entry = null;
		if ( isset( $manifest['index.html'] ) ) {
			$entry = $manifest['index.html'];
		} else {
			foreach ( $manifest as $item ) {
				if ( isset( $item['isEntry'] ) && $item['isEntry'] ) {
					$entry = $item;
					break;
				}
			}
		}

		if ( ! $entry || empty( $entry['file'] ) ) {
			return;
		}

		$base_uri = get_theme_file_uri( 'api-visualizer/dist/' );
		$js_uri   = $base_uri . ltrim( $entry['file'], '/' );

		// Registra/enfileira CSS extraído
		if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
			foreach ( $entry['css'] as $i => $css_file ) {
				$css_uri = $base_uri . ltrim( $css_file, '/' );
				wp_enqueue_style( $handle . '-css-' . $i, $css_uri, array(), null );
			}
		}

		// Script principal (module)
		wp_enqueue_script( $handle, $js_uri, array(), null, true );
		wp_script_add_data( $handle, 'type', 'module' );

		// Injeta config antes do módulo
		wp_add_inline_script(
			$handle,
			'window.API_VISUALIZER_CONFIG = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		return;
	}

	// Desenvolvimento (fallback): Vite dev server
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$dev = gstore_api_visualizer_dev_server_url();

		// @vite/client
		wp_enqueue_script( $handle . '-vite-client', $dev . '/@vite/client', array(), null, true );
		wp_script_add_data( $handle . '-vite-client', 'type', 'module' );

		// Entrypoint
		wp_enqueue_script( $handle, $dev . '/src/main.jsx', array(), null, true );
		wp_script_add_data( $handle, 'type', 'module' );

		wp_add_inline_script(
			$handle,
			'window.API_VISUALIZER_CONFIG = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}
}

/**
 * Shortcode: [api_visualizer]
 */
function gstore_api_visualizer_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'class' => '',
		),
		$atts,
		'api_visualizer'
	);

	$class = trim( 'gstore-api-visualizer ' . $atts['class'] );

	// Container onde o React vai montar
	return '<div id="api-visualizer-root" class="' . esc_attr( $class ) . '"></div>';
}
add_shortcode( 'api_visualizer', 'gstore_api_visualizer_shortcode' );

/**
 * Enfileira assets automaticamente quando a página contém o shortcode.
 *
 * Importante: estilos precisam ser enfileirados antes do wp_head, por isso
 * não podemos depender de enfileirar somente quando o shortcode renderiza.
 */
add_action(
	'wp_enqueue_scripts',
	function() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'api_visualizer' ) ) {
			gstore_api_visualizer_enqueue_assets();
		}
	},
	20
);


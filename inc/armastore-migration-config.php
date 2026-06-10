<?php
/**
 * Armastore-specific migration redirect configuration.
 *
 * Keeps legacy SEO redirects out of the shared GStore plugin so other stores
 * only opt in when they explicitly register their own host and maps.
 *
 * @package Gstore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'gstore_migration_allowed_hosts',
	static function ( $hosts ) {
		$hosts   = is_array( $hosts ) ? $hosts : array();
		$hosts[] = 'armastore.com.br';

		return array_values( array_unique( array_filter( $hosts ) ) );
	}
);

add_filter(
	'gstore_migration_auto_product_redirects_enabled',
	static function ( $enabled ) {
		if ( ! gstore_armastore_is_current_host() ) {
			return $enabled;
		}

		return false;
	}
);

add_filter(
	'gstore_migration_static_redirects',
	static function ( $redirects ) {
		$redirects = is_array( $redirects ) ? $redirects : array();
		$host      = function_exists( 'home_url' ) ? wp_parse_url( home_url( '/' ), PHP_URL_HOST ) : '';
		$host      = strtolower( preg_replace( '#^www\.#', '', (string) $host ) );

		if ( 'armastore.com.br' !== $host ) {
			return $redirects;
		}

		return array_merge(
			$redirects,
			array(
				'/38-tpc/pistola-taurus-gx4-carry-calibre-38-tpc-graphene/' => '/produto/pistola-taurus-gx4-carry-calibre-38-tpc-graphene/',
				'/38-tpc-pistolas/pistola-taurus-gx4-carry-calibre-38-tpc-graphene/' => '/produto/pistola-taurus-gx4-carry-calibre-38-tpc-graphene/',
				'/38-tpc/pistola-taurus-tx38tpc-calibre-38-tpc-full-size/' => '/produto/pistola-taurus-tx38tpc-calibre-38-tpc-full-size/',
				'/38-tpc-pistolas/pistola-taurus-tx38tpc-calibre-38-tpc-full-size/' => '/produto/pistola-taurus-tx38tpc-calibre-38-tpc-full-size/',
				'/38-tpc/pistola-gx2-calibre-38-tpc-cafo-black-t-o-r-o/' => '/produto/pistola-gx2-calibre-38-tpc-cafo-black-t-o-r-o/',
				'/12ga-municao/municao-cbc-cal-12ga-ch-7-1-2-f150-c-25-un/' => array(
					'target'         => '/produto/municao-cbc-cal-12ga-ch-7-1-2-f150-c-25-un/',
					'preserve_query' => false,
				),
			)
		);
	}
);

add_filter( 'gstore_migration_noindex_feeds', '__return_true' );

add_filter(
	'gstore_public_surface_robots_disallow_rules',
	static function ( $rules ) {
		$rules = is_array( $rules ) ? $rules : array();

		if ( ! gstore_armastore_is_current_host() ) {
			return $rules;
		}

		return array_values(
			array_filter(
				$rules,
				static function ( $rule ) {
					return ! in_array( trim( (string) $rule ), array( '/feed/', '*/feed/' ), true );
				}
			)
		);
	}
);

if ( ! function_exists( 'gstore_armastore_is_current_host' ) ) {
	function gstore_armastore_is_current_host() {
		$host = function_exists( 'home_url' ) ? wp_parse_url( home_url( '/' ), PHP_URL_HOST ) : '';
		$host = strtolower( preg_replace( '#^www\.#', '', (string) $host ) );

		return 'armastore.com.br' === $host;
	}
}

if ( ! function_exists( 'gstore_armastore_sync_physical_robots_txt' ) ) {
	function gstore_armastore_sync_physical_robots_txt() {
		if ( ! gstore_armastore_is_current_host() || ! defined( 'ABSPATH' ) || ! defined( 'WP_CONTENT_DIR' ) ) {
			return;
		}

		$content = apply_filters( 'robots_txt', "User-agent: *\n", true );
		$content = trim( preg_replace( "/\n{3,}/", "\n\n", (string) $content ) ) . "\n";
		if ( "User-agent: *\n" === $content ) {
			return;
		}

		$targets = apply_filters(
			'gstore_armastore_physical_robots_targets',
			array(
				ABSPATH . 'robots.txt',
				trailingslashit( WP_CONTENT_DIR ) . 'litespeed/robots.txt',
			)
		);

		foreach ( (array) $targets as $target ) {
			$target = (string) $target;
			if ( '' === $target ) {
				continue;
			}

			$dir = dirname( $target );
			if ( ! is_dir( $dir ) && function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $dir );
			}

			if ( is_file( $target ) && hash_equals( sha1( $content ), sha1( (string) file_get_contents( $target ) ) ) ) {
				continue;
			}

			if ( is_dir( $dir ) && is_writable( $dir ) ) {
				file_put_contents( $target, $content, LOCK_EX );
			}
		}
	}
}
add_action( 'init', 'gstore_armastore_sync_physical_robots_txt', 30 );

add_filter(
	'gstore_migration_legacy_category_map',
	static function ( $map ) {
		$map = is_array( $map ) ? $map : array();

		return array_merge(
			$map,
			array(
				'gbbr'                                => '/categoria-produto/airsoft/pistolas-e-revolveres-de-airsoft/',
				'gbb'                                 => '/categoria-produto/airsoft/pistolas-e-revolveres-de-airsoft/',
				'airsoft/armas-de-airsoft/pistolas-e-revolveres-de-airsoft' => '/categoria-produto/airsoft/pistolas-e-revolveres-de-airsoft/',
				'airsoft/armas-de-airsoft/rifles-de-airsoft' => '/categoria-produto/airsoft/rifles-de-airsoft/',
				'airsoft/armas-de-airsoft/sniper-e-dmr-de-airsoft' => '/categoria-produto/airsoft/sniper-e-dmr-de-airsoft/',
				'airsoft/armas-de-airsoft/submetralhadoras-de-airsoft' => '/categoria-produto/airsoft/',
				'airsoft/armas-de-airsoft/suporte-metralhadoras-airsoft' => '/categoria-produto/airsoft/',
				'airsoft/armas-de-airsoft/semi-novo' => '/categoria-produto/airsoft/',
				'airsoft/armas-de-airsoft'           => '/categoria-produto/airsoft/',
				'airsoft/acessorios-para-airsoft/carregadores-e-magazines' => '/categoria-produto/acessorios-para-airsoft/carregadores-e-magazines/',
				'airsoft/acessorios-para-airsoft/suportes-mounts-trilhos' => '/categoria-produto/acessorios/',
				'airsoft/acessorios-para-airsoft'    => '/categoria-produto/acessorios-para-airsoft/',
				'airsoft/baterias-e-carregadores'    => '/categoria-produto/airsoft/baterias-e-carregadores/',
				'airsoft/municoes-bbs-6mm'           => '/categoria-produto/airsoft/municoes-bbs-6mm/',
				'airsoft/pecas-de-customizacao-e-reposicao/gear-box-corpo' => '/categoria-produto/airsoft/cano-hop-up-pistao-cilindro/',
				'airsoft/pecas-de-customizacao-e-reposicao/cano-hop-up-pistao-cilindro' => '/categoria-produto/airsoft/cano-hop-up-pistao-cilindro/',
				'airsoft/pecas-de-customizacao-e-reposicao/supressores-tracers-ponteiras' => '/categoria-produto/acessorios-para-airsoft/diversos-airsoft/',
				'airsoft/pecas-de-customizacao-e-reposicao' => '/categoria-produto/pecas-de-customizacao-e-reposicao/',
				'airsoft/seguranca-e-protecao/luvas' => '/categoria-produto/acessorios/luvas/',
				'airsoft/seguranca-e-protecao/oculos-balaclavas-e-mascaras' => '/categoria-produto/vestuario/oculos/',
				'airsoft/seguranca-e-protecao/oculos' => '/categoria-produto/vestuario/oculos/',
				'airsoft/seguranca-e-protecao/mascaras' => '/categoria-produto/vestuario/oculos/',
				'airsoft/seguranca-e-protecao'       => '/categoria-produto/acessorios/',
				'armas-de-pressao/chumbinhos-e-municoes' => '/categoria-produto/carabinas-de-pressao/chumbinhos-e-municoes/',
				'armas-de-pressao/pistola-de-pressao' => '/categoria-produto/pistola-de-pressao/',
				'armas-de-pressao/carabinas-com-mola-gas-ram' => '/categoria-produto/carabinas-de-pressao/',
				'armas-de-pressao/carabinas-com-mola-helicoidal' => '/categoria-produto/carabinas-de-pressao/',
				'armas-de-pressao/carabinas-de-pressao' => '/categoria-produto/carabinas-de-pressao/',
				'armas-de-pressao/carabinas-pcp'      => '/categoria-produto/carabinas-pcp/',
				'armas-de-pressao'                    => '/catalogo/',
				'pistolas-e-revolveres-de-airsoft'    => '/categoria-produto/airsoft/pistolas-e-revolveres-de-airsoft/',
				'rifles-de-airsoft'                   => '/categoria-produto/airsoft/rifles-de-airsoft/',
				'sniper-e-dmr-de-airsoft'             => '/categoria-produto/airsoft/sniper-e-dmr-de-airsoft/',
				'municoes-bbs-6mm'                    => '/categoria-produto/airsoft/municoes-bbs-6mm/',
				'carregadores-e-magazines'            => '/categoria-produto/acessorios-para-airsoft/carregadores-e-magazines/',
				'cano-hop-up-pistao-cilindro'         => '/categoria-produto/airsoft/cano-hop-up-pistao-cilindro/',
				'diversos-airsoft'                    => '/categoria-produto/acessorios-para-airsoft/diversos-airsoft/',
				'gear-box-corpo'                      => '/categoria-produto/airsoft/cano-hop-up-pistao-cilindro/',
				'suportes-mounts-trilhos'             => '/categoria-produto/acessorios/',
				'supressores-tracers-ponteiras'       => '/categoria-produto/acessorios-para-airsoft/diversos-airsoft/',
				'seguranca-e-protecao/luvas'          => '/categoria-produto/acessorios/luvas/',
				'seguranca-e-protecao/oculos'         => '/categoria-produto/vestuario/oculos/',
				'seguranca-e-protecao/mascaras'       => '/categoria-produto/vestuario/oculos/',
				'seguranca-e-protecao'                => '/categoria-produto/acessorios/',
				'oculos'                              => '/categoria-produto/vestuario/oculos/',
				'luvas'                               => '/categoria-produto/acessorios/luvas/',
			)
		);
	}
);

add_filter(
	'gstore_migration_force_gone_slugs',
	static function ( $rules ) {
		$rules = is_array( $rules ) ? $rules : array();

		$rules[] = array( 'pattern' => '#/(?:produtos?|sem-categoria)/teste(?:-\d+)?(?:/|$)#' );
		$rules[] = array( 'pattern' => '#/(?:[^/]+/)*produto-teste-preco(?:-\d+)?(?:/|$)#' );

		return $rules;
	}
);

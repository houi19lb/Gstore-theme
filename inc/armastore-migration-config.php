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
	'gstore_migration_legacy_category_map',
	static function ( $map ) {
		$map = is_array( $map ) ? $map : array();

		return array_merge(
			$map,
			array(
				'gbbr'                                => '/categoria-produto/airsoft/pistolas-e-revolveres-de-airsoft/',
				'gbb'                                 => '/categoria-produto/airsoft/pistolas-e-revolveres-de-airsoft/',
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

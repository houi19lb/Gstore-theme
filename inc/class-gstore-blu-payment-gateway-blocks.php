<?php
/**
 * Integração do Gateway Blu com WooCommerce Blocks.
 *
 * @package Gstore
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

// Verifica se a classe AbstractPaymentMethodType está disponível antes de tentar extender
if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
	// Sai silenciosamente se a classe não estiver disponível
	return;
}

/**
 * Classe de integração para Blocos.
 */
final class Gstore_Blu_Payment_Gateway_Blocks extends AbstractPaymentMethodType {

	/**
	 * Nome do gateway (deve bater com o ID da classe principal).
	 *
	 * @var string
	 */
	protected $name = 'blu_checkout';

	public function __construct() {

		$this->initialize();
	}

	/**
	 * Inicializa a integração.
	 */
	public function initialize() {

		$this->settings = get_option( 'woocommerce_blu_checkout_settings', array() );
	}

	/**
	 * Verifica se o gateway está ativo.
	 *
	 * @return bool
	 */
	public function is_active() {

		// Se definido via constante, está sempre ativo
		if ( defined( 'BLU_API_TOKEN' ) ) {

			return true;
		}
		$active = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];

		return $active;
	}

	/**
	 * Registra os scripts do gateway.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$asset_path   = get_theme_file_path( 'assets/js/blu-checkout-block.asset.php' );
		$version      = '1.0.0';
		$dependencies = array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' );

		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
		}

		wp_register_script(
			'gstore-blu-checkout-block',
			get_theme_file_uri( 'assets/js/blu-checkout-block.js' ),
			$dependencies,
			$version,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'gstore-blu-checkout-block', 'gstore', get_theme_file_path( 'languages' ) );
		}

		return array( 'gstore-blu-checkout-block' );
	}

	/**
	 * Passa dados para o script frontend.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title', 'Pagamento via Link Blu' ),
			'description' => $this->get_setting( 'description', 'Geraremos um link Blu para você finalizar o pagamento com segurança.' ),
			'supports'    => array_filter( $this->get_supported_features(), array( $this, 'filter_supported_features' ) ),
		);
	}

	/**
	 * Filtra features suportadas.
	 *
	 * @param string $feature Feature.
	 * @return bool
	 */
	private function filter_supported_features( $feature ) {
		// Adicione features suportadas aqui se necessário
		return true;
	}
}

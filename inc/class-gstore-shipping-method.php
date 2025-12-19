<?php
/**
 * Método de envio customizado Gstore.
 *
 * @package Gstore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classe responsável pelo método de envio customizado baseado em peso tático e região.
 */
class Gstore_Shipping_Method extends WC_Shipping_Method {

	/**
	 * ID do método de envio.
	 */
	const METHOD_ID = 'gstore_custom_shipping';

	/**
	 * Construtor.
	 *
	 * @param int $instance_id ID da instância do método.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = self::METHOD_ID;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Frete Gstore', 'gstore' );
		$this->method_description = __( 'Calcula o frete baseado em peso tático (100kg por arma) e região de entrega.', 'gstore' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init();
	}

	/**
	 * Inicializa o método de envio.
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title', __( 'Frete Gstore', 'gstore' ) );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Define os campos de configuração.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Ativar/Desativar', 'gstore' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar este método de envio', 'gstore' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Título', 'gstore' ),
				'type'        => 'text',
				'description' => __( 'Título exibido ao cliente durante o checkout.', 'gstore' ),
				'default'     => __( 'Frete Gstore', 'gstore' ),
			),
		);
	}

	/**
	 * Calcula o custo do frete.
	 *
	 * @param array $package Pacote de produtos.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		// Verifica se há produtos no pacote
		if ( empty( $package['contents'] ) ) {
			return;
		}

		// Calcula peso total usando peso tático
		$total_weight = $this->calculate_tactical_weight( $package['contents'] );

		// Identifica a região pelo CEP ou Estado
		$region = $this->get_shipping_region( $package );

		// Obtém o valor do frete
		$shipping_cost = $this->get_shipping_cost( $total_weight, $region );

		// Se não encontrou valor, não exibe o método
		if ( $shipping_cost === false ) {
			return;
		}

		// Adiciona a taxa de envio
		$rate = array(
			'id'      => $this->get_rate_id(),
			'label'   => $this->title,
			'cost'    => $shipping_cost,
			'package' => $package,
		);

		$this->add_rate( $rate );
	}

	/**
	 * Calcula o peso tático do carrinho.
	 *
	 * @param array $contents Conteúdo do carrinho.
	 * @return float Peso total em kg.
	 */
	protected function calculate_tactical_weight( $contents ) {
		$total_weight = 0;

		foreach ( $contents as $item ) {
			if ( ! isset( $item['data'] ) || ! is_a( $item['data'], 'WC_Product' ) ) {
				continue;
			}

			$product = $item['data'];
			$quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;

			// Verifica se é uma arma (peso tático de 100kg)
			if ( $this->is_weapon( $product ) ) {
				$total_weight += 100 * $quantity;
			} else {
				// Munição ou outros produtos: usa peso real
				$product_weight = $product->get_weight();
				if ( $product_weight ) {
					$total_weight += floatval( $product_weight ) * $quantity;
				}
			}
		}

		return $total_weight;
	}

	/**
	 * Verifica se um produto é uma arma.
	 *
	 * @param WC_Product $product Produto.
	 * @return bool
	 */
	protected function is_weapon( $product ) {
		// Verifica se o produto tem peso >= 100kg (peso tático configurado)
		$weight = $product->get_weight();
		if ( $weight && floatval( $weight ) >= 100 ) {
			return true;
		}

		// Verifica por categoria
		$categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
		if ( ! is_array( $categories ) || is_wp_error( $categories ) ) {
			$categories = array();
		}

		// Categorias de armas conhecidas
		$weapon_categories = array(
			'armas',
			'weapons',
			'armas-longas',
			'armas-curtas',
		);

		foreach ( $weapon_categories as $weapon_cat ) {
			if ( in_array( $weapon_cat, $categories, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Identifica a região de envio baseado no CEP ou Estado.
	 *
	 * @param array $package Pacote de produtos.
	 * @return string Região: 'sul', 'resto_brasil', 'rio_janeiro'.
	 */
	protected function get_shipping_region( $package ) {
		$postcode = '';
		$state = '';

		// Tenta obter do endereço de entrega
		if ( isset( $package['destination']['postcode'] ) ) {
			$postcode = preg_replace( '/[^0-9]/', '', $package['destination']['postcode'] );
		}
		if ( isset( $package['destination']['state'] ) ) {
			$state = strtoupper( $package['destination']['state'] );
		}

		// Se não tem estado, tenta obter do CEP
		if ( empty( $state ) && ! empty( $postcode ) ) {
			$state = $this->get_state_from_postcode( $postcode );
		}

		// Identifica região pelo estado
		return gstore_get_shipping_region( $state, $postcode );
	}

	/**
	 * Obtém o estado a partir do CEP (primeiros dígitos).
	 *
	 * @param string $postcode CEP.
	 * @return string Estado (UF).
	 */
	protected function get_state_from_postcode( $postcode ) {
		// Primeiros dígitos do CEP indicam região
		$first_digits = substr( $postcode, 0, 2 );

		// Mapeamento aproximado (pode ser refinado)
		$cep_to_state = array(
			'20' => 'RJ', // Rio de Janeiro
			'21' => 'RJ',
			'22' => 'RJ',
			'23' => 'RJ',
			'90' => 'RS', // Rio Grande do Sul
			'91' => 'RS',
			'92' => 'RS',
			'93' => 'RS',
			'94' => 'RS',
			'95' => 'RS',
			'96' => 'RS',
			'80' => 'PR', // Paraná
			'81' => 'PR',
			'82' => 'PR',
			'83' => 'PR',
			'88' => 'SC', // Santa Catarina
			'89' => 'SC',
		);

		return isset( $cep_to_state[ $first_digits ] ) ? $cep_to_state[ $first_digits ] : '';
	}

	/**
	 * Obtém o custo do frete baseado no peso e região.
	 *
	 * @param float  $weight Peso total em kg.
	 * @param string $region Região: 'sul', 'resto_brasil', 'rio_janeiro'.
	 * @return float|false Valor do frete ou false se não encontrado.
	 */
	protected function get_shipping_cost( $weight, $region ) {
		// Carrega os valores do JSON
		$rates = $this->load_shipping_rates();

		if ( ! $rates || ! isset( $rates['regions'][ $region ] ) ) {
			return false;
		}

		$region_rates = $rates['regions'][ $region ];

		// Determina a faixa de peso e retorna o valor correspondente
		if ( $weight >= 0 && $weight < 100 ) {
			// Faixa 1: Só Munição (0kg a 99kg)
			return isset( $region_rates['only_ammunition'] ) ? floatval( $region_rates['only_ammunition'] ) : false;
		} elseif ( $weight >= 100 && $weight <= 100.1 ) {
			// Faixa 2: Só 1 Arma (100kg a 100,1kg)
			return isset( $region_rates['one_weapon'] ) ? floatval( $region_rates['one_weapon'] ) : false;
		} elseif ( $weight >= 100.2 && $weight < 200 ) {
			// Faixa 3: 1 Arma + Munição (100,2kg a 199kg)
			return isset( $region_rates['combo_one_weapon'] ) ? floatval( $region_rates['combo_one_weapon'] ) : false;
		} elseif ( $weight >= 200 && $weight <= 200.1 ) {
			// Faixa 4: 2 Armas (200kg a 200,1kg)
			return isset( $region_rates['two_weapons'] ) ? floatval( $region_rates['two_weapons'] ) : false;
		} elseif ( $weight >= 200.2 ) {
			// Faixa 5: 2 Armas + Munição (+200,2kg)
			return isset( $region_rates['combo_two_weapons'] ) ? floatval( $region_rates['combo_two_weapons'] ) : false;
		}

		return false;
	}

	/**
	 * Carrega os valores de frete do arquivo JSON.
	 *
	 * @return array|false Dados do JSON ou false em caso de erro.
	 */
	protected function load_shipping_rates() {
		$json_file = get_theme_file_path( 'assets/json/shipping-rates.json' );

		if ( ! file_exists( $json_file ) ) {
			return false;
		}

		$json_content = file_get_contents( $json_file );
		if ( $json_content === false ) {
			return false;
		}

		$data = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		return $data;
	}

	/**
	 * Verifica se um produto é uma arma (método público para uso externo).
	 *
	 * @param WC_Product $product Produto.
	 * @return bool
	 */
	public function is_weapon_product( $product ) {
		return $this->is_weapon( $product );
	}

	/**
	 * Calcula o peso tático de um array de produtos (método público para uso externo).
	 *
	 * @param array $contents Conteúdo do carrinho ou array de produtos.
	 * @return float Peso total em kg.
	 */
	public function calculate_tactical_weight_public( $contents ) {
		return $this->calculate_tactical_weight( $contents );
	}

	/**
	 * Obtém o custo do frete baseado no peso e região (método público para uso externo).
	 *
	 * @param float  $weight Peso total em kg.
	 * @param string $region Região: 'sul', 'resto_brasil', 'rio_janeiro'.
	 * @return float|false Valor do frete ou false se não encontrado.
	 */
	public function get_shipping_cost_public( $weight, $region ) {
		return $this->get_shipping_cost( $weight, $region );
	}
}


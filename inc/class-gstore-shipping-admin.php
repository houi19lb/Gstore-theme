<?php
/**
 * Classe para gerenciar a página de configuração de frete no admin.
 *
 * @package Gstore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classe Gstore_Shipping_Admin
 */
class Gstore_Shipping_Admin {

	/**
	 * Caminho do arquivo JSON de configuração.
	 *
	 * @var string
	 */
	private $json_file;

	/**
	 * Construtor.
	 */
	public function __construct() {
		$this->json_file = get_theme_file_path( 'assets/json/shipping-rates.json' );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submit' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Garante que o arquivo JSON existe com valores padrão
		$this->ensure_json_file_exists();
	}

	/**
	 * Garante que o arquivo JSON existe, criando com valores padrão se necessário.
	 */
	private function ensure_json_file_exists() {
		if ( ! file_exists( $this->json_file ) ) {
			$dir = dirname( $this->json_file );
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			$this->save_json( $this->get_default_data() );
		}
	}

	/**
	 * Adiciona menu no admin.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'gstore-setup',
			__( 'Configuração de Frete', 'gstore' ),
			__( 'Configuração de Frete', 'gstore' ),
			'manage_options',
			'gstore-shipping-config',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Processa o envio do formulário.
	 */
	public function handle_form_submit() {
		if ( ! isset( $_POST['gstore_shipping_save'] ) || ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Você não tem permissão para fazer isso.', 'gstore' ) );
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'gstore_shipping_save' ) ) {
			wp_die( __( 'Falha na verificação de segurança.', 'gstore' ) );
		}

		// Processa exportação
		if ( isset( $_POST['gstore_shipping_export'] ) ) {
			$this->export_json();
			return;
		}

		// Processa importação
		if ( isset( $_FILES['gstore_shipping_import_file'] ) && ! empty( $_FILES['gstore_shipping_import_file']['tmp_name'] ) ) {
			$this->import_json();
			return;
		}

		// Processa salvamento
		$regions = array( 'sul', 'resto_brasil', 'rio_janeiro' );
		$fields = array( 'only_ammunition', 'one_weapon', 'combo_one_weapon', 'two_weapons', 'combo_two_weapons' );

		$data = array( 'regions' => array() );

		foreach ( $regions as $region ) {
			$data['regions'][ $region ] = array();
			foreach ( $fields as $field ) {
				$key = "shipping_{$region}_{$field}";
				$value = isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '';
				$value = floatval( $value );
				$data['regions'][ $region ][ $field ] = $value;
			}
		}

		// Salva no JSON
		$this->save_json( $data );

		add_settings_error(
			'gstore_shipping_messages',
			'gstore_shipping_saved',
			__( 'Configurações de frete salvas com sucesso!', 'gstore' ),
			'success'
		);
	}

	/**
	 * Salva dados no arquivo JSON.
	 *
	 * @param array $data Dados para salvar.
	 * @return bool
	 */
	private function save_json( $data ) {
		// Garante que o diretório existe
		$dir = dirname( $this->json_file );
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		return file_put_contents( $this->json_file, $json ) !== false;
	}

	/**
	 * Carrega dados do arquivo JSON.
	 *
	 * @return array
	 */
	private function load_json() {
		if ( ! file_exists( $this->json_file ) ) {
			return $this->get_default_data();
		}

		$content = file_get_contents( $this->json_file );
		if ( $content === false ) {
			return $this->get_default_data();
		}

		$data = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return $this->get_default_data();
		}

		return $data;
	}

	/**
	 * Retorna dados padrão.
	 *
	 * @return array
	 */
	private function get_default_data() {
		return array(
			'regions' => array(
				'sul' => array(
					'only_ammunition'    => 200,
					'one_weapon'         => 330,
					'combo_one_weapon'   => 530,
					'two_weapons'        => 660,
					'combo_two_weapons'  => 860,
				),
				'resto_brasil' => array(
					'only_ammunition'    => 250,
					'one_weapon'         => 370,
					'combo_one_weapon'   => 620,
					'two_weapons'        => 740,
					'combo_two_weapons'  => 990,
				),
				'rio_janeiro' => array(
					'only_ammunition'    => 350,
					'one_weapon'         => 450,
					'combo_one_weapon'   => 800,
					'two_weapons'        => 900,
					'combo_two_weapons'  => 1250,
				),
			),
		);
	}

	/**
	 * Exporta o JSON para download.
	 */
	private function export_json() {
		$data = $this->load_json();
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="shipping-rates-' . date( 'Y-m-d' ) . '.json"' );
		header( 'Content-Length: ' . strlen( $json ) );

		echo $json;
		exit;
	}

	/**
	 * Importa JSON de um arquivo.
	 */
	private function import_json() {
		$file = $_FILES['gstore_shipping_import_file'];

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			add_settings_error(
				'gstore_shipping_messages',
				'gstore_shipping_import_error',
				__( 'Erro ao fazer upload do arquivo.', 'gstore' ),
				'error'
			);
			return;
		}

		$content = file_get_contents( $file['tmp_name'] );
		$data = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_settings_error(
				'gstore_shipping_messages',
				'gstore_shipping_import_error',
				__( 'Arquivo JSON inválido.', 'gstore' ),
				'error'
			);
			return;
		}

		// Valida estrutura básica
		if ( ! isset( $data['regions'] ) ) {
			add_settings_error(
				'gstore_shipping_messages',
				'gstore_shipping_import_error',
				__( 'Estrutura do arquivo JSON inválida.', 'gstore' ),
				'error'
			);
			return;
		}

		$this->save_json( $data );

		add_settings_error(
			'gstore_shipping_messages',
			'gstore_shipping_imported',
			__( 'Configurações importadas com sucesso!', 'gstore' ),
			'success'
		);
	}

	/**
	 * Enfileira scripts e estilos do admin.
	 *
	 * @param string $hook Hook da página atual.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( $hook !== 'gstore-setup_page_gstore-shipping-config' ) {
			return;
		}

		wp_add_inline_style( 'common', '
			.gstore-shipping-table {
				width: 100%;
				border-collapse: collapse;
				margin: 20px 0;
			}
			.gstore-shipping-table th,
			.gstore-shipping-table td {
				padding: 12px;
				border: 1px solid #c3c4c7;
				text-align: left;
			}
			.gstore-shipping-table th {
				background: #f0f0f1;
				font-weight: 600;
			}
			.gstore-shipping-table input[type="number"] {
				width: 100%;
				padding: 6px;
			}
			.gstore-shipping-actions {
				margin-top: 20px;
				display: flex;
				gap: 10px;
			}
		' );
	}

	/**
	 * Renderiza a página de configurações.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		settings_errors( 'gstore_shipping_messages' );

		$data = $this->load_json();
		$regions = array(
			'sul' => array(
				'label' => __( 'SUL (RS/SC/PR)', 'gstore' ),
				'description' => __( 'Rio Grande do Sul, Santa Catarina e Paraná', 'gstore' ),
			),
			'resto_brasil' => array(
				'label' => __( 'RESTO DO BRASIL', 'gstore' ),
				'description' => __( 'Todos os outros estados', 'gstore' ),
			),
			'rio_janeiro' => array(
				'label' => __( 'RIO DE JANEIRO', 'gstore' ),
				'description' => __( 'Estado do Rio de Janeiro', 'gstore' ),
			),
		);

		$fields = array(
			'only_ammunition'   => __( 'Só Munição', 'gstore' ),
			'one_weapon'        => __( '1 Arma', 'gstore' ),
			'combo_one_weapon'  => __( 'Combo (1 Arma + Mun)', 'gstore' ),
			'two_weapons'       => __( '2 Armas', 'gstore' ),
			'combo_two_weapons' => __( 'Combo (2 Armas + Mun)', 'gstore' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php _e( 'Configure os valores de frete para cada região. Os valores são baseados na estratégia de pesos táticos (100kg por arma).', 'gstore' ); ?></p>

			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'gstore_shipping_save' ); ?>

				<?php foreach ( $regions as $region_key => $region_info ) : ?>
					<h2><?php echo esc_html( $region_info['label'] ); ?></h2>
					<p class="description"><?php echo esc_html( $region_info['description'] ); ?></p>

					<table class="form-table gstore-shipping-table">
						<thead>
							<tr>
								<th><?php _e( 'Cenário', 'gstore' ); ?></th>
								<th><?php _e( 'Valor (R$)', 'gstore' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $fields as $field_key => $field_label ) : ?>
								<tr>
									<td>
										<label for="shipping_<?php echo esc_attr( $region_key ); ?>_<?php echo esc_attr( $field_key ); ?>">
											<?php echo esc_html( $field_label ); ?>
										</label>
									</td>
									<td>
										<input
											type="number"
											id="shipping_<?php echo esc_attr( $region_key ); ?>_<?php echo esc_attr( $field_key ); ?>"
											name="shipping_<?php echo esc_attr( $region_key ); ?>_<?php echo esc_attr( $field_key ); ?>"
											value="<?php echo esc_attr( isset( $data['regions'][ $region_key ][ $field_key ] ) ? $data['regions'][ $region_key ][ $field_key ] : 0 ); ?>"
											step="0.01"
											min="0"
											required
										/>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>

				<div class="gstore-shipping-actions">
					<?php submit_button( __( 'Salvar Configurações', 'gstore' ), 'primary', 'gstore_shipping_save', false ); ?>
					<?php submit_button( __( 'Exportar JSON', 'gstore' ), 'secondary', 'gstore_shipping_export', false ); ?>
				</div>

				<h2><?php _e( 'Importar Configurações', 'gstore' ); ?></h2>
				<p class="description"><?php _e( 'Faça upload de um arquivo JSON para importar configurações de frete.', 'gstore' ); ?></p>
				<p>
					<input type="file" name="gstore_shipping_import_file" accept=".json" />
					<?php submit_button( __( 'Importar JSON', 'gstore' ), 'secondary', 'gstore_shipping_import', false ); ?>
				</p>
			</form>

			<div class="card" style="margin-top: 20px;">
				<h2><?php _e( 'Como funciona', 'gstore' ); ?></h2>
				<p><?php _e( 'O sistema calcula o frete baseado no peso total do carrinho usando a estratégia de pesos táticos:', 'gstore' ); ?></p>
				<ul>
					<li><strong><?php _e( 'Faixa 1 (0kg a 99kg):', 'gstore' ); ?></strong> <?php _e( 'Só Munição', 'gstore' ); ?></li>
					<li><strong><?php _e( 'Faixa 2 (100kg a 100,1kg):', 'gstore' ); ?></strong> <?php _e( 'Só 1 Arma', 'gstore' ); ?></li>
					<li><strong><?php _e( 'Faixa 3 (100,2kg a 199kg):', 'gstore' ); ?></strong> <?php _e( '1 Arma + Munição', 'gstore' ); ?></li>
					<li><strong><?php _e( 'Faixa 4 (200kg a 200,1kg):', 'gstore' ); ?></strong> <?php _e( '2 Armas', 'gstore' ); ?></li>
					<li><strong><?php _e( 'Faixa 5 (+200,2kg):', 'gstore' ); ?></strong> <?php _e( '2 Armas + Munição', 'gstore' ); ?></li>
				</ul>
				<p><?php _e( '<strong>Importante:</strong> As armas devem ser cadastradas com peso tático de 100.000 kg para funcionar corretamente.', 'gstore' ); ?></p>
			</div>
		</div>
		<?php
	}
}


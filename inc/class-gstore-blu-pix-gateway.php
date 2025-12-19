<?php
/**
 * Gateway de pagamento via Pix Blu.
 *
 * @package Gstore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classe responsável por integrar a Blu ao WooCommerce via Pix.
 */
class Gstore_Blu_Pix_Gateway extends WC_Payment_Gateway {

	const GATEWAY_ID = 'blu_pix';

	const META_TRANSACTION_TOKEN = '_gstore_blu_pix_transaction_token';
	const META_QR_CODE_BASE64    = '_gstore_blu_pix_qr_code_base64';
	const META_EMV               = '_gstore_blu_pix_emv';
	const META_STATUS            = '_gstore_blu_pix_status';
	const META_EXPIRES_AT        = '_gstore_blu_pix_expires_at';
	const META_MOVEMENT_ID       = '_gstore_blu_pix_movement_id';
	const META_LAST_PAYLOAD      = '_gstore_blu_pix_last_payload';

	/**
	 * Token fornecido pela Blu.
	 *
	 * @var string
	 */
	protected $api_token = '';

	/**
	 * Ambiente selecionado (homolog ou produção).
	 *
	 * @var string
	 */
	protected $environment = 'homolog';

	/**
	 * Dias de expiração padrão do Pix.
	 *
	 * @var int
	 */
	protected $expiration_days = 1;

	/**
	 * Descrição externa do Pix.
	 *
	 * @var string
	 */
	protected $description_external = '';

	/**
	 * Descrição interna do Pix.
	 *
	 * @var string
	 */
	protected $description_internal = '';

	/**
	 * Ativa logs no WooCommerce.
	 *
	 * @var bool
	 */
	protected $debug_logging = false;

	/**
	 * Token para validar o webhook recebido da Blu.
	 *
	 * @var string
	 */
	protected $webhook_secret = '';

	/**
	 * Construtor.
	 */
	public function __construct() {

		$this->id                 = self::GATEWAY_ID;
		$this->icon               = '';
		$this->method_title       = __( 'Pix Blu', 'gstore' );
		$this->method_description = __( 'Permite pagamento via Pix com QR Code e código copia e cola.', 'gstore' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled                = $this->get_option( 'enabled', 'no' );
		$this->title                  = $this->get_option( 'title', __( 'Pix', 'gstore' ) );
		$this->description            = $this->get_option( 'description', __( 'Pague via Pix com QR Code ou código copia e cola.', 'gstore' ) );
		
		// Configurações do admin apenas
		$this->api_token            = $this->get_option( 'api_token', '' );
		$this->environment          = $this->get_option( 'environment', 'homolog' );
		$this->expiration_days      = (int) $this->get_option( 'expiration_days', 1 );
		$this->description_external = $this->get_option( 'description_external', '' );
		$this->description_internal = $this->get_option( 'description_internal', '' );
		$this->webhook_secret       = $this->get_option( 'webhook_secret', '' );
		$this->debug_logging        = 'yes' === $this->get_option( 'debug_logging', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'render_thankyou_instructions' ) );
		add_action( 'woocommerce_view_order', array( $this, 'render_account_order_instructions' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 4 );
		// Hook adicional para blocos do WooCommerce (página de confirmação de pedido via blocos)
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_blocks_order_confirmation' ) );
	}

	/**
	 * Define os campos de configuração no painel.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                => array(
				'title'   => __( 'Ativar/Desativar', 'gstore' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar pagamento via Pix Blu', 'gstore' ),
				'default' => 'no',
			),
			'title'                  => array(
				'title'       => __( 'Título', 'gstore' ),
				'type'        => 'text',
				'description' => __( 'Nome exibido ao cliente durante o checkout.', 'gstore' ),
				'default'     => __( 'Pix', 'gstore' ),
			),
			'description'            => array(
				'title'       => __( 'Descrição', 'gstore' ),
				'type'        => 'textarea',
				'description' => __( 'Texto mostrado ao cliente abaixo do nome do método.', 'gstore' ),
				'default'     => __( 'Pague via Pix com QR Code ou código copia e cola.', 'gstore' ),
			),
			'api_token'              => array(
				'title'       => __( 'Token da Blu', 'gstore' ),
				'type'        => 'password',
				'description' => __( 'Token informado pelo seu Executivo Blu. Informe sem o prefixo "Bearer".', 'gstore' ),
				'default'     => '',
				'required'    => true,
			),
			'environment'            => array(
				'title'       => __( 'Ambiente', 'gstore' ),
				'type'        => 'select',
				'description' => __( 'Escolha se as requisições serão enviadas para Homologação ou Produção.', 'gstore' ),
				'options'     => array(
					'homolog'    => __( 'Homologação (api-hlg.blu.com.br)', 'gstore' ),
					'production' => __( 'Produção (api.blu.com.br)', 'gstore' ),
				),
				'default'     => 'homolog',
			),
			'expiration_days'        => array(
				'title'       => __( 'Dias de expiração', 'gstore' ),
				'type'        => 'number',
				'description' => __( 'Número de dias até o Pix expirar. Padrão: 1 dia.', 'gstore' ),
				'default'     => 1,
				'custom_attributes' => array(
					'min' => 1,
					'max' => 365,
					'step' => 1,
				),
			),
			'description_external'   => array(
				'title'       => __( 'Descrição externa', 'gstore' ),
				'type'        => 'text',
				'description' => __( 'Descrição exibida para o cliente no momento do pagamento. Máximo de 25 caracteres. Se vazio, será gerada automaticamente (truncada se necessário).', 'gstore' ),
				'default'     => '',
				'custom_attributes' => array(
					'maxlength' => 25,
				),
			),
			'description_internal'   => array(
				'title'       => __( 'Descrição interna', 'gstore' ),
				'type'        => 'text',
				'description' => __( 'Descrição interna para controle. Máximo de 12 caracteres. Se vazio, será gerada automaticamente (truncada se necessário).', 'gstore' ),
				'default'     => '',
				'custom_attributes' => array(
					'maxlength' => 12,
				),
			),
			'webhook_secret'         => array(
				'title'       => __( 'Token do webhook', 'gstore' ),
				'type'        => 'password',
				'description' => __( 'Opcional. Se preenchido, a Blu deve enviar este valor no header X-Gstore-Blu-Webhook.', 'gstore' ),
				'default'     => '',
			),
			'debug_logging'          => array(
				'title'   => __( 'Log de depuração', 'gstore' ),
				'type'    => 'checkbox',
				'label'   => __( 'Registrar requisições/respostas no log do WooCommerce', 'gstore' ),
				'default' => 'no',
			),
		);
	}

	/**
	 * Verifica se o gateway está disponível.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Verifica se há token configurado
		if ( ! empty( $this->api_token ) ) {
			return parent::is_available();
		}
		
		return false;
	}

	/**
	 * Processa o pagamento e cria o Pix na Blu.
	 *
	 * @param int $order_id ID do pedido.
	 * @return array|null
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Não foi possível localizar o pedido.', 'gstore' ), 'error' );
			return null;
		}

		if ( empty( $this->api_token ) ) {
			$order->add_order_note( __( 'Token Blu não configurado. Não foi possível criar o Pix.', 'gstore' ) );
			wc_add_notice( __( 'Método de pagamento indisponível no momento. Entre em contato com o suporte.', 'gstore' ), 'error' );
			return null;
		}

		$response = $this->create_pix_charge( $order );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Erro ao criar Pix Blu', array( 'error' => $response->get_error_message() ) );
			wc_add_notice( $response->get_error_message(), 'error' );
			return null;
		}

		$this->store_pix_metadata( $order, $response );

		// Reduz estoque
		wc_reduce_stock_levels( $order_id );
		
		// Limpa carrinho
		WC()->cart->empty_cart();

		// Define status como pendente (aguardando pagamento)
		$order->update_status( 'pending', __( 'Aguardando pagamento via Pix.', 'gstore' ) );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Mostra instruções na página de obrigado.
	 *
	 * @param int $order_id Pedido.
	 * @return void
	 */
	public function render_thankyou_instructions( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$this->output_pix_instructions( $order );
	}

	/**
	 * Mostra instruções na página "Meus pedidos".
	 *
	 * @param int $order_id Pedido.
	 * @return void
	 */
	public function render_account_order_instructions( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$this->output_pix_instructions( $order );
	}

	/**
	 * Mostra instruções na página de confirmação de pedido via Blocos WooCommerce.
	 *
	 * @param WC_Order $order Pedido.
	 * @return void
	 */
	public function render_blocks_order_confirmation( $order ) {
		if ( ! $order instanceof WC_Order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$this->output_pix_instructions( $order );
	}

	/**
	 * Adiciona instruções no e-mail enviado ao cliente.
	 *
	 * @param WC_Order $order          Pedido.
	 * @param bool     $sent_to_admin  Se é e-mail do admin.
	 * @param bool     $plain_text     Se é texto puro.
	 * @param WC_Email $email          Objeto de e-mail.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $order instanceof WC_Order || $sent_to_admin ) {
			return;
		}

		if ( $order->get_payment_method() !== $this->id || ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
			return;
		}

		$emv = $order->get_meta( self::META_EMV );
		$qr_code = $order->get_meta( self::META_QR_CODE_BASE64 );

		if ( empty( $emv ) && empty( $qr_code ) ) {
			return;
		}

		$message = __( 'Finalize o pagamento via Pix usando o QR Code ou código copia e cola disponível na página do pedido.', 'gstore' );

		if ( $plain_text ) {
			echo "\n" . $message . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $emv ) {
				echo "\n" . __( 'Código Pix:', 'gstore' ) . "\n" . $emv . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return;
		}

		echo '<p>' . esc_html( $message ) . '</p>';
		if ( $emv ) {
			echo '<p><strong>' . esc_html__( 'Código Pix:', 'gstore' ) . '</strong><br><code style="word-break: break-all;">' . esc_html( $emv ) . '</code></p>';
		}
	}

	/**
	 * Cria a cobrança Pix via API.
	 *
	 * @param WC_Order $order Pedido.
	 * @return array|\WP_Error
	 */
	protected function create_pix_charge( WC_Order $order ) {
		$payload = $this->build_payload_from_order( $order );
		$this->log_debug( 'Payload enviado à Blu (Pix)', $payload );

		$response = $this->remote_request( 'POST', '', $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Se a resposta contém apenas transaction_token, precisa consultar para obter QR Code
		if ( isset( $response['transaction_token'] ) && ! isset( $response['qr_code_base64'] ) ) {
			$consult_response = $this->consult_pix( $response['transaction_token'] );
			if ( ! is_wp_error( $consult_response ) ) {
				$response = array_merge( $response, $consult_response );
			}
		}

		return $response;
	}

	/**
	 * Consulta o Pix por transaction_token.
	 *
	 * @param string $transaction_token Token retornado na criação.
	 * @return array|\WP_Error
	 */
	public function consult_pix( $transaction_token ) {
		if ( empty( $transaction_token ) ) {
			return new WP_Error( 'gstore_blu_pix_missing_token', __( 'Token do Pix não informado.', 'gstore' ) );
		}

		return $this->remote_request( 'GET', '/' . trim( $transaction_token ) );
	}

	/**
	 * Realiza a chamada HTTP para a Blu.
	 *
	 * @param string     $method Método HTTP.
	 * @param string     $path   Caminho adicional.
	 * @param array|null $body   Corpo da requisição.
	 * @return array|\WP_Error
	 */
	protected function remote_request( $method, $path = '', $body = null ) {
		$url = untrailingslashit( $this->get_base_endpoint() ) . $path;

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => $this->get_default_headers(),
			'timeout' => 20,
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gstore_blu_pix_http_error',
				sprintf( __( 'Erro de comunicação com a Blu: %s', 'gstore' ), $response->get_error_message() )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->log_debug(
			sprintf( 'Resposta Blu Pix (%s %s)', $method, $path ),
			array(
				'status_code' => $code,
				'body'        => $data,
			)
		);

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['message'] ) ? $data['message'] : __( 'A Blu retornou um erro inesperado.', 'gstore' );

			return new WP_Error(
				'gstore_blu_pix_request_failed',
				sprintf( __( 'Blu retornou %1$s: %2$s', 'gstore' ), $code, $message ),
				array(
					'status_code' => $code,
					'body'        => $data,
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'gstore_blu_pix_invalid_response', __( 'Resposta inválida da Blu.', 'gstore' ) );
		}

		return $data;
	}

	/**
	 * Define cabeçalhos padrão.
	 *
	 * @return array
	 */
	protected function get_default_headers() {
		$token = trim( $this->api_token );
		// Adiciona Bearer se não estiver presente
		if ( strpos( $token, 'Bearer ' ) !== 0 ) {
			$token = 'Bearer ' . $token;
		}

		return array(
			'Authorization' => $token,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Retorna a URL base do endpoint.
	 *
	 * @return string
	 */
	protected function get_base_endpoint() {
		return 'production' === $this->environment
			? 'https://api.blu.com.br/b2b/pix'
			: 'https://api-hlg.blu.com.br/b2b/pix';
	}

	/**
	 * Monta o payload com base no pedido.
	 *
	 * @param WC_Order $order Pedido.
	 * @return array
	 */
	protected function build_payload_from_order( WC_Order $order ) {
		// Calcula data de expiração
		$expires_at = date( 'Y-m-d', strtotime( '+' . $this->expiration_days . ' days' ) );

		// Descrições
		$description = ! empty( $this->description_external )
			? $this->description_external
			: sprintf(
				/* translators: %s: order number */
				__( 'Pedido #%s - %s', 'gstore' ),
				$order->get_order_number(),
				get_bloginfo( 'name' )
			);

		// Trunca description para máximo de 25 caracteres (limite da API Blu)
		$description = mb_substr( $description, 0, 25 );

		$description_internal = ! empty( $this->description_internal )
			? $this->description_internal
			: sprintf(
				/* translators: %s: order number */
				__( 'Pedido #%s', 'gstore' ),
				$order->get_order_number()
			);

		// Trunca description_internal para máximo de 12 caracteres (limite da API Blu)
		$description_internal = mb_substr( $description_internal, 0, 12 );

		$payload = array(
			'expires_at'          => $expires_at,
			'description'         => $description,
			'description_internal' => $description_internal,
			'value'               => $this->format_amount( $order->get_total() ),
		);

		return array_filter(
			$payload,
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
	}

	/**
	 * Armazena os dados retornados pela API.
	 *
	 * @param WC_Order $order    Pedido.
	 * @param array    $response Resposta Blu.
	 * @return void
	 */
	protected function store_pix_metadata( WC_Order $order, array $response ) {
		$order->update_meta_data( self::META_TRANSACTION_TOKEN, $response['transaction_token'] ?? '' );
		$order->update_meta_data( self::META_QR_CODE_BASE64, $response['qr_code_base64'] ?? '' );
		$order->update_meta_data( self::META_EMV, $response['emv'] ?? '' );
		$order->update_meta_data( self::META_STATUS, $response['status'] ?? '' );
		$order->update_meta_data( self::META_EXPIRES_AT, $response['expires_at'] ?? '' );
		$order->update_meta_data( self::META_LAST_PAYLOAD, wp_json_encode( $response ) );

		if ( isset( $response['transaction_token'] ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: transaction token */
					__( 'Pix Blu criado. Token: %s', 'gstore' ),
					$response['transaction_token']
				)
			);
		}
	}

	/**
	 * Mostra o card com instruções para o cliente.
	 *
	 * @param WC_Order $order Pedido.
	 * @return void
	 */
	protected function output_pix_instructions( WC_Order $order ) {
		// Evita duplicação - só renderiza uma vez por pedido por request
		static $rendered_orders = array();
		$order_id = $order->get_id();

		if ( in_array( $order_id, $rendered_orders, true ) ) {
			return;
		}
		$rendered_orders[] = $order_id;

		$transaction_token = $order->get_meta( self::META_TRANSACTION_TOKEN );
		$qr_code_base64    = $order->get_meta( self::META_QR_CODE_BASE64 );
		$emv               = $order->get_meta( self::META_EMV );
		$status            = $order->get_meta( self::META_STATUS );
		$expires_at        = $order->get_meta( self::META_EXPIRES_AT );

		// Se não tem QR Code ou EMV, tenta consultar
		if ( empty( $qr_code_base64 ) && empty( $emv ) && ! empty( $transaction_token ) ) {
			$response = $this->consult_pix( $transaction_token );
			if ( ! is_wp_error( $response ) ) {
				$qr_code_base64 = $response['qr_code_base64'] ?? '';
				$emv            = $response['emv'] ?? '';
				$status         = $response['status'] ?? $status;
				$expires_at      = $response['expires_at'] ?? $expires_at;

				// Atualiza metadados
				$order->update_meta_data( self::META_QR_CODE_BASE64, $qr_code_base64 );
				$order->update_meta_data( self::META_EMV, $emv );
				$order->update_meta_data( self::META_STATUS, $status );
				$order->update_meta_data( self::META_EXPIRES_AT, $expires_at );
				$order->save();
			}
		}

		if ( empty( $qr_code_base64 ) && empty( $emv ) ) {
			echo '<div class="woocommerce-info">' . esc_html__( 'Os dados do Pix ainda não estão disponíveis. Nossa equipe está verificando.', 'gstore' ) . '</div>';
			return;
		}

		// Determina a classe de status
		$status_class = 'pix-box--pending';
		$status_text  = __( 'Aguardando pagamento', 'gstore' );
		
		$order_status = $order->get_status();

		if ( in_array( $order_status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			$status_class = 'pix-box--expired';
			$status_text  = __( 'Pedido Cancelado', 'gstore' );
			
			if ( 'refunded' === $order_status ) {
				$status_text = __( 'Pedido Reembolsado', 'gstore' );
			} elseif ( 'failed' === $order_status ) {
				$status_text = __( 'Falha no Pagamento', 'gstore' );
			}
		} elseif ( $status ) {
			$status_lower = strtolower( $status );
			/**
			 * Apenas 'paid' indica pagamento confirmado na Blu.
			 * Outros status como 'processed' ou 'success' indicam apenas criação da cobrança.
			 */
			if ( 'paid' === $status_lower ) {
				$status_class = 'pix-box--processed';
				$status_text  = __( 'Pagamento aprovado', 'gstore' );
			} elseif ( 'expired' === $status_lower ) {
				$status_class = 'pix-box--expired';
				$status_text  = __( 'Pix expirado', 'gstore' );
			}
			// Se status for 'processed' ou outro, mantém o padrão "Aguardando pagamento"
		}

		// Formata valor total
		$total = $order->get_total();
		$formatted_total = wc_price( $total, array( 'decimals' => 2 ) );

		// Formata data de expiração
		$formatted_expires = $expires_at ? $this->format_date( $expires_at ) : '';

		$emv_id = 'pixCode-' . esc_attr( $order->get_id() );
		?>
		<div class="pix-box <?php echo esc_attr( $status_class ); ?>">
			<div class="pix-box__header">
				<div>
					<h2 class="pix-box__title"><?php esc_html_e( 'Pagamento via Pix', 'gstore' ); ?></h2>
					<p class="pix-box__subtitle">
						<?php esc_html_e( 'Escaneie o QR Code ou copie o código abaixo para concluir seu pagamento.', 'gstore' ); ?>
					</p>
				</div>

				<?php if ( $status ) : ?>
					<span class="pix-box__status">
						<?php echo esc_html( $status_text ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="pix-box__content">
				<?php if ( $qr_code_base64 ) : ?>
					<div class="pix-box__qr">
						<img src="data:image/png;base64,<?php echo esc_attr( $qr_code_base64 ); ?>" alt="<?php esc_attr_e( 'QR Code para pagamento Pix', 'gstore' ); ?>">
						<?php if ( $formatted_expires ) : ?>
							<p class="pix-box__expires">
								<?php echo wp_kses_post( sprintf( __( 'Válido até: %s', 'gstore' ), '<strong>' . esc_html( $formatted_expires ) . '</strong>' ) ); ?>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="pix-box__details">
					<div class="pix-box__amount">
						<span><?php esc_html_e( 'Total do pedido', 'gstore' ); ?></span>
						<strong><?php echo wp_kses_post( $formatted_total ); ?></strong>
					</div>

					<?php if ( $emv ) : ?>
						<label class="pix-box__label" for="<?php echo esc_attr( $emv_id ); ?>">
							<?php esc_html_e( 'Código Pix (copiar e colar)', 'gstore' ); ?>
						</label>

						<div class="pix-box__code-group">
							<textarea id="<?php echo esc_attr( $emv_id ); ?>" class="pix-box__code" readonly><?php echo esc_textarea( $emv ); ?></textarea>

							<button type="button" class="pix-box__copy" data-copy-target="#<?php echo esc_attr( $emv_id ); ?>">
								<?php esc_html_e( 'Copiar código', 'gstore' ); ?>
							</button>
						</div>
					<?php endif; ?>

					<?php if ( $transaction_token ) : ?>
						<p class="pix-box__meta">
							<?php echo esc_html( sprintf( __( 'Token: %s', 'gstore' ), $transaction_token ) ); ?>
						</p>
					<?php endif; ?>
					
					<?php if ( $status ) : ?>
						<p class="pix-box__meta pix-box__meta--muted">
							<?php echo esc_html( sprintf( __( 'Status: %s', 'gstore' ), $status ) ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<script>
		(function() {
			document.addEventListener('click', function (event) {
				const btn = event.target.closest('.pix-box__copy');
				if (!btn) return;

				const selector = btn.getAttribute('data-copy-target');
				const textarea = document.querySelector(selector);
				if (!textarea) return;

				const text = textarea.value;

				// Tenta usar Clipboard API moderna
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(function() {
						const originalText = btn.textContent;
						btn.textContent = 'Copiado!';
						setTimeout(function() {
							btn.textContent = originalText;
						}, 1500);
					}).catch(function() {
						fallbackCopy();
					});
				} else {
					fallbackCopy();
				}

				function fallbackCopy() {
					textarea.select();
					textarea.setSelectionRange(0, textarea.value.length);

					try {
						document.execCommand('copy');
						const originalText = btn.textContent;
						btn.textContent = 'Copiado!';
						setTimeout(function() {
							btn.textContent = originalText;
						}, 1500);
					} catch (e) {
						console.warn('Não foi possível copiar automaticamente.', e);
					}
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Formata valores monetários usando 2 casas decimais.
	 *
	 * @param float $amount Valor.
	 * @return string
	 */
	protected function format_amount( $amount ) {
		return number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Formata data no formato YYYY-MM-DD.
	 *
	 * @param string $value Data.
	 * @return string
	 */
	protected function format_date( $value ) {
		$timestamp = strtotime( $value );
		if ( ! $timestamp ) {
			return $value;
		}

		return wp_date( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Loga mensagens de depuração.
	 *
	 * @param string $message Texto.
	 * @param array  $context Contexto.
	 * @return void
	 */
	protected function log_debug( $message, $context = array() ) {
		if ( ! $this->debug_logging ) {
			return;
		}

		wc_get_logger()->info(
			$message . ' | ' . wp_json_encode( $context ),
			array( 'source' => $this->id )
		);
	}

	/**
	 * Loga erros.
	 *
	 * @param string $message Texto.
	 * @param array  $context Contexto.
	 * @return void
	 */
	protected function log_error( $message, $context = array() ) {
		wc_get_logger()->error(
			$message . ' | ' . wp_json_encode( $context ),
			array( 'source' => $this->id )
		);
	}

	/**
	 * Recupera o token do webhook.
	 *
	 * @return string
	 */
	public function get_webhook_secret() {
		return (string) $this->webhook_secret;
	}

	/**
	 * Recupera o token configurado (estático).
	 *
	 * @return string
	 */
	public static function get_configured_webhook_secret() {
		$settings = (array) get_option( 'woocommerce_' . self::GATEWAY_ID . '_settings', array() );
		return isset( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : '';
	}

	/**
	 * Recupera o token da API (estático).
	 *
	 * @return string
	 */
	public static function get_configured_api_token() {
		$settings = (array) get_option( 'woocommerce_' . self::GATEWAY_ID . '_settings', array() );
		return isset( $settings['api_token'] ) ? $settings['api_token'] : '';
	}
}

/**
 * Recupera a instância do gateway já carregado.
 *
 * @return Gstore_Blu_Pix_Gateway|null
 */
function gstore_blu_pix_get_gateway_instance() {
	if ( ! function_exists( 'WC' ) ) {
		return null;
	}

	$gateways = WC()->payment_gateways();
	if ( ! $gateways ) {
		return null;
	}

	$available = $gateways->payment_gateways();

	return isset( $available[ Gstore_Blu_Pix_Gateway::GATEWAY_ID ] )
		? $available[ Gstore_Blu_Pix_Gateway::GATEWAY_ID ]
		: null;
}

/**
 * Adiciona o gateway à lista de métodos disponíveis.
 *
 * @param array $methods Métodos atuais.
 * @return array
 */
function gstore_blu_pix_register_gateway( $methods ) {
	$methods[] = 'Gstore_Blu_Pix_Gateway';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'gstore_blu_pix_register_gateway' );

/**
 * Registra o endpoint de webhook Pix (/wp-json/gstore-blu/v1/pix-webhook).
 */
function gstore_blu_pix_register_webhook_route() {
	register_rest_route(
		'gstore-blu/v1',
		'/pix-webhook',
		array(
			'methods'             => array( 'POST', 'PUT' ),
			'callback'            => 'gstore_blu_pix_handle_webhook_request',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'gstore_blu_pix_register_webhook_route' );

/**
 * Manipula o webhook recebido da Blu para Pix.
 *
 * @param WP_REST_Request $request Requisição.
 * @return WP_REST_Response
 */
function gstore_blu_pix_handle_webhook_request( WP_REST_Request $request ) {
	$secret = Gstore_Blu_Pix_Gateway::get_configured_webhook_secret();

	if ( $secret ) {
		$provided = $request->get_header( 'x-gstore-blu-webhook' );
		if ( empty( $provided ) ) {
			$provided = $request->get_param( 'token' );
		}

		if ( ! hash_equals( $secret, (string) $provided ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Token do webhook inválido.', 'gstore' ) ),
				401
			);
		}
	}

	$payload = $request->get_json_params();

	// O webhook Pix pode vir com diferentes campos identificadores
	// Tenta encontrar o pedido por transaction_token, id, ou movement_id
	$order = null;

	if ( ! empty( $payload['transaction_token'] ) ) {
		$order = gstore_blu_pix_find_order_by_transaction_token( $payload['transaction_token'] );
	} elseif ( ! empty( $payload['id'] ) ) {
		$order = gstore_blu_pix_find_order_by_transaction_token( $payload['id'] );
	} elseif ( ! empty( $payload['movement_id'] ) ) {
		$order = gstore_blu_pix_find_order_by_movement_id( $payload['movement_id'] );
	}

	if ( ! $order ) {
		return new WP_REST_Response(
			array( 'message' => __( 'Pedido não encontrado para o Pix informado.', 'gstore' ) ),
			404
		);
	}

	gstore_blu_pix_apply_status_from_response( $order, $payload, false );

	return new WP_REST_Response(
		array(
			'message' => __( 'Webhook Pix processado com sucesso.', 'gstore' ),
			'orderId' => $order->get_id(),
		),
		200
	);
}

/**
 * Localiza pedido pelo transaction_token do Pix.
 *
 * @param string $transaction_token Token do Pix.
 * @return WC_Order|false
 */
function gstore_blu_pix_find_order_by_transaction_token( $transaction_token ) {
	$orders = wc_get_orders(
		array(
			'limit'      => 1,
			'meta_key'   => Gstore_Blu_Pix_Gateway::META_TRANSACTION_TOKEN,
			'meta_value' => $transaction_token,
			'orderby'    => 'date',
			'order'      => 'DESC',
		)
	);

	return ! empty( $orders ) ? $orders[0] : false;
}

/**
 * Localiza pedido pelo movement_id do Pix.
 *
 * @param string $movement_id ID da movimentação.
 * @return WC_Order|false
 */
function gstore_blu_pix_find_order_by_movement_id( $movement_id ) {
	$orders = wc_get_orders(
		array(
			'limit'      => 1,
			'meta_key'   => Gstore_Blu_Pix_Gateway::META_MOVEMENT_ID,
			'meta_value' => $movement_id,
			'orderby'    => 'date',
			'order'      => 'DESC',
		)
	);

	return ! empty( $orders ) ? $orders[0] : false;
}

/**
 * Aplica status no pedido com base na resposta do webhook Pix.
 *
 * @param WC_Order $order      Pedido.
 * @param array    $payload    Dados do webhook.
 * @param bool     $manual     Indica se é atualização manual.
 */
function gstore_blu_pix_apply_status_from_response( WC_Order $order, array $payload, $manual = false ) {
	// Atualiza movement_id se presente
	if ( isset( $payload['movement_id'] ) ) {
		$order->update_meta_data( Gstore_Blu_Pix_Gateway::META_MOVEMENT_ID, $payload['movement_id'] );
	}

	// Atualiza status
	$status = isset( $payload['status'] ) ? strtolower( $payload['status'] ) : '';
	if ( $status ) {
		$order->update_meta_data( Gstore_Blu_Pix_Gateway::META_STATUS, $status );
	}

	// Atualiza QR Code e EMV se presentes
	if ( isset( $payload['qr_code_base64'] ) ) {
		$order->update_meta_data( Gstore_Blu_Pix_Gateway::META_QR_CODE_BASE64, $payload['qr_code_base64'] );
	}
	if ( isset( $payload['emv'] ) ) {
		$order->update_meta_data( Gstore_Blu_Pix_Gateway::META_EMV, $payload['emv'] );
	}

	$order->update_meta_data( Gstore_Blu_Pix_Gateway::META_LAST_PAYLOAD, wp_json_encode( $payload ) );

	// Processa status do pagamento
	// O webhook Pix envia status "success" quando o pagamento é confirmado
	if ( 'success' === $status || 'paid' === $status ) {
		if ( ! $order->is_paid() ) {
			$order->payment_complete();
			$order->add_order_note(
				$manual
					? __( 'Status Pix consultado manualmente: pagamento confirmado.', 'gstore' )
					: __( 'Webhook Pix confirmou o pagamento.', 'gstore' )
			);
		}
	} elseif ( 'expired' === $status ) {
		if ( ! $order->has_status( array( 'cancelled', 'completed', 'refunded' ) ) ) {
			$order->update_status(
				'cancelled',
				$manual
					? __( 'Status Pix consultado manualmente: Pix expirado.', 'gstore' )
					: __( 'Webhook Pix informou que o Pix expirou.', 'gstore' )
			);
		}
	} else {
		if ( $status ) {
			$order->add_order_note(
				sprintf(
					__( 'Status Pix atualizado: %s', 'gstore' ),
					$status
				)
			);
		}
	}

	$order->save();
}

/**
 * Adiciona ação manual no admin para consultar status do Pix.
 *
 * @param array    $actions Ações.
 * @param WC_Order $order   Pedido.
 * @return array
 */
function gstore_blu_pix_register_order_action( $actions, $order ) {
	if ( ! $order instanceof WC_Order ) {
		return $actions;
	}

	if ( $order->get_payment_method() !== Gstore_Blu_Pix_Gateway::GATEWAY_ID ) {
		return $actions;
	}

	$actions['gstore_blu_pix_refresh_status'] = __( 'Consultar status do Pix na Blu', 'gstore' );

	return $actions;
}
add_filter( 'woocommerce_order_actions', 'gstore_blu_pix_register_order_action', 10, 2 );

/**
 * Executa a ação manual para consultar status do Pix no painel.
 *
 * @param WC_Order $order Pedido.
 */
function gstore_blu_pix_order_action_refresh_status( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$gateway = gstore_blu_pix_get_gateway_instance();

	if ( ! $gateway ) {
		$order->add_order_note( __( 'Gateway Pix Blu indisponível para consulta.', 'gstore' ) );
		return;
	}

	$transaction_token = $order->get_meta( Gstore_Blu_Pix_Gateway::META_TRANSACTION_TOKEN );

	if ( empty( $transaction_token ) ) {
		$order->add_order_note( __( 'Nenhum Pix está associado a este pedido.', 'gstore' ) );
		return;
	}

	$response = $gateway->consult_pix( $transaction_token );

	if ( is_wp_error( $response ) ) {
		$order->add_order_note( sprintf( __( 'Falha na consulta Pix Blu: %s', 'gstore' ), $response->get_error_message() ) );
		return;
	}

	gstore_blu_pix_apply_status_from_response( $order, $response, true );
}
add_action( 'woocommerce_order_action_gstore_blu_pix_refresh_status', 'gstore_blu_pix_order_action_refresh_status' );

/**
 * Exibe dados do Pix no painel do pedido.
 *
 * @param WC_Order $order Pedido.
 */
function gstore_blu_pix_admin_order_panel( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( $order->get_payment_method() !== Gstore_Blu_Pix_Gateway::GATEWAY_ID ) {
		return;
	}

	$transaction_token = $order->get_meta( Gstore_Blu_Pix_Gateway::META_TRANSACTION_TOKEN );
	$status            = $order->get_meta( Gstore_Blu_Pix_Gateway::META_STATUS );
	$expires_at        = $order->get_meta( Gstore_Blu_Pix_Gateway::META_EXPIRES_AT );
	$movement_id       = $order->get_meta( Gstore_Blu_Pix_Gateway::META_MOVEMENT_ID );

	?>
	<div class="order_data_column">
		<h4><?php esc_html_e( 'Pix Blu', 'gstore' ); ?></h4>
		<ul>
			<li><?php esc_html_e( 'Token:', 'gstore' ); ?> <strong><?php echo esc_html( $transaction_token ?: '-' ); ?></strong></li>
			<?php if ( $movement_id ) : ?>
				<li><?php esc_html_e( 'Movement ID:', 'gstore' ); ?> <strong><?php echo esc_html( $movement_id ); ?></strong></li>
			<?php endif; ?>
			<?php if ( $status ) : ?>
				<li><?php esc_html_e( 'Status:', 'gstore' ); ?> <strong><?php echo esc_html( $status ); ?></strong></li>
			<?php endif; ?>
			<?php if ( $expires_at ) : ?>
				<li><?php esc_html_e( 'Expira em:', 'gstore' ); ?> <strong><?php echo esc_html( $expires_at ); ?></strong></li>
			<?php endif; ?>
		</ul>
	</div>
	<?php
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'gstore_blu_pix_admin_order_panel' );


<?php
/**
 * Gateway de pagamento via Link Blu.
 *
 * @package Gstore
 */



defined( 'ABSPATH' ) || exit;



/**
 * Classe responsável por integrar a Blu ao WooCommerce via Link de Pagamento.
 */
class Gstore_Blu_Payment_Gateway extends WC_Payment_Gateway {

	const GATEWAY_ID = 'blu_checkout';

	const META_LINK_ID        = '_gstore_blu_link_id';
	const META_LINK_URL       = '_gstore_blu_link_url';
	const META_SMART_LINK_URL = '_gstore_blu_smart_checkout_url';
	const META_TRACE_KEY      = '_gstore_blu_trace_key';
	const META_STATUS         = '_gstore_blu_status';
	const META_EXPIRATION     = '_gstore_blu_expiration';
	const META_LAST_PAYLOAD   = '_gstore_blu_last_payload';

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
	 * Número máximo de parcelas permitido.
	 *
	 * @var int
	 */
	protected $max_installments = 12;

	/**
	 * Número fixo de parcelas (opcional).
	 *
	 * @var int
	 */
	protected $fixed_installments = 0;

	/**
	 * Define se haverá repasse de taxas.
	 *
	 * @var bool
	 */
	protected $issuer_rate_forwarding = false;

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
		$this->method_title       = __( 'Checkout Blu (Link de Pagamento)', 'gstore' );
		$this->method_description = __( 'Cria automaticamente um link de pagamento Blu e acompanha o status do pedido.', 'gstore' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled                = $this->get_option( 'enabled', 'no' );
		$this->title                  = $this->get_option( 'title', __( 'Pagamento via Link Blu', 'gstore' ) );
		$this->description            = $this->get_option( 'description', __( 'Você será redirecionado para finalizar o pagamento de forma segura.', 'gstore' ) );
		
		// Configurações do admin apenas
		$this->api_token = $this->get_option( 'api_token', '' );
		$this->environment = $this->get_option( 'environment', 'homolog' );

		$this->max_installments       = (int) $this->get_option( 'max_installments', 12 );
		$this->fixed_installments     = (int) $this->get_option( 'fixed_installments', 0 );
		$this->issuer_rate_forwarding = 'yes' === $this->get_option( 'issuer_rate_forwarding', 'no' );
		$this->webhook_secret         = $this->get_option( 'webhook_secret', '' );
		$this->debug_logging          = 'yes' === $this->get_option( 'debug_logging', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		// Removido thankyou e view_order pois o redirecionamento será direto
		// add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'render_thankyou_instructions' ) );
		// add_action( 'woocommerce_view_order', array( $this, 'render_account_order_instructions' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 4 );
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
				'label'   => __( 'Ativar pagamento via Link Blu', 'gstore' ),
				'default' => 'no',
			),
			'title'                  => array(
				'title'       => __( 'Título', 'gstore' ),
				'type'        => 'text',
				'description' => __( 'Nome exibido ao cliente durante o checkout.', 'gstore' ),
				'default'     => __( 'Pagamento via Link Blu', 'gstore' ),
			),
			'description'            => array(
				'title'       => __( 'Descrição', 'gstore' ),
				'type'        => 'textarea',
				'description' => __( 'Texto mostrado ao cliente abaixo do nome do método.', 'gstore' ),
				'default'     => __( 'Geraremos um link Blu para você finalizar o pagamento com segurança.', 'gstore' ),
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
			'max_installments'       => array(
				'title'       => __( 'Parcelas máximas', 'gstore' ),
				'type'        => 'number',
				'description' => __( 'Valor entre 1 e 12. Se vazio ou 0, o campo não será enviado.', 'gstore' ),
				'default'     => 12,
				'custom_attributes' => array(
					'min' => 0,
					'max' => 12,
					'step' => 1,
				),
			),
			'fixed_installments'     => array(
				'title'       => __( 'Parcelas fixas', 'gstore' ),
				'type'        => 'number',
				'description' => __( 'Número de parcelas obrigatórias. Se preenchido, substitui o máximo configurado.', 'gstore' ),
				'default'     => '',
				'custom_attributes' => array(
					'min' => 0,
					'max' => 12,
					'step' => 1,
				),
			),
			'issuer_rate_forwarding' => array(
				'title'   => __( 'Repasse de taxas', 'gstore' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar issuer_rate_forwarding', 'gstore' ),
				'default' => 'no',
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
		// Verifica se há token configurado (admin ou constante)
		if ( ! empty( $this->api_token ) ) {
			return parent::is_available();
		}
		
		return false;
	}

	/**
	 * Processa o pagamento e cria o link na Blu.
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
			$order->add_order_note( __( 'Token Blu não configurado. Não foi possível criar o link de pagamento.', 'gstore' ) );
			wc_add_notice( __( 'Método de pagamento indisponível no momento. Entre em contato com o suporte.', 'gstore' ), 'error' );
			return null;
		}

		$response = $this->create_payment_link( $order );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Erro ao criar link Blu', array( 'error' => $response->get_error_message() ) );
			wc_add_notice( $response->get_error_message(), 'error' );
			return null;
		}

		$this->store_link_metadata( $order, $response );

		// Verifica se é pré-checkout (dados incompletos)
		$is_precheckout = empty( $order->get_billing_address_1() ) && empty( $order->get_billing_city() );
		if ( $is_precheckout ) {
			$order->add_order_note( __( 'Pré-checkout: Dados completos serão coletados no checkout da Blu.', 'gstore' ) );
		}

		// Reduz estoque
		wc_reduce_stock_levels( $order_id );
		
		// Limpa carrinho
		WC()->cart->empty_cart();

		// Define status como pendente (aguardando pagamento)
		$order->update_status( 'pending', __( 'Aguardando pagamento no checkout da Blu.', 'gstore' ) );
		
		// Obtém a URL de redirecionamento (smart checkout ou link simples)
		$redirect_url = $response['smart_checkout_url'] ?? $response['link_url'];

		return array(
			'result'   => 'success',
			'redirect' => $redirect_url,
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

		$this->output_payment_instructions( $order );
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

		$this->output_payment_instructions( $order );
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

		$link_url = $order->get_meta( self::META_SMART_LINK_URL );
		if ( empty( $link_url ) ) {
			$link_url = $order->get_meta( self::META_LINK_URL );
		}

		if ( empty( $link_url ) ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: payment link url */
			__( 'Finalize o pagamento acessando o link seguro: %s', 'gstore' ),
			$link_url
		);

		if ( $plain_text ) {
			echo "\n" . $message . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo '<p>' . esc_html( $message ) . '</p>';
	}

	/**
	 * Cria o link de pagamento via API.
	 *
	 * @param WC_Order $order Pedido.
	 * @return array|\WP_Error
	 */
	protected function create_payment_link( WC_Order $order ) {
		$payload = $this->build_payload_from_order( $order );
		$this->log_debug( 'Payload enviado à Blu', $payload );

		return $this->remote_request( 'POST', '', $payload );
	}

	/**
	 * Consulta o link de pagamento por ID.
	 *
	 * @param string $link_id ID informado pela Blu.
	 * @return array|\WP_Error
	 */
	public function consult_payment_link( $link_id ) {
		if ( empty( $link_id ) ) {
			return new WP_Error( 'gstore_blu_missing_id', __( 'ID do link não informado.', 'gstore' ) );
		}

		return $this->remote_request( 'GET', '/' . trim( $link_id ) );
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
				'gstore_blu_http_error',
				sprintf( __( 'Erro de comunicação com a Blu: %s', 'gstore' ), $response->get_error_message() )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->log_debug(
			sprintf( 'Resposta Blu (%s %s)', $method, $path ),
			array(
				'status_code' => $code,
				'body'        => $data,
			)
		);

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['message'] ) ? $data['message'] : __( 'A Blu retornou um erro inesperado.', 'gstore' );

			return new WP_Error(
				'gstore_blu_request_failed',
				sprintf( __( 'Blu retornou %1$s: %2$s', 'gstore' ), $code, $message ),
				array(
					'status_code' => $code,
					'body'        => $data,
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'gstore_blu_invalid_response', __( 'Resposta inválida da Blu.', 'gstore' ) );
		}

		return $data;
	}

	/**
	 * Define cabeçalhos padrão.
	 *
	 * @return array
	 */
	protected function get_default_headers() {
		return array(
			'Authorization' => trim( $this->api_token ),
			'Content-Type'  => 'application/json',
			'Accept'        => 'version=1',
		);
	}

	/**
	 * Retorna a URL base do endpoint.
	 *
	 * @return string
	 */
	protected function get_base_endpoint() {
		return 'production' === $this->environment
			? 'https://api.blu.com.br/b2b/payment_links'
			: 'https://api-hlg.blu.com.br/b2b/payment_links';
	}

	/**
	 * Monta o payload com base no pedido.
	 *
	 * @param WC_Order $order Pedido.
	 * @return array
	 */
	protected function build_payload_from_order( WC_Order $order ) {
		$document = $this->extract_document_data( $order );
		$phone = $this->sanitize_digits( $order->get_billing_phone() );
		// Remove o código do país 55 se estiver presente e o número for maior que 11 dígitos
		if ( strlen( $phone ) > 11 && substr( $phone, 0, 2 ) === '55' ) {
			$phone = substr( $phone, 2 );
		}

		// Verifica se o pedido tem dados mínimos (pré-checkout)
		$is_precheckout = empty( $order->get_billing_address_1() ) && empty( $order->get_billing_city() );

		// Payload mínimo: apenas valor é obrigatório (mínimo R$ 10,00 = 1000 centavos)
		$description = sprintf(
			/* translators: %s: order number */
			__( 'Pedido #%s - %s', 'gstore' ),
			$order->get_order_number(),
			get_bloginfo( 'name' )
		);
		
		// Trunca description para máximo de 25 caracteres (limite da API Blu)
		$description = mb_substr( $description, 0, 25 );

		$payload = array(
			'amount'              => $this->format_amount( $order->get_total() ),
			'email_notification'  => $order->get_billing_email() ?: null,
			'phone_notification'  => ( strlen( $phone ) >= 10 ) ? $phone : null,
			'description'         => $description,
			'issuer_rate_forwarding' => $this->issuer_rate_forwarding ? true : null,
		);

		// Adiciona dados adicionais apenas se disponíveis (não for pré-checkout)
		if ( ! $is_precheckout ) {
			$payload['document_type'] = $document['type'];
			
			$customer_name = $order->get_formatted_billing_full_name();
			if ( ! empty( $customer_name ) ) {
				$payload['customer_name'] = $customer_name;
			}
			
			if ( 'CNPJ' === $document['type'] && ! empty( $document['value'] ) ) {
				$payload['customer_cnpj'] = $document['value'];
			}
			
			if ( 'CPF' === $document['type'] && ! empty( $document['value'] ) ) {
				$payload['customer_cpf'] = $document['value'];
			}
		}

		if ( $this->fixed_installments > 0 ) {
			$payload['fixed_installment_number'] = (string) min( 12, $this->fixed_installments );
		} elseif ( $this->max_installments > 0 ) {
			$payload['max_installment_number'] = (string) min( 12, $this->max_installments );
		}

		return array_filter(
			$payload,
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
	}

	/**
	 * Extrai CPF ou CNPJ do pedido.
	 *
	 * @param WC_Order $order Pedido.
	 * @return array
	 */
	protected function extract_document_data( WC_Order $order ) {
		// Lista estendida de chaves comuns em plugins brasileiros (Brazilian Market, Claudio Sanches, etc)
		$cnpj_keys = array(
			'billing_cnpj',
			'_billing_cnpj',
			'billing_company_document',
			'_billing_company_document',
			'billing_cpf_cnpj', // Alguns plugins usam o mesmo campo
			'_billing_cpf_cnpj',
		);

		$cpf_keys = array(
			'billing_cpf',
			'_billing_cpf',
			'billing_person_document',
			'_billing_person_document',
			'billing_cpf_cnpj',
			'_billing_cpf_cnpj',
		);

		// Tenta encontrar CNPJ primeiro
		foreach ( $cnpj_keys as $key ) {
			$value = $this->sanitize_digits( $order->get_meta( $key ) );
			// CNPJ tem 14 dígitos
			if ( strlen( $value ) === 14 ) {
				return array(
					'type'  => 'CNPJ',
					'value' => $value,
				);
			}
		}

		// Tenta encontrar CPF
		foreach ( $cpf_keys as $key ) {
			$value = $this->sanitize_digits( $order->get_meta( $key ) );
			// CPF tem 11 dígitos
			if ( strlen( $value ) === 11 ) {
				return array(
					'type'  => 'CPF',
					'value' => $value,
				);
			}
		}

		// Fallback: Tenta inferir pelo tamanho se achou algo num campo genérico mas não bateu nas regras acima
		// (Ex: campo customizado 'billing_document')
		$generic_value = $this->sanitize_digits( $order->get_meta( 'billing_document' ) );
		if ( ! empty( $generic_value ) ) {
			if ( strlen( $generic_value ) === 14 ) {
				return array( 'type' => 'CNPJ', 'value' => $generic_value );
			}
			if ( strlen( $generic_value ) === 11 ) {
				return array( 'type' => 'CPF', 'value' => $generic_value );
			}
		}

		return array(
			'type'  => '',
			'value' => '',
		);
	}

	/**
	 * Armazena os dados retornados pela API.
	 *
	 * @param WC_Order $order    Pedido.
	 * @param array    $response Resposta Blu.
	 * @return void
	 */
	protected function store_link_metadata( WC_Order $order, array $response ) {
		$order->update_meta_data( self::META_LINK_ID, $response['id'] ?? '' );
		$order->update_meta_data( self::META_LINK_URL, $response['link_url'] ?? '' );
		$order->update_meta_data( self::META_SMART_LINK_URL, $response['smart_checkout_url'] ?? '' );
		$order->update_meta_data( self::META_TRACE_KEY, $response['trace_key'] ?? '' );
		$order->update_meta_data( self::META_STATUS, $response['message'] ?? '' );
		$order->update_meta_data( self::META_EXPIRATION, $response['expiration_date'] ?? '' );
		$order->update_meta_data( self::META_LAST_PAYLOAD, wp_json_encode( $response ) );

		$link = $response['smart_checkout_url'] ?? $response['link_url'] ?? '';
		if ( $link ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: blu payment link */
					__( 'Link Blu gerado: %s', 'gstore' ),
					$link
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
	protected function output_payment_instructions( WC_Order $order ) {
		$link_id   = $order->get_meta( self::META_LINK_ID );
		$link_url  = $order->get_meta( self::META_SMART_LINK_URL );
		$raw_link  = $order->get_meta( self::META_LINK_URL );
		$status    = $order->get_meta( self::META_STATUS );
		$expires   = $order->get_meta( self::META_EXPIRATION );

		if ( empty( $link_url ) ) {
			$link_url = $raw_link;
		}

		if ( empty( $link_url ) ) {
			echo '<div class="woocommerce-info">' . esc_html__( 'O link Blu ainda não está disponível. Nossa equipe está verificando.', 'gstore' ) . '</div>';
			return;
		}

		?>
		<div class="woocommerce-info Gstore-blu-checkout-card">
			<h3><?php esc_html_e( 'Finalize o pagamento na Blu', 'gstore' ); ?></h3>
			<p><?php esc_html_e( 'Clique no botão abaixo para acessar o checkout seguro da Blu e concluir o pagamento.', 'gstore' ); ?></p>
			<p>
				<a class="button" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $link_url ); ?>">
					<?php esc_html_e( 'Abrir checkout Blu', 'gstore' ); ?>
				</a>
			</p>
			<ul>
				<?php if ( $link_id ) : ?>
					<li><?php echo esc_html( sprintf( __( 'ID do link: %s', 'gstore' ), $link_id ) ); ?></li>
				<?php endif; ?>
				<?php if ( $expires ) : ?>
					<li><?php echo esc_html( sprintf( __( 'Válido até: %s', 'gstore' ), $this->format_datetime( $expires ) ) ); ?></li>
				<?php endif; ?>
				<?php if ( $status ) : ?>
					<li><?php echo esc_html( sprintf( __( 'Mensagem Blu: %s', 'gstore' ), wp_strip_all_tags( $status ) ) ); ?></li>
				<?php endif; ?>
			</ul>
		</div>
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
	 * Remove todos os caracteres não numéricos.
	 *
	 * @param string $value Valor original.
	 * @return string
	 */
	protected function sanitize_digits( $value ) {
		return preg_replace( '/\D+/', '', (string) $value );
	}

	/**
	 * Formata data ISO exibida pela Blu.
	 *
	 * @param string $value Data.
	 * @return string
	 */
	protected function format_datetime( $value ) {
		$timestamp = strtotime( $value );
		if ( ! $timestamp ) {
			return $value;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
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
 * @return Gstore_Blu_Payment_Gateway|null
 */
function gstore_blu_get_gateway_instance() {
	if ( ! function_exists( 'WC' ) ) {
		return null;
	}

	$gateways = WC()->payment_gateways();
	if ( ! $gateways ) {
		return null;
	}

	$available = $gateways->payment_gateways();

	return isset( $available[ Gstore_Blu_Payment_Gateway::GATEWAY_ID ] )
		? $available[ Gstore_Blu_Payment_Gateway::GATEWAY_ID ]
		: null;
}

/**
 * Adiciona o gateway à lista de métodos disponíveis.
 *
 * @param array $methods Métodos atuais.
 * @return array
 */
function gstore_blu_register_gateway( $methods ) {

	$methods[] = 'Gstore_Blu_Payment_Gateway';
	return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'gstore_blu_register_gateway' );

/**
 * Adiciona ação manual no admin para consultar status na Blu.
 *
 * @param array    $actions Ações.
 * @param WC_Order $order   Pedido.
 * @return array
 */
function gstore_blu_register_order_action( $actions, $order ) {
	if ( ! $order instanceof WC_Order ) {
		return $actions;
	}

	if ( $order->get_payment_method() !== Gstore_Blu_Payment_Gateway::GATEWAY_ID ) {
		return $actions;
	}

	$actions['gstore_blu_refresh_status'] = __( 'Consultar status na Blu', 'gstore' );

	return $actions;
}
add_filter( 'woocommerce_order_actions', 'gstore_blu_register_order_action', 10, 2 );

/**
 * Executa a ação manual para consultar status no painel.
 *
 * @param WC_Order $order Pedido.
 */
function gstore_blu_order_action_refresh_status( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$gateway = gstore_blu_get_gateway_instance();

	if ( ! $gateway ) {
		$order->add_order_note( __( 'Gateway Blu indisponível para consulta.', 'gstore' ) );
		return;
	}

	$link_id = $order->get_meta( Gstore_Blu_Payment_Gateway::META_LINK_ID );

	if ( empty( $link_id ) ) {
		$order->add_order_note( __( 'Nenhum link Blu está associado a este pedido.', 'gstore' ) );
		return;
	}

	$response = $gateway->consult_payment_link( $link_id );

	if ( is_wp_error( $response ) ) {
		$order->add_order_note( sprintf( __( 'Falha na consulta Blu: %s', 'gstore' ), $response->get_error_message() ) );
		return;
	}

	gstore_blu_apply_status_from_response( $order, $response, true );
}
add_action( 'woocommerce_order_action_gstore_blu_refresh_status', 'gstore_blu_order_action_refresh_status' );

/**
 * Registra o endpoint de webhook (/wp-json/gstore-blu/v1/webhook).
 */
function gstore_blu_register_webhook_route() {
	register_rest_route(
		'gstore-blu/v1',
		'/webhook',
		array(
			'methods'             => array( 'POST', 'PUT' ),
			'callback'            => 'gstore_blu_handle_webhook_request',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'gstore_blu_register_webhook_route' );

/**
 * Manipula o webhook recebido da Blu.
 *
 * @param WP_REST_Request $request Requisição.
 * @return WP_REST_Response
 */
function gstore_blu_handle_webhook_request( WP_REST_Request $request ) {
	$secret = Gstore_Blu_Payment_Gateway::get_configured_webhook_secret();

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

	if ( empty( $payload['id'] ) ) {
		return new WP_REST_Response(
			array( 'message' => __( 'ID do link não informado.', 'gstore' ) ),
			400
		);
	}

	$order = gstore_blu_find_order_by_link_id( $payload['id'] );

	if ( ! $order ) {
		return new WP_REST_Response(
			array( 'message' => __( 'Pedido não encontrado para o link informado.', 'gstore' ) ),
			404
		);
	}

	gstore_blu_apply_status_from_response( $order, $payload, false );

	return new WP_REST_Response(
		array(
			'message' => __( 'Webhook processado com sucesso.', 'gstore' ),
			'orderId' => $order->get_id(),
		),
		200
	);
}

/**
 * Mapeia dados do last_payment_link_intent para campos do pedido WooCommerce.
 *
 * @param WC_Order $order              Pedido.
 * @param array    $payment_intent     Dados do last_payment_link_intent do webhook.
 * @return void
 */
function gstore_blu_map_payment_intent_to_order( WC_Order $order, array $payment_intent ) {
	if ( empty( $payment_intent ) ) {
		return;
	}

	// Nome completo
	if ( ! empty( $payment_intent['name'] ) ) {
		$name_parts = explode( ' ', trim( $payment_intent['name'] ), 2 );
		$first_name = isset( $name_parts[0] ) ? sanitize_text_field( $name_parts[0] ) : '';
		$last_name  = isset( $name_parts[1] ) ? sanitize_text_field( $name_parts[1] ) : '';
		
		if ( ! empty( $first_name ) ) {
			$order->set_billing_first_name( $first_name );
		}
		if ( ! empty( $last_name ) ) {
			$order->set_billing_last_name( $last_name );
		}
	}

	// Email
	if ( ! empty( $payment_intent['email'] ) && is_email( $payment_intent['email'] ) ) {
		$order->set_billing_email( sanitize_email( $payment_intent['email'] ) );
	}

	// Telefone
	if ( ! empty( $payment_intent['phone'] ) ) {
		$phone = preg_replace( '/\D+/', '', $payment_intent['phone'] );
		if ( ! empty( $phone ) ) {
			$order->set_billing_phone( $phone );
		}
	}

	// CPF/CNPJ
	if ( ! empty( $payment_intent['cpf_cnpj'] ) ) {
		$cpf_cnpj = preg_replace( '/\D+/', '', $payment_intent['cpf_cnpj'] );
		if ( ! empty( $cpf_cnpj ) ) {
			// Determina se é CPF (11 dígitos) ou CNPJ (14 dígitos)
			if ( strlen( $cpf_cnpj ) === 11 ) {
				$order->update_meta_data( 'billing_cpf', $cpf_cnpj );
				$order->update_meta_data( '_billing_cpf', $cpf_cnpj );
			} elseif ( strlen( $cpf_cnpj ) === 14 ) {
				$order->update_meta_data( 'billing_cnpj', $cpf_cnpj );
				$order->update_meta_data( '_billing_cnpj', $cpf_cnpj );
			}
		}
	}

	// Data de nascimento
	if ( ! empty( $payment_intent['birth_date'] ) ) {
		$order->update_meta_data( 'billing_birth_date', sanitize_text_field( $payment_intent['birth_date'] ) );
	}

	// Endereço
	if ( ! empty( $payment_intent['address'] ) && is_array( $payment_intent['address'] ) ) {
		$address = $payment_intent['address'];

		// CEP
		if ( ! empty( $address['cep'] ) ) {
			$cep = preg_replace( '/\D+/', '', $address['cep'] );
			if ( ! empty( $cep ) ) {
				$order->set_billing_postcode( $cep );
			}
		}

		// Rua
		if ( ! empty( $address['street'] ) ) {
			$order->set_billing_address_1( sanitize_text_field( $address['street'] ) );
		}

		// Número
		if ( ! empty( $address['number'] ) ) {
			$order->update_meta_data( 'billing_number', sanitize_text_field( $address['number'] ) );
			$order->update_meta_data( '_billing_number', sanitize_text_field( $address['number'] ) );
		}

		// Complemento
		if ( ! empty( $address['complement'] ) ) {
			$order->set_billing_address_2( sanitize_text_field( $address['complement'] ) );
		}

		// Bairro
		if ( ! empty( $address['district'] ) ) {
			$order->update_meta_data( 'billing_neighborhood', sanitize_text_field( $address['district'] ) );
			$order->update_meta_data( '_billing_neighborhood', sanitize_text_field( $address['district'] ) );
		}

		// Cidade
		if ( ! empty( $address['city'] ) ) {
			$order->set_billing_city( sanitize_text_field( $address['city'] ) );
		}

		// Estado
		if ( ! empty( $address['state'] ) ) {
			$state = strtoupper( substr( sanitize_text_field( $address['state'] ), 0, 2 ) );
			$order->set_billing_state( $state );
		}
	}

	$order->save();
}

/**
 * Aplica status no pedido com base na resposta Blu.
 *
 * @param WC_Order $order      Pedido.
 * @param array    $payload    Dados.
 * @param bool     $manual     Indica se é atualização manual.
 */
function gstore_blu_apply_status_from_response( WC_Order $order, array $payload, $manual = false ) {
	$status = isset( $payload['status'] ) ? strtolower( $payload['status'] ) : '';

	if ( isset( $payload['link_url'] ) ) {
		$order->update_meta_data( Gstore_Blu_Payment_Gateway::META_LINK_URL, $payload['link_url'] );
	}

	if ( isset( $payload['smart_checkout_url'] ) ) {
		$order->update_meta_data( Gstore_Blu_Payment_Gateway::META_SMART_LINK_URL, $payload['smart_checkout_url'] );
	}

	if ( isset( $payload['expiration_date'] ) ) {
		$order->update_meta_data( Gstore_Blu_Payment_Gateway::META_EXPIRATION, $payload['expiration_date'] );
	}

	if ( $status ) {
		$order->update_meta_data( Gstore_Blu_Payment_Gateway::META_STATUS, $status );
	}

	$order->update_meta_data( Gstore_Blu_Payment_Gateway::META_LAST_PAYLOAD, wp_json_encode( $payload ) );

	switch ( $status ) {
		case 'paid':
		case 'success':
		case 'confirmed':
			// Extrai e atualiza dados completos do pagador se disponível
			if ( ! empty( $payload['last_payment_link_intent'] ) && is_array( $payload['last_payment_link_intent'] ) ) {
				gstore_blu_map_payment_intent_to_order( $order, $payload['last_payment_link_intent'] );
				
				$order->add_order_note(
					__( 'Dados do cliente atualizados com informações coletadas no checkout da Blu.', 'gstore' )
				);
			}

			if ( ! $order->is_paid() ) {
				$order->payment_complete();
				$order->add_order_note(
					$manual
						? __( 'Status Blu consultado manualmente: pagamento confirmado.', 'gstore' )
						: __( 'Webhook Blu confirmou o pagamento.', 'gstore' )
				);
			}
			break;
		case 'expired':
			if ( ! $order->has_status( array( 'cancelled', 'completed', 'refunded' ) ) ) {
				$order->update_status(
					'cancelled',
					$manual
						? __( 'Status Blu consultado manualmente: link expirado.', 'gstore' )
						: __( 'Webhook Blu informou que o link expirou.', 'gstore' )
				);
			}
			break;
		default:
			if ( $status ) {
				$order->add_order_note(
					sprintf(
						__( 'Status Blu atualizado: %s', 'gstore' ),
						$status
					)
				);
			}
			break;
	}

	$order->save();
}

/**
 * Localiza pedido pelo meta do link Blu.
 *
 * @param string $link_id ID do link.
 * @return WC_Order|false
 */
function gstore_blu_find_order_by_link_id( $link_id ) {
	$orders = wc_get_orders(
		array(
			'limit'      => 1,
			'meta_key'   => Gstore_Blu_Payment_Gateway::META_LINK_ID,
			'meta_value' => $link_id,
			'orderby'    => 'date',
			'order'      => 'DESC',
		)
	);

	return ! empty( $orders ) ? $orders[0] : false;
}

/**
 * Exibe dados do link no painel do pedido.
 *
 * @param WC_Order $order Pedido.
 */
function gstore_blu_admin_order_panel( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( $order->get_payment_method() !== Gstore_Blu_Payment_Gateway::GATEWAY_ID ) {
		return;
	}

	$link_id  = $order->get_meta( Gstore_Blu_Payment_Gateway::META_LINK_ID );
	$link_url = $order->get_meta( Gstore_Blu_Payment_Gateway::META_SMART_LINK_URL );
	$raw_link = $order->get_meta( Gstore_Blu_Payment_Gateway::META_LINK_URL );

	if ( empty( $link_url ) ) {
		$link_url = $raw_link;
	}

	?>
	<div class="order_data_column">
		<h4><?php esc_html_e( 'Link de pagamento Blu', 'gstore' ); ?></h4>
		<ul>
			<li><?php esc_html_e( 'ID do link:', 'gstore' ); ?> <strong><?php echo esc_html( $link_id ?: '-' ); ?></strong></li>
			<li>
				<?php esc_html_e( 'URL:', 'gstore' ); ?>
				<?php if ( $link_url ) : ?>
					<a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Abrir', 'gstore' ); ?></a>
				<?php else : ?>
					<strong>-</strong>
				<?php endif; ?>
			</li>
		</ul>
	</div>
	<?php
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'gstore_blu_admin_order_panel' );

/**
 * Agenda cron job para verificar links pendentes quando webhook não estiver configurado.
 */
function gstore_blu_schedule_status_check() {
	if ( ! wp_next_scheduled( 'gstore_blu_check_pending_links' ) ) {
		wp_schedule_event( time(), 'hourly', 'gstore_blu_check_pending_links' );
	}
}
add_action( 'wp', 'gstore_blu_schedule_status_check' );

/**
 * Verifica links pendentes e atualiza status (fallback quando webhook não estiver configurado).
 */
function gstore_blu_check_pending_links() {
	// Só executa se o webhook não estiver configurado
	$webhook_secret = Gstore_Blu_Payment_Gateway::get_configured_webhook_secret();
	if ( ! empty( $webhook_secret ) ) {
		// Webhook está configurado, não precisa do cron
		return;
	}

	$gateway = gstore_blu_get_gateway_instance();
	if ( ! $gateway ) {
		return;
	}

	// Busca pedidos pendentes com link Blu
	$pending_orders = wc_get_orders(
		array(
			'limit'      => 50,
			'status'     => 'pending',
			'meta_key'   => Gstore_Blu_Payment_Gateway::META_LINK_ID,
			'meta_compare' => 'EXISTS',
			'date_query' => array(
				array(
					'after' => '24 hours ago',
				),
			),
		)
	);

	if ( empty( $pending_orders ) ) {
		return;
	}

	foreach ( $pending_orders as $order ) {
		$link_id = $order->get_meta( Gstore_Blu_Payment_Gateway::META_LINK_ID );
		
		if ( empty( $link_id ) ) {
			continue;
		}

		// Consulta status na Blu
		$response = $gateway->consult_payment_link( $link_id );
		
		if ( is_wp_error( $response ) ) {
			continue;
		}

		// Atualiza pedido com resposta
		gstore_blu_apply_status_from_response( $order, $response, false );
	}
}
add_action( 'gstore_blu_check_pending_links', 'gstore_blu_check_pending_links' );

/**
 * Limpa o cron quando necessário.
 * Nota: Em um tema, não há hook de desativação automático.
 */
function gstore_blu_cleanup_cron() {
	$timestamp = wp_next_scheduled( 'gstore_blu_check_pending_links' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'gstore_blu_check_pending_links' );
	}
}


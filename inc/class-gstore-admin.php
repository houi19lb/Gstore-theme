<?php
/**
 * Classe responsável por funcionalidades administrativas do tema.
 * Adiciona página de configurações para regeneração de thumbnails.
 *
 * @package Gstore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gstore_Admin {

	/**
	 * Construtor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_gstore_regenerate_thumbnails', array( $this, 'ajax_regenerate_thumbnails' ) );
	}

	/**
	 * Adiciona o menu no admin.
	 */
	public function add_admin_menu() {
		add_theme_page(
			'GStore Media',
			'GStore Media',
			'manage_options',
			'gstore-media',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enfileira scripts e estilos necessários.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'appearance_page_gstore-media' !== $hook ) {
			return;
		}

		// Adiciona CSS básico inline
		wp_add_inline_style( 'common', '
			.gstore-progress-wrapper {
				margin-top: 20px;
				background: #f0f0f1;
				border-radius: 4px;
				overflow: hidden;
				display: none;
				border: 1px solid #c3c4c7;
			}
			.gstore-progress-bar {
				height: 24px;
				width: 0;
				background: #2271b1;
				transition: width 0.2s;
			}
			.gstore-progress-text {
				margin-top: 10px;
				font-size: 14px;
				color: #3c434a;
			}
			.gstore-log {
				margin-top: 20px;
				background: #fff;
				border: 1px solid #c3c4c7;
				padding: 10px;
				height: 200px;
				overflow-y: auto;
				font-family: monospace;
				display: none;
			}
			.gstore-log-item {
				border-bottom: 1px solid #f0f0f1;
				padding: 4px 0;
			}
			.gstore-log-item.success { color: #00a32a; }
			.gstore-log-item.error { color: #d63638; }
		' );
	}

	/**
	 * Renderiza a página de administração.
	 */
	public function render_admin_page() {
		?>
		<div class="wrap">
			<h1>GStore Media Tools</h1>
			<p>Ferramentas para manutenção de mídia do tema GStore.</p>

			<div class="card">
				<h2>Regenerar Miniaturas (Thumbnails)</h2>
				<p>Use esta ferramenta para regenerar as miniaturas das imagens de produto. Isso é útil após alterar as configurações de corte (crop) ou tamanho.</p>
				<p><strong>Atenção:</strong> O processo pode levar alguns minutos dependendo da quantidade de produtos. Mantenha esta página aberta.</p>
				
				<button id="gstore-start-regeneration" class="button button-primary">Iniciar Regeneração</button>
				<button id="gstore-stop-regeneration" class="button button-secondary" style="display:none;">Parar</button>

				<div class="gstore-progress-wrapper">
					<div class="gstore-progress-bar"></div>
				</div>
				<div class="gstore-progress-text"></div>
				
				<div class="gstore-log"></div>
			</div>

			<script>
			jQuery(document).ready(function($) {
				var isProcessing = false;
				var itemsToProcess = [];
				var totalItems = 0;
				var processedItems = 0;

				function log(message, type = '') {
					$('.gstore-log').show().append('<div class="gstore-log-item ' + type + '">' + message + '</div>');
					var logDiv = $('.gstore-log')[0];
					logDiv.scrollTop = logDiv.scrollHeight;
				}

				function updateProgress() {
					if (totalItems > 0) {
						var percent = (processedItems / totalItems) * 100;
						$('.gstore-progress-bar').css('width', percent + '%');
						$('.gstore-progress-text').text('Processado ' + processedItems + ' de ' + totalItems + ' (' + Math.round(percent) + '%)');
					}
				}

				function processNext() {
					if (!isProcessing || itemsToProcess.length === 0) {
						isProcessing = false;
						$('#gstore-start-regeneration').show();
						$('#gstore-stop-regeneration').hide();
						log('Processo finalizado.', 'success');
						return;
					}

					var imageId = itemsToProcess.shift();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'gstore_regenerate_thumbnails',
							image_id: imageId
						},
						success: function(response) {
							if (response.success) {
								log('Imagem ID ' + imageId + ' regenerada com sucesso.', 'success');
							} else {
								log('Erro ao regenerar imagem ID ' + imageId + ': ' + (response.data || 'Erro desconhecido'), 'error');
							}
							processedItems++;
							updateProgress();
							processNext();
						},
						error: function() {
							log('Erro de conexão ao processar ID ' + imageId, 'error');
							processedItems++;
							updateProgress();
							processNext();
						}
					});
				}

				$('#gstore-start-regeneration').on('click', function() {
					$(this).hide();
					$('#gstore-stop-regeneration').show();
					$('.gstore-progress-wrapper').show();
					$('.gstore-log').html('').show();
					
					log('Buscando imagens de produtos...');
					isProcessing = true;

					// Passo 1: Buscar todos os IDs de imagens de produto
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'gstore_regenerate_thumbnails',
							step: 'get_ids'
						},
						success: function(response) {
							if (response.success && response.data.ids) {
								itemsToProcess = response.data.ids;
								totalItems = itemsToProcess.length;
								processedItems = 0;
								
								log('Encontradas ' + totalItems + ' imagens. Iniciando processamento...');
								updateProgress();
								processNext();
							} else {
								log('Nenhuma imagem encontrada ou erro ao buscar IDs.', 'error');
								isProcessing = false;
								$('#gstore-start-regeneration').show();
							}
						},
						error: function() {
							log('Erro fatal ao buscar IDs.', 'error');
							isProcessing = false;
							$('#gstore-start-regeneration').show();
						}
					});
				});

				$('#gstore-stop-regeneration').on('click', function() {
					isProcessing = false;
					log('Processo interrompido pelo usuário.', 'error');
					$(this).hide();
					$('#gstore-start-regeneration').show();
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * Processa o AJAX de regeneração.
	 */
	public function ajax_regenerate_thumbnails() {
		// Verifica permissões (se necessário, adicione check_ajax_referer)
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sem permissão.' );
		}

		$step = isset( $_POST['step'] ) ? $_POST['step'] : 'process';

		// Passo 1: Retorna lista de IDs
		if ( 'get_ids' === $step ) {
			// Busca imagens anexadas a produtos ou que são produto (attachments)
			// Uma query simples para pegar todos os attachments que são imagem
			// Idealmente filtraríamos apenas imagens usadas em produtos, mas isso é complexo.
			// Vamos pegar todas as imagens por simplicidade e robustez.
			$query_images = new WP_Query( array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			wp_send_json_success( array( 'ids' => $query_images->posts ) );
		}

		// Passo 2: Regenera uma imagem específica
		$image_id = isset( $_POST['image_id'] ) ? intval( $_POST['image_id'] ) : 0;

		if ( ! $image_id ) {
			wp_send_json_error( 'ID inválido.' );
		}

		$fullsizepath = get_attached_file( $image_id );

		if ( false === $fullsizepath || ! file_exists( $fullsizepath ) ) {
			wp_send_json_error( 'Arquivo não encontrado: ' . $image_id );
		}

		// Regenera os metadados (cria os novos tamanhos)
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}

		$metadata = wp_generate_attachment_metadata( $image_id, $fullsizepath );

		if ( is_wp_error( $metadata ) ) {
			wp_send_json_error( $metadata->get_error_message() );
		}

		if ( empty( $metadata ) ) {
			wp_send_json_error( 'Falha desconhecida ao gerar metadados.' );
		}

		// Atualiza o banco de dados
		wp_update_attachment_metadata( $image_id, $metadata );

		wp_send_json_success( 'Regenerado.' );
	}
}

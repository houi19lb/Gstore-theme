<?php
/**
 * Classe responsável por gerenciar logs de debug do tema.
 * Salva logs em arquivo e fornece interface no admin para visualização.
 *
 * @package Gstore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gstore_Debug_Logger {

	/**
	 * Caminho do arquivo de log.
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Instância única da classe (Singleton).
	 *
	 * @var Gstore_Debug_Logger|null
	 */
	private static $instance = null;

	/**
	 * Construtor.
	 */
	public function __construct() {
		// Usa a pasta de uploads do WordPress para garantir permissões de escrita
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/gstore-debug-logs';
		
		// Cria o diretório se não existir
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		
		$this->log_file = $log_dir . '/debug.log';
		
		// Registra hooks
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_gstore_get_debug_logs', array( $this, 'ajax_get_logs' ) );
		add_action( 'wp_ajax_gstore_clear_debug_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_gstore_debug_log', array( $this, 'ajax_log' ) );
		add_action( 'wp_ajax_nopriv_gstore_debug_log', array( $this, 'ajax_log' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_debug_helper' ) );
	}

	/**
	 * Obtém a instância única da classe.
	 *
	 * @return Gstore_Debug_Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Adiciona menu no admin.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'gstore-setup',
			__( 'Debug Logs', 'gstore' ),
			__( 'Debug Logs', 'gstore' ),
			'manage_options',
			'gstore-debug-logs',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enfileira scripts e estilos.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'gstore-setup_page_gstore-debug-logs' !== $hook ) {
			return;
		}

		wp_add_inline_style( 'common', '
			.gstore-debug-logs-container {
				margin-top: 20px;
			}
			.gstore-debug-logs-viewer {
				background: #1e1e1e;
				color: #d4d4d4;
				padding: 15px;
				border-radius: 4px;
				font-family: "Courier New", Courier, monospace;
				font-size: 12px;
				line-height: 1.6;
				max-height: 600px;
				overflow-y: auto;
				border: 1px solid #3c3c3c;
			}
			.gstore-log-entry {
				margin-bottom: 8px;
				padding: 4px 0;
				border-bottom: 1px solid #2d2d2d;
			}
			.gstore-log-entry:last-child {
				border-bottom: none;
			}
			.gstore-log-timestamp {
				color: #858585;
				margin-right: 10px;
			}
			.gstore-log-location {
				color: #4ec9b0;
				margin-right: 10px;
			}
			.gstore-log-message {
				color: #d4d4d4;
			}
			.gstore-log-data {
				color: #ce9178;
				margin-left: 20px;
				margin-top: 4px;
				white-space: pre-wrap;
				word-break: break-all;
			}
			.gstore-log-actions {
				margin-bottom: 20px;
			}
			.gstore-log-actions .button {
				margin-right: 10px;
			}
			.gstore-log-stats {
				background: #f0f0f1;
				padding: 10px;
				border-radius: 4px;
				margin-bottom: 20px;
			}
		' );
	}

	/**
	 * Renderiza a página de administração.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$log_content = $this->read_logs();
		$log_stats = $this->get_log_stats( $log_content );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( __( 'Debug Logs', 'gstore' ) ); ?></h1>
			<p><?php echo esc_html( __( 'Visualize e gerencie os logs de debug do tema GStore.', 'gstore' ) ); ?></p>

			<div class="gstore-log-stats">
				<strong><?php echo esc_html( __( 'Estatísticas:', 'gstore' ) ); ?></strong>
				<ul style="margin: 10px 0 0 20px;">
					<li><?php echo esc_html( sprintf( __( 'Total de entradas: %d', 'gstore' ), $log_stats['total'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Tamanho do arquivo: %s', 'gstore' ), size_format( $log_stats['size'] ) ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Última atualização: %s', 'gstore' ), $log_stats['last_update'] ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Caminho do arquivo: %s', 'gstore' ), $this->log_file ) ); ?></li>
				</ul>
			</div>

			<div class="gstore-log-actions">
				<button id="gstore-refresh-logs" class="button button-primary"><?php echo esc_html( __( 'Atualizar', 'gstore' ) ); ?></button>
				<button id="gstore-clear-logs" class="button button-secondary"><?php echo esc_html( __( 'Limpar Logs', 'gstore' ) ); ?></button>
				<button id="gstore-download-logs" class="button"><?php echo esc_html( __( 'Download', 'gstore' ) ); ?></button>
				<button id="gstore-copy-logs" class="button"><?php echo esc_html( __( 'Copiar para Chat', 'gstore' ) ); ?></button>
			</div>

			<div class="gstore-debug-logs-container">
				<div id="gstore-debug-logs-viewer" class="gstore-debug-logs-viewer">
					<?php echo $this->format_logs_for_display( $log_content ); ?>
				</div>
			</div>

			<script>
			jQuery(document).ready(function($) {
				function refreshLogs() {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'gstore_get_debug_logs'
						},
						success: function(response) {
							if (response.success) {
								$('#gstore-debug-logs-viewer').html(response.data.html);
								// Scroll para o final
								var viewer = $('#gstore-debug-logs-viewer')[0];
								viewer.scrollTop = viewer.scrollHeight;
							}
						}
					});
				}

				$('#gstore-refresh-logs').on('click', function() {
					refreshLogs();
				});

				$('#gstore-clear-logs').on('click', function() {
					if (!confirm('<?php echo esc_js( __( 'Tem certeza que deseja limpar todos os logs?', 'gstore' ) ); ?>')) {
						return;
					}

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'gstore_clear_debug_logs'
						},
						success: function(response) {
							if (response.success) {
								$('#gstore-debug-logs-viewer').html('<div class="gstore-log-entry"><?php echo esc_js( __( "Logs limpos com sucesso.", "gstore" ) ); ?></div>');
								location.reload();
							} else {
								alert('<?php echo esc_js( __( "Erro ao limpar logs.", "gstore" ) ); ?>');
							}
						}
					});
				});

				$('#gstore-download-logs').on('click', function() {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'gstore_get_debug_logs',
							format: 'raw'
						},
						success: function(response) {
							if (response.success) {
								var blob = new Blob([response.data.content], { type: 'text/plain' });
								var url = window.URL.createObjectURL(blob);
								var a = document.createElement('a');
								a.href = url;
								a.download = 'gstore-debug-' + new Date().getTime() + '.log';
								document.body.appendChild(a);
								a.click();
								document.body.removeChild(a);
								window.URL.revokeObjectURL(url);
							}
						}
					});
				});

				$('#gstore-copy-logs').on('click', function() {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'gstore_get_debug_logs',
							format: 'raw'
						},
						success: function(response) {
							if (response.success) {
								var textarea = document.createElement('textarea');
								textarea.value = response.data.content;
								document.body.appendChild(textarea);
								textarea.select();
								document.execCommand('copy');
								document.body.removeChild(textarea);
								alert('<?php echo esc_js( __( "Logs copiados para a área de transferência! Cole no chat.", "gstore" ) ); ?>');
							}
						}
					});
				});

				// Auto-refresh a cada 5 segundos
				setInterval(refreshLogs, 5000);
			});
			</script>
		</div>
		<?php
	}

	/**
	 * Registra rotas REST para acesso externo.
	 */
	public function register_rest_routes() {
		register_rest_route( 'gstore/v1', '/debug-logs', array(
			'methods' => 'GET',
			'callback' => array( $this, 'rest_get_logs' ),
			'permission_callback' => array( $this, 'rest_permission_check' ),
		) );
	}

	/**
	 * Verifica permissões para REST API.
	 */
	public function rest_permission_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Retorna logs via REST API.
	 */
	public function rest_get_logs( $request ) {
		$format = $request->get_param( 'format' ) ?: 'json';
		$log_content = $this->read_logs();

		if ( 'raw' === $format ) {
			return new WP_REST_Response( $log_content, 200, array( 'Content-Type' => 'text/plain' ) );
		}

		$entries = $this->parse_logs( $log_content );
		return new WP_REST_Response( array(
			'entries' => $entries,
			'stats' => $this->get_log_stats( $log_content ),
		), 200 );
	}

	/**
	 * Retorna logs via AJAX.
	 */
	public function ajax_get_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sem permissão.' );
		}

		$format = isset( $_POST['format'] ) ? $_POST['format'] : 'html';
		$log_content = $this->read_logs();

		if ( 'raw' === $format ) {
			wp_send_json_success( array( 'content' => $log_content ) );
		}

		wp_send_json_success( array(
			'html' => $this->format_logs_for_display( $log_content ),
			'entries' => $this->parse_logs( $log_content ),
		) );
	}

	/**
	 * Limpa os logs via AJAX.
	 */
	public function ajax_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sem permissão.' );
		}

		if ( file_exists( $this->log_file ) ) {
			@unlink( $this->log_file );
		}

		wp_send_json_success( 'Logs limpos.' );
	}

	/**
	 * Recebe logs via AJAX (para uso em JavaScript).
	 */
	public function ajax_log() {
		$location = isset( $_POST['location'] ) ? sanitize_text_field( $_POST['location'] ) : 'unknown:0';
		$message = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';
		$data = isset( $_POST['data'] ) ? json_decode( stripslashes( $_POST['data'] ), true ) : array();
		$session_id = isset( $_POST['sessionId'] ) ? sanitize_text_field( $_POST['sessionId'] ) : 'debug-session';
		$run_id = isset( $_POST['runId'] ) ? sanitize_text_field( $_POST['runId'] ) : 'run1';
		$hypothesis_id = isset( $_POST['hypothesisId'] ) ? sanitize_text_field( $_POST['hypothesisId'] ) : '';

		if ( empty( $message ) ) {
			wp_send_json_error( 'Mensagem vazia.' );
		}

		$this->log( $location, $message, $data, $session_id, $run_id, $hypothesis_id );
		wp_send_json_success( 'Log registrado.' );
	}

	/**
	 * Enfileira helper JavaScript para debug logging.
	 */
	public function enqueue_debug_helper() {
		// Adiciona script inline com helper global
		$ajax_url = admin_url( 'admin-ajax.php' );
		$script = "
		(function() {
			window.gstoreDebugLog = function(location, message, data, sessionId, runId, hypothesisId) {
				var payload = {
					action: 'gstore_debug_log',
					location: location || (window.location.pathname + ':' + (new Error().stack.split('\\n')[2] || '0')),
					message: message || '',
					data: data || {},
					sessionId: sessionId || 'debug-session',
					runId: runId || 'run1',
					hypothesisId: hypothesisId || ''
				};
				
				if (typeof data === 'object') {
					payload.data = JSON.stringify(data);
				}
				
				fetch('{$ajax_url}', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: Object.keys(payload).map(function(key) {
						return encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]);
					}).join('&')
				}).catch(function() {
					// Silenciosamente falha se não conseguir enviar
				});
			};
		})();
		";
		wp_add_inline_script( 'jquery', $script, 'before' );
	}

	/**
	 * Escreve uma entrada de log.
	 *
	 * @param string $location Localização (arquivo:linha).
	 * @param string $message Mensagem.
	 * @param array  $data Dados adicionais.
	 * @param string $session_id ID da sessão.
	 * @param string $run_id ID da execução.
	 * @param string $hypothesis_id ID da hipótese.
	 */
	public function log( $location, $message, $data = array(), $session_id = 'debug-session', $run_id = 'run1', $hypothesis_id = '' ) {
		$entry = array(
			'id' => 'log_' . time() . '_' . wp_generate_password( 6, false ),
			'timestamp' => time() * 1000, // milissegundos
			'location' => $location,
			'message' => $message,
			'data' => $data,
			'sessionId' => $session_id,
			'runId' => $run_id,
			'hypothesisId' => $hypothesis_id,
		);

		$log_line = wp_json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
		
		// Escreve no arquivo (append mode)
		@file_put_contents( $this->log_file, $log_line, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Lê todos os logs do arquivo.
	 *
	 * @return string Conteúdo do arquivo de log.
	 */
	private function read_logs() {
		if ( ! file_exists( $this->log_file ) ) {
			return '';
		}

		$content = @file_get_contents( $this->log_file );
		return $content ? $content : '';
	}

	/**
	 * Parse dos logs em formato NDJSON.
	 *
	 * @param string $content Conteúdo do arquivo.
	 * @return array Array de entradas de log.
	 */
	private function parse_logs( $content ) {
		$entries = array();
		$lines = explode( "\n", trim( $content ) );

		foreach ( $lines as $line ) {
			if ( empty( trim( $line ) ) ) {
				continue;
			}

			$entry = json_decode( $line, true );
			if ( $entry ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Formata logs para exibição HTML.
	 *
	 * @param string $content Conteúdo do arquivo.
	 * @return string HTML formatado.
	 */
	private function format_logs_for_display( $content ) {
		$entries = $this->parse_logs( $content );

		if ( empty( $entries ) ) {
			return '<div class="gstore-log-entry">Nenhum log encontrado.</div>';
		}

		$html = '';
		foreach ( $entries as $entry ) {
			$timestamp = isset( $entry['timestamp'] ) ? date( 'Y-m-d H:i:s', $entry['timestamp'] / 1000 ) : '';
			$location = isset( $entry['location'] ) ? esc_html( $entry['location'] ) : '';
			$message = isset( $entry['message'] ) ? esc_html( $entry['message'] ) : '';
			$data = isset( $entry['data'] ) ? $entry['data'] : array();
			$hypothesis = isset( $entry['hypothesisId'] ) ? esc_html( $entry['hypothesisId'] ) : '';

			$html .= '<div class="gstore-log-entry">';
			$html .= '<span class="gstore-log-timestamp">[' . esc_html( $timestamp ) . ']</span>';
			if ( $location ) {
				$html .= '<span class="gstore-log-location">' . $location . '</span>';
			}
			if ( $hypothesis ) {
				$html .= '<span style="color: #569cd6;">[H:' . $hypothesis . ']</span> ';
			}
			$html .= '<span class="gstore-log-message">' . $message . '</span>';
			
			if ( ! empty( $data ) ) {
				$html .= '<div class="gstore-log-data">' . esc_html( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</div>';
			}
			
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Obtém estatísticas dos logs.
	 *
	 * @param string $content Conteúdo do arquivo.
	 * @return array Estatísticas.
	 */
	private function get_log_stats( $content ) {
		$entries = $this->parse_logs( $content );
		$file_size = file_exists( $this->log_file ) ? filesize( $this->log_file ) : 0;
		$last_update = file_exists( $this->log_file ) ? date( 'Y-m-d H:i:s', filemtime( $this->log_file ) ) : __( 'Nunca', 'gstore' );

		return array(
			'total' => count( $entries ),
			'size' => $file_size,
			'last_update' => $last_update,
		);
	}

	/**
	 * Retorna o caminho do arquivo de log (para uso externo).
	 *
	 * @return string Caminho do arquivo.
	 */
	public function get_log_file_path() {
		return $this->log_file;
	}
}

// Inicializa a classe
$GLOBALS['gstore_debug_logger'] = Gstore_Debug_Logger::get_instance();

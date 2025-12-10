<?php
/**
 * Classe para gerenciar a interface de administração do plugin
 *
 * @package GStore_Optimizer
 */

// Previne acesso direto
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe GStore_Optimizer_Admin
 */
class GStore_Optimizer_Admin {

	/**
	 * Construtor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Adiciona menu no admin
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'GStore Optimizer', 'gstore-optimizer' ),
			__( 'GStore Optimizer', 'gstore-optimizer' ),
			'manage_options',
			'gstore-optimizer',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registra as configurações
	 */
	public function register_settings() {
		// Seção: Features
		add_settings_section(
			'gstore_optimizer_features',
			__( 'Funcionalidades', 'gstore-optimizer' ),
			array( $this, 'render_features_section' ),
			'gstore-optimizer'
		);

		// Campo: WebP Conversion
		add_settings_field(
			'gstore_optimizer_webp_conversion',
			__( 'Conversão para WebP', 'gstore-optimizer' ),
			array( $this, 'render_webp_conversion_field' ),
			'gstore-optimizer',
			'gstore_optimizer_features'
		);
		register_setting( 'gstore_optimizer_settings', 'gstore_optimizer_webp_conversion', array(
			'type' => 'boolean',
			'default' => true,
		) );

		// Campo: Cache Headers
		add_settings_field(
			'gstore_optimizer_cache_headers',
			__( 'Headers de Cache HTTP', 'gstore-optimizer' ),
			array( $this, 'render_cache_headers_field' ),
			'gstore-optimizer',
			'gstore_optimizer_features'
		);
		register_setting( 'gstore_optimizer_settings', 'gstore_optimizer_cache_headers', array(
			'type' => 'boolean',
			'default' => true,
		) );

		// Campo: Minify Assets
		add_settings_field(
			'gstore_optimizer_minify_assets',
			__( 'Minificação de Assets', 'gstore-optimizer' ),
			array( $this, 'render_minify_assets_field' ),
			'gstore-optimizer',
			'gstore_optimizer_features'
		);
		register_setting( 'gstore_optimizer_settings', 'gstore_optimizer_minify_assets', array(
			'type' => 'boolean',
			'default' => true,
		) );

		// Campo: Lazy Loading
		add_settings_field(
			'gstore_optimizer_lazy_loading',
			__( 'Lazy Loading Avançado', 'gstore-optimizer' ),
			array( $this, 'render_lazy_loading_field' ),
			'gstore-optimizer',
			'gstore_optimizer_features'
		);
		register_setting( 'gstore_optimizer_settings', 'gstore_optimizer_lazy_loading', array(
			'type' => 'boolean',
			'default' => true,
		) );

		// Campo: Script Optimization
		add_settings_field(
			'gstore_optimizer_script_optimization',
			__( 'Otimização de Scripts', 'gstore-optimizer' ),
			array( $this, 'render_script_optimization_field' ),
			'gstore-optimizer',
			'gstore_optimizer_features'
		);
		register_setting( 'gstore_optimizer_settings', 'gstore_optimizer_script_optimization', array(
			'type' => 'boolean',
			'default' => true,
		) );

		// Campo: Script Optimization
		add_settings_field(
			'gstore_optimizer_script_optimization',
			__( 'Otimização de Scripts', 'gstore-optimizer' ),
			array( $this, 'render_script_optimization_field' ),
			'gstore-optimizer',
			'gstore_optimizer_features'
		);
		register_setting( 'gstore_optimizer_settings', 'gstore_optimizer_script_optimization', array(
			'type' => 'boolean',
			'default' => true,
		) );

		// Seção: Configurações Avançadas
		add_settings_section(
			'gstore_optimizer_advanced',
			__( 'Configurações Avançadas', 'gstore-optimizer' ),
			array( $this, 'render_advanced_section' ),
			'gstore-optimizer'
		);

		// Campo: Qualidade WebP
		add_settings_field(
			'gstore_optimizer_webp_quality',
			__( 'Qualidade WebP', 'gstore-optimizer' ),
			array( $this, 'render_webp_quality_field' ),
			'gstore-optimizer',
			'gstore_optimizer_advanced'
		);
		register_setting( 'gstore_optimizer_settings', 'gstore_optimizer_webp_quality', array(
			'type' => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_webp_quality' ),
			'default' => 80,
		) );

		// Campo: Qualidade AVIF
		add_settings_field(
			'gstore_optimizer_avif_quality',
			__( 'Qualidade AVIF', 'gstore-optimizer' ),
			array( $this, 'render_avif_quality_field' ),
			'gstore-optimizer',
			'gstore_optimizer_advanced'
		);
		register_setting( 'gstore_optimizer_settings', 'gstore_optimizer_avif_quality', array(
			'type' => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_avif_quality' ),
			'default' => 75,
		) );
	}

	/**
	 * Renderiza seção de features
	 */
	public function render_features_section() {
		echo '<p>' . esc_html__( 'Ative ou desative as funcionalidades de otimização do plugin.', 'gstore-optimizer' ) . '</p>';
	}

	/**
	 * Renderiza campo de WebP Conversion
	 */
	public function render_webp_conversion_field() {
		$value = get_option( 'gstore_optimizer_webp_conversion', true );
		$gd_available = function_exists( 'imagewebp' );
		?>
		<label>
			<input type="checkbox" name="gstore_optimizer_webp_conversion" value="1" <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Converter imagens automaticamente para WebP', 'gstore-optimizer' ); ?>
		</label>
		<?php if ( ! $gd_available ) : ?>
			<p class="description" style="color: #d63638;">
				<strong><?php esc_html_e( 'Aviso:', 'gstore-optimizer' ); ?></strong>
				<?php esc_html_e( 'A extensão GD do PHP não está disponível. A conversão WebP não funcionará.', 'gstore-optimizer' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Converte automaticamente imagens JPG, PNG e GIF para WebP no upload.', 'gstore-optimizer' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renderiza campo de Cache Headers
	 */
	public function render_cache_headers_field() {
		$value = get_option( 'gstore_optimizer_cache_headers', true );
		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		?>
		<label>
			<input type="checkbox" name="gstore_optimizer_cache_headers" value="1" <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Adicionar headers HTTP de cache', 'gstore-optimizer' ); ?>
		</label>
		<?php if ( $is_debug ) : ?>
			<p class="description" style="color: #d63638;">
				<strong><?php esc_html_e( 'Aviso:', 'gstore-optimizer' ); ?></strong>
				<?php esc_html_e( 'WP_DEBUG está ativo. Cache headers serão desabilitados automaticamente.', 'gstore-optimizer' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Adiciona headers de cache para imagens (1 ano), CSS/JS (1 mês) e fontes (1 ano).', 'gstore-optimizer' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renderiza campo de Minify Assets
	 */
	public function render_minify_assets_field() {
		$value = get_option( 'gstore_optimizer_minify_assets', true );
		$is_script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		?>
		<label>
			<input type="checkbox" name="gstore_optimizer_minify_assets" value="1" <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Minificar CSS e JavaScript inline', 'gstore-optimizer' ); ?>
		</label>
		<?php if ( $is_script_debug ) : ?>
			<p class="description" style="color: #d63638;">
				<strong><?php esc_html_e( 'Aviso:', 'gstore-optimizer' ); ?></strong>
				<?php esc_html_e( 'SCRIPT_DEBUG está ativo. Minificação será desabilitada automaticamente.', 'gstore-optimizer' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Remove comentários e espaços desnecessários de CSS e JavaScript inline.', 'gstore-optimizer' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renderiza campo de Lazy Loading
	 */
	public function render_lazy_loading_field() {
		$value = get_option( 'gstore_optimizer_lazy_loading', true );
		?>
		<label>
			<input type="checkbox" name="gstore_optimizer_lazy_loading" value="1" <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Lazy loading avançado para imagens', 'gstore-optimizer' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Adiciona lazy loading automático a imagens abaixo da dobra e preload de recursos críticos.', 'gstore-optimizer' ); ?>
		</p>
		<?php
	}

	/**
	 * Renderiza campo de Script Optimization
	 */
	public function render_script_optimization_field() {
		$value = get_option( 'gstore_optimizer_script_optimization', true );
		?>
		<label>
			<input type="checkbox" name="gstore_optimizer_script_optimization" value="1" <?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Otimização de scripts JavaScript', 'gstore-optimizer' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Aplica defer/async em scripts não críticos, adiciona passive event listeners e remove scripts legacy desnecessários.', 'gstore-optimizer' ); ?>
		</p>
		<?php
	}

	/**
	 * Renderiza seção avançada
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Configure opções avançadas de otimização.', 'gstore-optimizer' ) . '</p>';
	}

	/**
	 * Renderiza campo de Qualidade WebP
	 */
	public function render_webp_quality_field() {
		$value = get_option( 'gstore_optimizer_webp_quality', 80 );
		?>
		<input type="number" name="gstore_optimizer_webp_quality" value="<?php echo esc_attr( $value ); ?>" min="0" max="100" step="1" class="small-text">
		<p class="description">
			<?php esc_html_e( 'Qualidade da conversão WebP (0-100). Valores mais altos = melhor qualidade, mas arquivos maiores. Padrão: 80', 'gstore-optimizer' ); ?>
		</p>
		<?php
	}

	/**
	 * Renderiza campo de Qualidade AVIF
	 */
	public function render_avif_quality_field() {
		$value = get_option( 'gstore_optimizer_avif_quality', 75 );
		$imagick_available = extension_loaded( 'imagick' );
		?>
		<input type="number" name="gstore_optimizer_avif_quality" value="<?php echo esc_attr( $value ); ?>" min="0" max="100" step="1" class="small-text" <?php echo $imagick_available ? '' : 'disabled'; ?>>
		<?php if ( ! $imagick_available ) : ?>
			<p class="description" style="color: #d63638;">
				<strong><?php esc_html_e( 'Aviso:', 'gstore-optimizer' ); ?></strong>
				<?php esc_html_e( 'A extensão ImageMagick do PHP não está disponível. A conversão AVIF não funcionará.', 'gstore-optimizer' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Qualidade da conversão AVIF (0-100). Valores mais altos = melhor qualidade, mas arquivos maiores. Padrão: 75', 'gstore-optimizer' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renderiza campo de Qualidade AVIF
	 */
	public function render_avif_quality_field() {
		$value = get_option( 'gstore_optimizer_avif_quality', 75 );
		?>
		<input type="number" name="gstore_optimizer_avif_quality" value="<?php echo esc_attr( $value ); ?>" min="0" max="100" step="1" class="small-text">
		<p class="description">
			<?php esc_html_e( 'Qualidade da conversão AVIF (0-100). Valores mais altos = melhor qualidade, mas arquivos maiores. Padrão: 75. Requer ImageMagick com suporte a AVIF.', 'gstore-optimizer' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitiza valor de qualidade WebP
	 *
	 * @param int $value Valor a sanitizar
	 * @return int
	 */
	public function sanitize_webp_quality( $value ) {
		$value = absint( $value );
		return max( 0, min( 100, $value ) );
	}

	/**
	 * Sanitiza valor de qualidade AVIF
	 *
	 * @param int $value Valor a sanitizar
	 * @return int
	 */
	public function sanitize_avif_quality( $value ) {
		$value = absint( $value );
		return max( 0, min( 100, $value ) );
	}

	/**
	 * Sanitiza valor de qualidade AVIF
	 *
	 * @param int $value Valor a sanitizar
	 * @return int
	 */
	public function sanitize_avif_quality( $value ) {
		$value = absint( $value );
		return max( 0, min( 100, $value ) );
	}

	/**
	 * Renderiza página de configurações
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Verifica se o formulário foi submetido
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'gstore_optimizer_messages',
				'gstore_optimizer_message',
				__( 'Configurações salvas com sucesso!', 'gstore-optimizer' ),
				'updated'
			);
		}

		settings_errors( 'gstore_optimizer_messages' );

		// Estatísticas
		$webp_count = $this->get_webp_count();
		$gd_available = function_exists( 'imagewebp' );
		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$is_script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="gstore-optimizer-header">
				<p><?php esc_html_e( 'Configure as otimizações de performance do GStore Optimizer. Todas as funcionalidades são ativadas por padrão.', 'gstore-optimizer' ); ?></p>
			</div>

			<div class="gstore-optimizer-status">
				<h2><?php esc_html_e( 'Status do Sistema', 'gstore-optimizer' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Extensão GD (WebP)', 'gstore-optimizer' ); ?></th>
						<td>
							<?php if ( $gd_available ) : ?>
								<span style="color: #00a32a;">✓ <?php esc_html_e( 'Disponível', 'gstore-optimizer' ); ?></span>
							<?php else : ?>
								<span style="color: #d63638;">✗ <?php esc_html_e( 'Não disponível', 'gstore-optimizer' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WP_DEBUG', 'gstore-optimizer' ); ?></th>
						<td>
							<?php if ( $is_debug ) : ?>
								<span style="color: #d63638;"><?php esc_html_e( 'Ativo', 'gstore-optimizer' ); ?></span>
								<p class="description"><?php esc_html_e( 'Cache headers serão desabilitados automaticamente.', 'gstore-optimizer' ); ?></p>
							<?php else : ?>
								<span style="color: #00a32a;"><?php esc_html_e( 'Desativado', 'gstore-optimizer' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'SCRIPT_DEBUG', 'gstore-optimizer' ); ?></th>
						<td>
							<?php if ( $is_script_debug ) : ?>
								<span style="color: #d63638;"><?php esc_html_e( 'Ativo', 'gstore-optimizer' ); ?></span>
								<p class="description"><?php esc_html_e( 'Minificação será desabilitada automaticamente.', 'gstore-optimizer' ); ?></p>
							<?php else : ?>
								<span style="color: #00a32a;"><?php esc_html_e( 'Desativado', 'gstore-optimizer' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $webp_count > 0 ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Imagens WebP Geradas', 'gstore-optimizer' ); ?></th>
						<td>
							<strong><?php echo esc_html( number_format_i18n( $webp_count ) ); ?></strong>
							<p class="description"><?php esc_html_e( 'Total de arquivos WebP gerados pelo plugin.', 'gstore-optimizer' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
				</table>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'gstore_optimizer_settings' );
				do_settings_sections( 'gstore-optimizer' );
				submit_button( __( 'Salvar Configurações', 'gstore-optimizer' ) );
				?>
			</form>
		</div>

		<style>
			.gstore-optimizer-header {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-left-width: 4px;
				border-left-color: #2271b1;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				padding: 1px 12px;
				margin: 20px 0;
			}
			.gstore-optimizer-status {
				background: #fff;
				border: 1px solid #c3c4c7;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				padding: 20px;
				margin: 20px 0;
			}
			.gstore-optimizer-status h2 {
				margin-top: 0;
			}
		</style>
		<?php
	}

	/**
	 * Conta arquivos WebP gerados
	 *
	 * @return int
	 */
	private function get_webp_count() {
		$upload_dir = wp_upload_dir();
		$count = get_transient( 'gstore_optimizer_webp_count' );

		if ( false === $count ) {
			$count = 0;
			$upload_path = $upload_dir['basedir'];

			if ( is_dir( $upload_path ) ) {
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $upload_path, RecursiveDirectoryIterator::SKIP_DOTS )
				);

				foreach ( $iterator as $file ) {
					if ( $file->isFile() && strtolower( $file->getExtension() ) === 'webp' ) {
						$count++;
					}
				}
			}

			// Cache por 1 hora
			set_transient( 'gstore_optimizer_webp_count', $count, HOUR_IN_SECONDS );
		}

		return $count;
	}

	/**
	 * Enfileira scripts do admin
	 *
	 * @param string $hook_suffix Hook do admin
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'settings_page_gstore-optimizer' !== $hook_suffix ) {
			return;
		}
		// Scripts adicionais podem ser adicionados aqui se necessário
	}
}


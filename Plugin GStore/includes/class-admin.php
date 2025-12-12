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
		add_action( 'admin_post_gstore_reset_templates', array( $this, 'handle_reset_templates' ) );
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

		// Seção: Ferramentas de Manutenção
		add_settings_section(
			'gstore_optimizer_tools',
			__( 'Ferramentas de Manutenção', 'gstore-optimizer' ),
			array( $this, 'render_tools_section' ),
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
	 * Renderiza seção de ferramentas
	 */
	public function render_tools_section() {
		echo '<p>' . esc_html__( 'Ferramentas úteis para manutenção e resolução de problemas do tema.', 'gstore-optimizer' ) . '</p>';
		
		// Botão para resetar templates customizados
		$reset_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=gstore_reset_templates' ),
			'gstore_reset_templates',
			'gstore_reset_nonce'
		);
		?>
		<div class="gstore-tools-section">
			<h3><?php esc_html_e( 'Resetar Templates Customizados', 'gstore-optimizer' ); ?></h3>
			<p>
				<?php esc_html_e( 'Remove templates customizados salvos no banco de dados que podem estar sobrescrevendo os arquivos do tema. Isso força o WordPress a usar os templates do tema novamente.', 'gstore-optimizer' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Atenção:', 'gstore-optimizer' ); ?></strong>
				<?php esc_html_e( 'Esta ação não pode ser desfeita. Todos os templates customizados serão removidos permanentemente.', 'gstore-optimizer' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $reset_url ); ?>" 
				   class="button button-secondary" 
				   onclick="return confirm('<?php echo esc_js( __( 'Tem certeza que deseja resetar todos os templates customizados? Esta ação não pode ser desfeita.', 'gstore-optimizer' ) ); ?>');">
					<?php esc_html_e( 'Resetar Templates Customizados', 'gstore-optimizer' ); ?>
				</a>
			</p>
		</div>
		<?php
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

		// Verifica se templates foram resetados
		if ( isset( $_GET['templates_reset'] ) && 'yes' === $_GET['templates_reset'] ) {
			$deleted_count = isset( $_GET['deleted_count'] ) ? absint( $_GET['deleted_count'] ) : 0;
			$errors_count = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0;

			if ( $deleted_count > 0 ) {
				add_settings_error(
					'gstore_optimizer_messages',
					'gstore_optimizer_templates_reset',
					sprintf(
						/* translators: %d: Number of templates deleted */
						_n(
							'%d template customizado foi removido com sucesso!',
							'%d templates customizados foram removidos com sucesso!',
							$deleted_count,
							'gstore-optimizer'
						),
						$deleted_count
					),
					'success'
				);
			}

			if ( $errors_count > 0 ) {
				add_settings_error(
					'gstore_optimizer_messages',
					'gstore_optimizer_templates_errors',
					sprintf(
						/* translators: %d: Number of errors */
						_n(
							'Ocorreu %d erro ao processar os templates.',
							'Ocorreram %d erros ao processar os templates.',
							$errors_count,
							'gstore-optimizer'
						),
						$errors_count
					),
					'error'
				);
			}

			if ( 0 === $deleted_count && 0 === $errors_count ) {
				add_settings_error(
					'gstore_optimizer_messages',
					'gstore_optimizer_templates_no_custom',
					__( 'Nenhum template customizado foi encontrado para remover.', 'gstore-optimizer' ),
					'info'
				);
			}
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
			.gstore-tools-section {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-left-width: 4px;
				border-left-color: #d63638;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				padding: 15px 20px;
				margin: 20px 0;
			}
			.gstore-tools-section h3 {
				margin-top: 0;
				color: #1d2327;
			}
			.gstore-tools-section p {
				margin: 10px 0;
			}
			.gstore-tools-section .button {
				margin-top: 10px;
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

	/**
	 * Processa requisição para resetar templates customizados
	 */
	public function handle_reset_templates() {
		// Verifica permissões
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para executar esta ação.', 'gstore-optimizer' ) );
		}

		// Verifica nonce
		if ( ! isset( $_GET['gstore_reset_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['gstore_reset_nonce'] ) ), 'gstore_reset_templates' ) ) {
			wp_die( esc_html__( 'Falha na verificação de segurança.', 'gstore-optimizer' ) );
		}

		// Lista de templates que devem ser resetados
		$templates_to_reset = array(
			'page-blog',
			'page-atendimento',
			'page-carrinho',
			'page-checkout',
			'page-home',
			'page-loja',
			'page-ofertas',
			'page-catalogo',
			'page-como-comprar-arma',
		);

		$deleted_count = 0;
		$errors = array();

		foreach ( $templates_to_reset as $template_slug ) {
			// Busca templates customizados no banco de dados
			// Templates customizados têm o formato: {theme}//{template-slug}
			$theme = wp_get_theme()->get_stylesheet();
			$template_id = $theme . '//' . $template_slug;

			// Usa WP_Query para buscar templates customizados
			$query = new WP_Query( array(
				'post_type'      => 'wp_template',
				'post_status'    => array( 'publish', 'auto-draft', 'draft' ),
				'posts_per_page' => -1,
				'name'           => $template_slug,
				'meta_query'     => array(
					array(
						'key'   => '_wp_template_file',
						'value' => $template_slug,
					),
				),
			) );

			if ( $query->have_posts() ) {
				foreach ( $query->posts as $template_post ) {
					// Verifica se é uma customização
					$is_custom = get_post_meta( $template_post->ID, '_wp_is_custom', true );
					$template_file = get_post_meta( $template_post->ID, '_wp_template_file', true );

					// Deleta apenas se for customizado (salvo no banco) ou se não corresponder ao arquivo do tema
					if ( $is_custom || ( $template_file === $template_slug && ! $this->template_file_exists( $template_file ) ) ) {
						$result = wp_delete_post( $template_post->ID, true );
						if ( $result ) {
							$deleted_count++;
						} else {
							$errors[] = sprintf(
								/* translators: %s: Template slug */
								__( 'Erro ao deletar template: %s', 'gstore-optimizer' ),
								$template_slug
							);
						}
					}
				}
			}

			// Também tenta deletar usando a API de templates do WordPress (se disponível)
			if ( function_exists( 'wp_delete_post_revision' ) ) {
				// Busca por slug completo
				$full_template = get_block_template( $template_id, 'wp_template' );
				if ( $full_template && $full_template->wp_id ) {
					$result = wp_delete_post( $full_template->wp_id, true );
					if ( $result ) {
						$deleted_count++;
					}
				}
			}
		}

		// Limpa cache de templates
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'themes' );
		}

		// Redireciona com mensagem de sucesso/erro
		$redirect_url = add_query_arg(
			array(
				'page'              => 'gstore-optimizer',
				'templates_reset'   => 'yes',
				'deleted_count'     => $deleted_count,
				'errors'            => count( $errors ),
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Verifica se um arquivo de template existe no tema
	 *
	 * @param string $template_file Nome do arquivo de template
	 * @return bool
	 */
	private function template_file_exists( $template_file ) {
		if ( empty( $template_file ) ) {
			return false;
		}

		$theme = wp_get_theme();
		$template_path = get_template_directory() . '/templates/' . $template_file . '.html';

		return file_exists( $template_path );
	}
}


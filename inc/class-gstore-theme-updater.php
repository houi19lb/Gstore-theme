<?php
/**
 * Atualizador do tema via GitHub (Git pull/reset) acionado pelo Admin.
 *
 * - Adiciona link "Atualizar via Git" na tela Aparência > Temas.
 * - Executa atualização via AJAX usando git fetch + reset --hard.
 *
 * Requisitos:
 * - Git instalado e disponível no PATH do servidor
 * - shell_exec habilitado
 * - Permissão de escrita no diretório do tema
 * - Token definido no wp-config.php: define('GSTORE_GITHUB_TOKEN', '...');
 *
 * @package Gstore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gstore_Theme_Git_Updater {

	/**
	 * URL do repositório remoto (sem token).
	 *
	 * Pode ser sobrescrito via wp-config.php:
	 * define('GSTORE_THEME_GIT_REPO', 'https://github.com/user/repo.git');
	 *
	 * @var string
	 */
	private $repo_url;

	/**
	 * Branch usada para atualizar.
	 *
	 * Pode ser sobrescrito via wp-config.php:
	 * define('GSTORE_THEME_GIT_BRANCH', 'main');
	 *
	 * @var string
	 */
	private $branch;

	/**
	 * Stylesheet (slug) do tema atual.
	 *
	 * @var string
	 */
	private $stylesheet;

	/**
	 * Construtor.
	 */
	public function __construct() {
		$this->repo_url   = defined( 'GSTORE_THEME_GIT_REPO' ) ? GSTORE_THEME_GIT_REPO : 'https://github.com/houi19lb/Gstore-theme.git';
		$this->branch     = defined( 'GSTORE_THEME_GIT_BRANCH' ) ? GSTORE_THEME_GIT_BRANCH : 'main';
		$this->stylesheet = get_stylesheet();

		add_filter( 'theme_action_links', array( $this, 'add_theme_action_link' ), 10, 2 );
		add_action( 'admin_footer-themes.php', array( $this, 'print_inline_script' ) );
		add_action( 'admin_footer-appearance_page_gstore-settings', array( $this, 'print_inline_script' ) );
		add_action( 'wp_ajax_gstore_theme_git_pull', array( $this, 'ajax_git_pull' ) );
	}

	/**
	 * Adiciona o link "Atualizar via Git" nos links de ação do tema ativo.
	 *
	 * @param array    $actions Links atuais.
	 * @param WP_Theme $theme   Tema da linha.
	 * @return array
	 */
	public function add_theme_action_link( $actions, $theme ) {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		if ( ! ( $theme instanceof WP_Theme ) ) {
			return $actions;
		}

		// Só exibe para o tema atual (ativo).
		if ( $theme->get_stylesheet() !== $this->stylesheet ) {
			return $actions;
		}

		$actions['gstore_git_update'] = sprintf(
			'<a href="#" class="gstore-theme-git-update" data-nonce="%s">%s</a>',
			esc_attr( wp_create_nonce( 'gstore_theme_git_pull' ) ),
			esc_html__( 'Atualizar via Git', 'gstore' )
		);

		return $actions;
	}

	/**
	 * Imprime script inline no footer da página themes.php.
	 */
	public function print_inline_script() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<script>
		(function($){
			$(document).on('click', '.gstore-theme-git-update', function(e){
				e.preventDefault();

				var $link = $(this);
				var originalText = $link.text();
				var nonce = $link.data('nonce');

				if (!nonce) {
					alert('Nonce inválido. Recarregue a página e tente novamente.');
					return;
				}

				if (!confirm('Isso vai sobrescrever os arquivos do tema com a versão do GitHub. Deseja continuar?')) {
					return;
				}

				$link.text('Atualizando...').css({pointerEvents: 'none', opacity: 0.6});

				$.post(ajaxurl, {
					action: 'gstore_theme_git_pull',
					nonce: nonce
				})
				.done(function(resp){
					if (resp && resp.success) {
						location.reload();
						return;
					}
					var msg = (resp && resp.data) ? resp.data : 'Erro desconhecido.';
					alert('Falha ao atualizar: ' + msg);
				})
				.fail(function(){
					alert('Falha ao atualizar: erro de conexão.');
				})
				.always(function(){
					$link.text(originalText).css({pointerEvents: '', opacity: ''});
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Endpoint AJAX que executa o git update.
	 */
	public function ajax_git_pull() {
		// Nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gstore_theme_git_pull' ) ) {
			wp_send_json_error( 'Nonce inválido.' );
		}

		// Permissão.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sem permissão.' );
		}

		// Token obrigatório.
		$token = '';
		if ( defined( 'GSTORE_THEME_GITHUB_TOKEN' ) && GSTORE_THEME_GITHUB_TOKEN ) {
			$token = (string) GSTORE_THEME_GITHUB_TOKEN;
		} elseif ( defined( 'GSTORE_GITHUB_TOKEN' ) && GSTORE_GITHUB_TOKEN ) {
			$token = (string) GSTORE_GITHUB_TOKEN;
		}

		if ( '' === $token ) {
			wp_send_json_error( 'Token ausente. Defina GSTORE_GITHUB_TOKEN (ou GSTORE_THEME_GITHUB_TOKEN) no wp-config.php.' );
		}

		if ( ! function_exists( 'shell_exec' ) ) {
			wp_send_json_error( 'shell_exec indisponível no servidor.' );
		}

		$theme_dir = get_stylesheet_directory();
		if ( ! is_dir( $theme_dir ) ) {
			wp_send_json_error( 'Diretório do tema não encontrado.' );
		}
		if ( ! is_writable( $theme_dir ) ) {
			wp_send_json_error( 'Diretório do tema sem permissão de escrita.' );
		}

		@set_time_limit( 300 );

		// Verifica se git existe.
		$git_version = $this->run_in_dir( 'git --version', $theme_dir );
		if ( false === $git_version || '' === trim( $git_version ) ) {
			wp_send_json_error( 'Git não encontrado no PATH do servidor.' );
		}

		$encoded_token   = rawurlencode( $token );
		$repo_with_token = preg_replace( '#^https://#', 'https://' . $encoded_token . '@', $this->repo_url );

		// Inicializa repo local se necessário.
		if ( ! is_dir( $theme_dir . DIRECTORY_SEPARATOR . '.git' ) ) {
			$out = $this->run_in_dir( 'git init', $theme_dir );
			if ( $this->looks_like_git_error( $out ) ) {
				wp_send_json_error( $this->mask_token( $out, $token ) );
			}

			$out = $this->run_in_dir( 'git remote add origin ' . $this->quote( $repo_with_token ), $theme_dir );
			if ( $this->looks_like_git_error( $out ) ) {
				wp_send_json_error( $this->mask_token( $out, $token ) );
			}
		} else {
			$out = $this->run_in_dir( 'git remote set-url origin ' . $this->quote( $repo_with_token ), $theme_dir );
			if ( $this->looks_like_git_error( $out ) ) {
				wp_send_json_error( $this->mask_token( $out, $token ) );
			}
		}

		// Sparse checkout (mantém o padrão do plugin; inclui pastas do tema).
		$this->run_in_dir( 'git config core.sparseCheckout true', $theme_dir );
		$this->write_sparse_checkout_file( $theme_dir );

		// Atualiza.
		$branch = $this->sanitize_branch( $this->branch );
		if ( '' === $branch ) {
			$branch = 'main';
		}
		$cmd = 'git fetch origin ' . $branch . ' && git reset --hard origin/' . $branch;
		$out = $this->run_in_dir( $cmd, $theme_dir );

		// Remove token do remote por segurança (melhor esforço).
		$this->run_in_dir( 'git remote set-url origin ' . $this->quote( $this->repo_url ), $theme_dir );

		$masked = $this->mask_token( $out, $token );
		if ( $this->looks_like_git_error( $out ) ) {
			wp_send_json_error( $masked );
		}

		wp_send_json_success(
			array(
				'message' => 'Atualizado.',
				'output'  => $masked,
			)
		);
	}

	/**
	 * Executa um comando dentro de um diretório e retorna o output (stdout+stderr).
	 *
	 * @param string $cmd Comando.
	 * @param string $dir Diretório.
	 * @return string|false
	 */
	private function run_in_dir( $cmd, $dir ) {
		$dir = rtrim( (string) $dir, "\\/ \t\n\r\0\x0B" );

		$cd = $this->is_windows() ? 'cd /d ' : 'cd ';
		$command = $cd . $this->quote_path( $dir ) . ' && ' . $cmd . ' 2>&1';

		return shell_exec( $command ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
	}

	/**
	 * Escreve o arquivo de sparse checkout com caminhos do tema.
	 *
	 * @param string $theme_dir Diretório do tema.
	 */
	private function write_sparse_checkout_file( $theme_dir ) {
		$info_dir = $theme_dir . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'info';
		if ( ! is_dir( $info_dir ) ) {
			wp_mkdir_p( $info_dir );
		}

		$file = $info_dir . DIRECTORY_SEPARATOR . 'sparse-checkout';
		$paths = array(
			'assets/',
			'inc/',
			'templates/',
			'parts/',
			'woocommerce/',
			'docs/',
			'style.css',
			'functions.php',
			'theme.json',
			'store-info.json',
			'document.json',
			'page-cart.php',
			'logo_branca_cac_1.png',
		);

		// Também inclui a pasta GSTORE/ caso o repo esteja estruturado assim.
		$paths[] = 'GSTORE/';

		@file_put_contents( $file, implode( "\n", $paths ) . "\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Detecta se estamos em Windows.
	 *
	 * @return bool
	 */
	private function is_windows() {
		return defined( 'PHP_OS_FAMILY' ) && 'Windows' === PHP_OS_FAMILY;
	}

	/**
	 * Faz quote de um caminho para uso em shell.
	 *
	 * @param string $path Caminho.
	 * @return string
	 */
	private function quote_path( $path ) {
		$path = (string) $path;
		if ( $this->is_windows() ) {
			return '"' . str_replace( '"', '\"', $path ) . '"';
		}
		return escapeshellarg( $path );
	}

	/**
	 * Faz quote de um argumento genérico.
	 *
	 * @param string $arg Argumento.
	 * @return string
	 */
	private function quote( $arg ) {
		if ( $this->is_windows() ) {
			return '"' . str_replace( '"', '\"', (string) $arg ) . '"';
		}
		return escapeshellarg( (string) $arg );
	}

	/**
	 * Mascara token (e variantes) do output.
	 *
	 * @param string|false $output Output.
	 * @param string       $token  Token.
	 * @return string
	 */
	private function mask_token( $output, $token ) {
		$out = (string) $output;
		if ( '' === $token ) {
			return $out;
		}
		$out = str_replace( $token, '***', $out );
		$out = str_replace( rawurlencode( $token ), '***', $out );
		return $out;
	}

	/**
	 * Heurística simples para detectar erro no output do git.
	 *
	 * @param string|false $output Output.
	 * @return bool
	 */
	private function looks_like_git_error( $output ) {
		$out = strtolower( (string) $output );
		if ( false === $output ) {
			return true;
		}
		return ( false !== strpos( $out, 'fatal:' ) ) || ( false !== strpos( $out, 'error:' ) );
	}

	/**
	 * Sanitiza nome de branch para uso em comando (sem quotes).
	 *
	 * @param string $branch Branch.
	 * @return string
	 */
	private function sanitize_branch( $branch ) {
		$branch = (string) $branch;
		$branch = preg_replace( '/[^A-Za-z0-9._\\/-]/', '', $branch );
		return trim( $branch );
	}
}


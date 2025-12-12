<?php
/**
 * Gerenciador de informações da loja.
 *
 * Centraliza todas as informações da loja em um arquivo JSON,
 * permitindo fácil exportação/importação para outras lojas.
 *
 * @package Gstore
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe GStore_Store_Info
 *
 * Gerencia as informações da loja via arquivo JSON.
 */
class GStore_Store_Info {

	/**
	 * Instância única da classe (Singleton).
	 *
	 * @var GStore_Store_Info|null
	 */
	private static $instance = null;

	/**
	 * Cache em memória das informações.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Caminho do arquivo JSON.
	 *
	 * @var string
	 */
	private $json_path;

	/**
	 * Construtor privado (Singleton).
	 */
	private function __construct() {
		$this->json_path = get_stylesheet_directory() . '/store-info.json';
	}

	/**
	 * Obtém a instância única da classe.
	 *
	 * @return GStore_Store_Info
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Retorna o caminho do arquivo JSON.
	 *
	 * @return string
	 */
	public function get_json_path() {
		return $this->json_path;
	}

	/**
	 * Carrega as informações do arquivo JSON.
	 *
	 * @param bool $force_reload Força recarregamento ignorando cache.
	 * @return array Dados da loja.
	 */
	public function load_from_json( $force_reload = false ) {
		if ( ! $force_reload && null !== $this->cache ) {
			return $this->cache;
		}

		if ( ! file_exists( $this->json_path ) ) {
			// Retorna estrutura padrão se o arquivo não existir
			$this->cache = $this->get_default_structure();
			return $this->cache;
		}

		$json_content = file_get_contents( $this->json_path );
		$data = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Se houver erro no JSON, retorna estrutura padrão
			$this->cache = $this->get_default_structure();
			return $this->cache;
		}

		// Mescla com estrutura padrão para garantir que todas as chaves existam
		$this->cache = $this->merge_with_defaults( $data );
		return $this->cache;
	}

	/**
	 * Salva as informações no arquivo JSON.
	 *
	 * @param array $data Dados para salvar.
	 * @return bool|WP_Error True em caso de sucesso, WP_Error em caso de erro.
	 */
	public function save_to_json( $data ) {
		// Valida a estrutura antes de salvar
		$validation = $this->validate_structure( $data );
		if ( $validation !== true ) {
			return new WP_Error( 'validation_failed', $validation );
		}

		$json_content = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		
		if ( false === $json_content ) {
			return new WP_Error( 
				'json_encode_failed', 
				sprintf( 
					__( 'Erro ao codificar JSON. Erro: %s', 'gstore' ), 
					json_last_error_msg() 
				) 
			);
		}

		// Verifica se o diretório existe e tem permissão de escrita
		$dir = dirname( $this->json_path );
		if ( ! is_dir( $dir ) ) {
			return new WP_Error( 
				'dir_not_exists', 
				sprintf( __( 'Diretório não existe: %s', 'gstore' ), $dir ) 
			);
		}
		
		if ( ! is_writable( $dir ) ) {
			return new WP_Error( 
				'dir_not_writable', 
				sprintf( __( 'Diretório sem permissão de escrita: %s', 'gstore' ), $dir ) 
			);
		}

		$result = file_put_contents( $this->json_path, $json_content );
		
		if ( false === $result ) {
			return new WP_Error( 
				'save_failed', 
				sprintf( 
					__( 'Erro ao salvar arquivo JSON em: %s. Verifique permissões de escrita.', 'gstore' ), 
					$this->json_path 
				) 
			);
		}

		// Limpa o cache para forçar recarregamento
		$this->cache = null;
		return true;
	}

	/**
	 * Obtém um valor específico usando notação de ponto.
	 *
	 * Exemplo: get_value('store.name') retorna o nome da loja.
	 *
	 * @param string $path   Caminho do valor (ex: "store.name").
	 * @param mixed  $default Valor padrão se não encontrar.
	 * @return mixed
	 */
	public function get_value( $path, $default = '' ) {
		$data = $this->load_from_json();
		$keys = explode( '.', $path );
		$value = $data;

		foreach ( $keys as $key ) {
			if ( is_array( $value ) && isset( $value[ $key ] ) ) {
				$value = $value[ $key ];
			} else {
				return $default;
			}
		}

		return $value;
	}

	/**
	 * Define um valor específico usando notação de ponto.
	 *
	 * @param string $path  Caminho do valor (ex: "store.name").
	 * @param mixed  $value Valor a definir.
	 * @return bool|WP_Error True em caso de sucesso, WP_Error em caso de erro.
	 */
	public function set_value( $path, $value ) {
		$data = $this->load_from_json();
		$keys = explode( '.', $path );
		$current = &$data;

		foreach ( $keys as $i => $key ) {
			if ( $i === count( $keys ) - 1 ) {
				$current[ $key ] = $value;
			} else {
				if ( ! isset( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
					$current[ $key ] = array();
				}
				$current = &$current[ $key ];
			}
		}

		$result = $this->save_to_json( $data );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Retorna todas as informações da loja.
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->load_from_json();
	}

	/**
	 * Exporta as informações como JSON para download.
	 *
	 * @return string JSON formatado.
	 */
	public function export_json() {
		$data = $this->load_from_json();
		return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Importa informações de um JSON.
	 *
	 * @param string $json_content Conteúdo JSON.
	 * @return bool|WP_Error True em caso de sucesso, WP_Error em caso de erro.
	 */
	public function import_json( $json_content ) {
		// Se já foi decodificado antes (no handler), usa diretamente
		// Caso contrário, decodifica aqui
		if ( is_array( $json_content ) ) {
			$data = $json_content;
		} else {
			$data = json_decode( $json_content, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error( 'invalid_json', sprintf( 
					__( 'O arquivo JSON é inválido. Erro: %s', 'gstore' ), 
					json_last_error_msg() 
				) );
			}
		}

		$validation = $this->validate_structure( $data );
		if ( $validation !== true ) {
			return new WP_Error( 'invalid_structure', $validation );
		}

		$save_result = $this->save_to_json( $data );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		return true;
	}

	/**
	 * Valida a estrutura do JSON.
	 *
	 * @param array $data Dados para validar.
	 * @return bool|string True se válido, string com mensagem de erro se inválido.
	 */
	public function validate_structure( $data ) {
		if ( ! is_array( $data ) && ! is_object( $data ) ) {
			return sprintf( 
				__( 'O JSON deve ser um objeto (começar com {). Tipo recebido: %s', 'gstore' ), 
				gettype( $data ) 
			);
		}

		// Converte objeto para array se necessário (deep conversion)
		if ( is_object( $data ) ) {
			$data = json_decode( json_encode( $data ), true );
		}

		// Verifica se as seções principais existem
		$required_sections = array( 
			'store'         => __( 'store', 'gstore' ),
			'contact'       => __( 'contact', 'gstore' ),
			'address'       => __( 'address', 'gstore' ),
			'social'        => __( 'social', 'gstore' ),
			'business_hours' => __( 'business_hours', 'gstore' ),
			'footer'        => __( 'footer', 'gstore' ),
			'meta'          => __( 'meta', 'gstore' ),
		);
		
		// Lista de seções encontradas para debug
		$found_sections = array();
		$missing_sections = array();
		
		foreach ( $required_sections as $section => $section_label ) {
			if ( ! isset( $data[ $section ] ) ) {
				$missing_sections[] = $section_label;
				continue;
			}
			
			$found_sections[] = $section_label;
			
			// Permite tanto arrays quanto objetos (arrays associativos)
			$section_value = $data[ $section ];
			if ( is_object( $section_value ) ) {
				$section_value = (array) $section_value;
			}
			
			if ( ! is_array( $section_value ) ) {
				return sprintf( 
					__( 'A seção "%s" deve ser um objeto (não um valor simples). Tipo recebido: %s', 'gstore' ), 
					$section_label,
					gettype( $data[ $section ] )
				);
			}
		}
		
		// Se há seções faltando, retorna erro detalhado
		if ( ! empty( $missing_sections ) ) {
			return sprintf( 
				__( 'Seções obrigatórias ausentes: %s. Seções encontradas: %s', 'gstore' ), 
				implode( ', ', $missing_sections ),
				implode( ', ', $found_sections )
			);
		}

		// Verifica campos obrigatórios da loja
		$store_data = $data['store'];
		if ( is_object( $store_data ) ) {
			$store_data = (array) $store_data;
		}
		
		if ( ! isset( $store_data['name'] ) || empty( $store_data['name'] ) ) {
			$store_keys = is_array( $store_data ) ? implode( ', ', array_keys( $store_data ) ) : 'N/A';
			return sprintf( 
				__( 'O campo "store.name" é obrigatório e não pode estar vazio. Chaves encontradas em store: %s', 'gstore' ),
				$store_keys
			);
		}

		return true;
	}

	/**
	 * Mescla dados com a estrutura padrão.
	 *
	 * @param array $data Dados para mesclar.
	 * @return array Dados mesclados.
	 */
	private function merge_with_defaults( $data ) {
		$defaults = $this->get_default_structure();
		return $this->array_merge_recursive_distinct( $defaults, $data );
	}

	/**
	 * Merge recursivo que substitui valores ao invés de criar arrays.
	 *
	 * @param array $array1 Array base.
	 * @param array $array2 Array para mesclar.
	 * @return array
	 */
	private function array_merge_recursive_distinct( array $array1, array $array2 ) {
		$merged = $array1;

		foreach ( $array2 as $key => $value ) {
			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				// Se ambos são arrays sequenciais (não associativos), substitui ao invés de mesclar
				if ( $this->is_sequential_array( $value ) && $this->is_sequential_array( $merged[ $key ] ) ) {
					$merged[ $key ] = $value;
				} else {
					$merged[ $key ] = $this->array_merge_recursive_distinct( $merged[ $key ], $value );
				}
			} else {
				$merged[ $key ] = $value;
			}
		}

		return $merged;
	}

	/**
	 * Verifica se é um array sequencial (não associativo).
	 *
	 * @param array $array Array para verificar.
	 * @return bool
	 */
	private function is_sequential_array( array $array ) {
		if ( empty( $array ) ) {
			return true;
		}
		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}

	/**
	 * Retorna a estrutura padrão das informações da loja.
	 *
	 * @return array
	 */
	public function get_default_structure() {
		return array(
			'store' => array(
				'name'           => 'CAC ARMAS',
				'display_name'   => 'CAC Armas',
				'name_highlight' => 'ARMAS',
				'founded_year'   => '2010',
				'cnpj'           => '41.132.692/0001-09',
				'slogan'         => '',
			),
			'contact' => array(
				'phone'           => '+55 62 9663-5633',
				'phone_raw'       => '556296635633',
				'whatsapp'        => '556296635633',
				'whatsapp_display' => '+55 62 9663-5633',
				'telegram'        => 'grupocacarmas',
				'email'           => '',
			),
			'address' => array(
				'street'       => 'Avenida Transbrasiliana, 368',
				'neighborhood' => 'Parque Amazônia',
				'city'         => 'Goiânia',
				'state'        => 'GO',
				'zipcode'      => '74.835-300',
				'country'      => 'Brasil',
				'maps_url'     => 'https://www.google.com/maps/place/R.+Ca%C3%A7ador,+214+-+Rio+Branco,+Novo+Hamburgo',
			),
			'social' => array(
				'instagram'      => 'grupocacarmas',
				'instagram_alt'  => 'armastoreoficial',
				'facebook'       => '100094019667432',
				'youtube'        => 'armastore',
				'telegram_group' => 'grupocacarmas',
				'twitter'        => '',
				'tiktok'         => '',
			),
			'business_hours' => array(
				'weekdays'    => 'Segunda a Sexta-feira das 11:30 às 21:00',
				'saturday'    => 'Sábados das 9h às 16h',
				'sunday'      => '',
				'full_text'   => 'Segunda a Sexta-feira das 11:30 às 21:00, e aos sábados das 9h às 16h',
				'support_hours' => 'Seg-Sex, 9h às 18h',
			),
			'footer' => array(
				'about_title' => 'CAC ARMAS',
				'about_paragraphs' => array(
					'Em um mercado tão competitivo, é imprescindível a qualidade no atendimento, produtos e serviços oferecidos para agilizar e contribuir com o seu crescimento e sucesso no seu esporte, atividade de lazer ou trabalho.',
					'Atuando desde 2010 contamos com atendimento diferenciado, oferecendo serviços de consultoria, vendas e serviços de reparo e manutenção.',
					'Por isso a CAC Armas vem atuando no mercado, procurando sempre oferecer serviços e soluções que atendam às necessidades dos nossos clientes.',
					'Dentre as várias linhas de atuação, destacamos nossa especialização em vendas de produtos para a prática de Airsoft, Carabinas de Pressão, Armas de Fogo e Artigos Militares.',
				),
				'newsletter' => array(
					'title'    => 'CADASTRE-SE E RECEBA',
					'subtitle' => 'NOVIDADES E OFERTAS EXCLUSIVAS',
				),
				'menu_links' => array(
					'duvidas' => array(
						array( 'label' => 'Dúvidas', 'url' => '/atendimento/' ),
						array( 'label' => 'Formas de pagamento', 'url' => '/atendimento/' ),
						array( 'label' => 'Entrega', 'url' => '/atendimento/' ),
						array( 'label' => 'Troca e devolução', 'url' => '/atendimento/' ),
						array( 'label' => 'Política de privacidade', 'url' => '#' ),
						array( 'label' => 'Fale conosco', 'url' => '/atendimento/' ),
					),
					'institucional' => array(
						array( 'label' => 'Institucional', 'url' => '#' ),
						array( 'label' => 'A empresa', 'url' => '#' ),
						array( 'label' => 'Localização', 'url' => '' ), // URL preenchida dinamicamente
					),
				),
				'copyright_text' => 'Copyright © {year} {store_name}. Todos os direitos reservados.',
			),
			'meta' => array(
				'description' => 'Loja especializada em vendas de produtos para a prática de Airsoft, Carabinas de Pressão, Armas de Fogo e Artigos Militares.',
				'keywords'    => 'armas, airsoft, carabinas, pressão, artigos militares, tiro esportivo, CAC',
				'og_image'    => '',
			),
			'branding' => array(
				'accent_color'   => '#b5a642',
				'primary_color'  => '#0a0a0a',
				'logo_alt'       => 'Logo CAC Armas',
			),
		);
	}

	/**
	 * Limpa o cache em memória.
	 */
	public function clear_cache() {
		$this->cache = null;
	}

	/**
	 * Verifica se o arquivo JSON existe.
	 *
	 * @return bool
	 */
	public function json_exists() {
		return file_exists( $this->json_path );
	}

	/**
	 * Cria o arquivo JSON com a estrutura padrão.
	 *
	 * @return bool|WP_Error True em caso de sucesso, WP_Error em caso de erro.
	 */
	public function create_default_json() {
		$defaults = $this->get_default_structure();
		$result = $this->save_to_json( $defaults );
		return is_wp_error( $result ) ? $result : true;
	}
}

/**
 * Função helper para obter a instância do gerenciador.
 *
 * @return GStore_Store_Info
 */
function gstore_store_info() {
	return GStore_Store_Info::get_instance();
}


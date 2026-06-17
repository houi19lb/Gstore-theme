<?php
/**
 * Template customizado para página de produto único (Gstore).
 *
 * @package Gstore
 * @version 2.1.0
 */

defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
*/

if ( ! function_exists( 'gstore_get_product_attributes' ) ) :
	/**
	 * Obtém atributos visíveis do produto.
	 *
	 * @param WC_Product $product Produto.
	 * @return array
	 */
	function gstore_get_product_attributes( $product ) {
		$raw_attributes = $product->get_attributes();
		$data           = array();

		if ( empty( $raw_attributes ) ) {
			return $data;
		}

		foreach ( $raw_attributes as $attribute ) {
			if ( ! $attribute->get_visible() ) {
				continue;
			}

			$label = wc_attribute_label( $attribute->get_name(), $product );

			if ( $attribute->is_taxonomy() ) {
				$values = wc_get_product_terms(
					gstore_get_product_id( $product ),
					$attribute->get_name(),
					array( 'fields' => 'names' )
				);
			} else {
				$values = $attribute->get_options();
			}

			$value = is_array( $values ) ? implode( ', ', $values ) : (string) $values;

			if ( '' === trim( $value ) ) {
				continue;
			}

			$data[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return $data;
	}
endif;

if ( ! function_exists( 'gstore_get_hero_meta_cards' ) ) :
	/**
	 * Retorna cards de meta informações.
	 *
	 * @param string $stock_label          Label de estoque.
	 * @param string $formatted_installment Texto de parcelamento.
	 * @return array
	 */
	function gstore_get_hero_meta_cards( $stock_label, $formatted_installment ) {
		return array(
			array(
				'icon'       => 'fa-circle-check',
				'label'      => __( 'Disponibilidade', 'gstore' ),
				'text'       => $stock_label,
				'allow_html' => true,
				'is_installment' => false,
			),
			array(
				'icon'       => 'fa-credit-card',
				'label'      => __( 'Condições de pagamento', 'gstore' ),
				'text'       => $formatted_installment ? $formatted_installment : __( 'Parcele no cartão ou finalize no PIX.', 'gstore' ),
				'allow_html' => (bool) $formatted_installment,
				'is_installment' => true,
			),
			array(
				'icon'       => 'fa-truck-fast',
				'label'      => __( 'Envio monitorado', 'gstore' ),
				'text'       => __( 'Logística segura e monitorada.', 'gstore' ),
				'allow_html' => false,
				'is_installment' => false,
			),
		);
	}
endif;

if ( ! function_exists( 'gstore_format_items_as_lines' ) ) :
	/**
	 * Formata itens (label/value) como linhas de texto.
	 *
	 * @param array $items         Itens a formatar.
	 * @param bool  $require_value Se true, só inclui itens com valor.
	 * @return array
	 */
	function gstore_format_items_as_lines( $items, $require_value = false ) {
		$lines = array();

		foreach ( $items as $item ) {
			if ( $require_value && empty( $item['value'] ) ) {
				continue;
			}

			$line = $item['label'];
			if ( ! empty( $item['value'] ) ) {
				$line .= ': ' . $item['value'];
			}
			$lines[] = $line;
		}

		return array_slice( $lines, 0, 6 );
	}
endif;

if ( ! function_exists( 'gstore_render_details_list' ) ) :
	/**
	 * Renderiza lista de detalhes como HTML.
	 *
	 * @param array $items Itens da lista.
	 * @return string
	 */
	function gstore_render_details_list( $items ) {
		if ( empty( $items ) ) {
			return '';
		}

		$html = '<ul class="Gstore-single-product__details-list">';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		$html .= '</ul>';

		return $html;
	}
endif;

if ( ! function_exists( 'gstore_get_product_category_depth' ) ) :
	/**
	 * Retorna a profundidade de uma categoria de produto.
	 *
	 * @param WP_Term $term Categoria.
	 * @return int
	 */
	function gstore_get_product_category_depth( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return 0;
		}

		return count( get_ancestors( (int) $term->term_id, 'product_cat', 'taxonomy' ) );
	}
endif;

if ( ! function_exists( 'gstore_get_product_category_product_count' ) ) :
	/**
	 * Retorna a quantidade de produtos publicados em uma categoria.
	 *
	 * @param WP_Term $term Categoria.
	 * @return int
	 */
	function gstore_get_product_category_product_count( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return 0;
		}

		return max( 0, (int) $term->count );
	}
endif;

if ( ! function_exists( 'gstore_get_product_category_preview_image_html' ) ) :
	/**
	 * Retorna a imagem do primeiro produto publicado da categoria.
	 *
	 * @param WP_Term $term Categoria.
	 * @return string
	 */
	function gstore_get_product_category_preview_image_html( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return '';
		}

		static $cache = array();
		$term_id = (int) $term->term_id;

		if ( isset( $cache[ $term_id ] ) ) {
			return $cache[ $term_id ];
		}

		$image_html = '';
		$query      = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => array( $term_id ),
						'include_children' => true,
					),
				),
			)
		);

		$product_id = ! empty( $query->posts[0] ) ? absint( $query->posts[0] ) : 0;
		if ( $product_id > 0 ) {
			$image_id = absint( get_post_thumbnail_id( $product_id ) );
			if ( $image_id > 0 ) {
				$image_html = wp_get_attachment_image(
					$image_id,
					'woocommerce_thumbnail',
					false,
					array(
						'class'   => 'Gstore-product-category-links__thumb-img',
						'loading' => 'lazy',
					)
				);
			}
		}

		if ( '' === $image_html ) {
			$image_id = absint( get_term_meta( $term_id, 'thumbnail_id', true ) );
			if ( $image_id > 0 ) {
				$image_html = wp_get_attachment_image(
					$image_id,
					'thumbnail',
					false,
					array(
						'class'   => 'Gstore-product-category-links__thumb-img',
						'loading' => 'lazy',
					)
				);
			}
		}

		$cache[ $term_id ] = (string) $image_html;

		return $cache[ $term_id ];
	}
endif;

if ( ! function_exists( 'gstore_get_product_related_category_cards' ) ) :
	/**
	 * Retorna os cards de direcionamento para categorias relacionadas do produto.
	 *
	 * @param int $product_id ID do produto.
	 * @return array<int, array<string, mixed>>
	 */
	function gstore_get_product_related_category_cards( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return array();
		}

		$terms = wc_get_product_terms(
			$product_id,
			'product_cat',
			array(
				'fields' => 'all',
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$terms = array_values(
			array_filter(
				$terms,
				function ( $term ) {
					return $term instanceof WP_Term
						&& 'product_cat' === $term->taxonomy
						&& 'sem-categoria' !== $term->slug
						&& 'uncategorized' !== $term->slug;
				}
			)
		);

		if ( empty( $terms ) ) {
			return array();
		}

		$primary_id = absint( get_post_meta( $product_id, '_yoast_wpseo_primary_product_cat', true ) );
		usort(
			$terms,
			function ( $a, $b ) use ( $primary_id ) {
				$depth_a = gstore_get_product_category_depth( $a );
				$depth_b = gstore_get_product_category_depth( $b );

				if ( $depth_a !== $depth_b ) {
					return $depth_b <=> $depth_a;
				}

				if ( $primary_id > 0 ) {
					$a_is_primary = (int) $a->term_id === $primary_id;
					$b_is_primary = (int) $b->term_id === $primary_id;

					if ( $a_is_primary !== $b_is_primary ) {
						return $a_is_primary ? -1 : 1;
					}
				}

				return strcasecmp( (string) $a->name, (string) $b->name );
			}
		);

		$primary_term = $terms[0];
		if ( ! $primary_term instanceof WP_Term ) {
			return array();
		}

		$parent_term = null;
		if ( ! empty( $primary_term->parent ) ) {
			$maybe_parent = get_term( (int) $primary_term->parent, 'product_cat' );
			if ( $maybe_parent instanceof WP_Term && ! is_wp_error( $maybe_parent ) ) {
				$parent_term = $maybe_parent;
			}
		}

		$card_terms = array( $primary_term );
		if ( $parent_term instanceof WP_Term ) {
			$card_terms[] = $parent_term;
		}

		$cards     = array();
		$seen_ids  = array();
		foreach ( $card_terms as $term ) {
			if ( ! $term instanceof WP_Term || isset( $seen_ids[ (int) $term->term_id ] ) ) {
				continue;
			}

			$link = get_term_link( $term, 'product_cat' );
			if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
				continue;
			}

			$product_count = gstore_get_product_category_product_count( $term );

			$cards[] = array(
				'title'      => wp_strip_all_tags( (string) $term->name ),
				'subtitle'   => sprintf(
					/* translators: %s: Quantidade de produtos. */
					__( 'Ver produtos (%s)', 'gstore' ),
					number_format_i18n( $product_count )
				),
				'url'        => $link,
				'image_html' => gstore_get_product_category_preview_image_html( $term ),
			);

			$seen_ids[ (int) $term->term_id ] = true;
		}

		return $cards;
	}
endif;

if ( ! function_exists( 'gstore_render_product_related_category_links' ) ) :
	/**
	 * Renderiza o bloco de direcionamento para categorias relacionadas.
	 *
	 * @param array<int, array<string, mixed>> $cards Cards.
	 * @param string                           $context Contexto visual.
	 * @return void
	 */
	function gstore_render_product_related_category_links( $cards, $context = 'desktop' ) {
		if ( empty( $cards ) || ! is_array( $cards ) ) {
			return;
		}

		$context = 'mobile' === $context ? 'mobile' : 'desktop';
		?>
		<section class="Gstore-product-category-links Gstore-product-category-links--<?php echo esc_attr( $context ); ?>" aria-label="<?php esc_attr_e( 'Categorias relacionadas', 'gstore' ); ?>">
			<div class="Gstore-product-category-links__header">
				<h3 class="Gstore-product-category-links__title"><?php esc_html_e( 'Navegue por categorias', 'gstore' ); ?></h3>
				<p class="Gstore-product-category-links__subtitle"><?php esc_html_e( 'Encontre mais opções dentro das categorias mais próximas.', 'gstore' ); ?></p>
			</div>

			<div class="Gstore-product-category-links__list">
				<?php foreach ( $cards as $card ) : ?>
					<?php
					$title      = isset( $card['title'] ) ? (string) $card['title'] : '';
					$subtitle   = isset( $card['subtitle'] ) ? (string) $card['subtitle'] : '';
					$url        = isset( $card['url'] ) ? (string) $card['url'] : '';
					$image_html = isset( $card['image_html'] ) ? (string) $card['image_html'] : '';

					if ( '' === $title || '' === $url ) {
						continue;
					}
					?>
					<a class="Gstore-product-category-links__item" href="<?php echo esc_url( $url ); ?>">
						<span class="Gstore-product-category-links__thumb" aria-hidden="true">
							<?php if ( '' !== $image_html ) : ?>
								<?php echo wp_kses_post( $image_html ); ?>
							<?php else : ?>
								<i class="fa-solid fa-layer-group" aria-hidden="true"></i>
							<?php endif; ?>
						</span>

						<span class="Gstore-product-category-links__body">
							<span class="Gstore-product-category-links__item-title"><?php echo esc_html( $title ); ?></span>
							<?php if ( '' !== $subtitle ) : ?>
								<span class="Gstore-product-category-links__item-subtitle"><?php echo esc_html( $subtitle ); ?></span>
							<?php endif; ?>
						</span>

						<span class="Gstore-product-category-links__arrow" aria-hidden="true"></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}
endif;

if ( ! function_exists( 'gstore_normalize_product_content' ) ) :
	/**
	 * Normaliza o HTML do produto para preservar quebras e linhas vazias no tema.
	 *
	 * - Se vier texto puro, aplica wpautop().
	 * - Se vier HTML sem tags de bloco, converte quebras \n em <br> e \n\n em <p>&nbsp;</p>.
	 *
	 * @param string $content Conteúdo bruto do produto.
	 * @return string
	 */
	function gstore_normalize_product_content( $content ) {
		$content = is_string( $content ) ? $content : '';
		if ( '' === trim( $content ) ) {
			return '';
		}
		$has_html = false !== strpos( $content, '<' );
		if ( ! $has_html ) {
			return wpautop( $content );
		}
		$has_block_tags = preg_match( '/<(p|div|br|ul|ol|li|h[1-6]|blockquote|table|pre|hr)\b/i', $content );
		if ( ! $has_block_tags ) {
			$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
			$content = '<p>' . preg_replace( "/\n\n+/", "</p><p>&nbsp;</p><p>", $content ) . '</p>';
			$content = str_replace( "\n", '<br>', $content );
		}
		$content = do_shortcode( $content );
		return wp_kses_post( $content );
	}
endif;

if ( ! function_exists( 'gstore_get_details_info_rows' ) ) :
	/**
	 * Retorna linhas de informação para a seção de detalhes.
	 *
	 * @param int    $product_id        ID do produto.
	 * @param string $short_description Descrição curta.
	 * @param string $full_description  Descrição completa.
	 * @param array  $attribute_data    Dados dos atributos.
	 * @return array
	 */
	function gstore_get_details_info_rows( $product_id, $short_description, $full_description, $attribute_data ) {
		$product_id = (int) $product_id;

		$principais_atributos_raw       = $product_id ? (string) get_post_meta( $product_id, '_gstore_key_attributes', true ) : '';
		$observacoes_importantes_raw    = $product_id ? (string) get_post_meta( $product_id, '_gstore_important_notes', true ) : '';
		$principais_atributos_has_value = '' !== trim( wp_strip_all_tags( $principais_atributos_raw ) );
		$observacoes_importantes_has_value = '' !== trim( wp_strip_all_tags( $observacoes_importantes_raw ) );

		$principais_atributos = $principais_atributos_has_value
			? apply_filters( 'the_content', $principais_atributos_raw )
			: '';

		$observacoes_importantes = $observacoes_importantes_has_value
			? apply_filters( 'the_content', $observacoes_importantes_raw )
			: '';

		$rows = array(
			array(
				'icon'       => 'fa-circle-info',
				'title'      => __( 'Resumo do produto', 'gstore' ),
				'content'    => $short_description ? $short_description : __( 'Produto selecionado com materiais premium e suporte dedicado em todas as etapas.', 'gstore' ),
				'allow_html' => (bool) $short_description,
			),
		);

		// Adiciona descrição completa se existir.
		if ( ! empty( $full_description ) ) {
			$rows[] = array(
				'icon'       => 'fa-file-lines',
				'title'      => __( 'Descrição completa', 'gstore' ),
				'content'    => $full_description,
				'allow_html' => true,
			);
		}

		if ( ! empty( $principais_atributos ) ) {
			$rows[] = array(
				'icon'       => 'fa-layer-group',
				'title'      => __( 'Principais atributos', 'gstore' ),
				'content'    => $principais_atributos,
				'allow_html' => true,
			);
		}

		if ( ! empty( $observacoes_importantes ) ) {
			$rows[] = array(
				'icon'       => 'fa-circle-exclamation',
				'title'      => __( 'Observações importantes', 'gstore' ),
				'content'    => $observacoes_importantes,
				'allow_html' => true,
			);
		}

		return array_values(
			array_filter(
				$rows,
				function ( $row ) {
					return ! empty( $row['content'] );
				}
			)
		);
	}
endif;

if ( ! function_exists( 'gstore_get_contact_entries' ) ) :
	/**
	 * Retorna entradas de contato/suporte.
	 *
	 * @return array
	 */
	function gstore_get_contact_entries() {
		$support_email_link = function_exists( 'gstore_get_store_email_link' ) ? gstore_get_store_email_link() : '';
		$my_account_url     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';

		$entries = array(
			array(
				'icon'  => 'fa-headset',
				'label' => __( 'Atendimento dedicado', 'gstore' ),
				'value' => __( 'Nosso time responde em até 2h úteis via WhatsApp ou e-mail.', 'gstore' ),
				'cta'   => $support_email_link ? __( 'Enviar mensagem', 'gstore' ) : '',
				'href'  => $support_email_link,
			),
			array(
				'icon'  => 'fa-user-shield',
				'label' => __( 'Central do cliente', 'gstore' ),
				'value' => __( 'Gerencie pedidos, notas fiscais e devoluções em um só lugar.', 'gstore' ),
				'cta'   => $my_account_url ? __( 'Acessar minha conta', 'gstore' ) : '',
				'href'  => $my_account_url,
			),
		);

		return array_values(
			array_filter(
				$entries,
				function ( $entry ) {
					return ! empty( $entry['label'] ) && ! empty( $entry['value'] );
				}
			)
		);
	}
endif;

if ( ! function_exists( 'gstore_get_guarantee_badges' ) ) :
	/**
	 * Retorna badges de garantia.
	 *
	 * @return array
	 */
	function gstore_get_guarantee_badges() {
		return array(
			array(
				'icon'  => 'fa-truck',
				'title' => __( 'Frete monitorado', 'gstore' ),
				'text'  => __( 'Rastreamento atualizado em cada etapa.', 'gstore' ),
			),
			array(
				'icon'  => 'fa-shield-halved',
				'title' => __( 'Garantia oficial', 'gstore' ),
				'text'  => __( 'Produtos com nota fiscal e suporte dedicado.', 'gstore' ),
			),
			array(
				'icon'  => 'fa-rotate-left',
				'title' => __( 'Devolução em 30 dias', 'gstore' ),
				'text'  => __( 'Processo guiado pelo nosso time.', 'gstore' ),
			),
		);
	}
endif;

/*
|--------------------------------------------------------------------------
| Início do Template
|--------------------------------------------------------------------------
*/

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}


// Remove hooks padrão do WooCommerce.
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

/*
|--------------------------------------------------------------------------
| Preparação de dados do produto
|--------------------------------------------------------------------------
*/

// Descrição e avaliações. Usar the_content para que filtros do plugin (normalização) rodem na descrição.
$short_description       = apply_filters( 'woocommerce_short_description', $product->get_short_description() );
$full_description        = apply_filters( 'the_content', $product->get_description() );
$review_count            = (int) $product->get_review_count();
$stock_status            = (string) $product->get_stock_status();
$is_variable             = $product->is_type( 'variable' );
$sku                     = (string) $product->get_sku();
$product_id              = (int) $product->get_id();
$related_category_cards  = gstore_get_product_related_category_cards( $product_id );
$is_public_draft_product = function_exists( 'gstore_theme_is_public_draft_product' )
	? gstore_theme_is_public_draft_product( $product )
	: false;
$average_rating          = (float) $product->get_average_rating();
$rating_display          = $average_rating > 0 ? number_format_i18n( $average_rating, 1 ) : '';
$review_count_i18n       = $review_count > 0 ? number_format_i18n( $review_count ) : '';

// Para produtos variáveis, verificar se TODAS as variações estão sem estoque.
$all_variations_out_of_stock = false;
$has_any_variation_in_stock  = false;

if ( $is_variable && method_exists( $product, 'get_children' ) ) {
	$children = $product->get_children();
	if ( ! empty( $children ) ) {
		$all_variations_out_of_stock = true;
		foreach ( $children as $child_id ) {
			$child_stock_status = (string) get_post_meta( (int) $child_id, '_stock_status', true );
			if ( 'instock' === $child_stock_status ) {
				$all_variations_out_of_stock = false;
				$has_any_variation_in_stock  = true;
				break;
			}
		}
	}
}

// Para variáveis: se TODAS estão sem estoque, forçar outofstock.
if ( $is_variable && $all_variations_out_of_stock ) {
	$stock_status = 'outofstock';
}

$is_out_of_stock   = 'outofstock' === $stock_status;
$is_on_backorder   = 'onbackorder' === $stock_status;
$is_in_stock       = 'instock' === $stock_status;

if ( $is_public_draft_product ) {
	$is_out_of_stock = true;
	$is_on_backorder = false;
	$is_in_stock     = false;
}

$buybox_stock_class = $is_out_of_stock ? 'is-out-of-stock' : ( $is_in_stock ? 'is-in-stock' : 'is-on-order' );
$show_price_oos     = false;
if ( ! $is_public_draft_product ) {
	$show_price_oos = 'yes' === (string) get_option( 'gstore_show_price_out_of_stock', 'no' );
}
$oos_price_mode     = $show_price_oos ? 'show' : 'hide';

// Disponibilidade (seleção do admin via plugin GSTORE).
$hide_price = ! $is_public_draft_product && (
	function_exists( 'gstore_product_hides_price' )
		? gstore_product_hides_price( $product, 'single' )
		: (bool) get_post_meta( $product_id, '_gstore_hide_price', true )
);

// Chave correta: _gstore_availability (com underline no começo).
$slug_disponibilidade = $product_id ? (string) get_post_meta( $product_id, '_gstore_availability', true ) : '';

// Converte o slug interno para o texto exibido ao cliente.
$nomes_disponibilidade = array(
	'ready'     => __( 'Pronta entrega', 'gstore' ),
	'pre-order' => __( 'Pré-venda', 'gstore' ),
	'on-demand' => __( 'Encomenda', 'gstore' ),
);

// Se não houver valor salvo (ou for inválido), usa "Pronta entrega" como padrão.
$texto_disponibilidade = isset( $nomes_disponibilidade[ $slug_disponibilidade ] )
	? $nomes_disponibilidade[ $slug_disponibilidade ]
	: __( 'Pronta entrega', 'gstore' );

$stock_title = $is_out_of_stock
	? __( 'Indisponível', 'gstore' )
	: ( $is_in_stock ? __( 'Disponível', 'gstore' ) : __( 'Sob encomenda', 'gstore' ) );

$stock_subtitle = $is_in_stock
	? (string) $texto_disponibilidade
	: ( $is_out_of_stock ? __( 'Sem estoque no momento', 'gstore' ) : __( 'Confirme prazos com nosso time', 'gstore' ) );

// Mantém um wrapper para facilitar estilização sem quebrar o layout.
$stock_label = sprintf(
	'<span class="disponibilidade-texto">%s</span>',
	esc_html( $is_out_of_stock ? $stock_title : $texto_disponibilidade )
);

// Preços e parcelamento.
$price_source_product = $product;

if ( $is_variable ) {
	$cheapest_variation_id = gstore_resolve_installment_product_id( $product );
	$cheapest_variation    = $cheapest_variation_id > 0 ? wc_get_product( $cheapest_variation_id ) : false;

	if ( $cheapest_variation instanceof WC_Product ) {
		$price_source_product = $cheapest_variation;
	}
}

$regular_price_raw = (float) $price_source_product->get_regular_price();
$sale_price_raw    = (float) $price_source_product->get_sale_price();
$current_price_raw = (float) $price_source_product->get_price();
$regular_price     = function_exists( 'wc_get_price_to_display' ) && $regular_price_raw > 0 ? (float) wc_get_price_to_display( $price_source_product, array( 'price' => $regular_price_raw, 'qty' => 1 ) ) : $regular_price_raw;
$sale_price        = function_exists( 'wc_get_price_to_display' ) && $sale_price_raw > 0 ? (float) wc_get_price_to_display( $price_source_product, array( 'price' => $sale_price_raw, 'qty' => 1 ) ) : $sale_price_raw;
$current_price     = function_exists( 'wc_get_price_to_display' ) && $current_price_raw > 0 ? (float) wc_get_price_to_display( $price_source_product, array( 'price' => $current_price_raw, 'qty' => 1 ) ) : $current_price_raw;
$has_discount      = $price_source_product->is_on_sale() && $regular_price > 0 && $sale_price > 0;
$display_price     = $has_discount ? $sale_price : ( $current_price > 0 ? $current_price : $regular_price );
$discount_percent  = $has_discount ? round( ( ( $regular_price - $display_price ) / $regular_price ) * 100 ) : 0;
$installments     = (int) apply_filters( 'armastore_single_product_installments', 21, $product );
$installment_preview = gstore_get_product_installment_preview_data( $product, $installments, 1, 'single' );
$formatted_installment = ! empty( $installment_preview['installments'] ) && ! empty( $installment_preview['per_installment_html'] )
	? sprintf(
		/* translators: 1: número de parcelas, 2: valor da parcela */
		__( 'ou em até %1$dx de %2$s', 'gstore' ),
		(int) $installment_preview['installments'],
		$installment_preview['per_installment_html']
	)
	: ( $installments > 0
	? sprintf(
		/* translators: 1: número de parcelas */
		__( 'ou em até %1$dx no cartão', 'gstore' ),
		$installments
	)
	: '' );
if ( $hide_price || $is_public_draft_product ) {
	$formatted_installment = '';
}

// Desconto Pix (apenas visual — não altera preço real, parcelas ou carrinho).
$pix_discount_config  = function_exists( 'gstore_blu_pix_get_discount_config' ) ? gstore_blu_pix_get_discount_config() : array( 'enabled' => false );
$pix_discount_active  = ! $hide_price && ! empty( $pix_discount_config['enabled'] ) && $display_price > 0;
$pix_discount_percent = $pix_discount_active ? (float) $pix_discount_config['percent'] : 0;
$pix_discount_price   = $pix_discount_active && function_exists( 'gstore_blu_pix_get_discounted_price' ) ? gstore_blu_pix_get_discounted_price( $display_price, $product->get_id() ) : false;
$show_variable_from_price = ! $hide_price && $is_variable && $display_price > 0;
$variable_price_html      = '';

if ( $is_variable ) {
	if ( $has_discount && $regular_price > 0 && $display_price > 0 ) {
		$variable_price_html = sprintf(
			'<del>%1$s</del><ins>%2$s</ins>',
			wc_price( $regular_price ),
			wc_price( $display_price )
		);
	} elseif ( $display_price > 0 ) {
		$variable_price_html = wc_price( $display_price );
	} else {
		$variable_price_html = $product->get_price_html();
	}
}

// Badge do produto: usa a mesma disponibilidade configurada no admin GSTORE.
$badge_text = $texto_disponibilidade;

/*
|--------------------------------------------------------------------------
| Dados para seções do template
|--------------------------------------------------------------------------
*/

$benefit_items = array(
	__( 'Entrega rápida com rastreamento em tempo real.', 'gstore' ),
	__( 'Garantia oficial com suporte humano.', 'gstore' ),
	__( 'Parcelamento e pagamento instantâneo via PIX.', 'gstore' ),
);

$hero_meta_cards   = gstore_get_hero_meta_cards( $stock_label, $formatted_installment );
if ( $hide_price && isset( $hero_meta_cards[1] ) && is_array( $hero_meta_cards[1] ) ) {
	$hero_meta_cards[1]['text']       = __( 'O valor continua disponível nas áreas liberadas para este produto.', 'gstore' );
	$hero_meta_cards[1]['allow_html'] = false;
}
$contact_entries   = gstore_get_contact_entries();
$guarantee_badges  = gstore_get_guarantee_badges();

// Conteúdo das abas (dinâmico): só renderiza se tiver valor.
// Seguro para UTF-8: converte Latin-1/Windows-1252 antes de strip para preservar à, às, ç, etc.; trata preg_replace retornando null.
$gstore_has_tab_content = function ( $html ) {
	$text = wp_strip_all_tags( (string) $html );
	$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8' );
	$text = str_replace( array( "\xc2\xa0", "\xa0" ), ' ', $text ); // nbsp
	// Converte Latin-1/Windows-1252 para UTF-8 antes de strip, para não perder à, às, ç.
	if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) && function_exists( 'mb_convert_encoding' ) ) {
		foreach ( array( 'Windows-1252', 'ISO-8859-1' ) as $enc ) {
			$converted = @mb_convert_encoding( $text, 'UTF-8', $enc );
			if ( is_string( $converted ) && mb_check_encoding( $converted, 'UTF-8' ) ) {
				$text = $converted;
				break;
			}
		}
	}
	if ( function_exists( 'wp_check_invalid_utf8' ) ) {
		$text = wp_check_invalid_utf8( $text, true );
	}
	// Remove caracteres invisíveis (BOM, zero-width, soft hyphen) para não contar como conteúdo.
	$next = preg_replace( '/[\x{FEFF}\x{200B}\x{200C}\x{200D}\x{2060}\x{AD}]/u', '', $text );
	$text = ( null === $next ) ? $text : $next;
	$next = preg_replace( '/\s+/u', ' ', $text );
	$text = ( null === $next ) ? $text : $next;

	return '' !== trim( (string) $text );
};

$short_description_has_value = $gstore_has_tab_content( $short_description );
$full_description_has_value  = $gstore_has_tab_content( $full_description );

$key_attributes_raw   = $product_id ? (string) get_post_meta( $product_id, '_gstore_key_attributes', true ) : '';
$important_notes_raw  = $product_id ? (string) get_post_meta( $product_id, '_gstore_important_notes', true ) : '';
// Normaliza primeiro; usa o resultado para decidir se tem valor e para exibir (evita esconder aba quando o bruto está “sujo”).
$key_attributes_html  = apply_filters( 'the_content', $key_attributes_raw );
$important_notes_html = apply_filters( 'the_content', $important_notes_raw );
$key_attributes_has_value   = $gstore_has_tab_content( $key_attributes_html );
$important_notes_has_value  = $gstore_has_tab_content( $important_notes_html );
$key_attributes_html  = $key_attributes_has_value ? $key_attributes_html : '';
$important_notes_html = $important_notes_has_value ? $important_notes_html : '';

$reviews_has_value = comments_open();

$gstore_product_tabs = array();

if ( $short_description_has_value ) {
	$gstore_product_tabs[] = array(
		'slug'    => 'short',
		'label'   => __( 'Resumo', 'gstore' ),
		'content' => $short_description,
	);
}

if ( $full_description_has_value ) {
	$gstore_product_tabs[] = array(
		'slug'    => 'description',
		'label'   => __( 'Descrição completa', 'gstore' ),
		'content' => $full_description,
	);
}

if ( $key_attributes_has_value ) {
	$gstore_product_tabs[] = array(
		'slug'    => 'key-attributes',
		'label'   => __( 'Principais atributos', 'gstore' ),
		'content' => $key_attributes_html,
	);
}

if ( $important_notes_has_value ) {
	$gstore_product_tabs[] = array(
		'slug'    => 'important-notes',
		'label'   => __( 'Observações importantes', 'gstore' ),
		'content' => $important_notes_html,
	);
}

if ( $reviews_has_value ) {
	$gstore_product_tabs[] = array(
		'slug'    => 'reviews',
		'label'   => __( 'Avaliações', 'gstore' ),
		'content' => '',
	);
}

$gstore_tab_next_cta_labels = array(
	'description'     => __( 'Continuar para descrição completa', 'gstore' ),
	'key-attributes'  => __( 'Ver principais atributos', 'gstore' ),
	'important-notes' => __( 'Ver observações importantes', 'gstore' ),
	'reviews'         => __( 'Ver avaliações do produto', 'gstore' ),
);

?>
<div class="Gstore-single-product-shell">
	<div class="Gstore-single-product-shell__inner">
		<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'Gstore-single-product__entry', $product ); ?>>

			<!-- Breadcrumb -->
			<nav class="Gstore-single-product__breadcrumb" aria-label="<?php esc_attr_e( 'Você está em', 'gstore' ); ?>">
				<?php
				if ( function_exists( 'woocommerce_breadcrumb' ) ) {
					woocommerce_breadcrumb(
						array(
							'delimiter'   => '<span class="Gstore-breadcrumb__separator" aria-hidden="true"></span>',
							'wrap_before' => '<div class="Gstore-breadcrumb">',
							'wrap_after'  => '</div>',
						)
					);
				}
				?>
			</nav>

			<!-- Seção Principal: Galeria + Compra -->
			<section class="Gstore-single-product__section Gstore-single-product__main">
				<div class="Gstore-single-product__left-stack">
					<div class="Gstore-single-product__left">
						<article class="product-gallery card Gstore-single-product__card Gstore-single-product__product-card">
							<div class="gallery-header">
								<div>
									<span class="badge"><?php echo esc_html( $badge_text ); ?></span>

									<h1 class="product_title entry-title product-title Gstore-single-product__title"><?php the_title(); ?></h1>

									<div class="product-meta">
										<?php if ( $sku ) : ?>
											<span class="product-meta__sku">
												<?php
												echo esc_html(
													sprintf(
														/* translators: %s: SKU do produto */
														__( 'SKU: %s', 'gstore' ),
														$sku
													)
												);
												?>
											</span>
										<?php endif; ?>

										<?php if ( $rating_display && $review_count_i18n ) : ?>
											<?php if ( $sku ) : ?>
												<span aria-hidden="true"> • </span>
											<?php endif; ?>

											<span class="product-meta__rating">
												<?php esc_html_e( 'Avaliação:', 'gstore' ); ?>
												<button type="button" class="product-meta__rating-link" data-gstore-tab-target="reviews">
													<?php echo esc_html( $rating_display ); ?> (<?php echo esc_html( $review_count_i18n ); ?>)
												</button>
											</span>
										<?php endif; ?>
									</div>
								</div>

								<button
									type="button"
									class="btn-secondary Gstore-single-product__favorite"
									aria-pressed="false"
									data-gstore-favorite-product="<?php echo esc_attr( (string) gstore_get_product_id( $product ) ); ?>"
								>
									<?php esc_html_e( 'Favoritar', 'gstore' ); ?>
								</button>
							</div>

							<div class="gallery-body Gstore-single-product__gallery">
								<div class="gallery-thumbs" data-gstore-gallery-thumbs></div>

								<div class="gallery-main">
									<?php do_action( 'woocommerce_before_single_product_summary' ); ?>

									<?php if ( $is_variable ) : ?>
										<div class="gallery-preview">
											<?php esc_html_e( 'Preview:', 'gstore' ); ?>
											<strong id="variantPreview" data-gstore-variation-preview aria-live="polite">—</strong>
										</div>
									<?php endif; ?>

									<button type="button" class="btn-secondary" data-gstore-gallery-zoom>
										<?php esc_html_e( 'Zoom', 'gstore' ); ?>
									</button>
								</div>
							</div>

							<?php if ( ! empty( $hero_meta_cards ) ) : ?>
								<div class="Gstore-single-product__info-cards Gstore-single-product__info-cards--gallery">
									<?php foreach ( $hero_meta_cards as $card ) : ?>
										<div class="Gstore-single-product__info-card">
											<div class="Gstore-single-product__info-title">
												<?php echo esc_html( $card['label'] ); ?>
											</div>
											<div
												class="Gstore-single-product__info-sub"
												<?php if ( ! $hide_price && ! empty( $card['is_installment'] ) ) : ?>
													data-gstore-installment-target="1"
													data-product-id="<?php echo esc_attr( (string) gstore_get_product_id( $product ) ); ?>"
													data-max-installments="<?php echo esc_attr( $installments ); ?>"
													data-initial-text="<?php echo esc_attr( wp_strip_all_tags( (string) $card['text'] ) ); ?>"
												<?php endif; ?>
											>
												<?php
												if ( ! empty( $card['allow_html'] ) ) {
													echo wp_kses_post( $card['text'] );
												} else {
													echo esc_html( $card['text'] );
												}
												?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</article>
					</div>

					<?php if ( ! empty( $gstore_product_tabs ) ) : ?>
						<article class="Gstore-single-product__card Gstore-single-product__tabs" data-gstore-tabs>
							<div class="Gstore-single-product__tab-buttons" role="tablist" aria-label="<?php esc_attr_e( 'Informações do produto', 'gstore' ); ?>">
								<?php foreach ( $gstore_product_tabs as $index => $tab ) : ?>
									<?php
									$slug      = isset( $tab['slug'] ) ? (string) $tab['slug'] : '';
									$label     = isset( $tab['label'] ) ? (string) $tab['label'] : '';
									$is_active = 0 === (int) $index;
									if ( '' === $slug || '' === $label ) {
										continue;
									}
									?>
									<button
										type="button"
										class="<?php echo $is_active ? 'is-active' : ''; ?>"
										role="tab"
										aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
										aria-controls="gstore-tab-<?php echo esc_attr( $slug ); ?>"
										id="gstore-tab-btn-<?php echo esc_attr( $slug ); ?>"
										data-gstore-tab="<?php echo esc_attr( $slug ); ?>"
									>
										<?php echo esc_html( $label ); ?>
									</button>
								<?php endforeach; ?>
							</div>

							<div class="Gstore-single-product__tab-panels">
								<?php foreach ( $gstore_product_tabs as $index => $tab ) : ?>
									<?php
									$slug      = isset( $tab['slug'] ) ? (string) $tab['slug'] : '';
									$label     = isset( $tab['label'] ) ? (string) $tab['label'] : '';
									$content   = isset( $tab['content'] ) ? (string) $tab['content'] : '';
									$is_active = 0 === (int) $index;
									$next_tab  = isset( $gstore_product_tabs[ $index + 1 ] ) && is_array( $gstore_product_tabs[ $index + 1 ] ) ? $gstore_product_tabs[ $index + 1 ] : array();
									$next_slug = isset( $next_tab['slug'] ) ? (string) $next_tab['slug'] : '';
									$next_cta  = isset( $gstore_tab_next_cta_labels[ $next_slug ] ) ? (string) $gstore_tab_next_cta_labels[ $next_slug ] : '';
									$content_classes = 'Gstore-single-product__tab-content';
									$content_id      = '';
									if ( 'description' === $slug ) {
										$content_id       = 'tab-description';
										$content_classes .= ' woocommerce-Tabs-panel woocommerce-Tabs-panel--description panel entry-content wc-tab';
									}

									if ( '' === $slug || '' === $label ) {
										continue;
									}
									?>
									<div
										id="gstore-tab-<?php echo esc_attr( $slug ); ?>"
										class="Gstore-single-product__tab-panel<?php echo $is_active ? ' is-active' : ''; ?>"
										role="tabpanel"
										aria-labelledby="gstore-tab-btn-<?php echo esc_attr( $slug ); ?>"
										<?php echo $is_active ? '' : 'hidden'; ?>
									>
										<?php if ( 'reviews' === $slug ) : ?>
											<?php comments_template(); ?>
										<?php else : ?>
											<section class="Gstore-single-product__tab-section" aria-label="<?php echo esc_attr( $label ); ?>">
												<h3 class="Gstore-single-product__tab-title">
													<?php echo esc_html( $label ); ?>
												</h3>
												<div
													<?php echo '' !== $content_id ? 'id="' . esc_attr( $content_id ) . '"' : ''; ?>
													class="<?php echo esc_attr( $content_classes ); ?>"
												>
													<?php echo wp_kses_post( $content ); ?>
												</div>
												<?php if ( '' !== $next_slug && '' !== $next_cta ) : ?>
													<button
														type="button"
														class="Gstore-single-product__tab-next"
														data-gstore-tab-next="<?php echo esc_attr( $next_slug ); ?>"
														aria-controls="gstore-tab-<?php echo esc_attr( $next_slug ); ?>"
													>
														<span><?php echo esc_html( $next_cta ); ?></span>
														<span class="Gstore-single-product__tab-next-icon" aria-hidden="true">→</span>
													</button>
												<?php endif; ?>
											</section>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</article>
					<?php endif; ?>

					<?php
					// Vídeo do produto
					$gstore_video_url_raw = $product_id ? (string) get_post_meta( $product_id, '_gstore_video_url', true ) : '';
					$gstore_video_embed_url = '';
					if ( '' !== $gstore_video_url_raw ) {
						$gstore_video_id = '';
						if ( preg_match( '/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $gstore_video_url_raw, $gstore_vm ) ) {
							$gstore_video_id = $gstore_vm[1];
						}
						if ( '' !== $gstore_video_id ) {
							$gstore_video_embed_url = 'https://www.youtube-nocookie.com/embed/' . $gstore_video_id . '?rel=0';
						}
					}
					$gstore_video_desc_raw  = $product_id ? (string) get_post_meta( $product_id, '_gstore_video_description', true ) : '';
					$gstore_video_desc_html = ( '' !== $gstore_video_desc_raw ) ? apply_filters( 'the_content', $gstore_video_desc_raw ) : '';
					?>
					<?php if ( '' !== $gstore_video_embed_url ) : ?>
					<div class="Gstore-single-product__video">
						<div class="Gstore-single-product__video-wrapper">
							<iframe
								src="<?php echo esc_url( $gstore_video_embed_url ); ?>"
								title="<?php echo esc_attr( get_the_title() ); ?>"
								frameborder="0"
								allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
								allowfullscreen
								loading="lazy"
							></iframe>
						</div>
						<?php if ( '' !== $gstore_video_desc_html ) : ?>
						<div class="Gstore-single-product__video-description">
							<?php echo wp_kses_post( $gstore_video_desc_html ); ?>
						</div>
						<?php endif; ?>
					</div>
					<?php endif; ?>

				</div>

				<div class="Gstore-single-product__summary">
					<div class="Gstore-single-product__summary-card Gstore-single-product__buybox buybox <?php echo esc_attr( $buybox_stock_class ); ?>">
						<!-- Preço -->
						<?php if ( $hide_price || ! $is_out_of_stock || $show_price_oos ) : ?>
							<div
								class="buybox-header<?php echo ( $is_out_of_stock && $show_price_oos && ! $hide_price ) ? ' is-unavailable' : ''; ?><?php echo $hide_price ? ' is-price-hidden' : ''; ?>"
								data-gstore-price-header
								data-gstore-oos-price-mode="<?php echo esc_attr( $oos_price_mode ); ?>"
								data-gstore-hide-price="<?php echo $hide_price ? '1' : '0'; ?>"
							>
								<div>
									<?php if ( $hide_price ) : ?>
										<div class="price-label"><?php esc_html_e( 'Preço oculto no site', 'gstore' ); ?></div>
										<div class="price" id="price" data-gstore-price data-pix-percent="0">
											<?php echo wp_kses_post( gstore_get_hidden_price_mask_html( 'single' ) ); ?>
										</div>
									<?php else : ?>
										<div class="price-label"><?php esc_html_e( 'À vista no PIX', 'gstore' ); ?></div>
									<?php if ( $show_variable_from_price ) : ?>
										<div class="price-prefix" data-gstore-price-prefix><?php esc_html_e( 'A partir de', 'gstore' ); ?></div>
									<?php endif; ?>
									<?php if ( $pix_discount_price ) : ?>
										<?php
										// Com desconto Pix: preço riscado + preço Pix, no mesmo padrão visual do WooCommerce (del/ins).
										// Produto com promoção: riscado = regular. Sem promoção: riscado = current.
										$pix_strikethrough = $has_discount ? $regular_price : $display_price;
										?>
										<div class="price" id="price" data-gstore-price data-pix-percent="<?php echo esc_attr( $pix_discount_percent ); ?>">
											<del><?php echo wp_kses_post( wc_price( $pix_strikethrough ) ); ?></del>
											<ins><?php echo wp_kses_post( wc_price( $pix_discount_price ) ); ?></ins>
										</div>
									<?php else : ?>
										<div class="price" id="price" data-gstore-price data-pix-percent="0">
											<?php if ( $is_variable ) : ?>
												<?php echo wp_kses_post( $variable_price_html ); ?>
											<?php else : ?>
												<?php woocommerce_template_single_price(); ?>
											<?php endif; ?>
										</div>
									<?php endif; ?>
										<?php if ( $formatted_installment ) : ?>
											<div class="price-sub-wrapper" data-gstore-installment-wrapper>
												<div
													class="price-sub"
													data-gstore-installment-target="1"
													data-product-id="<?php echo esc_attr( (string) ( ! empty( $installment_preview['product_id'] ) ? $installment_preview['product_id'] : gstore_resolve_installment_product_id( $product ) ) ); ?>"
													data-max-installments="<?php echo esc_attr( $installments ); ?>"
													data-initial-text="<?php echo esc_attr( wp_strip_all_tags( (string) $formatted_installment ) ); ?>"
												>
													<?php echo wp_kses_post( $formatted_installment ); ?>
												</div>
												<select
													class="price-sub-installments-select"
													data-gstore-installment-select
													aria-label="<?php esc_attr_e( 'Selecione o número de parcelas', 'gstore' ); ?>"
													style="display: none;"
												>
													<option value=""><?php esc_html_e( 'Carregando...', 'gstore' ); ?></option>
												</select>
											</div>
										<?php endif; ?>
									<?php endif; ?>
								</div>

								<?php if ( ! $hide_price && $show_price_oos ) : ?>
									<div class="price-unavailable-notice" data-gstore-price-unavailable-notice<?php echo $is_out_of_stock ? '' : ' hidden'; ?>>
										<i class="fa-solid fa-circle-info" aria-hidden="true"></i>
										<?php esc_html_e( 'Preço de referência (produto indisponível)', 'gstore' ); ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $hero_meta_cards ) ) : ?>
							<div class="Gstore-single-product__buybox-meta-strip" aria-label="<?php esc_attr_e( 'Destaques', 'gstore' ); ?>">
								<?php foreach ( $hero_meta_cards as $card ) : ?>
									<div class="Gstore-single-product__buybox-meta-chip">
										<div class="Gstore-single-product__buybox-meta-chip-label">
											<?php echo esc_html( $card['label'] ); ?>
										</div>
										<div class="Gstore-single-product__buybox-meta-chip-text">
											<?php
											if ( ! empty( $card['allow_html'] ) ) {
												echo wp_kses_post( $card['text'] );
											} else {
												echo esc_html( $card['text'] );
											}
											?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<!-- Disponibilidade -->
						<div class="stock <?php echo esc_attr( $buybox_stock_class ); ?>" 
							data-gstore-stock-block
							data-availability
							data-default-class="<?php echo esc_attr( $buybox_stock_class ); ?>"
							data-default-title="<?php echo esc_attr( $stock_title ); ?>"
							data-default-subtitle="<?php echo esc_attr( $stock_subtitle ); ?>"
							data-oos-title="<?php echo esc_attr__( 'Indisponível', 'gstore' ); ?>"
							data-oos-subtitle="<?php echo esc_attr__( 'Sem estoque no momento', 'gstore' ); ?>">
							<div class="stock-title" data-gstore-stock-title>
								<?php echo esc_html( $stock_title ); ?>
							</div>
							<div class="stock-sub" data-gstore-stock-subtitle>
								<?php echo esc_html( $stock_subtitle ); ?>
							</div>
						</div>

						<!-- Variações + Quantidade + CTA -->
						<div class="Gstore-single-product__add-to-cart">
							<?php
							$att_page = get_page_by_path( 'atendimento' );
							$att_url  = $att_page ? get_permalink( $att_page ) : home_url( '/atendimento/' );
							$warning_text = $is_out_of_stock
								? __( 'Selecione marca, cor, tamanho para enviar sua escolha ao atendimento.', 'gstore' )
								: __( 'Selecione todas as opções para liberar o botão', 'gstore' );

							$should_render_buy_now = ! $is_out_of_stock;

							$buy_now_button = $should_render_buy_now
								? sprintf(
									'<button type="submit" name="gstore_buy_now" value="1" class="btn-outline Gstore-single-product__buy-now"%1$s>%2$s</button>',
									$is_variable ? ' disabled' : '',
									esc_html__( 'Comprar agora', 'gstore' )
								)
								: '';

							$warning_markup = sprintf(
								'<div class="warning" id="warning" data-gstore-variation-warning>%s</div>',
								esc_html( $warning_text )
							);

							$should_render_add_to_cart = ! $is_out_of_stock || $is_variable;

							if ( $should_render_add_to_cart ) {
								ob_start();
								woocommerce_template_single_add_to_cart();
								$add_to_cart_markup = ob_get_clean();

								if ( $add_to_cart_markup ) {
									// Se o markup não contém um form válido, usa o fluxo padrão.
									if ( false === stripos( $add_to_cart_markup, '<form' ) ) {
										$add_to_cart_markup = '';
									}
								}

								if ( $add_to_cart_markup ) {
									// Aplica classe btn-main ao botão de adicionar ao carrinho (apenas uma vez).
									if ( false === strpos( $add_to_cart_markup, 'single_add_to_cart_button btn-main' ) ) {
										$add_to_cart_markup = preg_replace(
											'/(<button[^>]*class="[^"]*)single_add_to_cart_button([^"]*")/i',
											'$1single_add_to_cart_button btn-main$2',
											$add_to_cart_markup,
											1
										);
									}

									// Injeta o aviso ENTRE os selects e a área de quantidade/botões (produto variável).
									if ( $is_variable && false === strpos( $add_to_cart_markup, 'data-gstore-variation-warning' ) ) {
										if ( preg_match( '/<div class="single_variation_wrap"/i', $add_to_cart_markup ) ) {
											$add_to_cart_markup = preg_replace(
												'/(<div class="single_variation_wrap")/i',
												$warning_markup . '$1',
												$add_to_cart_markup,
												1
											);
										} elseif ( false !== strpos( $add_to_cart_markup, '</form>' ) ) {
											$add_to_cart_markup = str_replace( '</form>', $warning_markup . '</form>', $add_to_cart_markup );
										}
									}

									// Insere botão "Comprar agora" após o botão "Adicionar ao carrinho" (quando aplicável).
									if ( $buy_now_button && false === strpos( $add_to_cart_markup, 'Gstore-single-product__buy-now' ) ) {
										$button_pattern = '/(<button[^>]*single_add_to_cart_button[^>]*>.*?<\\/button>)/s';

										if ( preg_match( $button_pattern, $add_to_cart_markup ) ) {
											$add_to_cart_markup = preg_replace( $button_pattern, '$1' . $buy_now_button, $add_to_cart_markup, 1 );
										} elseif ( false !== strpos( $add_to_cart_markup, '</form>' ) ) {
											$add_to_cart_markup = str_replace( '</form>', $buy_now_button . '</form>', $add_to_cart_markup );
										}
									}

									echo $add_to_cart_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								} else {
									woocommerce_template_single_add_to_cart();
									if ( $is_variable ) {
										echo wp_kses_post( $warning_markup );
									}
									if ( $buy_now_button ) {
										echo wp_kses_post( $buy_now_button );
									}
								}
							}

						// Mostrar o card de indisponível:
						// - Produto simples sem estoque: sempre visível
						// - Produto variável sem estoque (todas variações): sempre visível
						// - Produto variável com algumas variações em estoque: oculto inicialmente, JS controla
						$show_oos_card        = $is_out_of_stock || $is_variable;
						$oos_card_hidden      = $is_variable && $has_any_variation_in_stock;

						if ( $show_oos_card ) :
							?>
							<div class="Gstore-oos-card" id="gstore-oos-card" role="region" aria-label="<?php esc_attr_e( 'Produto indisponível', 'gstore' ); ?>"<?php echo $oos_card_hidden ? ' hidden' : ''; ?> data-gstore-oos-card>
								<div class="Gstore-oos-card__title"><?php esc_html_e( 'Produto indisponível no momento', 'gstore' ); ?></div>
								<div class="Gstore-oos-card__text">
									<?php esc_html_e( 'Quer saber previsão de reposição ou alternativas? Fale com nossa equipe.', 'gstore' ); ?>
								</div>
								<a class="Gstore-oos-card__cta" href="<?php echo esc_url( $att_url ); ?>">
									<?php esc_html_e( 'Entrar em contato', 'gstore' ); ?>
								</a>
							</div>
							<?php
						endif;
						?>
						</div>

						<!-- Leia antes -->
						<div class="read-before">
							<a href="<?php echo esc_url( home_url( '/informativo' ) ); ?>">
								<div>
									<strong><?php esc_html_e( 'Leia antes de comprar', 'gstore' ); ?></strong>
									<div class="read-sub"><?php esc_html_e( 'Veja como funciona o processo passo a passo', 'gstore' ); ?></div>
								</div>
								<span aria-hidden="true">→</span>
							</a>
						</div>

						<!-- Ajuda -->
						<?php if ( ! empty( $contact_entries ) ) : ?>
							<section class="help help-box" aria-label="<?php esc_attr_e( 'Precisa de ajuda', 'gstore' ); ?>">
								<h3 class="help-title help-box__title"><?php esc_html_e( 'Precisa de ajuda?', 'gstore' ); ?></h3>

								<div class="help-items">
									<?php foreach ( $contact_entries as $contact ) : ?>
										<div class="help-item">
											<div class="help-item__text">
												<div class="help-item__heading"><?php echo esc_html( $contact['label'] ); ?></div>
												<div class="help-item__sub"><?php echo esc_html( $contact['value'] ); ?></div>
											</div>

											<?php if ( ! empty( $contact['cta'] ) && ! empty( $contact['href'] ) ) : ?>
												<a class="help-btn help-item__btn" href="<?php echo esc_url( $contact['href'] ); ?>">
													<?php echo esc_html( $contact['cta'] ); ?>
												</a>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</section>
						<?php endif; ?>

						<!-- Entrega (calculador de frete) -->
						<div
							class="shipping gstore-shipping-calculator"
							data-gstore-shipping-context="single_product"
							data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
						>
							<strong><?php esc_html_e( 'Entrega', 'gstore' ); ?></strong>

							<div class="shipping-row gstore-shipping-calculator__form">
								<input 
									type="text" 
									class="gstore-shipping-calculator__cep" 
									placeholder="<?php esc_attr_e( '00000-000', 'gstore' ); ?>"
									maxlength="9"
									aria-label="<?php esc_attr_e( 'CEP para cálculo de frete', 'gstore' ); ?>"
								/>
								<button type="button" class="gstore-shipping-calculator__button">
									<i class="fa-solid fa-truck" aria-hidden="true"></i>
									<?php esc_html_e( 'Calcular', 'gstore' ); ?>
								</button>
							</div>

							<div class="shipping-sub gstore-shipping-calculator__result" role="region" aria-live="polite"></div>
							<div class="gstore-shipping-calculator__error" role="alert"></div>
						</div>

						<?php gstore_render_product_related_category_links( $related_category_cards, 'desktop' ); ?>

					</div>
				</div>
			</section>

			<!-- Seção de Upsells/Relacionados -->
			<?php gstore_render_product_related_category_links( $related_category_cards, 'mobile' ); ?>

			<section class="Gstore-single-product__section Gstore-single-product__upsells">
				<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
			</section>

		</div>
	</div>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>

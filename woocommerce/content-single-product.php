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
					$product->get_id(),
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
			),
			array(
				'icon'       => 'fa-credit-card',
				'label'      => __( 'Condições de pagamento', 'gstore' ),
				'text'       => $formatted_installment ? $formatted_installment : __( 'Parcele no cartão ou finalize no PIX.', 'gstore' ),
				'allow_html' => (bool) $formatted_installment,
			),
			array(
				'icon'       => 'fa-truck-fast',
				'label'      => __( 'Envio monitorado', 'gstore' ),
				'text'       => __( 'Rastreamento atualizado em cada etapa.', 'gstore' ),
				'allow_html' => false,
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

		$attribute_lines = gstore_format_items_as_lines( $attribute_data, true );

		$rows = array(
			array(
				'icon'       => 'fa-circle-info',
				'title'      => __( 'Resumo do produto', 'gstore' ),
				'content'    => $short_description ? $short_description : __( 'Produto selecionado pela curadoria CAC Armas, com materiais premium e suporte dedicado em todas as etapas.', 'gstore' ),
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

		$rows[] = array(
			'icon'       => 'fa-sliders',
			'title'      => __( 'Informações técnicas', 'gstore' ),
			'content'    => ! empty( $attribute_lines ) ? gstore_render_details_list( $attribute_lines ) : '',
			'allow_html' => ! empty( $attribute_lines ),
		);

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
		$support_email  = get_option( 'admin_email' );
		$my_account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';

		$entries = array(
			array(
				'icon'  => 'fa-headset',
				'label' => __( 'Atendimento dedicado', 'gstore' ),
				'value' => __( 'Nosso time responde em até 2h úteis via WhatsApp ou e-mail.', 'gstore' ),
				'cta'   => $support_email ? __( 'Enviar mensagem', 'gstore' ) : '',
				'href'  => $support_email ? 'mailto:' . $support_email : '',
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

// Categoria do produto.
$category_label = __( 'Linha destaque CAC Armas', 'gstore' );
$categories     = get_the_terms( $product->get_id(), 'product_cat' );

if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
	$category_label = $categories[0]->name;
}

// Descrição e avaliações.
$short_description = apply_filters( 'woocommerce_short_description', $product->get_short_description() );
$full_description  = apply_filters( 'the_content', $product->get_description() );
$review_count      = (int) $product->get_review_count();
$is_in_stock       = $product->is_in_stock();
$is_variable       = $product->is_type( 'variable' );
$sku               = (string) $product->get_sku();
$average_rating    = (float) $product->get_average_rating();
$rating_display    = $average_rating > 0 ? number_format_i18n( $average_rating, 1 ) : '';
$review_count_i18n = $review_count > 0 ? number_format_i18n( $review_count ) : '';

// Disponibilidade (seleção do admin via plugin GSTORE).
$product_id = (int) $product->get_id();

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

// Mantém um wrapper para facilitar estilização sem quebrar o layout.
$stock_label = sprintf(
	'<span class="disponibilidade-texto">%s</span>',
	esc_html( $texto_disponibilidade )
);

// Preços e parcelamento.
$regular_price    = (float) $product->get_regular_price();
$current_price    = (float) $product->get_price();
$has_discount     = $product->is_on_sale() && $regular_price > 0;
$discount_percent = $has_discount ? round( ( ( $regular_price - $current_price ) / $regular_price ) * 100 ) : 0;
$installments     = (int) apply_filters( 'armastore_single_product_installments', 21, $product );
// Calcula parcela COM JUROS (taxa Blu: 2.99% a.m.)
$installment_amt  = ( $current_price > 0 && $installments ) ? gstore_calculate_installment_with_interest( $current_price, $installments ) : 0;

$formatted_installment = $installment_amt > 0
	? sprintf(
		/* translators: 1: número de parcelas, 2: valor da parcela */
		__( 'ou %1$dx de %2$s', 'gstore' ),
		$installments,
		wc_price( $installment_amt )
	)
	: '';

/*
|--------------------------------------------------------------------------
| Atributos e características do produto
|--------------------------------------------------------------------------
*/

$attribute_data = gstore_get_product_attributes( $product );

// Badge do produto (prioriza o 1º atributo visível; fallback: categoria).
$badge_text = $category_label;
if ( ! empty( $attribute_data ) && ! empty( $attribute_data[0]['value'] ) ) {
	$first_value = trim( (string) $attribute_data[0]['value'] );
	if ( '' !== $first_value ) {
		$first_value = explode( ',', $first_value )[0];
		$first_value = trim( (string) $first_value );
		if ( '' !== $first_value ) {
			$badge_text = $first_value;
		}
	}
}

/*
|--------------------------------------------------------------------------
| Dados para seções do template
|--------------------------------------------------------------------------
*/

$benefit_items = array(
	__( 'Entrega rápida com rastreamento em tempo real.', 'gstore' ),
	__( 'Garantia oficial CAC Armas com suporte humano.', 'gstore' ),
	__( 'Parcelamento e pagamento instantâneo via PIX.', 'gstore' ),
);

$hero_meta_cards   = gstore_get_hero_meta_cards( $stock_label, $formatted_installment );
$details_info_rows = gstore_get_details_info_rows( $product->get_id(), $short_description, $full_description, $attribute_data );
$contact_entries   = gstore_get_contact_entries();
$guarantee_badges  = gstore_get_guarantee_badges();

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
							'delimiter'   => '<span aria-hidden="true">/</span>',
							'wrap_before' => '<div class="Gstore-breadcrumb">',
							'wrap_after'  => '</div>',
						)
					);
				}
				?>
			</nav>

			<!-- Seção Principal: Galeria + Compra -->
			<section class="Gstore-single-product__section Gstore-single-product__main">
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
								data-gstore-favorite-product="<?php echo esc_attr( $product->get_id() ); ?>"
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
							<div class="Gstore-single-product__info-cards">
								<?php foreach ( $hero_meta_cards as $card ) : ?>
									<div class="Gstore-single-product__info-card">
										<div class="Gstore-single-product__info-title">
											<?php echo esc_html( $card['label'] ); ?>
										</div>
										<div class="Gstore-single-product__info-sub">
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

					<article class="Gstore-single-product__card Gstore-single-product__tabs" data-gstore-tabs>
						<div class="Gstore-single-product__tab-buttons" role="tablist" aria-label="<?php esc_attr_e( 'Informações do produto', 'gstore' ); ?>">
							<button type="button" class="is-active" role="tab" aria-selected="true" aria-controls="gstore-tab-description" id="gstore-tab-btn-description" data-gstore-tab="description">
								<?php esc_html_e( 'Descrição', 'gstore' ); ?>
							</button>
							<button type="button" role="tab" aria-selected="false" aria-controls="gstore-tab-specs" id="gstore-tab-btn-specs" data-gstore-tab="specs">
								<?php esc_html_e( 'Especificações', 'gstore' ); ?>
							</button>
							<button type="button" role="tab" aria-selected="false" aria-controls="gstore-tab-reviews" id="gstore-tab-btn-reviews" data-gstore-tab="reviews">
								<?php esc_html_e( 'Avaliações', 'gstore' ); ?>
							</button>
						</div>

						<div class="Gstore-single-product__tab-panels">
							<div id="gstore-tab-description" class="Gstore-single-product__tab-panel is-active" role="tabpanel" aria-labelledby="gstore-tab-btn-description">
								<?php if ( ! empty( $details_info_rows ) ) : ?>
									<?php foreach ( $details_info_rows as $row ) : ?>
										<?php
										$description_icons = array( 'fa-circle-info', 'fa-file-lines' );
										if ( empty( $row['icon'] ) || ! in_array( $row['icon'], $description_icons, true ) ) {
											continue;
										}
										?>
										<section class="Gstore-single-product__tab-section" aria-label="<?php echo esc_attr( $row['title'] ); ?>">
											<h3 class="Gstore-single-product__tab-title">
												<?php echo esc_html( $row['title'] ); ?>
											</h3>
											<div class="Gstore-single-product__tab-content">
												<?php
												if ( ! empty( $row['allow_html'] ) ) {
													echo wp_kses_post( $row['content'] );
												} else {
													echo esc_html( $row['content'] );
												}
												?>
											</div>
										</section>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>

							<div id="gstore-tab-specs" class="Gstore-single-product__tab-panel" role="tabpanel" aria-labelledby="gstore-tab-btn-specs" hidden>
								<?php if ( ! empty( $details_info_rows ) ) : ?>
									<?php foreach ( $details_info_rows as $row ) : ?>
										<?php
										$description_icons = array( 'fa-circle-info', 'fa-file-lines' );
										if ( ! empty( $row['icon'] ) && in_array( $row['icon'], $description_icons, true ) ) {
											continue;
										}
										?>
										<section class="Gstore-single-product__tab-section" aria-label="<?php echo esc_attr( $row['title'] ); ?>">
											<h3 class="Gstore-single-product__tab-title">
												<?php echo esc_html( $row['title'] ); ?>
											</h3>
											<div class="Gstore-single-product__tab-content">
												<?php
												if ( ! empty( $row['allow_html'] ) ) {
													echo wp_kses_post( $row['content'] );
												} else {
													echo esc_html( $row['content'] );
												}
												?>
											</div>
										</section>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>

							<div id="gstore-tab-reviews" class="Gstore-single-product__tab-panel" role="tabpanel" aria-labelledby="gstore-tab-btn-reviews" hidden>
								<?php comments_template(); ?>
							</div>
						</div>
					</article>
				</div>

				<div class="Gstore-single-product__summary">
					<div class="Gstore-single-product__summary-card Gstore-single-product__buybox buybox <?php echo $is_in_stock ? 'is-in-stock' : 'is-on-order'; ?>">
						<!-- Preço -->
						<div class="buybox-header">
							<div>
								<div class="price-label"><?php esc_html_e( 'À vista no PIX', 'gstore' ); ?></div>
								<div class="price" id="price" data-gstore-price>
									<?php woocommerce_template_single_price(); ?>
								</div>
								<?php if ( $is_variable ) : ?>
									<div class="price-sub"><?php esc_html_e( 'Preço muda conforme as opções', 'gstore' ); ?></div>
								<?php endif; ?>
								<?php if ( $formatted_installment ) : ?>
									<div class="price-sub">
										<?php echo wp_kses_post( $formatted_installment ); ?>
									</div>
								<?php endif; ?>
							</div>

							<button type="button" class="btn-secondary" data-gstore-reset-purchase>
								<?php esc_html_e( 'Limpar', 'gstore' ); ?>
							</button>
						</div>

						<!-- Disponibilidade -->
						<div class="stock <?php echo $is_in_stock ? 'is-in-stock' : 'is-on-order'; ?>">
							<div class="stock-title">
								<?php echo $is_in_stock ? esc_html__( 'Disponível', 'gstore' ) : esc_html__( 'Sob encomenda', 'gstore' ); ?>
							</div>
							<div class="stock-sub">
								<?php
								if ( $is_in_stock ) {
									echo esc_html( $texto_disponibilidade );
								} else {
									esc_html_e( 'Confirme prazos com nosso time', 'gstore' );
								}
								?>
							</div>
						</div>

						<!-- Variações + Quantidade + CTA -->
						<div class="Gstore-single-product__add-to-cart">
							<?php
							ob_start();
							woocommerce_template_single_add_to_cart();
							$add_to_cart_markup = ob_get_clean();

							$buy_now_button = sprintf(
								'<button type="submit" name="gstore_buy_now" value="1" class="btn-outline Gstore-single-product__buy-now"%1$s>%2$s</button>',
								$is_variable ? ' disabled' : '',
								esc_html__( 'Comprar agora', 'gstore' )
							);

							$warning_markup = sprintf(
								'<div class="warning" id="warning" data-gstore-variation-warning>%s</div>',
								esc_html__( 'Selecione todas as opções para liberar o botão', 'gstore' )
							);

							if ( $add_to_cart_markup ) {
								// Aplica classe btn-main ao botão de adicionar ao carrinho.
								$add_to_cart_markup = preg_replace(
									'/(<button[^>]*class="[^"]*)single_add_to_cart_button([^"]*")/i',
									'$1single_add_to_cart_button btn-main$2',
									$add_to_cart_markup,
									1
								);

								// Injeta o aviso ENTRE os selects e a área de quantidade/botões (produto variável).
								if ( $is_variable ) {
									if ( preg_match( '/<div class="single_variation_wrap"/i', $add_to_cart_markup ) ) {
										$add_to_cart_markup = preg_replace(
											'/(<div class="single_variation_wrap")/i',
											$warning_markup . '$1',
											$add_to_cart_markup,
											1
										);
									} elseif ( false !== strpos( $add_to_cart_markup, '</form>' ) ) {
										$add_to_cart_markup = str_replace( '</form>', $warning_markup . '</form>', $add_to_cart_markup );
									} else {
										$add_to_cart_markup .= $warning_markup;
									}
								}

								// Insere botão "Comprar agora" após o botão "Adicionar ao carrinho".
								$button_pattern = '/(<button[^>]*single_add_to_cart_button[^>]*>.*?<\\/button>)/s';

								if ( preg_match( $button_pattern, $add_to_cart_markup ) ) {
									$add_to_cart_markup = preg_replace( $button_pattern, '$1' . $buy_now_button, $add_to_cart_markup, 1 );
								} elseif ( false !== strpos( $add_to_cart_markup, '</form>' ) ) {
									$add_to_cart_markup = str_replace( '</form>', $buy_now_button . '</form>', $add_to_cart_markup );
								} else {
									$add_to_cart_markup .= $buy_now_button;
								}

								echo $add_to_cart_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} else {
								woocommerce_template_single_add_to_cart();
								if ( $is_variable ) {
									echo wp_kses_post( $warning_markup );
								}
								echo wp_kses_post( $buy_now_button );
							}
							?>
						</div>

						<!-- Leia antes -->
						<div class="read-before">
							<a href="<?php echo esc_url( home_url( '/como-comprar-arma/' ) ); ?>">
								<div>
									<strong><?php esc_html_e( 'Leia antes de comprar', 'gstore' ); ?></strong>
									<div class="read-sub"><?php esc_html_e( 'Veja como funciona o processo passo a passo', 'gstore' ); ?></div>
								</div>
								<span aria-hidden="true">→</span>
							</a>
						</div>

						<!-- Ajuda -->
						<?php if ( ! empty( $contact_entries ) ) : ?>
							<div class="help">
								<div class="help-title"><?php esc_html_e( 'Precisa de ajuda?', 'gstore' ); ?></div>

								<?php foreach ( $contact_entries as $contact ) : ?>
									<div class="help-item">
										<div>
											<strong><?php echo esc_html( $contact['label'] ); ?></strong>
											<div class="help-sub"><?php echo esc_html( $contact['value'] ); ?></div>
										</div>
										<?php if ( ! empty( $contact['cta'] ) && ! empty( $contact['href'] ) ) : ?>
											<a class="help-btn" href="<?php echo esc_url( $contact['href'] ); ?>">
												<?php echo esc_html( $contact['cta'] ); ?>
											</a>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<!-- Entrega (calculador de frete) -->
						<div class="shipping gstore-shipping-calculator">
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

						<!-- Extra (hooks de plugins) -->
						<div class="Gstore-single-product__summary-extra">
							<?php do_action( 'woocommerce_single_product_summary' ); ?>
						</div>
					</div>
				</div>
			</section>

			<!-- Seção de Upsells/Relacionados -->
			<section class="Gstore-single-product__section Gstore-single-product__upsells">
				<?php do_action( 'woocommerce_after_single_product_summary' ); ?>
			</section>

		</div>
	</div>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>

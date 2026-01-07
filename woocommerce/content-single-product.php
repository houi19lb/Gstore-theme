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

// URL de compra direta.
$buy_now_url = esc_url(
	add_query_arg(
		array( 'add-to-cart' => $product->get_id() ),
		wc_get_checkout_url()
	)
);

/*
|--------------------------------------------------------------------------
| Atributos e características do produto
|--------------------------------------------------------------------------
*/

$attribute_data = gstore_get_product_attributes( $product );

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

			<!-- Seção Principal: Galeria + Resumo -->
			<section class="Gstore-single-product__section Gstore-single-product__main">
				<div class="Gstore-single-product__gallery" data-gstore-sticky>
					<?php do_action( 'woocommerce_before_single_product_summary' ); ?>
				</div>

				<div class="Gstore-single-product__summary">
					<div class="Gstore-single-product__summary-card <?php echo $is_in_stock ? 'is-in-stock' : 'is-on-order'; ?>">
						<p class="Gstore-single-product__eyebrow">
							<i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
							<?php echo esc_html( $category_label ); ?>
						</p>

						<h1 class="product_title entry-title Gstore-single-product__title"><?php the_title(); ?></h1>

						<!-- Bloco de Preço -->
						<div class="Gstore-single-product__price-block">
							<?php if ( $has_discount ) : ?>
								<div class="Gstore-single-product__price-meta">
									<span class="Gstore-single-product__price-meta-old">
										<?php echo wp_kses_post( wc_price( $regular_price ) ); ?>
									</span>
									<span class="Gstore-single-product__price-meta-badge">
										-<?php echo esc_html( $discount_percent ); ?>%
									</span>
								</div>
							<?php endif; ?>

							<div class="Gstore-payment-label">
								<?php esc_html_e( 'À vista no PIX', 'gstore' ); ?>
							</div>
							<div class="price">
								<?php echo wp_kses_post( wc_price( $current_price ) ); ?>
							</div>

							<?php if ( $formatted_installment ) : ?>
								<p class="Gstore-single-product__installments-text">
									<?php echo wp_kses_post( $formatted_installment ); ?>
								</p>
							<?php endif; ?>
						</div>

						<!-- Add to Cart -->
						<div class="Gstore-single-product__add-to-cart">
							<div class="Gstore-single-product__stock-badge <?php echo $is_in_stock ? 'is-in-stock' : 'is-on-order'; ?>">
								<div class="Gstore-single-product__stock-badge-icon">
									<i class="fa-solid <?php echo $is_in_stock ? 'fa-circle-check' : 'fa-clock'; ?>" aria-hidden="true"></i>
								</div>
								<div class="Gstore-single-product__stock-badge-content">
									<span class="Gstore-single-product__stock-badge-label">
										<?php echo $is_in_stock ? esc_html__( 'Disponível', 'gstore' ) : esc_html__( 'Sob encomenda', 'gstore' ); ?>
									</span>
									<?php if ( ! $is_in_stock ) : ?>
										<span class="Gstore-single-product__stock-badge-note">
											<?php esc_html_e( 'Confirme prazos com nosso time', 'gstore' ); ?>
										</span>
									<?php endif; ?>
								</div>
							</div>

							<?php
							ob_start();
							woocommerce_template_single_add_to_cart();
							$add_to_cart_markup = ob_get_clean();

							if ( $add_to_cart_markup ) {
								$buy_now_button = sprintf(
									'<a class="Gstore-single-product__buy-now" href="%1$s">%2$s</a>',
									esc_url( $buy_now_url ),
									esc_html__( 'Comprar agora', 'gstore' )
								);

								$button_pattern = '/(<button[^>]*single_add_to_cart_button[^>]*>.*?<\\/button>)/s';

								if ( preg_match( $button_pattern, $add_to_cart_markup ) ) {
									$form_actions       = '<div class="Gstore-single-product__form-actions">$1' . $buy_now_button . '</div>';
									$add_to_cart_markup = preg_replace( $button_pattern, $form_actions, $add_to_cart_markup, 1 );
								} elseif ( false !== strpos( $add_to_cart_markup, '</form>' ) ) {
									$add_to_cart_markup = str_replace( '</form>', $buy_now_button . '</form>', $add_to_cart_markup );
								} else {
									$add_to_cart_markup .= $buy_now_button;
								}

								echo $add_to_cart_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} else {
								woocommerce_template_single_add_to_cart();
								?>
								<a class="Gstore-single-product__buy-now" href="<?php echo esc_url( $buy_now_url ); ?>">
									<?php esc_html_e( 'Comprar agora', 'gstore' ); ?>
								</a>
								<?php
							}
							?>
						</div>

						<!-- Calculador de Frete -->
						<div class="gstore-shipping-calculator">
							<h3 class="gstore-shipping-calculator__title">
								<i class="fa-solid fa-calculator" aria-hidden="true"></i>
								<?php esc_html_e( 'Calcular Frete', 'gstore' ); ?>
							</h3>
							<div class="gstore-shipping-calculator__form">
								<input 
									type="text" 
									class="gstore-shipping-calculator__cep" 
									placeholder="<?php esc_attr_e( '00000-000', 'gstore' ); ?>"
									maxlength="9"
									aria-label="<?php esc_attr_e( 'CEP para cálculo de frete', 'gstore' ); ?>"
								/>
								<button type="button" class="gstore-shipping-calculator__button">
									<i class="fa-solid fa-truck" aria-hidden="true"></i>
									<?php esc_html_e( 'Calcular frete', 'gstore' ); ?>
								</button>
							</div>
							<div class="gstore-shipping-calculator__result" role="region" aria-live="polite"></div>
							<div class="gstore-shipping-calculator__error" role="alert"></div>
						</div>

						<!-- Link para página "Como Comprar Arma" -->
						<a href="<?php echo esc_url( home_url( '/como-comprar-arma/' ) ); ?>" class="Gstore-single-product__read-before-buy">
							<i class="fa-solid fa-book-open" aria-hidden="true"></i>
							<span>
								<strong><?php esc_html_e( 'Leia antes de comprar', 'gstore' ); ?></strong>
								<small><?php esc_html_e( 'Veja como funciona o processo passo a passo', 'gstore' ); ?></small>
							</span>
							<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
						</a>

						<!-- Benefícios -->
						<?php if ( ! empty( $benefit_items ) ) : ?>
							<ul class="Gstore-single-product__benefits">
								<?php foreach ( $benefit_items as $benefit ) : ?>
									<li>
										<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
										<?php echo esc_html( $benefit ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<!-- Meta -->
						<div class="Gstore-single-product__meta">
							<?php woocommerce_template_single_meta(); ?>
						</div>

						<!-- Extra (hooks de plugins) -->
						<div class="Gstore-single-product__summary-extra">
							<?php do_action( 'woocommerce_single_product_summary' ); ?>
						</div>
					</div>

					<!-- Card de Contato -->
					<?php if ( ! empty( $contact_entries ) ) : ?>
						<aside class="Gstore-single-product__contact-card">
							<h3>
								<i class="fa-solid fa-headset" aria-hidden="true"></i>
								<?php esc_html_e( 'Precisa de ajuda?', 'gstore' ); ?>
							</h3>
							<ul>
								<?php foreach ( $contact_entries as $contact ) : ?>
									<li>
										<i class="fa-solid <?php echo esc_attr( $contact['icon'] ); ?>" aria-hidden="true"></i>
										<div>
											<strong><?php echo esc_html( $contact['label'] ); ?></strong>
											<span><?php echo esc_html( $contact['value'] ); ?></span>
										</div>
										<?php if ( ! empty( $contact['cta'] ) && ! empty( $contact['href'] ) ) : ?>
											<a href="<?php echo esc_url( $contact['href'] ); ?>">
												<?php echo esc_html( $contact['cta'] ); ?>
											</a>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</aside>
					<?php endif; ?>
				</div>
			</section>

			<!-- Seção de Detalhes -->
			<section class="Gstore-single-product__section Gstore-single-product__details">
				<div class="Gstore-single-product__details-overview">
					<?php if ( ! empty( $hero_meta_cards ) ) : ?>
						<div class="Gstore-single-product__details-highlight-grid">
							<?php foreach ( $hero_meta_cards as $card ) : ?>
								<article class="Gstore-single-product__details-highlight">
									<div class="Gstore-single-product__details-highlight-icon">
										<i class="fa-solid <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></i>
									</div>
									<div>
										<span class="Gstore-single-product__details-highlight-label">
											<?php echo esc_html( $card['label'] ); ?>
										</span>
										<p>
											<?php
											if ( ! empty( $card['allow_html'] ) ) {
												echo wp_kses_post( $card['text'] );
											} else {
												echo esc_html( $card['text'] );
											}
											?>
										</p>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $details_info_rows ) ) : ?>
						<div class="Gstore-single-product__details-info-card">
							<?php foreach ( $details_info_rows as $index => $row ) : ?>
								<?php
								$row_id = 'product-details-row-' . $index;
								?>
								<article class="Gstore-single-product__details-info-row Gstore-review-card--collapsible" aria-label="<?php echo esc_attr( $row['title'] ); ?>">
									<button 
										type="button" 
										class="Gstore-review-card__toggle Gstore-single-product__details-info-toggle" 
										aria-expanded="false" 
										aria-controls="<?php echo esc_attr( $row_id ); ?>"
										onclick="this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'false' ? 'true' : 'false'); this.closest('.Gstore-review-card--collapsible').classList.toggle('is-expanded');"
									>
										<header class="Gstore-review-card__header Gstore-single-product__details-info-meta">
											<div>
												<span class="Gstore-single-product__details-info-icon">
													<i class="fa-solid <?php echo esc_attr( $row['icon'] ); ?>" aria-hidden="true"></i>
												</span>
												<strong><?php echo esc_html( $row['title'] ); ?></strong>
											</div>
											<span class="Gstore-review-card__toggle-icon" aria-hidden="true">
												<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
													<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
												</svg>
											</span>
										</header>
									</button>
									<div id="<?php echo esc_attr( $row_id ); ?>" class="Gstore-review-card__content">
										<div class="Gstore-review-card__content-inner">
											<div class="Gstore-single-product__details-info-content">
												<?php
												if ( ! empty( $row['allow_html'] ) ) {
													echo wp_kses_post( $row['content'] );
												} else {
													echo esc_html( $row['content'] );
												}
												?>
											</div>
										</div>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="Gstore-single-product__details-reviews">
					<?php comments_template(); ?>
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

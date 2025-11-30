<?php
/**
 * Template customizado para exibir as avaliações de um produto.
 *
 * @package WooCommerce\Templates
 * @version 9.7.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product || ! comments_open() ) {
	return;
}

$review_count   = (int) $product->get_review_count();
$average_rating = (float) $product->get_average_rating();
$rating_counts  = (array) $product->get_rating_counts();
$total_ratings  = array_sum( $rating_counts );
$rating_html    = wc_get_rating_html( $average_rating, $review_count );

$reviews_subtitle = $review_count
	? sprintf(
		/* translators: %s: review count */
		esc_html(
			_n(
				'%s avaliação publicada · Apenas clientes com compra aprovada podem avaliar.',
				'%s avaliações publicadas · Apenas clientes com compra aprovada podem avaliar.',
				$review_count,
				'gstore'
			)
		),
		number_format_i18n( $review_count )
	)
	: esc_html__(
		'Ainda não há avaliações publicadas · Apenas clientes com compra aprovada podem avaliar.',
		'gstore'
	);

$average_display = $average_rating ? number_format_i18n( $average_rating, 1 ) : '0';

if ( ! function_exists( 'gstore_render_product_review' ) ) {
	/**
	 * Callback customizado para listar avaliações.
	 *
	 * @param WP_Comment $comment Comentário atual.
	 * @param array      $args    Argumentos do wp_list_comments.
	 */
	function gstore_render_product_review( $comment, $args, $depth ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$GLOBALS['comment'] = $comment;

		$rating        = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
		$rating_markup = $rating ? wc_get_rating_html( $rating ) : '';
		$is_verified   = wc_review_is_from_verified_owner( $comment->comment_ID );
		?>
		<article <?php comment_class( 'Gstore-review-item' ); ?> id="comment-<?php comment_ID(); ?>">
			<header class="Gstore-review-item__header">
				<div class="Gstore-review-item__author">
					<span class="Gstore-review-item__author-name"><?php comment_author(); ?></span>
					<span class="Gstore-review-item__meta">
						<?php echo esc_html( get_comment_date() ); ?>
						<?php if ( $is_verified ) : ?>
							<span aria-hidden="true">&#183;</span>
							<?php esc_html_e( 'Compra verificada', 'gstore' ); ?>
						<?php endif; ?>
					</span>
				</div>
				<div class="Gstore-review-item__rating">
					<?php if ( $rating_markup ) : ?>
						<span class="Gstore-review-stars" aria-hidden="true">
							<?php echo wp_kses_post( $rating_markup ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $rating ) : ?>
						<span class="Gstore-review-item__rating-text">
							<?php
							printf(
								/* translators: %s: numeric rating */
								esc_html__( '%s / 5', 'gstore' ),
								number_format_i18n( $rating, 1 )
							);
							?>
						</span>
					<?php endif; ?>
				</div>
			</header>
			<div class="Gstore-review-item__body">
				<?php if ( '0' === $comment->comment_approved ) : ?>
					<em><?php esc_html_e( 'Sua avaliação está aguardando moderação.', 'gstore' ); ?></em>
				<?php endif; ?>
				<?php comment_text(); ?>
			</div>
		</article>
		<?php
	}
}

?>
<section id="reviews" class="woocommerce-Reviews" aria-label="<?php esc_attr_e( 'Avaliações do produto', 'gstore' ); ?>">
	<div class="Gstore-review-layout">
		<article class="Gstore-review-card Gstore-review-card--unified" aria-label="<?php esc_attr_e( 'Avaliações do produto', 'gstore' ); ?>">
			<header class="Gstore-review-card__header">
				<div>
					<h3><?php esc_html_e( 'Experiência geral dos clientes', 'gstore' ); ?></h3>
					<p><?php echo esc_html( $reviews_subtitle ); ?></p>
				</div>
				<span class="Gstore-review-chip"><?php esc_html_e( 'Avaliações verificadas', 'gstore' ); ?></span>
			</header>

			<div class="Gstore-review-summary">
				<div class="Gstore-review-score" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Nota média %s de 5', 'gstore' ), $average_display ) ); ?>">
					<div class="Gstore-review-score__value">
						<span class="Gstore-review-score__number"><?php echo esc_html( $average_display ); ?></span>
						<span class="Gstore-review-score__max">/ 5</span>
					</div>
					<?php if ( $rating_html ) : ?>
						<div class="Gstore-review-stars" aria-hidden="true">
							<?php echo wp_kses_post( $rating_html ); ?>
						</div>
					<?php endif; ?>
					<span class="Gstore-review-score__meta">
						<?php
						if ( $review_count ) {
							printf(
								/* translators: %s: review count */
								esc_html__( 'Baseado em %s avaliações de clientes', 'gstore' ),
								number_format_i18n( $review_count )
							);
						} else {
							esc_html_e( 'Ainda não recebemos avaliações para este produto.', 'gstore' );
						}
						?>
					</span>
				</div>

				<div class="Gstore-review-distribution" aria-label="<?php esc_attr_e( 'Distribuição das notas', 'gstore' ); ?>">
					<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
						<?php
						$label       = sprintf(
							/* translators: %s: number of stars */
							_n( '%s estrela', '%s estrelas', $i, 'gstore' ),
							number_format_i18n( $i )
						);
						$rating_key   = (string) $i;
						$rating_total = isset( $rating_counts[ $rating_key ] ) ? (int) $rating_counts[ $rating_key ] : 0;
						$percentage   = $total_ratings ? ( $rating_total / $total_ratings ) * 100 : 0;
						?>
						<div class="Gstore-review-distribution__row">
							<span class="Gstore-review-distribution__label"><?php echo esc_html( $label ); ?></span>
							<div class="Gstore-review-distribution__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( round( $percentage ) ); ?>">
								<span class="Gstore-review-distribution__bar-fill" style="width: <?php echo esc_attr( $percentage ); ?>%;"></span>
							</div>
							<span class="Gstore-review-distribution__count"><?php echo esc_html( number_format_i18n( $rating_total ) ); ?></span>
						</div>
					<?php endfor; ?>
				</div>
			</div>

			<div class="Gstore-review-body" aria-label="<?php esc_attr_e( 'Lista de avaliações', 'gstore' ); ?>">
				<div class="Gstore-reviews-list">
					<?php if ( have_comments() ) : ?>
						<?php
						wp_list_comments(
							apply_filters(
								'woocommerce_product_review_list_args',
								array(
									'callback' => 'gstore_render_product_review',
									'style'    => 'div',
									'short_ping' => true,
								)
							)
						);
						?>

						<?php
						if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :
							?>
							<nav class="Gstore-review-pagination">
								<?php
								paginate_comments_links(
									apply_filters(
										'woocommerce_comment_pagination_args',
										array(
											'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
											'next_text' => is_rtl() ? '&larr;' : '&rarr;',
										)
									)
								);
								?>
							</nav>
							<?php
						endif;
						?>
					<?php else : ?>
						<article class="Gstore-review-item Gstore-review-item--empty" aria-hidden="true">
							<header class="Gstore-review-item__header">
								<div class="Gstore-review-item__author">
									<span class="Gstore-review-item__author-name"><?php esc_html_e( 'Cliente reservado', 'gstore' ); ?></span>
									<span class="Gstore-review-item__meta"><?php esc_html_e( 'Sua avaliação pode aparecer aqui', 'gstore' ); ?></span>
								</div>
								<div class="Gstore-review-item__rating">
									<span class="Gstore-review-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
									<span class="Gstore-review-item__rating-text">5 / 5</span>
								</div>
							</header>
							<div class="Gstore-review-item__body">
								<p><?php esc_html_e( 'Compartilhe sua experiência e ajude outros clientes a comprarem com segurança.', 'gstore' ); ?></p>
							</div>
						</article>
					<?php endif; ?>
				</div>
			</div>

			<div class="Gstore-review-separator" aria-hidden="true"></div>

			<div class="Gstore-review-form-section Gstore-review-card--collapsible" aria-label="<?php esc_attr_e( 'Adicionar uma avaliação', 'gstore' ); ?>">
			<button 
				type="button" 
				class="Gstore-review-card__toggle" 
				aria-expanded="false" 
				aria-controls="review-form-content"
				onclick="this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'false' ? 'true' : 'false'); this.closest('.Gstore-review-card--collapsible').classList.toggle('is-expanded');"
			>
				<header class="Gstore-review-card__header">
					<div>
						<h3><?php esc_html_e( 'Deixe sua avaliação', 'gstore' ); ?></h3>
						<p class="Gstore-review-card__hint">
							<?php esc_html_e( 'Clique para expandir e compartilhar sua experiência', 'gstore' ); ?>
						</p>
					</div>
					<span class="Gstore-review-card__toggle-icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
				</header>
			</button>

			<div id="review-form-content" class="Gstore-review-card__content">
				<div class="Gstore-review-card__content-inner">
					<p class="Gstore-review-card__description">
						<?php
						printf(
							/* translators: %s: required field indicator */
							esc_html__( 'Somente o seu primeiro nome será exibido publicamente. O seu endereço de e-mail não será publicado. Campos marcados com %s são obrigatórios.', 'gstore' ),
							'<span aria-hidden="true">*</span>'
						);
						?>
					</p>

					<?php if ( get_option( 'woocommerce_review_rating_verification_required' ) === 'no' || wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) ) : ?>
						<div id="review_form_wrapper" class="Gstore-review-form-wrapper">
						<?php
						$commenter          = wp_get_current_commenter();
						$require_name_email = (bool) get_option( 'require_name_email', 1 );

						$fields = array(
							'author' => sprintf(
								'<div class="Gstore-review-form__group"><label class="Gstore-review-form__label" for="author">%1$s%2$s</label><input id="author" name="author" type="text" class="Gstore-review-form__field" value="%3$s" placeholder="%4$s" %5$s autocomplete="name" /></div>',
								esc_html__( 'Nome', 'gstore' ),
								$require_name_email ? '<span class="required" aria-hidden="true">*</span>' : '',
								esc_attr( $commenter['comment_author'] ),
								esc_attr__( 'Como você deseja ser identificado', 'gstore' ),
								$require_name_email ? 'required' : ''
							),
							'email'  => sprintf(
								'<div class="Gstore-review-form__group"><label class="Gstore-review-form__label" for="email">%1$s%2$s</label><input id="email" name="email" type="email" class="Gstore-review-form__field" value="%3$s" placeholder="%4$s" %5$s autocomplete="email" /></div>',
								esc_html__( 'E-mail', 'gstore' ),
								$require_name_email ? '<span class="required" aria-hidden="true">*</span>' : '',
								esc_attr( $commenter['comment_author_email'] ),
								esc_attr__( 'Seu e-mail de contato', 'gstore' ),
								$require_name_email ? 'required' : ''
							),
						);

						$comment_form_fields = apply_filters( 'comment_form_default_fields', $fields );
						$comment_form_fields['cookies'] = '<div class="Gstore-review-form__checkbox"><label for="wp-comment-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" />' . esc_html__( 'Salvar meus dados para a próxima vez que eu avaliar.', 'gstore' ) . '</label></div>';

						$comment_form = array(
							'title_reply'          => '',
							'title_reply_to'       => '',
							'comment_notes_before' => '',
							'comment_notes_after'  => '',
							'fields'               => $comment_form_fields,
							'class_form'           => 'Gstore-review-form__form',
							'label_submit'         => esc_html__( 'Enviar avaliação', 'gstore' ),
							'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="Gstore-review-form__submit">%4$s</button>',
							'logged_in_as'         => '',
						);

						$comment_field_markup = '';

						if ( wc_review_ratings_enabled() ) {
							$comment_field_markup .= '<div class="Gstore-review-form__group">';
							$comment_field_markup .= '<label class="Gstore-review-form__label" for="rating">' . esc_html__( 'Sua nota geral', 'gstore' );

							if ( wc_review_ratings_required() ) {
								$comment_field_markup .= '<span class="required" aria-hidden="true">*</span>';
							}

							$comment_field_markup .= '</label>';
							$comment_field_markup .= '<div class="Gstore-review-form__rating-hint">' . esc_html__( 'Avalie de 1 a 5 estrelas a qualidade geral do produto.', 'gstore' ) . '</div>';
							$comment_field_markup .= '<select name="rating" id="rating" class="Gstore-review-form__field" ' . ( wc_review_ratings_required() ? 'required' : '' ) . '>
								<option value="">' . esc_html__( 'Selecione...', 'gstore' ) . '</option>
								<option value="5">' . esc_html__( 'Excelente', 'gstore' ) . '</option>
								<option value="4">' . esc_html__( 'Muito bom', 'gstore' ) . '</option>
								<option value="3">' . esc_html__( 'Bom', 'gstore' ) . '</option>
								<option value="2">' . esc_html__( 'Regular', 'gstore' ) . '</option>
								<option value="1">' . esc_html__( 'Ruim', 'gstore' ) . '</option>
							</select>';
							$comment_field_markup .= '</div>';
						}

						$comment_field_markup .= '<div class="Gstore-review-form__group">';
						$comment_field_markup .= '<label class="Gstore-review-form__label" for="comment">' . esc_html__( 'Sua avaliação sobre o produto', 'gstore' ) . '<span class="required" aria-hidden="true">*</span></label>';
						$comment_field_markup .= '<textarea id="comment" name="comment" class="Gstore-review-form__textarea" rows="6" placeholder="' . esc_attr__( 'Comente sobre desempenho, agrupamento, recuo e outros detalhes relevantes.', 'gstore' ) . '" required></textarea>';
						$comment_field_markup .= '</div>';

						$comment_form['comment_field'] = $comment_field_markup;

						ob_start();
						comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $comment_form ) );
						$form_markup = ob_get_clean();

						echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
						<p class="Gstore-review-form__disclaimer">
							<?php esc_html_e( 'A CAC Armas se reserva ao direito de moderar comentários que violem a legislação vigente ou incentivem o uso irresponsável de armas e munições.', 'gstore' ); ?>
						</p>
					</div>
				<?php else : ?>
					<p class="woocommerce-verification-required">
						<?php esc_html_e( 'Somente clientes conectados que compraram este produto podem deixar uma avaliação.', 'gstore' ); ?>
					</p>
				<?php endif; ?>
				</div>
			</div>
		</article>
	</div>
</section>


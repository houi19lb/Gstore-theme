<?php
/**
 * Friendly 410 page for removed products.
 *
 * @package Gstore
 */

defined( 'ABSPATH' ) || exit;

$context          = isset( $gstore_gone_product ) && is_array( $gstore_gone_product ) ? $gstore_gone_product : array();
$catalog_url      = ! empty( $context['catalog_url'] ) ? $context['catalog_url'] : home_url( '/catalogo/' );
$support_url      = ! empty( $context['support_url'] ) ? $context['support_url'] : home_url( '/atendimento/' );
$alternatives_url = ! empty( $context['alternatives_url'] ) ? $context['alternatives_url'] : $catalog_url;
$product_label    = ! empty( $context['product_label'] ) ? $context['product_label'] : __( 'Produto consultado', 'gstore' );
$suggestions      = ! empty( $context['suggestions'] ) && is_array( $context['suggestions'] ) ? $context['suggestions'] : array();
$quick_links      = ! empty( $context['quick_links'] ) && is_array( $context['quick_links'] ) ? $context['quick_links'] : array();
$related_products = ! empty( $context['related_products'] ) && is_array( $context['related_products'] ) ? array_filter( array_map( 'absint', $context['related_products'] ) ) : array();

if ( function_exists( 'wc_get_product' ) ) {
	$related_products = array_values(
		array_filter(
			$related_products,
			static function( $product_id ) {
				$product = wc_get_product( $product_id );
				return $product && $product->is_visible();
			}
		)
	);
}

$icons = array(
	'grid'     => 'fa-solid fa-grip',
	'tag'      => 'fa-solid fa-tag',
	'target'   => 'fa-solid fa-crosshairs',
	'category' => 'fa-solid fa-gun',
	'book'     => 'fa-solid fa-book-open',
	'support'  => 'fa-solid fa-headset',
);
?>

<main class="gstore-gone-product" id="conteudo">
	<div class="gstore-gone-product__wrap">
		<section class="gstore-gone-product__hero" aria-labelledby="gstore-gone-title">
			<div class="gstore-gone-product__notice Gstore-card">
				<div class="gstore-gone-product__notice-head">
					<span class="gstore-gone-product__notice-icon" aria-hidden="true">
						<i class="fa-solid fa-box-open"></i>
					</span>
					<div>
						<p class="gstore-gone-product__eyebrow"><?php esc_html_e( 'Sentimos por isso.', 'gstore' ); ?></p>
						<h1 id="gstore-gone-title"><?php esc_html_e( 'Este produto saiu do catálogo', 'gstore' ); ?></h1>
						<p class="gstore-gone-product__lead"><?php esc_html_e( 'Este produto foi desativado, mas separamos caminhos seguros para você continuar sua busca.', 'gstore' ); ?></p>
					</div>
				</div>

				<div class="gstore-gone-product__consulted">
					<span><?php esc_html_e( 'Produto consultado', 'gstore' ); ?></span>
					<strong><?php echo esc_html( $product_label ); ?></strong>
				</div>

				<div class="gstore-gone-product__trust" aria-label="<?php esc_attr_e( 'Diferenciais da Arma Store', 'gstore' ); ?>">
					<div>
						<i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
						<strong><?php esc_html_e( 'Compra segura', 'gstore' ); ?></strong>
						<span><?php esc_html_e( 'Ambiente protegido e dados confidenciais.', 'gstore' ); ?></span>
					</div>
					<div>
						<i class="fa-solid fa-award" aria-hidden="true"></i>
						<strong><?php esc_html_e( 'Especialistas à disposição', 'gstore' ); ?></strong>
						<span><?php esc_html_e( 'Nossa equipe pode ajudar na escolha.', 'gstore' ); ?></span>
					</div>
					<div>
						<i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
						<strong><?php esc_html_e( 'Envio para todo o Brasil', 'gstore' ); ?></strong>
						<span><?php esc_html_e( 'Consulte prazos e condições no checkout.', 'gstore' ); ?></span>
					</div>
				</div>
			</div>

			<aside class="gstore-gone-product__match Gstore-card" aria-labelledby="gstore-gone-match-title">
				<h2 id="gstore-gone-match-title"><?php esc_html_e( 'Podemos encontrar uma opção parecida', 'gstore' ); ?></h2>

				<?php if ( ! empty( $suggestions ) ) : ?>
					<div class="gstore-gone-product__suggestions">
						<?php foreach ( $suggestions as $suggestion ) : ?>
							<?php
							$icon_key   = ! empty( $suggestion['icon'] ) ? (string) $suggestion['icon'] : 'tag';
							$icon_class = ! empty( $icons[ $icon_key ] ) ? $icons[ $icon_key ] : $icons['tag'];
							?>
							<a href="<?php echo esc_url( $suggestion['url'] ); ?>">
								<i class="<?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></i>
								<span><?php echo esc_html( $suggestion['label'] ); ?></span>
								<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="gstore-gone-product__match-actions">
					<a class="Gstore-btn Gstore-btn--primary gstore-gone-product__green-btn" href="<?php echo esc_url( $alternatives_url ); ?>">
						<?php esc_html_e( 'Ver alternativas compatíveis', 'gstore' ); ?>
						<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
					</a>
					<a class="Gstore-btn Gstore-btn--outline" href="<?php echo esc_url( $support_url ); ?>">
						<i class="fa-solid fa-headset" aria-hidden="true"></i>
						<?php esc_html_e( 'Chamar consultor', 'gstore' ); ?>
					</a>
				</div>
			</aside>
		</section>

		<section class="gstore-gone-product__lower">
			<?php if ( ! empty( $quick_links ) ) : ?>
				<div class="gstore-gone-product__quick">
					<h2><?php esc_html_e( 'Caminhos rápidos', 'gstore' ); ?></h2>
					<div class="gstore-gone-product__quick-grid">
						<?php foreach ( $quick_links as $link ) : ?>
							<?php
							$icon_key   = ! empty( $link['icon'] ) ? (string) $link['icon'] : 'grid';
							$icon_class = ! empty( $icons[ $icon_key ] ) ? $icons[ $icon_key ] : $icons['grid'];
							?>
							<a class="gstore-gone-product__quick-card" href="<?php echo esc_url( $link['url'] ); ?>">
								<i class="<?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></i>
								<strong><?php echo esc_html( $link['label'] ); ?></strong>
								<span><?php echo esc_html( $link['description'] ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $related_products ) && function_exists( 'wc_get_template_part' ) && function_exists( 'wc_get_product' ) ) : ?>
				<div class="gstore-gone-product__products">
					<div class="gstore-gone-product__section-head">
						<h2><?php esc_html_e( 'Produtos próximos', 'gstore' ); ?></h2>
						<a href="<?php echo esc_url( $alternatives_url ); ?>"><?php esc_html_e( 'Ver todos', 'gstore' ); ?></a>
					</div>
					<div class="Gstore-products-grid">
						<ul class="products columns-4">
							<?php
							global $post, $product;

							foreach ( $related_products as $product_id ) :
								$post = get_post( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
								if ( ! $post instanceof WP_Post ) {
									continue;
								}
								$product = wc_get_product( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
								if ( ! $product ) {
									continue;
								}
								setup_postdata( $post );
								wc_get_template_part( 'content', 'product' );
							endforeach;
							wp_reset_postdata();
							?>
						</ul>
					</div>
				</div>
			<?php endif; ?>
		</section>
	</div>
</main>

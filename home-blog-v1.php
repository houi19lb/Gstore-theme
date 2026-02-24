<?php
/**
 * Home Blog V1 (Destaque + grid) - Gstore adaptation
 *
 * Shortcode: [gstore_home_blog_v1]
 * Optional attrs:
 * [gstore_home_blog_v1 posts="4" cat="blog" title="Conteudo & atualizacoes" subtitle="..." button_text="Ver todos" button_url="/blog" show_tags="1" show_meta="1"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gstore_home_blog_v1_render' ) ) {
	function gstore_home_blog_v1_render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'posts'       => 4,
				'cat'         => '',
				'title'       => 'Conteudo & atualizacoes',
				'subtitle'    => 'Guias, reviews e checklists direto ao ponto para voce decidir melhor.',
				'button_text' => 'Ver todos',
				'button_url'  => site_url( '/blog' ),
				'show_tags'   => 1,
				'show_meta'   => 1,
			),
			$atts,
			'gstore_home_blog_v1'
		);

		$posts_total = max( 2, (int) $atts['posts'] );
		$posts_total = min( 8, $posts_total );
		$show_tags   = (int) $atts['show_tags'] === 1;
		$show_meta   = (int) $atts['show_meta'] === 1;

		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $posts_total,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		if ( ! empty( $atts['cat'] ) ) {
			$args['category_name'] = sanitize_title( $atts['cat'] );
		}

		$q = new WP_Query( $args );
		if ( ! $q->have_posts() ) {
			return '';
		}

		$posts    = $q->posts;
		$featured = array_shift( $posts );

		$svg_featured = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675"><rect width="100%" height="100%" fill="#efefef"/></svg>' );
		$svg_thumb    = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600"><rect width="100%" height="100%" fill="#efefef"/></svg>' );

		$feat_id      = $featured->ID;
		$feat_title   = get_the_title( $feat_id );
		$feat_url     = get_permalink( $feat_id );
		$feat_date    = get_the_date( 'd M Y', $feat_id );
		$feat_excerpt = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $feat_id ) ), 26, '...' );
		$feat_img     = get_the_post_thumbnail_url( $feat_id, 'large' );
		$feat_img     = $feat_img ? $feat_img : $svg_featured;

		$feat_cat  = '';
		$feat_cats = get_the_category( $feat_id );
		if ( ! empty( $feat_cats ) && ! is_wp_error( $feat_cats ) ) {
			$feat_cat = $feat_cats[0]->name;
		}

		ob_start();
		?>
		<style>
			.gstore-hb-v1 {
				--hb-gap: var(--gstore-spacing-4, 16px);
				--hb-radius: var(--gstore-radius-base, 4px);
				--hb-border: var(--gstore-color-border-light, #e8e8e6);
				--hb-card-bg: var(--gstore-color-bg-light, #fff);
				--hb-card-soft: var(--gstore-color-bg-muted, #f0f2f5);
				--hb-title: var(--gstore-color-text-primary, #1a1a1a);
				--hb-muted: var(--gstore-color-text-muted, #7a7a7a);
				--hb-link: var(--gstore-color-text-primary, #1a1a1a);
				--hb-link-hover: var(--gstore-color-accent, #ff5c00);
				--hb-shadow: var(--gstore-shadow-sm, 0 1px 2px rgba(0, 0, 0, 0.06));
				background: var(--gstore-color-bg-light, #ffffff);
				color: var(--hb-title);
			}
			.gstore-hb-v1,
			.gstore-hb-v1 * {
				box-sizing: border-box;
			}
			.gstore-hb-v1 {
				width: 100%;
			}
			.gstore-hb-v1 .Gstore-container {
				padding-top: clamp(var(--gstore-spacing-6, 24px), 3vw, var(--gstore-spacing-10, 40px));
				padding-bottom: clamp(var(--gstore-spacing-6, 24px), 3vw, var(--gstore-spacing-10, 40px));
				background: var(--gstore-color-bg-light, #ffffff);
			}
			.gstore-hb-v1 .hb-shell {
				border: 1px solid var(--hb-border);
				border-radius: var(--hb-radius);
				background: var(--gstore-color-bg-light, #ffffff);
				box-shadow: var(--hb-shadow);
				padding: clamp(var(--gstore-spacing-4, 16px), 2.5vw, var(--gstore-spacing-6, 24px));
			}
			.gstore-hb-v1 .hb-head {
				display: flex;
				align-items: flex-end;
				justify-content: space-between;
				gap: var(--hb-gap);
				flex-wrap: wrap;
			}
			.gstore-hb-v1 .hb-head > div {
				min-width: 0;
				flex: 1 1 320px;
			}
			.gstore-hb-v1 .hb-title {
				margin: 0;
				color: var(--hb-title);
				font-size: clamp(var(--gstore-font-size-lg, 18px), 2vw, var(--gstore-font-size-2xl, 24px));
				line-height: var(--gstore-line-height-tight, 1.25);
				overflow-wrap: anywhere;
			}
			.gstore-hb-v1 .hb-subtitle {
				margin: var(--gstore-spacing-2, 8px) 0 0;
				font-size: var(--gstore-font-size-sm, 14px);
				line-height: var(--gstore-line-height-relaxed, 1.6);
				color: var(--hb-muted);
				max-width: 72ch;
			}
			.gstore-hb-v1 .hb-grid {
				margin-top: var(--gstore-spacing-5, 20px);
				display: grid;
				grid-template-columns: 1fr;
				gap: var(--hb-gap);
			}
			@media (min-width: 1024px) {
				.gstore-hb-v1 .hb-grid {
					grid-template-columns: 7fr 5fr;
					align-items: start;
				}
			}
			.gstore-hb-v1 .hb-card {
				background: var(--hb-card-bg);
				border: 1px solid var(--hb-border);
				border-radius: var(--hb-radius);
				overflow: hidden;
				min-width: 0;
			}
			.gstore-hb-v1 .hb-card__inner {
				padding: clamp(var(--gstore-spacing-4, 16px), 2vw, var(--gstore-spacing-5, 20px));
			}
			.gstore-hb-v1 .hb-row {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: var(--gstore-spacing-3, 12px);
				flex-wrap: wrap;
			}
			.gstore-hb-v1 .hb-meta {
				display: flex;
				align-items: center;
				flex-wrap: wrap;
				gap: var(--gstore-spacing-2, 8px);
				font-size: var(--gstore-font-size-xs, 12px);
				color: var(--hb-muted);
			}
			.gstore-hb-v1 .hb-dot {
				opacity: .35;
			}
			.gstore-hb-v1 .hb-pill {
				display: inline-flex;
				align-items: center;
				padding: 6px 10px;
				font-size: var(--gstore-font-size-xs, 12px);
				line-height: 1;
				border-radius: var(--hb-radius);
				border: 1px solid var(--hb-border);
				background: var(--hb-card-soft);
				color: var(--hb-title);
				white-space: nowrap;
			}
			.gstore-hb-v1 .hb-title-link,
			.gstore-hb-v1 .hb-thumb-link,
			.gstore-hb-v1 .hb-cover-link {
				text-decoration: none;
				color: var(--hb-link);
			}
			.gstore-hb-v1 .hb-thumb-link,
			.gstore-hb-v1 .hb-cover-link {
				display: block;
				width: 100%;
				height: 100%;
			}
			.gstore-hb-v1 .hb-title-link:hover,
			.gstore-hb-v1 .hb-thumb-link:hover,
			.gstore-hb-v1 .hb-cover-link:hover {
				color: var(--hb-link-hover);
			}
			.gstore-hb-v1 .hb-title-link:hover .hb-h3,
			.gstore-hb-v1 .hb-title-link:hover .hb-h4 {
				text-decoration: underline;
			}
			.gstore-hb-v1 .hb-h3 {
				margin: var(--gstore-spacing-2, 8px) 0 0;
				font-size: clamp(var(--gstore-font-size-xl, 20px), 2vw, var(--gstore-font-size-2xl, 24px));
				line-height: var(--gstore-line-height-tight, 1.25);
				color: var(--hb-title);
				overflow-wrap: anywhere;
			}
			.gstore-hb-v1 .hb-h4 {
				margin: var(--gstore-spacing-2, 8px) 0 0;
				font-size: var(--gstore-font-size-base, 16px);
				line-height: var(--gstore-line-height-tight, 1.25);
				color: var(--hb-title);
				display: -webkit-box;
				-webkit-line-clamp: 2;
				-webkit-box-orient: vertical;
				overflow: hidden;
				overflow-wrap: anywhere;
			}
			.gstore-hb-v1 .hb-excerpt {
				margin: var(--gstore-spacing-3, 12px) 0 0;
				font-size: var(--gstore-font-size-sm, 14px);
				line-height: var(--gstore-line-height-relaxed, 1.6);
				color: var(--hb-muted);
			}
			.gstore-hb-v1 .hb-cover {
				margin-top: var(--gstore-spacing-3, 12px);
				width: 100%;
				aspect-ratio: 16 / 9;
				overflow: hidden;
				border: 1px solid var(--hb-border);
				border-radius: var(--hb-radius);
				background: var(--hb-card-soft);
				position: relative;
			}
			.gstore-hb-v1 .hb-cover img,
			.gstore-hb-v1 .hb-thumb img {
				width: 100%;
				height: 100%;
				object-fit: cover;
				display: block;
				transition: transform .25s ease;
			}
			.gstore-hb-v1 .hb-cover:hover img,
			.gstore-hb-v1 .hb-thumb:hover img {
				transform: scale(1.02);
			}
			.gstore-hb-v1 .hb-cover::after {
				content: "";
				position: absolute;
				inset: 0;
				background: linear-gradient(to top, rgba(255, 255, 255, .02), rgba(255, 255, 255, 0));
				pointer-events: none;
			}
			.gstore-hb-v1 .hb-right {
				display: grid;
				gap: var(--hb-gap);
			}
			.gstore-hb-v1 .hb-mini {
				display: flex;
				gap: var(--gstore-spacing-3, 12px);
			}
			.gstore-hb-v1 .hb-thumb {
				width: 112px;
				flex: 0 0 112px;
				aspect-ratio: 4 / 3;
				overflow: hidden;
				border: 1px solid var(--hb-border);
				border-radius: var(--hb-radius);
				background: var(--hb-card-soft);
			}
			.gstore-hb-v1 .Gstore-btn--secondary {
				color: var(--hb-link);
				border-color: var(--hb-border);
				background: var(--hb-card-bg);
			}
			.gstore-hb-v1 .Gstore-btn--secondary:hover,
			.gstore-hb-v1 .Gstore-btn--secondary:focus {
				color: var(--hb-link-hover);
				border-color: var(--hb-link-hover);
				background: var(--hb-card-soft);
			}
			.gstore-hb-v1 .Gstore-btn--ghost {
				color: var(--hb-link);
			}
			.gstore-hb-v1 .Gstore-btn--ghost:hover,
			.gstore-hb-v1 .Gstore-btn--ghost:focus {
				color: var(--hb-link-hover);
				background: var(--hb-card-soft);
			}
			.gstore-hb-v1 .Gstore-btn--outline {
				color: var(--hb-link);
				border-color: var(--hb-border);
				background: var(--hb-card-bg);
			}
			.gstore-hb-v1 .Gstore-btn--outline:hover,
			.gstore-hb-v1 .Gstore-btn--outline:focus {
				color: var(--gstore-color-text-light, #f5f5f5);
				background: var(--gstore-color-accent-dark, #bf4500);
				border-color: var(--gstore-color-accent-dark, #bf4500);
			}
			.gstore-hb-v1 .hb-mini__body {
				min-width: 0;
				flex: 1;
			}
			.gstore-hb-v1 .hb-mini__excerpt {
				margin-top: var(--gstore-spacing-2, 8px);
				font-size: var(--gstore-font-size-xs, 12px);
				display: -webkit-box;
				-webkit-line-clamp: 2;
				-webkit-box-orient: vertical;
				overflow: hidden;
			}
			.gstore-hb-v1 .hb-actions-inline {
				margin-top: var(--gstore-spacing-2, 8px);
			}
			.gstore-hb-v1 .Gstore-btn {
				width: auto;
				display: inline-flex;
				align-items: center;
				gap: 8px;
				vertical-align: middle;
			}
			.gstore-hb-v1 .hb-head .Gstore-btn--secondary {
				gap: 10px;
			}
			.gstore-hb-v1 .hb-btn-icon {
				width: 14px;
				height: 14px;
				flex: 0 0 14px;
				transition: transform .2s ease;
			}
			.gstore-hb-v1 .hb-head .Gstore-btn--secondary:hover .hb-btn-icon,
			.gstore-hb-v1 .hb-head .Gstore-btn--secondary:focus .hb-btn-icon {
				transform: translateX(2px);
			}
			.gstore-hb-v1 .Gstore-btn span[aria-hidden="true"] {
				display: inline-block;
				line-height: 1;
				transform: translateY(-1px);
			}
			@media (max-width: 1024px) {
				.gstore-hb-v1 .hb-head {
					align-items: flex-start;
				}
				.gstore-hb-v1 .hb-head .Gstore-btn--secondary {
					align-self: flex-start;
				}
				.gstore-hb-v1 .hb-grid > .hb-card:first-child .hb-h3 {
					font-size: clamp(var(--gstore-font-size-lg, 18px), 5vw, var(--gstore-font-size-xl, 20px));
				}
				.gstore-hb-v1 .hb-grid > .hb-card:first-child .hb-excerpt {
					display: none;
				}
				.gstore-hb-v1 .hb-grid > .hb-card:first-child .hb-row[style] {
					margin-top: var(--gstore-spacing-2, 8px) !important;
				}
			}
			@media (max-width: 640px) {
				.gstore-hb-v1 .hb-grid {
					gap: var(--gstore-spacing-3, 12px);
				}
				.gstore-hb-v1 .hb-card__inner {
					padding: var(--gstore-spacing-3, 12px);
				}
				.gstore-hb-v1 .hb-cover,
				.gstore-hb-v1 .hb-thumb {
					aspect-ratio: 16 / 10;
				}
				/* No mobile, iguala o card destaque aos cards secundários */
				.gstore-hb-v1 .hb-grid > .hb-card:first-child .hb-pill,
				.gstore-hb-v1 .hb-grid > .hb-card:first-child .hb-tags {
					display: none !important;
				}
				.gstore-hb-v1 .hb-grid > .hb-card:first-child .hb-h3 {
					font-size: var(--gstore-font-size-base, 16px);
					display: -webkit-box;
					-webkit-line-clamp: 2;
					-webkit-box-orient: vertical;
					overflow: hidden;
				}
				.gstore-hb-v1 .hb-grid > .hb-card:first-child .hb-row {
					justify-content: flex-start;
				}
				.gstore-hb-v1 .hb-grid > .hb-card:first-child .Gstore-btn--outline {
					padding: 6px 10px;
					font-size: var(--gstore-font-size-xs, 12px);
				}
				.gstore-hb-v1 .hb-mini {
					display: block;
				}
				.gstore-hb-v1 .hb-thumb {
					width: 100%;
					flex-basis: auto;
					margin-bottom: var(--gstore-spacing-2, 8px);
				}
				.gstore-hb-v1 .hb-head .Gstore-btn--secondary {
					width: 100%;
					justify-content: center;
				}
			}
		</style>

		<section class="gstore-hb-v1" aria-label="Blog">
			<div class="Gstore-container">
				<div class="hb-shell">
					<div class="hb-head">
						<div>
							<h2 class="hb-title"><?php echo esc_html( $atts['title'] ); ?></h2>
							<?php if ( ! empty( $atts['subtitle'] ) ) : ?>
								<p class="hb-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
							<?php endif; ?>
						</div>
						<a class="Gstore-btn Gstore-btn--secondary" href="<?php echo esc_url( $atts['button_url'] ); ?>">
							<span class="hb-btn-label"><?php echo esc_html( $atts['button_text'] ); ?></span>
							<svg class="hb-btn-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false">
								<path d="M4.167 10h11.666M10.833 4.167 15.833 10l-5 5.833" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
							</svg>
						</a>
					</div>

					<div class="hb-grid">
						<article class="hb-card">
							<div class="hb-card__inner">
								<div class="hb-row">
									<?php if ( $show_meta ) : ?>
										<div class="hb-meta">
											<span><?php echo esc_html( $feat_date ); ?></span>
											<?php if ( ! empty( $feat_cat ) ) : ?>
												<span class="hb-dot">*</span>
												<span><?php echo esc_html( $feat_cat ); ?></span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
									<span class="hb-pill">Destaque</span>
								</div>

								<a class="hb-title-link" href="<?php echo esc_url( $feat_url ); ?>">
									<h3 class="hb-h3"><?php echo esc_html( $feat_title ); ?></h3>
								</a>

								<div class="hb-cover">
									<a class="hb-cover-link" href="<?php echo esc_url( $feat_url ); ?>" aria-label="<?php echo esc_attr( $feat_title ); ?>">
										<img src="<?php echo esc_url( $feat_img ); ?>" alt="<?php echo esc_attr( $feat_title ); ?>" loading="lazy" decoding="async">
									</a>
								</div>

								<?php if ( ! empty( $feat_excerpt ) ) : ?>
									<p class="hb-excerpt"><?php echo esc_html( $feat_excerpt ); ?></p>
								<?php endif; ?>

								<div class="hb-row" style="margin-top: var(--gstore-spacing-3, 12px);">
									<?php if ( $show_tags ) : ?>
										<div class="hb-tags" style="display:flex;gap:8px;flex-wrap:wrap;">
											<?php
											$tags = get_the_tags( $feat_id );
											if ( $tags && ! is_wp_error( $tags ) ) {
												$tags = array_slice( $tags, 0, 3 );
												foreach ( $tags as $tag ) {
													echo '<span class="hb-pill">' . esc_html( $tag->name ) . '</span>';
												}
											}
											?>
										</div>
									<?php else : ?>
										<span></span>
									<?php endif; ?>
									<a class="Gstore-btn Gstore-btn--outline" href="<?php echo esc_url( $feat_url ); ?>">
										<span class="hb-btn-label">Continuar</span>
										<svg class="hb-btn-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false">
											<path d="M4.167 10h11.666M10.833 4.167 15.833 10l-5 5.833" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
										</svg>
									</a>
								</div>
							</div>
						</article>

						<div class="hb-right">
							<?php foreach ( array_slice( $posts, 0, 3 ) as $post_item ) : ?>
								<?php
								$pid     = $post_item->ID;
								$title   = get_the_title( $pid );
								$url     = get_permalink( $pid );
								$date    = get_the_date( 'd M Y', $pid );
								$excerpt = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $pid ) ), 15, '...' );
								$img     = get_the_post_thumbnail_url( $pid, 'medium' );
								$img     = $img ? $img : $svg_thumb;

								$cat_name = '';
								$cats     = get_the_category( $pid );
								if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
									$cat_name = $cats[0]->name;
								}
								?>
								<article class="hb-card">
									<div class="hb-card__inner hb-mini">
										<div class="hb-thumb">
											<a class="hb-thumb-link" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
												<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" decoding="async">
											</a>
										</div>
										<div class="hb-mini__body">
											<?php if ( $show_meta ) : ?>
												<div class="hb-meta">
													<span><?php echo esc_html( $date ); ?></span>
													<?php if ( ! empty( $cat_name ) ) : ?>
														<span class="hb-dot">*</span>
														<span><?php echo esc_html( $cat_name ); ?></span>
													<?php endif; ?>
												</div>
											<?php endif; ?>
											<a class="hb-title-link" href="<?php echo esc_url( $url ); ?>">
												<h4 class="hb-h4"><?php echo esc_html( $title ); ?></h4>
											</a>
											<?php if ( ! empty( $excerpt ) ) : ?>
												<p class="hb-excerpt hb-mini__excerpt"><?php echo esc_html( $excerpt ); ?></p>
											<?php endif; ?>
											<div class="hb-actions-inline">
												<a class="Gstore-btn Gstore-btn--ghost Gstore-btn--sm" href="<?php echo esc_url( $url ); ?>">
													<span class="hb-btn-label">Ler</span>
													<svg class="hb-btn-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false">
														<path d="M4.167 10h11.666M10.833 4.167 15.833 10l-5 5.833" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
													</svg>
												</a>
											</div>
										</div>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</section>
		<?php

		wp_reset_postdata();
		return ob_get_clean();
	}
}

if ( ! shortcode_exists( 'gstore_home_blog_v1' ) ) {
	add_shortcode( 'gstore_home_blog_v1', 'gstore_home_blog_v1_render' );
}

if ( ! function_exists( 'gstore_home_blog_v1' ) ) {
	function gstore_home_blog_v1( $atts = array() ) {
		echo gstore_home_blog_v1_render( $atts );
	}
}

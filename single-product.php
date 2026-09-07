<?php
/**
 * Wplend — карточка товара WooCommerce.
 * wp-content/themes/impreza-child/woocommerce/single-product.php
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

global $product;

if ( ! is_a( $product, 'WC_Product' ) ) {
	$product = wc_get_product( get_the_ID() );
}

if ( ! $product ) {
	get_footer( 'shop' );
	return;
}

$product_id = $product->get_id();

$get_acf = function ( $field, $id ) {
	return function_exists( 'get_field' ) ? get_field( $field, $id ) : get_post_meta( $id, $field, true );
};

$version       = trim( wp_strip_all_tags( (string) $get_acf( 'version', $product_id ) ) );
$demo_url      = esc_url( (string) $get_acf( 'demo', $product_id ) );
$docs_url      = esc_url( (string) $get_acf( 'documentacion', $product_id ) );
$changelog_url = esc_url( (string) $get_acf( 'changelog', $product_id ) );
$purchase_img  = esc_url( (string) $get_acf( 'purchase', $product_id ) );

$brand_terms = get_the_terms( $product_id, 'product_brand' );
$brand_name  = ( $brand_terms && ! is_wp_error( $brand_terms ) ) ? $brand_terms[0]->name : '';
$brand_link  = ( $brand_terms && ! is_wp_error( $brand_terms ) ) ? get_term_link( $brand_terms[0] ) : '';

$last_updated = get_the_modified_date( get_option( 'date_format' ), $product_id );
$main_img_id  = $product->get_image_id();

$vt_url = wplend_get_virustotal_url( $product_id );

$is_logged_in            = is_user_logged_in();
$captcha                 = wplend_generate_captcha();
$membership_download_html = wplend_get_membership_download_html( $product_id );

/* Похожие товары: по тегам текущего товара */
$related_ids = array();
$tag_ids     = wc_get_product_term_ids( $product_id, 'product_tag' );

if ( ! empty( $tag_ids ) ) {
	$related_query = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 6,
			'post__not_in'   => array( $product_id ),
			'orderby'        => 'rand',
			'tax_query'      => array( // phpcs:ignore
				array(
					'taxonomy' => 'product_tag',
					'field'    => 'term_id',
					'terms'    => $tag_ids,
				),
			),
		)
	);
	$related_ids = wp_list_pluck( $related_query->posts, 'ID' );
	wp_reset_postdata();
}

/* Хлебные крошки: Rank Math, с ручным фоллбэком если он ничего не вывел
   (плагин выключен/модуль хлебных крошек не включён в настройках). */
$breadcrumb_html = '';
if ( function_exists( 'rank_math_the_breadcrumbs' ) ) {
	ob_start();
	rank_math_the_breadcrumbs();
	$breadcrumb_html = trim( ob_get_clean() );
}

if ( '' === $breadcrumb_html ) {
	$crumbs = array( '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'wplend' ) . '</a>' );

	$cats = get_the_terms( $product_id, 'product_cat' );
	if ( $cats && ! is_wp_error( $cats ) ) {
		$cat        = $cats[0];
		$crumbs[]   = '<a href="' . esc_url( get_term_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
	}

	$crumbs[]        = '<span>' . esc_html( get_the_title( $product_id ) ) . '</span>';
	$breadcrumb_html = implode( ' <span class="wl-crumb-sep">/</span> ', $crumbs );
}
?>

<main id="main" class="wl-single-product">
	<div class="wl-wrap">

		<div class="wl-breadcrumb"><?php echo wp_kses_post( $breadcrumb_html ); ?></div>

		<div class="wl-title-row">
			<h1 class="wl-title"><?php echo esc_html( get_the_title( $product_id ) ); ?></h1>
			<?php if ( $product->get_short_description() ) : ?>
				<p class="wl-tagline"><?php echo wp_kses_post( $product->get_short_description() ); ?></p>
			<?php endif; ?>
		</div>

		<div class="wl-product-card">

			<!-- ===== Изображение товара / характеристики ===== -->
			<div class="wl-media-col">

				<div class="wl-hero-media">
					<?php
					if ( $main_img_id ) {
						echo wp_get_attachment_image( $main_img_id, 'large', false, array( 'class' => 'wl-hero-img' ) );
					} else {
						echo wc_placeholder_img( 'large' ); // phpcs:ignore
					}
					?>
				</div>

				<div class="wl-trust-row">
					<div class="wl-trust-item">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V8a5 5 0 0 1 10 0v3"/></svg>
						<span><?php esc_html_e( 'Secure payment', 'wplend' ); ?></span>
					</div>
					<div class="wl-trust-item">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5h12M3 12h18M3 19h12"/></svg>
						<span><?php esc_html_e( 'English, Spanish and Mandarin support', 'wplend' ); ?></span>
					</div>
					<div class="wl-trust-item">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg>
						<span><?php esc_html_e( 'With you since 2018', 'wplend' ); ?></span>
					</div>
				</div>

				<div class="wl-meta-grid">
					<?php if ( $version ) : ?>
						<div class="wl-meta-item">
							<div class="wl-label"><?php esc_html_e( 'Version', 'wplend' ); ?></div>
							<div class="wl-value"><?php echo esc_html( $version ); ?></div>
						</div>
					<?php endif; ?>
					<div class="wl-meta-item">
						<div class="wl-label"><?php esc_html_e( 'Updated', 'wplend' ); ?></div>
						<div class="wl-value"><?php echo esc_html( $last_updated ); ?></div>
					</div>
					<?php if ( $changelog_url ) : ?>
						<div class="wl-meta-item">
							<div class="wl-label"><?php esc_html_e( 'Changelog', 'wplend' ); ?></div>
							<div class="wl-value"><a href="<?php echo esc_url( wplend_out_link( $changelog_url ) ); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e( 'View changes', 'wplend' ); ?></a></div>
						</div>
					<?php endif; ?>
					<?php if ( $demo_url ) : ?>
						<div class="wl-meta-item">
							<div class="wl-label"><?php esc_html_e( 'Developer', 'wplend' ); ?></div>
							<div class="wl-value"><a href="<?php echo esc_url( wplend_out_link( $demo_url ) ); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e( 'Visit site', 'wplend' ); ?></a></div>
						</div>
					<?php endif; ?>
					<?php if ( $brand_name ) : ?>
						<div class="wl-meta-item">
							<div class="wl-label"><?php esc_html_e( 'Brand', 'wplend' ); ?></div>
							<div class="wl-value"><?php echo $brand_link ? '<a href="' . esc_url( $brand_link ) . '">' . esc_html( $brand_name ) . '</a>' : esc_html( $brand_name ); ?></div>
						</div>
					<?php endif; ?>
					<?php if ( $docs_url ) : ?>
						<div class="wl-meta-item">
							<div class="wl-label"><?php esc_html_e( 'Documentation', 'wplend' ); ?></div>
							<div class="wl-value"><a href="<?php echo esc_url( wplend_out_link( $docs_url ) ); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e( 'View docs', 'wplend' ); ?></a></div>
						</div>
					<?php endif; ?>
				</div>

				<div class="wl-install-banner">
					<div class="wl-install-left">
						<svg class="wl-install-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
							<path d="M12 3l7 3v5c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6z"/>
							<path d="M9 12.2l2.1 2.1L15.5 10" stroke-width="2"/>
						</svg>
						<div>
							<div class="wl-h"><?php esc_html_e( 'File verified before installation', 'wplend' ); ?></div>
							<div class="wl-p"><?php esc_html_e( 'The package was scanned for malicious code and checked against the original vendor build.', 'wplend' ); ?></div>
						</div>
					</div>
					<?php if ( $vt_url ) : ?>
						<a href="<?php echo esc_url( wplend_out_link( $vt_url ) ); ?>" target="_blank" rel="nofollow noopener noreferrer" class="wl-install-right">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12l5 5L20 6"/></svg>
							<span><?php esc_html_e( 'VirusTotal report', 'wplend' ); ?></span>
						</a>
					<?php endif; ?>
				</div>

			</div>

			<!-- ===== Покупка ===== -->
			<div class="wl-buy-col">
				<div class="wl-buy-box">
					<div class="wl-purchase-pill"><?php esc_html_e( 'Single product access', 'wplend' ); ?></div>

					<div class="wl-price-row">
						<div class="wl-price"><?php echo $product->get_price_html(); // phpcs:ignore ?></div>
						<a href="<?php echo esc_url( home_url( '/membership' ) ); ?>" class="wl-price-note"><?php esc_html_e( 'Membership', 'wplend' ); ?></a>
					</div>

					<div class="wl-add-cart-row">
						<div class="wl-add-cart-wrap">
							<?php if ( $membership_download_html ) : ?>
								<div class="wl-membership-download-wrap"><?php echo $membership_download_html; // phpcs:ignore ?></div>
							<?php else : ?>
								<?php woocommerce_template_single_add_to_cart(); ?>
							<?php endif; ?>
						</div>
						<div class="wl-wishlist-wrap">
							<?php echo do_shortcode( '[yith_wcwl_add_to_wishlist product_id="' . absint( $product_id ) . '"]' ); ?>
						</div>
					</div>

					<p class="wl-member-hint">
						<?php
						printf(
							/* translators: %s: login link */
							esc_html__( 'Already a member? %s to download.', 'wplend' ),
							'<a href="' . esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url() ) . '">' . esc_html__( 'Sign in', 'wplend' ) . '</a>'
						);
						?>
					</p>

					<div class="wl-divider"></div>

					<ul class="wl-feature-list">
						<li>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
							<?php esc_html_e( 'Original package distribution', 'wplend' ); ?>
						</li>
						<li>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
							<?php esc_html_e( 'Support provided by Wplend', 'wplend' ); ?>
						</li>
						<li>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9"/><path d="M3 3v6h6"/></svg>
							<?php esc_html_e( '14-day refund policy', 'wplend' ); ?>
						</li>
					</ul>

					<p class="wl-disclaimer">
						<?php
						if ( $brand_name ) {
							printf(
								/* translators: %s: brand name */
								esc_html__( 'Independent reseller. Not affiliated with %s or the original marketplace.', 'wplend' ),
								esc_html( $brand_name )
							);
						} else {
							esc_html_e( 'Independent reseller. Not affiliated with the original developer or marketplace.', 'wplend' );
						}
						?>
					</p>

					<div class="wl-inline-links">
						<button type="button" class="wl-inline-link" data-wl-modal="wl-included-modal">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
							<?php esc_html_e( "What's included?", 'wplend' ); ?>
						</button>
						<a href="/gpl-license" class="wl-inline-link">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg>
							<?php esc_html_e( 'License details', 'wplend' ); ?>
						</a>
					</div>
				</div>

				<div class="wl-checklist-card">
					<h3><?php esc_html_e( 'Before you purchase', 'wplend' ); ?></h3>

					<button type="button" class="wl-check-box wl-check-box--action" data-wl-update-trigger data-product-id="<?php echo esc_attr( $product_id ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
						<span><?php esc_html_e( 'Found a newer version? Let us know', 'wplend' ); ?></span>
					</button>

					<div class="wl-check-box">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11a9 9 0 0 1 18 0v5a3 3 0 0 1-3 3h-1v-7h4M3 16v-5h4v7H6a3 3 0 0 1-3-3z"/></svg>
						<span><?php esc_html_e( 'Support — Wplend support, not the original author', 'wplend' ); ?></span>
					</div>

					<div class="wl-check-box">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V8a5 5 0 0 1 10 0v3"/></svg>
						<span><?php esc_html_e( 'License keys — see product access terms', 'wplend' ); ?></span>
					</div>

					<p class="wl-checklist-footnote">
						<?php
						printf(
							/* translators: %s: support policy link */
							esc_html__( 'Third-party assets and services may have separate terms. Read %s.', 'wplend' ),
							'<a href="/support-policy">' . esc_html__( 'support policy', 'wplend' ) . '</a>'
						);
						?>
					</p>
				</div>
			</div>
		</div>

		<!-- ===== Описание + сайдбар ===== -->
		<div class="wl-content-row">
			<div class="wl-description-col">
				<div class="wl-description-card">
					<?php the_content(); ?>
				</div>
			</div>

			<div class="wl-sidebar-col">
				<div class="wl-faq-card">
					<div class="wl-faq-kicker">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-8-8 18-2-8z"/></svg>
						<?php esc_html_e( 'Frequently asked questions', 'wplend' ); ?>
					</div>

					<details class="wl-faq-item" open>
						<summary><?php esc_html_e( 'On how many websites can I use the product?', 'wplend' ); ?><span class="wl-plus"></span></summary>
						<div class="wl-body"><?php esc_html_e( 'You can use any product from our store on as many websites as you like.', 'wplend' ); ?></div>
					</details>
					<details class="wl-faq-item">
						<summary><?php esc_html_e( 'Will I receive updates?', 'wplend' ); ?><span class="wl-plus"></span></summary>
						<div class="wl-body"><?php esc_html_e( 'A single purchase includes download access for 72 hours. Future version downloads require repurchase or an active membership.', 'wplend' ); ?></div>
					</details>
					<details class="wl-faq-item">
						<summary><?php esc_html_e( 'Do you offer technical support?', 'wplend' ); ?><span class="wl-plus"></span></summary>
						<div class="wl-body"><?php esc_html_e( 'Yes. We usually reply within 24–72 business hours via live chat or a support ticket.', 'wplend' ); ?></div>
					</details>
					<details class="wl-faq-item">
						<summary><?php esc_html_e( 'Are there any download limits?', 'wplend' ); ?><span class="wl-plus"></span></summary>
						<div class="wl-body"><?php esc_html_e( 'No. We use reliable, high-performance storage for fast and stable downloads.', 'wplend' ); ?></div>
					</details>
					<details class="wl-faq-item">
						<summary><?php esc_html_e( 'Do you provide license keys?', 'wplend' ); ?><span class="wl-plus"></span></summary>
						<div class="wl-body"><?php esc_html_e( 'No, license keys are not required — products are distributed under the GNU GPL license.', 'wplend' ); ?></div>
					</details>
					<details class="wl-faq-item">
						<summary><?php esc_html_e( 'Are your products genuine?', 'wplend' ); ?><span class="wl-plus"></span></summary>
						<div class="wl-body"><?php esc_html_e( 'Yes, 100%. Original code, legally redistributed under the GNU GPL v2/v3 license.', 'wplend' ); ?></div>
					</details>
					<details class="wl-faq-item">
						<summary><?php esc_html_e( 'Do you offer a warranty?', 'wplend' ); ?><span class="wl-plus"></span></summary>
						<div class="wl-body"><?php esc_html_e( 'Yes. If there is an unresolvable technical problem, we will help and, if needed, issue a refund.', 'wplend' ); ?></div>
					</details>
				</div>

				<?php if ( ! empty( $related_ids ) ) : ?>
					<div class="wl-related-list">
						<?php
						foreach ( $related_ids as $rid ) :
							$rp = wc_get_product( $rid );
							if ( ! $rp ) {
								continue;
							}
							?>
							<a href="<?php echo esc_url( get_permalink( $rid ) ); ?>" class="wl-related-item">
								<div class="wl-related-thumb"><?php echo $rp->get_image( 'thumbnail' ); // phpcs:ignore ?></div>
								<div class="wl-related-body">
									<div class="wl-related-name"><?php echo esc_html( $rp->get_name() ); ?></div>
									<div class="wl-related-price"><?php echo $rp->get_price_html(); // phpcs:ignore ?></div>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

	</div>
</main>

<!-- Модалка: Подтверждение покупки -->
<?php if ( $purchase_img ) : ?>
<div class="wl-modal" id="wl-purchase-modal" hidden>
	<div class="wl-modal-backdrop" data-wl-modal-close></div>
	<div class="wl-modal-box">
		<button type="button" class="wl-modal-close" data-wl-modal-close aria-label="<?php esc_attr_e( 'Close', 'wplend' ); ?>">&times;</button>
		<img src="<?php echo esc_url( $purchase_img ); ?>" alt="<?php esc_attr_e( 'Purchase confirmation', 'wplend' ); ?>">
	</div>
</div>
<?php endif; ?>

<!-- Модалка: Что входит в комплект -->
<div class="wl-modal" id="wl-included-modal" hidden>
	<div class="wl-modal-backdrop" data-wl-modal-close></div>
	<div class="wl-modal-box wl-modal-box--text">
		<button type="button" class="wl-modal-close" data-wl-modal-close aria-label="<?php esc_attr_e( 'Close', 'wplend' ); ?>">&times;</button>
		<h3><?php esc_html_e( "What's included", 'wplend' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'The original product package files (GPL license)', 'wplend' ); ?></li>
			<li><?php esc_html_e( 'Access to Wplend support for installation and usage questions', 'wplend' ); ?></li>
			<li><?php esc_html_e( 'Free updates for 72 hours after purchase', 'wplend' ); ?></li>
		</ul>
	</div>
</div>

<!-- Модалка: Запрос обновления версии -->
<div class="wl-modal" id="wl-update-modal" hidden>
	<div class="wl-modal-backdrop" data-wl-modal-close></div>
	<div class="wl-modal-box wl-modal-box--form">
		<button type="button" class="wl-modal-close" data-wl-modal-close aria-label="<?php esc_attr_e( 'Close', 'wplend' ); ?>">&times;</button>

		<form id="wl-update-form" data-product-id="<?php echo esc_attr( $product_id ); ?>">

			<label class="wl-icon-input">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.6"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
				<input type="text" name="username" placeholder="<?php echo esc_attr__( 'Username', 'wplend' ) . ' *'; ?>" required>
			</label>

			<label class="wl-icon-input">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 6.5l9 6 9-6"/></svg>
				<input type="email" name="email" placeholder="<?php echo esc_attr__( 'Your Email', 'wplend' ) . ' *'; ?>" required>
			</label>

			<label class="wl-icon-input">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 6l-5 6 5 6M16 6l5 6-5 6"/></svg>
				<input type="text" name="version" placeholder="<?php echo esc_attr__( 'New version number', 'wplend' ) . ' *'; ?>" required>
			</label>

			<div class="wl-captcha-row">
				<span><?php echo esc_html( $captcha['a'] ); ?> + <?php echo esc_html( $captcha['b'] ); ?> = ?</span>
				<input type="hidden" name="captcha_a" value="<?php echo esc_attr( $captcha['a'] ); ?>">
				<input type="hidden" name="captcha_b" value="<?php echo esc_attr( $captcha['b'] ); ?>">
				<input type="hidden" name="captcha_token" value="<?php echo esc_attr( $captcha['token'] ); ?>">
			</div>

			<label class="wl-icon-input">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="15" r="3.2"/><path d="M10.3 12.7L18 5M15.5 7.5L18 10M18 5l2.5 2.5"/></svg>
				<input type="text" name="captcha_answer" required inputmode="numeric">
			</label>

			<button type="submit"><?php esc_html_e( 'Send Request', 'wplend' ); ?></button>
			<p class="wl-form-message" data-wl-form-message hidden></p>
		</form>
	</div>
</div>

<?php get_footer( 'shop' ); ?>

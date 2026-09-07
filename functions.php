<?php
/**
 * Wplend: функции темы и интеграции WooCommerce.
 * Сайт: https://wplend.net/
 *
 * ВАЖНО: этот файл целиком заменяет предыдущий functions.php.
 *
 * ЭТАП 3 — ИЗМЕНЕНИЯ:
 *  - Полный ребрендинг: единый префикс "wplend_" для всех функций, текстовый домен 'wplend'.
 *  - Свой wishlist-механизм убран целиком — используется шорткод
 *    [yith_wcwl_add_to_wishlist] из YITH WooCommerce Wishlist.
 *  - Добавлена интеграция с YITH WooCommerce Membership Premium:
 *    если у пользователя активное членство — вместо "Add to Cart"
 *    показывается [membership_download_product_links].
 *  - VirusTotal SHA-256 теперь читается из ACF-поля 'virustotal' (с
 *    фоллбэком на старое мета-поле 'virustotal_sha256' для уже
 *    заполненных товаров — миграция не нужна). Старая панель в
 *    "Данные товара" убрана, редактируется через ACF.
 *  - Раздел "Download previous versions" ПОЛНОСТЬЮ УБРАН из кода —
 *    для автоматической подгрузки версий с Amazon S3 нужны: имя бакета,
 *    региона, способ хранения ключей доступа и правило, как файл в S3
 *    привязывается к конкретному товару (по слагу? по метаданным
 *    объекта? единый префикс на товар?). Как только это будет — соберём
 *    отдельный модуль с кэшированием (запросы к S3 на каждой загрузке
 *    страницы недопустимы по скорости).
 *  - Добавлена обёртка внешних ссылок через редирект + rel="nofollow
 *    noopener" (см. wplend_out_link()) и правило в robots.txt.
 */

/* ==========================================================================
   Подключение стилей и скриптов страницы товара
   ========================================================================== */

add_action( 'wp_enqueue_scripts', 'wplend_enqueue_product_page_assets' );
function wplend_enqueue_product_page_assets() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$theme_version = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'wplend-product-card',
		get_stylesheet_directory_uri() . '/assets/css/product-card.css',
		array(),
		$theme_version
	);

	wp_enqueue_script(
		'wplend-product-card',
		get_stylesheet_directory_uri() . '/assets/js/product-card.js',
		array(),
		$theme_version,
		true
	);

	wp_localize_script(
		'wplend-product-card',
		'wplendProductCard',
		array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'updateNonce' => wp_create_nonce( 'wplend_request_update' ),
			'isLoggedIn'  => is_user_logged_in(),
			'loginUrl'    => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url(),
			'i18n'        => array(
				'signInUpdate' => __( 'Please sign in to request a version update.', 'wplend' ),
				'genericError' => __( 'Something went wrong. Please try again.', 'wplend' ),
				'requestSent'  => __( 'Thank you! Your request has been sent. Our support team will get back to you shortly.', 'wplend' ),
				'captchaWrong' => __( 'The answer to the math question is incorrect. Please try again.', 'wplend' ),
			),
		)
	);
}

/* ==========================================================================
   Медиа / загрузка файлов
   ========================================================================== */

add_filter( 'upload_mimes', 'wplend_extra_mime_types' );
function wplend_extra_mime_types( $mimes ) {
	if ( current_user_can( 'manage_options' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}

	return $mimes;
}

/* ==========================================================================
   WooCommerce: оформление заказа, корзина, тексты
   ========================================================================== */

add_filter( 'woocommerce_checkout_fields', 'wplend_checkout_fields' );
function wplend_checkout_fields( $fields ) {
	if ( empty( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
		return $fields;
	}

	$remove_fields = array(
		'billing_company',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_phone',
		'billing_postcode',
	);

	foreach ( $remove_fields as $field ) {
		unset( $fields['billing'][ $field ] );
	}

	return $fields;
}

// НЕИСПОЛЬЗУЕМАЯ функция (нигде в этом файле не вызывается) — проверьте,
// не используется ли в другом месте, прежде чем удалять.
function only_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}

add_filter( 'woocommerce_min_password_strength', 'wplend_min_password_strength' );
function wplend_min_password_strength() {
	return 2;
}

/**
 * Текст кнопки в архивах/корзине — без цены.
 */
add_filter( 'woocommerce_product_add_to_cart_text', 'wplend_loop_cart_button_text' );
function wplend_loop_cart_button_text() {
	return __( 'Add To Cart', 'wplend' );
}

/**
 * Текст кнопки на странице товара — с ценой ("Add To Cart — $5.99"),
 * как в референсном макете.
 */
add_filter( 'woocommerce_product_single_add_to_cart_text', 'wplend_single_cart_button_text' );
function wplend_single_cart_button_text( $text ) {
	global $product;

	if ( is_a( $product, 'WC_Product' ) ) {
		$price = wp_strip_all_tags( wc_price( $product->get_price() ) );
		return sprintf( '%s — %s', __( 'Add To Cart', 'wplend' ), $price );
	}

	return $text;
}

add_filter( 'gettext', 'wplend_translate_text', 10, 3 );
function wplend_translate_text( $translated, $text, $domain ) {
	if ( 'All' === $text && 'woocommerce' === $domain ) {
		return __( 'All Categories', 'wplend' );
	}

	return $translated;
}

add_action( 'wp_footer', 'wplend_auto_update_cart' );
function wplend_auto_update_cart() {
	if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
		return;
	}

	echo '<style>.woocommerce button[name="update_cart"],.woocommerce input[name="update_cart"]{display:none}</style>';
	echo '<script>jQuery(function($){var timer;$(".woocommerce").on("change","input.qty",function(){clearTimeout(timer);timer=setTimeout(function(){$("[name=update_cart]").trigger("click");},300);});});</script>';
}

add_filter( 'woocommerce_get_order_item_totals', 'wplend_hide_order_cart_subtotal' );
function wplend_hide_order_cart_subtotal( $totals ) {
	unset( $totals['cart_subtotal'] );
	return $totals;
}

add_action( 'wp_loaded', 'wplend_remove_order_again_button' );
function wplend_remove_order_again_button() {
	remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
}

/* ==========================================================================
   Внешние ссылки: редирект + nofollow noopener (SEO — вес страницы не
   передаётся напрямую сторонним сайтам, сам домен реже "светится" в
   исходном коде страницы поисковым роботам).
   ========================================================================== */

function wplend_out_link( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	return add_query_arg( 'wplend_out', rawurlencode( $url ), home_url( '/' ) );
}

add_action( 'template_redirect', 'wplend_handle_outbound_redirect' );
function wplend_handle_outbound_redirect() {
	if ( ! isset( $_GET['wplend_out'] ) ) { // phpcs:ignore
		return;
	}

	$url = esc_url_raw( wp_unslash( $_GET['wplend_out'] ) ); // phpcs:ignore

	if ( $url && wp_http_validate_url( $url ) ) {
		wp_redirect( $url, 302 ); // phpcs:ignore
		exit;
	}

	wp_safe_redirect( home_url( '/' ) );
	exit;
}

// Если сайт использует ВИРТУАЛЬНЫЙ robots.txt (стандартно для WP, если
// нет физического файла robots.txt в корне) — добавляем правило.
// Если у вас загружен свой физический robots.txt на сервер, этот фильтр
// не сработает — добавьте строку `Disallow: /*wplend_out=*` туда вручную.
add_filter( 'robots_txt', 'wplend_robots_txt_disallow_out_link', 10, 2 );
function wplend_robots_txt_disallow_out_link( $output, $public ) {
	if ( $public ) {
		$output .= "Disallow: /*wplend_out=*\n";
	}
	return $output;
}

/* ==========================================================================
   SEO: OpenGraph, шорткоды, Schema.org
   ========================================================================== */

add_filter( 'language_attributes', 'wplend_opengraph_prefix', 20 );
function wplend_opengraph_prefix( $attributes ) {
	$prefix = 'prefix="og: https://ogp.me/ns# article: https://ogp.me/ns/article# profile: https://ogp.me/ns/profile# fb: https://ogp.me/ns/fb#"';

	if ( preg_match( '/prefix=".*?"/i', $attributes ) ) {
		return preg_replace( '/prefix=".*?"/i', $prefix, $attributes );
	}

	return trim( $attributes . ' ' . $prefix );
}

add_shortcode( 'brand_description', 'wplend_brand_description_shortcode' );
function wplend_brand_description_shortcode() {
	return term_description();
}

function wplend_schema_has_type( $entity, $type ) {
	return is_array( $entity )
		&& ! empty( $entity['@type'] )
		&& in_array( $type, (array) $entity['@type'], true );
}

function wplend_schema_org_id() {
	return trailingslashit( home_url( '/' ) ) . '#organization';
}

function wplend_schema_logo() {
	$id = (int) get_theme_mod( 'custom_logo' );
	if ( ! $id ) {
		return array();
	}

	$url = wp_get_attachment_image_url( $id, 'full' );
	if ( ! $url ) {
		return array();
	}

	$meta = wp_get_attachment_metadata( $id );
	$logo = array(
		'@type'      => 'ImageObject',
		'@id'        => trailingslashit( home_url( '/' ) ) . '#organization-logo',
		'url'        => esc_url_raw( $url ),
		'contentUrl' => esc_url_raw( $url ),
		'caption'    => get_bloginfo( 'name' ),
	);

	if ( is_array( $meta ) && ! empty( $meta['width'] ) ) {
		$logo['width'] = (int) $meta['width'];
	}
	if ( is_array( $meta ) && ! empty( $meta['height'] ) ) {
		$logo['height'] = (int) $meta['height'];
	}

	return $logo;
}

function wplend_schema_organization() {
	$name = get_bloginfo( 'name' );
	$org  = array(
		'@type' => 'Organization',
		'@id'   => wplend_schema_org_id(),
		'name'  => $name ? $name : 'Wplend',
		'url'   => trailingslashit( home_url( '/' ) ),
	);

	$logo = wplend_schema_logo();
	if ( $logo ) {
		$org['logo']  = $logo;
		$org['image'] = array( '@id' => $logo['@id'] );
	}

	return $org;
}

function wplend_schema_brand_name( $product ) {
	if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
		return '';
	}

	$name = sanitize_text_field(
		(string) get_post_meta( $product->get_id(), 'schema_brand', true )
	);

	if ( '' !== $name ) {
		return $name;
	}

	$taxonomies = array( 'product_brand', 'pwb-brand', 'yith_product_brand' );
	foreach ( $taxonomies as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$names = wp_get_post_terms(
			$product->get_id(),
			$taxonomy,
			array( 'fields' => 'names' )
		);

		if ( ! is_wp_error( $names ) && ! empty( $names[0] ) ) {
			return sanitize_text_field( $names[0] );
		}
	}

	return '';
}

function wplend_schema_sku( $product ) {
	if ( ! is_object( $product ) || ! method_exists( $product, 'get_sku' ) ) {
		return '';
	}

	$sku = trim( (string) $product->get_sku() );
	return '' !== $sku ? $sku : 'WPLEND-' . (int) $product->get_id();
}

function wplend_clean_offer( $offer ) {
	if ( ! is_array( $offer ) ) {
		return $offer;
	}

	unset(
		$offer['shippingDetails'],
		$offer['hasMerchantReturnPolicy'],
		$offer['deliveryLeadTime'],
		$offer['availableDeliveryMethod']
	);

	$offer['seller'] = array( '@id' => wplend_schema_org_id() );
	return $offer;
}

function wplend_clean_offers( $offers ) {
	if ( ! is_array( $offers ) ) {
		return $offers;
	}

	if ( isset( $offers[0] ) ) {
		foreach ( $offers as $key => $offer ) {
			$offers[ $key ] = wplend_clean_offer( $offer );
		}
		return $offers;
	}

	return wplend_clean_offer( $offers );
}

add_filter( 'rank_math/snippet/rich_snippet_product_entity', 'wplend_product_schema', 99 );
function wplend_product_schema( $entity ) {
	if ( ! is_array( $entity ) || ! is_singular( 'product' ) || ! function_exists( 'wc_get_product' ) ) {
		return $entity;
	}

	$product = wc_get_product( get_queried_object_id() );
	if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) ) {
		return $entity;
	}

	$url           = get_permalink( $product->get_id() );
	$entity['@id'] = $url . '#product';
	$entity['url'] = $url;
	$entity['sku'] = wplend_schema_sku( $product );

	$brand = wplend_schema_brand_name( $product );
	if ( '' !== $brand ) {
		$entity['brand'] = array(
			'@type' => 'Brand',
			'name'  => $brand,
		);
	} else {
		unset( $entity['brand'] );
	}

	if ( $product->is_virtual() || $product->is_downloadable() ) {
		$entity['additionalProperty'] = array(
			array(
				'@type' => 'PropertyValue',
				'name'  => 'Product type',
				'value' => 'Digital download',
			),
		);

		if ( isset( $entity['offers'] ) ) {
			$entity['offers'] = wplend_clean_offers( $entity['offers'] );
		}
	}

	return $entity;
}

add_filter( 'rank_math/json_ld', 'wplend_json_ld', 999, 2 );
function wplend_json_ld( $data, $jsonld ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$org   = wplend_schema_organization();
	$found = false;

	foreach ( $data as $key => $entity ) {
		if ( ! is_array( $entity ) ) {
			continue;
		}

		if ( wplend_schema_has_type( $entity, 'Organization' ) ) {
			$data[ $key ] = array_replace( $entity, $org );
			$found        = true;
		}

		if ( ( is_page() || is_front_page() ) && ! wplend_schema_has_type( $entity, 'Product' ) ) {
			unset( $data[ $key ]['datePublished'], $data[ $key ]['dateModified'] );
		}
	}

	if ( is_singular( 'product' ) && ! $found ) {
		$data['wplend-organization'] = $org;
	}

	if ( is_singular( 'product' ) && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( get_queried_object_id() );

		if ( is_object( $product ) && ( $product->is_virtual() || $product->is_downloadable() ) ) {
			foreach ( $data as $key => $entity ) {
				if ( wplend_schema_has_type( $entity, 'Product' ) && ! empty( $entity['offers'] ) ) {
					$data[ $key ]['offers'] = wplend_clean_offers( $entity['offers'] );
				}
			}
		}
	}

	return $data;
}

add_filter( 'wp_get_attachment_image_attributes', 'wplend_product_image_title', 20, 2 );
function wplend_product_image_title( $attr, $attachment ) {
	$attachment_id = is_object( $attachment ) && isset( $attachment->ID )
		? (int) $attachment->ID
		: (int) $attachment;
	$parent_id = (int) wp_get_post_parent_id( $attachment_id );

	if ( $parent_id && 'product' === get_post_type( $parent_id ) ) {
		$attr['title'] = get_the_title( $parent_id );
	}

	return $attr;
}

/* ==========================================================================
   VirusTotal — теперь через ACF-поле 'virustotal' (Text, только SHA-256,
   без URL). Фоллбэк на старое мета-поле 'virustotal_sha256' для товаров,
   заполненных до перехода на ACF — миграция значений не требуется.
   ========================================================================== */

function wplend_get_virustotal_sha256( $product_id ) {
	$value = function_exists( 'get_field' ) ? get_field( 'virustotal', $product_id ) : '';

	if ( ! $value ) {
		$value = get_post_meta( $product_id, 'virustotal_sha256', true ); // legacy fallback
	}

	return strtolower( trim( (string) $value ) );
}

function wplend_get_virustotal_url( $product_id ) {
	$sha256 = wplend_get_virustotal_sha256( $product_id );

	if ( 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $sha256 ) ) {
		return '';
	}

	return 'https://www.virustotal.com/gui/file/' . rawurlencode( $sha256 );
}

/**
 * Шорткод [virustotal_report] — для использования в произвольном контенте
 * (например, в статьях блога). На странице товара шаблон вызывает
 * wplend_get_virustotal_url() напрямую.
 */
add_shortcode( 'virustotal_report', 'wplend_virustotal_report_shortcode' );
function wplend_virustotal_report_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'product_id' => 0,
			'title'      => 'Archive checked',
			'link_text'  => 'VirusTotal Report',
		),
		$atts,
		'virustotal_report'
	);

	$product_id = absint( $atts['product_id'] );

	if ( ! $product_id && is_singular( 'product' ) ) {
		$product_id = get_queried_object_id();
	}

	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
		return '';
	}

	$url = wplend_get_virustotal_url( $product_id );
	if ( '' === $url ) {
		return '';
	}

	$title     = sanitize_text_field( $atts['title'] );
	$link_text = sanitize_text_field( $atts['link_text'] );

	$html  = '<div class="wplend-vt-report"><div class="wplend-vt-content">';
	if ( '' !== $title ) {
		$html .= '<span class="wplend-vt-title">' . esc_html( $title ) . '</span>';
	}
	$html .= '<a class="wplend-vt-link" href="' . esc_url( wplend_out_link( $url ) ) . '" target="_blank" rel="noopener noreferrer nofollow external">';
	$html .= esc_html( $link_text );
	$html .= '</a></div></div>';

	return $html;
}

/* ==========================================================================
   Математическая капча (форма запроса обновления)
   ========================================================================== */

function wplend_generate_captcha() {
	$a     = wp_rand( 1, 30 );
	$b     = wp_rand( 1, 30 );
	$token = hash_hmac( 'sha256', $a . '|' . $b, wp_salt( 'nonce' ) );

	return array(
		'a'     => $a,
		'b'     => $b,
		'token' => $token,
	);
}

function wplend_verify_captcha( $a, $b, $token, $answer ) {
	$a = absint( $a );
	$b = absint( $b );

	$expected_token = hash_hmac( 'sha256', $a . '|' . $b, wp_salt( 'nonce' ) );

	if ( ! hash_equals( $expected_token, (string) $token ) ) {
		return false;
	}

	return ( $a + $b ) === absint( $answer );
}

/* ==========================================================================
   Форма "Найдена новая версия?" — AJAX, только для авторизованных,
   письмо на support@, с математической капчей.
   ========================================================================== */

function wplend_get_support_email() {
	$host  = wp_parse_url( home_url(), PHP_URL_HOST );
	$email = 'support@' . preg_replace( '/^www\./i', '', (string) $host );

	return apply_filters( 'wplend_support_email', $email );
}

add_action( 'wp_ajax_wplend_request_update', 'wplend_ajax_request_update' );
add_action( 'wp_ajax_nopriv_wplend_request_update', 'wplend_ajax_request_update_guest' );

function wplend_ajax_request_update_guest() {
	wp_send_json_error( array( 'code' => 'not_logged_in' ) );
}

function wplend_ajax_request_update() {
	check_ajax_referer( 'wplend_request_update', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'code' => 'not_logged_in' ) );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$username   = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
	$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$version    = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';

	$captcha_a      = isset( $_POST['captcha_a'] ) ? $_POST['captcha_a'] : '';
	$captcha_b      = isset( $_POST['captcha_b'] ) ? $_POST['captcha_b'] : '';
	$captcha_token  = isset( $_POST['captcha_token'] ) ? $_POST['captcha_token'] : '';
	$captcha_answer = isset( $_POST['captcha_answer'] ) ? $_POST['captcha_answer'] : '';

	if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
		wp_send_json_error( array( 'code' => 'invalid_product' ) );
	}

	if ( empty( $username ) || ! is_email( $email ) || empty( $version ) ) {
		wp_send_json_error( array( 'code' => 'invalid_fields' ) );
	}

	if ( ! wplend_verify_captcha( $captcha_a, $captcha_b, $captcha_token, $captcha_answer ) ) {
		wp_send_json_error( array( 'code' => 'captcha' ) );
	}

	$product_title = get_the_title( $product_id );
	$product_link  = get_permalink( $product_id );
	$current_user  = wp_get_current_user();

	$subject = sprintf( '[Update request] %s', $product_title );
	$body    = "A logged-in user submitted a product update request.\n\n"
		. 'Product: ' . $product_title . ' (' . $product_link . ")\n"
		. 'Requested version: ' . $version . "\n\n"
		. 'Name: ' . $username . "\n"
		. 'Email: ' . $email . "\n"
		. 'WordPress account: ' . $current_user->user_login . ' (#' . $current_user->ID . ")\n";

	$sent = wp_mail( wplend_get_support_email(), $subject, $body );

	if ( ! $sent ) {
		wp_send_json_error( array( 'code' => 'mail_failed' ) );
	}

	wp_send_json_success();
}

/* ==========================================================================
   YITH WooCommerce Membership Premium — интеграция.
   Проверено по коду плагина: yith_wcmbs_user_has_membership( $user_id ).
   ========================================================================== */

function wplend_user_has_active_membership( $user_id = 0 ) {
	if ( ! function_exists( 'yith_wcmbs_user_has_membership' ) ) {
		return false;
	}
	$user_id = $user_id ? $user_id : get_current_user_id();
	return (bool) yith_wcmbs_user_has_membership( $user_id );
}

/**
 * HTML кнопки "Download Archive" для товара, если у пользователя активное
 * членство и есть доступные ссылки на скачивание. Возвращает '' если
 * доступа нет (тогда шаблон показывает обычный "Add to Cart").
 */
function wplend_get_membership_download_html( $product_id ) {
	if ( ! is_user_logged_in() || ! wplend_user_has_active_membership() ) {
		return '';
	}

	if ( ! shortcode_exists( 'membership_download_product_links' ) ) {
		return '';
	}

	$label = esc_html__( 'Download Archive', 'wplend' );
	$html  = do_shortcode( '[membership_download_product_links id="' . absint( $product_id ) . '" class="wplend-membership-download"]' . $label . '[/membership_download_product_links]' );

	return trim( (string) $html );
}

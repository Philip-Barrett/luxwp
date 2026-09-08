<?php
/**
 * WPLend: функции темы и интеграции WooCommerce.
 *
 * ВАЖНО: этот файл целиком заменяет предыдущий functions.php.
 * Не подключайте одновременно старую копию кода через Code Snippets/WPCode.
 */

// SVG разрешён только администраторам. Перед публикацией очищайте SVG.
add_filter( 'upload_mimes', 'wplend_extra_mime_types' );
function wplend_extra_mime_types( $mimes ) {
	if ( current_user_can( 'manage_options' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}

	return $mimes;
}

// Убираем ненужные поля оформления заказа для цифровых товаров.
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

add_filter( 'woocommerce_product_single_add_to_cart_text', 'wplend_cart_button_text' );
add_filter( 'woocommerce_product_add_to_cart_text', 'wplend_cart_button_text' );
function wplend_cart_button_text() {
	return __( 'Add To Cart', 'woocommerce' );
}

add_filter( 'gettext', 'wplend_translate_text', 10, 3 );
function wplend_translate_text( $translated, $text, $domain ) {
	if ( 'All' === $text && 'woocommerce' === $domain ) {
		return 'All Categories';
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

/**
 * Schema.org / Rank Math.
 */
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
		'name'  => $name ? $name : 'WPLend.com',
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

	$term = wplend_get_product_brand_term( $product->get_id() );
	if ( $term && ! is_wp_error( $term ) ) {
		return sanitize_text_field( $term->name );
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

/**
 * ============================================================
 * Безопасность админки: скрытый URL входа + защита от подбора.
 * ============================================================
 * Вход в админку доступен только по адресу /pentagon/.
 * Прямые запросы к wp-login.php и wp-admin (без сессии) блокируются.
 *
 * ВАЖНО: если на сайте уже используется отдельный security-плагин
 * (например, WPS Hide Login или Limit Login Attempts Reloaded),
 * этот блок нужно отключить, чтобы не было конфликта.
 */
define( 'WPLEND_LOGIN_SLUG', 'pentagon' );
define( 'WPLEND_LOGIN_MAX_ATTEMPTS', 5 );
define( 'WPLEND_LOGIN_LOCKOUT_MINUTES', 15 );

add_action( 'plugins_loaded', 'wplend_custom_login_gate', 1 );
function wplend_custom_login_gate() {
	if ( is_admin() && wp_doing_ajax() ) {
		return; // Не мешаем admin-ajax.php.
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	$request_path = wp_parse_url( add_query_arg( array() ), PHP_URL_PATH );
	$request_path = is_string( $request_path ) ? untrailingslashit( $request_path ) : '';
	$login_path   = '/' . WPLEND_LOGIN_SLUG;

	$is_login_php = ( false !== strpos( $_SERVER['SCRIPT_NAME'], 'wp-login.php' ) );
	$is_wp_admin  = ( false !== strpos( $request_path, '/wp-admin' ) );

	// /pentagon/ -> отдаём стандартный wp-login.php "под капотом".
	if ( $request_path === $login_path ) {
		global $pagenow;
		$pagenow = 'wp-login.php';
		require ABSPATH . 'wp-login.php';
		exit;
	}

	// Прямой запрос к wp-login.php скрываем.
	if ( $is_login_php && ! isset( $_GET['wplend_internal'] ) ) {
		wp_safe_redirect( home_url( '/' ), 404 );
		exit;
	}

	// wp-admin без активной сессии (кроме logout со специальным nonce) скрываем.
	if ( $is_wp_admin && ! is_user_logged_in() && ! wp_doing_cron() ) {
		wp_safe_redirect( home_url( '/' ), 404 );
		exit;
	}
}

add_filter( 'site_url', 'wplend_rewrite_login_url', 10, 4 );
add_filter( 'network_site_url', 'wplend_rewrite_login_url', 10, 3 );
add_filter( 'wp_redirect', 'wplend_rewrite_login_url', 10, 2 );
function wplend_rewrite_login_url( $url ) {
	if ( false !== strpos( $url, 'wp-login.php' ) && ! is_admin() ) {
		$url = str_replace( 'wp-login.php', WPLEND_LOGIN_SLUG . '/', $url );
	}
	return $url;
}

function wplend_login_attempts_key( $ip ) {
	return 'wplend_login_attempts_' . md5( $ip );
}

function wplend_get_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

add_filter( 'authenticate', 'wplend_check_login_lockout', 1 );
function wplend_check_login_lockout( $user ) {
	$ip = wplend_get_client_ip();
	if ( ! $ip ) {
		return $user;
	}

	$attempts = (int) get_transient( wplend_login_attempts_key( $ip ) );

	if ( $attempts >= WPLEND_LOGIN_MAX_ATTEMPTS ) {
		return new WP_Error(
			'wplend_locked_out',
			sprintf(
				__( 'Too many failed login attempts. Try again in %d minutes.', 'wplend' ),
				WPLEND_LOGIN_LOCKOUT_MINUTES
			)
		);
	}

	return $user;
}

add_action( 'wp_login_failed', 'wplend_register_failed_login' );
function wplend_register_failed_login() {
	$ip = wplend_get_client_ip();
	if ( ! $ip ) {
		return;
	}

	$key      = wplend_login_attempts_key( $ip );
	$attempts = (int) get_transient( $key );
	$attempts++;

	set_transient( $key, $attempts, WPLEND_LOGIN_LOCKOUT_MINUTES * MINUTE_IN_SECONDS );
}

add_action( 'wp_login', 'wplend_clear_failed_login', 10, 2 );
function wplend_clear_failed_login( $user_login, $user ) {
	$ip = wplend_get_client_ip();
	if ( $ip ) {
		delete_transient( wplend_login_attempts_key( $ip ) );
	}
}

/**
 * ============================================================
 * Верхняя панель админки видна только администратору.
 * ============================================================
 */
add_action( 'after_setup_theme', 'wplend_hide_admin_bar_for_non_admins' );
function wplend_hide_admin_bar_for_non_admins() {
	if ( ! current_user_can( 'manage_options' ) ) {
		show_admin_bar( false );
	}
}

/**
 * ============================================================
 * VirusTotal: хранится только в блоке "Произвольные поля".
 * ============================================================
 * Новый (основной) ключ произвольного поля: virustotal — сюда можно
 * положить либо готовую ссылку на отчёт, либо просто SHA-256 файла
 * (ссылка соберётся автоматически).
 *
 * Для уже существующих товаров, где значения ещё лежат в старых полях
 * virustotal_url / virustotal_sha256, они тоже продолжают работать —
 * переносить данные на новые товары не обязательно.
 */
add_action( 'init', 'wplend_enable_product_custom_fields' );
function wplend_enable_product_custom_fields() {
	add_post_type_support( 'product', 'custom-fields' );
}

// Нормализуем/проверяем формат при сохранении произвольного поля "virustotal".
add_filter( 'sanitize_post_meta_virustotal', 'wplend_sanitize_virustotal_meta', 10, 1 );
function wplend_sanitize_virustotal_meta( $meta_value ) {
	$value = trim( (string) $meta_value );

	if ( 1 === preg_match( '/\A[a-f0-9]{64}\z/i', $value ) ) {
		return strtolower( $value );
	}

	return esc_url_raw( $value );
}

function wplend_get_virustotal_url( $product_id ) {
	// 1) Новое поле "virustotal": ссылка целиком или SHA-256.
	$value = trim( (string) get_post_meta( $product_id, 'virustotal', true ) );

	if ( '' !== $value ) {
		if ( 1 === preg_match( '/\A[a-f0-9]{64}\z/i', $value ) ) {
			return 'https://www.virustotal.com/gui/file/' . rawurlencode( strtolower( $value ) );
		}
		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $value );
		}
	}

	// 2) Старое поле "virustotal_url" — готовая ссылка на отчёт.
	$legacy_url = trim( (string) get_post_meta( $product_id, 'virustotal_url', true ) );
	if ( '' !== $legacy_url && filter_var( $legacy_url, FILTER_VALIDATE_URL ) ) {
		return esc_url_raw( $legacy_url );
	}

	// 3) Старое поле "virustotal_sha256" — только хэш.
	$legacy_sha256 = strtolower( trim( (string) get_post_meta( $product_id, 'virustotal_sha256', true ) ) );
	if ( 1 === preg_match( '/\A[a-f0-9]{64}\z/', $legacy_sha256 ) ) {
		return 'https://www.virustotal.com/gui/file/' . rawurlencode( $legacy_sha256 );
	}

	return '';
}

/**
 * Поле "purchase" в произвольных полях товара скрыто из списка
 * и удаляется при каждом сохранении товара.
 */
add_filter( 'is_protected_meta', 'wplend_protect_purchase_meta', 10, 2 );
function wplend_protect_purchase_meta( $protected, $meta_key ) {
	if ( 'purchase' === $meta_key ) {
		return true;
	}
	return $protected;
}

add_action( 'save_post_product', 'wplend_remove_purchase_meta' );
function wplend_remove_purchase_meta( $product_id ) {
	if ( metadata_exists( 'post', $product_id, 'purchase' ) ) {
		delete_post_meta( $product_id, 'purchase' );
	}
}

/**
 * ============================================================
 * Дополнительные произвольные поля товара:
 * documentation, developer, changelog.
 * ============================================================
 * Значение можно указать как обычную ссылку:
 *   https://docs.example.com/product
 * либо со своей подписью через "|":
 *   Read the docs|https://docs.example.com/product
 * Для поля "developer" можно указать просто имя без ссылки —
 * тогда оно выводится обычным текстом.
 */
function wplend_get_link_field_row( $raw, $row_label, $default_link_text ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return null;
	}

	if ( false !== strpos( $raw, '|' ) ) {
		list( $text, $url ) = array_map( 'trim', explode( '|', $raw, 2 ) );
		$url                = esc_url_raw( $url );

		if ( '' === $url ) {
			return array(
				'label'   => $row_label,
				'is_link' => false,
				'text'    => sanitize_text_field( $text ),
			);
		}

		return array(
			'label'   => $row_label,
			'is_link' => true,
			'url'     => $url,
			'text'    => sanitize_text_field( '' !== $text ? $text : $default_link_text ),
		);
	}

	if ( filter_var( $raw, FILTER_VALIDATE_URL ) ) {
		return array(
			'label'   => $row_label,
			'is_link' => true,
			'url'     => esc_url_raw( $raw ),
			'text'    => $default_link_text,
		);
	}

	return array(
		'label'   => $row_label,
		'is_link' => false,
		'text'    => sanitize_text_field( $raw ),
	);
}

function wplend_resolve_shortcode_product_id( $atts ) {
	$product_id = absint( $atts['product_id'] );

	if ( ! $product_id && is_singular( 'product' ) ) {
		$product_id = get_queried_object_id();
	}

	if ( ! $product_id && function_exists( 'wc_get_product' ) ) {
		global $product;

		if ( is_object( $product ) && is_a( $product, 'WC_Product' ) ) {
			$product_id = $product->get_id();
		}
	}

	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
		return 0;
	}

	return $product_id;
}

/**
 * Одна строка "Подпись: значение/ссылка". Стили — инлайн, поэтому вид
 * не зависит от того, подключается ли style.css на странице (важно для
 * WPBakery, где блок может оказаться внутри текстового виджета с
 * собственным CSS-сбросом).
 */
function wplend_render_field_row( $row ) {
	if ( null === $row ) {
		return '';
	}

	$html  = '<div style="margin:4px 0;font-size:15px;line-height:1.5;font-family:inherit;">';
	$html .= '<span style="color:#35415b;font-weight:600;">' . esc_html( $row['label'] ) . ':</span> ';

	if ( ! empty( $row['is_link'] ) ) {
		// Внешняя ссылка: nofollow — не передаём вес, noopener/noreferrer —
		// безопасность/приватность. Ссылка остаётся в HTML и видна Google.
		$html .= '<a href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener noreferrer nofollow external" style="color:#2563eb;text-decoration:underline;font-weight:500;">';
		$html .= esc_html( $row['text'] );
		$html .= '</a>';
	} else {
		$html .= '<span style="color:#35415b;">' . esc_html( $row['text'] ) . '</span>';
	}

	$html .= '</div>';

	return $html;
}

/**
 * Бренд товара: читает ту же таксономию, что и Schema.org-код, и отдаёт
 * объект термина (для ссылки на страницу бренда) вместо просто имени.
 */
function wplend_get_product_brand_term( $product_id ) {
	$taxonomies = array( 'product_brand', 'pwb-brand', 'yith_product_brand' );

	foreach ( $taxonomies as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$terms = wp_get_post_terms( $product_id, $taxonomy );

		if ( ! is_wp_error( $terms ) && ! empty( $terms[0] ) ) {
			return $terms[0];
		}
	}

	return false;
}

/**
 * Шорткоды (каждый работает сам по себе, можно вставлять по одному
 * в разные колонки/строки WPBakery):
 * [virustotal_report]
 * [product_documentation]
 * [product_developer]
 * [product_changelog]
 * [product_brand]
 */
add_shortcode( 'virustotal_report', 'wplend_virustotal_report_shortcode' );
function wplend_virustotal_report_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'product_id' => 0,
			'title'      => 'File safety confirmed',
			'meta_text'  => 'No threats detected',
			'link_text'  => 'Open report',
		),
		$atts,
		'virustotal_report'
	);

	$product_id = wplend_resolve_shortcode_product_id( $atts );
	if ( ! $product_id ) {
		return '';
	}

	$url = wplend_get_virustotal_url( $product_id );
	if ( '' === $url ) {
		return '';
	}

	$title     = sanitize_text_field( $atts['title'] );
	$meta_text = sanitize_text_field( $atts['meta_text'] );
	$link_text = sanitize_text_field( $atts['link_text'] );

	// Уникальный класс на вызов, чтобы инлайновый <style> ниже не зависел
	// от внешнего style.css (может не грузиться / вырезаться оптимизатором).
	static $instance = 0;
	$instance++;
	$class = 'wplend-vt-' . $instance;

	$css = '<style>'
		. ".{$class}{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:14px 20px;margin:20px 0;background:#e3efe9;border-radius:10px;font-family:inherit}"
		. ".{$class} .wplend-vt-left{display:flex;align-items:center;gap:12px;min-width:0}"
		. ".{$class} .wplend-vt-icon{display:block;flex:0 0 26px;width:26px;height:26px}"
		. ".{$class} .wplend-vt-content{display:flex;align-items:center;gap:10px;flex-wrap:wrap;min-width:0}"
		. ".{$class} .wplend-vt-title{color:#1f2d27;font-size:16px;font-weight:600;line-height:1.3}"
		. ".{$class} .wplend-vt-meta{color:#5f7469;font-size:14px;line-height:1.3}"
		. ".{$class} .wplend-vt-meta-brand{font-weight:600}"
		. ".{$class} .wplend-vt-btn{display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;padding:10px 20px;background:#fff;color:#1f2d27!important;font-size:14px;font-weight:500;text-decoration:none!important;border:1px solid #c9d8cf;border-radius:6px}"
		. ".{$class} .wplend-vt-btn:hover,.{$class} .wplend-vt-btn:focus{background:#f5f9f7;border-color:#adc3b6}"
		. '</style>';

	$icon = '<svg class="wplend-vt-icon" viewBox="0 0 24 24" role="img" aria-label="Verified">'
		. '<circle cx="12" cy="12" r="12" fill="#58b99d"/>'
		. '<path d="M7.2 12.3l3 3 6.4-6.6" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>';

	$html  = $css;
	$html .= '<div class="' . esc_attr( $class ) . '">';
	$html .= '<div class="wplend-vt-left">';
	$html .= $icon;
	$html .= '<div class="wplend-vt-content">';

	if ( '' !== $title ) {
		$html .= '<span class="wplend-vt-title">' . esc_html( $title ) . '</span>';
	}
	if ( '' !== $meta_text ) {
		$html .= '<span class="wplend-vt-meta"><span class="wplend-vt-meta-brand">VirusTotal</span> &bull; ' . esc_html( $meta_text ) . '</span>';
	}

	$html .= '</div></div>';
	// Внешняя ссылка на virustotal.com: nofollow — не передаём вес,
	// noopener/noreferrer — техническая защита, ссылка при этом остаётся
	// видимой и индексируемой в HTML.
	$html .= '<a class="wplend-vt-btn" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer nofollow external">';
	$html .= esc_html( $link_text );
	$html .= '</a>';
	$html .= '</div>';

	return $html;
}

add_shortcode( 'product_documentation', 'wplend_product_documentation_shortcode' );
function wplend_product_documentation_shortcode( $atts ) {
	$atts       = shortcode_atts( array( 'product_id' => 0 ), $atts, 'product_documentation' );
	$product_id = wplend_resolve_shortcode_product_id( $atts );
	if ( ! $product_id ) {
		return '';
	}

	$row = wplend_get_link_field_row( get_post_meta( $product_id, 'documentation', true ), 'Documentation', 'View' );
	return wplend_render_field_row( $row );
}

add_shortcode( 'product_developer', 'wplend_product_developer_shortcode' );
function wplend_product_developer_shortcode( $atts ) {
	$atts       = shortcode_atts( array( 'product_id' => 0 ), $atts, 'product_developer' );
	$product_id = wplend_resolve_shortcode_product_id( $atts );
	if ( ! $product_id ) {
		return '';
	}

	$row = wplend_get_link_field_row( get_post_meta( $product_id, 'developer', true ), 'Developer', 'View' );
	return wplend_render_field_row( $row );
}

add_shortcode( 'product_changelog', 'wplend_product_changelog_shortcode' );
function wplend_product_changelog_shortcode( $atts ) {
	$atts       = shortcode_atts( array( 'product_id' => 0 ), $atts, 'product_changelog' );
	$product_id = wplend_resolve_shortcode_product_id( $atts );
	if ( ! $product_id ) {
		return '';
	}

	$row = wplend_get_link_field_row( get_post_meta( $product_id, 'changelog', true ), 'Changelog', 'View' );
	return wplend_render_field_row( $row );
}

add_shortcode( 'product_brand', 'wplend_product_brand_shortcode' );
function wplend_product_brand_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'product_id' => 0,
			'label'      => 'Brand',
		),
		$atts,
		'product_brand'
	);

	$product_id = wplend_resolve_shortcode_product_id( $atts );
	if ( ! $product_id ) {
		return '';
	}

	$term = wplend_get_product_brand_term( $product_id );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}

	$term_link = get_term_link( $term );
	if ( is_wp_error( $term_link ) ) {
		return '';
	}

	// Ссылка на архив бренда на этом же сайте — внутренняя, поэтому
	// без nofollow/noopener/noreferrer и без target="_blank".
	$row = array(
		'label'   => sanitize_text_field( $atts['label'] ),
		'is_link' => true,
		'url'     => $term_link,
		'text'    => $term->name,
	);

	$html  = '<div style="margin:4px 0;font-size:15px;line-height:1.5;font-family:inherit;">';
	$html .= '<span style="color:#35415b;font-weight:600;">' . esc_html( $row['label'] ) . ':</span> ';
	$html .= '<a href="' . esc_url( $row['url'] ) . '" style="color:#2563eb;text-decoration:underline;font-weight:500;">' . esc_html( $row['text'] ) . '</a>';
	$html .= '</div>';

	return $html;
}

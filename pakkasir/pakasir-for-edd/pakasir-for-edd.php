<?php

/**
 * Plugin Name: Pakasir for Easy Digital Downloads
 * Plugin URI:  https://pakasir.com/
 * Description: Pakasir Payment Gateway (QRIS, Virtual Account, etc) for EDD. (compatible with Indonesia banks/e-wallets only)
 * Version:     1.0.0
 * Author:      PT. Geksa Eksplorasi Satu
 * Author URI:  https://gx1.org/
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pakasir-for-edd
 * Requires Plugins: easy-digital-downloads
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Add gateway to EDD
 */
function pakasir_edd_register_gateway( $gateways ) {
	$gateways['pakasir'] = array(
		'admin_label'    => 'Pakasir',
		'checkout_label' => edd_get_option( 'pakasir_label', 'Pakai Pakasir' ),
		'supports'       => array( 'buy_now' ),
	);
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'pakasir_edd_register_gateway' );

/**
 * Process gateway - create EDD payment and redirect to Pakasir
 */
/**
 * Process gateway - create EDD payment and redirect to Pakasir
 */
function pakasir_edd_process_gateway( $purchase_data ) {
	// Only handle when gateway selected is pakasir
	if ( isset( $purchase_data['post_data']['edd-gateway'] ) && $purchase_data['post_data']['edd-gateway'] === 'pakasir' ) {

		$downloads    = $purchase_data['downloads'];
		$price        = $purchase_data['price']; // total amount
		$user_email   = $purchase_data['user_email'];
		$purchase_key = $purchase_data['purchase_key'];

		// Create a pending payment in EDD
		$payment_data = array(
			'price'        => $price,
			'date'         => gmdate( 'Y-m-d H:i:s' ), // gunakan waktu sekarang
			'user_email'   => $user_email,
			'purchase_key' => $purchase_key,
			'currency'     => edd_get_currency(),
			'downloads'    => $downloads,
			'user_info'    => $purchase_data['user_info'],
			'cart_details' => $purchase_data['cart_details'],
			'gateway'      => 'pakasir',
			'status'       => 'pending'
		);

		$payment_id = edd_insert_payment( $payment_data );

		if ( ! $payment_id ) {
			edd_set_error( 'pakasir_payment_error', 'Gagal membuat pembayaran. Coba ulang.' );
			edd_send_back_to_checkout();
			return;
		}

		// Build redirect URL
		$rate = (float) edd_get_option( 'pakasir_usd_rate', '' );
		if (!$rate) {
			$rate = 1;
		}
		$slug     = rawurlencode( edd_get_option( 'pakasir_slug', '' ) );
		$amount   = rawurlencode( $price * $rate );
		$order_id = rawurlencode( $payment_id );

		// Get EDD confirmation URL
		$success_page = edd_get_success_page_uri();
		$redirect_url_final = add_query_arg( 'payment_id', $payment_id, $success_page );

		$redirect_url = sprintf(
			'https://app.pakasir.com/pay/%s/%s?order_id=%s&redirect=%s',
			$slug,
			$amount,
			$order_id,
			rawurlencode( $redirect_url_final ),
		);

		wp_redirect( $redirect_url );
		exit;
	}
}
add_action( 'edd_gateway_pakasir', 'pakasir_edd_process_gateway' );

/**
 * Add settings fields to EDD settings -> Extensions
 */
function pakasir_edd_add_settings( $settings ) {
	$new_settings = array(
		'pakasir' => array(
			'id'   => 'pakasir',
			'name' => '<strong>Pakasir (Payment Gateway)</strong>',
			'desc' => 'Pengaturan untuk integrasi Pakasir.',
			'type' => 'header'
		),
		array(
			'id'   => 'pakasir_enabled',
			'name' => 'Enable Pakasir Gateway',
			'desc' => 'Centang untuk mengaktifkan metode pembayaran Pakasir.',
			'type' => 'checkbox',
			'std'  => 0
		),
		array(
			'id'   => 'pakasir_label',
			'name' => 'Nama pada Checkout',
			'desc' => 'Label yang tampil di checkout EDD.',
			'type' => 'text',
			'std'  => 'Pakai Pakasir'
		),
		array(
			'id'   => 'pakasir_slug',
			'name' => 'Pakasir Project Slug',
			'desc' => 'Masukkan slug project Pakasir (pakasir_slug).',
			'type' => 'text',
			'std'  => ''
		),
		array(
			'id'   => 'pakasir_api_key',
			'name' => 'Pakasir API Key',
			'desc' => 'Masukkan API Key untuk verifikasi transaksi.',
			'type' => 'password',
			'std'  => ''
		),
		array(
			'id'   => 'pakasir_usd_rate',
			'name' => 'Kurs USD',
			'desc' => 'Kosongkan jika harga sudah dalam rupiah',
			'type' => 'text',
			'std'  => ''
		),
        array(
            'id'   => 'pakasir_info',
            'name' => 'Panduan Setelah Simpan',
            'desc' => 
                '✅ Setelah tersimpan, silakan menuju <a href="/wp-admin/edit.php?post_type=download&page=edd-settings&tab=gateways"><strong>Payments</strong></a> kemudian aktifkan gateway <strong>Pakasir</strong>.<br><br>
                 💰 Kami juga menyediakan mata uang Rupiah. Silakan ganti currency <a href="/wp-admin/edit.php?post_type=download&page=edd-settings&tab=general&section=currency"><strong>di sini</strong></a>.',
            'type' => 'descriptive_text'
        ),
	);

	// Insert our settings near the end of Extensions tab
	return array_merge( $settings, $new_settings );
}
add_filter( 'edd_settings_extensions', 'pakasir_edd_add_settings' );

/**
 * Listener for webhook callback from Pakasir
 * Endpoint: ?edd-listener=pakasir
 * Pakasir will POST JSON body (assumption). We'll verify by calling transactiondetail endpoint.
 */
function pakasir_edd_listener() {
	if ( isset( $_GET['edd-listener'] ) && $_GET['edd-listener'] === 'pakasir' ) {
		// Allow only POST
		$method = isset($_SERVER['REQUEST_METHOD']) ? wp_unslash(sanitize_text_field($_SERVER['REQUEST_METHOD'])) : '';
		if ( $method !== 'POST' ) {
			status_header( 405 );
			echo 'Method Not Allowed';
			exit;
		}

		// Get raw POST body
		$raw = file_get_contents( 'php://input' );
		$data = json_decode( $raw, true );

		if ( ! $data || ! isset( $data['order_id'] ) ) {
			status_header( 400 );
			echo 'Bad Request';
			exit;
		}

		$order_id = sanitize_text_field( $data['order_id'] );
		$amount_received = isset( $data['amount'] ) ? $data['amount'] : null;

		// fetch local payment
		$payment_id = intval( $order_id );
		if ( ! $payment_id ) {
			status_header( 400 );
			echo 'Invalid order_id';
			exit;
		}

		$slug = edd_get_option( 'pakasir_slug', '' );
		$api_key = edd_get_option( 'pakasir_api_key', '' );
		if ( empty( $slug ) || empty( $api_key ) ) {
			status_header( 500 );
			echo 'Pakasir not configured';
			exit;
		}

		// Verify with Pakasir transactiondetail API (GET)
		$verify_url = add_query_arg( array(
			'project'  => $slug,
			'amount'   => $amount_received,
			'order_id' => $order_id,
			'api_key'  => $api_key
		), 'https://app.pakasir.com/api/transactiondetail' );

		$response = wp_remote_get( $verify_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			status_header( 502 );
			echo 'Error contacting Pakasir';
			exit;
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( ! $json ) {
			status_header( 502 );
			echo 'Invalid response from Pakasir';
			exit;
		}
		
		$transaction = $json['transaction'];

		// expected response: {"status":"completed"} (per agreement)
		if ( isset( $transaction['status'] ) && $transaction['status'] === 'completed' ) {
			// Optionally, you may check amount matches local payment amount
			$local_amount = edd_get_payment_amount( $payment_id );
			// Some systems use integers or strings; loosely compare after float cast
			if ( $amount_received !== null ) {
				if ( floatval( $amount_received ) != floatval( $local_amount ) ) {
					// Amount mismatch — do not mark completed
					// Log mismatches
					edd_insert_payment_note( $payment_id, 'Pakasir verification failed: amount mismatch. Received: ' . esc_html( $amount_received ) . ' Local: ' . esc_html( $local_amount ) );
					status_header( 400 );
					echo 'Amount mismatch';
					exit;
				}
			}

			// Save transaction info
			edd_update_payment_meta( $payment_id, 'pakasir_transaction', $transaction );
			edd_insert_payment_note( $payment_id, 'Pakasir reported completed transaction.' );

			// Mark payment as publish (completed) - as requested
			edd_update_payment_status( $payment_id, 'publish' );

			// Return success
			status_header( 200 );
			echo 'OK';
			exit;
		} else {
			// Not completed — record note
			edd_insert_payment_note( $payment_id, 'Pakasir reported non-completed status: ' . ( isset( $transaction['status'] ) ? esc_html( $transaction['status'] ) : 'unknown' ) );
			status_header( 400 );
			echo 'Not completed';
			exit;
		}
	}
}
add_action( 'init', 'pakasir_edd_listener' );

/**
 * Only show Pakasir gateway when enabled
 */
function pakasir_edd_gateway_enabled( $gateways ) {
	$enabled = edd_get_option( 'pakasir_enabled', false );
	if ( ! $enabled ) {
		unset( $gateways['pakasir'] );
	}
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'pakasir_edd_gateway_enabled', 20 );

/**
 * Add helpful info to payment details meta box in admin
 */
function pakasir_edd_payment_meta_box( $payment_id ) {
	$meta = edd_get_payment_meta( $payment_id );
	if ( isset( $meta['pakasir_transaction'] ) ) {
		echo '<p><strong>Pakasir transaction:</strong></p>';
		echo '<pre style="white-space:pre-wrap;">' . esc_html( json_encode($meta['pakasir_transaction']) ) . '</pre>';
	}
}
add_action( 'edd_payment_inside_details', 'pakasir_edd_payment_meta_box' );

/**
 * Disable credit card form for Pakasir
 */
// Hilangkan form kartu kredit untuk gateway Pakasir
add_filter( 'edd_pakasir_cc_form', '__return_false' );

// Tambahkan Rupiah ke daftar mata uang EDD
add_filter( 'edd_currencies', function( $currencies ) {
    $currencies['IDR'] = 'Indonesian Rupiah (Rp)';
    return $currencies;
} );

// Ubah simbol agar pakai "Rp"
add_filter( 'edd_currency_symbol', function( $symbol, $currency ) {
    if ( $currency === 'IDR' ) {
        return 'Rp';
    }
    return $symbol;
}, 10, 2 );

// Hilangkan desimal karena Rupiah tidak punya sen
add_filter( 'edd_format_amount_decimals', function( $decimals ) {
    return 0;
});


// Add Manage link, next to deactivate plugin link
function pakasir_add_plugin_action_links($links)
{
  $settings_link = '<a href="' . admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions') . '">Manage</a>';
  array_unshift($links, $settings_link);
  return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pakasir_add_plugin_action_links');

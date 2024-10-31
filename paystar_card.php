<?php

/*
Plugin Name: paystar-card-payment-for-woocommerce
Plugin URI: https://paystar.ir
Description: paystar-card-payment-for-woocommerce
Version: 1.0
Author: ماژول بانک
Author URI: https://www.modulebank.ir
 */

add_action('plugins_loaded', function() {
	load_plugin_textdomain('paystar-card-payment-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages');
	if (!class_exists('WC_Payment_Gateway')) return;
	class WC_PayStarCard extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$this->id = 'paystarcard';
			$this->plugin_name = __('paystar-card-payment-for-woocommerce', 'paystar-card-payment-for-woocommerce');
			$this->method_title = __('PayStar Card Payment Gateway', 'paystar-card-payment-for-woocommerce');
			$this->icon = plugin_dir_url(__FILE__).'images/logo.png';
			$this->has_fields = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->terminal = $this->settings['terminal'];
			add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_paystar_card_response'));
			add_action('valid-paystarcard-request', array($this, 'successful_request'));
			add_action('woocommerce_update_options_payment_gateways_paystarcard', array($this, 'process_admin_options'));
			add_action('woocommerce_receipt_paystarcard', array($this, 'receipt_page'));
		}

		function init_form_fields()
		{
			$this->form_fields = array(
				'enabled'     => array(
					'title'   => __('Enable / Disable', 'paystar-card-payment-for-woocommerce'),
					'type'    => 'checkbox',
					'label'   => __('Enable or Disable This Payment Mehod', 'paystar-card-payment-for-woocommerce'),
					'default' => 'yes'
				),
				'title'           => array(
					'title'       => __('Display Title', 'paystar-card-payment-for-woocommerce'),
					'type'        => 'text',
					'description' => __('Display Title', 'paystar-card-payment-for-woocommerce'),
					'default'     => __('PayStar Card Payment Gateway', 'paystar-card-payment-for-woocommerce')
				),
				'description'     => array(
					'title'       => __('Payment Instruction', 'paystar-card-payment-for-woocommerce'),
					'type'        => 'textarea',
					'description' => __('Payment Instruction', 'paystar-card-payment-for-woocommerce'),
					'default'     => __('Pay by PayStar Card Payment Gateway', 'paystar-card-payment-for-woocommerce')
				),
				'terminal'        => array(
					'title'       => __('PayStar Card Terminal', 'paystar-card-payment-for-woocommerce'),
					'type'        => 'text',
					'description' => __('Enter PayStar Card Terminal', 'paystar-card-payment-for-woocommerce')
				),
			);
		}

		public function admin_options()
		{
			echo '<h3>'.__('PayStar Card Payment Gateway', 'paystar-card-payment-for-woocommerce').'</h3>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		function payment_fields()
		{
			if($this->description) {
				echo esc_html(wpautop(wptexturize($this->description)));
			}
		}

		function receipt_page($order)
		{
			echo '<p>'.__('thank you for your order. you are redirecting to paystar gateway. please wait', 'paystar-card-payment-for-woocommerce').'</p>';
			echo $this->generate_paystar_card_form($order);
		}

		function process_payment($order_id)
		{
			$order = new WC_Order($order_id);
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true)); 
		}

		function check_paystar_card_response()
		{
			global $woocommerce;
			if (isset($_GET['amp;order_id']) || isset($_GET['order_id']))
			{
				if (isset($_GET['amp;order_id'])) {
					list($order_id, $nothing) = explode('#', sanitize_text_field($_GET['amp;order_id']));
					$order = new WC_Order($order_id);
				} elseif (isset($_GET['order_id'])){
					list($order_id, $nothing) = explode('#', sanitize_text_field($_GET['order_id']));
					$order = new WC_Order($order_id);
				}

				if (isset($_GET['amp;hashid'])) {
					$hashid = sanitize_text_field($_GET['amp;hashid']);
				} elseif (isset($_GET['hashid'])){
					$hashid = sanitize_text_field($_GET['hashid']);
				}

				if($order->status != 'completed')
				{
					require_once(dirname(__FILE__) . '/paystar_card_payment_helper.class.php');
					$p = new PayStarCard_Payment_Helper($this->terminal);
					$r = $p->paymentVerify($x = array(
							'hashid' => $hashid,
						));
					if ($r)
					{
						$message = sprintf(__("Paymenter Card Number : %s", 'paystar-card-payment-for-woocommerce'), '<span dir="ltr" style="direction:ltr">'.$p->data->card_number.'</span>');
						$order->add_order_note($message);
						$message = sprintf(__("Payment Completed. OrderID : %s . PaymentRefrenceID : %s", 'paystar-card-payment-for-woocommerce'), $order_id, $p->txn_id);
						$order->payment_complete();
						$order->add_order_note($message);
						$woocommerce->cart->empty_cart();
						wc_add_notice($message, 'success');
						wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
						exit;
					}
					else
					{
						$message = $p->error;
						$order->add_order_note($message);
					}
				}
				else
				{
					$message = __('Error : Order Not Exists OR Already Paid!', 'paystar-card-payment-for-woocommerce');
				}
			}
			else
			{
				$message = __('System (Permission) Error.', 'paystar-card-payment-for-woocommerce');
			}
			if (isset($message) && $message) wc_add_notice($message, 'error');
			wp_redirect($woocommerce->cart->get_checkout_url());
			exit;
		}

		public function generate_paystar_card_form($order_id)
		{
			global $woocommerce;
			$order = new WC_Order($order_id);
			require_once(dirname(__FILE__) . '/paystar_card_payment_helper.class.php');
			$p = new PayStarCard_Payment_Helper($this->terminal);
			$r = $p->paymentRequest(array(
					'amount'       => ($order->order_total * (get_woocommerce_currency() == 'IRHT' ? 10000 : (get_woocommerce_currency() == 'IRT' ? 10 : 1))),
					'order_id'     => $order_id . '#' . time(),
					'callback_url' => add_query_arg(array('wc-api' => get_class($this), 'order_id' => $order_id), get_site_url().'/'),
					'token'        => $this->terminal
				));
			if ($r)
			{
				update_post_meta($order_id, 'paystar_card_token', $r->token);
				session_write_close();
				echo '<form name="frmPayStarPayment" method="get" action="https://card.paystar.ir/check"><input type="hidden" name="token" value="'.esc_html($r->token).'" />';
				echo '<input class="paystar_btn btn button" type="submit" value="'.__('Pay', 'paystar-card-payment-for-woocommerce').'" /></form>';
				echo '<script>document.frmPayStarPayment.submit();</script>';
			}
			else
			{
				$order->add_order_note(__('Erorr', 'paystar-card-payment-for-woocommerce') . ' : ' . $p->error);
				echo esc_html($p->error);
			}
		}
	}

	add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
		return array_merge(array('settings' => '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=paystarcard').'">'.__('Settings', 'paystar-card-payment-for-woocommerce').'</a>'), $links);
	}, 666);

	add_filter('woocommerce_payment_gateways', function($methods) {
		$methods[] = 'WC_PayStarCard';
		return $methods;
	}, -666);
}, 666);

?>
<?php

if (!class_exists('PayStarCard_Payment_Helper'))
{
	class PayStarCard_Payment_Helper
	{
		public function __construct($terminal = '')
		{
			$this->terminal = $terminal;
		}
		public function paymentRequest($data)
		{
			$result = $this->curl('https://card.paystar.ir/api/call', $data);
			if (is_object($result) && isset($result->status)) {
				$this->data = $result->data;
				if ($result->status == 'ok') {
					return $result->data;
				} else {
					$this->error = $result->message;
				}
			} else {
				$this->error = 'خطا در ارتباط با درگاه پی استار';
			}
			return false;
		}
		public function paymentVerify($data)
		{
			if ($data['hashid']) {
				$result = $this->curl('https://card.paystar.ir/api/verify', array(
						'token'  => $this->terminal,
						'hashid' => $data['hashid'],
					));
				if (is_object($result) && isset($result->status)) {
					$this->data = $result->data;
					if ($result->status == 'ok' && $result->data->status == 1) {
						$this->txn_id = $data['hashid'];
						return true;
					} else {
						$this->error = $result->message;
					}
				} else {
					$this->error = 'خطا در ارتباط با درگاه پی استار';
				}
			} else {
				$this->error = 'تراکنش توسط کاربر لغو شد';
			}
			return false;
		}
		public function curl($url, $data)
		{
			$result = wp_remote_post($url, array('body' => wp_json_encode($data), 'headers' => ['Content-Type' => 'application/json'], 'sslverify' => false));
			if (!is_wp_error($result))
			{
				return json_decode($result['body']);
			}
		}
	}
}

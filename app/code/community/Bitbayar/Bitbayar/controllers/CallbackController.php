<?php

class Bitbayar_Bitbayar_CallbackController extends Mage_Core_Controller_Front_Action
{

	public function callbackAction() {

		$bitbayar_url_check = 'https://bitbayar.com/api/check_invoice';
		$apiToken = Mage::getStoreConfig('payment/Bitbayar/api_token');

		// Get callback data
		$id_order = (int)$_POST['invoice_id'];
		$id_bitbayar = $_POST['id'];
		$total_paid = $_POST['rp'];

		//~ $order = Mage::getModel('sales/order')->load($id_order);
		$order = \Mage::getModel('sales/order')->loadByIncrementId($id_order);

		//~ Get Status 
		$data_check = array(
			'token' => $apiToken,
			'id' => $id_bitbayar
		);
		$check_status = $this->curlPost($bitbayar_url_check, $data_check);
		$result = json_decode($check_status);
		$status = $result->status;

		// Update the order to notifiy that it has been paid
		if ($status === 'paid') {

			$payment = \Mage::getModel('sales/order_payment')->setOrder($order);

				$payment->registerCaptureNotification($total_paid);
				$order->addPayment($payment);

				// If the customer has not already been notified by email
				// send the notification now that there's a new order.
				if (!$order->getEmailSent()) {
					$order->sendNewOrderEmail();
				}

				$order->save();
		}else {
			\Mage::throwException('Could not create a payment object in the BitBayar Callback controller.');
		}
	}

	public function curlPost($url, $data) 
	{
		if(empty($url) OR empty($data))
		{
			return 'Error: invalid Url or Data';
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_POST,count($data));
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,10);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);  # Set curl to return the data instead of printing it to the browser.
		curl_setopt($ch, CURLOPT_USERAGENT , "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)"); # Some server may refuse your request if you dont pass user agent

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		//execute post
		$result = curl_exec($ch);

		//close connection
		curl_close($ch);
		return $result;
	}
}
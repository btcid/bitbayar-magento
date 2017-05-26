<?php

class Bitbayar_Bitbayar_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
	protected $_code = 'Bitbayar';

	/**
	 * Is this payment method a gateway (online auth/charge) ?
	 */
	protected $_isGateway = true;

	/**
	 * Can authorize online?
	 */
	protected $_canAuthorize = true;

	/**
	 * Can capture funds online?
	 */
	protected $_canCapture = false;

	/**
	 * Can capture partial amounts online?
	 */
	protected $_canCapturePartial = false;

	/**
	 * Can refund online?
	 */
	protected $_canRefund = false;

	/**
	 * Can void transactions online?
	 */
	protected $_canVoid = false;

	/**
	 * Can use this payment method in administration panel?
	 */
	protected $_canUseInternal = true;

	/**
	 * Can show this payment method as an option on checkout payment page?
	 */
	protected $_canUseCheckout = true;

	/**
	 * Is this payment method suitable for multi-shipping checkout?
	 */
	protected $_canUseForMultishipping = true;

	/**
	 * Can save credit card information for future processing?
	 */
	protected $_canSaveCc = false;


	public function authorize(Varien_Object $payment, $amount) 
	{
		$apiToken = Mage::getStoreConfig('payment/Bitbayar/api_token');
		$bitbayar_currency = 'IDR';

		if($apiToken == null) {
			throw new Exception("Before using the Bitbayar plugin, you need to enter an API Token in Magento Admin > Configuration > System > Payment Methods.");
		}
		if (strlen($apiToken) != 33 || $apiToken[0] != 'S')
		{
			throw new Exception("API Token is not valid!");
		}

		$baseCurrencyCode = Mage::app()->getBaseCurrencyCode();
		$allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies();
		$currencyRates = Mage::getModel('directory/currency')->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
		
		if($baseCurrencyCode!=$bitbayar_currency){
			$curr_rate = $currencyRates[$bitbayar_currency];
		}else{
			$curr_rate = 1;
		}

		$order = $payment->getOrder();
		$currency = $order->getBaseCurrencyCode();

		$successUrl = Mage::getStoreConfig('payment/Bitbayar/custom_success_url');
		$cancelUrl = Mage::getStoreConfig('payment/Bitbayar/custom_cancel_url');
		if ($successUrl == false) {
			$successUrl = Mage::getUrl('bitbayar'). 'redirect/success/';
		}
		if ($cancelUrl == false) {
			$cancelUrl = Mage::getUrl('bitbayar'). 'redirect/cancel/';
		}

		$bitbayar_url = 'https://bitbayar.com/api/create_invoice';

		$dataPost=array(
			'token'=>$apiToken,
			'invoice_id'=>$order['increment_id'],
			'rupiah'=>round($amount*$curr_rate),
			'memo'=>'Invoice #'.$order['increment_id'].' - Magento',
			'callback_url'=>Mage::getUrl('bitbayar'). 'callback/callback',
			'url_success'=>$successUrl,
			'url_failed'=>$cancelUrl
		);

		$bb_pay = $this->curlPost($bitbayar_url, $dataPost);
		$result = json_decode($bb_pay);

		if($result->success){
			$redirectUrl = $result->payment_url;
		}

		//~ Redirect customer to payment page
		$payment->setIsTransactionPending(true); // Set status to Payment Review while waiting for Bitbayar postback
		Mage::getSingleton('customer/session')->setRedirectUrl($redirectUrl);
		return $this;
	}


	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getSingleton('customer/session')->getRedirectUrl();
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
?>
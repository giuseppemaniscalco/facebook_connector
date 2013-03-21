<?php

require_once "/AbstractOrderIntegrator.php";

class GiuseppeOrderIntegration extends AbstractOrderIntegration
{
	private   $_key      	   = 'giuseppePwd';
	protected $_paymentMethod  = 'PayPal';
	protected $_oCodePrefix    = 'giuseppe_';
	
	public function parseRequest($data)
	{
		list($sharedKeyEncoded, $payloadEncoded) = explode('.', $data, 2);

		$sharedKeyDecoded  = ZG_AbstractOrderIntegration::base64_url_decode($sharedKeyEncoded);
		
		$payloadDecoded = json_decode(ZG_AbstractOrderIntegration::base64_url_decode($payloadEncoded), true);

		$expectedKey = hash_hmac('sha256', $payloadEncoded, $this->_key, true);
		
		if(empty($expectedKey) || !($expectedKey === $sharedKeyDecoded))
			return json_encode(array('status' => 'failure: invalid key'));
		
		return $this->_checkValidRequest($payloadDecoded); 
	}
	
	public function simulatorRequest($key, $order_data)
	{
		$data_encoded = ZG_AbstractOrderIntegration::base64_url_encode(json_encode($order_data));

		$sign = hash_hmac('sha256', $data_encoded, $key, true);
		$sign_encoded = ZG_AbstractOrderIntegration::base64_url_encode($sign);

		return  $sign_encoded.'.'.$data_encoded;
	}	
	
	protected function _checkValidRequest($data)
	{
		ZG_Log::log('_checkValidRequest', 'firebug');
		
		if(empty($data))
			return json_encode(array('status' => 'failure: invalid key'));
		
		if(!is_array($data['order']))
			return json_encode(array('status' => 'failure: The order detail is empty'));
		
		foreach($data['order'] as $r)
			$sumDetailOrder += ($r['price'] * $r['quantity']);

		if( !($data['total_cost'] == ($data['shipping_fee'] + $sumDetailOrder)) )
			return json_encode(array('status' => 'failure: The total cost is not equal to the sum between shipping fee and the sum of products (product price * quantity)'));

		if(preg_match('\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b', $data['email']) > 0)
			return json_encode(array('status' => 'failure: The email is not valid'));

		if($this->_checkTransaction($data['orderId'], $data['transaction']) > 0)
			return json_encode(array('status' => 'failure: Transaction already present in the db'));
		
		$checkOrder = $this->_createOrder($data, $this->_setOCode($data['orderId'], $data['transaction']));
		if(!$checkOrder)
			return json_encode(array('status' => 'failure: order has not been created'));

		return json_encode(array('status' => 'success')); 
	}
}

?>

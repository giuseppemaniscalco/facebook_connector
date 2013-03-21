<?php

abstract class AbstractOrderIntegration
{
	protected $_paymentMethod  = '';
	protected $_oCodePrefix    = '';
	
	abstract public function parseRequest($data);
	
	abstract public function simulatorRequest($key, $order_data);
	
	abstract protected function _checkValidRequest($data);

	public function _checkTransaction($orderId, $transaction)
	{
		$oCode = $this->_createOCode($orderId,$transaction);
		$db = ZG::getDb();
		$transactions = $db->getOne("query sql");
		ZG_Log::log("transactions=>{$transactions}",'firebug');
		return $transactions;
	}
	
	public function _createOrder($data, $oCode)
	{
		$db = ZG::getDb(); 
		$db->autocommit(false);
		
		$addressObj = $this->_createAddressObjByOrderData($data);

		$address = ZG_Address::singleton();
		
		$userId = self::_checkUser($data['email']);
		if(empty($userId))
		{
			$userId = $this->_createUser ($data, $addressObj);
		}
		else
		{
			$addressObj->user_id    = $userId;
			$addressObj->is_default = 1;
			$address->addAddress($addressObj);
		}

		if($userId <= 0)
			return false;
		
		$defaultAddressId = $address->getDefaultShippingAddressId( $userId );

		$order = $this->_createOrderByData( $data, $userId, $defaultAddressId, $oCode);

		$checkPayment = checkPayment::Factory( $this->_paymentMethod, $this->_getRequestMock($order->order_id));
		
		if( !checkPayment::isValid( $this->_paymentMethod ) ){
			$db->rollback();
			return false;
		}
	
		$result = $order->update();

		ZG::changeStatus( $checkPayment->order_id, ZG_Order::$PAID_ORDER_STATUS);
		
		if( method_exists($checkPayment, 'CheckNotification') ){
			$checkPayment->CheckNotification();
		}
		
		$db->commit();
		
		return true;
	}

	private function _createUser($data, $addressObj) {
		$objUser    = $this->_createUserObjByOrderData($data, $addressObj);
		$aObject = (object)'';
		$addUser = new User_AddUser($objUser, $aObject);
		$addUser->attach(new AuthenticateUser());
		return $addUser->run();
	}
	
	private function _createOrderByData($data, $userId, $addressId, $oCode) {
		$orderObj = new Es_order_ext ( $this->_createCart($data, $userId, $addressId, $oCode) );
		$order = $orderObj->_order;
		$order->total_paid                  = $data['total_cost'];
		$order->payment_method              = $this->_paymentMethod;
		$order->total_paid_default_currency = 0;
		$order->payment_type_id 			= $this->getPaymentTypeId($order->order_id);
		return $order;
	}
	
	private function _createUserObj($objUser) {
		
	}

	private function getPaymentTypeId($order_id){
		$payment = new Payment();
		$payment = $payment->Factory ( $this->_paymentMethod, $order_id );
		return $payment->payment->payment_id;
	}
	
	private function _getRequestMock($orderId){
		return new Anonymous($orderId);
	}
	
	private function _createCart($data,$userId, $addressId, $oCode){
		$virtualCart = new Es_cart_ext ( $userId );
		$virtualCart->setUid( $userId );
		$virtualCart->setAddressId( $addressId );
		$virtualCart->setOCode($oCode);
		
		foreach($data['order'] as $r)
		{
			$virtualCart->addItem($r['unique'], $r['quantity'], 'product');
		}
		
		$virtualCart->setStatus(1);
		
		return $virtualCart->getCart();
	}
	
	private function _createOCode($orderId, $transaction){
		return $this->_oCodePrefix."{$orderId}_{$transaction}";
	}
	
	protected function _setOCode($orderId, $transaction)
	{
		$oCode = $this->_createOCode($orderId, $transaction);
		ZG::setOrigin($oCode);
		return $oCode;
	}
	
	private function _createAddressObjByOrderData($data){
		$addressObj->first_name      = $data['name'];
		$addressObj->last_name       = $data['name'];
		$addressObj->email           = $data['email'];
		$addressObj->telephone       = $data['phone'];
		$addressObj->addr_1          = $data['address'];
		$addressObj->addr_2          = '';
		$addressObj->addr_3          = '';
		$addressObj->city            = $data['city'];
		$addressObj->post_code       = $data['zip'];

		return $addressObj;
	}
	
	private function _createUserObjByOrderData($data, $addressObj){
		$objPwd    = new Text_Password();
		$randomPwd = $objPwd->create();
		$objUser->user->username         = $data['email'];
		$objUser->user->passwd           = $randomPwd;
		$objUser->user->email            = $data['email'];
		$objUser->user->register_address = 1;
		$objUser->same_address           = 1;
		$objUser->shipping_address       = $addressObj;
		$objUser->user->is_email_public  = 3;
		$objUser->noredir                = 1;
		return $objUser;
	}
	
	static function base64_url_encode($input) {
		ZG_Log::log("base64_url_encode=>{$input}", 'firebug');
		return strtr(base64_encode($input), '+/', '-_');
	}

	static function base64_url_decode($input) {
		ZG_Log::log("base64_url_decode=>{$input}", 'firebug');
		return base64_decode(strtr($input, '-_', '+/'));
	}
	
	static function _checkUser($email)
	{
		$query = "sql query";
		$db = ZG::getDb();
		return $db->getOne($query);
	}
}

?>

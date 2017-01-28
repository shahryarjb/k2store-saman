<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_k2store
 * @subpackage 	Trangell_Saman
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

require_once (JPATH_ADMINISTRATOR.'/components/com_k2store/library/plugins/payment.php');
if (!class_exists ('checkHack')) {
	require_once( JPATH_PLUGINS . '/k2store/payment_trangellsaman/trangell_inputcheck.php');
}

class plgK2StorePayment_trangellsaman extends K2StorePaymentPlugin
{
    var $_element    = 'payment_trangellsaman';

	function plgK2StorePayment_trangellsaman(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage( 'com_k2store', JPATH_ADMINISTRATOR );
	}
	
	function _beforePayment($order) {
		$surcharge = 0;
		$surcharge_percent = $this->params->get('surcharge_percent', 0);
		$surcharge_fixed = $this->params->get('surcharge_fixed', 0);
		if((float) $surcharge_percent > 0 || (float) $surcharge_fixed > 0) {
			if((float) $surcharge_percent > 0) {
				$surcharge += ($order->order_total * (float) $surcharge_percent) / 100;
			}
	
			if((float) $surcharge_fixed > 0) {
				$surcharge += (float) $surcharge_fixed;
			}
			
			$order->order_surcharge = round($surcharge, 2);
			$order->calculateTotals();
		}
	
	}
	
    function _prePayment( $data ) {
		$vars = new JObject();
		$vars->order_id = $data['order_id'];
		$vars->orderpayment_id = $data['orderpayment_id'];
		$vars->orderpayment_amount = $data['orderpayment_amount'];
		$vars->orderpayment_type = $this->_element;
		$vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');
		$vars->button_text = $this->params->get('button_text', 'K2STORE_PLACE_ORDER');
		//==========================================================
		$vars->samanmerchantId = $this->params->get('samanmerchantId', '');

		if (($vars->samanmerchantId == null || $vars->samanmerchantId == '')){
			$link = JRoute::_(JURI::root(). "index.php?option=com_k2store" );
			$html =  '<h2 style="color:red;">لطفا تنظیمات درگاه بانک سامان را بررسی کنید</h2>';
			return $html;
		}
		else {
			$vars->reservationNumber = time();
			$vars->totalAmount =  round($vars->orderpayment_amount,0);
			$vars->callBackUrl  = JRoute::_(JURI::root(). "index.php?option=com_k2store&view=checkout" ) .'&orderpayment_id='.$vars->orderpayment_id . '&orderpayment_type=' . $vars->orderpayment_type .'&task=confirmPayment';
			$vars->sendUrl = "https://sep.shaparak.ir/Payment.aspx";
			$html = $this->_getLayout('prepayment', $vars);
			return $html;
		}
    }

    function _postPayment( $data ) {
        $app = JFactory::getApplication(); 
        $html = '';
		$jinput = $app->input;
		$orderpayment_id = $jinput->get->get('orderpayment_id', '0', 'INT');
        JTable::addIncludePath( JPATH_ADMINISTRATOR.'/components/com_k2store/tables' );
        $orderpayment = JTable::getInstance('Orders', 'Table');
        require_once (JPATH_SITE.'/components/com_k2store/models/address.php');
    	$address_model = new K2StoreModelAddress();
		//$address_model->getShippingAddress()->phone_2
		//==========================================================================
		$resNum = $jinput->post->get('ResNum', '0', 'INT');
		$trackingCode = $jinput->post->get('TRACENO', '0', 'INT');
		$stateCode = $jinput->post->get('stateCode', '1', 'INT');
		
		$refNum = $jinput->post->get('RefNum', 'empty', 'STRING');
		if (checkHack::strip($refNum) != $refNum )
			$refNum = "illegal";
		$state = $jinput->post->get('State', 'empty', 'STRING');
		if (checkHack::strip($state) != $state )
			$state = "illegal";
		$cardNumber = $jinput->post->get('SecurePan', 'empty', 'STRING'); 
		if (checkHack::strip($cardNumber) != $cardNumber )
			$cardNumber = "illegal";
	
		$merchantId = $this->params->get('samanmerchantId', '');	

	    if ($orderpayment->load( $orderpayment_id )){
			$customer_note = $orderpayment->customer_note;
			if($orderpayment->id == $orderpayment_id) {
				if (
					checkHack::checkNum($resNum) &&
					checkHack::checkNum($trackingCode) &&
					checkHack::checkNum($stateCode) 
				){
					if (isset($state) && ($state == 'OK' || $stateCode == 0)) {
						try {
							$out    = new SoapClient('https://sep.shaparak.ir/payments/referencepayment.asmx?WSDL');
							$resultCode    = $out->VerifyTransaction($refNum, $merchantId);
						
							if ($resultCode == round($orderpayment->order_total,0)) {
								$msg= $this->getGateMsg(1); 
								$this->saveStatus($msg,1,$customer_note,'ok',$trackingCode,$orderpayment,$cardNumber);
								$app->enqueueMessage($trackingCode . ' کد پیگیری شما', 'message');	
							}
							else {
								$msg= $this->getGateMsg($state); 
								$this->saveStatus($msg,3,$customer_note,'nonok',null,$orderpayment,$cardNumber);
								$link = JRoute::_(JURI::root(). "index.php?option=com_k2store" );
								$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
							}
						}
						catch(\SoapFault $e)  {
							$msg= $this->getGateMsg('error'); 
							$this->saveStatus($msg,3,$customer_note,'nonok',null,$orderpayment,$cardNumber);
							$link = JRoute::_(JURI::root(). "index.php?option=com_k2store" );
							$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
						}
					}
					else {
						$msg= $this->getGateMsg($state); 
						$this->saveStatus($msg,4,$customer_note,'nonok',null,$orderpayment,$cardNumber);
						$link = JRoute::_(JURI::root(). "index.php?option=com_k2store" );
						$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
					}
				}
				else {
					$msg= $this->getGateMsg('hck2'); 
					$this->saveStatus($msg,3,$customer_note,'nonok',null,$orderpayment,$cardNumber);
					$link = JRoute::_(JURI::root(). "index.php?option=com_k2store" );
					$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
				}
			}
			else {
				$msg= $this->getGateMsg('notff'); 
				$link = JRoute::_(JURI::root(). "index.php?option=com_k2store" );
				$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
			}
	    }
		else {
			$msg= $this->getGateMsg('notff'); 
			$link = JRoute::_(JURI::root(). "index.php?option=com_k2store" );
			$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
		}	
	}

    function _renderForm( $data )
    {
    	$user = JFactory::getUser();
        $vars = new JObject();
        $vars->onselection_text = $this->params->get('onselection', '');
        $html = $this->_getLayout('form', $vars);
        return $html;
    }

    function getPaymentStatus($payment_status) {
    	$status = '';
    	switch($payment_status) {
			case '1': $status = JText::_('K2STORE_CONFIRMED'); break;
			case '2': $status = JText::_('K2STORE_PROCESSED'); break;
			case '3': $status = JText::_('K2STORE_FAILED'); break;
			case '4': $status = JText::_('K2STORE_PENDING'); break;
			case '5': $status = JText::_('K2STORE_INCOMPLETE'); break;
			default: $status = JText::_('K2STORE_PENDING'); break;	
    	}
    	return $status;
    }

	function saveStatus($msg,$statCode,$customer_note,$emptyCart,$trackingCode,$orderpayment,$CardNumber){
		$html ='<br />';
		$html .='<strong>'.JText::_('K2STORE_BANK_TRANSFER_INSTRUCTIONS').'</strong>';
		$html .='<br />';
		if (isset($trackingCode)){
			$html .= '<br />';
			$html .= $trackingCode .'شماره پیگری ';
			$html .= '<br />';
			$html .= $CardNumber .' شماره کارت ';
			$html .= '<br />';
		}
		$html .='<br />' . $msg;
		$orderpayment->customer_note =$customer_note.$html;
		$payment_status = $this->getPaymentStatus($statCode); 
		$orderpayment->transaction_status = $payment_status;
		$orderpayment->order_state = $payment_status;
		$orderpayment->order_state_id = $this->params->get('payment_status', $statCode); 
		
		if ($orderpayment->save()) {
			if ($emptyCart == 'ok'){
				JLoader::register( 'K2StoreHelperCart', JPATH_SITE.'/components/com_k2store/helpers/cart.php');
				K2StoreHelperCart::removeOrderItems( $orderpayment->id );
			}
		}
		else
		{
			$errors[] = $orderpayment->getError();
		}
		if ($statCode == 1){
			require_once (JPATH_SITE.'/components/com_k2store/helpers/orders.php');
			K2StoreOrdersHelper::sendUserEmail($orderpayment->user_id, $orderpayment->order_id, $orderpayment->transaction_status, $orderpayment->order_state, $orderpayment->order_state_id);
		}

 		$vars = new JObject();
		$vars->onafterpayment_text = $msg;
		$html = $this->_getLayout('postpayment', $vars);
		$html .= $this->_displayArticle();
		return $html;
	}

    function getGateMsg ($msgId) {
		switch($msgId){
			case '-1': $out=  'خطای داخل شبکه مالی'; break;
			case '-2': $out=  'سپردها برابر نیستند'; break;
			case '-3': $out=  'ورودی های حاوی کاراکترهای غیر مجاز می باشد'; break;
			case '-4': $out=  'کلمه عبور یا کد فروشنده اشتباه است'; break;
			case '-5': $out=  'Database excetion'; break;
			case '-6': $out=  'سند قبلا برگشت کامل یافته است'; break;
			case '-7': $out=  'رسید دیجیتالی تهی است'; break;
			case '-8': $out=  'طول ورودی های بیش از حد مجاز است'; break;
			case '-9': $out=  'وجود کاراکترهای غیر مجاز در مبلغ برگشتی'; break;
			case '-10': $out=  'رسید دیجیتالی حاوی کاراکترهای غیر مجاز است'; break;
			case '-11': $out=  'طول ورودی های کمتر از حد مجاز است'; break;
			case '-12': $out=  'مبلغ برگشت منفی است'; break;
			case '-13': $out=  'مبلغ برگشتی برای برگشت جزیی بیش از مبلغ برگشت نخورده رسید دیجیتالی است'; break;
			case '-14': $out=  'چنین تراکنشی تعریف نشده است'; break;
			case '-15': $out=  'مبلغ برگشتی به صورت اعشاری داده شده است'; break;
			case '-16': $out=  'خطای داخلی سیستم'; break;
			case '-17': $out=  'برگشت زدن جزیی تراکنشی که با کارت بانکی غیر از بانک سامان انجام پذیرفته است'; break;
			case '-18': $out=  'IP Adderess‌ فروشنده نامعتبر'; break;
			case 'Canceled By User': $out=  'تراکنش توسط خریدار کنسل شده است'; break;
			case 'Invalid Amount': $out=  'مبلغ سند برگشتی از مبلغ تراکنش اصلی بیشتر است'; break;
			case 'Invalid Transaction': $out=  'درخواست برگشت یک تراکنش رسیده است . در حالی که تراکنش اصلی پیدا نمی شود.'; break;
			case 'Invalid Card Number': $out=  'شماره کارت اشتباه است'; break;
			case 'No Such Issuer': $out=  'چنین صادر کننده کارتی وجود ندارد'; break;
			case 'Expired Card Pick Up': $out=  'از تاریخ انقضا کارت گذشته است و کارت دیگر معتبر نیست'; break;
			case 'Allowable PIN Tries Exceeded Pick Up': $out=  'رمز (PIN) کارت ۳ بار اشتباه وارد شده است در نتیجه کارت غیر فعال خواهد شد.'; break;
			case 'Incorrect PIN': $out=  'خریدار رمز کارت (PIN) را اشتباه وارده کرده است'; break;
			case 'Exceeds Withdrawal Amount Limit': $out=  'مبلغ بیش از سقف برداشت می باشد'; break;
			case 'Transaction Cannot Be Completed': $out=  'تراکنش تایید شده است ولی امکان سند خوردن وجود ندارد'; break;
			case 'Response Received Too Late': $out=  'تراکنش در شبکه بانکی  timeout خورده است'; break;
			case 'Suspected Fraud Pick Up': $out=  'خریدار فیلد CVV2 یا تاریخ انقضا را اشتباه وارد کرده و یا اصلا وارد نکرده است.'; break;
			case 'No Sufficient Funds': $out=  'موجودی به اندازه کافی در حساب وجود ندارد'; break;
			case 'Issuer Down Slm': $out=  'سیستم کارت بانک صادر کننده در وضعیت عملیاتی نیست'; break;
			case 'TME Error': $out=  'کلیه خطاهای دیگر بانکی که باعث ایجاد چنین خطایی می گردد'; break;
			case '1': $out=  'تراکنش با موفقیت انجام شده است'; break;
			case 'error': $out ='خطا غیر منتظره رخ داده است';break;
			case 'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
			case 'notff': $out = 'سفارش پیدا نشد';break;
			default: $out ='خطا غیر منتظره رخ داده است';break;
		}
		return $out;
	}
}

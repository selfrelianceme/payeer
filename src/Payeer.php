<?php

namespace Selfreliance\Payeer;

use Illuminate\Http\Request;
use Config;
use Route;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Selfreliance\Payeer\Exceptions\PayeerException;

use Selfreliance\Payeer\Events\PayeerPaymentIncome;
use Selfreliance\Payeer\Events\PayeerPaymentCancel;

use Selfreliance\Payeer\PayeerInterface;
use Log;
use Selfreliance\Payeer\Service\CPayeer;

class Payeer implements PayeerInterface
{
	use ValidatesRequests;

	protected $memo;
	public function memo($memo){
		$this->memo = $memo;
		return $this;
	}

	public function __construct(){
		
	}

	protected $cpayeer;
	public function connect(){
		$this->cpayeer = new CPayeer(Config::get('payeer.payeer_wallet'), Config::get('payeer.api_id'), Config::get('payeer.api_key'));
	}

	function balance($unit = "USD"){
		$this->connect();
		if ($this->cpayeer->isAuth()){
			$arTransfer = $this->cpayeer->getBalance();
			return $arTransfer['balance'][$unit]['DOSTUPNO'];
		}else{
			throw new \Exception('Error get balance');
		}
	}

	function form($payment_id, $sum, $units='USD'){
		$sum = number_format($sum, 2, ".", "");
			
		$m_desc = base64_encode($this->memo);
		
		$arHash = array(Config::get('payeer.shop_id'), $payment_id, $sum, $units, $m_desc, Config::get('payeer.shop_secret_key'));
		$sign = strtoupper(hash('sha256', implode(":", $arHash)));

		$form_data = array(
			"m_shop"	=>	Config::get('payeer.shop_id'),
			"m_orderid"	=>	$payment_id,
			"m_amount"	=>	$sum,
			"m_curr"	=>	$units,
			"m_desc"	=>	$m_desc,
			"m_sign"	=>	$sign
		);
		ob_start();
			echo '<form method="GET" id="FORM_pay_ok" action="//payeer.com/api/merchant/m.php">';
			foreach ($form_data as $key => $value) {
				echo '<input type="hidden" name="'.$key.'" value="'.$value.'">';
			}
			echo '<input type="submit" style="width:0;height:0;border:0px; background:none;" class="content__login-submit submit_pay_ok" name="m_process" value="">';
			echo '</form>';
		$content = ob_get_contents();
			ob_end_clean();
			return $content;
	}

	function validateIPNRequest(Request $request) {
        return $this->check_transaction($request->all(), $request->server(), $request->headers);
    }

    function check_transaction(array $request, array $server, $headers = []){
		Log::info('Payeer IPN', [
			'request' => $request,
			'headers' => $headers,
			'server'  => array_intersect_key($server, [
				'PHP_AUTH_USER', 'PHP_AUTH_PW'
			])
		]);
		if(!array_key_exists('m_orderid', $request)){
			$request['m_orderid'] = 0;
		}
		try{
			$is_complete = $this->validateIPN($request, $server);
			if($is_complete){
				$PassData                     = new \stdClass();
				$PassData->amount             = $request['m_amount'];
				$PassData->payment_id         = $request['m_orderid'];
				$PassData->transaction        = $request['m_operation_id'];
				$PassData->add_info           = [
					"full_data_ipn" => json_encode($request)
				];
				event(new PayeerPaymentIncome($PassData));
				return \Response::make($request['m_orderid']."|success", "200");
			}
		}catch(PayeerException $e){
			Log::error('Payeer IPN', [
				'message' => $e->getMessage()
			]);

			return \Response::make($request['m_orderid']."|error", "422");
		}

		return \Response::make($request['m_orderid']."|error", "200");
	}

    function validateIPN(array $post_data, array $server_data){
		if(!array_key_exists('m_operation_id', $post_data)){
			throw new PayeerException("Need m operation id");
		}

		if(!array_key_exists('m_orderid', $post_data)){
			throw new PayeerException("Need m_orderid");
		}
		
		if(!isset($post_data["m_sign"])){
			throw new PayeerException("Need m_sign");
		}

		$arHash = array($post_data['m_operation_id'],
			$post_data['m_operation_ps'],
			$post_data['m_operation_date'],
			$post_data['m_operation_pay_date'],
			$post_data['m_shop'],
			$post_data['m_orderid'],
			$post_data['m_amount'],
			$post_data['m_curr'],
			$post_data['m_desc'],
			$post_data['m_status'],
			Config::get('payeer.shop_secret_key'));
		$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
		
		if($post_data["m_sign"] != $sign_hash){
			throw new PayeerException("m_sign dont confirm");
		}

		if($post_data['m_status'] != "success"){
			throw new PayeerException("Status not success");
		}

		return true;
	}

	function send_money($payment_id, $amount, $address, $currency){
		$this->connect();
		if ($this->cpayeer->isAuth()){
			$arTransfer = $this->cpayeer->transfer(array(
				'curIn'		=>	$currency,
				'sum'		=>	$amount,
				'curOut'	=>	$currency,
				'to'		=>	strtoupper(trim($address)),
				'comment'	=>	Config('app.name').", ID:".$payment_id
			));
			
			if(!empty($arTransfer["historyId"])){
				$PassData              = new \stdClass();
				$PassData->transaction = $arTransfer["historyId"];
				$PassData->sending     = true;
				$PassData->add_info    = [
					"full_data" => $arTransfer
				];
				return $PassData;
			}else{
				throw new \Exception($arTransfer['errors'][0]);
			}
		}else{
			throw new \Exception('Error auth');
		}
	}

	function cancel_payment(Request $request){
		$PassData     = new \stdClass();
		$PassData->id = $request->input('m_orderid');
		
		event(new PayeerPaymentCancel($PassData));

		return redirect(env('PERSONAL_LINK_CAB'));
	}
}
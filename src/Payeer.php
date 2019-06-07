<?php

namespace Selfreliance\Payeer;

use Illuminate\Http\Request;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Selfreliance\Payeer\Exceptions\PayeerException;

use Selfreliance\Payeer\Events\PayeerPaymentIncome;
use Selfreliance\Payeer\Events\PayeerPaymentCancel;

use Selfreliance\Payeer\PayeerInterface;
use App\Models\MerchantPosts;
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

    /**
     * @var \Selfreliance\Payeer\Service\CPayeer
     */
	protected $cpayeer;
	public function connect(){
		$this->cpayeer = new CPayeer(config('payeer.payeer_wallet'), config('payeer.api_id'), config('payeer.api_key'));
	}

    /**
     * @param string $unit
     * @return float
     * @throws \Exception
     */
    function balance($unit = "USD"){
		$this->connect();
		if ($this->cpayeer->isAuth()){
			$arTransfer = $this->cpayeer->getBalance();
			return $arTransfer['balance'][$unit]['DOSTUPNO'];
		}else{
			throw new \Exception('Error get balance');
		}
	}

    /**
     * @param int $payment_id
     * @param float $sum
     * @param string $units
     * @return test|string
     */
    function form($payment_id, $sum, $units='USD'){
		$sum = number_format($sum, 2, ".", "");
			
		$m_desc = base64_encode($this->memo);
		
		$arHash = array(config('payeer.shop_id'), $payment_id, $sum, $units, $m_desc, config('payeer.shop_secret_key'));
		$sign = strtoupper(hash('sha256', implode(":", $arHash)));

		$form_data = array(
			"m_shop"	=>	config('payeer.shop_id'),
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

    /**
     * @param Request $request
     * @return bool
     */
    function validateIPNRequest(Request $request) {
        return $this->check_transaction($request->all(), $request->server(), $request->headers);
    }

    /**
     * @param array $request
     * @param array $server
     * @param array $headers
     * @return bool
     */
    function check_transaction(array $request, array $server, $headers = []){
		MerchantPosts::create([
			'type'      => 'Payeer',
			'ip'        => real_ip(),
			'post_data' => $request
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
					"full_data_ipn" => $request
				];
				event(new PayeerPaymentIncome($PassData));
				return \Response::make($request['m_orderid']."|success", "200");
			}
		}catch(PayeerException $e){
			MerchantPosts::create([
				'type'      => 'Payeer_Error',
				'ip'        => real_ip(),
				'post_data' => ['request' => $request, 'message' => $e->getMessage()],
			]);

			return \Response::make($request['m_orderid']."|error", "200");
		}

		return \Response::make($request['m_orderid']."|error", "200");
	}

    /**
     * @param array $post_data
     * @param array $server_data
     * @return bool
     * @throws PayeerException
     */
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
			config('payeer.shop_secret_key'));
		$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
		
		if($post_data["m_sign"] != $sign_hash){
			throw new PayeerException("m_sign dont confirm");
		}

		if($post_data['m_status'] != "success"){
			throw new PayeerException("Status not success");
		}

		return true;
	}

    /**
     * @param int $payment_id
     * @param float $amount
     * @param $address
     * @param string $currency
     * @return bool|\stdClass
     * @throws \Exception
     */
    function send_money($payment_id, $amount, $address, $currency){
		$this->connect();
		if ($this->cpayeer->isAuth()){
			$arTransfer = $this->cpayeer->transfer(array(
				'curIn'		=>	$currency,
				'sum'		=>	$amount,
				'curOut'	=>	$currency,
				'to'		=>	strtoupper(trim($address)),
				'comment'	=>	config('app.name').", ID:".$payment_id
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

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    function cancel_payment(Request $request){
		$PassData     = new \stdClass();
		$PassData->id = $request->input('m_orderid');
		
		event(new PayeerPaymentCancel($PassData));

        return redirect(config('perfectmoney.to_account'));
	}
}
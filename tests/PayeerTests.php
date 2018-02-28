<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Selfreliance\Payeer\Payeer;
use Config;
use Carbon\Carbon;
class PayeerHandlerPayments extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBalance()
    {
    	$payeer = new Payeer();
    	$balance = $payeer->balance('USD');
    	$this->assertLessThanOrEqual($balance, 0);
    }

    public function testForm(){
    	$payeer = new Payeer();
    	$form = $payeer->form(1, 10, 'USD');
    	$this->assertSame(gettype($form), 'string');
    }

    public function testIPNRequestUnCorrect(){
    	$payeer = new Payeer();
    	$data = [

    	];
    	$result = $payeer->check_transaction($data, [], []);
    	$this->assertSame($result->original, '0|error');
    	$this->assertEquals(422, $result->status());
    }

    public function testIPNRequest(){
        $payeer = new Payeer();
        $generate_hash = 
        $data = [
            'm_amount'             => 1,
            'm_orderid'            => 0,
            'm_operation_id'       => 0,
            'm_sign'               => 1,
            'm_operation_ps'       => 1,
            'm_operation_date'     => 1,
            'm_operation_pay_date' => 'U1234',
            'm_shop'               => 'Now()',
            'm_curr'               => 1,
            'm_desc'               => 1,
            'm_status'             => 'success',
        ];

        $arHash = array($data['m_operation_id'],
            $data['m_operation_ps'],
            $data['m_operation_date'],
            $data['m_operation_pay_date'],
            $data['m_shop'],
            $data['m_orderid'],
            $data['m_amount'],
            $data['m_curr'],
            $data['m_desc'],
            $data['m_status'],
            Config::get('payeer.shop_secret_key'));
        $sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
        
        $data['m_sign'] = $sign_hash;

        $result = $payeer->check_transaction($data, [], []);
        $this->assertSame($result->original, $data['m_orderid'].'|success');
        $this->assertEquals(200, $result->status());
    }

    // public function testReatCreateData(){
    // 	$payeer = new Payeer();
    // 	$generate_hash = 
    // 	$data = [
    //         'm_amount'             => 30,
    //         'm_orderid'            => 8884,
    //         'm_operation_id'       => 8884,
    //         'm_sign'               => null,
    //         'm_operation_ps'       => 0,
    //         'm_operation_date'     => Carbon::now(),
    //         'm_operation_pay_date' => Carbon::now(),
    //         'm_shop'               => Config::get('payeer.shop_id'),
    //         'm_curr'               => 'USD',
    //         'm_desc'               => 'tranaction',
    //         'm_status'             => 'success',
    // 	];

    // 	$arHash = array($data['m_operation_id'],
    //         $data['m_operation_ps'],
    //         $data['m_operation_date'],
    //         $data['m_operation_pay_date'],
    //         $data['m_shop'],
    //         $data['m_orderid'],
    //         $data['m_amount'],
    //         $data['m_curr'],
    //         $data['m_desc'],
    //         $data['m_status'],
    //         Config::get('payeer.shop_secret_key'));
    //     $sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
        
    //     $data['m_sign'] = $sign_hash;

    // 	$result = $payeer->check_transaction($data, [], []);
    // 	$this->assertSame($result->original, $data['m_orderid'].'|success');
    // 	$this->assertEquals(200, $result->status());
    // }
}

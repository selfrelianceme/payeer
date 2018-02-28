<?php

Route::any('payeer/cancel', 'Selfreliance\Payeer\Payeer@cancel_payment')->name('payeer.cancel');
Route::post('payeer/confirm', 'Selfreliance\Payeer\Payeer@validateIPNRequest')->name('payeer.confirm');
Route::any('payeer/personal', function(){
	return redirect(env('PERSONAL_LINK_CAB'));
})->name('payeer.after_pay_to_cab');
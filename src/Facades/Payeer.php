<?php 
namespace Selfreliance\Payeer\Facades;  

use Illuminate\Support\Facades\Facade;  

use Selfreliance\Payeer\Payeer as PayeerClass;

class Payeer extends Facade 
{
	protected static function getFacadeAccessor() { 
		return PayeerClass::class;   
	}
}

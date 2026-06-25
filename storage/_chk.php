$c=new \App\Http\Controllers\Orders\OrderController();
auth()->login(\App\Models\User::where('user_type','GF')->first());
$vd=(new ReflectionMethod($c,'payable'))->invoke($c);
$err='NINGUNO';
set_error_handler(function($n,$s) use (&$err){ $err=$s; });
$h=\Illuminate\Support\Facades\View::make($vd->getName(),$vd->getData())->render();
restore_error_handler();
echo 'radios_cuenta='.substr_count($h,'name="dep-account" value').PHP_EOL;
echo 'warning='.$err.PHP_EOL;

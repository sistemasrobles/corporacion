<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Orders\OrderController;

// --- Autenticación propia (sesión nativa de Laravel) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', function () {
        $stats = [
            'orders'    => \App\Models\Order::count(),
            'suppliers' => \App\Models\Supplier::count(),
            'users'     => \App\Models\User::count(),
        ];
        return view('dashboard', compact('stats'));
    })->name('dashboard');



    // --- Órdenes (ruta padre) ---
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/view', [OrderController::class, 'index'])->name('view');             // Mis Órdenes
        Route::get('/view/history', [OrderController::class, 'history'])->name('history'); // Órdenes Históricas

        // --- Cuentas por Pagar (ciclo de abonos) ---
        Route::get('/payable', [OrderController::class, 'payable'])->name('payable');
        Route::get('/payments', [OrderController::class, 'paymentsHistory'])->name('payments'); // Histórico de Pagos
        Route::post('/abono/{quota}/deposit', [OrderController::class, 'abonoDeposit'])->name('abono.deposit');
        Route::post('/abono/{quota}/constancia', [OrderController::class, 'abonoConstancia'])->name('abono.constancia');
        Route::post('/abono/{quota}/verify', [OrderController::class, 'abonoVerify'])->name('abono.verify');
        Route::post('/abono/{quota}/observe', [OrderController::class, 'abonoObserve'])->name('abono.observe');
        Route::get('/create', [OrderController::class, 'create'])->name('create');
        Route::post('/store', [OrderController::class, 'store'])->name('store');
        Route::get('/{order}/edit', [OrderController::class, 'edit'])->name('edit');
        Route::post('/{order}/update', [OrderController::class, 'update'])->name('update');
        Route::get('/{order}/show', [OrderController::class, 'show'])->name('show');
        Route::post('/{order}/approve', [OrderController::class, 'approve'])->name('approve');
        Route::post('/{order}/observe', [OrderController::class, 'observe'])->name('observe');
        Route::post('/{order}/reject', [OrderController::class, 'reject'])->name('reject');
        Route::post('/{order}/code', [OrderController::class, 'code'])->name('code');
        Route::get('/cost-centers/{area}', [OrderController::class, 'costCenters'])->name('costcenters');
        Route::get('/timeline/{order}', [OrderController::class, 'timeline'])->name('timeline');
        Route::get('/{order}/summary', [OrderController::class, 'summary'])->name('summary');
        Route::get('/{order}/comprobantes', [OrderController::class, 'comprobantes'])->name('comprobantes');
        Route::post('/{order}/comprobante', [OrderController::class, 'uploadComprobante'])->name('comprobante.upload');
        Route::post('/{order}/comprobante/{file}/delete', [OrderController::class, 'deleteComprobante'])->name('comprobante.delete');
        // Código de Registro por documento de pago (UC1)
        Route::get('/{order}/vistacontable', [OrderController::class, 'vistaContable'])->name('vistacontable');
        Route::post('/{order}/registro/{file}', [OrderController::class, 'saveRegistrationCode'])->name('registro.save');
        Route::post('/{order}/registro-advance', [OrderController::class, 'advanceRegistro'])->name('registro.advance');
        Route::get('/categories/{format}', [OrderController::class, 'categories'])->name('categories');
        Route::get('/supplier/search', [OrderController::class, 'searchSupplier'])->name('supplier.search');
        Route::post('/supplier', [OrderController::class, 'storeSupplier'])->name('supplier.store');

    });
});





// La raíz entra al sistema nuevo. Si no hay sesión, el middleware 'auth'
// del dashboard redirige a /login automáticamente.
// (El panel Filament /erp queda como histórico hasta migrar todo.)
Route::get('/', fn () => redirect()->route('dashboard'));

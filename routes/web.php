<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Supplier\SupplierController;
use App\Http\Controllers\Refund\RefundController;

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
        Route::get('/rows', [OrderController::class, 'ordersRows'])->name('rows');         // Filas frescas (botón Recargar)
        Route::post('/bulk-approve', [OrderController::class, 'bulkApprove'])->name('bulk-approve'); // GA: aprobación masiva
        Route::get('/view/history', [OrderController::class, 'history'])->name('history'); // Órdenes Históricas
        Route::get('/view/history/rows', [OrderController::class, 'historyRows'])->name('history-rows'); // Filas frescas (botón Recargar)

        // --- Cuentas por Pagar (ciclo de abonos) ---
        Route::get('/payable', [OrderController::class, 'payable'])->name('payable');
        Route::get('/payable/rows', [OrderController::class, 'payableRows'])->name('payable.rows');     // Filas frescas (Recargar)
        Route::get('/payments', [OrderController::class, 'paymentsHistory'])->name('payments'); // Histórico de Pagos
        Route::get('/payments/rows', [OrderController::class, 'paymentsRows'])->name('payments.rows');   // Filas frescas (Recargar)
        Route::post('/abono/{quota}/deposit', [OrderController::class, 'abonoDeposit'])->name('abono.deposit');
        Route::post('/abono/{quota}/constancia', [OrderController::class, 'abonoConstancia'])->name('abono.constancia');
        Route::post('/abono/{quota}/verify', [OrderController::class, 'abonoVerify'])->name('abono.verify');
        Route::post('/abono/{quota}/observe', [OrderController::class, 'abonoObserve'])->name('abono.observe');
        Route::post('/abono/{quota}/bankcode', [OrderController::class, 'abonoBankCode'])->name('abono.bankcode');
        Route::post('/{order}/abono-bankcode-advance', [OrderController::class, 'advanceAbonoBankCode'])->name('abono.bankcode.advance');
        Route::post('/{order}/edit-codigo-registro/{file}', [OrderController::class, 'editRegistrationCode'])->name('edit.codigo.registro');
        Route::post('/{order}/edit-codigo-banco/{quota}', [OrderController::class, 'editBankCode'])->name('edit.codigo.banco');
        Route::get('/create', [OrderController::class, 'create'])->name('create');
        Route::post('/store', [OrderController::class, 'store'])->name('store');
        Route::post('/store-ja', [OrderController::class, 'storeJa'])->name('store-ja'); // Solicitud liviana del JA
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
        // Documentos anexos (AA en [102])
        Route::get('/{order}/anexos', [OrderController::class, 'anexos'])->name('anexos');
        Route::post('/{order}/anexo', [OrderController::class, 'uploadAnexo'])->name('anexo.upload');
        Route::post('/{order}/anexo/{file}/delete', [OrderController::class, 'deleteAnexo'])->name('anexo.delete');
        // Código de Registro por documento de pago (UC1)
        Route::get('/{order}/vistacontable', [OrderController::class, 'vistaContable'])->name('vistacontable');
        Route::post('/{order}/registro/{file}', [OrderController::class, 'saveRegistrationCode'])->name('registro.save');
        Route::post('/{order}/registro-advance', [OrderController::class, 'advanceRegistro'])->name('registro.advance');
        Route::get('/categories/{format}', [OrderController::class, 'categories'])->name('categories');
        Route::get('/supplier/search', [OrderController::class, 'searchSupplier'])->name('supplier.search');
        Route::post('/supplier', [OrderController::class, 'storeSupplier'])->name('supplier.store');

    });

    // --- Órdenes de Requerimientos (módulo propio, independiente de Órdenes) ---
    Route::prefix('requirements')->name('requirements.')->group(function () {
        Route::get('/', [RefundController::class, 'index'])->name('index');
        Route::get('/rows', [RefundController::class, 'rows'])->name('rows');       // filas frescas (Recargar)
        Route::get('/create', [RefundController::class, 'create'])->name('create'); // formulario AA
        Route::post('/store', [RefundController::class, 'store'])->name('store');
        Route::get('/cost-centers/{area}', [RefundController::class, 'costCenters'])->name('costcenters');
        Route::get('/beneficiary/search', [RefundController::class, 'searchBeneficiary'])->name('beneficiary.search');
        Route::post('/beneficiary', [RefundController::class, 'storeBeneficiary'])->name('beneficiary.store');
        // Edición del AA (orden observada) + detalle + acciones del GA.
        Route::get('/{refund}/edit', [RefundController::class, 'edit'])->name('edit');
        Route::post('/{refund}/update', [RefundController::class, 'update'])->name('update');
        // El {refund} de un solo segmento va al final para no chocar con las rutas fijas de arriba.
        Route::get('/{refund}', [RefundController::class, 'show'])->name('show');
        Route::post('/{refund}/approve', [RefundController::class, 'approve'])->name('approve');
        Route::post('/{refund}/observe', [RefundController::class, 'observe'])->name('observe');
        Route::post('/{refund}/reject', [RefundController::class, 'reject'])->name('reject');
        // Abono (GF) y constancia (GF/AF)
        Route::post('/{refund}/abono', [RefundController::class, 'abono'])->name('abono');
        Route::post('/{refund}/constancia', [RefundController::class, 'constancia'])->name('constancia');
        // Rendición del AA (comprobantes de gasto)
        Route::get('/{refund}/rendicion', [RefundController::class, 'rendicion'])->name('rendicion');
        Route::post('/{refund}/comprobante', [RefundController::class, 'uploadComprobante'])->name('comprobante.upload');
        Route::post('/{refund}/comprobante/{file}/delete', [RefundController::class, 'deleteComprobante'])->name('comprobante.delete');
        Route::post('/{refund}/rendir', [RefundController::class, 'rendir'])->name('rendir');
        // Liquidación y cierre (Fase 5)
        Route::post('/{refund}/reembolso', [RefundController::class, 'reembolso'])->name('reembolso');         // GF (faltante)
        Route::post('/{refund}/devolucion', [RefundController::class, 'devolucion'])->name('devolucion');      // AA (sobrante)
        Route::post('/{refund}/conforme', [RefundController::class, 'conforme'])->name('conforme');            // AF
        Route::post('/{refund}/observe-rendicion', [RefundController::class, 'observeRendicion'])->name('observe-rendicion'); // AF
        Route::post('/{refund}/cerrar', [RefundController::class, 'cerrar'])->name('cerrar');                  // UC1
        Route::post('/{refund}/observe-uc1', [RefundController::class, 'observeUc1'])->name('observe-uc1');    // UC1 observa
    });

    // --- Proveedores (módulo propio, independiente de Órdenes) ---
    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::get('/rows', [SupplierController::class, 'rows'])->name('rows');     // filas frescas (Recargar)
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::post('/{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::post('/{supplier}/toggle', [SupplierController::class, 'toggleActive'])->name('toggle');
    });
});





// La raíz entra al sistema nuevo. Si no hay sesión, el middleware 'auth'
// del dashboard redirige a /login automáticamente.
// (El panel Filament /erp queda como histórico hasta migrar todo.)
Route::get('/', fn () => redirect()->route('dashboard'));

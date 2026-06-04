<?php

use Illuminate\Support\Facades\Route;

// Todo el sistema vive en el panel Filament (/erp)
Route::get('/', fn () => redirect('/erp'));

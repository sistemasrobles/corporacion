<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'orders'    => Order::count(),
            'suppliers' => Supplier::count(),
            'users'     => User::count(),
        ];

        return view('dashboard.index', compact('stats'));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PaymentSchedule;

class Order extends Model
{
    protected $fillable = [
        'company_id', 'code', 'status', 'title', 'type_id', 'format_id',
        'user_responsible', 'payment_schedule_id',
        'created_by', 'updated_by',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    public function files()
    {
        return $this->hasMany(OrderFile::class, 'order_id');
    }

    public function detail()
    {
        return $this->hasOne(OrderDetail::class, 'order_id');
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public function history()
    {
        return $this->hasMany(OrderHistory::class, 'order_id');
    }

    public function products()
    {
        return $this->hasMany(OrderProduct::class, 'order_id');
    }

    public function responsible()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_responsible');
    }

    public function quotas()
    {
        return $this->hasMany(OrderQuota::class, 'order_id');
    }

    public function paymentSchedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }
}

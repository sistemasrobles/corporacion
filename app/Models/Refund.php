<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $table = 'refund';

    protected $fillable = [
        'code', 'status',
        'company_id', 'area_id', 'cost_center_id', 'category_id', 'currency', 'title', 'purpose', 'needed_date',
        'beneficiary_id', 'beneficiary_account_id', 'beneficiary_account', 'beneficiary_bank', 'beneficiary_name', 'beneficiary_doc',
        'requested_amount', 'approved_amount', 'paid_amount', 'rendered_amount', 'difference_amount',
        'created_by', 'responsible_id', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason',
        'closed_by', 'closed_at', 'updated_by', 'uc1_subsanacion',
    ];

    protected $casts = [
        'requested_amount'  => 'decimal:2',
        'approved_amount'   => 'decimal:2',
        'paid_amount'       => 'decimal:2',
        'rendered_amount'   => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'needed_date'       => 'date',
        'uc1_subsanacion'   => 'boolean',
        'approved_at'       => 'datetime',
        'rejected_at'       => 'datetime',
        'closed_at'         => 'datetime',
    ];

    public function statusInfo()
    {
        return $this->belongsTo(RefundStatus::class, 'status');
    }

    public function category()
    {
        return $this->belongsTo(RefundCategory::class, 'category_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class, 'beneficiary_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function details()
    {
        return $this->hasMany(RefundDetail::class, 'refund_id');
    }

    public function files()
    {
        return $this->hasMany(RefundFile::class, 'refund_id');
    }

    public function payments()
    {
        return $this->hasMany(RefundPayment::class, 'refund_id');
    }

    public function observations()
    {
        return $this->hasMany(RefundObservation::class, 'refund_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(RefundStatusLog::class, 'refund_id');
    }
}
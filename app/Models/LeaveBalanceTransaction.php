<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceTransaction extends Model
{
    use HasFactory;

    // This table is append-only (ledger). No updates or deletes allowed.
    public $timestamps = false;

    protected $fillable = [
        'leave_balance_id',
        'leave_request_id',
        'type',
        'days',
        'note',
        'created_by',
    ];

    protected $casts = [
        'type'       => TransactionType::class,
        'days'       => 'integer',
        'created_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Boot: set created_at manually since $timestamps = false
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_at = now();
        });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isCredit(): bool
    {
        return $this->type->isCredit();
    }

    public function isDebit(): bool
    {
        return $this->type->isDebit();
    }
}

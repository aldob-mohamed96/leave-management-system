<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    protected $fillable = [
        'number',
        'employee_id',
        'organization_id',
        'leave_type_id',
        'substitute_employee_id',
        'start_date',
        'end_date',
        'days',
        'written_at',
        'reason',
        'balance_entitled',
        'balance_used',
        'balance_remaining',
        'status',
        'current_stage',
        'rejection_reason',
        'created_by',
        'submitted_at',
        'decided_at',
        'decided_by',
    ];

    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'written_at'        => 'date',
        'days'              => 'decimal:1',
        'balance_entitled'  => 'decimal:1',
        'balance_used'      => 'decimal:1',
        'balance_remaining' => 'decimal:1',
        'status'            => LeaveStatus::class,
        'submitted_at'      => 'datetime',
        'decided_at'        => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Activity Log
    // -------------------------------------------------------------------------

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName) {
                $employee = $this->employee?->full_name ?? "#{$this->employee_id}";
                return match($eventName) {
                    'created' => "تم إنشاء طلب إجازة للموظف {$employee} - رقم {$this->number}",
                    'updated' => "تم تحديث طلب الإجازة رقم {$this->number} - الحالة: {$this->status->label()}",
                    'deleted' => "تم حذف طلب الإجازة رقم {$this->number}",
                    default   => $eventName,
                };
            });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function substituteEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(LeaveRequestStep::class)->orderBy('step_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveRequestAttachment::class);
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(LeaveBalanceTransaction::class);
    }

    // -------------------------------------------------------------------------
    // Computed Attributes
    // -------------------------------------------------------------------------

    public function getCanCancelAttribute(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function getCanEditAttribute(): bool
    {
        return $this->status->canBeEdited();
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === LeaveStatus::APPROVED;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeWithStatus($query, LeaveStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            LeaveStatus::SUBMITTED->value,
            LeaveStatus::IN_REVIEW->value,
        ]);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', LeaveStatus::APPROVED->value);
    }

    /**
     * Requests that overlap with the given date range for a specific employee.
     * Used for overlap validation in LeaveRequestService.
     */
    public function scopeOverlapping($query, int $employeeId, string $startDate, string $endDate, ?int $excludeId = null)
    {
        return $query->where('employee_id', $employeeId)
            ->whereNotIn('status', [
                LeaveStatus::REJECTED->value,
                LeaveStatus::CANCELLED->value,
            ])
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId));
    }

    /** Requests with no action taken for more than X days */
    public function scopeOverdue($query, int $days = 3)
    {
        return $query->whereIn('status', [
            LeaveStatus::SUBMITTED->value,
            LeaveStatus::IN_REVIEW->value,
        ])->where(function ($q) use ($days) {
            $q->where('submitted_at', '<', now()->subDays($days))
              ->orWhereNull('submitted_at');
        });
    }
}

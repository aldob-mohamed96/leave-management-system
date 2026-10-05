<?php

namespace App\Models;

use App\Enums\EntitlementGrade;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Employee extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    protected $fillable = [
        'organization_id',
        'user_id',
        'employee_code',
        'full_name',
        'job_title',
        'grade',
        'entitlement_grade',
        'birth_date',
        'hire_date',
        'work_start_date',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'birth_date'        => 'date',
        'hire_date'         => 'date',
        'work_start_date'   => 'date',
        'is_active'         => 'boolean',
        'entitlement_grade' => EntitlementGrade::class,
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
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => "تم تسجيل الموظف: {$this->full_name}",
                'updated' => "تم تحديث بيانات الموظف: {$this->full_name}",
                'deleted' => "تم حذف الموظف: {$this->full_name}",
                default   => $eventName,
            });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** System user linked to this employee (optional) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the leave balance for a specific type and year.
     * Returns null if no balance record exists yet.
     */
    public function balanceFor(int $leaveTypeId, ?int $year = null): ?LeaveBalance
    {
        return $this->leaveBalances()
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year ?? now()->year)
            ->first();
    }

    /** Check if employee has any approved leave on a given date */
    public function isOnLeaveOn(\Carbon\Carbon $date): bool
    {
        return $this->leaveRequests()
            ->where('status', \App\Enums\LeaveStatus::APPROVED->value)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Calculate yearly entitlement for regular leave based on entitlement_grade and age.
     * Falls back to 28 days if grade is not set.
     * When a grade IS assigned and the employee is over 50, the age rule overrides the grade.
     */
    public function regularLeaveEntitlement(): int
    {
        // No grade assigned → default 28 days (age rule does not apply)
        if ($this->entitlement_grade === null) {
            return 28;
        }

        // Grade assigned + employee over 50 → override to 50 days
        if ($this->birth_date && $this->birth_date->age >= 50) {
            return EntitlementGrade::ADMIN_OVER_50->yearlyDays();
        }

        return $this->entitlement_grade->yearlyDays();
    }
}

<?php

namespace App\Models;

use App\Enums\StepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeaveRequestStep extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Always eager-load the actor — Filament/activity-log touch actedBy
     * frequently, and Model::shouldBeStrict() forbids lazy loading.
     *
     * @var list<string>
     */
    protected $with = ['actedBy'];

    protected $fillable = [
        'leave_request_id',
        'step_order',
        'stage',
        'status',
        'acted_by',
        'acted_at',
        'note',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'status'     => StepStatus::class,
        'acted_at'   => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Activity Log
    // -------------------------------------------------------------------------

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                // Avoid lazy-loading under Model::shouldBeStrict() — resolve via
                // already-loaded relations or a direct attribute/query lookup.
                $actor = 'النظام';
                if ($this->relationLoaded('actedBy')) {
                    $actor = $this->actedBy?->name ?? 'النظام';
                } elseif ($this->acted_by) {
                    $actor = User::query()->whereKey($this->acted_by)->value('name') ?? 'النظام';
                }

                $requestNumber = "#{$this->leave_request_id}";
                if ($this->relationLoaded('leaveRequest')) {
                    $requestNumber = $this->leaveRequest?->number ?? $requestNumber;
                } elseif ($this->leave_request_id) {
                    $requestNumber = LeaveRequest::query()
                        ->whereKey($this->leave_request_id)
                        ->value('number') ?? $requestNumber;
                }

                $decision = $this->status?->label() ?? '';

                return "قرار المرحلة {$this->stage} على طلب {$requestNumber}: {$decision} بواسطة {$actor}";
            });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === StepStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === StepStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === StepStatus::REJECTED;
    }
}

<?php

namespace App\Models;

use App\Enums\ApprovalRule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkflowConfiguration extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'organization_id',
        'stage_name',
        'step_order',
        'approval_rule',
        'required_role',
        'label',
        'is_active',
    ];

    protected $casts = [
        'approval_rule' => ApprovalRule::class,
        'step_order'    => 'integer',
        'is_active'     => 'boolean',
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
                $org = $this->organization?->name ?? "#{$this->organization_id}";
                return "تم تحديث إعداد مسار الاعتماد للمؤسسة {$org} - المرحلة: {$this->stage_name}";
            });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('step_order');
    }
}

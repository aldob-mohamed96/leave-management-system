<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Organization extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'code',
        'path',
        'depth',
        'is_active',
    ];

    protected $casts = [
        'type'      => OrganizationType::class,
        'depth'     => 'integer',
        'is_active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Activity Log
    // -------------------------------------------------------------------------

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'created' => "تم إنشاء المؤسسة: {$this->name}",
                'updated' => "تم تحديث المؤسسة: {$this->name}",
                'deleted' => "تم حذف المؤسسة: {$this->name}",
                default   => $eventName,
            });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    /** Recursively eager-load all descendants */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function workflowConfigurations(): HasMany
    {
        return $this->hasMany(WorkflowConfiguration::class)->orderBy('step_order');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, OrganizationType $type)
    {
        return $query->where('type', $type->value);
    }

    /**
     * Returns all organizations within this org's subtree (including self).
     * Uses the materialized path for a single indexed query.
     */
    public function scopeDescendantsAndSelf($query)
    {
        return $query->where('path', 'like', $this->path . '%');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the materialized path for this node.
     * Called by the observer before saving when parent changes.
     */
    public function buildPath(): string
    {
        if ($this->parent_id === null) {
            return "/{$this->id}/";
        }

        $parent = Organization::withoutGlobalScopes()->find($this->parent_id);

        return $parent ? $parent->path . "{$this->id}/" : "/{$this->id}/";
    }

    public function isDirectorate(): bool
    {
        return $this->type === OrganizationType::DIRECTORATE;
    }

    public function isAdministration(): bool
    {
        return $this->type === OrganizationType::ADMINISTRATION;
    }

    public function isSchool(): bool
    {
        return $this->type === OrganizationType::SCHOOL;
    }

    /** IDs of this org plus all its descendants — used for permission scoping */
    public function subtreeIds(): array
    {
        return Organization::withoutGlobalScopes()
            ->where('path', 'like', $this->path . '%')
            ->pluck('id')
            ->all();
    }
}

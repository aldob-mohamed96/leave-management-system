<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * الدرجة الوظيفية التي تحدد عدد أيام الإجازة الاعتيادية المستحقة.
 * تُدار من لوحة التحكم (إضافة / تعديل / تفعيل).
 */
class EntitlementGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'category',
        'name',
        'yearly_days',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'yearly_days' => 'integer',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    public const CATEGORIES = [
        'teacher'  => 'معلمون',
        'guidance' => 'توجيه',
        'admin'    => 'إداريون',
        'special'  => 'حالات خاصة',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'entitlement_grade', 'code');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function isTeacher(): bool
    {
        return $this->category === 'teacher';
    }

    /**
     * Options for Filament/API selects: code => "الاسم — N يوم"
     *
     * @return array<string, string>
     */
    public static function options(bool $activeOnly = true): array
    {
        $query = static::query()->ordered();

        if ($activeOnly) {
            $query->active();
        }

        return $query
            ->get()
            ->mapWithKeys(fn (self $g) => [
                $g->code => "{$g->name} — {$g->yearly_days} يوم",
            ])
            ->all();
    }

    public static function findByCode(?string $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return static::query()->where('code', $code)->first();
    }
}

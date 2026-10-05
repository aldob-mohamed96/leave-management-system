<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Holiday extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'date',
        'name',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Activity Log
    // -------------------------------------------------------------------------

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "تم {$eventName} الإجازة الرسمية: {$this->name}");
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeInYear($query, int $year)
    {
        return $query->whereYear('date', $year);
    }

    public function scopeBetween($query, \Carbon\Carbon $from, \Carbon\Carbon $to)
    {
        return $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }
}

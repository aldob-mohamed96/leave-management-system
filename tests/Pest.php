<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/
uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Build a 3-level org hierarchy and return all nodes.
 * Bypasses OrganizationScope (no auth user in unit tests).
 */
function createHierarchy(): array
{
    $dir = \App\Models\Organization::create([
        'parent_id' => null,
        'type'      => 'directorate',
        'name'      => 'مديرية الأقصر التعليمية',
        'code'      => 'DIR-TEST',
        'path'      => '/',
        'depth'     => 0,
        'is_active' => true,
    ]);

    $adm = \App\Models\Organization::create([
        'parent_id' => $dir->id,
        'type'      => 'administration',
        'name'      => 'إدارة أرمنت التعليمية',
        'code'      => 'ADM-TEST',
        'path'      => '/',
        'depth'     => 0,
        'is_active' => true,
    ]);

    $school = \App\Models\Organization::create([
        'parent_id' => $adm->id,
        'type'      => 'school',
        'name'      => 'مدرسة النيل الابتدائية',
        'code'      => 'SCH-TEST',
        'path'      => '/',
        'depth'     => 0,
        'is_active' => true,
    ]);

    // Refresh to get observer-built paths
    return [
        'directorate'    => $dir->fresh(),
        'administration' => $adm->fresh(),
        'school'         => $school->fresh(),
    ];
}

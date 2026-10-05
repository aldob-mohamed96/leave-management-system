<?php

namespace App\Observers;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Handles two concerns:
 *  1. Auto-builds / updates the materialized `path` when parent changes.
 *  2. Cascades path updates to all descendants when a node moves.
 */
class OrganizationObserver
{
    /**
     * Before saving, recompute path and depth so they're always consistent.
     * This fires on both create and update.
     */
    public function saving(Organization $organization): void
    {
        if ($organization->parent_id === null) {
            // Root node — path is set after create (we need the ID).
            // Depth is always 0.
            $organization->depth = 0;
            return;
        }

        $parent = Organization::withoutGlobalScopes()
            ->select('id', 'path', 'depth')
            ->find($organization->parent_id);

        if ($parent) {
            $organization->depth = $parent->depth + 1;
            // If the model is already persisted we can build the full path;
            // for brand-new models the ID isn't available yet — handled in created().
            if ($organization->exists) {
                $organization->path = $parent->path . "{$organization->id}/";
            }
        }
    }

    /**
     * After a new node is created we always know the ID,
     * so we can finalize the path.
     */
    public function created(Organization $organization): void
    {
        if ($organization->parent_id === null) {
            $organization->updateQuietly(['path' => "/{$organization->id}/"]);
        } else {
            $parent = Organization::withoutGlobalScopes()
                ->select('path')
                ->find($organization->parent_id);

            if ($parent) {
                $organization->updateQuietly([
                    'path' => $parent->path . "{$organization->id}/",
                ]);
            }
        }
    }

    /**
     * If parent_id changed, cascade the new path to all descendants.
     * Uses a single SQL UPDATE with REPLACE for efficiency.
     */
    public function updated(Organization $organization): void
    {
        if (! $organization->wasChanged('parent_id')) {
            return;
        }

        $this->cascadePathToDescendants($organization);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function cascadePathToDescendants(Organization $organization): void
    {
        // Fetch fresh path after the update
        $newPath = $organization->fresh()->path;
        $oldPath = $organization->getOriginal('path');

        if ($oldPath === $newPath) {
            return;
        }

        // Get all descendants (excluding self — already updated)
        $descendants = Organization::withoutGlobalScopes()
            ->where('path', 'like', $oldPath . '%')
            ->where('id', '!=', $organization->id)
            ->get(['id', 'path', 'depth']);

        foreach ($descendants as $descendant) {
            $updatedPath  = $newPath . ltrim(substr($descendant->path, strlen($oldPath)), '/');
            $updatedDepth = substr_count(trim($updatedPath, '/'), '/');

            $descendant->updateQuietly([
                'path'  => $updatedPath,
                'depth' => $updatedDepth,
            ]);
        }
    }
}

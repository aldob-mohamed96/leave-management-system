@php
    use App\Enums\OrganizationType;
    use App\Models\Organization;

    $user = auth()->user();
    $school = null;
    $administration = null;
    $directorate = null;
    $role = null;

    if ($user?->organization_id) {
        $current = Organization::withoutGlobalScopes()->find($user->organization_id);

        while ($current) {
            match ($current->type) {
                OrganizationType::SCHOOL => $school = $current,
                OrganizationType::ADMINISTRATION => $administration = $current,
                OrganizationType::DIRECTORATE => $directorate = $current,
                default => null,
            };

            $current = $current->parent_id
                ? Organization::withoutGlobalScopes()->find($current->parent_id)
                : null;
        }

        $user->setOrganizationTeam();
        $role = $user->getRoleNames()->first();
    }

    $primaryName = $school?->name ?? $administration?->name ?? $directorate?->name ?? 'بدون مؤسسة';
@endphp

@if($user)
    <div class="fi-context-bar hidden sm:flex items-center gap-x-3 me-3 text-sm">
        <div class="rounded-lg bg-primary-50 dark:bg-gray-800 border border-primary-100 dark:border-gray-700 px-3 py-1.5 leading-tight max-w-xl">
            <div class="font-semibold text-primary-700 dark:text-primary-300 truncate">
                {{ $primaryName }}
            </div>
            <div class="text-xs text-gray-600 dark:text-gray-300 truncate">
                @if($school && $administration)
                    {{ $administration->name }}
                    @if($directorate) — {{ $directorate->name }} @endif
                @elseif($administration && $directorate)
                    {{ $directorate->name }}
                @elseif($directorate)
                    {{ $directorate->name }}
                @endif
            </div>
        </div>

        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-1.5 leading-tight">
            <div class="font-medium text-gray-800 dark:text-gray-100 truncate">
                {{ $user->name }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                {{ $role ?: 'بدون دور' }}
                @if($user->email)
                    — {{ $user->email }}
                @endif
            </div>
        </div>
    </div>
@endif

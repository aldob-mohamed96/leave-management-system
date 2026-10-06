<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password) {
            $allowed = $request->routeIs([
                'filament.admin.pages.force-password-change',
                'filament.admin.auth.logout',
                'livewire.update',
                'livewire.upload-file',
            ]);

            if (! $allowed) {
                return redirect()->route('filament.admin.pages.force-password-change');
            }
        }

        return $next($request);
    }
}

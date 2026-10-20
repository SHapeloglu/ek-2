<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class RoleMiddleware {
    public function handle(Request $request, Closure $next, string $role): Response {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->role === $role, 403);
        return $next($request);
    }
}

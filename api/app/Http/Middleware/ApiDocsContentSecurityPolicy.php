<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content Security Policy for the API documentation page.
 *
 * The edge proxy sends a strict default policy ('self' only). The docs need more: Scalar's script
 * from the pinned CDN file, an inline bootstrap script (allowed by a per-request nonce, never
 * 'unsafe-inline') and the styles Scalar injects at runtime. Like the edge policy, it only applies
 * in production; locally, the Vite dev server and Scramble's dev tools would be blocked.
 */
final class ApiDocsContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            /** @var Response */
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $scalarScript = config('scramble.renderers.scalar.cdn');

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "script-src 'nonce-{$nonce}' ".(is_string($scalarScript) ? $scalarScript : ''),
            // Scalar injects its stylesheets at runtime.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "base-uri 'none'",
            "form-action 'none'",
            "frame-ancestors 'none'",
        ]));

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Problems\OpenApi\ProblemResponses;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Server;
use Illuminate\Support\ServiceProvider;

/**
 * OpenAPI document tweaks that cannot live in config/scramble.php (closures are not cacheable).
 */
final class ApiDocumentationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A relative server URL keeps the exported contract identical in every environment
        // (the docs are served from the API's own origin).
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->servers = [new Server('/api/v1')];

            ProblemResponses::registerSchemas($openApi->components);
        });
    }
}

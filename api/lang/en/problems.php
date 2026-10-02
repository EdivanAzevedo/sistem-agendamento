<?php

declare(strict_types=1);

return [

    /*
    | Titles and details for generic HTTP failures (`type: about:blank`).
    */

    'status' => [
        400 => ['title' => 'Bad request', 'detail' => 'The request could not be processed.'],
        401 => ['title' => 'Unauthenticated', 'detail' => 'Sign in to continue.'],
        403 => ['title' => 'Forbidden', 'detail' => 'You are not allowed to perform this action.'],
        404 => ['title' => 'Not found', 'detail' => 'What you are looking for does not exist or was removed.'],
        405 => ['title' => 'Method not allowed', 'detail' => 'This operation is not allowed on this address.'],
        419 => ['title' => 'Session expired', 'detail' => 'Your session has expired. Reload the page and try again.'],
        429 => ['title' => 'Too many attempts', 'detail' => 'You made too many attempts. Wait a moment and try again.'],
        500 => ['title' => 'Internal error', 'detail' => 'An unexpected error occurred. If it persists, send the trace id to support.'],
        503 => ['title' => 'Service unavailable', 'detail' => 'The service is temporarily unavailable. Try again shortly.'],
    ],

    /*
    | Application-specific problem types, keyed by their `type` slug.
    */

    'types' => [
        'validation-error' => [
            'title' => 'Invalid data',
            'detail' => 'Some fields were not filled in correctly.',
        ],
    ],

];

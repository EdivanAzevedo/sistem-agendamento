<?php

declare(strict_types=1);

namespace App\Support\Problems\OpenApi;

use App\Support\Problems\ProblemDetails;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

/**
 * OpenAPI building blocks for RFC 9457 responses (`application/problem+json`).
 *
 * Descriptions are written in pt-BR because they are rendered as the public API documentation.
 * Scramble's fluent setters declare no return types, so objects are configured statement by statement.
 */
final class ProblemResponses
{
    public const PROBLEM_SCHEMA = 'ProblemDetails';

    public const VALIDATION_PROBLEM_SCHEMA = 'ValidationProblemDetails';

    public static function make(Components $components, int $status, string $description, bool $validation = false): Response
    {
        self::registerSchemas($components);

        $response = new Response($status);
        $response->setDescription($description);
        $response->setContent(ProblemDetails::CONTENT_TYPE, self::schema(new Reference(
            'schemas',
            $validation ? self::VALIDATION_PROBLEM_SCHEMA : self::PROBLEM_SCHEMA,
            $components,
        )));

        return $response;
    }

    /**
     * Always present in the document, so clients get the error types even before an endpoint uses them.
     */
    public static function registerSchemas(Components $components): void
    {
        if (! $components->hasSchema(self::PROBLEM_SCHEMA)) {
            $problem = self::problemObject(404, 'Não encontrado');
            $problem->setRequired(['type', 'title', 'status', 'trace_id']);

            $components->addSchema(self::PROBLEM_SCHEMA, self::schema($problem));
        }

        if (! $components->hasSchema(self::VALIDATION_PROBLEM_SCHEMA)) {
            $messages = new ArrayType;
            $messages->setItems(new StringType);

            $errors = new ObjectType;
            $errors->setDescription('Mensagens de validação, agrupadas pelo nome do campo.');
            $errors->additionalProperties($messages);

            $validation = self::problemObject(422, 'Dados inválidos');
            $validation->addProperty('errors', $errors);
            $validation->setRequired(['type', 'title', 'status', 'trace_id', 'errors']);

            $components->addSchema(self::VALIDATION_PROBLEM_SCHEMA, self::schema($validation));
        }
    }

    private static function problemObject(int $exampleStatus, string $exampleTitle): ObjectType
    {
        $type = new StringType;
        $type->format('uri');
        $type->setDescription('Identifica o tipo do problema: `about:blank` para falhas HTTP genéricas ou '
            .'`{APP_URL}/problems/{slug}` para falhas da aplicação. Compare apenas o slug.');
        $type->examples(['about:blank']);

        $title = new StringType;
        $title->setDescription('Resumo do problema para exibir ao usuário. Não use para decidir comportamento.');
        $title->examples([$exampleTitle]);

        $status = new IntegerType;
        $status->setDescription('Código de status HTTP, igual ao da resposta.');
        $status->examples([$exampleStatus]);

        $detail = new StringType;
        $detail->setDescription('Explicação desta ocorrência para exibir ao usuário.');

        $traceId = new StringType;
        $traceId->pattern('^[0-9a-f]{32}$');
        $traceId->setDescription('Código de rastreio da requisição (também no header `X-Trace-Id`). Informe-o ao suporte.');
        $traceId->examples(['4bf92f3577b34da6a3ce929d0e0e4736']);

        $object = new ObjectType;
        $object->addProperty('type', $type);
        $object->addProperty('title', $title);
        $object->addProperty('status', $status);
        $object->addProperty('detail', $detail);
        $object->addProperty('trace_id', $traceId);

        return $object;
    }

    private static function schema(Type $type): Schema
    {
        /** @var Schema $schema Scramble's factory declares no return type. */
        $schema = Schema::fromType($type);

        return $schema;
    }
}

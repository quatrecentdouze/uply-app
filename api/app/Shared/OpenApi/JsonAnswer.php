<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;

final readonly class JsonAnswer
{
    private const string MEDIA_TYPE = 'application/json';

    public function __construct(
        private string $action,
        private int $status,
        private array $requiredFields,
    ) {}

    public function __invoke(Operation $operation, RouteInfo $route): void
    {
        if ($route->className().'@'.$route->methodName() !== $this->action) {
            return;
        }

        $answer = Response::make($this->status)->setContent(self::MEDIA_TYPE, $this->schema());
        $responses = array_map(
            fn (Response|Reference $response): Response|Reference => $this->answers($response) ? $answer : $response,
            $operation->responses ?? [],
        );
        if (! in_array($answer, $responses, true)) {
            $responses[] = $answer;
        }

        $operation->responses = $responses;
    }

    private function schema(): Schema
    {
        $body = new ObjectType;
        foreach ($this->requiredFields as $name => $type) {
            if (is_string($name) && $type instanceof Type) {
                $body->addProperty($name, $type);
            }
        }
        $body->setRequired(array_keys($body->properties));

        $schema = new Schema;
        $schema->type = $body;

        return $schema;
    }

    private function answers(Response|Reference $response): bool
    {
        return $response instanceof Response && $response->code === $this->status;
    }
}

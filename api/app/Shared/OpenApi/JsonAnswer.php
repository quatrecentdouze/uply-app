<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;

final readonly class JsonAnswer
{
    private const string MEDIA_TYPE = 'application/json';

    private const int FIRST_SUCCESS_STATUS = 200;

    private const int LAST_SUCCESS_STATUS = 299;

    private function __construct(
        private string $action,
        private int $status,
        private ?Type $body,
    ) {}

    public static function object(string $action, int $status, array $requiredFields): self
    {
        $body = new ObjectType;
        foreach ($requiredFields as $name => $type) {
            if (is_string($name) && $type instanceof Type) {
                $body->addProperty($name, $type);
            }
        }
        $body->setRequired(array_keys($body->properties));

        return new self($action, $status, $body);
    }

    public static function listOf(string $action, int $status, Type $items): self
    {
        $body = new ArrayType;
        $body->setItems($items);

        return new self($action, $status, $body);
    }

    public static function withoutBody(string $action, int $status): self
    {
        return new self($action, $status, null);
    }

    public function __invoke(Operation $operation, RouteInfo $route): void
    {
        if ($route->className().'@'.$route->methodName() !== $this->action) {
            return;
        }

        $answer = $this->answer();
        $responses = [];
        foreach ($operation->responses ?? [] as $response) {
            if (! $this->isSuccess($response)) {
                $responses[] = $response;
            } elseif (! in_array($answer, $responses, true)) {
                $responses[] = $answer;
            }
        }
        if (! in_array($answer, $responses, true)) {
            $responses[] = $answer;
        }

        $operation->responses = $responses;
    }

    private function answer(): Response
    {
        $answer = Response::make($this->status);
        if ($this->body === null) {
            return $answer;
        }
        $schema = new Schema;
        $schema->type = $this->body;

        return $answer->setContent(self::MEDIA_TYPE, $schema);
    }

    private function isSuccess(Response|Reference $response): bool
    {
        return $response instanceof Response
            && is_int($response->code)
            && $response->code >= self::FIRST_SUCCESS_STATUS
            && $response->code <= self::LAST_SUCCESS_STATUS;
    }
}

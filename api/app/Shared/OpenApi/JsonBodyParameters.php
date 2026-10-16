<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\RouteInfo;

final readonly class JsonBodyParameters
{
    private const string MEDIA_TYPE = 'application/json';

    private const string QUERY = 'query';

    public function __construct(private array $actions) {}

    public function __invoke(Operation $operation, RouteInfo $route): void
    {
        if (! in_array($route->className().'@'.$route->methodName(), $this->actions, true)) {
            return;
        }

        $body = new ObjectType;
        $required = [];
        $kept = [];
        foreach ($operation->parameters as $parameter) {
            if (! $parameter instanceof Parameter || $parameter->in !== self::QUERY || ! $parameter->schema instanceof Schema) {
                $kept[] = $parameter;

                continue;
            }
            $body->addProperty($parameter->name, $parameter->schema->type);
            if ($parameter->required) {
                $required[] = $parameter->name;
            }
        }
        if ($body->properties === []) {
            return;
        }
        $body->setRequired($required);
        $schema = new Schema;
        $schema->type = $body;

        $operation->parameters = $kept;
        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent(self::MEDIA_TYPE, $schema)->required($required !== []),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\FunctionReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionLikeType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;

final class ArrayFunctionReturnTypes implements FunctionReturnTypeExtension
{
    private const string MAP = 'array_map';

    private const string VALUES = 'array_values';

    public function shouldHandle(string $name): bool
    {
        return in_array($name, [self::MAP, self::VALUES], true);
    }

    public function getFunctionReturnType(FunctionCallEvent $event): ?Type
    {
        $arguments = $event->arguments;

        return match (true) {
            $event->name === self::MAP && $arguments->count() === 2 => $this->mapped($arguments->get('callback', 0), $arguments->get('array', 1)),
            $event->name === self::VALUES => $this->listed($arguments->get('array', 0)),
            default => null,
        };
    }

    private function mapped(?Type $callback, ?Type $array): ?Type
    {
        if (! $callback instanceof FunctionLikeType || ($array instanceof KeyedArrayType && ! $array->isList)) {
            return null;
        }
        $item = (new TypeWalker)->map($callback->getReturnType(), $this->withoutTemplates(...));

        return $array instanceof ArrayType ? new ArrayType($item, $array->key) : new ArrayType($item);
    }

    private function listed(?Type $array): ?Type
    {
        return $array instanceof ArrayType ? new ArrayType($array->value) : null;
    }

    private function withoutTemplates(Type $type): Type
    {
        return $type instanceof TemplateType && $type->is !== null ? $type->is : $type;
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Illuminate\Validation\Rules\In;

final readonly class IntegerInValues implements RuleTransformer
{
    private const string IN = 'in';

    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is(self::IN) || $rule->instanceOf(In::class);
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        if (! $previous instanceof IntegerType || $previous->enum === []) {
            return $previous;
        }
        $values = [];
        foreach ($previous->enum as $value) {
            $number = filter_var($value, FILTER_VALIDATE_INT);
            if ($number === false) {
                return $previous;
            }
            $values[] = $number;
        }

        return $previous->enum($values);
    }
}

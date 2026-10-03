<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\GeneratesParametersFromRules;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;

final readonly class RequestRulesExtractor implements ParameterExtractor
{
    use GeneratesParametersFromRules;

    public function __construct(
        private TypeTransformer $openApiTransformer,
        private RequestRules $requestRules,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $rules = $this->requestRules->of($routeInfo);
        if ($rules === null) {
            return $parameterExtractionResults;
        }

        return [
            ...$parameterExtractionResults,
            new ParametersExtractionResult($this->makeParameters($rules, $this->openApiTransformer, in: 'body')),
        ];
    }
}

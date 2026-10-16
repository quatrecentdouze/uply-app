<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use App\Shared\Http\MetaController;
use App\Shared\Meta\MetaSection;
use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Http\Response;

final readonly class MetaResponse implements OperationTransformer
{
    private const string ACTION = MetaController::class.'@__invoke';

    private const string DESCRIBE_METHOD = 'describe';

    public function __construct(
        private TypeTransformer $openApiTransformer,
        private Infer $infer,
        #[Tag(MetaSection::TAG)] private iterable $sections,
    ) {}

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if ($routeInfo->className().'@'.$routeInfo->methodName() !== self::ACTION) {
            return;
        }

        $data = new ObjectType;
        foreach ($this->sections as $section) {
            if ($section instanceof MetaSection) {
                $data->addProperty($section->key(), $this->openApiTransformer->transform($this->described($section)));
            }
        }
        ksort($data->properties);
        $data->setRequired(array_keys($data->properties));

        JsonAnswer::object(self::ACTION, Response::HTTP_OK, ['data' => $data])($operation, $routeInfo);
    }

    private function described(MetaSection $section): Type
    {
        return $this->infer->analyzeClass($section::class)->getMethodDefinition(self::DESCRIBE_METHOD)?->type->getReturnType() ?? new UnknownType;
    }
}

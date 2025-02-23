<?php

namespace LaravelRest\Http\Response\Strategies;

use LaravelRest\Http\Transformers\BaseTransformer;

class ItemTransformStrategy implements TransformStrategyInterface
{
    public function transform($data, $transformer = null)
    {
        if ($transformer) {
            return $transformer->transform($data);
        }

        // Если кастомный трансформер не передан
        $transformerModel = BaseTransformer::getTransformClass(
            BaseTransformer::getClass($data, BaseTransformer::getPrefix($data))
        );
        return (new $transformerModel)->transform($data);
    }
}

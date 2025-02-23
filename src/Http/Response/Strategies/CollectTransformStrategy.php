<?php

namespace LaravelRest\Http\Response\Strategies;

use Illuminate\Support\Collection;
use LaravelRest\Http\Transformers\BaseTransformer;

class CollectTransformStrategy implements TransformStrategyInterface
{
    /**
     * @param $data
     * @param $transformer
     * @return mixed
     */
    public function transform($data, $transformer = null)
    {
        $data->transform(function ($value) use ($transformer) {
            if ($transformer) {
                return $transformer->transform($value);
            }
            // Если кастомный трансформер не передан:
            $transformerModel = BaseTransformer::getTransformClass(
                BaseTransformer::getClass($value, BaseTransformer::getPrefix($value))
            );
            return (new $transformerModel)->transform($value);
        });

        return $data;
    }
}

<?php

namespace LaravelRest\Http\Response\Strategies;

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

            if(method_exists($value, 'getTransformer'))
            {
                $transformerModel = $value->getTransformer();

                return (new $transformerModel)->transform($value);
            }

            if(method_exists($value, 'getTransformerData'))
            {
                return $value->getTransformerData();
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

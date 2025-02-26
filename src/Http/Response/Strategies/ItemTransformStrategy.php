<?php

namespace LaravelRest\Http\Response\Strategies;

use LaravelRest\Http\Transformers\BaseTransformer;

class ItemTransformStrategy implements TransformStrategyInterface
{
    /**
     * @param $item
     * @param $transformer
     * @return array|mixed
     */
    public function transform($item, $transformer = null)
    {
        if(!$item)
        {
            return [];
        }

        if ($transformer) {
            return $transformer->transform($item);
        }

        if(method_exists($item, 'getTransformer'))
        {
            $transformerModel = $item->getTransformer();

            return (new $transformerModel)->transform($item);
        }

        // Если кастомный трансформер не передан
        $transformerModel = BaseTransformer::getTransformClass(
            BaseTransformer::getClass($item, BaseTransformer::getPrefix($item))
        );
        return (new $transformerModel)->transform($item);
    }
}

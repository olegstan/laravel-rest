<?php

namespace LaravelRest\Http\Response\Strategies;

use Illuminate\Pagination\LengthAwarePaginator;
use LaravelRest\Http\Transformers\BaseTransformer;

class PaginatorTransformStrategy implements TransformStrategyInterface
{
    /**
     * @param LengthAwarePaginator $data
     * @param $transformer
     * @return mixed
     */
    public function transform($data, $transformer = null)
    {
        if ($transformer) {
            $data->getCollection()->transform(function ($value) use ($transformer) {
                return $transformer->transform($value);
            });
        } else {
            $data->getCollection()->transform(function ($value) {

                if(method_exists($value, 'getTransformer'))
                {
                    $transformerModel = $value->getTransformer();

                    return (new $transformerModel)->transform($value);
                }

                if(method_exists($value, 'getTransformerData'))
                {
                    return $value->getTransformerData();
                }

                // Если кастомный трансформер не передан,
                // пытаемся найти подходящий трансформер через BaseTransformer
                $transformerModel = BaseTransformer::getTransformClass(
                    BaseTransformer::getClass($value, BaseTransformer::getPrefix($value))
                );
                return (new $transformerModel)->transform($value);
            });
        }

        return $data;
    }
}

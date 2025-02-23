<?php


namespace LaravelRest\Http\Response\Strategies;

use LaravelRest\Http\Transformers\BaseTransformer;

interface TransformStrategyInterface
{
    /**
     * Выполняет трансформацию данных в зависимости от логики внутри.
     *
     * @param mixed $data Данные, которые необходимо трансформировать
     * @param BaseTransformer|null $transformer Пользовательский трансформер (если передан)
     *
     * @return mixed Трансформированные данные
     */
    public function transform($data, ?BaseTransformer $transformer = null);
}

<?php

namespace LaravelRest\Http\Response\Strategies;

use Illuminate\Pagination\LengthAwarePaginator;
use LaravelRest\Http\Transformers\BaseTransformer;

class PaginatorTransformStrategy implements TransformStrategyInterface
{
    /**
     * Ключи пагинатора со ссылками на страницы.
     *
     * Клиенту они не нужны: запросы идут через `api/{version}/call`, страницу он
     * задаёт сам параметром page, а для навигации ему хватает current_page,
     * last_page и total. Ссылки же только раздувают ответ (links — массив на
     * каждую страницу) и утекают наружу внутренним host'ом приложения.
     */
    protected const LINK_KEYS = [
        'links',
        'path',
        'first_page_url',
        'last_page_url',
        'next_page_url',
        'prev_page_url',
    ];

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

        return $this->withoutLinks($data);
    }

    /**
     * Отдаёт пагинатор массивом без ссылок на страницы.
     *
     * @param LengthAwarePaginator $paginator
     * @return array
     */
    protected function withoutLinks($paginator)
    {
        $result = $paginator->toArray();

        foreach (static::LINK_KEYS as $key) {
            unset($result[$key]);
        }

        return $result;
    }
}

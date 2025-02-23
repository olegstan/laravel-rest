<?php

namespace LaravelRest\Http\Response;

use LaravelRest\Http\Response\Strategies\CollectTransformStrategy;
use LaravelRest\Http\Response\Strategies\ItemTransformStrategy;
use LaravelRest\Http\Response\Strategies\PaginatorTransformStrategy;
use LaravelRest\Http\Response\Strategies\TransformStrategyInterface;

class TransformationStrategyFactory
{
    public static function make($type = null)
    {
        switch ($type) {
            case 'paginator':
                return new PaginatorTransformStrategy();

            case 'collect':
                return new CollectTransformStrategy();

            case 'item':
                return new ItemTransformStrategy();
        }
    }
}

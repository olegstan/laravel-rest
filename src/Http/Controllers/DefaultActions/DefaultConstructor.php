<?php
namespace LaravelRest\Http\Controllers\DefaultActions;

use LaravelRest\Http\Controllers\RestLayerController;
use LaravelRest\Http\Services\RestQueryService;

/**
 * Trait DefaultActionUpdate
 *
 *  @mixin RestLayerController
 */
trait DefaultConstructor
{
    use DefaultConstructorParams;

    /**
     * @throws \Exception
     */
    public function __construct($request)
    {
        $this->setRequest($request);
    }


}
<?php

namespace App\Api\V1\Controllers\Common;

use LaravelRest\Http\Controllers\RestLayerController;

abstract class BaseController extends RestLayerController
{
    /**
     * @return array
     */
    public function getBuilderAvailableMethod(): array
    {
        return array_merge($this->builderAvailableMethod,
            [

            ]
        );
    }

    /**
     * @param $request
     * @return int|void|null
     */
    public function getUserId($request)
    {

    }
}

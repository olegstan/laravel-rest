<?php

namespace LaravelRest\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use LaravelRest\Http\Requests\StartRequest;
use LaravelRest\Http\Response\ResponseTrait;
use LaravelRest\Http\Validators\ValidatorAble;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, ResponseTrait, ValidatorAble;

    /**
     * @var null
     */
    public static $target = null;
    /**
     * @var null
     */
    public static $action = null;

    /**
     * Controller constructor.
     * @param StartRequest $request
     */
    function __construct(StartRequest $request)
    {
        $request->init();
    }
}

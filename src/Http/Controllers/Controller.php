<?php

namespace LaravelRest\Http\Controllers;

use LaravelRest\Http\Requests\StartRequest;
use LaravelRest\Http\Validators\ValidatorAble;
use LaravelRest\Http\Response\ResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, ResponseTrait, ValidatorAble;

    public static $target = null;
    public static $method = null;

    function __construct(StartRequest $request)
    {
        $request->init();
    }
}

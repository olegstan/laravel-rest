<?php

namespace LaravelRest\Http\Response;

trait ResponseTrait
{
    /**
     * @return ResponseFactory|\Illuminate\Foundation\Application|mixed
     */
	function response()
    {
		return app(ResponseFactory::class);
	}
}

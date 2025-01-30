<?php

namespace LaravelRest\Http\Requests;

/**
 * Interface RequestInterface
 * @package LaravelRest\Http\Requests
 */
interface RequestInterface
{
    public function authorize();

    public function rules();

    public function messages();

    public function fails();

    public function errors();

    public function errorMessage();
}

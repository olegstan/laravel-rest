<?php

namespace LaravelRest\Http\Requests;

use Illuminate\Http\Request;
use LaravelRest\Http\Requests\Traits\TrimRequestTrait;

/**
 * Class DefaultRequest
 * @package LaravelRest\Http\Requests
 */
class DefaultRequest extends Request implements RequestInterface
{
    use TrimRequestTrait;

    /**
     * @var array
     */
    public array $arguments = [];

    /**
     * @var array
     */
    public array $buildQuery = [];

    /**
     * @param Request $request
     * @param $to
     * @return Request|DefaultRequest
     */
    public static function createFrom(Request $request, $to = null)
    {
        $newRequest = parent::createFrom($request);

        //до очистки и переопределения данных запишем arguments и query в отдельные массивы
        $newRequest->arguments = $request->get('arguments', []);

        $newRequest->buildQuery = $request->get('query', []);

        $newRequest->trimInput();

        return $newRequest;
    }

    /**
     * @var string|null
     */
    public $routeController;

    /**
     * @var string|null
     */
    public $routeMethod;

    /**
     * @return mixed
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return mixed
     */
    public function getQuery()
    {
        return $this->buildQuery;
    }

    /**
     * @param $item
     * @param $method
     * @return array|array[]|string|string[]
     */
    public function recursiveUrlDecode($item, $method)
    {
        if (is_array($item)) {
            return array_map(function ($value) use ($method) {
                return $this->recursiveUrlDecode($value, $method);
            }, $item);
        }

        if (is_string($item)) {
            if ($method === 'GET') {
                // Чтобы + не превратилось в пробел
                $item = str_replace('+', '%2B', $item);
                return urldecode($item);
            }

            return $item;
        }

        return $item;
    }


    /**
     * @return string|null
     */
    public function getRouteController()
    {
        return $this->routeController;
    }

    /**
     * @return string|null
     */
    public function getRouteMethod()
    {
        return $this->routeMethod;
    }

    /**
     * Получение токена из запроса (пример).
     *
     * @return string|null
     */
    public function getApiToken()
    {
        return $this->get('api_token');
    }

    /**
     * Получаем заголовок из объекта $this->request.
     *
     * @param string $key
     * @return array|string|null
     */
    public function getHeader($key)
    {
        return $this->getHeader($key);
    }
}
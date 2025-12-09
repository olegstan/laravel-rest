<?php

namespace LaravelRest\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LaravelRest\Http\Response\ResponseTrait;
use LaravelRest\Http\Services\RestQueryService;

/**
 * Class RestController
 * @package LaravelRest\Http\Controllers
 */
abstract class RestController
{
    use ResponseTrait;

    /**
     * @var bool
     */
    public bool $debug = false;

    /**
     * @var Model
     */
    public $model = null;

    /**
     * @var string
     */
    public $modelName = '';

    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    public $modelQuery;

    /**
     * @var bool
     */
    public $modelTableAlias = false;

    /**
     * @var int
     */
    public $perPage = 25;

    /**
     * @var int
     */
    public $fields = ['id'];


    /**
     * @var string
     */
    public $controllerName;

    /**
     * @var string
     */
    public $actionName;

    /**
     * @var string
     */
    public $cacheSortByPart = '';

    /**
     * @var array
     */
    public $onlyFieldsCreate = [];

    /**
     * @var array
     */
    public $onlyFieldsUpdate = [];

    /**
     * @var array
     */
    public $baseOnlyFields = [];

    /**
     * Параметры запроса для построения
     *
     * @var array
     */
    public $queryBuild;

    /**
     * @var array
     */
    public $disabledMethods = [];

    /**
     * Экземпляр нашего нового сервиса
     *
     * @var RestQueryService
     */
    protected RestQueryService $restQueryService; // >>> Сервис для работы с запросами

    public function getBuilderAvailableMethod()
    {
        return [];
    }

    /**
     * @param null|int $perPage
     * @return $this
     */
    private function setPerPage($perPage = null)
    {
        if (is_null($perPage)) {
            // Если нужно — можно вернуть что-то из сессии или ещё откуда
            // ...
            return $this;
        }

        $this->perPage = (int) $perPage;
        return $this;
    }

    /**
     * Сортировка по умолчанию (если не задана другая)
     */
    public function defaultOrderBy(Request $request)
    {
        $this->modelQuery->orderBy(
            $this->restQueryService->withTableAlias('id'),
            'DESC'
        );
    }

    /**
     * Получить ключ кэша (пример из исходного кода)
     *
     * @return string
     */
    public function getCacheKey()
    {
//        $key = $this->cacheKeyMainPart();
//        if ($this->request->query->get('page')) {
//            $key .= "page:" . $this->request->query->get('page');
//        } else {
//            $key .= "page:0";
//        }
//
//        if ($this->cacheSortByPart) {
//            $key .= $this->cacheSortByPart;
//        } else {
//            $key .= ":orderby:id:desc";
//        }

//        return $key;
    }

    /**
     * Пример формирования основной части ключа кэша
     * (логика как в исходнике)
     *
     * @return string
     */
    public function cacheKeyMainPart()
    {
//        $path = $this->request->path();
//        $pathParts = explode('/', $path);
//
//        // По исходному коду: return $pathParts[3] . ":";
//        // Зависит от структуры URL.
//        return isset($pathParts[3]) ? $pathParts[3] . ":" : '';
    }
}
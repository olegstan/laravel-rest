<?php

namespace LaravelRest\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LaravelRest\Http\Requests\RequestInterface;
use LaravelRest\Http\Response\Response;

/**
 * Class RestLayerController
 * @package LaravelRest\Http\Controllers
 */
abstract class RestLayerController extends RestController
{
    /**
     * @param $request
     * @return \Illuminate\Http\Response
     */
    public function getIndex($request)
    {
        $this->queryCondition($request);
        $this->indexCallback($request);
        return $this->responseIndex($request->get('paginateType'));
    }

    /**
     * @param null $action
     * @return string
     */
    protected function getMessageSuccess($action = null)
    {
        switch ($this->getActionName()) {
            case 'store':
                return 'Запись создана';
            case 'update':
                return 'Запись обновлена';
            case 'destroy':
                return 'Запись удалена';
        }
    }

    /**
     * @return string
     */
    protected function getActionName()
    {
        return $this->actionName;
    }

    /**
     * @return string
     */
    protected function getMessageError()
    {
        switch ($this->getActionName()) {
            case 'store':
                return 'Не удалось создать запись';
            case 'update':
                return 'Не удалось обновить запись';
            case 'destroy':
                return 'Не удалось удалить запись';
        }
    }

    /**
     * @param $request
     */
    protected function queryCondition($request)
    {

    }

    /**
     * @param $request
     */
    protected function indexCallback($request)
    {

    }

    /**
     * @param $paginate
     * @return \Illuminate\Http\Response
     */
    protected function responseIndex($paginate)
    {
        if($this->modelName)
        {
            switch ($paginate) {
                case 'all':
//                if (!$this->modelQuery->getQuery()->limit || $this->modelQuery->getQuery()->limit > 200)
//                    $this->modelQuery->limit(1500);//TODO

                    return $this->response()->collection($this->modelQuery->get());
                case 'allOptimize':
                    return $this->response()->collectOptimize($this->modelQuery->get());
                case 'first':
                    return $this->response()->item($this->modelQuery->first());
                case 'pluck':
                    return $this->response()->pluck($this->modelQuery->pluck($this->fields));
                case 'paginate':
                default:
                    return $this->response()
                        ->paginator($this->modelQuery->paginate($this->perPage))//->addMeta('cache_key', $this->getCacheKey())
                        ;

            }
        }
    }
}
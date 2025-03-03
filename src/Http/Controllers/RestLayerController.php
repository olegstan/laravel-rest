<?php

namespace LaravelRest\Http\Controllers;

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
        return $this->responseIndex($request->input('paginateType'));
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
    protected function getControllerName()
    {
        return $this->controllerName;
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
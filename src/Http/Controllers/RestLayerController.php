<?php

namespace LaravelRest\Http\Controllers;

use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LaravelRest\Http\Requests\RequestInterface;
use LaravelRest\Http\Requests\StartRequest;
use LaravelRest\Http\Response\Response;

/**
 * Class RestLayerController
 * @package LaravelRest\Http\Controllers
 */
abstract class RestLayerController extends RestController
{

    /**
     * @param $request
     * @return mixed
     * @throws \Throwable
     */
    public function postStore($request)
    {
        return DB::transaction(function () use ($request)
        {
            $item = call_user_func([$this->modelName, 'create'], $request->only($this->onlyFieldsCreate));
            if ($item) {
                return $this->response()->json($item->toArray())->addMeta('text', 'Запись создана')->addMeta('id', $item->id);
            }
            return $this->response()->error('Не удалось создать запись');
        }, config('app.transaction_tries'));
    }

    /**
     * @param $id
     * @param $request
     * @return Response
     */
    public function putActive($id, $request)
    {
        $this->queryCondition($request);
        /**
         * @var BaseModel $item
         */
        $item = $this->modelQuery->where('id', '=', $id)->firstOrFail();
        $item->fieldSwitch('is_active');
        return $this->response()->success();
    }

    /**
     * @param $id
     * @param $request
     * @return Response
     */
    public function deleteRestore($id, $request)
    {
        $this->modelQuery = $this->modelName::query();
        $this->queryCondition($request);
        $item = $this->modelQuery->where('id', '=', $id)->whereNotNull('deleted_at')->withoutGlobalScope(SoftDeletingScope::class)->firstOrFail();
        $item->update(['deleted_at' => null]);
        return $this->response()->success('Запись успешно восстановлена');
    }

    /**
     * @param $id
     * @param $request
     * @return Response|mixed
     * @throws \Throwable
     */
    public function putUpdate($id, $request)
    {
        return DB::transaction(function () use ($id, $request){
            $this->queryCondition($request);
            $this->updateCallback($request);
            $item = $this->modelQuery->where('id', '=', $id)->first(); // firstOrError?

            if ($item && $item->update($request->only($this->onlyFieldsUpdate))) {
                return $this->response()->success('Запись обновлена');
            }

            return $this->response()->error('Не удалось обновить запись');
        }, config('app.transaction_tries'));
    }

    /**
     * @param RequestInterface $request
     * @return void
     */
    public function updateCallback($request)
    {

    }

    /**
     * @param int $id
     * @param StartRequest $request
     * @return mixed
     * @throws \Throwable
     */
    public function deleteDestroy($id, StartRequest $request)
    {
        return DB::transaction(function () use ($id, $request){
            $this->queryCondition($request);
            $this->destroyCallback($id, $request);
            $item = $this->modelQuery->where('id', '=', $id)->first();

            if ($this->sofDelete) {
                if ($item && $item->update(['deleted_at' => Carbon::now()])) {
                    return $this->response()->success('Запись успешно удалена');
                }
            } else {
                if ($item && $item->delete()) {
                    return $this->response()->success('Запись успешно удалена');
                }
            }

            return $this->response()->error('Не удалось удалить запись');
        }, config('app.transaction_tries'));
    }

    /**
     * @param $id
     * @param RequestInterface $request
     * @return mixed
     */

    public function destroyCallback($id, $request)
    {

    }

    /**
     * @return string
     */
    public function getMessageSuccess($action = null)
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
     * @return mixed|string
     */
    public function getActionName()
    {
        return $this->actionName;
    }

    /**
     * @return string
     */
    public function getMessageError()
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
     * @param RequestInterface $request
     * @return Response
     * @throws Exception
     */
    public function getIndex($request)
    {
        $this->queryCondition($request);
        $this->indexCallback($request);
        return $this->responseIndex($request->get('paginateType'));
    }

    /**
     * @param $request
     */
    public function queryCondition($request)
    {

    }

    /**
     * @param RequestInterface $request
     */
    public function indexCallback($request)
    {

    }

    /**
     * @param $paginate
     * @return \Illuminate\Http\Response
     */
    public function responseIndex($paginate)
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
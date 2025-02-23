<?php
namespace LaravelRest\Http\Controllers\DefaultActions;

use Illuminate\Support\Facades\DB;

/**
 * Trait DefaultActionUpdate
 *
 *  @mixin \LaravelRest\Http\Controllers\RestLayerController
 */
trait DefaultActionUpdate
{
    /**
     * @param $id
     * @param $request
     * @return \Illuminate\Http\Response|mixed
     * @throws \Throwable
     */
    public function putUpdate($id, $request)
    {
        return DB::transaction(function () use ($id, $request){
            $this->queryCondition($request);
            $item = $this->modelQuery->where('id', '=', $id)->first(); // firstOrError?

            if ($item && $item->update($request->only($this->onlyFieldsUpdate))) {
                return $this->response()->success('Запись обновлена');
            }

            return $this->response()->error('Не удалось обновить запись');
        }, config('app.transaction_tries'));
    }
}
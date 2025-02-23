<?php
namespace LaravelRest\Http\Controllers\DefaultActions;

use Illuminate\Support\Facades\DB;

/**
 * Trait DefaultActionStore
 *
 *  @mixin \LaravelRest\Http\Controllers\RestLayerController
 */
trait DefaultActionStore
{
    /**
     * @param $request
     * @return \Illuminate\Http\Response|mixed
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
}
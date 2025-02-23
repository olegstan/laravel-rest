<?php
namespace LaravelRest\Http\Controllers\DefaultActions;

use Illuminate\Support\Facades\DB;

/**
 * Trait DefaultActionDelete
 *
 *  @mixin \LaravelRest\Http\Controllers\RestLayerController
 */
trait DefaultActionDelete
{
    /**
     * @param $id
     * @param $request
     * @return mixed
     */
    public function deleteDestroy($id, $request)
    {
        return DB::transaction(function () use ($id, $request){
            $this->queryCondition($request);
            $item = $this->modelQuery->where('id', '=', $id)->first();

            if ($item && $item->delete()) {
                return $this->response()->success('Запись успешно удалена');
            }

            return $this->response()->error('Не удалось удалить запись');
        }, config('app.transaction_tries'));
    }
}
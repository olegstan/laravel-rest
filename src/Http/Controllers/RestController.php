<?php

namespace LaravelRest\Http\Controllers;

use App\Api\V1\Helpers\ApiSession;
use LaravelRest\Http\Requests\RequestInterface;
use LaravelRest\Http\Requests\StartRequest;
use LaravelRest\Http\Validators\ValidatorAble;
use LaravelRest\Http\Response\Response;
use LaravelRest\Http\Transformers\TransformerAble;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;

/**
 * Class RestController
 * @package LaravelRest\Http\Controllers
 */
abstract class RestController extends Controller
{
    use ValidatorAble, TransformerAble;

    /**
     * @var bool
     */
    public $debug = false;
    /**
     * @var bool|null
     */
    public $sofDelete = null;
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
     * @var Request
     */
    public $request;
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
     * @var
     */
    public $queryBuild;
    /**
     * @var array
     */
    public $validators = [];
    /**
     * @var array
     */
    public $defaultValidators = [];
    /**
     * @var array
     */
    public $disabledMethods = [];
    /**
     * @var array
     */
    public $builderAvailableMethod = [
        'select',
        'where',
        'has',
        'whereAbs',
        'whereIn',
        'whereNotIn',
        'whereHas',
        'orWhereHas',
        'whereDoesntHave',
        'orWhereDoesntHave',
        'orWhere',
        'orderBy',
        'groupBy',
        'whereNull',
        'orWhereNull',
        'whereNotNull',
        'orWhereNotNull',
        'with',
        'offset',
        'limit',
        'distinct',
        'having',

        'owner',
        'whereAbs',
        'whereDate',
        'orWhereDate',
        'whereYear',
        'orWhereYear',
        'whereMonth',
        'orWhereMonth',
    ];

    /**
     * RestController constructor.
     * @param RequestInterface $request
     * @throws Exception
     */
    public function __construct(RequestInterface $request)
    {
        if ($request->input('debug')) {
            $this->debug = true;
        }

        if ($request->has('page')) {
            \Illuminate\Pagination\Paginator::currentPageResolver(function () use ($request) {
                return $request->get('page');
            });
        }

        $this->request = $request;

        if($this->modelName)
        {
            $this->model = new $this->modelName;

            $this->setPerPage($request->get('perPage'));
            $this->setSoftDeletes();
            $this->queryBuild = $request->getQuery();

            $this->modelQuery = $this->modelName::query();
            $this->modelQuery->select($this->withTableAlias('*'));
            $this->prepareQuery();
            if ($this->modelTableAlias) {
                $this->modelQuery->from($this->model->getTable() . ' AS ' . $this->modelTableAlias);
            }
            $this->softDeleteCondition($request);
            $this->defaultOrderBy($request);
        }
    }

    /**
     * @return array|string[]
     */
    public function getBuilderAvailableMethod()
    {
        return $this->builderAvailableMethod;
    }

    /**
     * @param null $perPage
     * @return $this
     */
    public function setPerPage($perPage = null)
    {
        if (is_null($perPage))
        {
            if (ApiSession::has('perPage')) {
                $allowValues = [10, 15, 20, 25];
                $value = ApiSession::getInt('perPage');

                if (in_array($value, $allowValues)) {
                    $this->perPage = $value;
                }
            }

            return $this;
        }

        $this->perPage = $perPage;

        return $this;
    }

    /**
     * @return $this
     */
    public function setSoftDeletes()
    {
        if (is_null($this->sofDelete)) {
            $this->sofDelete = in_array('deleted_at', $this->model->getFillable());
        } else {
            $this->sofDelete = false;
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    protected function prepareQuery()
    {
        $this->filterQueryWith($this->queryBuild);
        $query = $this->queryBuild;

        $arrWhere = [];

        foreach ($query as &$subQuery)
        {
            $key = key($subQuery);
            if (in_array($key, [
                'where',
                'orWhere',
                'whereDate',
                'orWhereDate',
                'whereYear',
                'orWhereYear',
                'whereMonth',
                'orWhereMonth',
            ])) {
                $arrWhere[] = $subQuery;
            } else {
                $this->commonPrepareQuery($this->modelQuery, $subQuery);
            }
        }

        $this->prepareWhere($arrWhere);
    }

    /**
     * @param $query
     */
    protected function filterQueryWith(&$query)
    {
        $with = [];

        foreach ($query as $t => &$val) {
            if (key_exists('with', $val) && isset($args[0])) {
                foreach ($val['with'] as $k => $v) {
                    if (!in_array($v, $with)) {
                        $with[] = $v;
                    } else {
                        if (count($val['with']) === 1) {
                            unset($query[$t]);
                        } else {
                            unset($val['with'][$k]);
                        }
                    }
                }
            }
        }
    }


    /**
     * @param $j
     * @param $subQuery
     * @throws Exception
     * TODO
     */
    protected function commonPrepareQuery($j, &$subQuery)
    {
        $key = key($subQuery);
        $args = $subQuery[$key];

        if ($key === 'whereHas') {
            $this->prepareWhereHas($j, $subQuery);
            return;
        }else if ($key === 'whereDoesntHave') {

            $this->prepareWhereDoesntHave($j, $subQuery);
            return;
        }else if ($key === 'whereAbs') {
            $this->prepareWhereAbs($j, $subQuery);
            return;
        }else if ($key === 'with') {
            $args = $this->prepareWith($subQuery);
        } else if ($key === 'limit') {
            $this->perPage = !isset($args[0]) || (isset($args[0]) && $args[0]) > 200 ? 200 : $args[0];
        } else if ($key === 'select') {
            $this->queryCheckSelect($args);
        } elseif ($key === 'orderBy' && $args[0]) {
            if (method_exists($this->model, 'scope' . ucfirst($args[0]))) {
                $j->{$args[0]}($args[1] ?? 'desc');
            }else{
                call_user_func_array([$j, $key], $args);
            }
        } else {
            $this->prepareBase($args);
        }

        $this->queryCheckAvailable($key);
        if ($key !== 'orderBy') {
            //вызов метода с аргументами
            call_user_func_array([$j, $key], $args);
        }
    }

    /**
     * @param $withQuery
     * @return array
     */
    protected function prepareWith(&$withQuery)
    {
        $args = [];
        if (isset($withQuery['with'][1]) && isset($withQuery['with'][1]['query'])) {

            $query = $withQuery['with'][1]['query'];
            $args[] = [$withQuery['with'][0] => function ($j) use ($query)
            {
                foreach ($query as &$subQuery)
                {
                    $this->commonPrepareQuery($j, $subQuery);
                }
            }];

        } else {
            $args[] = $withQuery['with'];
        }
        return $args;
    }

    /**
     * @param $j
     * @param $whereHasQuery
     * @return array
     */
    protected function prepareWhereHas($j, &$whereHasQuery)
    {
        if (isset($whereHasQuery['whereHas'][1]) && isset($whereHasQuery['whereHas'][1]['query'])) {

            $query = $whereHasQuery['whereHas'][1]['query'];
            $j->whereHas($whereHasQuery['whereHas'][0], function ($j) use ($query)
            {
                foreach ($query as &$subQuery)
                {
                    $this->commonPrepareQuery($j, $subQuery);
                }
            });
        } else if(isset($whereHasQuery['whereHas']) && is_array($whereHasQuery['whereHas'])) {
            foreach ($whereHasQuery['whereHas'] as $query)
            {
                $j->whereHas($query);
            }
        }
    }
    /**
     * @param $j
     * @param $whereHasQuery
     * @return array
     */
    protected function prepareWhereDoesntHave($j, &$whereHasQuery)
    {
        if (isset($whereHasQuery['whereDoesntHave'][1]) && isset($whereHasQuery['whereDoesntHave'][1]['query'])) {

            $query = $whereHasQuery['whereDoesntHave'][1]['query'];
            $j->whereDoesntHave($whereHasQuery['whereDoesntHave'][0], function ($j) use ($query)
            {
                foreach ($query as &$subQuery)
                {
                    $this->commonPrepareQuery($j, $subQuery);
                }
            });
        } else if(isset($whereHasQuery['whereDoesntHave']) && is_array($whereHasQuery['whereDoesntHave'])) {
            foreach ($whereHasQuery['whereDoesntHave'] as $query)
            {
                $j->whereDoesntHave($query);
            }
        }
    }

    /**
     * @param $j
     * @param $whereAbsQuery
     */
    protected function prepareWhereAbs($j, &$whereAbsQuery)
    {
        if (isset($whereAbsQuery['whereAbs'][0]) && isset($whereAbsQuery['whereAbs'][1]) && isset($whereAbsQuery['whereAbs'][2])) {

            $j->whereAbs($whereAbsQuery['whereAbs'][0], $whereAbsQuery['whereAbs'][1], $whereAbsQuery['whereAbs'][2]);
        }
    }

    /**
     * @param $args
     * @throws Exception
     */
    protected function queryCheckSelect($args)
    {
        $arr = is_array($args[0]) ? $args[0] : $args;

        foreach ($arr as &$val) {
            if (strpos($val, ' as ') !== false)
                throw new Exception('Не используйте конструкцию "as" в select выражениях: ' . $val);
        }
    }

    /**
     * @param $args
     */
    protected function prepareBase(&$args)
    {
        foreach ($args as &$arg)
        {
            if (is_array($arg) && array_key_exists('query', $arg))
            {
                $query = $arg['query'];
                $arg = function ($j) use ($query)
                {
                    foreach ($query as &$subQuery)
                    {
                        $this->commonPrepareQuery($j, $subQuery);
                    }
                };
            }
        }
    }

    /**
     * @param $funcName
     * @throws Exception
     */
    protected function queryCheckAvailable($funcName)
    {
        if (array_search($funcName, $this->builderAvailableMethod) === false)
        {
            throw new Exception('Недоступный метод ' . $funcName);
        }
    }

    protected function prepareWhere($arr)
    {
        if (count($arr) > 0)
        {
            $this->modelQuery->where(function ($j) use ($arr)
            {
                foreach ($arr as $val)
                {
                    $this->commonPrepareQuery($j, $val);
                }
            });
        }
    }

    /**
     * @param Request $request
     */
    public function softDeleteCondition(Request $request)
    {
        if (!$this->sofDelete) {
            return;
        }

        if (!$request->has('deleted') || $request->get('deleted') === 'false') {
            $this->modelQuery->whereNull($this->withTableAlias('deleted_at'));
        } else {
            $this->modelQuery->whereNotNull($this->withTableAlias('deleted_at'));
            if (!$this->modelTableAlias) {
                $this->modelQuery->withoutGlobalScope(SoftDeletingScope::class);
            }
        }
    }

    /**
     * @param string $fieldName
     *
     * @return string
     */
    public function withTableAlias(string $fieldName): string
    {
        return ($this->modelTableAlias ? $this->modelTableAlias . '.' : $this->model->getTable() . '.') . $fieldName;
    }

    /**
     *
     */
    public function defaultOrderBy($request)
    {
        $this->modelQuery->orderBy($this->withTableAlias('id'), 'DESC');
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
     * @return Response
     */
    public function responseIndex($paginate)
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
            case 'paginate':
            default:
                return $this->response()
                    ->paginator($this->modelQuery->paginate($this->perPage))//->addMeta('cache_key', $this->getCacheKey())
                    ;

        }
    }

    /**
     * @return string
     */
    public function getCacheKey()
    {
        $key = $this->cacheKeyMainPart();
        if ($this->request->query->get('page')) {
            $key .= "page:" . $this->request->query->get('page');
        } else {
            $key .= "page:0";
        }

        if ($this->cacheSortByPart) {
            $key .= $this->cacheSortByPart;
        } else {
            $key .= ":orderby:id:desc";
        }

        return $key;
    }

    /**
     * @return string
     */
    public function cacheKeyMainPart()
    {
        $path = $this->request->path();
        $pathParts = explode('/', $path);
        return $pathParts[3] . ":";
    }

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
        }, config('app.transaction_second'));
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
        }, config('app.transaction_second'));
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
    public function deleteDestroy(int $id, StartRequest $request)
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
        }, config('app.transaction_second'));
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
}

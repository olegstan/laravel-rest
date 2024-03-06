<?php

namespace LaravelRest\Http\Controllers;

use LaravelRest\Http\Requests\RequestInterface;
use LaravelRest\Http\Requests\StartRequest;
use LaravelRest\Http\Validators\ValidatorAble;
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
    use ValidatorAble;

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
     * @var int
     */
    public $fields = ['id'];
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
        'orWhere',
        'whereDate',
        'orWhereDate',
        'whereYear',
        'orWhereYear',
        'whereMonth',
        'orWhereMonth',
        'has',
        'whereIn',
        'orWhereIn',
        'whereNotIn',
        'orWhereNotIn',
        'whereHas',
        'orWhereHas',
        'whereHasMorph',
        'orWhereHasMorph',
        'whereDoesntHave',
        'orWhereDoesntHave',

        'orderBy',
        'groupBy',
        'whereNull',
        'orWhereNull',
        'whereNotNull',
        'orWhereNotNull',

        'with',
        'withCount',
        'offset',
        'limit',
        'distinct',
        'owner',
        'whereAbs',
    ];

    /**
     * RestController constructor.
     * @param RequestInterface|StartRequest $request
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
            $this->queryBuild = $request->recursiveUrlDecode($request->getQuery());

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



    private function setFields()
    {

    }

    /**
     * @param null $perPage
     * @return $this
     */
    private function setPerPage($perPage = null)
    {
        if (is_null($perPage))
        {
//            if (ApiSession::has('perPage')) {
//                $allowValues = [10, 15, 20, 25];
//                $value = ApiSession::getInt('perPage');
//
//                if (in_array($value, $allowValues)) {
//                    $this->perPage = $value;
//                }
//            }

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
    private function prepareQuery()
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
    private function filterQueryWith(&$query)
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
    private function commonPrepareQuery($j, &$subQuery)
    {
        $key = key($subQuery);
        $args = $subQuery[$key];

        switch ($key)
        {
            case 'whereHas':
                $this->prepareWhereHas($j, $subQuery);
                return;
                break;
            case 'whereHasMorph':
                $this->prepareWhereHasMorph($j, $subQuery);
                return;
                break;
            case 'whereDoesntHave':
                $this->prepareWhereDoesntHave($j, $subQuery);
                return;
                break;
            case 'whereAbs':
                $this->prepareWhereAbs($j, $subQuery);
                return;
                break;
            case 'with':
                $args = $this->prepareWith($subQuery);
                break;
            case 'limit':
                $this->perPage = !isset($args[0]) || (isset($args[0]) && $args[0]) > 200 ? 200 : $args[0];
                break;
            case 'select':
                $this->queryCheckSelect($args);
                break;
            case 'orderBy':
                if (isset($args[0]) && method_exists($this->model, 'scope' . ucfirst($args[0]))) {
                    $j->{$args[0]}($args[1] ?? 'desc');
                }else{
                    call_user_func_array([$j, $key], $args);
                }

                break;
            default:
                $this->prepareBase($args);
                break;
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
    private function prepareWith(&$withQuery)
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
    private function prepareWhereHas($j, &$whereHasQuery)
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
    private function prepareWhereHasMorph($j, &$whereHasQuery)
    {
        if (isset($whereHasQuery['whereHasMorph'][1]) && isset($whereHasQuery['whereHasMorph'][1]['query'])) {

            $query = $whereHasQuery['whereHasMorph'][1]['query'];
            $j->whereHasMorph($whereHasQuery['whereHasMorph'][0], '*', function ($j) use ($query)
            {
                foreach ($query as &$subQuery)
                {
                    $this->commonPrepareQuery($j, $subQuery);
                }
            });
        } else if(isset($whereHasQuery['whereHasMorph']) && is_array($whereHasQuery['whereHasMorph'])) {
            foreach ($whereHasQuery['whereHasMorph'] as $query)
            {
                $j->whereHasMorph($query);
            }
        }
    }
    /**
     * @param $j
     * @param $whereHasQuery
     * @return array
     */
    private function prepareWhereDoesntHave($j, &$whereHasQuery)
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
    private function prepareWhereAbs($j, &$whereAbsQuery)
    {
        if (isset($whereAbsQuery['whereAbs'][0]) && isset($whereAbsQuery['whereAbs'][1]) && isset($whereAbsQuery['whereAbs'][2])) {

            $j->whereAbs($whereAbsQuery['whereAbs'][0], $whereAbsQuery['whereAbs'][1], $whereAbsQuery['whereAbs'][2]);
        }
    }

    /**
     * @param $args
     * @throws Exception
     */
    private function queryCheckSelect($args)
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
    private function prepareBase(&$args)
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
     * @return array|string[]
     */
    public function getBuilderAvailableMethod()
    {
        return $this->builderAvailableMethod;
    }

    /**
     * @param $funcName
     * @throws Exception
     */
    private function queryCheckAvailable($funcName)
    {
        if (array_search($funcName, $this->getBuilderAvailableMethod()) === false)
        {
            throw new Exception('Недоступный метод ' . $funcName);
        }
    }

    /**
     * @param $arr
     * @throws Exception
     */
    private function prepareWhere($arr)
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
}
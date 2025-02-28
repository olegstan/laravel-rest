<?php

namespace LaravelRest\Http\Services;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Класс, отвечающий за построение и модификацию запроса к модели
 */
class RestQueryService
{
    /**
     * Использовать ли псевдоним таблицы при формировании запроса
     *
     * @var bool
     */
    protected bool $modelTableAlias = false;

    /**
     * Текущая модель
     *
     * @var Model|null
     */
    protected ?Model $model = null;

    /**
     * Список доступных к вызову методов Query Builder
     *
     * @var array
     */
    protected array $builderAvailableMethod = [
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
     * @param $builderAvailableMethods
     * @return void
     */
    public function addAvailableMethods($builderAvailableMethods)
    {
        $this->builderAvailableMethod = [...$this->builderAvailableMethod, ...$builderAvailableMethods];
    }

    /**
     * Установить модель, с которой будем работать
     *
     * @param  Model  $model
     * @return $this
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Установить флаг использования псевдонима таблицы
     *
     * @param  bool  $alias
     * @return $this
     */
    public function setModelTableAlias(bool $alias): self
    {
        $this->modelTableAlias = $alias;
        return $this;
    }

    /**
     * Основной метод, подготавливающий переданный Builder на основе
     * параметров, полученных из запроса (queryBuild)
     *
     * @param  Builder  $builder
     * @param  array    $queryBuild
     * @throws Exception
     */
    public function prepareQuery(Builder $builder, array $queryBuild): void
    {
        // Предварительно фильтруем параметр with, чтобы не было дубликатов
        $this->filterQueryWith($queryBuild);

        $arrWhere = [];

        // Разделяем «where»-условия и всё остальное
        foreach ($queryBuild as &$subQuery) {
            $key = key($subQuery);

            // Сюда попадают те, что являются where, orWhere и т.п.
            if (in_array($key, [
                'where', 'orWhere', 'whereDate', 'orWhereDate',
                'whereYear', 'orWhereYear', 'whereMonth', 'orWhereMonth',
            ])) {
                $arrWhere[] = $subQuery;
            } else {
                // Обрабатываем остальные методы
                $this->commonPrepareQuery($builder, $subQuery);
            }
        }

        // Применяем все where-условия группой через вложенный колбэк
        $this->prepareWhere($builder, $arrWhere);
    }

    /**
     * Удаляем повторяющиеся with, чтобы не грузить одни и те же связи
     *
     * @param  array  $query
     */
    protected function filterQueryWith(array &$query): void
    {
        $with = [];

        foreach ($query as $t => &$val) {
            if (array_key_exists('with', $val) && isset($val['with'])) {
                foreach ($val['with'] as $k => $v) {
                    if (!in_array($v, $with)) {
                        $with[] = $v;
                    } else {
                        // Если связь уже была, удаляем повтор
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
     * Общий метод, который вызывает необходимые методы Query Builder
     * (whereHas, with, orderBy и т.д.)
     *
     * @param  $builder
     * @param  array    $subQuery
     * @throws Exception
     */
    protected function commonPrepareQuery($builder, array &$subQuery): void
    {
        $key  = key($subQuery);
        $args = $subQuery[$key];

        switch ($key) {
            case 'whereHas':
                $this->prepareWhereHas($builder, $subQuery);
                return;

            case 'whereHasMorph':
                $this->prepareWhereHasMorph($builder, $subQuery);
                return;

            case 'whereDoesntHave':
                $this->prepareWhereDoesntHave($builder, $subQuery);
                return;

            case 'whereAbs':
                $this->prepareWhereAbs($builder, $subQuery);
                return;

            case 'with':
                // Подготавливаем связи для загрузки
                $args = $this->prepareWith($subQuery);
                break;

            case 'limit':
                // Ограничение выборки
                // Не даём выбрать больше 200
                $args[0] = !isset($args[0]) || (isset($args[0]) && $args[0] > 200) ? 200 : $args[0];
                break;

            case 'select':
                // Проверка, что нет конструкций "as"
                $this->queryCheckSelect($args);
                break;

            case 'orderBy':
                // Если в модели есть scope-метод, вызываем его
                if (isset($args[0]) && method_exists($this->model, 'scope' . ucfirst($args[0]))) {
                    $builder->{$args[0]}($args[1] ?? 'desc');
                    return;
                }
                break;

            default:
                // Обрабатываем вложенные query при необходимости
                $this->prepareBase($args);
                break;
        }

        // Проверяем доступность метода
        $this->queryCheckAvailable($key);

        // Если это не orderBy с кастомным scope, вызываем метод напрямую
        if ($key !== 'orderBy') {
            call_user_func_array([$builder, $key], $args);
        } else {
            // Для orderBy без скоупов – тоже напрямую
            if (!isset($args[0]) || method_exists($this->model, 'scope' . ucfirst($args[0])) === false) {
                call_user_func_array([$builder, $key], $args);
            }
        }
    }

    /**
     * Обработка вложенных условий при with
     *
     * @param  array  $withQuery
     * @return array
     */
    protected function prepareWith(array &$withQuery): array
    {
        $args = [];
        if (isset($withQuery['with'][1]) && isset($withQuery['with'][1]['query'])) {
            $relation = $withQuery['with'][0];
            $sub      = $withQuery['with'][1]['query'];

            $args[] = [
                $relation => function ($q) use ($sub) {
                    foreach ($sub as &$subQuery) {
                        $this->commonPrepareQuery($q, $subQuery);
                    }
                }
            ];
        } else {
            $args[] = $withQuery['with'];
        }

        return $args;
    }

    /**
     * Обработка whereHas
     *
     * @param  Builder  $builder
     * @param  array    $whereHasQuery
     */
    protected function prepareWhereHas(Builder $builder, array &$whereHasQuery): void
    {
        if (isset($whereHasQuery['whereHas'][1]) && isset($whereHasQuery['whereHas'][1]['query'])) {
            $relation = $whereHasQuery['whereHas'][0];
            $sub      = $whereHasQuery['whereHas'][1]['query'];

            $builder->whereHas($relation, function ($q) use ($sub) {
                foreach ($sub as &$subQuery) {
                    $this->commonPrepareQuery($q, $subQuery);
                }
            });
        } elseif (isset($whereHasQuery['whereHas']) && is_array($whereHasQuery['whereHas'])) {
            // Возможна ситуация, когда передан массив реляций без доп. условий
            // Пример: 'whereHas' => [['relationA'], ['relationB']]
            foreach ($whereHasQuery['whereHas'] as $relation) {
                $builder->whereHas($relation);
            }
        }
    }

    /**
     * Обработка whereHasMorph
     *
     * @param  Builder  $builder
     * @param  array    $whereHasQuery
     */
    protected function prepareWhereHasMorph(Builder $builder, array &$whereHasQuery): void
    {
        if (isset($whereHasQuery['whereHasMorph'][1]) && isset($whereHasQuery['whereHasMorph'][1]['query'])) {
            $relation = $whereHasQuery['whereHasMorph'][0];
            $sub      = $whereHasQuery['whereHasMorph'][1]['query'];

            $builder->whereHasMorph($relation, '*', function ($q) use ($sub) {
                foreach ($sub as &$subQuery) {
                    $this->commonPrepareQuery($q, $subQuery);
                }
            });
        } elseif (isset($whereHasQuery['whereHasMorph']) && is_array($whereHasQuery['whereHasMorph'])) {
            foreach ($whereHasQuery['whereHasMorph'] as $relation) {
                $builder->whereHasMorph($relation);
            }
        }
    }

    /**
     * Обработка whereDoesntHave
     *
     * @param  Builder  $builder
     * @param  array    $whereDoesntHaveQuery
     */
    protected function prepareWhereDoesntHave(Builder $builder, array &$whereDoesntHaveQuery): void
    {
        if (isset($whereDoesntHaveQuery['whereDoesntHave'][1]) && isset($whereDoesntHaveQuery['whereDoesntHave'][1]['query'])) {
            $relation = $whereDoesntHaveQuery['whereDoesntHave'][0];
            $sub      = $whereDoesntHaveQuery['whereDoesntHave'][1]['query'];

            $builder->whereDoesntHave($relation, function ($q) use ($sub) {
                foreach ($sub as &$subQuery) {
                    $this->commonPrepareQuery($q, $subQuery);
                }
            });
        } elseif (isset($whereDoesntHaveQuery['whereDoesntHave']) && is_array($whereDoesntHaveQuery['whereDoesntHave'])) {
            foreach ($whereDoesntHaveQuery['whereDoesntHave'] as $relation) {
                $builder->whereDoesntHave($relation);
            }
        }
    }

    /**
     * Обработка whereAbs
     *
     * @param  Builder  $builder
     * @param  array    $whereAbsQuery
     */
    protected function prepareWhereAbs(Builder $builder, array &$whereAbsQuery): void
    {
        if (isset($whereAbsQuery['whereAbs'][0]) && isset($whereAbsQuery['whereAbs'][1]) && isset($whereAbsQuery['whereAbs'][2])) {
            // Предполагается, что в модели есть собственный scope whereAbs
            // или расширение Query\Builder, где реализован такой метод
            $builder->whereAbs(
                $whereAbsQuery['whereAbs'][0],
                $whereAbsQuery['whereAbs'][1],
                $whereAbsQuery['whereAbs'][2]
            );
        }
    }

    /**
     * Проверка, чтобы не было конструкции "as" в select
     * (исходя из исходной логики)
     *
     * @param  array  $args
     * @throws Exception
     */
    protected function queryCheckSelect(array $args): void
    {
        $arr = is_array($args[0]) ? $args[0] : $args;
        foreach ($arr as $val) {
            if (strpos($val, ' as ') !== false) {
                throw new Exception('Не используйте конструкцию "as" в select выражениях: ' . $val);
            }
        }
    }

    /**
     * Подготовка аргументов для where, orWhere, etc., когда внутри есть 'query' => [...subQueries...]
     *
     * @param  array  $args
     */
    protected function prepareBase(array &$args): void
    {
        foreach ($args as &$arg) {
            if (is_array($arg) && array_key_exists('query', $arg)) {
                $subQueryArray = $arg['query'];
                $arg = function ($q) use ($subQueryArray) {
                    foreach ($subQueryArray as &$subQuery) {
                        $this->commonPrepareQuery($q, $subQuery);
                    }
                };
            }
        }
    }

    /**
     * Проверка на доступность вызова метода у Query Builder
     *
     * @param  string  $funcName
     * @throws Exception
     */
    protected function queryCheckAvailable(string $funcName): void
    {
        if (!in_array($funcName, $this->builderAvailableMethod, true)) {
            throw new Exception('Недоступный метод ' . $funcName);
        }
    }

    /**
     * Групповая обёртка для всех where/или where*
     *
     * @param  Builder  $builder
     * @param  array    $arr
     */
    protected function prepareWhere(Builder $builder, array $arr): void
    {
        if (count($arr) > 0) {
            $builder->where(function ($q) use ($arr) {
                foreach ($arr as $val) {
                    $this->commonPrepareQuery($q, $val);
                }
            });
        }
    }

    /**
     * Генерация поля с учётом псевдонима таблицы
     *
     * @param  string  $fieldName
     * @return string
     */
    public function withTableAlias(string $fieldName): string
    {
        if (!$this->model) {
            return $fieldName;
        }

        $table = $this->model->getTable();
        return $this->modelTableAlias
            ? ($this->modelTableAlias . '.' . $fieldName)
            : ($table . '.' . $fieldName);
    }
}

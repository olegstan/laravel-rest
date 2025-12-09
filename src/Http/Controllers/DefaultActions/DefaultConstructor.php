<?php
namespace LaravelRest\Http\Controllers\DefaultActions;

use LaravelRest\Http\Services\RestQueryService;

/**
 * Trait DefaultActionUpdate
 *
 *  @mixin \LaravelRest\Http\Controllers\RestLayerController
 */
trait DefaultConstructor
{
    public function __construct($request)
    {
        if($request)
        {
            $query = method_exists($request, 'getQuery') ? $request->getQuery() : [];
            // Если задана модель, подготавливаем её для работы
            if ($this->modelName) {
                $this->restQueryService = new RestQueryService(); // >>> Сохраняем в свойство

                $this->restQueryService->addAvailableMethods($this->getBuilderAvailableMethod());

                if ($request->input('debug')) {
                    $this->debug = true;
                }

                if ($request->has('page')) {
                    \Illuminate\Pagination\Paginator::currentPageResolver(function () use ($request) {
                        return $request->input('page');
                    });
                }

                $this->model = new $this->modelName;

                // Устанавливаем кол-во записей на странице
                $this->setPerPage($request->input('perPage'));

                // Считываем query-параметры (ваш метод recursiveUrlDecode может быть где-то в helper)
                $this->queryBuild = $request->recursiveUrlDecode($request->getQuery(), $request->method());

                // Инициализируем Query Builder
                $this->modelQuery = $this->modelName::query();

                // >>> Передаём сервису модель и флаг псевдонима
                $this->restQueryService
                    ->setModel($this->model)
                    ->setModelTableAlias($this->modelTableAlias);

                // Прописываем select с учётом псевдонима (если нужно)
                $this->modelQuery->select($this->restQueryService->withTableAlias('*'));

                // Если нужен alias в секции FROM
                if ($this->modelTableAlias) {
                    $this->modelQuery->from($this->model->getTable().' AS '.$this->modelTableAlias);
                }

                $this->restQueryService->prepareQuery($this->modelQuery, $this->queryBuild);

                // Применяем сортировку по умолчанию
                $this->defaultOrderBy($request);
            }
        }
    }
}
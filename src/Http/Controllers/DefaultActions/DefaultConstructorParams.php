<?php

namespace LaravelRest\Http\Controllers\DefaultActions;

use LaravelRest\Http\Controllers\RestLayerController;
use LaravelRest\Http\Services\RestQueryService;
use Illuminate\Pagination\Paginator;

/**
 * Типовые экшены, совершаемые при вызове конструктора. Могут вызываться отдельно, если необходимо проинициализировать конструктор с DI
 *
 * @mixin RestLayerController
 */
trait DefaultConstructorParams
{
    /**
     * @param $request
     * @return void
     * @throws \Exception
     */
    public function setRequest($request): void
    {
        if (!$request) {
            return;
        }

        if (!$this->modelName) {
            return;
        }

        $this->initializeRestQueryService();
        $this->configureDebugMode($request);
        $this->configurePagination($request);
        $this->initializeModel();
        $this->configurePerPage($request);
        $this->decodeAndStoreQuery($request);
        $this->initializeModelQuery();
        $this->configureQuerySelectAndFrom();
        $this->applyQueryFilters();
        $this->applyDefaultOrdering($request);
    }

    /**
     * @return void
     */
    protected function initializeRestQueryService(): void
    {
        $this->restQueryService = new RestQueryService();
        $this->restQueryService->addAvailableMethods($this->getBuilderAvailableMethod());
    }

    /**
     * @param $request
     * @return void
     */
    protected function configureDebugMode($request): void
    {
        if ($request->input('debug')) {
            $this->debug = true;
        }
    }

    /**
     * @param $request
     * @return void
     */
    protected function configurePagination($request): void
    {
        if ($request->has('page')) {
            Paginator::currentPageResolver(fn () => (int) $request->input('page'));
        }
    }

    /**
     * @return void
     */
    protected function initializeModel(): void
    {
        $this->model = new $this->modelName;
    }

    /**
     * @param $request
     * @return void
     */
    protected function configurePerPage($request): void
    {
        $this->setPerPage($request->input('perPage'));
    }

    /**
     * @param $request
     * @return void
     */
    protected function decodeAndStoreQuery($request): void
    {
        $this->queryBuild = $request->recursiveUrlDecode(
            $request->getQuery(),
            $request->method()
        );
    }

    /**
     * @return void
     */
    protected function initializeModelQuery(): void
    {
        $this->modelQuery = $this->modelName::query();
    }

    /**
     * @return void
     */
    protected function configureQuerySelectAndFrom(): void
    {
        $this->restQueryService
            ->setModel($this->model)
            ->setModelTableAlias($this->modelTableAlias);

        $this->modelQuery->select($this->restQueryService->withTableAlias('*'));

        if ($this->modelTableAlias) {
            $this->modelQuery->from($this->model->getTable() . ' AS ' . $this->modelTableAlias);
        }
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function applyQueryFilters(): void
    {
        $this->restQueryService->prepareQuery($this->modelQuery, $this->queryBuild);
    }

    /**
     * @param $request
     * @return void
     */
    protected function applyDefaultOrdering($request): void
    {
        $this->defaultOrderBy($request);
    }
}
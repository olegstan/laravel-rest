<?php
namespace LaravelRest\Http\Controllers\DefaultActions;

/**
 * Trait DefaultActionComposition
 *
 *  @mixin \LaravelRest\Http\Controllers\RestLayerController
 *  @mixin DefaultActionStore
 *  @mixin DefaultActionUpdate
 *  @mixin DefaultActionDelete
 */
trait DefaultActionComposition
{
    use DefaultActionStore;
    use DefaultActionUpdate;
    use DefaultActionDelete;
}
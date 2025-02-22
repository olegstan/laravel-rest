<?php
namespace LaravelRest\Http\Controllers\DefaultActions;

/**
 * Trait DefaultActionDelete
 *
 *  @mixin \LaravelRest\Http\Controllers\RestLayerController
 */
trait DefaultActionComposition
{
    use DefaultActionStore, DefaultActionUpdate, DefaultActionDelete;
}
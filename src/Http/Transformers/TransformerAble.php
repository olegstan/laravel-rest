<?php
namespace LaravelRest\Http\Transformers;

use App\Helpers\Str;

trait TransformerAble
{
    /**
     *
     */
	public function registerTransformer()
    {
		$this->transformer = BaseTransformer::getTransformClass($this->getTransformClass());
	}

    /**
     * @return bool|mixed|string
     */
	public function getTransformClass()
    {
        return Str::getClass($this->modelName, 'App\Models\\');
	}
}

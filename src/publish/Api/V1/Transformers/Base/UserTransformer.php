<?php

namespace App\Api\V1\Transformers\Base;

use LaravelRest\Http\Transformers\BaseTransformer;

class UserTransformer extends BaseTransformer
{
    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return mixed
     */
    public function transform($model)
    {
        $data = [
            'id' => $model->id,
        ];

        return $this->withRelations($data, $model);
    }

}
<?php

namespace LaravelRest\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use LaravelRest\Http\Requests\Traits\PrepareNumberRequestTrait;

/**
 * Class BaseFormRequest
 * @package LaravelRest\Http\Requests
 */
class BaseFormRequest extends FormRequest
{
    use PrepareNumberRequestTrait;

    /**
     * Массив нестандартных ошибок,
     * которые нужно добавить к результатам валидации.
     *
     * @var array
     */
    public $errors = [];

    /**
     * @var array
     */
    public array $arguments = [];

    /**
     * @var array
     */
    public array $buildQuery = [];

    /**
     * @param BaseFormRequest|\Illuminate\Http\Request $from
     * @param $to
     * @return \Illuminate\Http\Request|BaseFormRequest|DefaultRequest
     */
    public static function createFrom(self|\Illuminate\Http\Request $from, $to = null)
    {
        $request = parent::createFrom($from, $to);

        if($from instanceof DefaultRequest)
        {
            //прокинем аргументы и запрос, чтобы можно было их использовать в валидации
            $request->buildQuery = $from->buildQuery;
            $request->arguments = $from->arguments;
        }

        return $request;
    }

    /**
     * @return mixed
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return mixed
     */
    public function getQuery()
    {
        return $this->buildQuery;
    }

    /**
     *
     */
    public function prepareForValidation()
    {
        $this->prepareInputNumbers();
    }

    /**
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function getValidatorInstance()
    {
        return parent::getValidatorInstance();
    }

    /**
     * Получение текущего ID пользователя.
     *
     * @return int|null
     */
    public function getUserId()
    {
        return Auth::id();
    }

    /**
     * Добавляет кастомную ошибку в массив $this->errors.
     *
     * @param  string  $key
     * @param  string  $value
     * @return void
     */
    public function addError($key, $value)
    {
        $this->errors[] = [
            'key'   => $key,
            'value' => $value,
        ];
    }

    /**
     * @param $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator)
        {
            if($this->errors)
            {
                foreach ($this->errors as $error)
                {
                    $validator->errors()->add($error['key'], $error['value']);
                }
            }
        });
    }

    /**
     * Правила валидации (задаётся в потомках).
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * Кастомные сообщения (задаётся в потомках).
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }

    /**
     * Единая ошибка (если нужна) — переопределяется в потомках.
     *
     * @return string
     */
    public function errorMessage()
    {
        return '';
    }
}
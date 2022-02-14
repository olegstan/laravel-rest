<?php
namespace LaravelRest\Http\Validators;

use App\Api\V1\Requests\StartRequest;
use App\Models\User;
use Auth;
use Closure;
use Validator;

/**
 * Class BaseValidator
 * @package LaravelRest\Http\Validators
 */
class BaseValidator
{
    /**
     * @var
     */
    public $request;

    /**
     * @var \Illuminate\Validation\Validator
     */
    public $validator;

    /**
     * @var Closure
     */
    public $after;

    /**
     * @var array
     */
    public $errors = [];

    /**
     * @var array
     */
    public $prepare = [
        'number' => []
    ];

    /**
     * BaseValidator constructor.
     * @param StartRequest $request
     */
    public function __construct($request)
    {
        $this->prepareInput($request);
        $this->trimInput($request);
        $this->after = function ($validator)
        {
            foreach ($this->errors as $error)
            {
                $validator->errors()->add($error['key'], $error['value']);
            }
        };
        $this->request = $request;

        $this->request->merge($this->customRequest());
        $this->validate();
    }

    /**
     * @return int|null
     */
    public function getUserId()
    {
        return Auth::id();
    }

    /**
     * @param $key
     * @param $value
     */
    public function addError($key, $value)
    {
        $this->errors[] = [
            'key' => $key,
            'value' => $value,
        ];
    }

    public function setText($text)
    {

    }

    /**
     * @return mixed
     */
    public function getNumber($request)
    {
        return $this->prepare['number'];
    }

    /**
     * @param $request
     */
    public function prepareInput($request)
    {
        $input = $request->all();
        $input = is_array($input) ? $input : [];

        foreach ($this->getNumber($request) as $key)
        {
            $parts = explode('.', $key);

            $count = count($parts);

            if($count === 1)
            {
                if(isset($input[$key]))
                {
                    $input[$key] = str_replace(' ', '', isset($input[$key]) ? $input[$key] : 0);

                    if($input[$key] === '')
                    {
                        $input[$key] = 0;
                    }
                }


            }

            if($count === 2)
            {
                if(isset($input[$parts[0]][$parts[1]]))
                {
                    $input[$parts[0]][$parts[1]] = str_replace(' ', '', isset($input[$parts[0]][$parts[1]]) ? $input[$parts[0]][$parts[1]] : 0);

                    if($input[$parts[0]][$parts[1]] === '')
                    {
                        $input[$parts[0]][$parts[1]] = 0;
                    }
                }

            }

            if($count > 2)
            {
                $link = &$input;

                //проверяеям что данные вообще были переданы
                if(isset($input[$parts[0]]))
                {
                    foreach ($parts as $k => $part)
                    {
                        if($part === '*')
                        {
                            if(is_array($link))
                            {
                                //проставим значения для вложенных в массив
                                $innerPart = null;
                                if(($k + 2) === $count)
                                {
                                    $innerPart = $parts[$k + 1];
                                }

                                foreach ($link as &$subItem)
                                {
                                    if(($k + 2) === $count && isset($innerPart) && is_array($subItem))
                                    {
                                        $subItem[$innerPart] = str_replace(' ', '', isset($subItem[$innerPart]) ? $subItem[$innerPart] : 0);

                                        if($subItem[$innerPart] === '')
                                        {
                                            $subItem[$innerPart] = 0;
                                        }
                                    }
                                }
                            }
                        }else{
                            if(isset($input[$part]))
                            {
                                $link = &$input[$part];
                            }
                        }
                    }
                }
            }
        }

        $request->merge($input);
    }

    public function getByLink()
    {
        
    }

    /**
     * @param StartRequest $request
     */
    public function trimInput($request)
    {
        $input = $request->all();
        $input = is_array($input) ? $input : [];
        array_walk_recursive($input, function(&$item)
        {
            if(is_array($item)){

            }else{
                $item = trim($item);

                if($item === '')
                {
                    $item = null;
                }
            }
            return $item;
        });

        $request->merge($input);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function setBefore($data)
    {
        return $data;
    }

    /**
     * @param $callable
     */
	public function setAfter($callable)
    {
		$this->after = $callable;
	}

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
	public function __call($name, $arguments)
    {
		return call_user_func_array([$this->validator, $name], $arguments);
	}


    /**
     * @return \Closure
     */
	public function afterValidate()
    {
		return function($validator){
			
		};
	}

    /**
     * Set request.
     *
     * @return array
     */
	public function customRequest(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * @return array
     */
    public function messages()
    {
        return [];
    }

    /**
     *
     */
    public function validate()
    {
        $data = $this->setBefore($this->request->all());
        $this->validator = Validator::make($data, $this->rules(), $this->messages());
        $this->validator->after($this->after);
    }
}

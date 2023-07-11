<?php

namespace LaravelRest\Http\Validators;

use Auth;
use LaravelRest\Http\Controllers\IndexController;
use Request;

trait ValidatorAble
{
    public static $namespaceValidators = 'App\Api\V1\Requests\\';

    /**
     * @param string $t
     * @return bool
     */
	public function registerValidator($t = '')
    {
        if ($validateClass = $this->getValidateClass())
        {
            $validate = new $validateClass($this->request);

            if ($validate->fails())
            {
                return $validate->errors();
            }
        }

        return false;
    }

    /**
     * @return bool|string
     */
	public function getValidateClass()
    {
        $str = '';
        $arr = explode('-', IndexController::$method);
        foreach($arr as $k => $val)
        {
            $str .= ucfirst(camel_case($val));
        }

        $key = strtolower(Request::method()) . $str;
        $validators = $this->validators;


        if (property_exists($this, 'defaultValidators') && is_array($this->defaultValidators)) {
            $validators = array_merge($this->defaultValidators, $validators);
        }

        if ($validators[$key] ?? null) {
            if (strpos($validators[$key], self::$namespaceValidators) !== false && class_exists($validators[$key])) {
                $validateClass = $validators[$key];
            } else {
                $validateClass = self::$namespaceValidators . ucfirst(Auth::getPrefix()) . '\\' . $validators[$key];
            }

            return $validateClass;
        }

        $validateClass = self::$namespaceValidators . ucfirst(Auth::getPrefix()) . '\\' . ucfirst(IndexController::$target) . 'Request';

        if (class_exists($validateClass)) {
            return $validateClass;
        }

        $validateClass = 'App\Api\V1\Requests\Base\\' . ucfirst(IndexController::$target) . 'Request';
        if (class_exists($validateClass)) {
            return $validateClass;
        }

        return false;
	}
}

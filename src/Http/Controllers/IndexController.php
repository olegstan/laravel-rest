<?php

namespace LaravelRest\Http\Controllers;

use LaravelRest\Http\Requests\RequestInterface;
use LaravelRest\Http\Requests\StartRequest;
use Exception;
use LaravelRest\Http\Response\ResponseTrait;
use ReflectionException;
use ReflectionMethod;
use Auth;
use Request;
use Session;

/**
 * Class IndexController
 * @package App\Api\V1\Controllers
 */
class IndexController extends Controller
{
    use ResponseTrait;
    
    public static $target = null;
    public static $method = null;

    /**
     * @param $target
     * @param $method
     * @param StartRequest $request
     * @return \LaravelRest\Http\Response\Response|mixed
     * @throws Exception
     */
	public function index($target, $method, StartRequest $request)
    {
        self::$target = $target;
        self::$method = $method;
        $role = Auth::getRole();

        $controllerName = 'App\\Api\\V1\\Controllers\\' . ucfirst($role) . '\\' . $this->getTarget($target);
        if(!class_exists($controllerName))
        {
			$controllerName = 'App\\Api\\V1\\Controllers\\Common\\' . $this->getTarget($target);
			if(!class_exists($controllerName))
			{
				return $this->response()->error('Урл не найден', 404, 'Not found controller ' . $controllerName, [
				    'controller' => $controllerName
                ]);
			}
		}else{
            $request->routeController = $controllerName;
        }


		if(!method_exists($controllerName, $this->getMethod($method)))
		{
			return $this->response()->error('Урл не найден', 404, 'In controller ' . $controllerName . ' not found method ' . $this->getMethod($method), [
                'controller' => $controllerName,
                'method' => $method
            ]);
		}else{
            $request->routeMethod = $this->getMethod($method);
        }

        /**
         * @var Controller $controller
         */
        $controller = new $controllerName($request, $request->getQuery());

        if(property_exists($controller, 'disabledMethods') && in_array($this->getMethod($method), $controller->disabledMethods))
        {
            return $this->response()->error('Действие недоступно', 403);
        }

        $arguments = $request->getArguments();

        $refMethod = new ReflectionMethod($controller, $this->getMethod($method));
        $params = $refMethod->getParameters();
        for($i = count($arguments); $i < $refMethod->getNumberOfParameters(); $i++)
        {
            if($params[$i]->isDefaultValueAvailable()){
                $arguments[] = $params[$i]->getDefaultValue();
            }else{
                $type = $params[$i]->getType();
                if(($type && $type->getName() === RequestInterface::class) || $params[$i]->getName() === 'request'){
                    $arguments[] = $request;
                }else{
                    throw new Exception('Обязательный аргумент #'.($i + 1) . ' не был передан');
                }
            }
        }

		if($errors = $controller->registerValidator())
		{
			return $this->response()->error($errors);
		}

        return call_user_func_array([$controller, $this->getMethod($method)], $arguments);
	}

    /**
     * @param $method
     * @return string
     */
	public function getMethod($method)
    {
		return camel_case(strtolower(Request::method()) . ucfirst($method));
	}

    /**
     * @param $target
     * @return string
     */
	public function getTarget($target)
    {
		if(strpos($target, '.') !== false){
			$str = '';
			$arr = explode('.', $target);
			foreach($arr as $key => $val)
			{
				if($key < count($arr) - 1){
					$str .= ucfirst(camel_case($val)) . '\\';
				}
			}
			$str .= ucfirst(camel_case($arr[count($arr)-1])) . 'Controller';
			return $str;
		}else{
			return ucfirst(camel_case($target)) . 'Controller';
		}
	}
}

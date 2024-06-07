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
 * @package LaravelRest\Http\Controllers
 */
class IndexController extends Controller
{
    use ResponseTrait;

    /**
     * @var null
     */
    public static $target = null;
    /**
     * @var null
     */
    public static $action = null;

    /**
     * @param $target
     * @param $method
     * @param StartRequest $request
     * @return \LaravelRest\Http\Response\Response|mixed
     * @throws Exception
     */
	public function index($target, $action, StartRequest $request)
    {
        self::$target = $target;
        self::$action = $action;
        $role = Auth::getRole();

        $controllerNameRole = 'App\\Api\\V1\\Controllers\\' . ucfirst($role) . '\\' . $this->getTarget($target);
        $controllerName = $controllerNameRole;
        if(!class_exists($controllerName))
        {
			$controllerName = 'App\\Api\\V1\\Controllers\\Common\\' . $this->getTarget($target);
			if(!class_exists($controllerName))
			{
				return $this->response()->error('Урл не найден', 404, 'Not found controller ' . $controllerName . ($role ? ' и ' . $controllerNameRole : ''), [
				    'controller' => $controllerName
                ]);
			}
		}else{
            $request->routeController = $controllerName;
        }

		if(!method_exists($controllerName, $this->getAction($action)))
		{
			return $this->response()->error('Урл не найден', 404, 'In controller ' . $controllerName . ' not found method ' . $this->getAction($action), [
                'controller' => $controllerName,
                'action' => $action
            ]);
		}else{
            $request->routeMethod = $this->getAction($action);
        }

        /**
         * @var Controller $controller
         */
        $controller = new $controllerName($request, $request->getQuery());

        if(property_exists($controller, 'disabledMethods') && in_array($this->getAction($action), $controller->disabledMethods))
        {
            return $this->response()->error('Действие недоступно', 403);
        }

        $arguments = $request->getArguments();

        $refMethod = new ReflectionMethod($controller, $this->getAction($action));
        $params = $refMethod->getParameters();


        //TODO может быть несколько аргументов в put методе, если передать только один, то в него не подставится тот что был передеан
        //TODO putUpdate($request) вызовает ошибку, придумать как обработать этот момент
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

        return call_user_func_array([$controller, $this->getAction($action)], $arguments);
	}

    /**
     * @param $action
     * @return string
     */
	public function getAction($action)
    {
        $string = Request::method();
        //ищем замену метода
        if(isset($_REQUEST['_method']))
        {
            $string = $_REQUEST['_method'];
        }

        if(isset($_POST['_method']))
        {
            $string = $_POST['_method'];
        }

        $data = json_decode(file_get_contents("php://input"));
        if(isset($data->_method))
        {
            $string = $data->_method;
        }

		return camel_case(strtolower($string) . ucfirst($action));
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

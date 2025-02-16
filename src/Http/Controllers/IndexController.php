<?php

namespace LaravelRest\Http\Controllers;

use Auth;
use Exception;
use LaravelRest\Http\Requests\RequestInterface;
use LaravelRest\Http\Requests\StartRequest;
use LaravelRest\Http\Response\ResponseTrait;
use ReflectionMethod;
use Request;
use Session;
use Str;

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
     * @param $role
     * @return array|array[]|string[]|\string[][]
     */
    public function getControllers($role)
    {
        $baseControllers = array_merge(
            [
                'App\\Api\\V1\\Controllers\\' . ucfirst($role),
                'App\\Api\\V1\\Controllers\\Common'
            ],
        );

        $customControllers = array_map(
            fn($path) => str_replace('{role}', ucfirst($role), $path),
            config('rest.controllers', [])
        );

        return array_merge($baseControllers, $customControllers);
    }

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

        $controllers = $this->getControllers($role);

        $controllerName = null;

        foreach ($controllers as $namespace) {
            $controllerClass = $namespace . '\\' . $this->getTarget($target);
            if (class_exists($controllerClass)) {
                $controllerName = $controllerClass;
                break;
            }
        }

        if(!$controllerName)
        {
            return $this->response()->error('Урл не найден', 404, 'Not found controller', [
                'controllers' => $controllers
            ]);
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

        $errorData = $controller->registerValidator();
        $errors = $errorData[0] ?? false;
        $errorMessage = $errorData[1] ?? null;

		if($errors)
		{
			return $this->response()->error($errors, 422, $errorMessage);
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
        
        //если мы из запроса хотим изменить метод контроллера
        if(isset($data->data) && isset($data->data->_method))
        {
            $string = $data->data->_method;
        }

		return Str::camel(strtolower($string) . ucfirst($action));
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
					$str .= ucfirst(Str::camel($val)) . '\\';
				}
			}
			$str .= ucfirst(Str::camel($arr[count($arr)-1])) . 'Controller';
			return $str;
		}else{
			return ucfirst(Str::camel($target)) . 'Controller';
		}
	}
}

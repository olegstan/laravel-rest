<?php

namespace LaravelRest\Http\Controllers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Request as RequestHelper;
use LaravelRest\Http\Requests\DefaultRequest;
use LaravelRest\Http\Response\ResponseTrait;
use ReflectionMethod;
use Session;
use Str;
use Illuminate\Routing\Controller;

/**
 * Class RoleRouteController
 * @package LaravelRest\Http\Controllers
 */
class RoleRouteController extends Controller
{
    use ResponseTrait;

    /**
     * @var string[]
     */
    public static array $pathsToControllers = [
        'App\\Api\\V1\\Controllers\\{role}',
        'App\\Api\\V1\\Controllers\\Common'
    ];

    /**
     * @param $role
     * @return array[]|string[]|\string[][]
     */
    public function getControllers($role)
    {
        $baseControllers = array_map(
            fn($path) => str_replace('{role}', ucfirst($role), $path),
            self::$pathsToControllers
        );

        $customControllers = array_map(
            fn($path) => str_replace('{role}', ucfirst($role), $path),
            config('rest.controllers', [])
        );

        return array_merge($baseControllers, $customControllers);
    }

    /**
     * @param Request $request
     * @param $target
     * @param $action
     * @return false|\Illuminate\Http\Response|mixed
     * @throws \ReflectionException
     * @throws BindingResolutionException
     * @throws \Exception
     */
    public function index(DefaultRequest $request, $target, $action)
    {
        $role = Auth::getRole();

        $controllers = $this->getControllers($role);
        $controllersPaths = [];

        $controllerName = null;

        foreach ($controllers as $namespace) {
            $controllerClass = $namespace . '\\' . $this->getTarget($target);
            if (class_exists($controllerClass)) {
                $controllerName = $controllerClass;
                $controllersPaths = [];//очистим чтобы не хранить уже не нужную переменную
                break;
            }else{
                $controllersPaths[] = $controllerClass;
            }
        }

        if (!$controllerName) {
            return $this->response()->error(
                'Урл не найден',
                404,
                [
                    'text' => 'Not found controller',
                    'controllers' => $controllersPaths
                ]
            );
        } else {
            $request->routeController = $controllerName;
        }

        $action = $this->getAction($action);

        if (!method_exists($controllerName, $action)) {
            return $this->response()->error(
                'Урл не найден',
                404,
                [
                    'text' => 'In controller ' . $controllerName . ' not found method ' . $action,
                    'controller' => $controllerName,
                    'action'     => $action
                ]
            );
        }

        /**
         * @var Controller $controller
         */
        $controller = new $controllerName($request);

        if (property_exists($controller, 'disabledMethods') && in_array($action, $controller->disabledMethods)) {
            return $this->response()->error('Действие недоступно', 403);
        }

        // Здесь аргументы, которые приходят из роута (например, /users/1 -> id=1)
        $arguments = $request->getArguments();

        $refMethod = new ReflectionMethod($controller, $action);
        $params    = $refMethod->getParameters();
        $container = app(Container::class);

        // *** Начинаем проход по параметрам метода, чтобы подставить нужные объекты
        for ($i = count($arguments); $i < $refMethod->getNumberOfParameters(); $i++) {
            $param = $params[$i];

            // Если у параметра есть значение по умолчанию, можем его сразу взять.
            if ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
                continue;
            }

            $paramType = $param->getType();

            // Если у параметра указан тип, проверяем, не относится ли он к RequestInterface
            if ($paramType && !$paramType->isBuiltin()) {
                // Получаем FQCN типа
                $paramClass = $paramType->getName();

                // *** Проверяем, что этот класс - реализация/наследник нашего FormRequest
                if (class_exists($paramClass))
                {
                    if(is_subclass_of($paramClass, FormRequest::class))
                    {
                        // Создаём экземпляр FormRequest, передавая туда запросные данные
                        /**
                         * @var FormRequest $newRequest
                         */
                        $newRequest = $paramClass::createFrom($request);
                        $newRequest->setContainer($container);
                        $newRequest->prepareForValidation();

                        // Запускаем валидацию
                        /**
                         * @var Validator $validator
                         */
                        $validator = $newRequest->getValidatorInstance();

                        if ($validator->fails()) {
                            return $this->response()->validationError($validator->errors(), 422, $newRequest->errorMessage());
                        }


                        $arguments[] = $newRequest;
                        continue;
                    }

                    $resolvedDependency = $container->make($paramClass);
                    $arguments[] = $resolvedDependency;
                    continue;
                }
            }

            // *** Если дошли сюда и всё ещё не отработали, но вдруг
            //     имя параметра "request", то тоже подставляем исходный $request
            if ($param->getName() === 'request') {
                $arguments[] = $request;
                continue;
            }

            // *** Иначе кидаем исключение: параметр обязателен, но мы не знаем, чем его заполнить
            throw new \Exception(
                'Обязательный аргумент #' . ($i + 1) . ' (' . $param->getName() . ') не был передан'
            );
        }

        // Вызываем нужный метод на контроллере, передав собранные аргументы
        return call_user_func_array([$controller, $action], $arguments);
    }

    /**
     * @param $action
     * @return string
     */
    public function getAction($action)
    {
        $string = RequestHelper::method();

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

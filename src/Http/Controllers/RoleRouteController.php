<?php

namespace LaravelRest\Http\Controllers;

use Auth;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Request as RequestHelper;
use LaravelRest\Http\Requests\DefaultRequest;
use LaravelRest\Http\Response\ResponseTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Str;

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
     * @throws ReflectionException
     * @throws BindingResolutionException
     * @throws Exception
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
                $controllersPaths = [];
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

        // Создаём контроллер с помощью рефлексии конструктора
        $controller = $this->instantiateController($controllerName, $request);

        if (property_exists($controller, 'disabledMethods') && in_array($action, $controller->disabledMethods)) {
            return $this->response()->error('Действие недоступно', 403);
        }

        // Резолвим аргументы для метода действия
        $arguments = $this->resolveMethodArguments($controller, $action, $request);

        // Вызываем метод на контроллере
        return call_user_func_array([$controller, $action], $arguments);
    }

    /**
     * Создание экземпляра контроллера с учётом параметров конструктора
     *
     * @param string $controllerName
     * @param Request $request
     * @return Controller
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    private function instantiateController(string $controllerName, Request $request)
    {
        $reflectionClass = new ReflectionClass($controllerName);
        $constructor = $reflectionClass->getConstructor();

        // Если конструктора нет, просто создаём без параметров
        if (!$constructor) {
            return new $controllerName();
        }

        $params = $constructor->getParameters();
        $constructorArgs = [];
        $container = app(Container::class);


        foreach ($params as $param) {
            // Если есть значение по умолчанию
            if ($param->isDefaultValueAvailable()) {
                $constructorArgs[] = $param->getDefaultValue();
                continue;
            }

            $paramType = $param->getType();

            // Если тип указан и это не встроенный тип
            if ($paramType && !$paramType->isBuiltin()) {
                $paramClass = $paramType->getName();

                // Если это Request или его наследник
                if (is_a($paramClass, Request::class, true)) {
                    $constructorArgs[] = $request;
                    continue;
                }

                // Пытаемся резолвить через контейнер
                if (class_exists($paramClass)) {
                    $constructorArgs[] = $container->make($paramClass);
                    continue;
                }
            }

            // Если имя параметра 'request'
            if ($param->getName() === 'request') {
                $constructorArgs[] = $request;
                continue;
            }

            // Если обязательный параметр и мы не знаем, что передать
            if (!$param->isOptional()) {
                throw new \Exception(
                    "Cannot resolve constructor parameter '{$param->getName()}' for controller {$controllerName}"
                );
            }

            $constructorArgs[] = null;
        }

        return $reflectionClass->newInstanceArgs($constructorArgs);
    }

    /**
     * Резолв аргументов для метода действия контроллера
     *
     * @param Controller $controller
     * @param string $action
     * @param Request $request
     * @return array
     * @throws \ReflectionException
     * @throws BindingResolutionException
     */
    private function resolveMethodArguments($controller, string $action, Request $request): array
    {
        // Здесь аргументы, которые приходят из роута (например, /users/1 -> id=1)
        $arguments = $request->getArguments();

        $refMethod = new ReflectionMethod($controller, $action);
        $params = $refMethod->getParameters();
        $container = app(Container::class);

        // Начинаем проход по параметрам метода, которые ещё не заполнены
        for ($i = count($arguments); $i < $refMethod->getNumberOfParameters(); $i++) {
            $param = $params[$i];

            // Если у параметра есть значение по умолчанию
            if ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
                continue;
            }

            $paramType = $param->getType();

            // Если у параметра указан тип и это не встроенный тип
            if ($paramType && !$paramType->isBuiltin()) {
                $paramClass = $paramType->getName();

                if (class_exists($paramClass)) {
                    // Если это FormRequest - создаём и валидируем
                    if (is_subclass_of($paramClass, FormRequest::class)) {
                        $validatedRequest = $this->resolveFormRequest($paramClass, $request, $container);

                        // Если валидация не прошла, возвращаем ответ с ошибкой
                        if ($validatedRequest instanceof \Illuminate\Http\Response) {
                            return $validatedRequest;
                        }

                        $arguments[] = $validatedRequest;
                        continue;
                    }

                    // Резолвим через контейнер
                    $resolvedDependency = $container->make($paramClass);
                    $arguments[] = $resolvedDependency;
                    continue;
                }
            }

            // Если имя параметра 'request', подставляем исходный request
            if ($param->getName() === 'request') {
                $arguments[] = $request;
                continue;
            }

            // Параметр обязателен, но мы не знаем, чем его заполнить
            throw new \Exception(
                'Обязательный аргумент #' . ($i + 1) . ' (' . $param->getName() . ') не был передан'
            );
        }

        return $arguments;
    }

    /**
     * Резолв и валидация FormRequest
     *
     * @param string $formRequestClass
     * @param Request $request
     * @param Container $container
     * @return FormRequest|\Illuminate\Http\Response
     */
    private function resolveFormRequest(string $formRequestClass, Request $request, Container $container)
    {
        /**
         * @var FormRequest $newRequest
         */
        $newRequest = $formRequestClass::createFrom($request);
        $newRequest->setContainer($container);
        $newRequest->prepareForValidation();

        /**
         * @var Validator $validator
         */
        $validator = $newRequest->getValidatorInstance();

        if ($validator->fails()) {
            return $this->response()->validationError(
                $validator->errors(),
                422,
                $newRequest->errorMessage()
            );
        }

        return $newRequest;
    }

    /**
     * @param $action
     * @return string
     */
    public function getAction($action)
    {
        $string = RequestHelper::method();

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
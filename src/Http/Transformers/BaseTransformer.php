<?php
namespace LaravelRest\Http\Transformers;

use Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class BaseTransformer extends TransformerAbstract
{
    /**
     * @var array
     */
    public $register = [];
    /**
     * @var array
     */
    public $registerForce = [];
    /**
     * @var array
     */
    public $withOnly = [];
    /**
     * @var array
     */
    public $fields = [];

    /**
     * @var array
     */
    public static $found = [];

    /**
     * @param Model $model
     * @return mixed
     */
    public function transform($model)
    {
        $this->registerAttributes($model);
        $arr = [];
        foreach($this->fields as &$value)
        {
            if(isset($this->registerForce[$value])){
                $arr[$value] = $this->registerForce[$value];
            }else{
                if($model->hasAttribute($value)){
                    $arr[$value] = isset($this->register[$value]) ? $this->register[$value] : $model->{$value};
                }
            }
        }

        return $this->withRelations($arr, $model);
    }

    /**
     * @param $transformed
     * @param Model $model
     * @return mixed
     */
    public function withRelations(&$transformed, $model)
    {
        $relations = $model->getRelations();
        if(count($this->withOnly) > 0)
        {
            $relations = Arr::only($relations, $this->withOnly);
        }

        unset($relations['pivot']);
        foreach($relations as $key => $value)
        {
            if($value && Str::is('*Collection', get_class($value)))
            {
                $first = $value->first();
                if ($first) {
                    $transform = self::createTransformer($first);
                    $transformed[$key] = [];

                    foreach ($value as $v) {
                        $transformed[$key][] = $transform->transform($v);
                    }
                } else {
                    $transformed[$key] = [];
                }
            }else{
                if($value){
                    $transform = self::createTransformer($value);

                    $transformed[$key] = $transform->transform($value);
                }else{
                    $transformed[$key] = null;
                }
            }
        }

        return $transformed;
    }

    /**
     * @param $str
     * @param bool $prefixTo
     * @return bool|mixed|string
     */
    public static function getClass($str, $prefixTo = false)
    {
        if(gettype($str) == 'object'){
            $str = get_class($str);
        }

        if(!$prefixTo){
            return substr(strrchr($str, "\\"), 1);
        }else{
            return str_replace($prefixTo, '', $str);
        }
    }

    /**
     * @param $value
     * @return string
     */
    public static function getPrefix($value)
    {
        if(Str::is('Common\Models*', get_class($value)))
        {
            return 'Common\Models\\';
        }

        return 'App\Models\\';
    }

    /**
     * @param $model
     */
    public function registerAttributes($model)
    {

    }

    /**
     * @param $value
     * @return mixed
     */
    public static function createTransformer($value)
    {
        $name = self::getClass($value, self::getPrefix($value));
        $transformName = self::getTransformClass($name);
        return new $transformName();
    }

    /**
     * @param $name
     * @return string
     */
    public static function getTransformClass($name)
    {
        if(isset(BaseTransformer::$found[$name]))
        {
            return BaseTransformer::$found[$name];
        }

        $str = 'Common\\Transformers\\' . $name . 'Transformer';

        if(class_exists($str))
        {
            BaseTransformer::$found[$name] = $str;

            return $str;
        }

        $role = Auth::getRole();

        $str = 'App\\Api\\V1\\Transformers\\'.ucfirst($role).'\\' . $name . 'Transformer';

        if(class_exists($str))
        {
            BaseTransformer::$found[$name] = $str;

            return $str;
        }

        $str = 'App\\Api\\V1\\Transformers\\Base\\' . $name . 'Transformer';

        if(class_exists($str))
        {
            BaseTransformer::$found[$name] = $str;

            return $str;
        }

        $str = 'App\\Api\\V1\\Transformers\\Base\\' . $name . 'Transformer';

        BaseTransformer::$found[$name] = $str;

        return $str;
    }
}
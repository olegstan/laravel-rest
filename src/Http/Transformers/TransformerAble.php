<?php
namespace LaravelRest\Http\Transformers;

trait TransformerAble
{
    /**
     *
     */
	public function registerTransformer()
    {
		$this->transformer = BaseTransformer::getTransformClass($this->getTransformClass());
	}

    /**
     * @return bool|mixed|string
     */
	public function getTransformClass()
    {
        return self::getClass($this->modelName, 'App\Models\\');
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
}

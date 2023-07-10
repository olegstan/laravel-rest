<?php
namespace App\Api\V1\Helpers;

use Cache;
use Illuminate\Support\Facades\Auth;

class ApiAuthFacade extends Auth
{
    const DEFAULT_ROLE = 'guest';

    public static function getRole()
    {
        if (self::check()) {
            return Cache::rememberForever('user_roll_id' . self::user()->id, function () {
                $roles = config('hosts');
                foreach ($roles as $role) {
                    if (self::user()->is($role)) {
                        return $role;
                    }
                }
            });
        }
        return self::DEFAULT_ROLE;
    }

    public static function getPrefix()
    {
        $config = config('hosts');
        if(self::getPrefixId() && isset($config[self::getPrefixId()]))
        {
            return $config[self::getPrefixId()] ?: 0;
        }
    }

    public static function getPrefixId()
    {
        $role = self::getRole();
        $hosts = config('hosts');

        return array_search($role, $hosts);
    }
}

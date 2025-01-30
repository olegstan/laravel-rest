<?php
namespace App\Api\V1\Helpers;

use App\Models\Role;
use Cache;
use Illuminate\Support\Facades\Auth;

class ApiAuthFacade extends Auth
{
    /**
     *
     */
    const DEFAULT_ROLE = 'guest';

    /**
     * @return string
     */
    public static function getRole()
    {
        if (self::check())
        {
            return Cache::rememberForever('user_roll_id' . self::user()->id, function ()
            {
                $roles = Role::all();
                foreach ($roles as $role)
                {
                    if (self::user()->is($role->slug))
                    {
                        return $role->slug;
                    }
                }
            });
        }
        return self::DEFAULT_ROLE;
    }

    /**
     * @return string
     */
    public static function getPrefix()
    {
        $role = self::getRole();

        if($role && $role !== self::DEFAULT_ROLE)
        {
            return $role;
        }
    }
}

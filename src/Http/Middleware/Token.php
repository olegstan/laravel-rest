<?php

namespace LaravelRest\Http\Middleware;

use Auth;
use Closure;

class Token
{
    /**
     * @param $request
     * @param Closure $next
     * @param null $guard
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response|mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $token = $request->get('api_token');

        if(empty($token))
        {
            $token = $request->header('Authorization');
        }


        /**
         *
         */
        if(!Auth::check() && $token)
        {

            /**
             * @var User $user
             */
			$user = User::where('api_token', $token)
                ->whereNotNull('api_token')
                ->get()
                ->first();

			if($user)
			{
			    Auth::loginUsingId($user->id);
            }
        }

        if(Auth::check())
        {
            if (!is_null(Auth::user()->blocked_at) || !is_null(Auth::user()->deleted_at)) {
                Auth::logout();
                return response('Unauthorized.', 401);
            }
        }

        return $next($request);
    }
}

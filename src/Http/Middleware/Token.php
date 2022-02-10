<?php

namespace LaravelRest\Http\Middleware;

use LaravelRest\Requests\StartRequest;
use Auth;
use Closure;

class Token
{
    /**
     * Handle an incoming request.
     *
     * @param  StartRequest $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     *
     * TODO User
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $token = method_exists($request,'getApiToken') ? $request->getApiToken() : $request->get('api_token');


        /**
         * @var StartRequest $request
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

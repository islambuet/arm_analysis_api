<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\UserHelper;
use Symfony\Component\HttpFoundation\Response;

class loggedUser
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response

    {
        $user=UserHelper::getLoggedUser();
        if(!$user){
            return response()->json(['error' => 'USER_SESSION_EXPIRED','messages'=>__('User Session Expired')]);
        }
        return $next($request);
    }
}

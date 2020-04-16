<?php

namespace App\Http\Middleware;

use Closure;

class MeasureExecutionTimeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        //Thank you Stackoverflow: https://stackoverflow.com/questions/34778433/how-to-add-execution-time-taken-for-an-api-to-respond-in-lumen-framework-in-the 
        $response = $next($request);

        $response->headers->set('X-Elapsed-Time', microtime(true) - LUMEN_START);

        return $response;
    }
}

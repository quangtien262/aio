<?php

namespace Tests\Concerns;

use App\Support\FrontendLocalization;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;

trait InteractsWithStorefrontRoutes
{
    protected function storefrontLocale(): string
    {
        return 'vi';
    }

    protected function storefrontPath(string $path = '', array $query = []): string
    {
        $normalizedPath = trim($path, '/');
        $basePath = '/'.$this->storefrontLocale();

        if ($normalizedPath !== '') {
            $basePath .= '/'.$normalizedPath;
        }

        if ($query === []) {
            return $basePath;
        }

        return $basePath.'?'.http_build_query($query);
    }

    protected function storefrontRoute(string $name, array $parameters = []): string
    {
        $route = Route::getRoutes()->getByName($name);
        $parameterNames = $route instanceof IlluminateRoute
            ? array_flip($route->parameterNames())
            : [];

        $defaultParameters = array_intersect_key(
            FrontendLocalization::routeParameterDefaults($this->storefrontLocale()),
            $parameterNames,
        );

        return route($name, array_merge($defaultParameters, $parameters));
    }
}

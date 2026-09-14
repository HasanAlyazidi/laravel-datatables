<?php

namespace HasanAlyazidi\DataTables;

use HasanAlyazidi\DataTables\Http\DataTableController;
use HasanAlyazidi\DataTables\Http\ScriptController;
use HasanAlyazidi\DataTables\View\Components\Export;
use HasanAlyazidi\DataTables\View\Components\Table;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DataTablesServiceProvider extends ServiceProvider
{
    /**
     * The macro is registered in register() on purpose: route files load
     * right after the app's RouteServiceProvider boots, which is BEFORE
     * providers registered after it get to boot. register() always runs
     * before any provider boots, so the macro exists no matter where this
     * provider sits.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/datatables.php', 'datatables');

        // The leading backslash keeps route groups from adding their
        // controller namespace in front of the action string.
        Route::macro('dataTable', function (string $uri, string $table) {
            return Route::get($uri, '\\'.DataTableController::class.'@__invoke')
                ->defaults('table', $table);
        });
    }

    /**
     * Deep-merge, unlike the framework's shallow mergeConfigFrom, so a
     * project's config only needs to carry the keys it changes and the rest
     * fall through to the package defaults (e.g. change one PDF colour
     * without re-declaring every exporter). Signature matches the parent.
     *
     * @param  string  $path
     * @param  string  $key
     */
    protected function mergeConfigFrom($path, $key)
    {
        $defaults = require $path;
        $app = $this->app['config']->get($key, []);

        $this->app['config']->set($key, $this->mergeConfigRecursive($defaults, $app));
    }

    /**
     * Associative arrays merge key-by-key; lists (e.g. the exporters list)
     * and scalars from the app replace the default outright — so narrowing a
     * list never appends to it, and setting a value to false really disables
     * it. Used by mergeConfigFrom above.
     */
    private function mergeConfigRecursive(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $recurse = is_array($value)
                && isset($defaults[$key])
                && is_array($defaults[$key])
                && $this->isAssoc($value)
                && $this->isAssoc($defaults[$key]);

            if ($recurse) {
                $defaults[$key] = $this->mergeConfigRecursive($defaults[$key], $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    private function isAssoc(array $array): bool
    {
        return $array !== [] && array_keys($array) !== range(0, count($array) - 1);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'datatables');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'datatables');

        $this->registerRoutes();
        $this->registerBlade();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    private function registerRoutes(): void
    {
        // String actions with __invoke are the route:cache-safe form on
        // every supported Laravel version.
        Route::get('_datatables/script.js', '\\'.ScriptController::class.'@__invoke')
            ->name('datatables.script');

        Route::get('_datatables/vendor/{file}', '\\'.ScriptController::class.'@vendor')
            ->where('file', '[A-Za-z0-9._-]+')
            ->name('datatables.vendor');
    }

    private function registerBlade(): void
    {
        // Component tags exist since Laravel 7; on Laravel 6 the directives
        // below are the render path.
        if (version_compare($this->app->version(), '7.0', '>=')) {
            Blade::component(Table::class, 'datatable');
            Blade::component(Export::class, 'datatable.export');
        }

        Blade::directive('datatable', function ($expression) {
            return "<?php echo \HasanAlyazidi\DataTables\Renderer::table({$expression}); ?>";
        });

        Blade::directive('datatableExport', function ($expression) {
            return "<?php echo \HasanAlyazidi\DataTables\Renderer::export({$expression}); ?>";
        });

        Blade::directive('dataTablesStyles', function () {
            return "<?php echo \HasanAlyazidi\DataTables\Assets::styles(); ?>";
        });

        // An empty expression must compile to [] — interpolated raw it
        // would be a PHP syntax error in the compiled view. $__env is passed
        // so the page's @section('dataTable.*') overrides can be read.
        Blade::directive('dataTablesScripts', function ($expression) {
            $expression = trim((string) $expression) === '' ? '[]' : $expression;

            return "<?php echo \HasanAlyazidi\DataTables\Assets::scripts({$expression}, \$__env); ?>";
        });
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/datatables.php' => config_path('datatables.php'),
        ], 'datatables-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/datatables'),
        ], 'datatables-views');

        $this->publishes([
            __DIR__.'/../lang' => $this->langPath(),
        ], 'datatables-lang');

        $this->publishes([
            __DIR__.'/../resources/js/datatables.js' => public_path('vendor/datatables/datatables.js'),
        ], 'datatables-assets');

        $this->publishes([
            __DIR__.'/../resources/vendor/datatables' => public_path('vendor/datatables'),
        ], 'datatables-vendor');
    }

    /**
     * Laravel 9 moved app translations from resources/lang to lang/.
     */
    private function langPath(): string
    {
        if (function_exists('lang_path')) {
            return lang_path('vendor/datatables');
        }

        return resource_path('lang/vendor/datatables');
    }
}

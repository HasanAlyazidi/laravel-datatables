<?php

namespace HasanAlyazidi\DataTables;

use Illuminate\Contracts\View\Factory;

/**
 * Emits the <link>/<script> tags behind the dataTablesStyles and
 * dataTablesScripts Blade directives, matched to the configured theme, and
 * builds the window.laravelDataTables config the client script reads.
 *
 * Sources: 'local' (default) serves the bundled distribution — published
 * copies first, else the package routes; 'cdn' pins cdn.datatables.net
 * with integrity hashes; enabled=false emits only the package's script.
 * VERSION drives the core + i18n URLs; RESPONSIVE_VERSION the Responsive
 * extension URLs, so neither can drift from the bundled files.
 */
final class Assets
{
    /**
     * The DataTables release this package bundles and pins on the CDN.
     */
    const VERSION = '2.3.6';

    /**
     * The Responsive extension release (versioned separately from core).
     */
    const RESPONSIVE_VERSION = '3.0.4';

    /**
     * Public file name => [path inside the bundle, mime type].
     * The whitelist served by the _datatables/vendor/{file} route.
     *
     * @var array
     */
    private static $vendorFiles = [
        'dataTables.min.js' => ['js/dataTables.min.js', 'application/javascript'],
        'dataTables.bootstrap5.min.js' => ['js/dataTables.bootstrap5.min.js', 'application/javascript'],
        'dataTables.bootstrap4.min.js' => ['js/dataTables.bootstrap4.min.js', 'application/javascript'],
        'dataTables.bootstrap.min.js' => ['js/dataTables.bootstrap.min.js', 'application/javascript'],
        'dataTables.responsive.min.js' => ['js/dataTables.responsive.min.js', 'application/javascript'],
        'responsive.bootstrap5.min.js' => ['js/responsive.bootstrap5.min.js', 'application/javascript'],
        'responsive.bootstrap4.min.js' => ['js/responsive.bootstrap4.min.js', 'application/javascript'],
        'responsive.bootstrap.min.js' => ['js/responsive.bootstrap.min.js', 'application/javascript'],
        'dataTables.bootstrap5.min.css' => ['css/dataTables.bootstrap5.min.css', 'text/css'],
        'dataTables.bootstrap4.min.css' => ['css/dataTables.bootstrap4.min.css', 'text/css'],
        'dataTables.bootstrap.min.css' => ['css/dataTables.bootstrap.min.css', 'text/css'],
        'responsive.bootstrap5.min.css' => ['css/responsive.bootstrap5.min.css', 'text/css'],
        'responsive.bootstrap4.min.css' => ['css/responsive.bootstrap4.min.css', 'text/css'],
        'responsive.bootstrap.min.css' => ['css/responsive.bootstrap.min.css', 'text/css'],
        'lang-ar.json' => ['lang/ar.json', 'application/json'],
        'lang-fr.json' => ['lang/fr.json', 'application/json'],
        'lang-it.json' => ['lang/it.json', 'application/json'],
        'lang-ms.json' => ['lang/ms.json', 'application/json'],
    ];

    /**
     * Theme => suffix in the DataTables integration file names. Bootstrap 3
     * predates versioned names, so its files are the legacy unversioned
     * dataTables.bootstrap.* / responsive.bootstrap.* — that quirk lives here.
     *
     * @var array
     */
    private static $integrations = [
        Theme::BOOTSTRAP5 => 'bootstrap5',
        Theme::BOOTSTRAP4 => 'bootstrap4',
        Theme::BOOTSTRAP3 => 'bootstrap',
    ];

    /**
     * App locale => file under the bundle's lang/ dir and under the CDN's
     * plug-ins/{VERSION}/i18n/ path. English needs no file.
     *
     * @var array
     */
    private static $languages = [
        'ar' => 'ar.json',
        'fr' => 'fr.json',
        'it' => 'it.json',
        'ms' => 'ms.json',
    ];

    /**
     * sha384 integrity hashes for cdn mode, one per CDN file name —
     * computed from the pinned files on cdn.datatables.net. A file without
     * an entry is emitted without an integrity attribute. Recompute when
     * bumping a version: openssl dgst -sha384 -binary FILE | openssl base64 -A
     *
     * @var array
     */
    private static $integrity = [
        'dataTables.min.js' => 'sha384-UWkoRdMUXnG8Q4NLgaww6X9JWGlDfsKjC6ymI792g6v93zDTOEuOkkYJFzD6pQkR',
        'dataTables.bootstrap5.min.js' => 'sha384-3BApNGXgbm9rg2kjIbaEVprAGb2B0n9QyLjBrH090WdkzZ3IiUv8RZoTh5uP8oWH',
        'dataTables.bootstrap4.min.js' => 'sha384-mQYCF2gxqKl3YTl+txVPKMyrgj14Qf/YxyCAcI+r+CylaK4hucffh2hza6Dtap6y',
        'dataTables.bootstrap.min.js' => 'sha384-dWxQaWIW01kOo1Nq6GAXs3j8feQUr2oRJqX98pfW0MULdhw/Jc03disinzgnGUkh',
        'dataTables.responsive.min.js' => 'sha384-A6In5tKqlvPZKDpH+ei4A3A4TZrEsyvvN2Fe+oCB1IaQfGD5HNqDIxwjztNKSGDd',
        'responsive.bootstrap5.min.js' => 'sha384-VdUZen/UKzp4O+wnInvMeDymYoESBoFxn8hXwJcu+3QTKXC0Ewzr1Wj+17lPUrtn',
        'responsive.bootstrap4.min.js' => 'sha384-Zm+rJWkaDoGi7tTVmpYGS6kANTuroh/OG7LjV9cyZJS1JArRzXhHo+V4Jvad+nu8',
        'responsive.bootstrap.min.js' => 'sha384-zdWF0aSog7mYRhBpAa+vs6bKOSUn/CCxLfXklfEwemdv1pjzbANdtt/Nq7t6uwk1',
        'dataTables.bootstrap5.min.css' => 'sha384-q6bAgUAsga3oT16XWJ1toXdKcHmBp45jM5roe3RCQ6dET9xGL89Qmpx4tJAI2pm2',
        'dataTables.bootstrap4.min.css' => 'sha384-eKtLViuW31F9jDrqZyyVG1J2hNiy+5phgt+BEe6F03Jqnfppy+wRKLmtLpWdiMaw',
        'dataTables.bootstrap.min.css' => 'sha384-DcxmRBO1osxpJCs+i/4Jrhesl67w4TvKK2yW6ioINL8kEoMy3pxLxebnx6a5rFh+',
        'responsive.bootstrap5.min.css' => 'sha384-seyUnB//1QOFEqox9uI7YTLBgz9jBwFRqZvsEPFrTw6NAsFEo70nhBWsQfODqiYA',
        'responsive.bootstrap4.min.css' => 'sha384-ABjKU7bmH4PbanV9tKU7t2iQzGKeS6dSDeRpnHXGqo5mxNVuHbUDctXjQkuEn2BW',
        'responsive.bootstrap.min.css' => 'sha384-ABjKU7bmH4PbanV9tKU7t2iQzGKeS6dSDeRpnHXGqo5mxNVuHbUDctXjQkuEn2BW',
    ];

    /**
     * The <link> tag(s) for @dataTablesStyles: the DataTables + Responsive
     * CSS matching the configured theme. Empty when assets.enabled is false.
     */
    public static function styles(): string
    {
        if (! config('datatables.assets.enabled', true)) {
            return '';
        }

        return self::styleTag('dataTables.'.self::integration().'.min.css')
            .self::styleTag('responsive.'.self::integration().'.min.css');
    }

    /**
     * The <script> tags for @dataTablesScripts: DataTables core + the theme
     * integration + Responsive (unless assets.enabled is false), the config
     * object, then this package's own script. $env, passed by the directive,
     * lets the page's @section('dataTable.*') overrides be read.
     *
     * @param  Factory|null  $env
     */
    public static function scripts(array $overrides = [], $env = null): string
    {
        $tags = '';

        if (config('datatables.assets.enabled', true)) {
            $tags .= self::vendorScriptTag('dataTables.min.js');
            $tags .= self::vendorScriptTag('dataTables.'.self::integration().'.min.js');
            $tags .= self::vendorScriptTag('dataTables.responsive.min.js');
            $tags .= self::vendorScriptTag('responsive.'.self::integration().'.min.js');
        }

        $config = self::clientConfig($overrides, $env);
        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Merge INTO any inline window.laravelDataTables the page set first,
        // letting that inline object win — a project can still override in JS.
        // The IIFE guards against jQuery not being present yet.
        $tags .= '<script>(function(w){var d='.$json.';'
            .'w.laravelDataTables=w.jQuery?w.jQuery.extend(true,{},d,w.laravelDataTables||{}):d;'
            .'})(window);</script>'."\n";

        return $tags.'<script src="'.self::packageJsUrl().'"></script>'."\n";
    }

    /**
     * The window.laravelDataTables config: table defaults (config, then the
     * page's @section overrides, then the directive argument), an auto
     * language url, the "All" label, and the delete-confirm strings.
     *
     * @param  Factory|null  $env
     */
    private static function clientConfig(array $overrides, $env): array
    {
        $defaults = (array) config('datatables.defaults', []);
        $defaults = array_merge($defaults, self::sectionOverrides($env));

        if (isset($overrides['defaults']) && is_array($overrides['defaults'])) {
            $defaults = array_merge($defaults, $overrides['defaults']);
        }

        if (! isset($defaults['language'])) {
            $url = self::languageUrl();

            if ($url !== null) {
                $defaults['language'] = ['url' => $url];
            }
        }

        $config = $overrides;
        $config['defaults'] = $defaults;
        $config['deleteConfirm'] = self::deleteConfirm();

        if (! isset($config['allLabel'])) {
            $config['allLabel'] = Translation::get('all');
        }

        return $config;
    }

    /**
     * The page's @section('dataTable.*') overrides, coerced to real types.
     * A sentinel default tells a set-but-empty section from an unset one, so
     * only keys the page actually declared are returned.
     *
     * @param  Factory|null  $env
     */
    private static function sectionOverrides($env): array
    {
        if ($env === null) {
            return [];
        }

        $unset = '__dt_unset__';
        $out = [];

        foreach (['responsive', 'stateSave', 'paging', 'searching'] as $key) {
            $value = self::section($env, 'dataTable.'.$key, $unset);

            if ($value !== $unset) {
                $out[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $length = self::section($env, 'dataTable.pageLength', $unset);

        if ($length !== $unset && is_numeric($length)) {
            $out['pageLength'] = (int) $length;
        }

        $column = self::section($env, 'dataTable.order.column', $unset);
        $direction = self::section($env, 'dataTable.order.type', $unset);

        if ($column !== $unset || $direction !== $unset) {
            $out['order'] = [[
                $column !== $unset && is_numeric($column) ? (int) $column : 0,
                strtolower((string) $direction) === 'desc' ? 'desc' : 'asc',
            ]];
        }

        return $out;
    }

    /**
     * One @section value, trimmed (block sections carry whitespace), or the
     * sentinel when the section was never declared.
     *
     * @param  Factory  $env
     */
    private static function section($env, string $name, string $unset): string
    {
        $value = $env->yieldContent($name, $unset);

        // Return the sentinel verbatim when the section was never declared —
        // do NOT trim it, or trim() (which also strips null bytes) could turn
        // the sentinel into something that reads as a real value, forcing the
        // key off. A declared section is trimmed (block sections carry space).
        if ($value === $unset) {
            return $unset;
        }

        return is_string($value) ? trim($value) : $unset;
    }

    /**
     * Strings, theme button classes and the CSRF token for the .DeleteRowButton
     * confirm flow. Strings come from this package's translations, which the
     * app can override with its own datatables.php lang file.
     */
    private static function deleteConfirm(): array
    {
        return [
            'title' => Translation::get('delete_alert.confirmation.title'),
            'text' => Translation::get('delete_alert.confirmation.text'),
            'confirmText' => Translation::get('delete_alert.confirmation.button'),
            'cancelText' => Translation::get('cancel'),
            'successTitle' => Translation::get('delete_alert.success.title'),
            'errorTitle' => Translation::get('delete_alert.error.title'),
            'errorText' => Translation::get('delete_alert.error.text'),
            'closeText' => Translation::get('close'),
            'confirmClass' => self::buttonClass('danger'),
            'cancelClass' => self::buttonClass('secondary'),
            'csrf' => csrf_token(),
        ];
    }

    /**
     * A theme-appropriate button class. Bootstrap 3 has neither btn-secondary
     * nor the spacing utilities, so it gets its own pairing.
     */
    private static function buttonClass(string $variant): string
    {
        if (Theme::current() === Theme::BOOTSTRAP3) {
            return $variant === 'danger' ? 'btn btn-danger' : 'btn btn-default';
        }

        return $variant === 'danger' ? 'btn btn-danger m-1' : 'btn btn-secondary m-1';
    }

    /**
     * One vendor stylesheet <link> from the configured source.
     */
    private static function styleTag(string $file): string
    {
        if (config('datatables.assets.source', 'local') === 'cdn') {
            return '<link rel="stylesheet" href="'.self::cdnUrl($file).'"'.self::integrityAttributes($file).'>'."\n";
        }

        return '<link rel="stylesheet" href="'.self::localUrl($file).'">'."\n";
    }

    /**
     * One vendor <script> tag from the configured source.
     */
    private static function vendorScriptTag(string $file): string
    {
        if (config('datatables.assets.source', 'local') === 'cdn') {
            return '<script src="'.self::cdnUrl($file).'"'.self::integrityAttributes($file).'></script>'."\n";
        }

        return '<script src="'.self::localUrl($file).'"></script>'."\n";
    }

    /**
     * Absolute path of this package's own JavaScript.
     */
    public static function packageJsPath(): string
    {
        return __DIR__.'/../resources/js/datatables.js';
    }

    /**
     * Absolute path of one whitelisted bundle file, or null for anything
     * not in the whitelist.
     */
    public static function vendorFilePath(string $file): ?string
    {
        if (! isset(self::$vendorFiles[$file])) {
            return null;
        }

        return __DIR__.'/../resources/vendor/datatables/'.self::$vendorFiles[$file][0];
    }

    /**
     * The mime type of one whitelisted bundle file.
     */
    public static function vendorFileMime(string $file): ?string
    {
        return isset(self::$vendorFiles[$file]) ? self::$vendorFiles[$file][1] : null;
    }

    /**
     * The integration-file suffix for the current theme. An unknown custom
     * theme falls back to Bootstrap 5's files — a custom theme that needs
     * other vendor files loads them itself with assets.enabled = false.
     */
    private static function integration(): string
    {
        $theme = Theme::current();

        return self::$integrations[$theme] ?? self::$integrations[Theme::BOOTSTRAP5];
    }

    private static function languageUrl(): ?string
    {
        $language = config('datatables.assets.language', 'auto');

        if ($language === false || $language === null) {
            return null;
        }

        // A URL (it has a slash or a dot) is used verbatim; anything else is
        // a locale code, and 'auto' means the current app locale.
        if (is_string($language) && $language !== 'auto'
            && (strpos($language, '/') !== false || strpos($language, '.') !== false)) {
            return $language;
        }

        $locale = $language === 'auto' ? app()->getLocale() : $language;

        if (! isset(self::$languages[$locale])) {
            return null;   // English, or any locale with no bundled file
        }

        if (config('datatables.assets.source', 'local') === 'cdn') {
            return 'https://cdn.datatables.net/plug-ins/'.self::VERSION.'/i18n/'.self::cdnLanguageFile($locale);
        }

        return self::localUrl('lang-'.$locale.'.json');
    }

    /**
     * The i18n file name on the CDN for one locale. The plug-ins repo names
     * some files by region (fr-FR.json); the bundle normalises them to the
     * bare locale, so only the CDN needs this map.
     */
    private static function cdnLanguageFile(string $locale): string
    {
        $regional = [
            'fr' => 'fr-FR.json',
            'it' => 'it-IT.json',
        ];

        return $regional[$locale] ?? self::$languages[$locale];
    }

    /**
     * The URL for one bundle file: the published copy when it exists
     * (vendor:publish --tag=datatables-vendor), else the package route.
     * Both carry a modification-time query so browsers cache forever and
     * still pick up updates.
     */
    private static function localUrl(string $file): string
    {
        $relative = self::$vendorFiles[$file][0];
        $published = public_path('vendor/datatables/'.$relative);

        if (is_file($published)) {
            return asset('vendor/datatables/'.$relative).'?v='.filemtime($published);
        }

        $bundled = self::vendorFilePath($file);
        $version = is_string($bundled) && is_file($bundled) ? filemtime($bundled) : self::VERSION;

        return route('datatables.vendor', ['file' => $file]).'?v='.$version;
    }

    /**
     * The cdn.datatables.net URL for a whitelisted file. Responsive lives
     * under responsive/{RESPONSIVE_VERSION}/; core under {VERSION}/.
     */
    private static function cdnUrl(string $file): string
    {
        $relative = isset(self::$vendorFiles[$file]) ? self::$vendorFiles[$file][0] : $file;

        if (self::isResponsive($file)) {
            return 'https://cdn.datatables.net/responsive/'.self::RESPONSIVE_VERSION.'/'.$relative;
        }

        return 'https://cdn.datatables.net/'.self::VERSION.'/'.$relative;
    }

    private static function isResponsive(string $file): bool
    {
        return $file === 'dataTables.responsive.min.js' || strpos($file, 'responsive.') === 0;
    }

    private static function integrityAttributes(string $file): string
    {
        if (! isset(self::$integrity[$file])) {
            return '';
        }

        return ' integrity="'.self::$integrity[$file].'" crossorigin="anonymous"';
    }

    /**
     * The URL of this package's own script: the published copy when it
     * exists (vendor:publish --tag=datatables-assets), else the package
     * route.
     */
    private static function packageJsUrl(): string
    {
        $published = public_path('vendor/datatables/datatables.js');

        if (is_file($published)) {
            return asset('vendor/datatables/datatables.js').'?v='.filemtime($published);
        }

        return route('datatables.script').'?v='.filemtime(self::packageJsPath());
    }
}

<?php

namespace HasanAlyazidi\DataTables;

/**
 * Emits the <link>/<script> tags behind the dataTablesStyles and
 * dataTablesScripts Blade directives, matched to the configured theme.
 *
 * Sources: 'local' (default) serves the bundled distribution — published
 * copies first, else the package routes; 'cdn' pins cdn.datatables.net
 * with integrity hashes; enabled=false emits only the package's script.
 * VERSION drives both the CDN core URLs and the i18n URL, so the two can
 * never drift apart.
 */
final class Assets
{
    /**
     * The DataTables release this package bundles and pins on the CDN.
     */
    const VERSION = '2.3.6';

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
        'dataTables.bootstrap5.min.css' => ['css/dataTables.bootstrap5.min.css', 'text/css'],
        'dataTables.bootstrap4.min.css' => ['css/dataTables.bootstrap4.min.css', 'text/css'],
        'dataTables.bootstrap.min.css' => ['css/dataTables.bootstrap.min.css', 'text/css'],
        'lang-ar.json' => ['lang/ar.json', 'application/json'],
        'lang-fr.json' => ['lang/fr.json', 'application/json'],
        'lang-it.json' => ['lang/it.json', 'application/json'],
        'lang-ms.json' => ['lang/ms.json', 'application/json'],
    ];

    /**
     * Theme => suffix in the DataTables integration file names. Bootstrap 3
     * predates versioned names, so its files are the legacy unversioned
     * dataTables.bootstrap.* — that quirk lives here only.
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
     * computed from the VERSION files on cdn.datatables.net. A file without
     * an entry is emitted without an integrity attribute. Recompute when
     * bumping VERSION: openssl dgst -sha384 -binary FILE | openssl base64 -A
     *
     * @var array
     */
    private static $integrity = [
        'dataTables.min.js' => 'sha384-UWkoRdMUXnG8Q4NLgaww6X9JWGlDfsKjC6ymI792g6v93zDTOEuOkkYJFzD6pQkR',
        'dataTables.bootstrap5.min.js' => 'sha384-3BApNGXgbm9rg2kjIbaEVprAGb2B0n9QyLjBrH090WdkzZ3IiUv8RZoTh5uP8oWH',
        'dataTables.bootstrap4.min.js' => 'sha384-mQYCF2gxqKl3YTl+txVPKMyrgj14Qf/YxyCAcI+r+CylaK4hucffh2hza6Dtap6y',
        'dataTables.bootstrap.min.js' => 'sha384-dWxQaWIW01kOo1Nq6GAXs3j8feQUr2oRJqX98pfW0MULdhw/Jc03disinzgnGUkh',
        'dataTables.bootstrap5.min.css' => 'sha384-q6bAgUAsga3oT16XWJ1toXdKcHmBp45jM5roe3RCQ6dET9xGL89Qmpx4tJAI2pm2',
        'dataTables.bootstrap4.min.css' => 'sha384-eKtLViuW31F9jDrqZyyVG1J2hNiy+5phgt+BEe6F03Jqnfppy+wRKLmtLpWdiMaw',
        'dataTables.bootstrap.min.css' => 'sha384-DcxmRBO1osxpJCs+i/4Jrhesl67w4TvKK2yW6ioINL8kEoMy3pxLxebnx6a5rFh+',
    ];

    /**
     * The <link> tag(s) for @dataTablesStyles: the DataTables CSS matching
     * the configured theme. Empty when assets.enabled is false.
     */
    public static function styles(): string
    {
        if (! config('datatables.assets.enabled', true)) {
            return '';
        }

        $file = 'dataTables.'.self::integration().'.min.css';

        if (config('datatables.assets.source', 'local') === 'cdn') {
            return '<link rel="stylesheet" href="'.self::cdnUrl('css/'.$file).'"'.self::integrityAttributes($file).'>'."\n";
        }

        return '<link rel="stylesheet" href="'.self::localUrl($file).'">'."\n";
    }

    /**
     * The <script> tags for @dataTablesScripts: DataTables core + the
     * theme's integration (unless assets.enabled is false), the overrides
     * object (auto-language merged in), then this package's own script.
     */
    public static function scripts(array $overrides = []): string
    {
        $tags = '';

        if (config('datatables.assets.enabled', true)) {
            $tags .= self::vendorScriptTag('dataTables.min.js');
            $tags .= self::vendorScriptTag('dataTables.'.self::integration().'.min.js');
        }

        $overrides = self::withLanguage($overrides);

        if ($overrides !== []) {
            $json = json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $tags .= '<script>window.laravelDataTables = '.$json.';</script>'."\n";
        }

        return $tags.'<script src="'.self::packageJsUrl().'"></script>'."\n";
    }

    /**
     * One vendor <script> tag from the configured source.
     */
    private static function vendorScriptTag(string $file): string
    {
        if (config('datatables.assets.source', 'local') === 'cdn') {
            return '<script src="'.self::cdnUrl('js/'.$file).'"'.self::integrityAttributes($file).'></script>'."\n";
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

    /**
     * Fill defaults.language.url from the assets.language setting when the
     * caller has not set one. 'auto' follows the app locale; a string is
     * used as the URL; false/null does nothing.
     */
    private static function withLanguage(array $overrides): array
    {
        if (isset($overrides['defaults']['language'])) {
            return $overrides;
        }

        $url = self::languageUrl();

        if ($url === null) {
            return $overrides;
        }

        $overrides['defaults'] = $overrides['defaults'] ?? [];
        $overrides['defaults']['language'] = ['url' => $url];

        return $overrides;
    }

    private static function languageUrl(): ?string
    {
        $language = config('datatables.assets.language', 'auto');

        if ($language === false || $language === null) {
            return null;
        }

        if (is_string($language) && $language !== 'auto') {
            return $language;
        }

        $locale = app()->getLocale();

        if (! isset(self::$languages[$locale])) {
            return null;
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

    private static function cdnUrl(string $path): string
    {
        return 'https://cdn.datatables.net/'.self::VERSION.'/'.$path;
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

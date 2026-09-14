<?php

namespace HasanAlyazidi\DataTables\Tests;

use HasanAlyazidi\DataTables\Assets;
use HasanAlyazidi\DataTables\Theme;
use Illuminate\Support\Facades\Blade;

class AssetsTest extends TestCase
{
    public function test_local_mode_serves_theme_matched_files_from_the_package_routes()
    {
        $styles = Assets::styles();
        $scripts = Assets::scripts();

        $this->assertStringContainsString('_datatables/vendor/dataTables.bootstrap5.min.css', $styles);
        $this->assertStringContainsString('?v=', $styles);

        $this->assertStringContainsString('_datatables/vendor/dataTables.min.js', $scripts);
        $this->assertStringContainsString('_datatables/vendor/dataTables.bootstrap5.min.js', $scripts);
        $this->assertStringContainsString('_datatables/script.js', $scripts);
        $this->assertStringNotContainsString('integrity', $scripts);
    }

    public function test_the_bootstrap3_theme_uses_the_legacy_unversioned_file_names()
    {
        config(['datatables.theme' => Theme::BOOTSTRAP3]);

        $this->assertStringContainsString('dataTables.bootstrap.min.css', Assets::styles());
        $this->assertStringNotContainsString('bootstrap3.min', Assets::styles());
        $this->assertStringContainsString('dataTables.bootstrap.min.js', Assets::scripts());
    }

    public function test_auto_language_follows_the_app_locale()
    {
        app()->setLocale('ar');
        $arabic = Assets::scripts();
        $this->assertStringContainsString('lang-ar.json', $arabic);
        $this->assertStringContainsString('laravelDataTables', $arabic);

        // English needs no file: the config object is still emitted (it carries
        // the table defaults and delete strings), just without a language url.
        app()->setLocale('en');
        $english = Assets::scripts();
        $this->assertStringContainsString('laravelDataTables', $english);
        $this->assertStringNotContainsString('lang-', $english);
    }

    public function test_explicit_overrides_win_over_the_auto_language()
    {
        app()->setLocale('ar');

        $scripts = Assets::scripts(['defaults' => ['language' => ['url' => '/my/ar.json']]]);

        $this->assertStringContainsString('/my/ar.json', $scripts);
        $this->assertStringNotContainsString('lang-ar.json', $scripts);
    }

    public function test_overrides_are_emitted_alongside_the_injected_language()
    {
        app()->setLocale('ar');

        $scripts = Assets::scripts(['defaults' => ['stateSave' => true]]);

        $this->assertStringContainsString('"stateSave":true', $scripts);
        $this->assertStringContainsString('lang-ar.json', $scripts);
    }

    public function test_cdn_mode_pins_the_version_and_adds_integrity()
    {
        config(['datatables.assets.source' => 'cdn']);

        $styles = Assets::styles();
        $scripts = Assets::scripts();

        $this->assertStringContainsString('https://cdn.datatables.net/'.Assets::VERSION.'/css/dataTables.bootstrap5.min.css', $styles);
        $this->assertStringContainsString('integrity="sha384-', $styles);
        $this->assertStringContainsString('crossorigin="anonymous"', $styles);
        $this->assertStringContainsString('https://cdn.datatables.net/'.Assets::VERSION.'/js/dataTables.min.js', $scripts);
    }

    public function test_cdn_language_files_use_the_regional_names()
    {
        config(['datatables.assets.source' => 'cdn']);
        app()->setLocale('fr');

        $this->assertStringContainsString('plug-ins/'.Assets::VERSION.'/i18n/fr-FR.json', Assets::scripts());
    }

    public function test_disabled_assets_emit_only_the_package_script()
    {
        config(['datatables.assets.enabled' => false]);

        $this->assertSame('', Assets::styles());

        $scripts = Assets::scripts();

        $this->assertStringContainsString('_datatables/script.js', $scripts);
        $this->assertStringNotContainsString('_datatables/vendor/', $scripts);
    }

    public function test_the_vendor_route_serves_whitelisted_files_only()
    {
        $response = $this->get(route('datatables.vendor', ['file' => 'dataTables.min.js']));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));

        $this->get(route('datatables.vendor', ['file' => 'lang-ar.json']))->assertStatus(200);
        $this->get(route('datatables.vendor', ['file' => 'evil.php']))->assertStatus(404);
        $this->get(route('datatables.vendor', ['file' => '..dataTables.min.js']))->assertStatus(404);
    }

    public function test_the_script_route_serves_the_package_javascript()
    {
        $response = $this->get(route('datatables.script'));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));

        // A BinaryFileResponse streams; read the file it points at.
        $file = $response->baseResponse->getFile();

        $this->assertStringContainsString('laravel-datatables', file_get_contents($file->getPathname()));
    }

    public function test_the_scripts_directive_compiles_its_empty_form_safely()
    {
        // $__env is passed so the page's @section('dataTable.*') can be read.
        $this->assertStringContainsString('Assets::scripts([], $__env)', Blade::compileString('@dataTablesScripts'));
        $this->assertStringContainsString("Assets::scripts(['defaults' => []], \$__env)", Blade::compileString("@dataTablesScripts(['defaults' => []])"));
        $this->assertStringContainsString('Assets::styles()', Blade::compileString('@dataTablesStyles'));
    }

    public function test_responsive_extension_is_bundled_and_emitted()
    {
        $styles = Assets::styles();
        $scripts = Assets::scripts();

        $this->assertStringContainsString('responsive.bootstrap5.min.css', $styles);
        $this->assertStringContainsString('dataTables.responsive.min.js', $scripts);
        $this->assertStringContainsString('responsive.bootstrap5.min.js', $scripts);

        $this->get(route('datatables.vendor', ['file' => 'dataTables.responsive.min.js']))->assertStatus(200);
        $this->get(route('datatables.vendor', ['file' => 'responsive.bootstrap.min.css']))->assertStatus(200);
    }

    public function test_cdn_responsive_uses_its_own_version_segment()
    {
        config(['datatables.assets.source' => 'cdn']);

        $this->assertStringContainsString(
            'cdn.datatables.net/responsive/'.Assets::RESPONSIVE_VERSION.'/js/dataTables.responsive.min.js',
            Assets::scripts()
        );
    }

    public function test_the_config_carries_the_defaults_and_delete_strings()
    {
        $scripts = Assets::scripts();

        $this->assertStringContainsString('"lengthMenu":[1,5,10,25,50,75,100,-1]', $scripts);
        $this->assertStringContainsString('"responsive":true', $scripts);
        $this->assertStringContainsString('"deleteConfirm"', $scripts);
        $this->assertStringContainsString('Yes, Delete.', $scripts);
    }

    public function test_a_bare_locale_code_selects_that_languages_file()
    {
        config(['datatables.assets.language' => 'ar']);
        app()->setLocale('en');   // the explicit code wins over the app locale

        $this->assertStringContainsString('lang-ar.json', Assets::scripts());
    }

    public function test_the_config_is_merge_emitted_so_an_inline_object_wins()
    {
        $this->assertStringContainsString('w.jQuery.extend(true,{},d,w.laravelDataTables||{})', Assets::scripts());
    }
}

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
        $this->assertStringContainsString('lang-ar.json', Assets::scripts());
        $this->assertStringContainsString('laravelDataTables', Assets::scripts());

        app()->setLocale('en');
        $this->assertStringNotContainsString('laravelDataTables', Assets::scripts());
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
        $this->assertStringContainsString('Assets::scripts([])', Blade::compileString('@dataTablesScripts'));
        $this->assertStringContainsString("Assets::scripts(['defaults' => []])", Blade::compileString("@dataTablesScripts(['defaults' => []])"));
        $this->assertStringContainsString('Assets::styles()', Blade::compileString('@dataTablesStyles'));
    }
}

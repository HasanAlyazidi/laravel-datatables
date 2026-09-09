<?php

namespace HasanAlyazidi\DataTables\Http;

use HasanAlyazidi\DataTables\Assets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the package's front-end files straight from the installed package,
 * so nothing has to be published to public/ (the Livewire pattern).
 *
 * Responses are cached "forever" by the browser; the URLs the directives
 * emit carry a ?v=<mtime> query, so a package update changes the URL and
 * browsers simply fetch the new file once.
 */
class ScriptController
{
    /**
     * GET _datatables/script.js — this package's own JavaScript.
     */
    public function __invoke()
    {
        return $this->file(Assets::packageJsPath(), 'application/javascript');
    }

    /**
     * GET _datatables/vendor/{file} — one file of the bundled DataTables
     * distribution, for assets.source = 'local'. Only names in the fixed
     * whitelist are served; the route parameter never touches the
     * filesystem directly.
     */
    public function vendor(string $file)
    {
        $path = Assets::vendorFilePath($file);

        abort_unless(is_string($path) && is_file($path), 404);

        return $this->file($path, Assets::vendorFileMime($file));
    }

    /**
     * @return BinaryFileResponse
     */
    private function file(string $path, string $mime)
    {
        return response()->file($path, [
            'Content-Type' => $mime.'; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}

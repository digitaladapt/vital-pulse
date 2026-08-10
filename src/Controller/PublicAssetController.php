<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicAssetController
{
    #[Route('/', name: 'serve_home')]
    public function serveHome(): Response
    {
        return $this->serveFile('index.html');
    }

    #[Route('/{path}', name: 'serve_asset', requirements: ['path' => '.+'])]
    public function serveAsset(string $path): Response
    {
        return $this->serveFile($path);
    }

    private function serveFile(string $path): Response
    {
        // Prevent directory traversal attacks
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return new Response('Not found', 404);
        }

        // Explicitly block PHP files — they must go through the front controller
        if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            return new Response('Not found', 404);
        }

        $publicPath = dirname(__DIR__, 2) . '/public';
        $fullPath   = $publicPath . '/' . $path;

        // File must exist on disk
        if (!file_exists($fullPath)) {
            return new Response('Not found', 404);
        }

        // Resolve symlinks and verify the resolved path is still inside public/
        $realPath = realpath($fullPath);
        if ($realPath === false || !str_starts_with($realPath, $publicPath . '/')) {
            return new Response('Not found', 404);
        }

        // Map extensions to MIME types
        $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimes = [
            'html'  => 'text/html',
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'json'  => 'application/json',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'webp'  => 'image/webp',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
            'eot'   => 'application/vnd.ms-fontobject',
            'manifest' => 'text/cache-manifest',
            'webmanifest' => 'application/manifest+json',
        ];

        $contentType = $mimes[$ext] ?? 'application/octet-stream';

        $response = new BinaryFileResponse($fullPath);
        $response->headers->set('Content-Type', $contentType);

        // Cache for 1 hour — asset filenames are typically cache-busted anyway
        $response->setMaxAge(3600);
        $response->setSharedMaxAge(3600);
        $response->headers->addCacheControlDirective('public', true);

        return $response;
    }
}

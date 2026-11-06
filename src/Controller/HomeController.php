<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AppVersion;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Renders the dashboard (the only HTML page).
 *
 * Static assets under public/ are served directly by the web server
 * (Caddy's file_server in the image, the PHP built-in server in dev) —
 * they do not go through Symfony at all. What used to be a catch-all
 * asset controller in PHP is gone: the document is a template, the
 * files are files, and each is served the normal way.
 */
class HomeController extends AbstractController
{
    public function __construct(
        private readonly AppVersion $appVersion,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'app_version' => $this->appVersion->getVersion(),
        ]);
    }
}

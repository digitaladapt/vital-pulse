<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The application version, as baked into the image at build time.
 *
 * Priority:
 *   1. VERSION file (written by the Dockerfile from the APP_VERSION build arg,
 *      which docker-bake.hcl passes from the CI tag)
 *   2. Hardcoded fallback
 *
 * The old git-describe path used `shell_exec()` on every /api/about request.
 * That is a shell invocation in a production request path to produce a version
 * string the image already knows — both a performance problem and a hardening
 * one, since it hands a subprocess to the request lifecycle for no reason
 * (GUIDING-LIGHT §8.13). The build arg writes the VERSION file instead, which
 * is the source of truth.
 */
final class AppVersion
{
    private const FALLBACK_VERSION = 'unknown';

    private ?string $cached = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getVersion(): string
    {
        if (null !== $this->cached) {
            return $this->cached;
        }

        $versionFile = $this->projectDir.'/VERSION';
        if (is_file($versionFile) && is_readable($versionFile)) {
            $version = trim((string) file_get_contents($versionFile));
            if ('' !== $version && 'dev' !== $version) {
                // Strip leading "v" if present, so the reported version is bare SemVer.
                $version = str_starts_with($version, 'v') ? substr($version, 1) : $version;

                return $this->cached = $version;
            }
        }

        return $this->cached = self::FALLBACK_VERSION;
    }
}

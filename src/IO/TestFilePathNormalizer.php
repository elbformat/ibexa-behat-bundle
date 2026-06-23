<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\IO;

use Ibexa\Core\IO\FilePathNormalizer\Flysystem;
use Ibexa\Core\IO\FilePathNormalizerInterface;

/**
 * Make predictable hashes for testing.
 *
 * @author Hannes Giesenow <hannes.giesenow@format-h.com>
 */
class TestFilePathNormalizer implements FilePathNormalizerInterface
{
    public function __construct(protected Flysystem $parent)
    {
    }

    public function normalizePath(string $filePath, bool $doHash = true, ?string $realFilePath = null): string
    {
        return $this->parent->normalizePath($filePath, false);
    }
}

<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\IO;

use eZ\Publish\Core\IO\FilePathNormalizerInterface;
use eZ\Publish\Core\Persistence\Legacy\Content\UrlAlias\SlugConverter;
use League\Flysystem\Util;

/**
 * Make predictable hashes for testing.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class TestFilePathNormalizer implements FilePathNormalizerInterface
{
    public function __construct(protected SlugConverter $slugConverter)
    {
    }

    public function normalizePath(string $filePath, bool $doHash = true): string
    {
        $fileName = pathinfo($filePath, \PATHINFO_BASENAME);
        $directory = pathinfo($filePath, \PATHINFO_DIRNAME);

        $fileName = $this->slugConverter->convert($fileName);

        // No hash for tests
        $hash = '';

        $filePath = $directory.\DIRECTORY_SEPARATOR.$hash.$fileName;

        return Util::normalizePath($filePath);
    }
}

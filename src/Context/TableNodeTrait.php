<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Gherkin\Node\TableNode;
use Webmozart\Assert\Assert;

trait TableNodeTrait
{
    /** @return array<string,string> */
    private function rowsHash(?TableNode $table): array
    {
        $data = $table?->getRowsHash() ?? [];
        foreach ($data as $key => $value) {
            Assert::scalar($value);
        }

        return $data;
    }
}

<?php
declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\State;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;

class State
{
    protected ?Content $lastContent = null;

    public function reset(): void
    {
        $this->lastContent = null;
    }

    public function getLastContent(): Content
    {
        if (null === $this->lastContent) {
            throw new \RuntimeException('No content object created before');
        }

        return $this->lastContent;
    }

    public function setLastContent(Content $lastContent): void
    {
        $this->lastContent = $lastContent;
    }
}
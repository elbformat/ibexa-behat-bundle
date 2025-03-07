<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Elbformat\IbexaBehatBundle\State\State;
use Ibexa\Contracts\Core\Repository\Repository;

/**
 * Modify the content's object state
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class ObjectstateContext implements Context
{
    public function __construct(
        protected Repository $repo,
        protected State $state,
    ) {
    }

    #[Given('objectstate :groupName is :stateName')]
    public function objectstateIs($groupName, $stateName): void
    {
        $this->repo->sudo(function (Repository $repo) use ($groupName, $stateName) {
            $svc = $repo->getObjectStateService();
            $stateGroup = $svc->loadObjectStateGroupByIdentifier($groupName);
            $state = $svc->loadObjectStateByIdentifier($stateGroup, $stateName);
            $repo->getObjectStateService()->setContentState($this->state->getLastContent()->contentInfo, $stateGroup, $state);
        });
    }
}

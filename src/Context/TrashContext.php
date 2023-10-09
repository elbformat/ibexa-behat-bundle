<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Doctrine\ORM\EntityManagerInterface;
use Elbformat\IbexaBehatBundle\State\State;
use Elbformat\SymfonyBehatBundle\Context\AbstractDatabaseContext;
use eZ\Publish\API\Repository\Values\Content\TrashItem;
use Ibexa\Contracts\Core\Repository\Repository;

/**
 * Basic creating and testing contents and locations.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class TrashContext extends AbstractDatabaseContext
{
    public function __construct(
        protected Repository $repo,
        EntityManagerInterface $em,
        protected State $state,
        protected int $minId,
    ) {
        parent::__construct($em);
    }

    #[BeforeScenario]
    public function resetDb(): void
    {
        // Content
        $this->exec('DELETE FROM `ezcontentobject_trash` WHERE contentobject_id >= ' . $this->minId);
    }

    #[Given('the content object is trashed')]
    #[Given('the content object :id is trashed')]
    public function theContentObjectIsTrashed(?int $id=null): void
    {
        $this->repo->sudo(function (Repository $repo) use ($id) {
            $svc = $repo->getTrashService();
            if (null === $id) {
                $locationId = $this->state->getLastContent()->contentInfo->mainLocationId;
            } else {
                $locationId = $repo->getContentService()->loadContentInfo($id)->mainLocationId;
            }
            $location = $repo->getLocationService()->loadLocation($locationId);
            $svc->trash($location);
        });
    }

    #[Then('there exists no content object with id :id in trash')]
    public function thereExistsNoContentObjectWithIdInTrash(int $id): void
    {
        $this->repo->sudo(function (Repository $repo) use ($id) {
            $svc = $repo->getTrashService();
            try {
                $svc->loadTrashItem($id);
            } catch (\eZ\Publish\Core\Base\Exceptions\NotFoundException $e) {
                return;
            }
            throw new \Exception('Content found');
        });
    }

    #[Then('there exists a content object with id :id in trash')]
    public function thereExistsAContentObjectWithIdInTrash(int $id): void
    {
        $this->repo->sudo(function (Repository $repo) use ($id) {
            $svc = $repo->getTrashService();
            $svc->loadTrashItem($id);
        });
    }

    protected function getClassName(): string
    {
        return TrashItem::class;
    }

}

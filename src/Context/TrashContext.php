<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Elbformat\FieldHelperBundle\Registry\RegistryInterface;
use Elbformat\SymfonyBehatBundle\Context\AbstractDatabaseContext;
use Exception;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentStruct;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\VersionInfo;
use eZ\Publish\Api\Repository\Values\ContentType\ContentType;
use eZ\Publish\Core\Base\Exceptions\ContentFieldValidationException;
use eZ\Publish\Core\FieldType\Checkbox\Value as CheckboxValue;
use eZ\Publish\Core\FieldType\RelationList\Value as RelListValue;
use eZ\Publish\Core\FieldType\Selection\Value as SelectionValue;
use eZ\Publish\Core\FieldType\Url\Value as UrlValue;
use eZ\Publish\Core\Repository\SiteAccessAware\Repository;
use EzSystems\EzPlatformMatrixFieldtype\FieldType\Value;
use EzSystems\EzPlatformMatrixFieldtype\FieldType\Value\Row;
use RuntimeException;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Component\HttpKernel\KernelInterface;
use const JSON_THROW_ON_ERROR;

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

    #[Given('the contentobject is trashed')]
    #[Given('the contentobject :id is trashed')]
    public function itIsTrashed(?int $id=null): void
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

}

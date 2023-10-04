<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Behat\Context\Context;
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
use EzSystems\EzPlatformSolrSearchEngine\Gateway;
use RuntimeException;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Component\HttpKernel\KernelInterface;


/**
 * Reset solr between tests.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class SolrContext implements Context
{
    public function __construct(
        protected int $minId,
        protected Gateway $solrGateway,
    ) {
    }

    #[BeforeScenario]
    public function resetSolr(): void
    {
        // Delete test data from solr index
        $this->solrGateway->deleteByQuery(sprintf('content_id_normalized_i:[%d TO *]', $this->minId));
    }
}

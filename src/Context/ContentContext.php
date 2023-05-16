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
class ContentContext extends AbstractDatabaseContext
{
    use ContentFieldValidationTrait;

    protected string $cacheDir;
    protected ?Content $lastContent;

    public function __construct(
        protected KernelInterface $kernel,
        protected Repository $repo,
        protected RegistryInterface $fieldHelperRegistry,
        EntityManagerInterface $em,
        protected Psr6CacheClearer $cache,
        protected int $minId,
    ) {
        parent::__construct($em);
        $this->cacheDir = $kernel->getContainer()->getParameter('kernel.cache_dir');
    }

    #[BeforeScenario]
    public function resetDb(): void
    {
        // Content
        $this->exec('DELETE FROM `ezcontentobject` WHERE id >= '.$this->minId);
        $this->exec('DELETE FROM `ezcontentobject_attribute` WHERE contentobject_id >= '.$this->minId);
        $this->exec('DELETE FROM `ezcontentobject_name` WHERE contentobject_id >= '.$this->minId);
        $this->exec('DELETE FROM `ezcontentobject_version` WHERE contentobject_id >= '.$this->minId);
        $this->exec('DELETE FROM `ezcontentobject_tree` WHERE node_id >= '.$this->minId);
        $this->exec('DELETE FROM `ezurlalias_ml_incr` WHERE id >= '.$this->minId);
        $this->exec('DELETE FROM `ezurlalias_ml` WHERE id >= '.$this->minId);
        $this->exec('DELETE FROM `ezcontentobject_link` WHERE from_contentobject_id >= '.$this->minId.' OR to_contentobject_id >= '.$this->minId);
        $this->exec('ALTER TABLE `ezcontentobject` AUTO_INCREMENT='.$this->minId);
        $this->exec('ALTER TABLE `ezcontentobject_attribute` AUTO_INCREMENT='.$this->minId);
        $this->exec('ALTER TABLE `ezcontentobject_tree` AUTO_INCREMENT='.$this->minId);
        $this->exec('ALTER TABLE `ezurlalias_ml_incr` AUTO_INCREMENT='.$this->minId);

        // Clear cache
        $this->cache->clear($this->cacheDir);
    }

    #[Given('there is a(n) :contentType content object')]
    #[Given('there is another :contentType content object')]
    public function thereIsAContentObject($contentType, TableNode $table = null): void
    {
        $this->createContent($contentType, $table ? $table->getRowsHash() : []);
    }

    #[Given('the content object has a translation in :languageCode')]
    public function theContentObjectHasATranslationIn(string $languageCode, TableNode $table = null): void
    {
        /** @var Content $draft */
        $draft = $this->repo->sudo(
            fn (Repository $repo) => $repo->getContentService()->createContentDraft($this->lastContent->contentInfo)
        );
        $updateStruct = $this->repo->getContentService()->newContentUpdateStruct();
        $updateStruct->initialLanguageCode = $languageCode;

        // Copy data
        foreach ($draft->getFields() as $field) {
            $updateStruct->setField($field->fieldDefIdentifier, $field->value);
        }

        // Map overwritten fields
        $this->mapFields($table ? $table->getRowsHash() : [], $draft->getContentType(), $updateStruct);

        // Save and publish
        try {
            $this->repo->sudo(function (Repository $repository) use ($draft, $languageCode, $updateStruct): void {
                $updated = $repository->getContentService()->updateContent($draft->versionInfo, $updateStruct);
                $this->lastContent = $repository->getContentService()->publishVersion(
                    $updated->versionInfo,
                    [$languageCode]
                );
            });
        } catch (ContentFieldValidationException $e) {
            $this->convertContentFieldValidationException($e);
        }
    }

    #[Given('the content object is hidden')]
    public function theContentObjectIsHidden(): void
    {
        $this->repo->sudo(
            function (Repository $repo): void {
                $contentSvc = $repo->getContentService();
                $contentSvc->hideContent($this->lastContent->contentInfo);
            }
        );
    }

    #[Given('the page has a location in :parentLocation')]
    public function theContentHasALocationIn(int $parentLocation): void
    {
        /** @var Content $draft */
        $struct = $this->repo->sudo(
            fn (Repository $repo) => $repo->getLocationService()->newLocationCreateStruct($parentLocation)
        );
        $contentInfo = $this->lastContent->contentInfo;

        // Save and publish
        try {
            $this->repo->sudo(function (Repository $repository) use ($contentInfo, $struct): void {
                $updated = $repository->getLocationService()->createLocation(
                    $contentInfo,
                    $struct
                );
            });
        } catch (ContentFieldValidationException $e) {
            $this->convertContentFieldValidationException($e);
        }
    }

    #[Then('there exists a(n) :contentType content object')]
    public function thereExistsAContentObject($contentType, TableNode $table = null): void
    {
        $criterion = $this->getCriterion($contentType, $table);
        $content = $this->repo->sudo(
            function () use ($contentType, $criterion) {
                try {
                    $content = $this->repo->getSearchService()->findSingle($criterion);
                } catch (NotFoundException $e) {
                    try {
                        $content = $this->repo->getSearchService()->findSingle($criterion);
                    } catch (NotFoundException $e) {
                        $query = new Query();
                        $query->filter = new Criterion\ContentTypeIdentifier($contentType);
                        $contents = $this->repo->getSearchService()->findContent($query);
                        if ($contents->totalCount > 0) {
                            /** @var Content $content */
                            $content = $contents->searchHits[0]->valueObject;
                            $fields = [
                                'RemoteId' => $content->contentInfo->remoteId,
                            ];
                            foreach ($content->getFields() as $field) {
                                $def = sprintf('%s [%s]', $field->fieldDefIdentifier, $field->fieldTypeIdentifier);
                                $fields[$def] = (string) $field->value;
                            }
                            $msg = sprintf("Entry not found. Did you mean\n%s", var_export($fields, true));
                            throw new Exception($msg);
                        }
                        throw new Exception('No content found');
                    }
                }

                return $content;
            }
        );

        $this->postCheck($contentType, $table, $content);

        $this->lastContent = $content;
    }

    #[Then('there must not be a(n) :contentType content object')]
    public function thereMustNotBeAContentObject($contentType, TableNode $table = null): void
    {
        $criterion = $this->getCriterion($contentType, $table);

        // Wait some time for SolR.
        try {
            $content = $this->repo->sudo(
                fn () => $this->searchSvc->findSingle($criterion)
            );
        } catch (NotFoundException $e) {
            return;
        }

        try {
            $this->postCheck($contentType, $table, $content);
        } catch (DomainException $e) {
            return;
        }

        throw new Exception('Content with this criteria was still found');
    }

    #[Then('the content object field :field must contain')]
    public function theContentObjectFieldMustContain(string $field, PyStringNode $text): void
    {
        if (null === $this->lastContent) {
            throw new DomainException('No content object found');
        }
        $value = $this->getPlainFieldValue($this->lastContent->getContentType(), $this->lastContent, $field);
        if (!str_contains($value, $text->getRaw())) {
            throw new DomainException(sprintf("Text not found in \n%s", $value));
        }
    }

    public function getLastContent(): Content
    {
        if (null === $this->lastContent) {
            throw new RuntimeException('No content object created before');
        }

        return $this->lastContent;
    }

    public function setLastContent(Content $lastContent): void
    {
        $this->lastContent = $lastContent;
    }

    public function getMinId(): ?int
    {
        return $this->minId;
    }
    
    protected function getClassName(): string
    {
        return Content::class;
    }

    /** @param array<string, string> $data */
    protected function createContent($contentType, array $data): void
    {
        $ct = $this->repo->getContentTypeService()->loadContentTypeByIdentifier($contentType);

        $languageCode = $data['_languageCode'] ?? $ct->mainLanguageCode;
        $struct = $this->repo->getContentService()->newContentCreateStruct($ct, $languageCode);
        $parentLocationId = $data['_parentLocationId'] ?? 2;
        $locationStruct = $this->repo->getLocationService()->newLocationCreateStruct((int) $parentLocationId);
        $fieldMappings = [];
        foreach ($data as $field => $value) {
            switch ($field) {
                case '_languageCode':
                case '_parentLocationId':
                case '_publish':
                    // handled earlier or later
                    break;
                case '_remoteId':
                    $struct->remoteId = (string) $value;
                    break;
                case '_hidden':
                    $locationStruct->hidden = (bool) $value;
                    break;
                case '_sectionId':
                    $sectionId = $this->repo->sudo(
                        fn (Repository $repo) => $repo->getSectionService()->loadSectionByIdentifier($value)->id
                    );
                    $struct->sectionId = $sectionId;
                    break;
                default:
                    $fieldMappings[$field] = $value;
                    break;
            }
        }

        // Map fields
        $this->mapFields($fieldMappings, $ct, $struct);

        try {
            $this->repo->sudo(
                function (Repository $repo) use ($struct, $locationStruct): void {
                    $this->lastContent = $repo->getContentService()->createContent($struct, [$locationStruct]);
                }
            );
        } catch (ContentFieldValidationException $e) {
            $this->convertContentFieldValidationException($e);
        }
        if ($data['_publish'] ?? true) {
            $this->publishContent($this->lastContent->versionInfo);
        }
    }

    protected function mapFields(array $data, ContentType $contentType, ContentStruct $struct): void
    {
        foreach ($data as $field => $value) {
            $struct->setField($field, $this->getFieldDefValue($contentType, $field, $value));
        }
    }

    protected function getFieldDefValue(ContentType $ct, string $field, string $value)
    {
        $fieldDef = $ct->getFieldDefinition($field);
        switch ($fieldDef->fieldTypeIdentifier) {
            case 'ezselection':
                return new SelectionValue([$value]);

            case 'ezurl':
                return new UrlValue($value);

            case 'ezdate':
                return new DateTime($value, new DateTimeZone('UTC'));
                break;

            // RelationList
            case 'ezobjectrelationlist':
                return new RelListValue(explode(',', $value));
                break;

            // Image
            case 'ezimageasset':
                if (is_numeric($value)) {
                    return new \eZ\Publish\Core\FieldType\ImageAsset\Value($value);
                }
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

                return new \eZ\Publish\Core\FieldType\ImageAsset\Value($value['id'], $value['alt'] ?? '');
            case 'ezimage':
                if (str_starts_with($value, '{')) {
                    $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    $path = $value['path'];
                    $alt = $value['alt'];
                } else {
                    $path = $value;
                }
                $data = [
                    'inputUri' => __DIR__.'/../../'.$path,
                    'fileName' => basename($path),
                    'fileSize' => filesize(__DIR__.'/../../'.$path),
                    'alternativeText' => $alt ?? '',
                ];

                return new \eZ\Publish\Core\FieldType\Image\Value($data);
            case 'ezbinaryfile':
                $data = [
                    'inputUri' => __DIR__.'/../fixtures/'.$value,
                    'fileName' => $value,
                    'fileSize' => filesize(__DIR__.'/../fixtures/'.$value),
                ];

                return new \eZ\Publish\Core\FieldType\BinaryFile\Value($data);
            case 'ezuser':
                [$login, $email] = explode('/', $value);

                return new \eZ\Publish\Core\FieldType\User\Value(['login' => $login, 'email' => $email]);

            case 'ezboolean':
                return new CheckboxValue((bool) $value);

            case 'ezinteger':
                return new \eZ\Publish\Core\FieldType\Integer\Value((int) $value);

            case 'ezmatrix':
                $rows = [];
                $json = json_decode($value, true);
                if (null === $json) {
                    throw new Exception(json_last_error_msg());
                }
                foreach ($json as $row) {
                    $rows[] = new Row($row);
                }

                return new Value($rows);

            case 'ezrichtext':
                // Wrap xml around, when plain text
                if (!str_starts_with($value, '<?xml')) {
                    $value = sprintf(
                        '<?xml version="1.0" encoding="UTF-8"?><section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:ezxhtml="http://ez.no/xmlns/ezpublish/docbook/xhtml" xmlns:ezcustom="http://ez.no/xmlns/ezpublish/docbook/custom" version="5.0-variant ezpublish-1.0"><para>%s</para></section>',
                        $value
                    );
                }

                return $value;

            case 'ezauthor':
                if (str_starts_with($value, '{')) {
                    $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
                    $id = $value['id'];
                    $name = $value['name'];
                    $email = $value['email'];
                } else {
                    $name = $value;
                }
                $authorValue = new \eZ\Publish\Core\FieldType\Author\Author();
                $authorValue->id = $id ?? 0;
                $authorValue->name = $name;
                $authorValue->email = $email ?? '';

                return new \eZ\Publish\Core\FieldType\Author\Value([$authorValue]);

            case 'eztags':
                $list = [];
                /** @var NetgenTagsFieldHelper $tagsFieldHelper */
                $tagsFieldHelper = $this->fieldHelperRegistry->getFieldHelper('App\FieldHelper\NetgenTagsFieldHelper');
                foreach (explode(',', $value) as $tagId) {
                    if (($tag = $tagsFieldHelper->loadTag((int) $tagId)) !== null) {
                        $list[] = $tag;
                    }
                }

                return $tagsFieldHelper->getTagsList($list);

            case 'novaseometas':
                $metaFieldsData = [];
                if (str_starts_with($value, '{')) {
                    $metaFieldsData = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
                } else {
                    throw new Exception('Field of type novaseometas expect JSON like {"title": "title", "description": "desc"}');
                }

                /** @var NovaSeoMetaFieldHelper $novaSeoMetaFieldHelper */
                $novaSeoMetaFieldHelper = $this->fieldHelperRegistry->getFieldHelper(
                    'App\FieldHelper\NovaSeoMetaFieldHelper'
                );

                return $novaSeoMetaFieldHelper->mapFieldsToMetasValues($metaFieldsData);

            default:
                if (preg_match('/^FIXTURE\[(.*)\]FIXTURE$/', $value, $match)) {
                    $value = file_get_contents(__DIR__.'/../fixtures/'.$match[1]);
                }

                return $value;
        }
    }

    protected function publishContent(VersionInfo $versionInfo): Content
    {
        try {
            return $this->repo->sudo(
                fn (Repository $repo) => $repo->getContentService()->publishVersion($versionInfo)
            );
        } catch (ContentFieldValidationException $e) {
            $this->convertContentFieldValidationException($e);
        }
    }

    protected function getCriterion(string $contentType, TableNode $table = null)
    {
        $criterions = [];
        $criterions[] = new Criterion\ContentTypeIdentifier($contentType);
        $ct = $this->repo->getContentTypeService()->loadContentTypeByIdentifier($contentType);
        if (null !== $table) {
            foreach ($table->getRowsHash() as $key => $value) {
                $fieldType = $ct->getFieldDefinition($key)->fieldTypeIdentifier;
                switch ($fieldType) {
                    case 'ezinteger':
                    case 'ezstring':
                        $criterions[] = new Criterion\Field($key, Criterion\Operator::EQ, $value);
                        break;
                    case 'eztags':
                    case 'ezurl':
                        $toCheck[$key] = $value;
                        break;
                    case 'ezdatetime':
                        $date = new DateTime($value);
                        $criterions[] = new Criterion\Field($key, Criterion\Operator::EQ, $date->getTimestamp());
                        break;
                    default:
                        if ('remoteid' === strtolower($key)) {
                            $criterions[] = new Criterion\RemoteId((string) $value);
                            break;
                        } else {
                            throw new Exception('Unknown fieldType '.$fieldType);
                        }
                }
            }
        }

        return new Criterion\LogicalAnd($criterions);
    }

    protected function postCheck(string $contentType, TableNode $table = null, Content $content): void
    {
        $ct = $this->repo->getContentTypeService()->loadContentTypeByIdentifier($contentType);
        if (null !== $table) {
            foreach ($table->getRowsHash() as $key => $value) {
                $fieldType = $ct->getFieldDefinition($key)->fieldTypeIdentifier;
                // only these fields need a post-check as they are not searchable
                if (!\in_array($fieldType, ['eztags', 'ezurl'])) {
                    continue;
                }
                $contentVal = $this->getPlainFieldValue($ct, $content, $key);
                if ($contentVal !== $value) {
                    $msg = sprintf("Field value differs: Found '%s' but expected '%s'", $contentVal, $value);
                    throw new DomainException($msg);
                }
            }
        }
    }

    protected function getPlainFieldValue(ContentType $ct, Content $content, string $field): string
    {
        $fieldType = $ct->getFieldDefinition($field)->fieldTypeIdentifier;
        switch ($fieldType) {
            default:
                return (string) $content->getField($field)->value;
        }
    }

}

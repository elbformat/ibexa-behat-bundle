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
use Elbformat\IbexaBehatBundle\State\State;
use Elbformat\SymfonyBehatBundle\Context\AbstractDatabaseContext;
use Exception;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Base\Exceptions\ContentFieldValidationException;
use Ibexa\Core\FieldType\Url\Value as UrlValue;
use Ibexa\Contracts\Core\Repository\Repository;
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
    protected int $attributeOffset = 0;
    protected const ATTRIBUTE_INCREMENT = 100;

    public function __construct(
        protected KernelInterface $kernel,
        protected Repository $repo,
        protected RegistryInterface $fieldHelperRegistry,
        EntityManagerInterface $em,
        protected Psr6CacheClearer $cache,
        protected int $minId,
        protected string $rootFolder,
        protected State $state,
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
        $this->attributeOffset = 0;

        // Clear cache
        $this->cache->clear($this->cacheDir);

        $this->state->reset();
    }

    #[Given('there is a(n) :contentType content object')]
    #[Given('there is another :contentType content object')]
    public function thereIsAContentObject($contentType, TableNode $table = null): void
    {
        $this->createContent($contentType, $table ? $table->getRowsHash() : []);
        $this->attributeOffset+=self::ATTRIBUTE_INCREMENT;
        $this->exec('ALTER TABLE `ezcontentobject_attribute` AUTO_INCREMENT='.$this->minId+$this->attributeOffset);
    }

    #[Given('the content object has a translation in :languageCode')]
    #[Given('the content object :id has a translation in :languageCode')]
    public function theContentObjectHasATranslationIn(string $languageCode, TableNode $table = null, ?int $id = null): void
    {
        /** @var Content $draft */
        $draft = $this->repo->sudo(
            function(Repository $repo) use ($id) { return $repo->getContentService()->createContentDraft($this->getContentInfo($id)); }
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
                $lastContent = $repository->getContentService()->publishVersion(
                    $updated->versionInfo,
                    [$languageCode]
                );
                $this->state->setLastContent($lastContent);
            });
        } catch (ContentFieldValidationException $e) {
            $this->convertContentFieldValidationException($e);
        }
    }

    #[Given('the content object is hidden')]
    #[Given('the content object :id is hidden')]
    public function theContentObjectIsHidden(?int $id = null): void
    {
        $this->repo->sudo(
            function (Repository $repo) use ($id): void {
                $contentSvc = $repo->getContentService();
                $contentInfo = $this->getContentInfo($id);
                $contentSvc->hideContent($contentInfo);
            }
        );
    }

    #[Given('the content object has another location in :parentLocation')]
    #[Given('the content object :id has another location in :parentLocation')]
    public function theContentHasALocationIn(int $parentLocation, ?int $id = null): void
    {
        /** @var Content $draft */
        $struct = $this->repo->sudo(
            fn(Repository $repo) => $repo->getLocationService()->newLocationCreateStruct($parentLocation)
        );
        $contentInfo = $this->getContentInfo($id);

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

    #[Given('the location is hidden')]
    #[Given('the location :id is hidden')]
    public function theLocationIsHidden(?int $id = null): void
    {
        $this->repo->sudo(
            function (Repository $repo) use ($id): void {
                $locationSvc = $repo->getLocationService();
                $location = $locationSvc->loadLocation($id ?? $this->getContentInfo(null)->mainLocationId);
                $locationSvc->hideLocation($location);
            }
        );
    }

    #[Then('there exists a(n) :contentType content object')]
    public function thereExistsAContentObject($contentType, TableNode $table = null): void
    {
        $criterion = $this->getAllCriterion($contentType, $table);
        $content = $this->repo->sudo(
            function (Repository $repository) use ($contentType, $criterion) {
                try {
                    $content = $repository->getSearchService()->findSingle($criterion);
                } catch (NotFoundException $e) {
                    try {
                        $content = $repository->getSearchService()->findSingle($criterion);
                    } catch (NotFoundException $e) {
                        $query = new Query();
                        $query->filter = new Criterion\ContentTypeIdentifier($contentType);
                        $contents = $repository->getSearchService()->findContent($query);
                        if ($contents->totalCount > 0) {
                            /** @var Content $content */
                            $content = $contents->searchHits[0]->valueObject;
                            $fields = [
                                'RemoteId' => $content->contentInfo->remoteId,
                            ];
                            foreach ($content->getFields() as $field) {
                                $def = sprintf('%s [%s]', $field->fieldDefIdentifier, $field->fieldTypeIdentifier);
                                $fields[$def] = (string)$field->value;
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

        $this->postCheckAll($contentType, $table, $content);

        $this->state->setLastContent($content);
    }

    #[Then('there is no :contentType content object')]
    #[Then('there exists no :contentType content object')]
    public function thereIsNoContentObject($contentType, TableNode $table = null): void
    {
        $criterion = $this->getAllCriterion($contentType, $table);

        try {
            $content = $this->repo->sudo(
                fn(Repository $repository) => $repository->getSearchService()->findSingle($criterion)
            );
        } catch (NotFoundException $e) {
            return;
        }

        try {
            $this->postCheckAll($contentType, $table, $content);
        } catch (DomainException $e) {
            return;
        }

        throw new Exception('Content with this criteria was still found');
    }

    #[Then('the content object field :field must contain')]
    #[Then('the content object :id field :field must contain')]
    public function theContentObjectFieldMustContain(string $field, PyStringNode $text, ?int $id=null): void
    {
        $contentInfo = $this->getContentInfo($id);
        $content = $this->repo->sudo(function(Repository $repo) use ($contentInfo) {
            return $repo->getContentService()->loadContentByContentInfo($contentInfo);
        });
        $value = $this->getPlainFieldValue($contentInfo->getContentType(), $content, $field);
        if (!str_contains($value, $text->getRaw())) {
            throw new DomainException(sprintf("Text not found in \n%s", $value));
        }
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
        $locationStruct = $this->repo->getLocationService()->newLocationCreateStruct((int)$parentLocationId);
        $fieldMappings = [];
        foreach ($data as $field => $value) {
            switch ($field) {
                case '_languageCode':
                case '_parentLocationId':
                case '_publish':
                    // handled earlier or later
                    break;
                case '_remoteId':
                    $struct->remoteId = (string)$value;
                    break;
                case '_hidden':
                    $locationStruct->hidden = (bool)$value;
                    break;
                case '_sortField':
                    $constVal = constant(sprintf('%s::SORT_FIELD_%s',Location::class, strtoupper((string)$value)));
                    $locationStruct->sortField = $constVal;
                    break;
                case '_sortOrder':
                    $constVal = constant(sprintf('%s::SORT_ORDER_%s', Location::class, strtoupper((string)$value)));
                    $locationStruct->sortOrder = $constVal;
                    break;
                case '_sectionId':
                    $sectionId = $this->repo->sudo(
                        fn(Repository $repo) => $repo->getSectionService()->loadSectionByIdentifier($value)->id
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
                    $lastContent = $repo->getContentService()->createContent($struct, [$locationStruct]);
                    $this->state->setLastContent($lastContent);
                }
            );
            if ($data['_publish'] ?? true) {
                $lastContent = $this->publishContent($this->state->getLastContent()->versionInfo);
                $this->state->setLastContent($lastContent);
            }
        } catch (ContentFieldValidationException $e) {
            $this->convertContentFieldValidationException($e);
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
        if (null === $fieldDef) {
            throw new \DomainException(sprintf('Could not determine field type for %s in %s',$field,$ct->identifier));
        }
        switch ($fieldDef->fieldTypeIdentifier) {
            case 'ezselection':
                if (is_numeric($value)) {
                    return new \Ibexa\Core\FieldType\Selection\Value([$value]);
                }
                $keyToIndex = array_flip($fieldDef->getFieldSettings()['options']);
                $index = $keyToIndex[$value] ?? null;
                if (null === $index) {
                    return null;
                }

                return new \Ibexa\Core\FieldType\Selection\Value([$index]);

            case 'ezurl':
                if (str_starts_with($value, '{')) {
                    $jsonData = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    $link = $jsonData['link'];
                    $text = $jsonData['text'];
                } else {
                    $link = $value;
                    $text = null;
                }
                return new UrlValue($link, $text);

            case 'ezdate':
                return new DateTime($value, new DateTimeZone('UTC'));

            // RelationList
            case 'ezobjectrelationlist':
                return new \Ibexa\Core\FieldType\RelationList\Value(explode(',', $value));

            // Image
            case 'ezimageasset':
                if (is_numeric($value)) {
                    return new \Ibexa\Core\FieldType\ImageAsset\Value($value);
                }
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

                return new \Ibexa\Core\FieldType\ImageAsset\Value($value['id'], $value['alt'] ?? '');
            case 'ezimage':
                if (str_starts_with($value, '{')) {
                    $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    $path = $value['path'];
                    $alt = $value['alt'];
                } else {
                    $path = $value;
                }
                $data = [
                    'inputUri' => $this->rootFolder.'/'.$path,
                    'fileName' => basename($path),
                    'fileSize' => filesize($this->rootFolder.'/'.$path),
                    'alternativeText' => $alt ?? '',
                ];

                return new \Ibexa\Core\FieldType\Image\Value($data);
            case 'ezbinaryfile':
                $data = [
                    'inputUri' => $this->rootFolder.'/'.$value,
                    'fileName' => basename($value),
                    'fileSize' => filesize($this->rootFolder.'/'.$value),
                ];

                return new \Ibexa\Core\FieldType\BinaryFile\Value($data);
            case 'ezuser':
                [$login, $email] = explode('/', $value);

                return new \Ibexa\Core\FieldType\User\Value(['login' => $login, 'email' => $email]);

            case 'ezboolean':
                return new \Ibexa\Core\FieldType\Checkbox\Value((bool)$value);

            case 'ezinteger':
                return new \Ibexa\Core\FieldType\Integer\Value((int)$value);

            case 'ezmatrix':
                $rows = [];
                $json = json_decode($value, true);
                if (null === $json) {
                    throw new Exception(json_last_error_msg());
                }
                foreach ($json as $row) {
                    $rows[] = new  \Ibexa\FieldTypeMatrix\FieldType\Value\Row($row);
                }

                return new \Ibexa\FieldTypeMatrix\FieldType\Value($rows);

            case 'ezrichtext':
                // Wrap xml around, when plain text
                if (!str_starts_with($value, '<?xml')) {
                    $value = sprintf(
                        '<?xml version="1.0" encoding="UTF-8"?><section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:ezxhtml="http://ibexa.co/xmlns/dxp/docbook/xhtml" xmlns:ezcustom="http://ibexa.co/xmlns/dxp/docbook/custom" version="5.0-variant ezpublish-1.0"><para>%s</para></section>',
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
                $authorValue = new \Ibexa\Core\FieldType\Author\Author();
                $authorValue->id = $id ?? 0;
                $authorValue->name = $name;
                $authorValue->email = $email ?? '';

                return new \Ibexa\Core\FieldType\Author\Value([$authorValue]);

            default:
                if (preg_match('/^FIXTURE\[(.*)\]FIXTURE$/', $value, $match)) {
                    $value = file_get_contents($this->rootFolder.'/'.$match[1]);
                }

                return $value;
        }
    }

    protected function publishContent(VersionInfo $versionInfo): Content
    {
        try {
            return $this->repo->sudo(
                fn(Repository $repo) => $repo->getContentService()->publishVersion($versionInfo)
            );
        } catch (ContentFieldValidationException $e) {
            $this->convertContentFieldValidationException($e);
        }
    }

    protected function getAllCriterion(string $contentType, TableNode $table = null): Criterion
    {
        $criterions = [];
        $criterions[] = new Criterion\ContentTypeIdentifier($contentType);
        $ct = $this->repo->getContentTypeService()->loadContentTypeByIdentifier($contentType);
        if (null !== $table) {
            foreach ($table->getRowsHash() as $key => $value) {
                $fieldType = $ct->getFieldDefinition($key)->fieldTypeIdentifier;
                $criterion = $this->getCriterion($fieldType, $key, $value);
                if (null !== $criterion) {
                    $criterions[] = $criterion;
                }
            }
        }

        return new Criterion\LogicalAnd($criterions);
    }

    protected function getCriterion(?string $fieldType, string $key, string $value): ?Criterion
    {
        switch ($fieldType) {
            // No criterions available -> needs a post check (after loading the content)
            case 'ezurl':
                return null;
            case 'ezinteger':
            case 'ezstring':
                return new Criterion\Field($key, Criterion\Operator::EQ, $value);
            case 'ezdatetime':
                $date = new DateTime($value);

                return new Criterion\Field($key, Criterion\Operator::EQ, $date->getTimestamp());
            default:
                switch ($key) {
                    case '_contentId':
                        return new Criterion\ContentId((int)$value);
                    case '_remoteId':
                        return new Criterion\RemoteId((string)$value);
                    default:
                        throw new \DomainException(sprintf('Cannot get criterion for fieldType %s', $fieldType));
                }
        }
    }

    protected function postCheckAll(string $contentType, TableNode $table = null, Content $content): void
    {
        $ct = $this->repo->getContentTypeService()->loadContentTypeByIdentifier($contentType);
        if (null !== $table) {
            foreach ($table->getRowsHash() as $key => $val) {
                $fieldType = $ct->getFieldDefinition($key)->fieldTypeIdentifier;
                $this->postCheck($fieldType, $key, $val, $content->getField($key)->value);
            }
        }
    }

    protected function postCheck(?string $fieldType, string $fieldname, string $value, mixed $contentValue): void
    {
        switch ($fieldType) {
            case 'ezurl':
                $contentVal = (string)$contentValue;
                if ($contentVal !== $value) {
                    $msg = sprintf("Field value differs: Found '%s' but expected '%s'", $contentVal, $value);
                    throw new DomainException($msg);
                }

        }
    }

    protected function getPlainFieldValue(ContentType $ct, Content $content, string $field): string
    {
        $fieldType = $ct->getFieldDefinition($field)->fieldTypeIdentifier;
        switch ($fieldType) {
            default:
                return (string)$content->getField($field)->value;
        }
    }

    protected function getContentInfo(?int $id): ContentInfo
    {
        if (null === $id) {
            return $this->state->getLastContent()->contentInfo;
        }

        return $this->repo->sudo(
            function (Repository $repo) use ($id): ContentInfo {
                return $repo->getContentService()->loadContentInfo($id);
            }
        );

    }

}

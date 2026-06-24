<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Doctrine\ORM\EntityManagerInterface;
use Elbformat\IbexaBehatBundle\State\State;
use Elbformat\SymfonyBehatBundle\Context\AbstractDatabaseContext;
use Ibexa\Contracts\Core\Ibexa;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\FieldTypePage\FieldType\LandingPage\Model\Attribute;
use Ibexa\Contracts\FieldTypePage\FieldType\LandingPage\Model\BlockValue;
use Ibexa\Contracts\FieldTypePage\FieldType\LandingPage\Model\Page;
use Ibexa\Contracts\FieldTypePage\FieldType\LandingPage\Model\Zone;
use Ibexa\Core\Base\Exceptions\ContentFieldValidationException;
use Ibexa\FieldTypePage\FieldType\LandingPage\Value;
use Ibexa\FieldTypePage\FieldType\Page\Block\Definition\BlockDefinitionFactoryInterface;
use Ibexa\FieldTypePage\Form\DataTransformer\ScheduleAttributeDataTransformer;
use Ibexa\FieldTypePage\ScheduleBlock\ScheduleBlock;
use JMS\Serializer\SerializerInterface;
use Webmozart\Assert\Assert;

/**
 * Landingpage (blocks) creation.
 *
 * @author Hannes Giesenow <hannes.giesenow@format-h.com>
 *
 * @extends AbstractDatabaseContext<Page>
 */
class LandingpageContext extends AbstractDatabaseContext
{
    use ContentFieldValidationTrait;
    use TableNodeTrait;

    public function __construct(
        EntityManagerInterface $em,
        protected BlockDefinitionFactoryInterface $blockDefFactory,
        protected Repository $repo,
        protected State $state,
        protected int $minId,
        protected SerializerInterface $serializer,
    ) {
        parent::__construct($em);
    }

    #[BeforeScenario]
    public function resetDb(): void
    {
        if (version_compare(Ibexa::VERSION, '5.0.0', '<')) {
            $this->exec('DELETE FROM ezpage_attributes');
            $this->exec('DELETE FROM ezpage_blocks');
            $this->exec('DELETE FROM ezpage_blocks_design');
            $this->exec('DELETE FROM ezpage_blocks_visibility');
            $this->exec('DELETE FROM ezpage_map_attributes_blocks');
            $this->exec('DELETE FROM ezpage_map_blocks_zones');
            $this->exec('DELETE FROM ezpage_map_zones_pages WHERE zone_id >= '.$this->minId);
            $this->exec('DELETE FROM ezpage_pages WHERE content_id >= '.$this->minId);
            $this->exec('DELETE FROM ezpage_zones WHERE id >= '.$this->minId);
            $this->exec('ALTER TABLE `ezpage_blocks` AUTO_INCREMENT='.$this->minId);
            $this->exec('ALTER TABLE `ezpage_zones` AUTO_INCREMENT='.$this->minId);
        } else {
            $this->exec('DELETE FROM ibexa_page_attribute');
            $this->exec('DELETE FROM ibexa_page_block');
            $this->exec('DELETE FROM ibexa_page_block_design');
            $this->exec('DELETE FROM ibexa_page_block_visibility');
            $this->exec('DELETE FROM ibexa_page_map_attribute_block');
            $this->exec('DELETE FROM ibexa_page_map_block_zone');
            $this->exec('DELETE FROM ibexa_page_map_zone_page WHERE zone_id >= '.$this->minId);
            $this->exec('DELETE FROM ibexa_page WHERE content_id >= '.$this->minId);
            $this->exec('DELETE FROM ibexa_page_zone WHERE id >= '.$this->minId);
            $this->exec('ALTER TABLE `ibexa_page_block` AUTO_INCREMENT='.$this->minId);
            $this->exec('ALTER TABLE `ibexa_page_zone` AUTO_INCREMENT='.$this->minId);
        }
    }

    #[Given('the page contains a(n) :blockType block')]
    #[Given('the page contains a(n) :blockType block in zone :zoneName')]
    #[Given('the page :id contains a(n) :blockType block')]
    #[Given('the page :id contains a(n) :blockType block in zone :zoneName')]
    public function thePageContainsABlock(string $blockType, ?TableNode $table = null, ?string $zoneName = null, ?int $id = null): void
    {
        // Extract attributes
        $data = $this->rowsHash($table);
        Assert::notEmpty($blockType);
        $blockDef = $this->blockDefFactory->getBlockDefinition($blockType);
        $attribs = [];
        foreach ($blockDef->getAttributes() as $attributeDefinition) {
            $blockId = $attributeDefinition->getIdentifier();
            if (!isset($data[$blockId])) {
                continue;
            }
            $value = $this->getValueByType($data[$blockId], $attributeDefinition->getType());
            $attribs[] = new Attribute('0', $blockId, $value);
        }

        // Create block
        $view = $data['view'] ?? 'default';
        $name = $data['name'] ?? uniqid('', false);
        $block = new BlockValue('', $blockType, $name, $view, null, null, null, null, null, $attribs);

        // Load layout
        if (null === $id) {
            $lastContent = $this->state->getLastContent();
        } else {
            $lastContent = $this->repo->sudo(static fn (Repository $repo) => $repo->getContentService()->loadContent($id));
        }
        Assert::notNull($lastContent);
        $fieldName = $this->getFieldNameByContent($lastContent);
        $landingPage = $lastContent->getField($fieldName)?->value;
        Assert::isInstanceOf($landingPage, Value::class);
        $zone = $this->getZoneByLandingpage($landingPage, $zoneName);
        $zone->addBlock($block);

        // Save layout
        $struct = $this->repo->getContentService()->newContentUpdateStruct();
        $struct->setField($fieldName, $landingPage);

        try {
            $newContent = $this->repo->sudo(
                static function (Repository $repo) use ($struct, $lastContent) {
                    $contentSvc = $repo->getContentService();
                    $draft = $contentSvc->createContentDraft($lastContent->contentInfo);
                    $draft = $contentSvc->updateContent($draft->versionInfo, $struct);

                    return $contentSvc->publishVersion($draft->versionInfo);
                }
            );
            $this->state->setLastContent($newContent);
        } catch (ContentFieldValidationException $e) {
            $this->convertContentFieldValidationException($e);
        }
    }

    protected function getClassName(): string
    {
        return Page::class;
    }

    protected function getZoneByLandingpage(Value $landingPage, ?string $zoneName): Zone
    {
        $foundZones = [];
        foreach ($landingPage->getPage()?->getZones() ?? [] as $zone) {
            if ($zoneName === $zone->getName() || $zoneName === $zone->getId() || null === $zoneName) {
                return $zone;
            }
            $foundZones[] = $zone->getName();
        }
        throw new \DomainException(\sprintf('Zone %s not found in block. Only %s available', $zoneName, implode(',', $foundZones)));
    }

    protected function getFieldNameByContent(Content $content): string
    {
        foreach ($content->getFields() as $field) {
            if (\in_array($field->getFieldTypeIdentifier(), ['ibexa_landing_page', 'ezlandingpage'])) {
                return $field->getFieldDefinitionIdentifier();
            }
        }
        throw new \DomainException('Could not find a landingpage in content-type '.$content->getContentType()->identifier);
    }

    protected function getValueByType(?string $value, string $type): mixed
    {
        if (null === $value || 'NULL' === $value) {
            return null;
        }
        switch ($type) {
            case 'embedform':
            case 'embed':
            case 'integer':
                return (int) $value;
                // Scheduler
            case 'schedule_'.ScheduleBlock::ATTRIBUTE_INITIAL_ITEMS:
            case 'schedule_'.ScheduleBlock::ATTRIBUTE_SNAPSHOTS:
            case 'schedule_'.ScheduleBlock::ATTRIBUTE_EVENTS:
            case 'schedule_'.ScheduleBlock::ATTRIBUTE_LOADED_SNAPSHOT:
            case 'schedule_'.ScheduleBlock::ATTRIBUTE_SLOTS:
                $transformer = new ScheduleAttributeDataTransformer($this->serializer, str_replace('schedule_', '', $type));

                return $transformer->reverseTransform($value);
            default:
                return $value;
        }
    }
}

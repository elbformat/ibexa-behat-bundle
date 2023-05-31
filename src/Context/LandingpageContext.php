<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Doctrine\ORM\EntityManagerInterface;
use Elbformat\SymfonyBehatBundle\Context\AbstractDatabaseContext;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\Core\Base\Exceptions\ContentFieldValidationException;
use eZ\Publish\Core\Repository\SiteAccessAware\Repository;
use EzSystems\EzPlatformPageFieldType\FieldType\LandingPage\Model\Attribute;
use EzSystems\EzPlatformPageFieldType\FieldType\LandingPage\Model\BlockValue;
use EzSystems\EzPlatformPageFieldType\FieldType\LandingPage\Model\Page;
use EzSystems\EzPlatformPageFieldType\FieldType\LandingPage\Model\Zone;
use EzSystems\EzPlatformPageFieldType\FieldType\LandingPage\Value;
use Ibexa\FieldTypePage\FieldType\Page\Block\Definition\BlockDefinitionFactoryInterface;

/**
 * Landingpage (blocks) creation.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class LandingpageContext extends AbstractDatabaseContext
{
    use ContentFieldValidationTrait;

    public function __construct(
        EntityManagerInterface $em,
        protected BlockDefinitionFactoryInterface $blockDefFactory,
        protected Repository $repo,
        protected ?ContentContext $contentContext = null
    ) {
        parent::__construct($em);
    }

    #[BeforeScenario]
    public function resetDb(): void
    {
        $minId = 1000;
        $this->exec('DELETE FROM ezpage_attributes');
        $this->exec('DELETE FROM ezpage_blocks');
        $this->exec('DELETE FROM ezpage_blocks_design');
        $this->exec('DELETE FROM ezpage_blocks_visibility');
        $this->exec('DELETE FROM ezpage_map_attributes_blocks');
        $this->exec('DELETE FROM ezpage_map_blocks_zones');
        $this->exec('DELETE FROM ezpage_map_zones_pages WHERE zone_id >= '.$minId);
        $this->exec('DELETE FROM ezpage_pages WHERE content_id >= '.$this->contentContext->getMinId());
        $this->exec('DELETE FROM ezpage_zones WHERE id >= '.$minId);
        $this->exec('ALTER TABLE `ezpage_blocks` AUTO_INCREMENT='.$minId);
        $this->exec('ALTER TABLE `ezpage_zones` AUTO_INCREMENT='.$minId);
    }

    #[BeforeScenario]
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        // @rfe: maybe we can fetch the symfony service instead?
        $environment = $scope->getEnvironment();

        // Check each context, if it is a (sub)class of the ContentContext
        foreach($environment->getContextClasses() as $contextClass) {
            $context = $environment->getContext($contextClass);
            if ($context instanceof ContentContext) {
                $this->contentContext = $context;
                return;
            }
        }
        // Use the default to raise a sensible exception when no ContentContext was found.
        $this->contentContext = $environment->getContext(ContentContext::class);
    }

    #[Given('the page contains a(n) :blockType block')]
    #[Given('the page contains a(n) :blockType block in zone :zone')]
    public function thePageContainsABlock($blockType, TableNode $table = null, $zoneName = null): void
    {
        // Extract attributes
        $data = null !== $table ? $table->getRowsHash() : [];
        $blockDef = $this->blockDefFactory->getBlockDefinition($blockType);
        $attribs = [];
        foreach ($blockDef->getAttributes() as $attributeDefinition) {
            $blockId = $attributeDefinition->getIdentifier();
            if (!isset($data[$blockId])) {
                continue;
            }
            $value = $data[$blockId];
            if (\in_array($attributeDefinition->getType(), ['embedform', 'embed'], true)) {
                $value = (int)$value;
            }
            $attribs[] = new Attribute('0', $blockId, $value);
        }

        // Create block
        $view = $data['view'] ?? 'default';
        $name = $data['name'] ?? uniqid('', false);
        $block = new BlockValue('', $blockType, $name, $view, null, null, null, null, null, $attribs);

        // Load layout
        $lastContent = $this->getContentContext()->getLastContent();
        $fieldName = $this->getFieldNameByContent($lastContent);
        $landingPage = $lastContent->getField($fieldName)->value;
        $zone = $this->getZoneByLandingpage($landingPage, $zoneName);
        $zone->addBlock($block);

        // Save layout
        $struct = $this->repo->getContentService()->newContentUpdateStruct();
        $struct->setField($fieldName, $landingPage);

        try {
            $newContent = $this->repo->sudo(
                function (Repository $repo) use ($struct, $lastContent) {
                    $contentSvc = $repo->getContentService();
                    $draft = $contentSvc->createContentDraft($lastContent->contentInfo);
                    $draft = $contentSvc->updateContent($draft->versionInfo, $struct);

                    return $contentSvc->publishVersion($draft->versionInfo);
                }
            );
        } catch (ContentFieldValidationException $e) {
            $this->convertContentFieldValidationException($e);
        }

        $this->getContentContext()->setLastContent($newContent);
    }

    protected function getClassName(): string
    {
        return Page::class;
    }

    public function getContentContext(): ContentContext
    {
        if (null === $this->contentContext) {
            throw new \RuntimeException('ContentContext not gathered yet');
        }

        return $this->contentContext;
    }

    protected function getZoneByLandingpage(Value $landingPage, ?string $zoneName): Zone
    {
        $foundZones = [];
        foreach ($landingPage->getPage()->getZones() as $zone) {
            if ($zoneName === $zone->getName() || $zoneName === $zone->getId() || null === $zoneName) {
                return $zone;
            }
            $foundZones[] = $zone->getName();
        }
        throw new \DomainException(sprintf('Zone %s not found in block. Only %s available', $zoneName, implode(',', $foundZones)));
    }

    protected function getFieldNameByContent(Content $content): string
    {
        foreach ($content->getFields() as $field) {
            if ('ezlandingpage' === $field->getFieldTypeIdentifier()) {
                return $field->getFieldDefinitionIdentifier();
            }
        }
        throw new \DomainException('Could not find a landingpage in content-type '.$content->getContentType()->identifier);
    }
}

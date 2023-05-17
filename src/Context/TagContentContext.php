<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Doctrine\ORM\EntityManagerInterface;
use Elbformat\SymfonyBehatBundle\Context\AbstractDatabaseContext;
use eZ\Publish\Core\Repository\SiteAccessAware\Repository;
use Netgen\TagsBundle\API\Repository\TagsService;
use Netgen\TagsBundle\Core\FieldType\Tags\Value;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Basic creating and testing tags with netgen tags bundle.
 */
class TagContentContext extends AbstractDatabaseContext
{
    protected KernelInterface $kernel;

    public function __construct(
        EntityManagerInterface $em,
        protected Repository $repo,
        protected TagsService $tagsService
    ) {
        parent::__construct($em);
    }

    #[BeforeScenario]
    public function resetDb(): void
    {
        $minId = 1000;
        $this->exec('DELETE FROM `eztags` WHERE id >= '.$minId);
        $this->exec('DELETE FROM `eztags_attribute_link` WHERE id >= '.$minId);
        $this->exec('DELETE FROM `eztags_keyword` WHERE keyword_id >= '.$minIdContentContext::MIN_ID);
        $this->exec('ALTER TABLE `eztags` AUTO_INCREMENT='.$minId);
        $this->exec('ALTER TABLE `eztags_attribute_link` AUTO_INCREMENT='.$minId);
        $this->exec('ALTER TABLE `eztags_keyword` AUTO_INCREMENT='.$minId);
    }

    #[Given('there is a tag in parent tag :parentLocation and main language :mainLanguage')]
    #[Given('there is a tag in parent tag :parentLocation')]
    public function thereIsATag(int $parentLocation, string $mainLanguage = 'eng-GB', TableNode $table = null): void
    {
        $struct = $this->tagsService->newTagCreateStruct(
            $parentLocation,
            $mainLanguage
        );

        // Map data value
        if ($table instanceof TableNode) {
            $data = $table->getRowsHash();

            if (isset($data['keyword'])
            ) {
                $struct->setKeyword(
                    $data['keyword'],
                    $data['language'] ?? $mainLanguage
                );
            } else {
                throw new \DomainException('thereIsATag: Required the field "keyword".');
            }
        }

        // Save and publish
        $this->repo->sudo(function () use ($struct): void {
            $this->tagsService->createTag($struct);
        });
    }

    protected function getClassName(): string
    {
        return Value::class;
    }


}

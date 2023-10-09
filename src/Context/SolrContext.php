<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Ibexa\Solr\Gateway;


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

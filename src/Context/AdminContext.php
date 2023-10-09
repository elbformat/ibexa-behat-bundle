<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Elbformat\SymfonyBehatBundle\Browser\State;
use Elbformat\SymfonyBehatBundle\Context\RequestTrait;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Login into the admin UI.
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class AdminContext implements Context
{
    use RequestTrait;

    public function __construct(
        protected KernelInterface $kernel,
        protected State $state,
    ) {
    }

    #[Given('I am logged in as admin')]
    public function iAmLoggedInAsAdmin(): void
    {
        $adminSiteaccessUrl = '/login';
        $this->doRequest($this->buildRequest($adminSiteaccessUrl));
        $crawler = $this->state->getCrawler();
        $form = $crawler->filterXpath('//form[@role="form"]')->form();
        $form->get('_username')->setValue('admin');
        $form->get('_password')->setValue('publish');
        $values = $form->getPhpValues();
        $this->doRequest($this->buildRequest(uri: $form->getUri(), method: $form->getMethod(), parameters: $values));
    }
}

<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Elbformat\SymfonyBehatBundle\Browser\State;
use Elbformat\SymfonyBehatBundle\Context\RequestTrait;
use Ibexa\Contracts\Core\Repository\Values\UserPreference\UserPreferenceSetStruct;
use Ibexa\Core\Repository\Repository;
use Ibexa\Core\Repository\Values\User\UserReference;
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
        private Repository $repository,
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

    #[Given('focus mode is disabled for :username')]
    public function focusModeIsDisabled(string $username): void
    {
        // Switch user
        $permissionResolver = $this->repository->getPermissionResolver();
        $userBackup = $permissionResolver->getCurrentUserReference();
        $user = $this->repository->getUserService()->loadUserByLogin($username);
        $newUser = new UserReference($user->id);
        $permissionResolver->setCurrentUserReference($newUser);

        // update preferences
        $struct = new UserPreferenceSetStruct(['name' => 'focus_mode', 'value' => '0']);
        $this->repository->getUserPreferenceService()->setUserPreference([$struct]);

        // Switch user back
        $permissionResolver->setCurrentUserReference($userBackup);
    }
}

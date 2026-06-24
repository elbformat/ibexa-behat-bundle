<?php

declare(strict_types=1);

namespace Test\Context;

use Elbformat\IbexaBehatBundle\Context\AdminContext;
use Elbformat\SymfonyBehatBundle\Browser\State;
use Ibexa\Contracts\Core\Repository\Repository;
use PHPUnit\Framework\MockObject\MockClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

class AdminContextTest extends TestCase
{
    private AdminContext $context;
    private Kernel&MockObject $kernel;
    private State $state;

    protected function setUp(): void
    {
        $this->kernel = $this->getMockForAbstractClass(Kernel::class, [],'',false,true,true,['handle']);
        $this->state = new State();
        $repository = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->context = new AdminContext($this->kernel, $this->state, $repository);
    }


    public function testIAmLoggedInAsAdmin(): void
    {
        $this->kernel->method('handle')->willReturn(new Response('<form role="form"><input name="_username" /><input name="_password" /></form>'));
        $this->expectNotToPerformAssertions();
        $this->context->iAmLoggedInAsAdmin();
    }
}

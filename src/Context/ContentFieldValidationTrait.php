<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Ibexa\Contracts\Core\FieldType\ValidationError;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\Repository\Values\Translation;
use Ibexa\Contracts\Core\Repository\Values\Translation\Message;
use Ibexa\Contracts\Core\Repository\Values\Translation\Plural;
use Webmozart\Assert\Assert;

trait ContentFieldValidationTrait
{
    /**
     * @throws \DomainException
     */
    protected function convertContentFieldValidationException(ContentFieldValidationException $e): never
    {
        $message = $e->getMessage();

        foreach ($e->getFieldErrors() as $fieldName => $languages) {
            Assert::isArray($languages);
            foreach ($languages as $validationErrors) {
                if ($validationErrors instanceof ValidationError) {
                    $validationErrors = [$validationErrors];
                }
                Assert::isArray($validationErrors);

                foreach ($validationErrors as $validationError) {
                    Assert::isInstanceOf($validationError, ValidationError::class);
                    /** @var Translation $trans */
                    $trans = $validationError->getTranslatableMessage();

                    if ($trans instanceof Plural || $trans instanceof Message) {
                        $message .= \sprintf("\n %s: %s", $fieldName, (string) $trans);
                    }
                }
            }
        }

        throw new \DomainException($message, 0, $e);
    }
}

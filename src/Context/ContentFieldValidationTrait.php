<?php

declare(strict_types=1);

namespace Elbformat\IbexaBehatBundle\Context;

use Ibexa\Contracts\Core\Repository\Values\Translation;
use Ibexa\Contracts\Core\Repository\Values\Translation\Message;
use Ibexa\Contracts\Core\Repository\Values\Translation\Plural;
use Ibexa\Contracts\Core\Repository\Exceptions\ContentFieldValidationException;
use Ibexa\Contracts\Core\FieldType\ValidationError;

trait ContentFieldValidationTrait
{
    protected function convertContentFieldValidationException(ContentFieldValidationException $e): void
    {
        $message = $e->getMessage();

        foreach ($e->getFieldErrors() as $fieldName => $languages) {
            foreach ($languages as $validationErrors) {
                if ($validationErrors instanceof ValidationError) {
                    $validationErrors = [$validationErrors];
                }

                foreach ($validationErrors as $validationError) {
                    /** @var Translation $trans */
                    $trans = $validationError->getTranslatableMessage();

                    if ($trans instanceof Plural || $trans instanceof Message) {
                        $message .= sprintf("\n %s: %s", $fieldName, (string) $trans);
                    }
                }
            }
        }

        throw new \DomainException($message, 0, $e);
    }
}

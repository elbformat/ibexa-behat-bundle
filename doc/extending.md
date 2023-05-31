When you need to override a context, make sure that you also adapt the according service.
For example to extend the ContentContext to add some custom fieldtypes:

tests/behat/ContentContext.php:
```php
namespace App\Tests\Behat;
class ContentContext extends \Elbformat\IbexaBehatBundle\Context\ContentContext {
    protected function getFieldDefValue(ContentType $ct, string $field, string $value) {
        $fieldDef = $ct->getFieldDefinition($field);
        switch ($fieldDef->fieldTypeIdentifier) {
            case '...':
                ...
            default:
                return parent::getFieldDefValue($ct, $field, $value);
        }
    }
}
```

config/services_behat.yml:
```yaml
    App\Tests\Behat\ContentContext:
        decorates: Elbformat\IbexaBehatBundle\Context\ContentContext
        parent: Elbformat\IbexaBehatBundle\Context\ContentContext
```
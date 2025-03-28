# ibexa-behat-bundle
Although there already is an [official behat bundle](https://github.com/ibexa/behat) for ibexa, this bundle has a completely different approach.
It is optimized for faster execution by using the Symfony Kernel directly and only partially reset the database.
Also, it has more strict wordings in a behavioural manner.
Last but not least it also supports creating page builder blocks.

## Installation

1. Add the bundle via composer
```console
composer require elbformat/ibexa-behat-bundle
```

2. Activate bundles in `config/bundles.php`
```php
Elbformat\SymfonyBehatBundle\ElbformatSymfonyBehatBundle::class => ['test' => true],
Elbformat\IbexaBehatBundle\ElbformatIbexaBehatBundle::class => ['test' => true],
```

3. Configure behat.yml
See the [symfony-behat-bundle installation](https://packagist.org/packages/elbformat/symfony-behat-bundle) instructions.
Add more contexts as you like
```
default:
  suites:
    default:
      contexts:
        - Elbformat\IbexaBehatBundle\Context\AdminContext
        - Elbformat\IbexaBehatBundle\Context\ContentContext
        - Elbformat\IbexaBehatBundle\Context\LandingpageContext
        - Elbformat\IbexaBehatBundle\Context\ObjectstateContext
        - Elbformat\IbexaBehatBundle\Context\SolrContext
        - Elbformat\IbexaBehatBundle\Context\TrashContext
```

4. Use TestFilePathNormalizer
In order to test uploaded images, you need a predictable filename. 
This can be achieved by skipping the random hash at the end, when running test.
Just put the following line in your `config/services_behat.yaml` to
```yaml
services:
  # Prevent random character in image urls for tests
  Elbformat\IbexaBehatBundle\IO\TestFilePathNormalizer:
    decorates: 'Ibexa\Core\IO\FilePathNormalizer\Flysystem'
    arguments: ['@.inner']
```

## Run tests
Make sure you have a database configured for the test environment.
It's recommended to have an extra database configured for tests in `.env.test`, to not accidentally delete real contents.
After configuration you should initialise it once, before running any test against it.

```shell
bin/console -e test do:da:cr
bin/console -e test do:mi:mi -n
bin/console -e test ib:mi:mi -n
bin/console -e test ibexa:reindex
```

Then you can simply run the tests.
```shell
vendor/bin/behat
```

## Tweaks
When you have internal, fixed content/location ids > 1000 you may want to change the minimum id for resetting the database.
To do this, you can simply add an enviromment variable `BEHAT_CONTENT_MIN_ID=10000` to `.env.behat`
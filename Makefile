ROOT_DIR:=$(shell dirname $(realpath $(firstword $(MAKEFILE_LIST))))
DOCKER_RUN ?= docker compose run --rm php

.PHONY: php-cs-fixer
php-cs-fixer:
	docker run -v "$(ROOT_DIR):/code" --rm ghcr.io/php-cs-fixer/php-cs-fixer:3.95-php8.3 fix --diff src

.PHONY: phpstan
phpstan:
	$(DOCKER_RUN) vendor/bin/phpstan --memory-limit=-1

.PHONY: phpunit
phpunit:
	$(DOCKER_RUN) vendor/bin/phpunit

.PHONY: composer
composer:
	$(DOCKER_RUN) composer

.PHONY: shell
shell:
	docker compose run -it --rm php sh

.PHONY: behat
behat:
	docker compose up -d
	docker compose run -it --rm php composer require ibexa/experience-skeleton:^4.6 -n
	cp -R vendor/ibexa/experience-skeleton/config .
	mkdir -p src/Entity
	rm config/services*.yaml
	docker compose run -it --rm php composer require symfony/flex -n
	docker compose run -it --rm php apk add git && composer recipes:install friends-of-behat/symfony-extension --force -n
	docker compose run -it --rm php php -d memory_limit=1G vendor/bin/behat
	docker compose down

.PHONY: clean
clean:
	rm -Rf assets bin config migrations public src/Controller src/Entity src/Repository templates tests/Behat translations var/encore
	rm -f features/demo.feature
	rm -f .env.dev .env.test .php-cs-fixer.cache .phpunit.result.cache composer.lock behat.yml.dist package.json webpack.config.js
	git checkout -- composer.json
	git checkout -- symfony.lock
	git checkout -- .gitignore
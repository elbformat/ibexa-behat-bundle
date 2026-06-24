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
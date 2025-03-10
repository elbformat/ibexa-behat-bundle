For local development you can use docker-compose.
```bash
docker compose up -d
docker compose run php sh
composer install
```

Enable xdebug inside the container
```bash
export XDEBUG_CONFIG="client_host=172.17.0.1 idekey=PHPSTORM"
export XDEBUG_MODE="debug"
```

Run tests
```bash
vendor/bin/phpunit
```
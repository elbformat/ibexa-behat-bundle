For local development you can use docker compose together with make.
```bash
make composer install
```

Enable xdebug inside the container
```bash
export XDEBUG_CONFIG="client_host=172.17.0.1 idekey=PHPSTORM"
export XDEBUG_MODE="debug"
```

Run tests
```bash
make phpunit
```

Run phpstan
```bash
make phpstan
```

Fix code styles
```bash
make php-cs-fixer
```

Open a shell
```bash
make shell
```
.PHONY: test

vendor/autoload.php: composer.json
	rm -rf composer.lock vendor/
	composer install

test: vendor/autoload.php
	vendor/bin/phpunit

test-coverage: vendor/autoload.php
	php -dzend_extension=xdebug.so vendor/bin/phpunit --coverage-text

lint:
	find . -name '*.php' '!' -path './vendor/*' -exec php -l {} >/dev/null \;

stan:
	vendor/bin/phpstan analyze

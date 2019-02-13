
.PHONY: ALL cover test

PHP=php
BROWSER=firefox

ALL: test

test:
	$(PHP) ./vendor/bin/phpunit tests/

cover:
	$(PHP) -e -d zend_extension=xdebug ./vendor/bin/phpunit --coverage-html cover --whitelist src tests/
	$(BROWSER) cover/index.html

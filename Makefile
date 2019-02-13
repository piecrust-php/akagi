
.PHONY: ALL cover release test

PHP=php
BROWSER=firefox

ALL: test

test:
	$(PHP) ./vendor/bin/phpunit tests/

cover:
	$(PHP) -e -d zend_extension=xdebug ./vendor/bin/phpunit --coverage-html cover --whitelist src tests/
	$(BROWSER) cover/index.html

release:
	@if [ 0 -lt `git status -s | wc -l` ]; then \
	    echo "Uncommitted changes:"; git status -s ; \
	    false; \
    fi
	@if [ -z "$${VERSION}" ]; then echo "Please provide an version: make release VERSION=v?.?.?" ; false ; fi
	@LASTVERSION=`git tag|tail -n 1` ;\
	if [ -z "$${LASTVERSION}" ]; then LASTVERSION=v0.1.0 ; fi ;\
	if [ 1 -ne `$(PHP) -r "print(version_compare('$${VERSION}','$${LASTVERSION}'));"` ]; \
	    then echo "The version $${VERSION} is lower then last version $${LASTVERSION}"; \
	    false; \
	fi ;\
	git tag -m"releasing $${VERSION}" $${VERSION}
	git push --tags




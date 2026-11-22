PHP ?= php
V ?= 8.3

.PHONY: test lines docker-test

test: lines
	$(PHP) bin/baretest

lines:
	@n=$$(cat bin/baretest src/*.php | wc -l); echo "$$n lines of source"; test $$n -lt 1000

docker-test:
	docker build --build-arg PHP_VERSION=$(V) -t baretest-php:$(V) .
	docker run --rm -v $(CURDIR):/app baretest-php:$(V) php bin/baretest

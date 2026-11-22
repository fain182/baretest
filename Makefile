PHP ?= php

.PHONY: test lines

test: lines
	$(PHP) bin/baretest

lines:
	@n=$$(cat bin/baretest src/*.php | wc -l); echo "$$n lines of source"; test $$n -lt 1000

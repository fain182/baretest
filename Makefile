PHP ?= php

.PHONY: test lines update-snapshots screenshot

test: lines vendor/autoload.php
	$(PHP) bin/baretest

vendor/autoload.php: composer.json
	composer dump-autoload --dev

update-snapshots: vendor/autoload.php
	SNAPSHOT_REPLACE=1 $(PHP) bin/baretest tests/snapshot_test.php

lines:
	@n=$$(cat bin/baretest $$(find src -name '*.php') | wc -l); echo "$$n lines of source"; test $$n -lt 1200

# Records the README example with vhs, from a directory laid out like a project that uses baretest.
screenshot:
	tmp=$$(mktemp -d) && mkdir -p "$$tmp/tests" "$$tmp/vendor/bin" && cp fixtures/readme/*.php "$$tmp/tests/" \
		&& ln -s $(CURDIR)/bin/baretest "$$tmp/vendor/bin/baretest" \
		&& (cd "$$tmp" && vhs $(CURDIR)/docs/output.tape \
			&& ffmpeg -loglevel error -sseof -0.1 -i output.gif -frames:v 1 -update 1 -y $(CURDIR)/docs/output.png); \
		status=$$?; rm -r -- "$${tmp:?}"; exit $$status

PHP ?= php

.PHONY: test deptrac update-snapshots screenshot

test: vendor/autoload.php
	$(PHP) bin/baretest

vendor/autoload.php: composer.json
	composer dump-autoload --dev

update-snapshots: vendor/autoload.php
	SNAPSHOT_REPLACE=1 $(PHP) bin/baretest tests/snapshot_test.php

deptrac:
	vendor/bin/deptrac analyse --no-progress

# Records the README example with vhs, from a directory laid out like a project that uses baretest.
screenshot:
	tmp=$$(mktemp -d) && mkdir -p "$$tmp/tests" "$$tmp/vendor/bin" && cp fixtures/readme/*.php docs/import_test.php "$$tmp/tests/" \
		&& ln -s $(CURDIR)/bin/baretest "$$tmp/vendor/bin/baretest" \
		&& (cd "$$tmp" && vhs $(CURDIR)/docs/output.tape \
			&& ffmpeg -loglevel error -sseof -0.1 -i output.gif -frames:v 1 -update 1 -y $(CURDIR)/docs/output.png); \
		status=$$?; rm -r -- "$${tmp:?}"; exit $$status

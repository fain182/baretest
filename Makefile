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

# Records a README screenshot with vhs, from a directory laid out like a project that uses baretest:
# $(1) are its test files, $(2) the name of both the tape and the image in docs/.
define record
	tmp=$$(mktemp -d) && mkdir -p "$$tmp/tests" "$$tmp/vendor/bin" && cp $(1) "$$tmp/tests/" \
		&& ln -s $(CURDIR)/bin/baretest "$$tmp/vendor/bin/baretest" \
		&& (cd "$$tmp" && vhs $(CURDIR)/docs/$(2).tape \
			&& ffmpeg -loglevel error -sseof -0.1 -i output.gif -frames:v 1 -update 1 -y $(CURDIR)/docs/$(2).png); \
		status=$$?; rm -r -- "$${tmp:?}"; exit $$status
endef

screenshot:
	$(call record,fixtures/readme/*.php,output)
	$(call record,docs/slow/*.php,slow)

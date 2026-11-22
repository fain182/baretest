PHP ?= php

.PHONY: test lines screenshot

test: lines
	$(PHP) bin/baretest

lines:
	@n=$$(cat bin/baretest src/*.php | wc -l); echo "$$n lines of source"; test $$n -lt 1000

# Runs the README example from a directory where its files are tests/*_test.php, as in the README.
screenshot:
	tmp=$$(mktemp -d) && mkdir "$$tmp/tests" && cp fixtures/readme/*.php "$$tmp/tests/" \
		&& (cd "$$tmp" && freeze --execute "sh -c '$(PHP) $(CURDIR)/bin/baretest; exit 0'" \
			--window --padding 20 --font.ligatures=false -o $(CURDIR)/docs/output.svg); \
		status=$$?; rm -r -- "$${tmp:?}"; exit $$status

#!/bin/bash

set -ex

# Run the unit tests, if they exist
if [ -f "phpunit.xml" ] || [ -f "phpunit.xml.dist" ]
then
	phpunit
fi

# Run the functional tests
BEHAT_TAGS=$(php utils/behat-tags.php)

vendor/bin/behat --format progress $BEHAT_TAGS --strict

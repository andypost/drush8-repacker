PHP=81
NAME=skilldlabs/php
PHAR ?= drush.phar
DOCKER_BUILDKIT ?= 1
TIME ?= time

.PHONY: all drush compress

all: drush

CUID ?= $(shell id -u)
CGID ?= $(shell id -g)

php=docker run --rm -u $(CUID):$(CGID) -v $(CURDIR):/srv $(NAME):$(PHP) $(TIME) ${1}


drush:
	$(call php, php$(PHP) -dphar.readonly=0 phar-repack.php -v --file=$(PHAR))

compress: drush
	$(call php, php$(PHP) -dphar.readonly=0 /usr/bin/phar$(PHP) compress -f $(PHAR) -c gz)

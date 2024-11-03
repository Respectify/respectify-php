# respectify-php

PHP library for the [Respectify API](https://docs.respectify.org). Respectify aims to improve internet discussions: encourage healthy, understanding discussion in comments and replies.

## Getting started

This contains the Respectify client, which is currently available only as an asychronous API using ReactPHP.

Check out `src/RespectifyClientAsync.php`. For info on the REST API that it wraps, have a look [here](https://docs.respectify.org).

## Developing

To be able to make changes:

```bash
$ brew install php
$ brew install composer
```

If you want to run unit tests:
```bash
$ composer require --dev phpunit/phpunit mockery/mockery 
```

## Docs

Docs are generated in Markdown format for Docusaurus via the phpdocumentor-markdown-customised phpdoc theme. It is MIT-licensed. Please see the readme in that folder for more information.

If you have a `phpdoc` command available (eg, set up as a Docker container):

```bash
$ alias phpdoc="docker run --rm -v \"$(pwd)\":/data phpdoc/phpdoc:3"
```

then you can general doc like so:

```bash
# Run phpDocumentor with --template argument pointed to markdown template
$ phpdoc --directory=src --target=docs --template="phpdocumentor-markdown-customised/themes/markdown" --title="Respectify PHP Library" -c phpdoc.xml
```



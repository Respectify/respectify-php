# respectify-php

PHP library for the [Respectify API](https://docs.respectify.org). Respectify aims to improve internet discussions: encourage healthy, understanding discussion in comments and replies.

## Getting started

This contains the Respectify client, which is currently available only as an asychronous API using ReactPHP.

Check out `src/RespectifyClientAsync.php`. For info on the REST API that it wraps, have a look [here](https://docs.respectify.org).

If you are migrating from Google's Perspective API, use the dedicated Perspective compatibility sub-client:

```php
$client->perspective()->analyzeComment([
    'comment' => ['text' => 'You clearly did not read the article.'],
    'requestedAttributes' => [
        'TOXICITY' => new stdClass(),
        'INSULT' => new stdClass(),
    ],
])->then(function ($result) {
    echo $result->attributeScores['TOXICITY']->summaryScore->value . "\n";
});

$client->run();
```

### Moderation tolerances

Each API key has a saved tolerance for nine categories of problem (`threat`, `identity_attack`, `sexual_content`,
`harassment`, `insult`, `profanity`, `dismissive_tone`, `logical_fallacy`, `low_effort`), each one of `relaxed`,
`standard` (the default), `strict` or `zero_tolerance`. You can override them for a single call by passing a
`ModerationTolerances` as the last argument of `evaluateComment()` or `megacall()`. Only the categories you name are
overridden; the rest keep their saved level.

`threat`, `identity_attack`, `sexual_content` and `harassment` are floored: they can be made stricter but never
`relaxed`. `ModerationTolerances` rejects that (and any unknown category or level) with a `BadRequestException` when it
is constructed, so an invalid request is never sent.

Use `CommentScore::$exceedsTolerance` to decide whether to act on a comment. `toxicityScore` is not adjusted for
tolerances. Each objectionable or negative tone phrase also has a `category` and a `severity` (1 minor, 2 clear,
3 severe), and each logical fallacy has a `severity`.

```php
use Respectify\Moderation\ModerationCategory;
use Respectify\Moderation\ModerationTolerances;
use Respectify\Moderation\ToleranceLevel;

$tolerances = new ModerationTolerances([
    ModerationCategory::PROFANITY => ToleranceLevel::RELAXED,
    ModerationCategory::THREAT => ToleranceLevel::ZERO_TOLERANCE,
]);

$client->evaluateComment($articleId, $comment, null, $tolerances)->then(function ($score) {
    if ($score->exceedsTolerance) {
        foreach ($score->objectionablePhrases as $phrase) {
            echo $phrase->category . ' (severity ' . $phrase->severity . '): ' . $phrase->quotedObjectionablePhrase . "\n";
        }
    }
});

$client->run();
```

## Developing

To be able to make changes:

```bash
$ brew install php
$ brew install composer
```

If you want to run unit tests:
```bash
$ composer require --dev phpunit/phpunit mockery/mockery vlucas/phpdotenv
```

and then to run tests:

```bash
$ vendor/bin/phpunit --bootstrap vendor/autoload.php tests
```

Tests are run against mocks, but there is value in running against the real API. Do do this create a tests/.venv file, with content:

```ini
USE_REAL_API=true
RESPECTIFY_EMAIL=you@example.com
RESPECTIFY_API_KEY=your-api-key-here
REAL_ARTICLE_ID=an-existing-article-id-here
```

using real Respectify credentials.

## Docs

Docs are generated in Markdown format for Docusaurus via the phpdocumentor-markdown-customised phpdoc theme. It is MIT-licensed. Please see the readme in that folder for more information.

You'll need `twig` and `php-documentor` installed (install the dev dependencies.)

If you have a `phpdoc` command available (eg, set up as a Docker container):

```bash
$ alias phpdoc="docker run --rm -v \"$(pwd)\":/data phpdoc/phpdoc:3"
```

then you can general doc like so:

```bash
# Run phpDocumentor with --template argument pointed to markdown template
$ phpdoc --directory=src --target=docs --template="phpdocumentor-markdown-customised/themes/markdown" --title="Respectify PHP Library" -c phpdoc.xml
```


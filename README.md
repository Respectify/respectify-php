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

### Comment health and moderation tolerances

Comment health is made of ten items: nine categories of problem (`threat`, `identity_attack`, `sexual_content`,
`harassment`, `insult`, `profanity`, `dismissive_tone`, `logical_fallacy`, `low_effort`) and `quality`. Each item has a
level: `off`, `permissive`, `relaxed`, `standard` (the default), `strict` or `zero_tolerance`. Nothing is saved on the API key: pass a
`ModerationTolerances` as the last argument of `evaluateComment()` or `megacall()` on every call. Items you do not name
are `standard`; only the items you name are sent.

Each item that is not `off` gets a score from 1 to 5. For a problem category, 5 means nothing was found; otherwise it
is the worst finding (minor 4, clear 3, severe 2, extreme 1). For `quality`, it is the quality score. Each level sets the lowest
score accepted:

| Level | Problem category | Quality |
|---|---|---|
| `off` | not scored, not coached, not counted | not scored |
| `permissive` | 2 (filters only the very worst: minor, clear and severe findings accepted) | 2 |
| `relaxed` | 3 (minor and clear findings accepted) | 2 |
| `standard` | 4 (minor findings accepted) | 3 |
| `strict` | 5 (no findings accepted) | 4 |
| `zero_tolerance` | 5 (no findings accepted, and any hint gets a full assessment) | 5 |

Quality has only four distinct minimums, so `permissive` and `relaxed` share 2 there.

`threat`, `identity_attack`, `sexual_content` and `harassment` are floored: they can be made stricter but never
`permissive`, `relaxed` or `off`, and accept no finding at any level. `ModerationTolerances` rejects a floor violation (and any unknown
item or level) with a `BadRequestException` when it is constructed, so an invalid request is never sent.
`HealthItem::allowedLevels()` lists the levels an item accepts, for building a settings UI.

Use `CommentScore::$exceedsTolerance` to decide whether to act on a comment: it is true when any item scores below its
minimum, or the comment is severely toxic. The response also has:

- `healthItems`: each item that is not off, by name, as a `HealthItemScore` with `score`, `minimum` and `isAcceptable`.
  The raw quality score is `$score->healthItems['quality']->score`.
- `healthScore`: comment health, 1.0 to 5.0 (the geometric mean of the quality score and the worst problem item's
  score). `null` when every item is off.
- `overallScore`: comment health rounded to an integer, 1 to 5.

Older servers send neither `healthItems` nor `healthScore`; they read as `[]` and `null`. Findings an item accepts are
removed from the response. `toxicityScore` is not adjusted for tolerances. Each objectionable or negative tone phrase
also has a `category` and a `severity` (1 minor, 2 clear, 3 severe, 4 extreme), and each logical fallacy has a
`severity`. Severe is a slur or dehumanising language, a threat, sexual harassment, urging self-harm or sustained
abuse; extreme is the same at full force, and no level accepts it.

```php
use Respectify\Moderation\HealthItem;
use Respectify\Moderation\ModerationCategory;
use Respectify\Moderation\ModerationTolerances;
use Respectify\Moderation\ToleranceLevel;

$tolerances = new ModerationTolerances([
    ModerationCategory::PROFANITY => ToleranceLevel::RELAXED,
    ModerationCategory::LOGICAL_FALLACY => ToleranceLevel::OFF,
    ModerationCategory::THREAT => ToleranceLevel::ZERO_TOLERANCE,
    HealthItem::QUALITY => ToleranceLevel::STRICT,
]);

$client->evaluateComment($articleId, $comment, null, $tolerances)->then(function ($score) {
    if ($score->exceedsTolerance) {
        foreach ($score->healthItems as $item => $result) {
            if (!$result->isAcceptable) {
                echo $item . ': ' . $result->score . ' (needs ' . $result->minimum . ")\n";
            }
        }
    }
});

$client->run();
```

### Errors and logging

API failures reject the promise with a `RespectifyException` subclass (`BadRequestException`, `UnauthorizedException`,
`PaymentRequiredException`, `ServerException`, `JsonDecodingException`, and so on).

`getMessage()` is safe to log. When the library builds a message from an API response, the message carries only the
kind of error, the HTTP status, the server's error title, and the server's error description when that description is
one of Respectify's known fixed strings, for example `Unauthorized (HTTP 401): Authentication failed - Invalid user ID
or API key.`. It never contains a raw response body, an excerpt of JSON that failed to decode, or anything the server
echoed back from the request. That matters because responses can contain the user's comment text.

If you need the full detail while debugging, `getResponseData()` returns the parsed error JSON and `getResponseBody()`
returns the raw body. Both may contain user content: do not log them, store them, or show them to other users.

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


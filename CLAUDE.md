# Respectify PHP Library - Developer Guidelines

This is a PHP wrapper for the Respectify service, which lives live at app.respectify.org and
is implemented in ../discussion-arena-backend . The service is described in OpenAPI format
at ../discussion-arena-docgen/respectify-docs/openapi.json and you must always refer to this
when implementing an API call.

It is an async service intended to keep the server this PHP code is run on non-blocking while
it runs. Expect many calls at once.

The human you're working with is not an expert at PHP, and so keep an eye out for common pitfalls,
security issues, and proactively suggest best practices or refactorings. Check implicit assumptions.

Try to keep a stable API.

While this is a general-purpose PHP library, it is consumed by the Respectify Wordpress plugin, which
is located at ../respectify-wordpress.

## Build/Test Commands
- Install dependencies: `composer install`
- Run all tests: `vendor/bin/phpunit --bootstrap vendor/autoload.php tests`
- To run tests against the real API edit tests/.env to have `USE_REAL_API=true`. Edit it back to run tests with mocks. We have found issues using the real API before which mocks did not show, so this is worth doing.
- Build docs: `python build.py`. This mostly generates documentation. Do this rarely because it spins up a large Docker instance. After generating this copies them elsewhere; don't focus on this, but remind the user so they can perform a manual deployment step.

## Publishing New Versions
- To publish a new version, create a git tag using semantic versioning
- Check git history to determine appropriate version increment. Because this has not hit v1.0 yet:
  - Major (0.x.0): All versions are 0.x.y, currently
  - Minor (0.x.0): New features, or breaking changes (because it has not hit v1 yet)
  - Patch (0.0.x): Most changes, until enough accrue that it's worth bumping the 0.x version
- Remind the user to commit changes and push tags themselves
- Do not commit the CLAUDE.md file to git

## Code Style Guidelines
- PSR-4 autoloading: namespaces match directory structure
- Class names: PascalCase
- Method/function names: camelCase
- Properties/variables: camelCase
- Constants: UPPER_SNAKE_CASE
- Indentation: 4 spaces
- Line length: aim for 80-120 characters
- PHP version: 7.4+ (uses typed properties)

## Error Handling
- Custom exceptions inherit from RespectifyException
- Use strong typing with type hints and return types
- Always sanitize external data (use htmlspecialchars or other sanitisation methods as appropriate.) This is CRITICAL. Note, sometimes custom sanitisation methods are written for complex data.
- Prefer explicit null checks over isset() when appropriate

## Documentation
- PHPDoc comments for all classes, methods, and properties
- Include @param, @return, @throws tags for methods
- Write clear examples for public API methods

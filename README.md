# WP Buyer Blacklist

WP Buyer Blacklist is a WooCommerce plugin for preventing specified Buyers from placing orders.

## Status

The plugin is not implemented yet. Its v1 product requirements are defined in the [Product PRD](docs/prd/README.md).

## Product requirements

[docs/prd/product.md](docs/prd/product.md) is the source of truth for product behaviour, including matching, enrollment, checkout enforcement, permissions, privacy, and v1 non-goals. The [domain glossary](CONTEXT.md) defines its terms.

## Architecture records

The records in [docs/adr](docs/adr/) describe implementation architecture only. They do not define or override product behaviour; implementation work must follow the Product PRD.

## Compatibility

V1 supports WordPress 6.9+, WooCommerce 10.8+, PHP 8.3+, and WooCommerce High-Performance Order Storage (HPOS). It supports classic checkout, Checkout Block, Store API checkout, and the Agentic Commerce checkout API available in compatible WooCommerce versions.

## Testing

The plugin uses PHP and React/TypeScript. Business logic and React components require unit tests. PHP unit tests use PHPUnit, and React unit tests use Jest through `@wordpress/scripts`.

```
composer install
composer test
npm install
npm test
```

`wp-phpunit/wp-phpunit` is installed for WordPress and WooCommerce integration tests. Those tests will be configured with a dedicated test database when the first ticket needs them.

## Coding standards

PHP code follows [WordPress-Extra](https://github.com/WordPress/WordPress-Coding-Standards) via PHP_CodeSniffer, configured in `phpcs.xml.dist`:

```
composer install
composer lint       # report violations
composer lint:fix    # auto-fix what phpcbf can
```

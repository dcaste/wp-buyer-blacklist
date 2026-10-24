# Contributing

Thanks for taking a look. Bug reports, ideas and pull requests are welcome.

If you found a security problem, don't open an issue. Follow [SECURITY.md](SECURITY.md).

## Before you start

- Search the [issues](https://github.com/dcaste/wp-buyer-blacklist/issues) first. If your bug or idea isn't there, open one and describe it.
- For anything bigger than a small fix, wait for a reply on the issue before you write code, so the work isn't wasted.
- The product terms are in [CONTEXT.md](CONTEXT.md): Buyer, Blacklist entry, Match type, Canonical value, Blocked attempt, Audit event. Use them in code, docs and issues.
- What the plugin should do is in [docs/prd/product.md](docs/prd/product.md). Architecture decisions are in [docs/adr/](docs/adr/).

## Set up

You need PHP 8.3 or later, Composer, and Node.js. Fork the repository, clone your fork, then:

```sh
composer install
npm install
```

The plugin needs WordPress 6.9 or later and WooCommerce 10.8 or later with High-Performance Order Storage. Put the folder in `wp-content/plugins/` of a test site.

## Branches and pull requests

The rules are in [docs/engineering/git-workflow.md](docs/engineering/git-workflow.md). In short:

1. Branch from the latest `dev`, never from `main`. Name the branch `issue/<number>-<short-slug>`, using the issue number.
2. Open the pull request into `dev`, not `main`. Only the maintainer merges `dev` into `main`.
3. Write the pull request in English, and put `Closes #<number>` in the description.
4. One issue, one branch, one pull request. It is squash-merged into `dev`.

## Code and checks

Run these before you push:

```sh
composer lint     # PHP_CodeSniffer with the WordPress coding standards
composer test     # PHP unit tests
npm test          # Jest tests for the admin app
npm run build     # rebuild the admin app into build/
```

- `composer lint:fix` fixes most style findings for you.
- The built admin app in `build/` is committed. If you change anything under `assets/admin/`, run `npm run build` and commit the result.
- Integration tests need the WordPress test library and a MySQL database. The README's Development section explains how to run them.
- A new class goes in the `src/` folder that owns its feature. Its namespace and file path must match its class name, because the plugin loads classes with its own autoloader.
- Markup lives in `templates/`, not inside PHP classes.
- Wrap user-facing strings for translation with the `wp-buyer-blacklist` text domain, in PHP and in the admin app.
- Add or update tests when behavior changes. If you change what users see or do, update the README too.
- Write commit messages, comments and docs in plain English.

## License

By contributing, you agree that your work is released under the [MIT license](LICENSE).

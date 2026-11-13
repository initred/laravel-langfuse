# Contributing

Thanks for your interest in `initred/laravel-langfuse`. Issues and pull requests are welcome.

## Branches and commits

- `main` is the only long-lived branch. Work in a short-lived feature branch and open a pull request against `main`.
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/):

  ```
  feat(neuron-ai): record the provider's model on the generation
  ```

- Notable changes go under `[Unreleased]` in [CHANGELOG.md](../CHANGELOG.md).

## Releasing

A release is a SemVer tag on `main`.

1. Rename `[Unreleased]` in `CHANGELOG.md` to the new version and date, for example `## [1.0.1] - 2026-11-20`.
2. Set `SDK_VERSION` in `src/Version.php` to the same version. `VersionTest` fails until both match.
3. Commit, then push a `v`-prefixed tag: `git tag -a v1.0.1 -m v1.0.1 && git push origin v1.0.1`.

The `release` workflow checks that the tag, `SDK_VERSION` and the changelog agree, and creates the GitHub release with that changelog entry as its notes.

## Verification

Run the checks locally before opening a pull request.

```bash
composer install
composer test
```

`composer test` runs PHPStan (max), the Pint check, 100% type coverage and Pest, in that order.
The individual commands are `composer analyse`, `composer lint`, `composer lint:check`, `composer test:types` and `composer test:unit`.

The `tests` GitHub Actions workflow runs on every push and pull request to `main`, and can be run by hand with **Run workflow** on the Actions tab.
It covers PHP 8.5, Laravel 13, prefer-lowest and prefer-stable, on Ubuntu and Windows.

Never put Langfuse keys, prompts or user data from a real project in logs, exception messages or test fixtures. Tests use synthetic data only.

## Dependency update pull requests

Dependabot opens weekly pull requests for Composer and GitHub Actions updates. `composer.lock` is not committed,
so a pull request only opens when a new release falls outside the constraints in `composer.json`. CI runs on each of them:

1. Check the `tests` workflow result on the pull request.
2. To dig into a failure, check the branch out locally:

   ```bash
   git fetch origin
   git switch --detach origin/dependabot/<branch>
   composer update
   composer test
   ```

3. If it passes, merge it and delete the branch. A pull request that changes the supported range (the PHP and Laravel matrix) needs `.github/workflows/tests.yml` and the constraints in `composer.json` reviewed together.
4. If it fails, or there is no reason to take it now, close it and record why in a commit or an issue.

# Upgrade guide

- [Upgrading to 2.x from 1.x](#upgrading-to-2x-from-1x)
- [Support for 1.x](#support-for-1x)
- [Updating your installation](#updating-your-installation)
- [Cached packages and metadata](#cached-packages-and-metadata)
- [Command changes](#command-changes)
    - [Listing installed packages](#listing-installed-packages)
    - [Package aliases](#package-aliases)
    - [The check, format, and test commands](#the-check-format-and-test-commands)
    - [Checking the cpx version](#checking-the-cpx-version)
- [Behavior changes](#behavior-changes)
    - [Local binaries are preferred](#local-binaries-are-preferred)
    - [Running PHP files](#running-php-files)
    - [The composer_require function](#the-composer_require-function)
    - [Non-interactive output](#non-interactive-output)

## Upgrading to 2.x from 1.x

cpx 2.x is a full rewrite that ships as a self-contained PHAR, but it keeps the same package name, the same `~/.cpx` cache directory, and the same core usage — `cpx <package-name> <command> [arguments]` works exactly as it did in 1.x. Most upgrades take about five minutes: update the global installation, then review the command and behavior changes below.

For everything new in 2.x — local package directories, gist execution, user-defined aliases, JSON output, and more — see the [README](./README.md).

## Support for 1.x

The 1.x release series is frozen and no longer supported. No further 1.x releases will be published, including bug fixes and security fixes. All development happens on the 2.x series.

## Updating your installation

cpx 2.x requires PHP 8.3 or higher.

To upgrade, require cpx globally again:

```shell
composer global require cpx/cpx
```

Running `composer global require` re-resolves the version constraint in your global `composer.json` to the latest release. Note that `composer global update cpx/cpx` alone will not upgrade you to 2.x while your global constraint still only allows `^1.0`.

The `cpx upgrade` command has been removed. To update cpx itself in the future, use Composer directly:

```shell
composer global update cpx/cpx
```

## Cached packages and metadata

No manual migration of the `~/.cpx` directory is required. cpx 2.x reads your existing metadata and upgrades it to the new format automatically, and packages installed without a version constraint are reused as-is.

Packages that were run with a version constraint (such as `friendsofphp/php-cs-fixer:^3.0`) are stored under new directory names in 2.x, so they are installed fresh the first time you run them. You may remove the 1.x copies left behind:

```shell
cpx clean
```

The old directories are reported as orphaned packages and deleted.

## Command changes

### Listing installed packages

`cpx list` now shows every available cpx command, matching standard console behavior. To see the packages you have installed through cpx, use:

```shell
cpx installed
```

### Package aliases

1.x shipped a built-in list of shortcuts for popular packages, so commands like `cpx phpstan` and `cpx laravel` worked out of the box. This list has been removed in 2.x. Instead, you may define your own aliases:

```shell
cpx alias phpstan/phpstan phpstan
cpx alias laravel/installer laravel
```

`cpx aliases` now lists the aliases you have defined instead of the built-in list, and `cpx unalias <name>` removes one.

Inside a project that already installs a tool, no alias is needed — a bare name such as `cpx phpstan` runs the matching binary from your project's `vendor/bin` directory.

### The check, format, and test commands

The `cpx check`, `cpx format`, and `cpx test` commands (and their `analyze`, `analyse`, and `fmt` aliases) have been removed. Run the underlying tool directly instead:

```shell
cpx pint
cpx phpstan
cpx pest
```

Because 2.x prefers binaries already installed in your project, these commands run your project's own pinned version of each tool, which is what the 1.x commands approximated.

### Checking the cpx version

The `cpx version` command and the `-v` shorthand have been removed. Use the standard option instead:

```shell
cpx --version
```

## Behavior changes

### Local binaries are preferred

When you run a package inside a Composer project that already has a matching binary installed, 2.x runs the binary from your project's `vendor/bin` directory instead of installing an isolated copy — the same way npx prefers local binaries. 1.x always used the isolated copy.

To restore the 1.x behavior for a single run, pass `--skip-local` before the package name:

```shell
cpx --skip-local laravel/pint --version
```

### Running PHP files

In 1.x, passing a path to an existing PHP file (such as `cpx script.php`) ran the file. In 2.x, files must be run through `cpx exec` explicitly:

```shell
cpx exec script.php
```

### The `composer_require` function

The `composer_require()` helper available inside `cpx exec` scripts and `cpx tinker` sessions has been renamed to `cpx_require()`:

```php
cpx_require('nesbot/carbon');

echo Carbon\Carbon::now();
```

### Non-interactive output

When cpx detects that it is not running in an interactive terminal — stdin is redirected, `--no-interaction` is passed, or an AI agent is detected — its own management commands (`installed`, `aliases`, `alias`, `unalias`, `clean`, and `update`) respond with a single line of JSON instead of formatted text, and package runs stream only the tool's own output. If you parse cpx output in scripts, update them for the JSON format, or pass `--json` to get the same output from an interactive terminal.

<?php

declare(strict_types=1);

namespace Cpx\Packages;

class PackageAliases
{
    /** @var array<string, PackageAlias>|null */
    private static ?array $packages = null;

    /** @return array<string, PackageAlias> */
    public static function all(): array
    {
        return self::$packages ??= [
            'psalm' => new PackageAlias(
                name: 'Psalm',
                description: 'A static analysis tool for PHP, focusing on improving code quality and detecting bugs.',
                command: 'psalm',
                package: 'vimeo/psalm',
            ),
            'phpcs' => new PackageAlias(
                name: 'PHP_CodeSniffer',
                description: 'Detects violations of coding standards.',
                command: 'phpcs',
                package: 'squizlabs/php_codesniffer',
            ),
            'phpstan' => new PackageAlias(
                name: 'PHPStan',
                description: 'A static analysis tool for finding bugs in PHP code without actually running it.',
                command: 'phpstan',
                package: 'phpstan/phpstan',
            ),
            'phploc' => new PackageAlias(
                name: 'PHPLoc',
                description: 'A tool for quickly measuring the size and analyzing the structure of a PHP project.',
                command: 'phploc',
                package: 'cmgmyr/phploc',
            ),
            'rector' => new PackageAlias(
                name: 'Rector',
                description: 'An automated refactoring tool that simplifies upgrades and cleanups in your PHP codebase.',
                command: 'rector',
                package: 'rector/rector',
            ),
            'phpmetrics' => new PackageAlias(
                name: 'PHPMetrics',
                description: 'A static analysis tool that provides metrics and quality assessments of your PHP code.',
                command: 'phpmetrics',
                package: 'phpmetrics/phpmetrics',
            ),
            'php-cs-fixer' => new PackageAlias(
                name: 'PHP-CS-Fixer',
                description: 'A tool to automatically fix coding standards in PHP files.',
                command: 'php-cs-fixer',
                package: 'friendsofphp/php-cs-fixer',
            ),
            'pint' => new PackageAlias(
                name: 'Pint',
                description: 'An opinionated code styler for Laravel.',
                command: 'pint',
                package: 'laravel/pint',
            ),
            'phinx' => new PackageAlias(
                name: 'Phinx',
                description: 'A database migration tool for PHP.',
                command: 'phinx',
                package: 'robmorgan/phinx',
            ),
            'box' => new PackageAlias(
                name: 'Box',
                description: 'A tool to generate PHAR files from a PHP project.',
                command: 'box',
                package: 'humbug/box',
            ),
            'pdepend' => new PackageAlias(
                name: 'Pdepend',
                description: 'A static analysis tool that generates software metrics like complexity and inheritance information.',
                command: 'pdepend',
                package: 'pdepend/pdepend',
            ),
            'dep' => new PackageAlias(
                name: 'Deployer',
                description: 'A deployment tool for PHP applications.',
                command: 'dep',
                package: 'deployer/deployer',
            ),
            'phpbench' => new PackageAlias(
                name: 'PHPBench',
                description: 'A benchmark framework for PHP.',
                command: 'phpbench',
                package: 'phpbench/phpbench',
            ),
            'phing' => new PackageAlias(
                name: 'Phing',
                description: 'A PHP project build system or automation tool similar to Apache Ant.',
                command: 'phing',
                package: 'phing/phing',
            ),
            'captainhook' => new PackageAlias(
                name: 'CaptainHook',
                description: 'A tool to manage and configure git hooks for PHP.',
                command: 'captainhook',
                package: 'captainhook/captainhook',
            ),
            'infection' => new PackageAlias(
                name: 'Infection',
                description: 'A mutation testing framework for PHP.',
                command: 'infection',
                package: 'infection/infection',
            ),
            'grumphp' => new PackageAlias(
                name: 'GrumPHP',
                description: 'A task runner tool to enforce code quality by running tasks on commit (like PHPUnit, PHPStan, etc.).',
                command: 'grumphp',
                package: 'phpro/grumphp',
            ),
            'laravel' => new PackageAlias(
                name: 'Laravel Installer',
                description: 'A tool for quickly creating new Laravel applications via CLI.',
                command: 'laravel',
                package: 'laravel/installer',
            ),
            'wp' => new PackageAlias(
                name: 'Wp-CLI',
                description: 'Command line interface for WordPress, allowing you to manage WordPress installations.',
                command: 'wp',
                package: 'wp-cli/wp-cli',
            ),
            'bref' => new PackageAlias(
                name: 'Bref',
                description: 'A tool to deploy PHP applications to AWS Lambda.',
                command: 'bref',
                package: 'bref/bref',
            ),
            'phpspec' => new PackageAlias(
                name: 'PhpSpec',
                description: 'A behavior-driven development (BDD) testing framework.',
                command: 'phpspec',
                package: 'phpspec/phpspec',
            ),
            'psysh' => new PackageAlias(
                name: 'PsySH',
                description: 'A PHP interactive shell with a powerful REPL.',
                command: 'psysh',
                package: 'psy/psysh',
            ),
            'composer-require-checker' => new PackageAlias(
                name: 'Composer Require Checker',
                description: 'A tool to check whether all dependencies required in composer.json are actually used in your code.',
                command: 'composer-require-checker',
                package: 'maglnet/composer-require-checker',
            ),
            'monorepo-builder' => new PackageAlias(
                name: 'Monorepo Builder',
                description: 'A tool for managing PHP monorepo projects.',
                command: 'monorepo-builder',
                package: 'symplify/monorepo-builder',
            ),
            'churn' => new PackageAlias(
                name: 'Churn PHP',
                description: 'A tool that helps identify PHP files in a project that have a high churn and complexity.',
                command: 'churn',
                package: 'bmitch/churn-php',
            ),
            'sculpin' => new PackageAlias(
                name: 'Sculpin',
                description: 'A static site generator written in PHP.',
                command: 'sculpin',
                package: 'sculpin/sculpin',
            ),
            'robo' => new PackageAlias(
                name: 'Robo.li',
                description: 'A PHP task runner for automating tasks in PHP applications.',
                command: 'robo',
                package: 'consolidation/robo',
            ),
            'phpdox' => new PackageAlias(
                name: 'PHPDox',
                description: 'A documentation generator for PHP, focused on unit test coverage and code analysis.',
                command: 'phpdox',
                package: 'theseer/phpdox',
            ),
            'phpinsights' => new PackageAlias(
                name: 'PHP Insights',
                description: 'Provides metrics and insights about PHP code quality, complexity, and architecture.',
                command: 'phpinsights',
                package: 'nunomaduro/phpinsights',
            ),
            'couscous' => new PackageAlias(
                name: 'Couscous',
                description: 'Static site generator for generating documentation from Markdown files.',
                command: 'couscous',
                package: 'couscous/couscous',
            ),
            'valet' => new PackageAlias(
                name: 'Valet',
                description: 'Manage a Laravel development environment with minimal configuration.',
                command: 'valet',
                package: 'laravel/valet',
            ),
            'deptrac' => new PackageAlias(
                name: 'Deptrac',
                description: 'Static analysis that defines and enforces architectural layers.',
                command: 'deptrac',
                package: 'qossmic/deptrac',
            ),
            'php-scoper' => new PackageAlias(
                name: 'PHP-Scoper',
                description: 'Isolate a PHP library\'s dependencies, useful for creating PHAR files.',
                command: 'php-scoper',
                package: 'humbug/php-scoper',
            ),
            'phpcbf' => new PackageAlias(
                name: 'Phpcbf',
                description: 'Automatically fix coding standards issues.',
                command: 'phpcbf',
                package: 'squizlabs/php_codesniffer',
            ),
            'phpmd' => new PackageAlias(
                name: 'PHPMD',
                description: 'Analyze PHP code for potential mess and problems.',
                command: 'phpmd',
                package: 'phpmd/phpmd',
            ),
            'ecs' => new PackageAlias(
                name: 'Easy Coding Standard',
                description: 'Check and fix coding standards in PHP code.',
                command: 'ecs',
                package: 'symplify/easy-coding-standard',
            ),
            'config-transformer' => new PackageAlias(
                name: 'Config Transformer',
                description: 'Transform configuration files from one format to another.',
                command: 'config-transformer',
                package: 'symplify/config-transformer',
            ),
            'class-leak' => new PackageAlias(
                name: 'ClassLeak',
                description: 'Spot unused classes you can remove.',
                command: 'class-leak',
                package: 'tomasvotruba/class-leak',
            ),
            'composer-dependency-analyser' => new PackageAlias(
                name: 'Composer Dependency Analyser',
                description: 'Detect unused dependencies, transitional dependencies, missing classes and more.',
                command: 'composer-dependency-analyser',
                package: 'shipmonk/composer-dependency-analyser',
            ),
            'swiss-knife' => new PackageAlias(
                name: 'Swiss Knife',
                description: 'Finalize classes without children, make class constants private and more.',
                command: 'swiss-knife',
                package: 'rector/swiss-knife',
            ),
        ];
    }
}

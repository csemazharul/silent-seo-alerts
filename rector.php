<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\ExplicitReturnNullRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\UnwrapFutureCompatibleIfPhpVersionRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/backend/app',
        __DIR__ . '/backend/hooks',
        __DIR__ . '/backend/db',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withSkip([
        UnwrapFutureCompatibleIfPhpVersionRector::class,
        ExplicitReturnNullRector::class,

        // strict_types changes runtime coercion, and this plugin takes loosely
        // typed input straight from WordPress options, $_POST and the DB. The
        // project already opts out deliberately - see the commented-out
        // SlevomatCodingStandard.TypeHints.DeclareStrictTypes rule in phpcs.xml.
        SafeDeclareStrictTypesRector::class,

        // Rewrites [$this, 'method'] to $this->method(...). WordPress keys a
        // hooked callback with _wp_filter_build_unique_id(), which is stable
        // for an array callback but a fresh spl_object_hash for every closure,
        // so remove_action()/has_action() from a site or another plugin would
        // silently stop matching our hooks.
        ArrayToFirstClassCallableRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        privatization: true,
        earlyReturn: true,
    )
    // No argument: the ceiling comes from composer.json's "php" constraint, so
    // this stays correct the next time that is raised.
    ->withPhpSets();

<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP73Migration' => true,
        '@PHP71Migration:risky' => true,
        '@PHPUnit84Migration:risky' => true,
        // Causes too much problems for now, fix later
        'declare_strict_types' => false,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('vendor')
            ->exclude('docs')
            ->in(__DIR__)
    )
    ->setRiskyAllowed(true);

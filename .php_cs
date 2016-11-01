<?php
$config = Symfony\CS\Config\Config::create()
    ->level('psr2')
    ->fixers(
        array(
            'single_blank_line_before_namespace',
            'concat_with_spaces',
            'single_quote',
            'braces',
        )
    )
    ->finder(
        Symfony\CS\Finder\DefaultFinder::create()
            ->exclude('vendor')
            ->exclude('docs')
            ->in(__DIR__)
    )
    ->setUsingCache(true);

$cacheDir = getenv('TRAVIS') ? getenv('HOME') . '/.php-cs-fixer' : __DIR__;
$config->setDir($cacheDir);

return $config;

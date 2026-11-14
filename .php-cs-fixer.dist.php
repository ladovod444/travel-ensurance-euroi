<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true, // Используем набор правил Symfony
        '@PSR12' => true,   // И стандарт PSR-12
        'array_syntax' => ['syntax' => 'short'], // Короткий синтаксис массивов
    ])
    ->setFinder($finder)
;

<?php

use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create();
$config->addRules( ['declare_strict_types'=>true]);
$config->getFinder()
    ->in(__DIR__ . '/src')
    ->append( ['redminesimilarities'])
;

return $config;

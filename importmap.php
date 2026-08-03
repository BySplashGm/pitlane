<?php

declare(strict_types=1);

/*
 * This file is part of Pitlane.
 *
 * (c) Maxime Valin
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
    '@hotwired/stimulus' => ['version' => '3.2.2'],
    '@symfony/stimulus-bundle' => ['path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js'],
    '@hotwired/turbo' => ['version' => '8.0.23'],
    'tom-select' => ['version' => '2.6.2'],
    '@orchidjs/sifter' => ['version' => '1.1.0'],
    '@orchidjs/unicode-variants' => ['version' => '1.1.2'],
    'tom-select/dist/css/tom-select.default.min.css' => ['version' => '2.6.2', 'type' => 'css'],
    'tom-select/dist/css/tom-select.default.css' => ['version' => '2.6.2', 'type' => 'css'],
    'tom-select/dist/css/tom-select.bootstrap4.css' => ['version' => '2.6.2', 'type' => 'css'],
    'tom-select/dist/css/tom-select.bootstrap5.css' => ['version' => '2.6.2', 'type' => 'css'],
];

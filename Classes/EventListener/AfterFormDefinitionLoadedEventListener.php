<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace WapplerSystems\FeRegistration\EventListener;


use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Form\Mvc\Persistence\Event\AfterFormDefinitionLoadedEvent;

#[AsEventListener(
    identifier: 'fe-registration/after-form-definition-loaded',
)]
#[Autoconfigure(public: true)]
final readonly class AfterFormDefinitionLoadedEventListener
{
    public function __invoke(AfterFormDefinitionLoadedEvent $event): void
    {

    }
}

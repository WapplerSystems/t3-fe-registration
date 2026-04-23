<?php

namespace WapplerSystems\FeRegistration\Hooks;

use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

class BeforeRenderingHook
{

    public function beforeRendering(FormRuntime $formRuntime, RootRenderableInterface $renderable): void
    {
    }

}
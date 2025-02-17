<?php

namespace WapplerSystems\FeRegistration\Hooks;

use TYPO3\CMS\Form\Domain\Model\Renderable\CompositeRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

class FormInitializationHook
{


    public function afterInitializeCurrentPage(FormRuntime $formRuntime, CompositeRenderableInterface $currentPage = null, CompositeRenderableInterface $lastPage = null, array $requestArguments = []): ?CompositeRenderableInterface
    {
        if ($formRuntime->getFormDefinition()->getPages()[0]->getIdentifier() === 'pseudoPage') {
            $currentPage = null;
        }

        return $currentPage;
    }


}

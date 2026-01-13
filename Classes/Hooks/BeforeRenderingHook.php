<?php

namespace WapplerSystems\FeRegistration\Hooks;

use TYPO3\CMS\Form\Domain\Model\FormElements\GenericFormElement;
use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

class BeforeRenderingHook
{


    /**
     * @param GenericFormElement $renderable
     * @return void
     * @deprecated
     */
    public function initializeFormElement(GenericFormElement $renderable)
    {
    }


    /**
     * @param FormRuntime $formRuntime
     * @param RootRenderableInterface $renderable
     * @return void
     */
    public function beforeRendering(FormRuntime $formRuntime, RootRenderableInterface $renderable)
    {

        /*
        if ($renderable->getIdentifier() === 'preDefinedValues') {

            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
            $predefinedValues = $eventDispatcher->dispatch(
                new SetPredefinedRegistrationFormValuesEvent($formRuntime)
            );
            if (count($predefinedValues->getValues()) > 0) {
                $formRuntime->getFormState()->setFormValue('preDefinedValues', json_encode($predefinedValues->getValues()));
            }

        }*/

    }


}

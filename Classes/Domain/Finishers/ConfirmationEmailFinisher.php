<?php

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;

class ConfirmationEmailFinisher extends EmailFinisher
{

    protected function executeInternal(): void
    {

        if (is_array($this->options['variables'] ?? null)) {
            $this->options['variables']['confirmationHash'] = $this->finisherContext->getFormRuntime()->getFormState()->getFormValue('confirmationHash');
        } else {
            $this->options['variables'] = ['confirmationHash' => $this->finisherContext->getFormRuntime()->getFormState()->getFormValue('confirmationHash')];
        }

        parent::executeInternal();
    }

}

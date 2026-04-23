<?php

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Service\ConfirmationService;

class CompleteRegistrationFinisher extends AbstractFinisher
{

    /**
     * @var array
     */
    protected $defaultOptions = [
        'confirmationRequest' => null,
    ];


    public function __construct(readonly ConfirmationService $confirmationService)
    {
    }


    /**
     * @see AbstractFinisher::execute()
     *
     */
    protected function executeInternal()
    {
        /** @var ConfirmationRequest $confirmationRequest */
        $confirmationRequest = $this->finisherContext->getFormRuntime()->getFormDefinition()->getRenderingOptions()['confirmationRequest'] ?? null;
        if (!$confirmationRequest instanceof ConfirmationRequest) {
            $confirmationRequest = $this->options['confirmationRequest'] ?? null;
        }


        if ($confirmationRequest === null) {
            throw new \RuntimeException('No confirmation request provided to CompleteRegistrationFinisher', 1687334861);
        }

        $this->confirmationService->setRegistrationCompleted($confirmationRequest);

    }


}

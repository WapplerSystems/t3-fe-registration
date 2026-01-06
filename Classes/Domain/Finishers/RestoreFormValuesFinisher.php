<?php

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;
use WapplerSystems\FeRegistration\Event\AfterRegistrationCompletionEvent;
use WapplerSystems\FeRegistration\Service\ConfirmationService;
use WapplerSystems\FeRegistration\Service\DatabaseService;
use WapplerSystems\FeRegistration\Service\MailingService;

class RestoreFormValuesFinisher extends AbstractFinisher
{

    /**
     * @var array
     */
    protected $defaultOptions = [
        'confirmationRequest' => null,
    ];


    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository,
                                readonly EventDispatcherInterface      $eventDispatcher,
                                readonly ConfirmationService           $confirmationService,
                                readonly Random                        $random)
    {
    }


    /**
     * @see AbstractFinisher::execute()
     *
     */
    protected function executeInternal()
    {
        /** @var ConfirmationRequest $confirmationRequest */
        $confirmationRequest = $this->options['confirmationRequest'];

        $formValues = $confirmationRequest->getDecodedValues();
        foreach ($formValues as $formKey => $formValue) {
            $this->finisherContext->getFormRuntime()->getFormState()->setFormValue($formKey, $formValue);
        }

    }


}

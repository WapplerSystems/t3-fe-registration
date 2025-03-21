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

class CompleteRegistrationFinisher extends AbstractFinisher
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
                                readonly DatabaseService               $databaseService,
                                readonly MailingService                $mailingService,
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
        $feUserUid = $this->options['feUserUid'];
        $settings = $this->options['settings'];

        $formValues = $this->finisherContext->getFormValues();
        if (count($formValues)) {
            $this->databaseService->updateFeUser($feUserUid, $formValues);
        }
        $formValues = array_merge($formValues, $confirmationRequest->getDecodedValues());

        $user = $this->databaseService->getFeUser($feUserUid);

        $formDefinition = $this->finisherContext->getFormRuntime()->getFormDefinition();

        $password = null;
        if (!$formDefinition->getRenderingOptions()['hasPasswordField']) {

            $passwordRules = [
                'length' => 10,
                'digitCharacters' => true,
                'specialCharacters' => false,
            ];
            $password = $this->random->generateRandomPassword($passwordRules);

            $this->databaseService->updateFeUserPassword($feUserUid, $password);
        }


        $this->eventDispatcher->dispatch(
            new AfterRegistrationCompletionEvent($user, $formValues, $settings)
        );

        $this->mailingService->sendWelcomeMail($user, $this->finisherContext->getRequest(), $settings, $password);

        $this->databaseService->setRegistrationCompletedOfUser($feUserUid);

    }


}

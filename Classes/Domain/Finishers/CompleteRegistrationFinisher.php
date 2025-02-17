<?php

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;
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

        $confirmationRequest = $this->options['confirmationRequest'];
        $feUserUid = $this->options['feUserUid'];
        $settings = $this->options['settings'];

        $values = $this->finisherContext->getFormValues();
        if (count($values)) {
            $this->databaseService->updateFeUser($feUserUid, $values);
        }

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



        $this->mailingService->sendWelcomeMail($user, $this->finisherContext->getRequest(), $settings, $password);


        //DebugUtility::debug($values, 'CompleteRegistrationFinisher');
        //exit();


        $this->confirmationService->completeRegistration($confirmationRequest);


    }


}

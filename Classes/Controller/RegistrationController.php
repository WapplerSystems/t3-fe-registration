<?php

namespace WapplerSystems\FeRegistration\Controller;

use Doctrine\DBAL\ParameterType;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Exception;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;
use WapplerSystems\FeRegistration\Form\Factory\RegistrationPatchFormFactory;
use WapplerSystems\FeRegistration\Service\ConfirmationService;
use WapplerSystems\FeRegistration\Service\MailingService;

class RegistrationController extends ActionController
{


    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository,
                                EventDispatcherInterface               $eventDispatcher,
                                readonly ConfirmationService           $confirmationService)
    {
    }


    public function newAction(): ResponseInterface
    {
        if (($this->settings['form'] ?? '') === '') {
            return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No form configuration found.']));
        }
        if (($this->settings['identifierFieldName'] ?? '') === '') {
            return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No identifier field name set.']));
        }
        if (($this->settings['confirmationEmail']['senderEmailAddress'] ?? '') === '') {
            return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No sender email address set.']));
        }
        if (($this->settings['confirmationEmail']['senderName'] ?? '') === '') {
            return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No sender name set.']));
        }
        if (($this->settings['confirmationRequestPid'] ?? '') === '') {
            return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No storage for confirmation requests set.']));
        }
        if (($this->settings['feUserStoragePid'] ?? '') === '') {
            return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No storage of user records set.']));
        }
        if (($this->settings['usergroups'] ?? '') === '') {
            return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No usergroup set.']));
        }

        $confirmationRequestFinisher = [
            'identifier' => 'ConfirmationRequest',
            'options' => [
                'confirmationRequestPid' => $this->settings['confirmationRequestPid'] ?? '',
            ]
        ];
        $emailFinisher = [
            'identifier' => 'ConfirmationEmail',
            'options' => [
                'senderAddress' => $this->settings['confirmationEmail']['senderEmailAddress'] ?? '',
                'senderName' => $this->settings['confirmationEmail']['senderName'] ?? '',
                'useFluidEmail' => $this->settings['confirmationEmail']['useFluidEmail'] ?? 0,
                'subject' => LocalizationUtility::translate('LLL:EXT:fe_registration/Resources/Private/Language/locallang.xlf:confirmationEmail.subject'),
                'recipients' => ['{email}'],
                'templateName' => 'Email/Confirmation',
            ]
        ];
        $redirectFinisher = [
            'identifier' => 'RedirectToUri',
            'options' => [
                'uri' => $this->uriBuilder->reset()->uriFor('confirmationMailSent'),
            ]
        ];

        $overrideConfiguration = [
            'finishers' => [
                'ConfirmationRequest' => $confirmationRequestFinisher, // save request
                'ConfirmationEmail' => $emailFinisher,  // send email
                'RedirectToUri' => $redirectFinisher, // output message
            ],
            'renderingOptions' => [
                'controllerAction' => 'new',
            ]
        ];

        $factory = GeneralUtility::makeInstance(RegistrationPatchFormFactory::class, $this->settings, $this->uriBuilder);

        return $this->htmlResponse($this->view->renderSection('Form', [
            'settings' => $this->settings,
            'overrideConfiguration' => $overrideConfiguration,
            'factory' => $factory,
        ]));
    }

    /**
     *
     * @param string $hash
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function confirmAction(string $hash = ''): ResponseInterface
    {

        if (($this->settings['form'] ?? '') === '') {
            return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No form configuration found.']));
        }

        if ($hash !== '') {
            /** @var ConfirmationRequest $confirmationRequest */
            $confirmationRequest = $this->confirmationRequestRepository->findOneByConfirmationHash($hash);

            if ($confirmationRequest) {

                /*
                if ($confirmationRequest->isConfirmed()) {
                    return $this->htmlResponse($this->view->renderSection('AlreadyConfirmed'));
                }*/

                $feUser = $this->confirmationService->requestToFeUser($confirmationRequest, $this->settings);
                if ($feUser === null) {
                    return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No user loadable or creatable.']));
                }
                if (($feUser['registration_completed'] ?? 0) === 1) {
                    return $this->htmlResponse($this->view->renderSection('AlreadyCompleted'));
                }

                $completeRegistrationFinisher = [
                    'identifier' => 'CompleteRegistration',
                    'options' => [
                        'confirmationRequest' => $confirmationRequest,
                        'feUserUid' => $feUser['uid'],
                        'settings' => $this->settings
                    ]
                ];
                $redirectFinisher = [
                    'identifier' => 'RedirectToUri',
                    'options' => [
                        'uri' => $this->uriBuilder->reset()->uriFor('success'),
                    ]
                ];


                $overrideConfiguration = [
                    'finishers' => [
                        //'SaveFeUser' => $saveFeUserFinisher,
                        'CompleteRegistration' => $completeRegistrationFinisher,
                        'RedirectToUri' => $redirectFinisher,
                    ],
                    'renderingOptions' => [
                        'controllerAction' => 'confirm',
                        'additionalParams' => ['tx_feregistration_registration' => ['hash' => $hash]],
                        'submitButtonLabel' => LocalizationUtility::translate('LLL:EXT:fe_registration/Resources/Private/Language/locallang.xlf:btn.completeRegistration'),
                    ]
                ];

                $factory = GeneralUtility::makeInstance(RegistrationPatchFormFactory::class, $this->settings, $this->uriBuilder, $confirmationRequest->getDecodedValues());

                return $this->htmlResponse($this->view->renderSection('Form', [
                    'settings' => $this->settings,
                    'overrideConfiguration' => $overrideConfiguration,
                    'factory' => $factory,
                ]));
            }
        }

        return $this->htmlResponse($this->view->renderSection('HashNotFound'));
    }


    public function successAction(): ResponseInterface
    {

        return $this->htmlResponse();
    }

    public function confirmationMailSentAction(): ResponseInterface
    {

        return $this->htmlResponse();
    }


    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     * @throws FinisherException
     * @throws \Doctrine\DBAL\Exception
     */
    public function resendConfirmationEmailAction(): ResponseInterface
    {


        $currentPageId = (int)($this->request->getAttribute('routing')->getPageId() ?? 0);
        $currentLanguageUid = (int)($this->request->getAttribute('language')->getLanguageId() ?? 0);

        // QueryBuilder für tt_content erstellen
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');

        $settings = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS);


        // Inhaltselement mit CType 'feregistration' und aktueller Sprache suchen
        $record = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($currentPageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('feregistration_registration')),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($currentLanguageUid, ParameterType::INTEGER))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($record) {
            // FlexForm-Daten parsen
            $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
            $flexFormSettings = $flexFormService->convertFlexFormContentToArray($record['pi_flexform']);

            // FlexForm-Werte nutzen
            $settings = array_merge($settings, $flexFormSettings['settings'] ?? []);

            $email = $this->request->getQueryParams()['email'] ?? '';

            /** @var ConfirmationRequest $confirmationRequestRecord */
            $confirmationRequestRecord = $this->confirmationRequestRepository->findOneByEmail($email);
            if ($confirmationRequestRecord) {

                if ($confirmationRequestRecord->isConfirmed()) {
                    return new JsonResponse(['success' => false, 'alreadyConfirmed' => true]);
                }
                if ($confirmationRequestRecord->getLastSent() && $confirmationRequestRecord->getLastSent()->getTimestamp() + (int)$settings['confirmationEmail']['timeLock'] > time()) {
                    return new JsonResponse(['success' => false, 'wait' => true, 'nextSend' => $confirmationRequestRecord->getLastSent()->getTimestamp() + (int)$settings['confirmationEmail']['timeLock']]);
                }

                /** @var MailingService $mailer */
                $mailer = GeneralUtility::makeInstance(MailingService::class);
                $mailer->sendConfirmationMail($confirmationRequestRecord, $this->request, $settings, $currentPageId);


                return new JsonResponse(['success' => true]);
            }


        }


        return new JsonResponse(['success' => false]);
    }


}

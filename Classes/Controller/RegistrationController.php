<?php

namespace WapplerSystems\FeRegistration\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Exception;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Model\EmailAddress;
use WapplerSystems\FeRegistration\Domain\Repository\EmailAddressRepository;
use WapplerSystems\FeRegistration\Domain\Repository\FrontendUserRepository;
use WapplerSystems\FeRegistration\Form\Factory\RegistrationPatchFormFactory;
use WapplerSystems\FeRegistration\Service\ConfirmationService;
use WapplerSystems\FeRegistration\Service\ContentElementService;
use WapplerSystems\FeRegistration\Service\DatabaseService;
use WapplerSystems\FeRegistration\Service\MailingService;

class RegistrationController extends ActionController
{


    public function __construct(readonly EmailAddressRepository $emailAddressRepository,
                                EventDispatcherInterface        $eventDispatcher,
                                readonly DatabaseService        $databaseService,
                                readonly ConfirmationService    $confirmationService,
                                readonly PersistenceManager     $persistenceManager,
                                readonly FrontendUserRepository $frontendUserRepository,
                                readonly MailingService         $mailingService,
                                readonly ContentElementService  $contentElementService,
                                readonly LoggerInterface        $logger,
    )
    {
    }


    public function newAction(): ResponseInterface
    {
        if (($this->settings['form'] ?? '') === '') {
            return $this->renderErrorMessage(
                message: 'No form configuration found.',
            );
        }
        if (($this->settings['identifierFieldName'] ?? '') === '') {
            return $this->renderErrorMessage(
                message: 'No identifier field name set.',
            );
        }
        if (($this->settings['emailFieldName'] ?? '') === '') {
            return $this->renderErrorMessage(
                message: 'No email field name set.',
            );
        }
        if (($this->settings['confirmationEmail']['senderEmailAddress'] ?? '') === '') {
            return $this->renderErrorMessage(
                message: 'No sender email address set.',
            );
        }
        if (($this->settings['confirmationEmail']['senderName'] ?? '') === '') {
            return $this->renderErrorMessage(
                message: 'No sender name set.',
            );
        }
        if (($this->settings['confirmationRequestPid'] ?? '') === '') {
            return $this->renderErrorMessage(
                message: 'No storage for confirmation requests set.',
            );
        }
        if (($this->settings['feUserStoragePid'] ?? '') === '') {
            return $this->renderErrorMessage(
                message: 'No storage of user records set.',
            );
        }
        if (($this->settings['usergroups'] ?? '') === '') {
            return $this->renderErrorMessage(
                message: 'No usergroup set.',
            );
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
                'recipients' => ['{' . $this->settings['emailFieldName'] . '}'],
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

        $factory = GeneralUtility::makeInstance(RegistrationPatchFormFactory::class);
        $factory->setSettings($this->settings);
        $factory->setUriBuilder($this->uriBuilder);
        $factory->setPreDefinedValues([]);

        $this->view->assignMultiple([
            'settings' => $this->settings,
            'overrideConfiguration' => $overrideConfiguration,
            'factory' => $factory,
        ]);

        return $this->htmlResponse();
    }


    public function renderErrorMessage(?string $message = null, ?string $title = null): ResponseInterface
    {
        $this->view->assignMultiple([
            'settings' => $this->settings,
            'title' => $title,
            'message' => $message,
        ]);
        return $this->htmlResponse($this->view->render('ErrorMessage'));
    }

    public function renderNotification(string $type): ResponseInterface
    {
        $this->view->assignMultiple([
            'settings' => $this->settings,
            'type' => $type,
        ]);
        return $this->htmlResponse($this->view->render('Notifications'));
    }


    /**
     *
     * @param string $hash
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException|AspectNotFoundException
     */
    public function confirmAction(string $hash = ''): ResponseInterface
    {

        $currentContentObject = $this->request->getAttribute('currentContentObject');
        $contentUid = $currentContentObject->data['uid'];

        if (($this->settings['form'] ?? '') === '') {
            return $this->renderErrorMessage(
                message: 'No form configuration found.',
            );
        }

        if ($hash !== '') {

            $confirmationRequest = $this->confirmationService->findByHash($hash);

            if ($confirmationRequest) {

                if ($confirmationRequest->isRegistrationCompleted()) {
                    return $this->renderNotification(
                        type: 'AlreadyCompleted',
                    );
                }

                if ($confirmationRequest->isExpired()) {
                    return $this->renderNotification(
                        type: 'Expired',
                    );
                }

                $this->confirmationService->setRequestConfirmed($confirmationRequest);


                // TODO: change to event dispatcher
                // optional
                $frontendUser = $this->frontendUserRepository->findByConfirmationRequest($confirmationRequest);

                $completeRegistrationFinisher = [
                    'identifier' => 'CompleteRegistration',
                    'options' => [
                        'confirmationRequest' => $confirmationRequest,
                        'settings' => $this->settings
                    ]
                ];

                $notificationEmailRecipients = [];
                if ((int)($this->settings['notificationEmails']['registrationCompleted']['emailAddresses'] ?? 0) > 0) {
                    $addresses = $this->emailAddressRepository->findByTablenameAndUidForeignAndFieldname('tt_content', $contentUid, 'settings.notificationEmails.registrationCompleted.emailAddresses');
                    /** @var EmailAddress $address */
                    foreach ($addresses as $address) {
                        $notificationEmailRecipients[$address->getEmail()] = $address->getName();
                    }
                }
                $notificationEmailFinisher = [
                    'identifier' => 'EmailToReceiver',
                    'options' => [
                        'senderAddress' => $this->settings['notificationEmails']['senderEmailAddress'] ?? '',
                        'senderName' => $this->settings['notificationEmails']['senderName'] ?? '',
                        'useFluidEmail' => $this->settings['notificationEmails']['useFluidEmail'] ?? 0,
                        'subject' => LocalizationUtility::translate('LLL:EXT:fe_registration/Resources/Private/Language/locallang.xlf:notificationEmail.subject'),
                        'recipients' => $notificationEmailRecipients,
                        'templateName' => 'Email/Notification/RegistrationCompleted',
                        'variables' => [
                            'confirmationRequest' => $confirmationRequest,
                            'user' => $frontendUser
                        ]
                    ]
                ];
                $redirectFinisher = [
                    'identifier' => 'RedirectToUri',
                    'options' => [
                        'uri' => $this->uriBuilder->reset()->uriFor('success'),
                    ]
                ];

                $finishers = [];
                if (count($notificationEmailRecipients) > 0) {
                    $finishers['NotificationEmail'] = $notificationEmailFinisher;
                }
                $finishers['CompleteRegistration'] = $completeRegistrationFinisher;
                $finishers['RedirectToUri'] = $redirectFinisher;

                $overrideConfiguration = [
                    'finishers' => $finishers,
                    'renderingOptions' => [
                        'fe-registration' => true,
                        'controllerAction' => 'confirm',
                        'additionalParams' => ['tx_feregistration_registration' => ['hash' => $hash]],
                        'submitButtonLabel' => LocalizationUtility::translate('LLL:EXT:fe_registration/Resources/Private/Language/locallang.xlf:btn.completeRegistration'),
                    ]
                ];

                $factory = GeneralUtility::makeInstance(RegistrationPatchFormFactory::class);
                $factory->setSettings($this->settings);
                $factory->setUriBuilder($this->uriBuilder);
                $factory->setPreDefinedValues($confirmationRequest->getDecodedValues());
                $factory->setConfirmationRequest($confirmationRequest);

                $this->view->assignMultiple([
                    'settings' => $this->settings,
                    'overrideConfiguration' => $overrideConfiguration,
                    'factory' => $factory,
                ]);
                return $this->htmlResponse();
            }
        }

        return $this->renderNotification(
            type: 'HashNotFound',
        );
    }


    public function successAction(): ResponseInterface
    {

        $this->view->assignMultiple([
            'settings' => $this->settings,
        ]);

        return $this->htmlResponse();
    }

    public function confirmationMailSentAction(): ResponseInterface
    {

        return $this->htmlResponse();
    }


    /**
     * Re-sends the double-opt-in mail for a still-unconfirmed registration.
     *
     * Hardened against email-enumeration and SMTP-spam abuse:
     *  - POST only — GET requests can't be triggered cross-origin via a
     *    `<img>` / link prefetch, and POST forces the caller to be a real
     *    XHR/fetch from the same origin.
     *  - Uniform response shape and content; never reveal whether the email
     *    exists, was already confirmed, or is throttled.
     *  - Per-IP throttle bound to the configured `timeLock`. Cuts off bulk
     *    probing even for non-existent emails (the per-email gate already
     *    protects legitimate inboxes).
     */
    public function resendConfirmationEmailAction(): ResponseInterface
    {
        $uniformResponse = new JsonResponse(['success' => true]);

        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $uniformResponse;
        }

        $currentPageId = (int)($this->request->getAttribute('routing')->getPageId() ?? 0);
        $currentLanguageUid = (int)($this->request->getAttribute('language')->getLanguageId() ?? 0);

        $settings = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS);

        $pluginContentRecord = $this->contentElementService->findFeRegistrationPlugin($currentPageId, $currentLanguageUid);
        if ($pluginContentRecord === null) {
            return $uniformResponse;
        }

        $flexFormSettings = GeneralUtility::makeInstance(FlexFormService::class)
            ->convertFlexFormContentToArray($pluginContentRecord['pi_flexform']);
        $settings = array_merge($settings, $flexFormSettings['settings'] ?? []);

        $timeLock = (int)($settings['confirmationEmail']['timeLock'] ?? 300);

        $remoteAddress = (string)($this->request->getAttribute('normalizedParams')?->getRemoteAddress() ?? '');
        $ipKey = 'resend-' . sha1($remoteAddress);
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('fe_registration');
        if ($cache->has($ipKey)) {
            return $uniformResponse;
        }
        // Burn the per-IP slot up front so a slow downstream send can't be
        // replayed mid-flight.
        $cache->set($ipKey, time(), [], max(60, $timeLock));

        $parsedBody = $this->request->getParsedBody();
        $email = is_array($parsedBody) ? trim((string)($parsedBody['email'] ?? '')) : '';
        if ($email === '') {
            return $uniformResponse;
        }

        $confirmationRequest = $this->confirmationService->findUnconfirmedByEmail($email);
        if ($confirmationRequest === null || $confirmationRequest->isConfirmed()) {
            return $uniformResponse;
        }

        $lastSentTs = $confirmationRequest->getLastSent()?->getTimestamp() ?? 0;
        if (($lastSentTs + $timeLock) > time()) {
            return $uniformResponse;
        }

        try {
            $this->mailingService->sendConfirmationMail($confirmationRequest, $this->request, $settings, $currentPageId);
        } catch (\Throwable $e) {
            $this->logger->error('resendConfirmationEmail: send failed', [
                'exception' => $e->getMessage(),
            ]);
        }

        return $uniformResponse;
    }


}

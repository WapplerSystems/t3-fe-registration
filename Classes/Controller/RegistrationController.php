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
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;
use WapplerSystems\FeRegistration\Event\AfterConfirmationEvent;
use WapplerSystems\FeRegistration\Form\Factory\RegistrationPatchFormFactory;
use WapplerSystems\FeRegistration\Service\Database;
use WapplerSystems\FeRegistration\Service\Mailer;

class RegistrationController extends ActionController
{


    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository,
                                EventDispatcherInterface               $eventDispatcher)
    {
    }


    public function newAction(): ResponseInterface
    {
        if (($this->settings['formStep1'] ?? '') === '') {
            return $this->htmlResponse($this->view->renderSection('Error', ['error' => 'No form configuration found for step 1.']));
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

        $confirmationRequestFinisher = [
            'identifier' => 'ConfirmationRequest',
            'options' => [
                'confirmationRequestPid' => $this->settings['confirmationRequestPid'] ?? '',
            ]
        ];
        $emailFinisher = [
            'identifier' => 'EmailToSender',
            'options' => [
                'senderAddress' => $this->settings['confirmationEmail']['senderEmailAddress'] ?? '',
                'senderName' => $this->settings['confirmationEmail']['senderName'] ?? '',
                'useFluidEmail' => $this->settings['confirmationEmail']['useFluidEmail'] ?? 0,
                'subject' => LocalizationUtility::translate('LLL:EXT:fe_registration/Resources/Private/Language/locallang.xlf:confirmationEmail.subject'),
                'recipients' => ['{email}'],
                'templateName' => 'Email/ConfirmationMail',
            ]
        ];
        $confirmationFinisher = [
            'identifier' => 'Confirmation',
            'options' => [

            ]
        ];

        $overrideConfiguration = [
            'finishers' => [
                'ConfirmationRequest' => $confirmationRequestFinisher,
                'ConfirmationEmail' => $emailFinisher,
                'Confirmation' => $confirmationFinisher,
            ],
            'renderingOptions' => [
                'controllerAction' => 'new',
            ]
        ];

        GeneralUtility::makeInstance(RegistrationPatchFormFactory::class, $this->settings, $this->uriBuilder);

        return $this->htmlResponse($this->view->renderSection('Form', [
            'settings' => $this->settings,
            'overrideConfiguration' => $overrideConfiguration,
            'factoryClass' => RegistrationPatchFormFactory::class
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

        if ($hash !== '') {
            /** @var ConfirmationRequest $confirmationRequest */
            $confirmationRequest = $this->confirmationRequestRepository->findOneByConfirmationHash($hash);

            if ($confirmationRequest) {

                if ($confirmationRequest->isConfirmed()) {
                    return $this->htmlResponse($this->view->renderSection('AlreadyConfirmed'));
                }

                $values = $confirmationRequest->getDecodedValues();


                if (($this->settings['formStep2'] ?? '') !== '') {

                    // Formular für Schritt 2 laden


                    return $this->htmlResponse($this->view->renderSection('Form'));
                }

                if ((int)($this->settings['createFeUser'] ?? 0) === 1) {

                    /** @var Database $database */
                    $database = GeneralUtility::makeInstance(Database::class);
                    $database->createFeUser($values, $this->settings);
                }


                $confirmationRequest->setConfirmationDate(new \DateTime());
                $this->confirmationRequestRepository->update($confirmationRequest);

                $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
                $persistenceManager->persistAll();

                $this->eventDispatcher->dispatch(
                    new AfterConfirmationEvent($confirmationRequest)
                );


                if (isset($this->settings['forward']) && (int)$this->settings['forward'] > 0) {
                    $url = $this->uriBuilder->reset()->setCreateAbsoluteUri(true)->setTargetPageUid($this->settings['forward'])->build();
                    $this->redirectToUri($url);
                }

                $this->view->assignMultiple($values);

                return $this->htmlResponse($this->view->renderSection('Success'));
            }
        }

        return $this->htmlResponse($this->view->renderSection('HashNotFound'));
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

                /** @var Mailer $mailer */
                $mailer = GeneralUtility::makeInstance(Mailer::class);
                $mailer->sendConfirmationMail($confirmationRequestRecord, $this->request, $settings, $currentPageId);


                return new JsonResponse(['success' => true]);
            }


        }


        return new JsonResponse(['success' => false]);
    }


    public function successAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }

}

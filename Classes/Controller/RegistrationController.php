<?php

namespace WapplerSystems\FeRegistration\Controller;

use Doctrine\DBAL\ParameterType;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;
use WapplerSystems\FeRegistration\Event\AfterConfirmationEvent;
use WapplerSystems\FeRegistration\Form\Factory\RegistrationPatchFormFactory;
use WapplerSystems\FeRegistration\Service\Mailer;

class RegistrationController extends ActionController
{


    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository,
                                EventDispatcherInterface               $eventDispatcher)
    {
    }


    public function newAction(): ResponseInterface
    {
        DebugUtility::debug($this->settings);

        $confirmationRequestFinisher = [
            'identifier' => 'ConfirmationRequest',
            'options' => [
                'confirmationRequestPid' => $this->settings['confirmationRequestPid'] ?? '',
            ]
        ];
        $emailFinisher = [
            'identifier' => 'ConfirmationEmail',
            'options' => [
                'senderAddress' => $this->settings['confirmationEmail']['senderAddress'] ?? '',
                'senderName' => $this->settings['confirmationEmail']['senderName'] ?? '',
                'useFluidEmail' => $this->settings['confirmationEmail']['useFluidEmail'] ?? 0,
                'subject' => LocalizationUtility::translate('LLL:EXT:fe_registration/Resources/Private/Language/locallang.xlf:confirmationEmail.subject')
            ]
        ];

        $overrideConfiguration = [
            'finishers' => [
                'ConfirmationRequest' => $confirmationRequestFinisher,
                'ConfirmationEmail' => $emailFinisher,
            ],
            'renderingOptions' => [
                'controllerAction' => 'new',

            ]
        ];
        $this->view->assign('overrideConfiguration', $overrideConfiguration);


        GeneralUtility::makeInstance(RegistrationPatchFormFactory::class, $this->settings, $this->uriBuilder);



        $this->view->assign('factoryClass', RegistrationPatchFormFactory::class);

        return $this->htmlResponse();
    }

    /**
     * action validation
     *
     * @param string $hash
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function validateAction(string $hash = ''): ResponseInterface
    {

        if ($hash !== '') {
            /** @var ConfirmationRequest $confirmationRequest */
            $confirmationRequest = $this->confirmationRequestRepository->findOneByConfirmationHash($hash);

            if ($confirmationRequest) {

                if ($confirmationRequest->isConfirmed()) {
                    $this->view->assign('alreadyConfirmed', true);
                    return $this->htmlResponse();
                }

                $confirmationRequest->setIsConfirmed(TRUE);
                $confirmationRequest->setConfirmationDate(new \DateTime);
                $this->confirmationRequestRepository->update($confirmationRequest);

                $this->eventDispatcher->dispatch(
                    new AfterConfirmationEvent($confirmationRequest)
                );

                if (isset($this->settings['forward']) && (int)$this->settings['forward'] > 0) {
                    $url = $this->uriBuilder->reset()->setCreateAbsoluteUri(true)->setTargetPageUid($this->settings['forward'])->build();
                    $this->redirectToUri($url);
                }

                if ((int)($this->settings['createFeUser'] ?? 0) === 1) {

                    $values = $confirmationRequest->getDecodedValues();

                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
                        'fe_users'
                    );
                    $queryBuilder
                        ->insert('fe_users')
                        ->values([
                            'pid' => (int)$this->settings['feUserStoragePid'],
                            'usergroup' => $this->settings['usergroups'] ?? '',
                            'username' => $values['email'],
                            'email' => $values['email'],
                            'first_name' => $values['firstName'] ?? '',
                            'last_name' => $values['lastName'] ?? '',
                            'password' => $values['password'],
                            'crdate' => time(),
                            'tstamp' => time(),
                            'deleted' => 0,
                            'disable' => 0,
                        ])
                        ->executeStatement();

                    $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
                    $persistenceManager->persistAll();
                }


                $this->view->assign('success', true);
                return $this->htmlResponse();
            }
        }

        $this->view->assign('notFound', true);

        return $this->htmlResponse();
    }


    public function resendConfirmationEmailAction(): ResponseInterface
    {


        $currentPageId = (int)($this->request->getAttribute('routing')->getPageId() ?? 0);
        $currentLanguageUid = (int)($this->request->getAttribute('language')->getLanguageId() ?? 0);

        // QueryBuilder für tt_content erstellen
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');

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
            $settings = $flexFormSettings['settings'] ?? null;


            $email = $this->request->getQueryParams()['email'] ?? '';

            /** @var ConfirmationRequest $confirmationRequestRecord */
            $confirmationRequestRecord = $this->confirmationRequestRepository->findOneByEmail($email);
            if ($confirmationRequestRecord) {

                if ($confirmationRequestRecord->isConfirmed()) {
                    return new JsonResponse(['success' => false, 'alreadyConfirmed' => true]);
                }
                if ($confirmationRequestRecord->getLastSent() && $confirmationRequestRecord->getLastSent()->getTimestamp() + (int)$settings['optInEmail']['timeLock'] > time()) {
                    return new JsonResponse(['success' => false, 'wait' => true, 'nextSend' => $confirmationRequestRecord->getLastSent()->getTimestamp() + (int)$settings['optInEmail']['timeLock']]);
                }

                $settings['validationPid'] = $currentPageId;

                /** @var Mailer $mailer */
                $mailer = GeneralUtility::makeInstance(Mailer::class);
                $mailer->sendconfirmationMail($confirmationRequestRecord, $this->request, $settings);


                return new JsonResponse(['success' => true]);
            }


        }




        return new JsonResponse(['success' => false]);
    }


}

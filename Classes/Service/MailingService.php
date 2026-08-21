<?php

namespace WapplerSystems\FeRegistration\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Exception;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;

class MailingService
{

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ConfirmationRequestRepository $confirmationRequestRepository,
        private readonly PersistenceManager $persistenceManager,
        private readonly MailerInterface $mailer,
    ) {
    }


    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     * @throws FinisherException
     */
    public function sendConfirmationMail(ConfirmationRequest $confirmationRequest, ServerRequestInterface $request, array $settings, int $pageId): void
    {

        $receiverAddress = $confirmationRequest->getEmail();

        $addHtmlPart = $settings['confirmationEmail']['useHTML'] ?? false;

        $senderAddress = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '';
        $senderName = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] ?? '';


        $senderAddress = !empty($settings['confirmationEmail']['senderEmailAddress'] ?? '') ? $settings['confirmationEmail']['senderEmailAddress'] :  $senderAddress;
        $senderName = !empty($settings['confirmationEmail']['senderName'] ?? '') ? $settings['confirmationEmail']['senderName'] :  $senderName;

        if (empty($senderAddress)) {
            throw new Exception('The option "senderEmailAddress" must be set.', 1735853778);
        }
        if (empty($senderName)) {
            throw new Exception('The option "senderName" must be set.', 1735853779);
        }


        $mail = $this
            ->initializeFluidEmail('Email/Confirmation', $settings, $request)
            ->from(new Address($senderAddress, $senderName))
            ->to($receiverAddress)
            ->subject(LocalizationUtility::translate('subject.pleaseConfirmEmailAddress', 'fe_registration'))
            ->format($addHtmlPart ? FluidEmail::FORMAT_BOTH : FluidEmail::FORMAT_PLAIN)
            ->assign('title', LocalizationUtility::translate('subject.pleaseConfirmEmailAddress', 'fe_registration'))
            ->assign('confirmationRequest', $confirmationRequest)
            ->assign('confirmationHash', $confirmationRequest->getConfirmationHash())
            ->assign('pageId', $pageId);

        try {
            $this->mailer->send($mail);
        } catch (TransportExceptionInterface $e) {
            // No email address in the log context: it is personal data, the log file
            // has no retention of its own, and the confirmation request uid below is
            // enough to trace the case while it exists.
            $this->logger->error('Failed to send confirmation email', ['confirmationRequest' => $confirmationRequest->getUid(), 'exception' => $e->getMessage()]);
            throw $e;
        }

        $confirmationRequest->setLastSent(new \DateTime());
        $this->confirmationRequestRepository->update($confirmationRequest);
        $this->persistenceManager->persistAll();

        $this->logger->info('Confirmation email sent', ['confirmationRequest' => $confirmationRequest->getUid()]);
    }



    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     * @throws FinisherException
     */
    public function sendWelcomeMail(array $feUser, ServerRequestInterface $request, array $settings): void
    {

        $addHtmlPart = $settings['welcomeEmail']['useHTML'] ?? false;

        $senderAddress = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '';
        $senderName = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] ?? '';

        $receiverAddress = $feUser['email'] === '' ? $feUser['username'] : $feUser['email'];

        $senderAddress = !empty($settings['welcomeEmail']['senderEmailAddress'] ?? '') ? $settings['welcomeEmail']['senderEmailAddress'] :  $senderAddress;
        $senderName = !empty($settings['welcomeEmail']['senderName'] ?? '') ? $settings['welcomeEmail']['senderName'] :  $senderName;

        if (empty($senderAddress)) {
            throw new Exception('The option "senderEmailAddress" must be set.', 1735853778);
        }
        if (empty($senderName)) {
            throw new Exception('The option "senderName" must be set.', 1735853779);
        }

        $mail = $this
            ->initializeFluidEmail('Email/Welcome', $settings['email'], $request)
            ->from(new Address($senderAddress, $senderName))
            ->to($receiverAddress)
            ->subject(LocalizationUtility::translate('subject.welcome', 'fe_registration'))
            ->format($addHtmlPart ? FluidEmail::FORMAT_BOTH : FluidEmail::FORMAT_PLAIN)
            ->assign('title', LocalizationUtility::translate('subject.welcome', 'fe_registration'))
            ->assign('user', $feUser)
            ->assign('mustBeConfirmed', $settings['feUserMustConfirmed'] ?? false);

        try {
            $this->mailer->send($mail);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send welcome email', ['exception' => $e->getMessage()]);
            throw $e;
        }

        $this->logger->info('Welcome email sent', []);
    }



    protected function initializeFluidEmail(string $templateName, array $options, ServerRequestInterface $request): FluidEmail
    {
        $templatePaths = $this->initializeTemplatePaths(
            $GLOBALS['TYPO3_CONF_VARS']['MAIL'],
            $options,
        );
        /** @var FluidEmail $fluidEmail */
        $fluidEmail = GeneralUtility::makeInstance(FluidEmail::class, $templatePaths);


        $fluidEmail
            ->setRequest($request)
            ->setTemplate($templateName);

        if (is_array($options['variables'] ?? null)) {
            $fluidEmail->assignMultiple($options['variables']);
        }

        return $fluidEmail;
    }


    protected function initializeTemplatePaths(array $globalConfig, array $localConfig): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths(array_replace(
            $globalConfig['templateRootPaths'] ?? [],
            $localConfig['templateRootPaths'] ?? [],
        ));
        $templatePaths->setLayoutRootPaths(array_replace(
            $globalConfig['layoutRootPaths'] ?? [],
            $localConfig['layoutRootPaths'] ?? [],
        ));
        $templatePaths->setPartialRootPaths(array_replace(
            $globalConfig['partialRootPaths'] ?? [],
            $localConfig['partialRootPaths'] ?? [],
        ));
        return $templatePaths;
    }

}

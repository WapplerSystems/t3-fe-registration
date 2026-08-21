<?php

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\ViewHelpers\RenderRenderableViewHelper;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;
use WapplerSystems\FeRegistration\Service\AddressLookupService;
use WapplerSystems\FeRegistration\Event\AfterConfirmationRequestCreationEvent;

class ConfirmationRequestFinisher extends AbstractFinisher
{

    /**
     * @var array
     */
    protected $defaultOptions = [
        'confirmationRequestPid' => null,
        'expirationDays' => 7,
        // Storage page of the fe_users records. Needed so the guard below can also
        // recognise an address that already has an account, not just a pending
        // request. 0 skips that half of the check.
        'feUserStoragePid' => 0,
    ];

    /**
     * Rendering option set when the submitted address already has an account or a
     * pending request. ConfirmationEmailFinisher reads it and sends the
     * "you already have an account" mail instead of the double-opt-in mail.
     */
    public const RENDERING_OPTION_ACCOUNT_EXISTS = 'accountExists';

    /**
     * Fallback lifetime of a double-opt-in link, also used when `expirationDays` is
     * configured as 0 or negative.
     */
    private const DEFAULT_EXPIRATION_DAYS = 7;


    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository,
                                readonly EventDispatcherInterface      $eventDispatcher,
                                readonly PersistenceManager            $persistenceManager)
    {
    }


    /**
     * Executes this finisher
     * @throws FinisherException|IllegalObjectTypeException
     * @see AbstractFinisher::execute()
     *
     */
    protected function executeInternal()
    {
        $formValues = $this->getFormValues();

        $email = null;
        $elements = $this->finisherContext->getFormRuntime()->getFormDefinition()->getElements();
        foreach ($elements as $element) {
            if ($element->getType() === 'Email') {
                $email = $formValues[$element->getIdentifier()] ?? null;
            }
        }
        if ($email === null) {
            throw new FinisherException('No receiver email address found in form data.', 1599834020);
        }

        // Account-enumeration guard. The email field used to carry the
        // ConfirmationRequest and FeUser validators, which answered "this address is
        // already registered" straight back into the form — anyone could probe who has
        // an account. Those validators are gone from the form; the check happens here
        // instead, silently.
        //
        // This also carries the duplicate-protection the ConfirmationRequest validator
        // used to provide: without it a second submission would insert another row for
        // the same address.
        //
        // Nothing is persisted and no confirmationHash is written. The rest of the
        // finisher chain still runs, so the visitor sees exactly the same view as after
        // a genuine signup; ConfirmationEmailFinisher swaps the mail template.
        $confirmationRequestPid = (int)$this->options['confirmationRequestPid'];
        $addressBlocked = GeneralUtility::makeInstance(AddressLookupService::class)->isAddressBlocked(
            $email,
            $confirmationRequestPid,
            (int)($this->options['feUserStoragePid'] ?? 0)
        );

        if ($addressBlocked) {
            $this->finisherContext->getFormRuntime()->getFormDefinition()
                ->setRenderingOption(self::RENDERING_OPTION_ACCOUNT_EXISTS, true);
            return;
        }

        /* Opt in data set  */
        $confirmationRequest = new ConfirmationRequest();
        $confirmationRequest->setEmail($email);

        $payload = $this->prepareData();
        $confirmationRequest->setDecodedValues($payload);

        $confirmationRequest->setPid($this->options['confirmationRequestPid']);
        $confirmationRequest->setLastSent(new \DateTime());

        // Always set an expiry. `expirationDays: 0` used to mean "never expires", which
        // left rows that no cleanup run could reach except through its --days fallback,
        // and links that stayed valid indefinitely. Since ConfirmationRequest::isExpired()
        // is now fail-closed, a row without a date would also count as instantly expired
        // and its link would never work — so 0 falls back to the default instead of
        // producing either surprise.
        $expirationDays = (int)($this->options['expirationDays'] ?? self::DEFAULT_EXPIRATION_DAYS);
        if ($expirationDays <= 0) {
            $expirationDays = self::DEFAULT_EXPIRATION_DAYS;
        }
        $expiresAt = new \DateTime();
        $expiresAt->modify('+' . $expirationDays . ' days');
        $confirmationRequest->setExpiresAt($expiresAt);

        $this->confirmationRequestRepository->add($confirmationRequest);

        $this->eventDispatcher->dispatch(
            new AfterConfirmationRequestCreationEvent($confirmationRequest)
        );

        $this->persistenceManager->persistAll();

        $this->finisherContext->getFormRuntime()->getFormState()->setFormValue('confirmationHash', $confirmationRequest->getConfirmationHash());
    }


    /**
     * Prepare data for saving to database
     *
     * @return mixed
     */
    protected function prepareData()
    {
        $data = [];
        $formRuntime = $this->finisherContext->getFormRuntime();
        $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('FE');

        foreach ($this->getFormValues() as $elementIdentifier => $elementValue) {

            $element = $formRuntime->getFormDefinition()->getElementByIdentifier($elementIdentifier);
            if ($element !== null && ($element->getType() === 'Password' || $element->getType() === 'AdvancedPassword')) {
                $data[$elementIdentifier] = $hashInstance->getHashedPassword($elementValue);
            } else {
                $data[$elementIdentifier] = $elementValue;
            }

        }
        return $data;
    }

    /**
     * Returns the values of the submitted form
     *
     * @return array
     */
    protected function getFormValues(): array
    {
        return $this->finisherContext->getFormValues();
    }


    /**
     * Returns a form element object for a given identifier.
     *
     * @param string $elementIdentifier
     * @return FormElementInterface|null
     */
    protected function getElementByIdentifier(string $elementIdentifier)
    {
        return $this
            ->finisherContext
            ->getFormRuntime()
            ->getFormDefinition()
            ->getElementByIdentifier($elementIdentifier);
    }


    protected function initializeFluidEmail(FormRuntime $formRuntime): FluidEmail
    {
        $templateConfiguration = $GLOBALS['TYPO3_CONF_VARS']['MAIL'];

        if (is_array($this->options['templateRootPaths'] ?? null)) {
            $templateConfiguration['templateRootPaths'] = array_replace_recursive(
                $templateConfiguration['templateRootPaths'],
                $this->options['templateRootPaths']
            );
            ksort($templateConfiguration['templateRootPaths']);
        }

        if (is_array($this->options['partialRootPaths'] ?? null)) {
            $templateConfiguration['partialRootPaths'] = array_replace_recursive(
                $templateConfiguration['partialRootPaths'],
                $this->options['partialRootPaths']
            );
            ksort($templateConfiguration['partialRootPaths']);
        }

        if (is_array($this->options['layoutRootPaths'] ?? null)) {
            $templateConfiguration['layoutRootPaths'] = array_replace_recursive(
                $templateConfiguration['layoutRootPaths'],
                $this->options['layoutRootPaths']
            );
            ksort($templateConfiguration['layoutRootPaths']);
        }

        $fluidEmail = GeneralUtility::makeInstance(
            FluidEmail::class,
            GeneralUtility::makeInstance(TemplatePaths::class, $templateConfiguration)
        );

        if (!isset($this->options['templateName']) || $this->options['templateName'] === '') {
            throw new FinisherException('The option "templateName" must be set to use FluidEmail.', 1599834020);
        }

        // Migrate old template name to default FluidEmail name
        if ($this->options['templateName'] === '{@format}.html') {
            $this->options['templateName'] = 'Default';
        }

        $fluidEmail
            ->setRequest($this->finisherContext->getRequest())
            ->setTemplate($this->options['templateName'])
            ->assignMultiple([
                'finisherVariableProvider' => $this->finisherContext->getFinisherVariableProvider(),
                'form' => $formRuntime,
            ]);

        if (is_array($this->options['variables'] ?? null)) {
            $fluidEmail->assignMultiple($this->options['variables']);
        }

        $fluidEmail
            ->getViewHelperVariableContainer()
            ->addOrUpdate(RenderRenderableViewHelper::class, 'formRuntime', $formRuntime);

        return $fluidEmail;
    }

}

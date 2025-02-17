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
use WapplerSystems\FeRegistration\Event\AfterConfirmationRequestCreationEvent;

class ConfirmationRequestFinisher extends AbstractFinisher
{

    /**
     * @var array
     */
    protected $defaultOptions = [
        'confirmationRequestPid' => null,
    ];


    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository,
                                readonly EventDispatcherInterface      $eventDispatcher)
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

        /* Opt in data set  */
        $confirmationRequest = new ConfirmationRequest();
        $confirmationRequest->setEmail($email);

        $payload = $this->prepareData();
        $confirmationRequest->setDecodedValues($payload);

        $confirmationRequest->setPid($this->options['confirmationRequestPid']);
        $confirmationRequest->setLastSent(new \DateTime());

        $this->confirmationRequestRepository->add($confirmationRequest);

        $this->eventDispatcher->dispatch(
            new AfterConfirmationRequestCreationEvent($confirmationRequest)
        );

        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $persistenceManager->persistAll();

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

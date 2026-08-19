<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Form\Factory;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;

#[Autoconfigure(public: true, shared: false)]
class RegistrationPatchFormFactory extends ArrayFormFactory
{

    protected array $settings;

    protected UriBuilder $uriBuilder;

    protected array $preDefinedValues;

    protected ?ConfirmationRequest $confirmationRequest = null;

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    public function setUriBuilder(UriBuilder $uriBuilder): void
    {
        $this->uriBuilder = $uriBuilder;
    }

    public function setPreDefinedValues(array $preDefinedValues): void
    {
        $this->preDefinedValues = $preDefinedValues;
    }

    public function getPreDefinedValues(): array
    {
        return $this->preDefinedValues;
    }

    public function setConfirmationRequest(?ConfirmationRequest $confirmationRequest): void
    {
        $this->confirmationRequest = $confirmationRequest;
    }

    public function getConfirmationRequest(): ?ConfirmationRequest
    {
        return $this->confirmationRequest;
    }

    public function build(
        array $configuration,
        ?string $prototypeName = null,
        ?ServerRequestInterface $request = null
    ): FormDefinition {

        $allRenderables = $this->getRenderables($configuration['renderables']);

        $newConfiguration = $configuration;
        $newConfiguration['renderingOptions']['hasPasswordField'] = $this->hasPasswordField($allRenderables);

        // The RegistrationController sets renderingOptions.controllerAction
        // to either 'new' (initial submit → send the double-opt-in mail) or
        // 'confirm' (user clicked the DOI link → finalize the registration).
        // We used to detect this implicitly by looking for a string-keyed
        // 'ConfirmationRequest' finisher in the merged config, which broke
        // once the merge in RenderViewHelper started deduplicating by
        // identifier and produced string keys in BOTH passes. Read the
        // explicit signal instead. Fall back to the legacy lookup for any
        // callers that build configurations without setting controllerAction.
        $controllerAction = $configuration['renderingOptions']['controllerAction'] ?? null;
        if ($controllerAction === 'new') {
            $preConfirmation = true;
        } elseif ($controllerAction === 'confirm') {
            $preConfirmation = false;
        } else {
            $preConfirmation = false;
            foreach ($configuration['finishers'] ?? [] as $finisherName => $page) {
                if ($finisherName === 'ConfirmationRequest') {
                    $preConfirmation = true;
                }
            }
        }

        if ($preConfirmation) {
            // remove in pre confirmation-steps all finishers except ConfirmationRequest, ConfirmationEmail, RedirectToUri and special preConfirmation finishers
            foreach ($configuration['finishers'] ?? [] as $finisherName => $finisherConfig) {
                if ($finisherConfig['options']['preConfirmation'] ?? false) {
                    continue;
                }
                if ($finisherName !== 'ConfirmationRequest' && $finisherName !== 'ConfirmationEmail' && $finisherName !== 'RedirectToUri') {
                    unset($newConfiguration['finishers'][$finisherName]);
                }
            }
        } else {

            foreach ($configuration['finishers'] ?? [] as $finisherName => $finisherConfig) {
                if ($finisherConfig['options']['preConfirmation'] ?? false) {
                    unset($newConfiguration['finishers'][$finisherName]);
                }
            }

            // set RestoreFormValues to first position of finishers
            if (array_key_exists('RestoreFormValues', $newConfiguration['finishers'])) {
                $newConfiguration['finishers'] = ['RestoreFormValues' => $newConfiguration['finishers']['RestoreFormValues']] + $newConfiguration['finishers'];
            }
        }

        foreach ($configuration['renderables'] ?? [] as $pageKey => $page) {
            if ($page['type'] === 'EmailConfirmation') {
                if ($preConfirmation) {
                    $newConfiguration['renderables'] = array_slice($newConfiguration['renderables'], 0 , $pageKey);
                } else {
                    $newConfiguration['renderables'] = array_slice($newConfiguration['renderables'], $pageKey + 1);
                }
            }
        }
        if (count($newConfiguration['renderables']) === 0) {
            // add pseudo page
            $newConfiguration['renderables'][] = [
                'type' => 'Page',
                'identifier' => 'pseudoPage',
                'renderables' => [
                    [
                        'type' => 'Text',
                        'identifier' => 'pseudoText',
                        'properties' => [
                            'text' => 'No fields available'
                        ]
                    ]
                ]
            ];
        }

        $this->patchFieldConfigurations($newConfiguration, $request);

        if ($preConfirmation) {
            $allowedParameterizedFields = explode(',',$this->settings['allowedParameterizedFields'] ?? '');

            foreach ($allowedParameterizedFields as $field) {
                if ($field === '') {
                    continue;
                }
                // create hidden field for each parameterized field which is after mail confirmation
                if ($this->isFieldAfterEmailConfirmation($configuration, $field)) {
                    $newConfiguration['renderables'][0]['renderables'][] = [
                        'type' => 'Hidden',
                        'identifier' => $field,
                        'defaultValue' => $request->getQueryParams()[$field] ?? ''
                    ];
                    // TODO: copy validators from original field
                }

            }
            $newConfiguration['renderingOptions']['preConfirmation'] = true;
        } else {
            $newConfiguration['renderingOptions']['afterConfirmation'] = true;
        }

        $formDefinition = parent::build($newConfiguration, $prototypeName, $request);
        if ($this->confirmationRequest !== null) {
            $formDefinition->setRenderingOption('confirmationRequest', $this->confirmationRequest);
        }
        return $formDefinition;
    }


    private function patchFieldConfigurations(array &$fieldConfig, ?ServerRequestInterface $request = null) : void
    {
        if (!isset($fieldConfig['renderables']) || !is_array($fieldConfig['renderables'])) {
            return;
        }
        foreach ($fieldConfig['renderables'] as &$renderable) {

            if ($renderable['identifier'] === $this->settings['identifierFieldName']) {

                $renderable['validators'][] = [
                    'identifier' => 'ConfirmationRequest',
                    'options' => [
                        'pid' => $this->settings['confirmationRequestPid'] ?? '',
                        'uriBuilder' => $this->uriBuilder,
                        'request' => $request
                    ]
                ];
                $renderable['validators'][] = [
                    'identifier' => 'FeUser',
                    'options' => [
                        'pid' => $this->settings['feUserStoragePid'] ?? '',
                    ]
                ];
            }

            // Never seed a password field from the stored payload. The value kept
            // on the ConfirmationRequest is the *hash* produced by
            // ConfirmationRequestFinisher, and assigning it as a defaultValue puts
            // it into the form state, which is serialized into a hidden field and
            // handed to the browser. The state is HMAC-signed but not encrypted, so
            // anyone holding the confirmation link (it travels by email, lands in
            // browser history, proxy logs, …) could read the password hash straight
            // out of the page and attack it offline.
            //
            // Nothing needs it there: CompleteRegistrationFinisher builds the
            // fe_users row from $confirmationRequest->getDecodedValues(), never
            // from the submitted form values, and after confirmation the password
            // field is not even rendered — the factory replaces the page with a
            // pseudo page.
            if (array_key_exists($renderable['identifier'], $this->preDefinedValues)
                && !in_array($renderable['type'] ?? '', ['Password', 'AdvancedPassword'], true)
            ) {
                $renderable['defaultValue'] = $this->preDefinedValues[$renderable['identifier']];
            }

            $this->patchFieldConfigurations($renderable, $request);

        }

    }


    private function isFieldAfterEmailConfirmation(array $configuration, string $fieldName) : bool
    {

        $foundEmailConfirmation = false;
        foreach ($configuration['renderables'] as $pages) {
            if ($foundEmailConfirmation && $this->findRenderable($pages['renderables'], $fieldName) !== null) {
                return true;
            }
            if ($pages['type'] === 'EmailConfirmation') {
                $foundEmailConfirmation = true;
            }
        }
        return false;
    }

    private function hasPasswordField(array $allRenderables) : bool
    {
        foreach ($allRenderables as $renderable) {
            if ($renderable['type'] === 'Password' || $renderable['type'] === 'AdvancedPassword') {
                return true;
            }
        }
        return false;
    }

    private function getRenderables(array $configuration): array
    {
        $renderables = [];
        foreach ($configuration as $elements) {
            $renderables[] = $elements;
            if (isset($elements['renderables'])) {
                $renderables = array_merge($renderables, $this->getRenderables($elements['renderables']));
            }
        }
        return $renderables;
    }

    private function findRenderable(array $configuration, string $identifier): ?array
    {
        foreach ($configuration as $elements) {
            if ($elements['identifier'] === $identifier) {
                return $elements;
            }
            if (isset($elements['renderables'])) {
                $renderable = $this->findRenderable($elements['renderables'], $identifier);
                if ($renderable !== null) {
                    return $renderable;
                }
            }
        }
        return null;
    }

}

<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Form\Factory;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;

#[Autoconfigure(public: true, shared: false)]
class RegistrationPatchFormFactory extends ArrayFormFactory
{

    protected array $settings;

    protected UriBuilder $uriBuilder;

    protected array $preDefinedValues;

    public function __construct(?array $settings = null, ?UriBuilder $uriBuilder = null, ?array $preDefinedValues = null)
    {
        if ($settings !== null) {
            $this->settings = $settings;
        }
        if ($uriBuilder !== null) {
            $this->uriBuilder = $uriBuilder;
        }
        if ($preDefinedValues !== null) {
            $this->preDefinedValues = $preDefinedValues;
        } else {
            $this->preDefinedValues = [];
        }
    }

    public function build(
        array $configuration,
        ?string $prototypeName = null,
        ?ServerRequestInterface $request = null
    ): FormDefinition {

        $allRenderables = $this->getRenderables($configuration['renderables']);

        $newConfiguration = $configuration;
        $newConfiguration['renderingOptions']['hasPasswordField'] = $this->hasPasswordField($allRenderables);

        $preConfirmation = false;
        foreach ($configuration['finishers'] ?? [] as $finisherName => $page) {
            if ($finisherName === 'ConfirmationRequest') {
                $preConfirmation = true;
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

        foreach ($newConfiguration['renderables'] ?? [] as $pageKey => $page) {
            foreach ($page['renderables'] ?? [] as $elementKey => $renderable) {

                /*
                if (($renderable['properties']['mapOnDatabaseColumn'] ?? '') === 'username') {
                    $newConfiguration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][] = [
                        'identifier' => 'ConfirmationRequest',
                        'options' => [
                            'pid' => $this->settings['confirmationRequestPid'] ?? '',
                            'uriBuilder' => $this->uriBuilder,
                            'request' => $request
                        ]
                    ];
                    $newConfiguration['renderables'][$pageKey]['renderables'][$elementKey]['validators'][] = [
                        'identifier' => 'FeUser',
                        'options' => [
                            'pid' => $this->settings['feUserStoragePid'] ?? '',
                        ]
                    ];
                }*/

                // TODO: Enhance this so support deep nested renderables

                if (array_key_exists($renderable['identifier'], $this->preDefinedValues)) {
                    $newConfiguration['renderables'][$pageKey]['renderables'][$elementKey]['defaultValue'] = $this->preDefinedValues[$renderable['identifier']];
                }

            }
        }

        /*
        $newConfiguration['renderables'][0]['renderables'][] = [
            'type' => 'Hidden',
            'identifier' => 'preDefinedValues',
        ];*/

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
        }

        return parent::build($newConfiguration, $prototypeName, $request);
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

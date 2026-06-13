<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Widgets;

use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Settings\SettingDefinition;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetContext;
use TYPO3\CMS\Dashboard\Widgets\WidgetRendererInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetResult;
use WapplerSystems\FeRegistration\Widgets\Provider\FeRegistrationListDataProviderInterface;

/**
 * Generic dashboard widget that lists frontend registration entries.
 *
 * Reused across the two registered widget instances ("latest confirmed"
 * and "pending DOI") — the difference between the two is just the
 * concrete FeRegistrationListDataProviderInterface implementation
 * wired in Services.yaml. The widget itself only knows how to format
 * the rows the provider yields.
 *
 * Settings:
 *   - limit `int` number of rows to show (default 10)
 */
readonly class FeRegistrationListWidget implements WidgetRendererInterface
{
    public function __construct(
        private BackendViewFactory $backendViewFactory,
        private FeRegistrationListDataProviderInterface $dataProvider,
        private WidgetConfigurationInterface $configuration,
        private ?ButtonProviderInterface $buttonProvider = null,
    ) {}

    public function getSettingsDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'limit',
                type: 'int',
                default: 10,
                label: 'LLL:EXT:fe_registration/Resources/Private/Language/locallang.xlf:widgets.registrations.settings.limit',
                description: 'LLL:EXT:fe_registration/Resources/Private/Language/locallang.xlf:widgets.registrations.settings.limit.description',
                options: [
                    'min' => 1,
                ],
            ),
        ];
    }

    public function renderWidget(WidgetContext $context): WidgetResult
    {
        $limit = (int)$context->settings->get('limit');
        $items = $this->dataProvider->getItems($limit);

        $view = $this->backendViewFactory->create(
            $context->request,
            ['typo3/cms-dashboard', 'wapplersystems/fe-registration'],
        );
        $view->assignMultiple([
            'items' => $items,
            'button' => $this->buttonProvider,
            'configuration' => $this->configuration,
            'dateFormat' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] . ' ' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'],
        ]);

        return new WidgetResult(
            content: $view->render('Widget/FeRegistrationListWidget'),
            refreshable: true,
        );
    }
}
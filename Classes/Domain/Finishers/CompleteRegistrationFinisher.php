<?php

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Event\AfterRegistrationCompletionEvent;
use WapplerSystems\FeRegistration\Service\ConfirmationService;
use WapplerSystems\FeRegistration\Service\DatabaseService;

class CompleteRegistrationFinisher extends AbstractFinisher implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array
     */
    protected $defaultOptions = [
        'confirmationRequest' => null,
        'settings' => [],
    ];

    public function __construct(
        readonly ConfirmationService $confirmationService,
        readonly DatabaseService $databaseService,
        readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @see AbstractFinisher::execute()
     */
    protected function executeInternal()
    {
        /** @var ConfirmationRequest|null $confirmationRequest */
        $confirmationRequest = $this->finisherContext->getFormRuntime()->getFormDefinition()->getRenderingOptions()['confirmationRequest'] ?? null;
        if (!$confirmationRequest instanceof ConfirmationRequest) {
            $confirmationRequest = $this->options['confirmationRequest'] ?? null;
        }

        if (!$confirmationRequest instanceof ConfirmationRequest) {
            throw new \RuntimeException('No confirmation request provided to CompleteRegistrationFinisher', 1687334861);
        }

        // Materialize the fe_users row from the form values that
        // ConfirmationRequestFinisher stored on the initial submit. The
        // SaveToDatabaseFinisher path can't help here: post-confirmation the
        // form only carries a pseudo placeholder field, so the registration
        // payload only lives on the ConfirmationRequest record.
        // The registration_request FK lookup keeps this idempotent across
        // accidental re-submission of the DOI form.
        // RegistrationController::confirmAction adds a second
        // CompleteRegistration finisher entry that carries the plugin
        // settings (storage pid, usergroups, identifier field). The yaml may
        // also declare CompleteRegistration without those settings — that
        // entry is intentionally a no-op here so we don't double-insert and
        // don't crash on missing required keys.
        $settings = is_array($this->options['settings'] ?? null) ? $this->options['settings'] : [];
        $hasUsableSettings = !empty($settings['feUserStoragePid']) && !empty($settings['identifierFieldName']);

        // RegistrationController::confirmAction injects a CompleteRegistration
        // finisher entry that carries the plugin settings (storage pid,
        // usergroups, identifier field). The yaml typically declares a second
        // CompleteRegistration entry that lacks those settings; if we ran the
        // full body on both, the no-op instance would call
        // setRegistrationCompleted() first — which clears decodedValues — and
        // the real instance would then have nothing left to insert into
        // fe_users. So the yaml-path instance is a no-op here.
        if (!$hasUsableSettings) {
            return;
        }


        $existing = $this->databaseService->findFeUserByConfirmationRequest($confirmationRequest);
        if ($existing === false) {
            $values = $confirmationRequest->getDecodedValues();
            if (!empty($values)) {
                $values['registration_request'] = $confirmationRequest->getUid();
                // createFeUser() only accepts keys that match a fe_users column or
                // its snake_case form. The form element is called `emailAddress`,
                // which maps to `email_address` — not a column — so the address was
                // dropped and every account created here ended up with an empty
                // email field. The confirmed address on the request is the
                // authoritative one anyway: it is the address the double opt-in was
                // answered from.
                if (($values['email'] ?? '') === '') {
                    $values['email'] = $confirmationRequest->getEmail();
                }
                $values['disable'] = ((int)($settings['feUserMustConfirmed'] ?? 0)) === 1 ? 1 : 0;

                $row = $this->databaseService->createFeUser($values, $settings);
                $this->logger?->info('Registration completed: fe_user created', [
                    'uid' => $row['uid'] ?? null,
                    'email' => $confirmationRequest->getEmail(),
                ]);

                $this->eventDispatcher->dispatch(
                    new AfterRegistrationCompletionEvent($row, $values, $settings)
                );
            }
        }

        $this->confirmationService->setRegistrationCompleted($confirmationRequest);
    }
}

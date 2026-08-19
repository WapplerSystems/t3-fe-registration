<?php
declare(strict_types=1);

namespace WapplerSystems\FeRegistration\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Form\Event\MailBeforeSendingEvent;

#[Autoconfigure(public: true)]
class MailBeforeSendingEventListener
{

    #[AsEventListener('fe-registration/mail-before-sending')]
    public function modifyDataStructureIdentifier(MailBeforeSendingEvent $event): void
    {
        $fluidMail = $event->mail;

        if ($event->finisherContext->getFormRuntime()->getRenderingOptions()['fe-registration'] ?? false) {
            $confirmationRequest = $event->finisherContext->getFormRuntime()->getFormDefinition()->getRenderingOptions()['confirmationRequest'];
            $fluidMail->assign('confirmationRequest', $confirmationRequest);
        }

        if ($event->finisherContext->getFormRuntime()->getRenderingOptions()['user'] ?? false) {
            $user = $event->finisherContext->getFormRuntime()->getFormDefinition()->getRenderingOptions()['user'];
            $fluidMail->assign('user', $user);
        }

    }


}

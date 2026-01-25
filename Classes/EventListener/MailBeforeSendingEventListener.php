<?php
declare(strict_types=1);

namespace WapplerSystems\FeRegistration\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use WapplerSystems\FormExtended\Event\MailBeforeSendingEvent;

#[Autoconfigure(public: true)]
class MailBeforeSendingEventListener
{

    #[AsEventListener('fe-registration/mail-before-sending')]
    public function modifyDataStructureIdentifier(MailBeforeSendingEvent $event): void
    {
        $fluidMail = $event->getMail();

        if ($event->getFinisherContext()->getFormRuntime()->getRenderingOptions()['fe-registration'] ?? false) {
            $confirmationRequest = $event->getFinisherContext()->getFormRuntime()->getFormDefinition()->getRenderingOptions()['confirmationRequest'];
            $fluidMail->assign('confirmationRequest', $confirmationRequest);
        }

        if ($event->getFinisherContext()->getFormRuntime()->getRenderingOptions()['user'] ?? false) {
            $user = $event->getFinisherContext()->getFormRuntime()->getFormDefinition()->getRenderingOptions()['user'];
            $fluidMail->assign('user', $user);
        }

    }


}

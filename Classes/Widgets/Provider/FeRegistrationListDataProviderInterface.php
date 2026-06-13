<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Widgets\Provider;

/**
 * Data provider for the FeRegistrationListWidget. Each provider yields a
 * list of registration entries the widget renders into a generic table.
 *
 * Items must be arrays with at least the keys:
 *   - uid          int       fe_users.uid or confirmationrequest.uid
 *   - editTable    string    "fe_users" or "tx_feregistration_domain_model_confirmationrequest"
 *                             (used by the widget's edit-link)
 *   - email        string    user's email address
 *   - name         string    "<first> <last>" (may be empty)
 *   - company      string    company name (may be empty)
 *   - timestamp    int       UNIX timestamp the row should sort/display by
 */
interface FeRegistrationListDataProviderInterface
{
    /**
     * @return list<array{uid:int,editTable:string,email:string,name:string,company:string,timestamp:int}>
     */
    public function getItems(int $limit = 10): array;
}
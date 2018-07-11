<?php

namespace Network\Model\Table;

use App\Model\Table\AppTable;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * SyncDomain
 *
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop Network Plugin 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, http://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */
class SyncDomainsTable extends AppTable
{

    public function validationDefault(Validator $validator)
    {
        $validator->add('domain', 'hostname', [
            'rule' => array('custom', HOSTNAME_REGEX),
            'message' => 'Die Domain darf nur aus https:// und dem Hostnamen bestehen (ohne / am Ende).'
        ]);
        $validator->notEmpty('domain', 'Bitte gib eine Domain ein, sie muss mit https:// beginnen.');
        $validator->add('domain', 'https', [
            'rule' => array('custom', HTTPS_REGEX),
            'message' => 'Die Domain muss mit https:// beginnen.'
        ]);
        $validator->add('domain', 'unique', [
            'rule' => 'validateUnique',
            'provider' => 'table',
            'message' => 'Die Domain ist bereits vorhanden.'
        ]);
        return $validator;
    }

    public function getSyncDomains($minStatus = APP_OFF)
    {
        $syncDomains = $this->find('all', [
            'conditions' => [
                'SyncDomains.active >= ' . $minStatus
            ]
        ]);
        return $syncDomains;
    }

    /**
     * @return array
     */
    public function getActiveSyncDomains()
    {
        return $this->getSyncDomains(APP_ON);
    }

    /**
     * @param array $appAuth
     * @return boolean success
     */
    public function isAllowedEditManufacturerOptionsDropdown($appAuth)
    {

        $isAllowed = false;
        if ($appAuth->isSuperadmin()) {
            $isAllowed = true;
        }

        if ($appAuth->isManufacturer()) {
            $manufacturer = TableRegistry::getTableLocator()->get('Manufacturers');
            $isAllowed = $manufacturer->getOptionVariableMemberFee(
                $appAuth->manufacturer->variable_member_fee
            ) == 0;
        }

        if ($isAllowed) {
            $syncDomains = $this->getActiveSyncDomains();
            $isAllowed &= $syncDomains->count() > 0;
        }

        return $isAllowed;
    }

    /**
     * @return array
     */
    public function getActiveManufacturerSyncDomains($enabledSyncDomains)
    {
        $activeSyncDomains = $this->getActiveSyncDomains();
        $preparedDomains = [];
        $enabledSyncDomainsAsArray = explode(',', $enabledSyncDomains);
        foreach ($activeSyncDomains as $activeSyncDomain) {
            if (in_array($activeSyncDomain->id, $enabledSyncDomainsAsArray)) {
                $preparedDomains[] = $activeSyncDomain;
            }
        }
        return $preparedDomains;
    }

    public function getForDropdown()
    {
        $syncDomains = $this->find('all', [
            'conditions' => [
                'SyncDomains.active' => APP_ON
            ],
            'fields' => ['SyncDomains.id', 'SyncDomains.domain']
        ]);
        $result = [];
        foreach ($syncDomains as $syncDomain) {
            $result[$syncDomain->id] = $syncDomain->domain;
        }
        return $result;
    }
}

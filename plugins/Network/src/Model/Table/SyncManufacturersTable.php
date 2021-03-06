<?php
/**
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

namespace Network\Model\Table;

use App\Model\Table\ManufacturersTable;

class SyncManufacturersTable extends ManufacturersTable
{

    public function isAllowedToUseAsMasterFoodcoop($appAuth)
    {
        $isAllowed =
            $appAuth->isManufacturer() &&
            $this->getOptionVariableMemberFee(
                $appAuth->manufacturer->variable_member_fee
            ) == 0;
        return $isAllowed;
    }
}

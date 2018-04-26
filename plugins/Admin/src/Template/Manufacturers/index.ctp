<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, http://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */
use Cake\Core\Configure;

?>
<div id="manufacturers-list">
    <?php
    $this->element('addScript', [
        'script' => Configure::read('app.jsNamespace') . ".Helper.initDatepicker();
            $('input.datepicker').datepicker();".
            Configure::read('app.jsNamespace') . ".Admin.init();" . Configure::read('app.jsNamespace') . ".Admin.initEmailToAllButton();" . Configure::read('app.jsNamespace') . ".AppFeatherlight.initLightboxForImages('a.lightbox');" . Configure::read('app.jsNamespace') . ".Helper.setCakeServerName('" . Configure::read('app.cakeServerName') . "');".Configure::read('app.jsNamespace') . ".Helper.initTooltip('.manufacturer-details-read-button');"
    ]);
    if (Configure::read('app.allowManualOrderListSending')) {
        $this->element('addScript', [
            'script' => Configure::read('app.jsNamespace') . ".Admin.setWeekdaysBetweenOrderSendAndDelivery('" . json_encode($this->MyTime->getWeekdaysBetweenOrderSendAndDelivery()) . "');" . Configure::read('app.jsNamespace') . ".Admin.initManualOrderListSend('#manufacturers-list .manual-order-list-send-link', " . date('N', time()) . ");"
        ]);
    }
    ?>

    <div class="filter-container">
        <?php echo $this->Form->create(null, ['type' => 'get']); ?>
            <?php echo $this->element('dateFields', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo, 'nameTo' => 'dateTo', 'nameFrom' => 'dateFrom']); ?>
            <?php echo $this->Form->control('active', ['type' => 'select', 'label' => '', 'options' => $this->MyHtml->getActiveStates(), 'default' => isset($active) ? $active : '']); ?>
            <div class="right">
                <?php
                echo '<div id="add-manufacturer-button-wrapper" class="add-button-wrapper">';
                echo $this->Html->link('<i class="fa fa-plus-circle"></i> Neuen Hersteller erstellen', $this->Slug->getManufacturerAdd(), [
                    'class' => 'btn btn-default',
                    'escape' => false
                ]);
                echo '</div>';
                ?>
            </div>
        <?php echo $this->Form->end(); ?>
    </div>

    <div id="help-container">
        <ul>
            <li>Auf dieser Seite werden die <b>Hersteller</b> verwaltet.</li>
            <?php echo $this->element('docs/hersteller'); ?>
        </ul>
    </div>    
    
<?php

echo '<table class="list">';
echo '<tr class="sort">';
    echo '<th class="hide">' . $this->Paginator->sort('Manufacturers.id_manufacturer', 'ID') . '</th>';
    echo '<th>Logo</th>';
    echo '<th></th>';
    echo '<th>' . $this->Paginator->sort('Manufacturers.name', 'Name') . '</th>';
    echo '<th style="width:83px;">Produkte</th>';
    echo '<th>Pfand</th>';
    if (Configure::read('appDb.FCS_TIMEBASED_CURRENCY_ENABLED')) {
        echo '<th>' . $this->Paginator->sort('Manufacturers.timebased_currency_enabled', Configure::read('appDb.FCS_TIMEBASED_CURRENCY_NAME')) . '</th>';
    }
    echo '<th>' . $this->Paginator->sort('Manufacturers.iban', 'IBAN') . '</th>';
    echo '<th>' . $this->Paginator->sort('Manufacturers.active', 'Aktiv') . '</th>';
    echo '<th>' . $this->Paginator->sort('Manufacturers.holiday_from', 'Lieferpause') . '</th>';
    echo '<th>' . $this->Paginator->sort('Manufacturers.is_private', 'Nur für Mitglieder') . '</th>';
    echo '<th title="Summe offener Bestellungen im oben angegebenen Zeitraum">O.B</th>';
    echo '<th>Opt.</th>';
    if (Configure::read('appDb.FCS_USE_VARIABLE_MEMBER_FEE')) {
        echo '<th>%</th>';
    }
    echo '<th></th>';
    if (Configure::read('app.allowManualOrderListSending')) {
        echo '<th></th>';
    }
    echo '<th></th>';
    echo '<th></th>';
echo '</tr>';
$i = 0;
$sumProductCount = 0;
$sumDeposit = 0;
$sumTimebasedCurrency = null;
foreach ($manufacturers as $manufacturer) {
    $i ++;
    echo '<tr id="manufacturer-' . $manufacturer->id_manufacturer . '" class="data">';
    echo '<td class="hide">';
        echo $manufacturer->id_manufacturer;
    echo '</td>';
    echo '<td align="center" style="background-color: #fff;">';
        $srcLargeImage = $this->Html->getManufacturerImageSrc($manufacturer->id_manufacturer, 'large');
        $largeImageExists = preg_match('/de-default-large_default/', $srcLargeImage);
        if (! $largeImageExists) {
            echo '<a class="lightbox" href="' . $srcLargeImage . '">';
        }
        echo '<img width="50" src="' . $this->Html->getManufacturerImageSrc($manufacturer->id_manufacturer, 'medium') . '" />';
        if (! $largeImageExists) {
            echo '</a>';
        }
    echo '</td>';
    echo '<td>';
        echo $this->Html->getJqueryUiIcon($this->Html->image($this->Html->getFamFamFamPath('page_edit.png')), [
            'title' => 'Bearbeiten'
        ], $this->Slug->getManufacturerEdit($manufacturer->id_manufacturer));
    echo '</td>';

    echo '<td>';

    $details = $manufacturer->address_manufacturer->firstname . ' ' . $manufacturer->address_manufacturer->lastname;
    if ($manufacturer->address_manufacturer->phone_mobile != '') {
        $details .= '<br />'.$manufacturer->address_manufacturer->phone_mobile;
    }
    if ($manufacturer->address_manufacturer->phone != '') {
        $details .= '<br />' . $manufacturer->address_manufacturer->phone;
    }
        echo '<div class="manufacturer-details-wrapper">';
            echo $this->Html->getJqueryUiIcon($this->Html->image($this->Html->getFamFamFamPath('telephone.png')), [
                'class' => 'manufacturer-details-read-button',
                'title' => $details
            ], 'javascript:void(0);');
        echo '</div>';

        echo '<b>' . $manufacturer->name . '</b><br />';
        echo $manufacturer->address_manufacturer->city;
        echo '<br /><span class="email">' . $manufacturer->address_manufacturer->email . '</span>';
        
        if (!empty($manufacturer->customer)) {
            echo '<br /><i class="fa fa-fw fa-male" title="Ansprechperson"></i>' . $manufacturer->customer->firstname . ' ' . $manufacturer->customer->lastname;
        }
        
    echo '</td>';

    echo '<td style="width:140px;">';
    $sumProductCount += $manufacturer->product_count;
    $productString = $manufacturer->product_count == 1 ? 'Produkt' : 'Produkte';
    echo $this->Html->getJqueryUiIcon(
        $this->Html->image($this->Html->getFamFamFamPath('tag_green.png')) . $manufacturer->product_count . '&nbsp;' . $productString,
        [
        'title' => 'Alle Produkte von ' . $manufacturer->name . ' anzeigen',
        'class' => 'icon-with-text'
        ],
        $this->Slug->getProductAdmin($manufacturer->id_manufacturer)
    );
    echo '</td>';

    echo '<td>';
    if ($manufacturer->sum_deposit_delivered > 0) {
        $sumDeposit += $manufacturer->deposit_credit_balance;
        $depositCreditBalanceClasses = [];
        if ($manufacturer->deposit_credit_balance < 0) {
            $depositCreditBalanceClasses[] = 'negative';
        }
        $depositCreditBalanceHtml = '<span class="'.implode(' ', $depositCreditBalanceClasses).'">' . $this->Html->formatAsEuro($manufacturer->deposit_credit_balance);

        echo $this->Html->getJqueryUiIcon(
            'Pfand:&nbsp;' . $depositCreditBalanceHtml,
            [
            'class' => 'icon-with-text',
            'title' => 'Pfandkonto anzeigen'
            ],
            $this->Slug->getDepositList($manufacturer->id_manufacturer)
        );
    }
    echo '</td>';
    
    if (Configure::read('appDb.FCS_TIMEBASED_CURRENCY_ENABLED')) {
        echo '<td>';
            if ($manufacturer->timebased_currency_enabled) {
                $sumTimebasedCurrency += $manufacturer->timebased_currency_credit_balance;
                
                $timebasedCurrencyCreditBalanceClasses = [];
                if ($manufacturer->timebased_currency_credit_balance < 0) {
                    $timebasedCurrencyCreditBalanceClasses[] = 'negative';
                }
                $timebasedCurrencyCreditBalanceHtml = '<span class="'.implode(' ', $timebasedCurrencyCreditBalanceClasses).'">' . $this->TimebasedCurrency->formatSecondsToTimebasedCurrency($manufacturer->timebased_currency_credit_balance);
                
                if ($appAuth->isSuperadmin()) {
                    echo $this->Html->getJqueryUiIcon(
                        $timebasedCurrencyCreditBalanceHtml,
                        [
                            'class' => 'icon-with-text',
                            'title' => $this->TimebasedCurrency->getName() . ' anzeigen'
                        ],
                        $this->Slug->getTimebasedCurrencyBalanceForManufacturers($manufacturer->id_manufacturer)
                    );
                } else {
                    echo $timebasedCurrencyCreditBalanceHtml;
                }
            }
        echo '</td>';
    }
    
    echo '<td style="text-align:center;width:42px;">';
    if ($manufacturer->iban != '') {
        echo $this->Html->image($this->Html->getFamFamFamPath('accept.png'));
    }
    echo '</td>';
    echo '<td style="text-align:center;padding-left:5px;width:42px;">';
    if ($manufacturer->active == 1) {
        echo $this->Html->image($this->Html->getFamFamFamPath('accept.png'));
    }
    if ($manufacturer->active == '') {
        echo $this->Html->image($this->Html->getFamFamFamPath('delete.png'));
    }
    echo '</td>';

    echo '<td>';
        echo $this->Html->getManufacturerHolidayString($manufacturer->holiday_from, $manufacturer->holiday_to, $manufacturer->is_holiday_active);
    echo '</td>';

    echo '<td align="center">';
    if ($manufacturer->is_private == 1) {
        echo $this->Html->image($this->Html->getFamFamFamPath('accept.png'));
    }
    echo '</td>';

    echo '<td class="right">';
    if ($manufacturer->sum_open_order_detail > 0) {
        echo $this->Html->formatAsEuro($manufacturer->sum_open_order_detail);
    }
    echo '</td>';

    echo '<td>';
    echo $this->Html->getJqueryUiIcon(
        $this->Html->image($this->Html->getFamFamFamPath('page_white_gear.png')),
        [
            'title' => 'Hersteller-Einstellungen bearbeiten'
        ],
        $this->Slug->getManufacturerEditOptions($manufacturer->id_manufacturer)
    );
    echo '</td>';

    if (Configure::read('appDb.FCS_USE_VARIABLE_MEMBER_FEE')) {
        echo '<td>';
            echo $manufacturer->variable_member_fee.'%';
        echo '</td>';
    }

    echo '<td style="width:140px;">';
    echo 'Bestellliste prüfen<br />';
    echo $this->Html->link('Produkt', '/admin/manufacturers/getOrderListByProduct/' . $manufacturer->id_manufacturer . '/' . $dateFrom . '/' . $dateTo . '.pdf', [
            'target' => '_blank'
        ]);
    echo ' / ';
    echo $this->Html->link('Mitglied', '/admin/manufacturers/getOrderListByCustomer/' . $manufacturer->id_manufacturer . '/' . $dateFrom . '/' . $dateTo . '.pdf', [
        'target' => '_blank'
    ]);
    echo '</td>';
    if (Configure::read('app.allowManualOrderListSending')) {
        echo '<td>';
        echo $this->Html->getJqueryUiIcon($this->Html->image($this->Html->getFamFamFamPath('email.png')), [
            'title' => 'Bestellliste manuell versenden',
            'class' => 'manual-order-list-send-link'
        ], 'javascript:void(0);');
        echo '</td>';
    }

    echo '<td>';
    echo $this->Html->link('Rechnung prüfen', '/admin/manufacturers/getInvoice/' . $manufacturer->id_manufacturer . '/' . $dateFrom . '/' . $dateTo . '.pdf', [
        'target' => '_blank'
    ]);
    echo '</td>';
    echo '<td style="width: 29px;">';
    if ($manufacturer->active) {
        $manufacturerLink = $this->Slug->getManufacturerDetail($manufacturer->id_manufacturer, $manufacturer->name);
        echo $this->Html->getJqueryUiIcon($this->Html->image($this->Html->getFamFamFamPath('arrow_right.png')), [
            'title' => 'Hersteller-Seite',
            'target' => '_blank'
        ], $manufacturerLink);
    }
    echo '</td>';
    echo '</tr>';
}

echo '<tr>';
echo '<td colspan="3"><b>' . $i . '</b> Datensätze</td>';
echo '<td><b>' . $sumProductCount . '</b></td>';
$colspan = 10;
echo '<td>';
    if ($sumDeposit > 0) {
        echo '<b class="' . ($sumDeposit < 0 ? 'negative' : '') . '">'.$this->Html->formatAsEuro($sumDeposit) . '</b>';
    }
echo '</td>';

if (Configure::read('appDb.FCS_USE_VARIABLE_MEMBER_FEE')) {
    $colspan ++;
}
if (Configure::read('app.allowManualOrderListSending')) {
    $colspan ++;
}
if (Configure::read('appDb.FCS_TIMEBASED_CURRENCY_ENABLED')) {
    echo '<td><b class="' . ($sumTimebasedCurrency < 0 ? 'negative' : '') . '">'.$this->TimebasedCurrency->formatSecondsToTimebasedCurrency($sumTimebasedCurrency) . '</b></td>';
}
echo '<td colspan="' . $colspan . '"></td>';
echo '</tr>';
echo '</table>';
echo '<div class="sc"></div>';
echo '<div class="bottom-button-container">';
echo '<button class="email-to-all btn btn-default" data-column="4"><i class="far fa-envelope"></i> Alle E-Mail-Adressen kopieren</button>';
echo '</div>';
echo '<div class="sc"></div>';

?>
</div>

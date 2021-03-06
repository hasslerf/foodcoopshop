<?php

namespace Admin\Controller;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

/**
 * OrdersController
 *
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
class OrdersController extends AdminAppController
{

    public function isAuthorized($user)
    {
        return ! $this->AppAuth->isManufacturer();
    }

    public function editComment()
    {
        $this->RequestHandler->renderAs($this, 'ajax');

        $orderId = $this->getRequest()->getData('orderId');
        $orderComment = htmlspecialchars_decode(strip_tags(trim($this->getRequest()->getData('orderComment')), '<strong><b>'));

        $this->Order = TableRegistry::getTableLocator()->get('Orders');
        $oldOrder = $this->Order->find('all', [
            'conditions' => [
                'Orders.id_order' => $orderId
            ],
            'contain' => [
                'Customers'
            ]
        ])->first();

        $this->Order->save(
            $this->Order->patchEntity(
                $oldOrder,
                [
                    'comment' => $orderComment
                ]
            )
        );

        $this->Flash->success(__d('admin', 'The_comment_was_changed_successfully.'));

        $this->ActionLog = TableRegistry::getTableLocator()->get('ActionLogs');
        $this->ActionLog->customSave('order_comment_changed', $this->AppAuth->getUserId(), $orderId, 'orders', __d('admin', 'The_comment_of_the_order_number_{0}_by_{1}_was_changed:', [$oldOrder->id_order, $oldOrder->customer->name]) . ' <div class="changed">' . $orderComment . ' </div>');

        die(json_encode([
            'status' => 1,
            'msg' => 'ok'
        ]));
    }

    public function ordersAsPdf()
    {
        if (empty($this->getRequest()->getQuery('orderIds'))) {
            throw new RecordNotFoundException('wrong orderIds');
        }

        $orderIds = explode(',', $this->getRequest()->getQuery('orderIds'));
        if (empty($orderIds)) {
            throw new RecordNotFoundException('wrong orderIds');
        }
        $this->Order = TableRegistry::getTableLocator()->get('Orders');
        $orders = $this->Order->find('all', [
            'conditions' => [
                'Orders.id_order IN' => $orderIds
            ],
            'contain' => [
                'Customers',
                'OrderDetails' => [
                    'sort' => ['OrderDetails.product_name' => 'ASC']
                ],
                'OrderDetails.Products.Manufacturers',
                'OrderDetails.OrderDetailUnits'
            ],
            'order' => Configure::read('app.htmlHelper')->getCustomerOrderBy()
        ]);

        if (empty($orders)) {
            throw new RecordNotFoundException('no orders found');
        }

        $this->set('orders', $orders);
    }

    public function correctInstantOrder()
    {
        $orderId = Configure::read('app.htmlHelper')->getOrderIdFromCartFinishedUrl($this->getRequest()->getQuery('url'));

        $this->Order = TableRegistry::getTableLocator()->get('Orders');

        if ($orderId > 0) {
            $order = $this->Order->find('all', [
                'conditions' => [
                    'Orders.id_order' => $orderId
                ],
                'order' => [
                    'Orders.date_add' => 'DESC'
                ],
                'contain' => [
                    'Customers'
                ]
            ])->first();

            $newDate = Configure::read('app.timeHelper')->getDateForInstantOrder(Configure::read('app.timeHelper')->getCurrentDay());

            $this->Order->save(
                $this->Order->patchEntity(
                    $order,
                    [
                        'date_add' => $newDate,
                        'current_state' => Configure::read('appDb.FCS_INSTANT_ORDER_DEFAULT_STATE')
                    ]
                )
            );

            $message = __d('admin', 'Instant_order_nr_{0}_successfully_placed_for_{1}_and_the_date_was_changed_to_{2}._The_manufacturer_was_notified_unless_the_notification_was_deactivated.', [
                $order->id_order,
                '<b>' . $order->customer->name . '</b>',
                Configure::read('app.timeHelper')->formatToDateShort($newDate)
            ]);

            $this->ActionLog = TableRegistry::getTableLocator()->get('ActionLogs');
            $this->ActionLog->customSave('orders_shop_added', $this->AppAuth->getUserId(), $orderId, 'orders', $message);
            $this->Flash->success($message);

            $this->getRequest()->getSession()->write('highlightedRowId', $orderId);
            $this->redirect($this->referer());
        } else {
            die('order id not correct: ' + $orderId);
        }
    }

    public function changeOrderStateToClosed()
    {
        $this->RequestHandler->renderAs($this, 'ajax');

        $orderIds = $this->getRequest()->getData('orderIds');
        $orderIds = array_unique($orderIds);
        $orderState = $this->getRequest()->getData('orderState');

        $this->Order = TableRegistry::getTableLocator()->get('Orders');
        foreach ($orderIds as $orderId) {
            $this->Order->save(
                $this->Order->patchEntity(
                    $this->Order->get($orderId),
                    [
                        'current_state' => $orderState
                    ]
                )
            );
        }

        $message = count($orderIds) . ' Bestellungen wurden erfolgreich abgeschlossen';
        $this->ActionLog = TableRegistry::getTableLocator()->get('ActionLogs');
        $this->ActionLog->customSave('orders_closed', $this->AppAuth->getUserId(), 0, 'orders', $message . ': ' . join(', ', $orderIds));

        $this->Flash->success($message . '.');

        die(json_encode([
            'status' => 1,
            'msg' => 'ok'
        ]));
    }

    public function changeOrderState()
    {
        $this->RequestHandler->renderAs($this, 'ajax');

        $orderIds = $this->getRequest()->getData('orderIds');
        $orderState = $this->getRequest()->getData('orderState');

        $this->Order = TableRegistry::getTableLocator()->get('Orders');
        if (empty($orderIds) || empty($orderState)) {
            die(json_encode([
                'status' => 0,
                'msg' => 'error'
            ]));
        }

        foreach ($orderIds as $orderId) {
            $oldOrder = $this->Order->find('all', [
                'conditions' => [
                    'Orders.id_order' => $orderId
                ],
                'contain' => [
                    'Customers'
                ]
            ])->first();

            $this->Order->save(
                $this->Order->patchEntity(
                    $oldOrder,
                    [
                        'current_state' => $orderState
                    ]
                )
            );
        }

        $this->ActionLog = TableRegistry::getTableLocator()->get('ActionLogs');

        $orderStateAsText = '<b>' . Configure::read('app.htmlHelper')->getOrderStates()[$orderState] . '</b>';
        if (count($orderIds) == 1) {
            $message = __d('admin', 'The_order_status_of_the_order_of_{0}_was_successfully_changed_to_{1}.', [
                $oldOrder->customer->name,
                $orderStateAsText
            ]);
        } else {
            $message = __d('admin', 'The_order_status_of_the_orders_{0}_was_successfully_changed_to_{1}.', [
                join(', ', array_reverse($orderIds)),
                $orderStateAsText
            ]);
        }
        $this->ActionLog->customSave('orders_state_changed', $this->AppAuth->getUserId(), $orderId, 'orders', $message);

        $this->Flash->success($message);

        // always redirect to orders (and keep some filters)
        $redirectUrlParams = [];
        $parsedReferer = parse_url($this->referer());

        $refererQueryParams = [];
        if (isset($parsedReferer['query'])) {
            parse_str($parsedReferer['query'], $refererQueryParams);
        }

        foreach ($refererQueryParams as $param => $value) {
            if (in_array($param, [
                'dateFrom',
                'dateTo',
                'orderStates'
            ])) {
                $redirectUrlParams[$param] = $value;
            }
        }
        $queryString = '';
        if (!empty($redirectUrlParams)) {
            $queryString = '?' . http_build_query($redirectUrlParams);
        }
        $redirectUrl = Configure::read('app.slugHelper')->getOrdersList() . $queryString;

        die(json_encode([
            'status' => 1,
            'msg' => 'ok',
            'redirectUrl' => $redirectUrl
        ]));
    }

    public function index()
    {

        // for filter from action logs page
        $orderId = '';
        if (! empty($this->getRequest()->getQuery('orderId'))) {
            $orderId = $this->getRequest()->getQuery('orderId');
        }

        $dateFrom = '';
        if ($orderId == '') {
            $dateFrom = Configure::read('app.timeHelper')->getOrderPeriodFirstDay(Configure::read('app.timeHelper')->getCurrentDay());
        }
        if (! empty($this->getRequest()->getQuery('dateFrom'))) {
            $dateFrom = $this->getRequest()->getQuery('dateFrom');
        }
        $this->set('dateFrom', $dateFrom);

        $dateTo = '';
        if ($orderId == '') {
            $dateTo = Configure::read('app.timeHelper')->getOrderPeriodLastDay(Configure::read('app.timeHelper')->getCurrentDay());
        }
        if (! empty($this->getRequest()->getQuery('dateTo'))) {
            $dateTo = $this->getRequest()->getQuery('dateTo');
        }
        $this->set('dateTo', $dateTo);

        $orderStates = Configure::read('app.htmlHelper')->getOrderStateIds();
        if (in_array('orderStates', array_keys($this->getRequest()->getQueryParams()))) {
            $orderStates = $this->getRequest()->getQuery('orderStates');
            if ($orderStates == '') {
                $orderStates = [];
            }
        }
        // legacy cakephp2: param was called "orderState" and contained csv data
        if (! empty($this->getRequest()->getQuery('orderState'))) {
            $orderStates = explode(', ', $this->getRequest()->getQuery('orderState'));
        }
        $this->set('orderStates', $orderStates);

        $customerId = '';
        if (! empty($this->getRequest()->getQuery('customerId'))) {
            $customerId = $this->getRequest()->getQuery('customerId');
        }
        $this->set('customerId', $customerId);

        $groupByCustomer = 0;
        if (! empty($this->getRequest()->getQuery('groupByCustomer'))) {
            $groupByCustomer = $this->getRequest()->getQuery('groupByCustomer');
        }
        $this->set('groupByCustomer', $groupByCustomer);

        $this->Order = TableRegistry::getTableLocator()->get('Orders');
        $orderParams = $this->Order->getOrderParams($customerId, $orderStates, $dateFrom, $dateTo, $orderId, $this->AppAuth);

        $query = $this->Order->find('all', [
            'conditions' => $orderParams['conditions'],
            'contain' => $orderParams['contain']
        ])
        ->select($this->Order->Customers);

        $query->select($this->Order->TimebasedCurrencyOrders);

        if ($groupByCustomer) {
            $query->select(['orders_total_paid' => $query->func()->sum('Orders.total_paid')]);
            $query->select(['orders_count' => $query->func()->count('Orders.total_paid')]);
            $query->select('Orders.id_customer');
            $query->group(['Orders.id_customer']);
        } else {
            $query->select($this->Order);
        }

        $orders = $this->paginate($query, [
            'sortWhitelist' => [
                'Orders.total_paid', 'Orders.date_add', 'Orders.current_state', 'Customers.' . Configure::read('app.customerMainNamePart')
            ],
            'order' => $orderParams['order']
        ])->toArray();
        foreach ($orders as $order) {
            if (!empty($order->customer)) {
                $order->customer->order_count = $this->Order->getCountByCustomerId($order->customer->id_customer);
            }
        }
        $this->set('orders', $orders);

        $timebasedCurrencyOrderInList = false;
        foreach($orders as $order) {
            if (!empty($order->timebased_currency_order)) {
                $timebasedCurrencyOrderInList = true;
                break;
            }
        }
        $this->set('timebasedCurrencyOrderInList', $timebasedCurrencyOrderInList);

        $this->set('customersForDropdown', $this->Order->Customers->getForDropdown(false, 'id_customer', $this->AppAuth->isSuperadmin()));

        $this->set('title_for_layout', __d('admin', 'Orders'));
    }

    public function iframeStartPage()
    {
        $this->set('title_for_layout', __d('admin', 'Instant_order'));
    }

    /**
     * this url is called if shop order (sofortbestellung) is initialized
     * saves the desired user in session
     */
    public function initInstantOrder($customerId)
    {
        if (! $customerId) {
            throw new RecordNotFoundException('customerId not passed');
        }

        $this->Customer = TableRegistry::getTableLocator()->get('Customers');
        $instantOrderCustomer = $this->Customer->find('all', [
            'conditions' => [
                'Customers.id_customer' => $customerId
            ],
            'contain' => [
                'AddressCustomers'
            ]
        ])->first();
        if (! empty($instantOrderCustomer)) {
            $this->getRequest()->getSession()->write('Auth.instantOrderCustomer', $instantOrderCustomer);
        } else {
            $this->Flash->error(__d('admin', 'No_member_found_with_id_{0}.', [$customerId]));
        }
        $this->redirect('/');
    }

    public function editDate()
    {
        $this->RequestHandler->renderAs($this, 'ajax');

        $orderId = $this->getRequest()->getData('orderId');
        $date = $this->getRequest()->getData('date');

        $this->Order = TableRegistry::getTableLocator()->get('Orders');
        $oldOrder = $this->Order->find('all', [
            'conditions' => [
                'Orders.id_order' => $orderId
            ],
            'contain' => [
                'Customers'
            ]
        ])->first();

        $oldDate = $oldOrder->date_add;

        $this->Order->save(
            $this->Order->patchEntity(
                $oldOrder,
                [
                    'date_add' => $date
                ]
            )
        );

        $message = __d('admin', 'The_date_of_the_order_{0}_of_{1}_was_changed_to_{2}.', [
            $orderId,
            $oldOrder->customer->name,
            Configure::read('app.timeHelper')->formatToDateShort($date)
        ]);
        $this->ActionLog = TableRegistry::getTableLocator()->get('ActionLogs');
        $this->ActionLog->customSave('orders_date_changed', $this->AppAuth->getUserId(), $orderId, 'orders', $message);

        $this->Flash->success($message);

        die(json_encode([
            'status' => 1,
            'msg' => 'ok'
        ]));
    }
}

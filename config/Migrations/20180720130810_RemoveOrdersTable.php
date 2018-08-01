<?php

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

use Migrations\AbstractMigration;

class RemoveOrdersTable extends AbstractMigration
{
    public function change()
    {
        
        $this->execute("
            
            CREATE TABLE `fcs_pickup_days` (
              `id` int(10) UNSIGNED NOT NULL,
              `customer_id` int(10) UNSIGNED NOT NULL,
              `pickup_day` date NOT NULL,
              `comment` text DEFAULT NULL,
              `products_picked_up` tinyint(4) UNSIGNED NOT NULL DEFAULT '0'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            
            ALTER TABLE `fcs_pickup_days`
              ADD PRIMARY KEY (`id`),
              ADD KEY `customer_id` (`customer_id`),
              ADD KEY `pickup_day` (`pickup_day`);

            ALTER TABLE `fcs_pickup_days`
              MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
            COMMIT;

            ALTER TABLE `fcs_order_detail` 
                ADD `id_customer` INT(10) UNSIGNED NOT NULL AFTER `deposit`, 
                ADD `id_cart_product` INT(10) UNSIGNED NOT NULL AFTER `id_customer`, 
                ADD `order_state` TINYINT(4) UNSIGNED NOT NULL AFTER `id_cart_product`, 
                ADD `pickup_day` DATE NOT NULL AFTER `order_state`, 
                ADD `created` DATETIME NOT NULL AFTER `pickup_day`, 
                ADD `modified` DATETIME NOT NULL AFTER `created`;
            
            UPDATE fcs_order_detail od 
                JOIN fcs_orders o ON od.id_order = o.id_order
                SET 
                od.id_customer = o.id_customer,
                od.order_state = o.current_state,
                od.created = o.date_add,
                od.modified = o.date_upd;

            UPDATE fcs_order_detail od 
                JOIN fcs_orders o ON od.id_order = o.id_order
                JOIN fcs_carts c ON c.id_cart = o.id_cart
                JOIN fcs_cart_products cp ON cp.id_cart = c.id_cart 
                    AND cp.id_product = od.product_id AND
                    cp.id_product_attribute = od.product_attribute_id
                SET 
                od.id_cart_product = cp.id_cart_product;

            ALTER TABLE `fcs_order_detail` DROP `id_order`;

            ALTER TABLE `fcs_order_detail` DROP INDEX `id_order_id_order_detail`;
            ALTER TABLE `fcs_order_detail` ADD INDEX(`id_customer`);
            ALTER TABLE `fcs_order_detail` ADD INDEX(`pickup_day`);
            ALTER TABLE `fcs_order_detail` ADD INDEX(`created`);
            ALTER TABLE `fcs_order_detail` ADD INDEX(`order_state`);
            ALTER TABLE `fcs_order_detail` ADD INDEX(`product_name`);
            
            ALTER TABLE `fcs_product` ADD INDEX(`id_manufacturer`);

        ");
        
        $this->migrateDataToOrderDetails();
        
        $this->migrateDataToPickupDays();
        
    }
    
    private function migrateDataToPickupDays()
    {
        $this->Order = TableRegistry::getTableLocator()->get('Orders');
        $this->PickupDay = TableRegistry::getTableLocator()->get('PickupDays');
        $this->PickupDay->setPrimaryKey(['customer_id', 'pickup_day']);
        
        $orders = $this->Order->find('all');
        $i = 0;
        foreach($orders as $order) {
            
            $productsPickedUp = 0;
            if (in_array($order->current_state, [
                ORDER_STATE_CASH,
                ORDER_STATE_CASH_FREE
            ])) {
                $productsPickedUp = 1;
            }
            
            $pickupDay = Configure::read('app.timeHelper')->getDbFormattedPickupDayByDbFormattedDate($order->date_add->i18nFormat(Configure::read('DateFormat.Database')));
            
            if ($order->comment != '' || $productsPickedUp == 1) {
                $this->PickupDay->save(
                    $this->PickupDay->newEntity([
                        'pickup_day' => $pickupDay,
                        'customer_id' => $order->id_customer,
                        'comment' => $order->comment,
                        'products_picked_up' => $productsPickedUp
                    ], [
                        'validate' => false
                    ])
                    );
                $i++;
            }
            
        }
        
        $this->out('Comments from ' . $i . ' orders migrated.');
        
    }
    
    
    private function migrateDataToOrderDetails()
    {
        $this->OrderDetail = TableRegistry::getTableLocator()->get('OrderDetails');
        $orderDetails = $this->OrderDetail->find('all');
        
        foreach($orderDetails as $orderDetail) {
            if ($orderDetail->created) {
                $pickupDay = Configure::read('app.timeHelper')->getDbFormattedPickupDayByDbFormattedDate($orderDetail->created->i18nFormat(Configure::read('DateFormat.Database')));
                $created = $orderDetail->created;
                $modified = $orderDetail->modified;
            }
            $this->OrderDetail->save(
                $this->OrderDetail->patchEntity($orderDetail, [
                    'pickup_day' => $pickupDay,
                    'created' => $created,
                    'modified' => $modified
                ])
                );
        }
        
        $this->out('Pickup day for ' . $orderDetails->count() . ' order details calculated.');
        
    }
    
}

<?php

namespace App\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

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
class CartsTable extends AppTable
{

    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->setPrimaryKey('id_cart');
        $this->belongsTo('Customer', [
            'foreignKey' => 'id_customer'
        ]);
        $this->hasMany('CartProducts', [
            'foreignKey' => 'id_cart'
        ]);
        $this->addBehavior('Timestamp');
    }

    public function getProductNameWithUnity($productName, $unity)
    {
        return $productName . ($unity != '' ? ' : ' . $unity : '');
    }
    
    public function adaptCartWithTimebasedCurrency($cart, $selectedTimebasedCurrencyTime, $selectedTimeAdaptionFactor)
    {
        
        $cart['CartProductSum'] -= $cart['CartTimebasedCurrencyPartMoneyInclSum'];
        $cart['CartProductSumExcl'] -= $cart['CartTimebasedCurrencyPartMoneyExclSum'];
        
        foreach($cart['CartProducts'] as &$cartProduct) {
            if (isset($cartProduct['timebasedCurrencyPartMoneyIncl'])) {
                $cartProduct['price'] -= $cartProduct['timebasedCurrencyPartMoneyIncl'];
            }
            if (isset($cartProduct['timebasedCurrencyPartMoneyExcl'])) {
                $cartProduct['priceExcl'] -=  $cartProduct['timebasedCurrencyPartMoneyExcl'];
            }
        }

        return $cart;
    }

    public function getCart($customerId)
    {
        $cart = $this->find('all', [
            'conditions' => [
                'Carts.status' => APP_ON,
                'Carts.id_customer' => $customerId
            ]
        ])->first();

        if (empty($cart)) {
            $cart2save = [
                'id_customer' => $customerId
            ];
            $cart = $this->save($this->newEntity($cart2save));
        }

        $cartProductsTable = TableRegistry::get('CartProducts');
        $productsTable = TableRegistry::get('Products');
        $manufacturersTable = TableRegistry::get('Manufacturers');
        
        $cartProducts = $cartProductsTable->find('all', [
            'conditions' => [
                'CartProducts.id_cart' => $cart['id_cart']
            ],
            'order' => [
                'ProductLangs.name' => 'ASC'
            ],
            'contain' => [
                'ProductLangs',
                'Products.ProductShops',
                'Products.Manufacturers',
                'Products.DepositProducts',
                'ProductAttributes.ProductAttributeCombinations.Attributes',
                'ProductAttributes.ProductAttributeShops',
                'ProductAttributes.DepositProductAttributes',
                'Products.Images'
            ]
        ])->toArray();

        $preparedCart = [
            'Cart' => $cart,
            'CartProducts' => []
        ];
        foreach ($cartProducts as &$cartProduct) {
            $manufacturerLink = Configure::read('app.htmlHelper')->link($cartProduct->product->manufacturer->name, Configure::read('app.slugHelper')->getManufacturerDetail($cartProduct->product->id_manufacturer, $cartProduct->product->manufacturer->name));

            $imageId = 0;
            if (!empty($cartProduct->product->image)) {
                $imageId = $cartProduct->product->image->id_image;
            }

            $productImage = Configure::read('app.htmlHelper')->image(Configure::read('app.htmlHelper')->getProductImageSrc($imageId, 'home'));
            $productLink = Configure::read('app.htmlHelper')->link(
                $cartProduct->product_lang->name,
                Configure::read('app.slugHelper')->getProductDetail(
                    $cartProduct->id_product,
                    $cartProduct->product_lang->name
                ),
                ['class' => 'product-name']
            );
            
            if (!empty($cartProduct->product_attribute->product_attribute_combination)) {
                
                $grossPrice = $productsTable->getGrossPrice($cartProduct->id_product, $cartProduct->product_attribute->product_attribute_shop->price) * $cartProduct->amount;
                $tax = $productsTable->getUnitTax($grossPrice, $cartProduct->product_attribute->product_attribute_shop->price, $cartProduct->amount) * $cartProduct->amount;
                
                // attribute
                $productData = [
                    'cartProductId' => $cartProduct->id_cart_product,
                    'productId' => $cartProduct->id_product . '-' . $cartProduct->id_product_attribute,
                    'productName' => $cartProduct->product_lang->name,
                    'productLink' => $productLink,
                    'unity' => $cartProduct->product_attribute->product_attribute_combination->attribute->name,
                    'amount' => $cartProduct->amount,
                    'manufacturerId' => $cartProduct->product->id_manufacturer,
                    'manufacturerLink' => $manufacturerLink,
                    'manufacturerName' => $cartProduct->product->manufacturer->name,
                    'image' => $productImage,
                    'deposit' => !empty($cartProduct->product_attribute->deposit_product_attribute->deposit) ? $cartProduct->product_attribute->deposit_product_attribute->deposit * $cartProduct->amount : 0, // * 1 to convert to float
                    'price' => $grossPrice,
                    'priceExcl' => $cartProduct->product_attribute->product_attribute_shop->price * $cartProduct->amount,
                    'tax' => $tax
                ];
                
            } else {
                // no attribute
                
                $grossPrice = $productsTable->getGrossPrice($cartProduct->id_product, $cartProduct->product->product_shop->price) * $cartProduct->amount;
                $tax = $productsTable->getUnitTax($grossPrice, $cartProduct->product->product_shop->price, $cartProduct->amount) * $cartProduct->amount;
                
                $productData = [
                    'cartProductId' => $cartProduct->id_cart_product,
                    'productId' => $cartProduct->id_product,
                    'productName' => $cartProduct->product_lang->name,
                    'productLink' => $productLink,
                    'unity' => $cartProduct->product_lang->unity,
                    'amount' => $cartProduct->amount,
                    'manufacturerId' => $cartProduct->product->id_manufacturer,
                    'manufacturerLink' => $manufacturerLink,
                    'manufacturerName' => $cartProduct->product->manufacturer->name,
                    'image' => $productImage,
                    'deposit' => !empty($cartProduct->product->deposit_product->deposit) ? $cartProduct->product->deposit_product->deposit * $cartProduct->amount : 0,
                    'price' => $grossPrice,
                    'priceExcl' => $cartProduct->product->product_shop->price * $cartProduct->amount,
                    'tax' => $tax
                ];
                
            }
            
            if (Configure::read('appDb.FCS_TIMEBASED_CURRENCY_ENABLED') && $this->getLoggedUser()['timebased_currency_enabled']) {
                if ($manufacturersTable->getOptionTimebasedCurrencyEnabled($cartProduct->product->manufacturer->timebased_currency_enabled)) {
                    $productData['timebasedCurrencyPartMoneyExcl'] = $manufacturersTable->getTimebasedCurrencyPartMoney($productData['priceExcl'], $cartProduct->product->manufacturer->timebased_currency_max_percentage);
                    $productData['timebasedCurrencyPartMoneyIncl'] = $manufacturersTable->getTimebasedCurrencyPartMoney($productData['price'], $cartProduct->product->manufacturer->timebased_currency_max_percentage);
                    $productData['timebasedCurrencyPartTime'] = $manufacturersTable->getTimebasedCurrencyPartTime($productData['price'], $cartProduct->product->manufacturer->timebased_currency_max_percentage);
                }
            }
            
            $preparedCart['CartProducts'][] = $productData;
            
        }

        // sum up deposits and products
        $preparedCart['CartDepositSum'] = 0;
        $preparedCart['CartProductSum'] = 0;
        $preparedCart['CartProductSumExcl'] = 0;
        $preparedCart['CartTaxSum'] = 0;
        $preparedCart['CartTimebasedCurrencyPartMoneyExclSum'] = 0;
        $preparedCart['CartTimebasedCurrencyPartMoneyInclSum'] = 0;
        $preparedCart['CartTimebasedCurrencyPartTimeSum'] = 0;
        foreach ($preparedCart['CartProducts'] as $p) {
            $preparedCart['CartDepositSum'] += $p['deposit'];
            $preparedCart['CartProductSum'] += $p['price'];
            $preparedCart['CartTaxSum'] += $p['tax'];
            $preparedCart['CartProductSumExcl'] += $p['priceExcl'];
            if (!empty($p['timebasedCurrencyPartMoneyExcl'])) {
                $preparedCart['CartTimebasedCurrencyPartMoneyExclSum'] += $p['timebasedCurrencyPartMoneyExcl'];
            }
            if (!empty($p['timebasedCurrencyPartMoneyIncl'])) {
                $preparedCart['CartTimebasedCurrencyPartMoneyInclSum'] += $p['timebasedCurrencyPartMoneyIncl'];
            }
            if (!empty($p['timebasedCurrencyPartTime'])) {
                $preparedCart['CartTimebasedCurrencyPartTimeSum'] += $p['timebasedCurrencyPartTime'];
            }
        }
        $preparedCart['CartTimebasedCurrencyPartMoneyExclSum'] = round($preparedCart['CartTimebasedCurrencyPartMoneyExclSum'], 2);
        $preparedCart['CartTimebasedCurrencyPartMoneyInclSum'] = round($preparedCart['CartTimebasedCurrencyPartMoneyInclSum'], 2);
        $preparedCart['CartTimebasedCurrencyPartTimeSum'] = round($preparedCart['CartTimebasedCurrencyPartTimeSum'], 2);
        return $preparedCart;
    }
}

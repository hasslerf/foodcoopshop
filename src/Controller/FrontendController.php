<?php

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;

/**
 * FrontendController
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
class FrontendController extends AppController
{

    public function isAuthorized($user)
    {
        return true;
    }

    /**
     * should be moved into component
     * adds product attributes and deposit
     *
     * @param array $products
     */
    protected function prepareProductsForFrontend($products)
    {
        $this->Product = TableRegistry::get('Products');
        $this->ProductAttribute = TableRegistry::get('ProductAttributes');

        foreach ($products as &$product) {
            $grossPrice = $this->Product->getGrossPrice($product['id_product'], $product['price']);
            $product['gross_price'] = $grossPrice;
            $product['tax'] = $grossPrice - $product['price'];
            $product['is_new'] = $this->Product->isNew($product['date_add']);

            $product['attributes'] = $this->ProductAttribute->find('all', [
                'conditions' => [
                    'ProductAttributes.id_product' => $product['id_product']
                ],
                'contain' => [
                    'ProductAttributeShops',
                    'StockAvailables',
                    'ProductAttributeCombinations.Attributes'
                ]
            ])->toArray();
            $i = 0;
            foreach ($product['attributes'] as $attribute) {
                $grossPrice = $this->Product->getGrossPrice($attribute['ProductAttributeShops']['id_product'], $attribute['ProductAttributeShops']['price']);
                $product['attributes'][$i]['ProductAttributeShops']['gross_price'] = $grossPrice;
                $product['attributes'][$i]['ProductAttributeShops']['tax'] = $grossPrice - $attribute['ProductAttributeShops']['price'];
                $i++;
            }
        }

        return $products;
    }

    protected function resetOriginalLoggedCustomer()
    {
        if ($this->request->session()->read('Auth.originalLoggedCustomer')) {
            $this->AppAuth->login($this->request->session()->read('Auth.originalLoggedCustomer'));
        }
    }

    protected function destroyShopOrderCustomer()
    {
        $this->request->session()->delete('Auth.shopOrderCustomer');
        $this->request->session()->delete('Auth.originalLoggedCustomer');
    }

    // is not called on ajax actions!
    public function beforeRender(Event $event)
    {

        parent::beforeRender($event);

        // when a shop order was placed, the pdfs that are rendered for the order confirmation email
        // called this method and therefore called resetOriginalLoggedCustomer() => email was sent t
        // the user who placed the order for a member and not to the member
        if ($this->response->type() != 'text/html') {
            return;
        }

        $this->resetOriginalLoggedCustomer();

        $categoriesForMenu = [];
        if (Configure::read('AppConfigDb.FCS_SHOW_PRODUCTS_FOR_GUESTS') || $this->AppAuth->user()) {
            $this->Category = TableRegistry::get('Categories');
            $allProductsCount = $this->Category->getProductsByCategoryId(Configure::read('AppConfig.categoryAllProducts'), false, '', 0, true);
            $newProductsCount = $this->Category->getProductsByCategoryId(Configure::read('AppConfig.categoryAllProducts'), true, '', 0, true);
            $categoriesForMenu = $this->Category->getForMenu();
            array_unshift($categoriesForMenu, [
                'slug' => '/neue-produkte',
                'name' => 'Neue Produkte <span class="additional-info"> (' . $newProductsCount . ')</span>',
                'options' => [
                    'fa-icon' => 'fa-star' . ($newProductsCount > 0 ? ' gold' : '')
                ]
            ]);
            array_unshift($categoriesForMenu, [
                'slug' => Configure::read('AppConfig.slugHelper')->getAllProducts(),
                'name' => 'Alle Produkte <span class="additional-info"> (' . $allProductsCount . ')</span>',
                'options' => [
                    'fa-icon' => 'fa-tags'
                ]
            ]);
        }
        $this->set('categoriesForMenu', $categoriesForMenu);

        $this->Manufacturer = TableRegistry::get('Manufacturers');
        
        $manufacturersForMenu = $this->Manufacturer->getForMenu($this->AppAuth);
        $this->set('manufacturersForMenu', $manufacturersForMenu);

        $this->Page = TableRegistry::get('Pages');
        $conditions = [];
        $conditions['Pages.active'] = APP_ON;
        $conditions[] = 'Pages.position > 0';
        if (! $this->AppAuth->user()) {
            $conditions['Pages.is_private'] = APP_OFF;
        }

        $pages = $this->Page->findAllGroupedByMenu($conditions);
        $pagesForHeader = [];
        $pagesForFooter = [];
        foreach ($pages as $page) {
            if ($page->menu_type == 'header') {
                $pagesForHeader[] = $page;
            }
            if ($page->menu_type == 'footer') {
                $pagesForFooter[] = $page;
            }
        }
        $this->set('pagesForHeader', $pagesForHeader);
        $this->set('pagesForFooter', $pagesForFooter);
    }

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);

        if (($this->name == 'Categories' && $this->request->action == 'detail') || $this->name == 'Carts') {
            // do not allow but call isAuthorized
        } else {
            $this->AppAuth->allow();
        }

        /*
         * changed the acutally logged in customer to the desired shopOrderCustomer
         * but only in controller beforeFilter(), beforeRender() sets the customer back to the original one
         * this means, in views $appAuth ALWAYS returns the original customer, in controllers ALWAYS the desired shopOrderCustomer
         */
        if ($this->request->session()->read('Auth.shopOrderCustomer')) {
            $this->request->session()->write('Auth.originalLoggedCustomer', $this->AppAuth->user());
            $this->AppAuth->login($this->request->session()->read('Auth.shopOrderCustomer')['Customers']);
        }

        if ($this->AppAuth->user() && Configure::read('AppConfig.htmlHelper')->paymentIsCashless()) {
            $creditBalance = $this->AppAuth->getCreditBalance();
            $this->set('creditBalance', $creditBalance);

            $shoppingLimitReached = Configure::read('AppConfigDb.FCS_MINIMAL_CREDIT_BALANCE') != - 1 && $creditBalance < Configure::read('AppConfigDb.FCS_MINIMAL_CREDIT_BALANCE') * - 1;
            $this->set('shoppingLimitReached', $shoppingLimitReached);
        }

        $this->AppAuth->setCart($this->AppAuth->getCart());
    }
}

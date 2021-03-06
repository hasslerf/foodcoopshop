<?php

/**
 * ProductsControllerTest
 *
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 1.4.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, http://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */
use App\Test\TestCase\AppCakeTestCase;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

class ProductsControllerTest extends AppCakeTestCase
{

    public $Product;

    public function setUp()
    {
        parent::setUp();
        $this->Product = TableRegistry::getTableLocator()->get('Products');
    }

    public function testChangeProductStatus()
    {
        $this->loginAsSuperadmin();
        $productId = 60;
        $status = APP_OFF;
        $this->browser->get('/admin/products/changeStatus/' . $productId . '/' . $status);
        $product = $this->Product->find('all', [
            'conditions' => [
                'Products.id_product' => $productId
            ]
        ])->first();
        $this->assertEquals($product->active, $status, 'changing product status did not work');
    }

    public function testEditPriceWithInvalidPriceAsSuperadmin()
    {
        $this->loginAsSuperadmin();
        $price = 'invalid-price';
        $this->changeProductPrice(346, $price);
        $response = $this->browser->getJsonDecodedContent();
        $this->assertRegExpWithUnquotedString('input format not correct: ' . $price, $response->msg);
        $this->assertJsonError();
    }

    public function testEditPriceOfNonExistingProductAsSuperadmin()
    {
        $this->loginAsSuperadmin();
        $productId = 1000;
        $this->changeProductPrice($productId, '0,15');
        // as long as isAuthorized does not return json on ajax requests...
        $this->assertAccessDeniedWithRedirectToLoginForm();
    }

    public function testEditPriceOfMeatManufactuerProductAsVegatableManufacturer()
    {
        $this->loginAsVegetableManufacturer();
        $productId = 102;
        $this->changeProductPrice($productId, '0,15');
        // as long as isAuthorized does not return json on ajax requests...
        $this->assertAccessDeniedWithRedirectToLoginForm();
    }

    public function testEditPriceOfProductAsSuperadminToZero()
    {
        $this->loginAsSuperadmin();
        $this->assertPriceChange(346, '0', '0,00');
    }

    public function testEditPriceOfProductAsSuperadmin()
    {
        $this->loginAsSuperadmin();
        $this->assertPriceChange(346, '2,20', '2,00');
    }

    public function testEditPricePerUnitOfProductAsSuperadmin()
    {
        $this->loginAsSuperadmin();
        $this->assertPriceChange(346, '2,20', '2,00');
    }

    public function testEditPriceOfAttributeAsSuperadmin()
    {
        $this->loginAsSuperadmin();
        $this->assertPriceChange('60-10', '1,25', '1,106195');
    }

    public function testEditPriceWith0PercentTax()
    {
        $this->loginAsSuperadmin();
        $this->assertPriceChange('163', '1,60', '1,60');
    }

    /**
     * asserts price in database (getGrossPrice)
     */
    private function assertPriceChange($productId, $price, $expectedNetPrice)
    {
        $price = Configure::read('app.numberHelper')->parseFloatRespectingLocale($price);
        $expectedNetPrice = Configure::read('app.numberHelper')->parseFloatRespectingLocale($expectedNetPrice);
        $this->changeProductPrice($productId, $price);
        $this->assertJsonOk();
        $netPrice = $this->Product->getNetPrice($productId, $price);
        $this->assertEquals(floatval($expectedNetPrice), $netPrice, 'editing price failed');
    }
}

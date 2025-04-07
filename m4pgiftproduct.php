<?php
/**
 * LICENCE
 *
 * ALL RIGHTS RESERVED.
 * YOU ARE NOT ALLOWED TO COPY/EDIT/SHARE/WHATEVER.
 *
 * IN CASE OF ANY PROBLEM CONTACT AUTHOR.
 *
 *  @author    Jan Kołodziej (contact@modules4presta.io)
 *  @copyright Modules4Presta.io
 *  @license   ALL RIGHTS RESERVED
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class M4PGiftProduct extends Module
{
    public function __construct()
    {
        $this->name = 'm4pgiftproduct';
        $this->tab = 'checkout';
        $this->version = '1.1.0';
        $this->author = 'Modules4Presta.io';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Gift on Price Threshold');
        $this->description = $this->l('Adds a gift product to cart if the order exceeds a specified amount.');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('displayCartExtraProductActions') &&
            $this->registerHook('displayShoppingCart') &&
            $this->installConfiguration();
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->removeConfiguration();
    }

    private function installConfiguration()
    {
        return Configuration::updateValue('GIFT_PRODUCT_ID', 0) &&
               Configuration::updateValue('GIFT_THRESHOLD', 100) &&
               Configuration::updateValue('GIFT_PRICE', 0) &&
               Configuration::updateValue('GIFT_EXPIRY_DATE', '') &&
               Configuration::updateValue('GIFT_STOCK_DEPENDENT', 0) &&
               Configuration::updateValue('GIFT_ACTIVE', true);
    }

    private function removeConfiguration()
    {
        return Configuration::deleteByName('GIFT_PRODUCT_ID') &&
               Configuration::deleteByName('GIFT_THRESHOLD') &&
               Configuration::deleteByName('GIFT_PRICE') &&
               Configuration::deleteByName('GIFT_EXPIRY_DATE') &&
               Configuration::deleteByName('GIFT_STOCK_DEPENDENT') &&
               Configuration::deleteByName('GIFT_ACTIVE');
    }

    public function hookDisplayCartExtraProductActions($params)
    {
        if (!Configuration::get('GIFT_ACTIVE')) {
            return;
        }

        $cart = $this->context->cart;
        $giftProductId = (int) Configuration::get('GIFT_PRODUCT_ID');
        $threshold = (float) Configuration::get('GIFT_THRESHOLD');
        $giftPrice = (float) Configuration::get('GIFT_PRICE');
        $expiryDate = Configuration::get('GIFT_EXPIRY_DATE');
        $stockDependent = Configuration::get('GIFT_STOCK_DEPENDENT');

        if (empty($giftProductId) || empty((new Product($giftProductId))->id)) {
            return;
        }

        if (
            !empty($expiryDate)
            && strtotime($expiryDate) < strtotime(date('Y-m-d'))
        ) {
            return;
        }

        if ($stockDependent) {
            $product = new Product($giftProductId);
            if ($product->quantity <= 0) {
                return;
            }
        }

        $total = $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING);

        if ($total >= $threshold) {
            $cartProducts = $cart->getProducts();
            foreach ($cartProducts as $product) {
                if ((int)$product['id_product'] === $giftProductId) {
                    $cart->updateQty($product['cart_quantity'], $giftProductId, null, false, 'down');
                    break;
                }
            }

            $cart->updateQty(1, $giftProductId, null, false, 'up');
        }
    }

    private function getProductImages($idProduct)
    {
        $images = (new Product($idProduct))->getImages($this->context->language->id);

        if (!empty($images)) {
            foreach ($images as $image) {
                if (!empty($image['cover'])) {
                    $images = (array) $image;
                    $imageInstance = new Image($image['id_image']);
                    $imagesUrl = _PS_BASE_URL_ . _THEME_PROD_DIR_ . $imageInstance->getExistingImgPath() . '.jpg';

                    return $imagesUrl;
                }
            }
        }

        $basicDir = _PS_BASE_URL_ . _THEME_PROD_DIR_ . $this->context->language->iso_code . '-default-large_default.jpg';
        return $basicDir;
    }

    public function hookDisplayShoppingCart()
    {
        if (!Configuration::get('GIFT_ACTIVE')) {
            return;
        }

        $idProduct = (int) Configuration::get('GIFT_PRODUCT_ID');
        $product = new Product($idProduct);
        if (empty($product->id)) {
            return;
        }

        $threshold = (float) Configuration::get('GIFT_THRESHOLD');
        $price = (float) Configuration::get('GIFT_PRICE');

        $this->context->smarty->assign([
            'threshold' => Tools::displayPrice($threshold),
            'image' => $this->getProductImages($idProduct),
            'name' => Product::getProductName($idProduct),
            'price' => Tools::displayPrice($price),
            'url' => $product->getLink(),
        ]);

        return $this->context->smarty->fetch('module:' . $this->name . '/views/templates/hook/displayShoppingCart.tpl');
    }

    private function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Gift Product Settings')
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Active'),
                        'name' => 'GIFT_ACTIVE',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            ]
                        ]
                    ],
                    ['type' => 'text', 'label' => $this->l('Gift Product ID'), 'name' => 'GIFT_PRODUCT_ID', 'required' => true],
                    ['type' => 'text', 'label' => $this->l('Threshold Price'), 'name' => 'GIFT_THRESHOLD', 'required' => true],
                    ['type' => 'text', 'label' => $this->l('Gift Price'), 'name' => 'GIFT_PRICE', 'required' => true],
                    ['type' => 'date', 'label' => $this->l('Expiry Date'), 'name' => 'GIFT_EXPIRY_DATE'],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Stock Dependent'),
                        'name' => 'GIFT_STOCK_DEPENDENT',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            ]
                        ]
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save')
                ]
            ]
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submitGiftSettings';

        $helper->fields_value = [
            'GIFT_PRODUCT_ID' => Configuration::get('GIFT_PRODUCT_ID'),
            'GIFT_THRESHOLD' => Configuration::get('GIFT_THRESHOLD'),
            'GIFT_PRICE' => Configuration::get('GIFT_PRICE'),
            'GIFT_EXPIRY_DATE' => Configuration::get('GIFT_EXPIRY_DATE'),
            'GIFT_STOCK_DEPENDENT' => Configuration::get('GIFT_STOCK_DEPENDENT'),
            'GIFT_ACTIVE' => Configuration::get('GIFT_ACTIVE')
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitGiftSettings')) {
            Configuration::updateValue('GIFT_PRODUCT_ID', (int) Tools::getValue('GIFT_PRODUCT_ID'));
            Configuration::updateValue('GIFT_THRESHOLD', (float) Tools::getValue('GIFT_THRESHOLD'));
            Configuration::updateValue('GIFT_PRICE', (float) Tools::getValue('GIFT_PRICE'));
            Configuration::updateValue('GIFT_EXPIRY_DATE', Tools::getValue('GIFT_EXPIRY_DATE'));
            Configuration::updateValue('GIFT_STOCK_DEPENDENT', (int) Tools::getValue('GIFT_STOCK_DEPENDENT'));
            Configuration::updateValue('GIFT_ACTIVE', (int) Tools::getValue('GIFT_ACTIVE'));

            $output .= $this->displayConfirmation($this->l('Successful update.'));
        }

        $output .= $this->renderForm();

        return $output;
    }
}

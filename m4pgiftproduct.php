<?php

/**
 * m4pgiftproduct
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class M4PGiftProduct extends Module
{
    const PRODUCT_ID = 'M4PGIFTPRODUCT_PRODUCT_ID';
    const THRESHOLD = 'M4PGIFTPRODUCT_THRESHOLD';
    const PRICE = 'M4PGIFTPRODUCT_PRICE';
    const EXPIRY_DATE = 'M4PGIFTPRODUCT_EXPIRY_DATE';
    const STOCK_DEPENDENT = 'M4PGIFTPRODUCT_STOCK_DEPENDENT';
    const ACTIVE = 'M4PGIFTPRODUCT_ACTIVE';

    /** Guards against re-entering the cart hook through our own updateQty() */
    private static $updatingCart = false;

    public function __construct()
    {
        $this->name = 'm4pgiftproduct';
        $this->tab = 'checkout';
        $this->version = '2.0.0';
        $this->author = 'Modules4Presta';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.6.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('Gift on price threshold', [], 'Modules.M4pgiftproduct.Admin');
        $this->description = $this->trans('Adds a gift product to the cart once the order passes an amount you set.', [], 'Modules.M4pgiftproduct.Admin');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionCartSave')
            && $this->registerHook('actionProductPriceCalculation')
            && $this->registerHook('displayShoppingCart')
            && $this->registerHook('actionFrontControllerSetMedia')
            && Configuration::updateValue(self::PRODUCT_ID, 0)
            && Configuration::updateValue(self::THRESHOLD, 100)
            && Configuration::updateValue(self::PRICE, 0)
            && Configuration::updateValue(self::EXPIRY_DATE, '')
            && Configuration::updateValue(self::STOCK_DEPENDENT, 0)
            && Configuration::updateValue(self::ACTIVE, 0)
            && $this->installDb();
    }

    public function uninstall()
    {
        foreach ($this->settingNames() as $name) {
            Configuration::deleteByName($name);
        }

        return $this->uninstallDb() && parent::uninstall();
    }

    protected function installDb(): bool
    {
        return (bool) Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'm4pgiftproduct_cart` (
                `id_cart` INT(10) UNSIGNED NOT NULL,
                `gift_added` TINYINT(1) NOT NULL DEFAULT 0,
                `dismissed` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_cart`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;'
        );
    }

    protected function uninstallDb(): bool
    {
        return (bool) Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'm4pgiftproduct_cart`');
    }

    /**
     * Keeps the gift in step with the cart on every change: adds it once the
     * threshold is met, takes it away when the cart falls back below, and stops
     * offering it when the customer deletes it by hand.
     */
    public function hookActionCartSave($params)
    {
        if (self::$updatingCart) {
            return;
        }

        $cart = isset($params['cart']) && Validate::isLoadedObject($params['cart']) ? $params['cart'] : $this->context->cart;
        if (!Validate::isLoadedObject($cart)) {
            return;
        }

        $idGift = (int) Configuration::get(self::PRODUCT_ID);
        if (!$idGift) {
            return;
        }

        $idCart = (int) $cart->id;
        $state = $this->cartState($idCart);
        $inCart = $this->giftQuantityInCart($cart, $idGift);

        $eligible = Configuration::get(self::ACTIVE)
            && $this->giftIsAvailable($idGift)
            && $this->cartTotalWithoutGift($cart, $idGift) >= (float) Configuration::get(self::THRESHOLD);

        if (!$eligible) {
            if ($inCart > 0 && $state['gift_added']) {
                $this->changeGiftQuantity($cart, $idGift, -$inCart);
            }
            $this->saveCartState($idCart, false, false);

            return;
        }

        if ($state['dismissed']) {
            if ($inCart === 0) {
                return;
            }

            // Put back by hand from the cart block, so the offer stands again.
            $this->saveCartState($idCart, true, false);
            if ($inCart > 1) {
                $this->changeGiftQuantity($cart, $idGift, 1 - $inCart);
            }

            return;
        }

        // The gift was there a moment ago and the threshold still holds, so the
        // customer took it out — offering it again would make the line undeletable.
        if ($inCart === 0 && $state['gift_added']) {
            $this->saveCartState($idCart, false, true);

            return;
        }

        if ($inCart === 1) {
            return;
        }

        if ($this->changeGiftQuantity($cart, $idGift, 1 - $inCart)) {
            $this->saveCartState($idCart, true, false);
        }
    }

    /**
     * Sells the gift at the configured price for as long as it sits in the cart.
     * The cart hook decides whether it belongs there, so no threshold is
     * recalculated here — that would recurse into the price calculation.
     *
     * @param array $params price is passed by reference by the core
     */
    public function hookActionProductPriceCalculation(&$params)
    {
        if (!Configuration::get(self::ACTIVE)) {
            return;
        }

        $idGift = (int) Configuration::get(self::PRODUCT_ID);
        if (!$idGift || (int) $params['id_product'] !== $idGift) {
            return;
        }

        $idCart = (int) $params['id_cart'];
        if (!$idCart || !$this->cartState($idCart)['gift_added'] || !$this->giftRowExists($idCart, $idGift)) {
            return;
        }

        $price = (float) Configuration::get(self::PRICE);
        $params['price'] = empty($params['use_tax'])
            ? $price
            : $price * (1 + ((float) Tax::getProductTaxRate($idGift, $this->addressId($params)) / 100));
    }

    public function hookActionFrontControllerSetMedia()
    {
        if ($this->context->controller->php_self === 'cart') {
            $this->context->controller->registerStylesheet(
                'm4pgiftproduct-block',
                'modules/' . $this->name . '/views/css/front-gift-block.css',
                ['media' => 'all', 'priority' => 150]
            );
        }
    }

    public function hookDisplayShoppingCart()
    {
        $idGift = (int) Configuration::get(self::PRODUCT_ID);
        if (!Configuration::get(self::ACTIVE) || !$idGift || !$this->giftIsAvailable($idGift)) {
            return;
        }

        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart)) {
            return;
        }

        $product = $this->presentGift($idGift);
        if (!$product) {
            return;
        }

        $threshold = (float) Configuration::get(self::THRESHOLD);
        $total = $this->cartTotalWithoutGift($cart, $idGift);
        $inCart = $this->giftQuantityInCart($cart, $idGift) > 0;

        $this->context->smarty->assign([
            'm4pgiftproduct_product' => $product,
            'm4pgiftproduct_miniature' => file_exists(_PS_THEME_DIR_ . 'templates/catalog/_partials/miniatures/product.tpl'),
            'm4pgiftproduct_threshold' => $this->formatPrice($threshold),
            'm4pgiftproduct_price' => $this->formatPrice((float) Configuration::get(self::PRICE)),
            'm4pgiftproduct_free' => (float) Configuration::get(self::PRICE) <= 0,
            'm4pgiftproduct_missing' => $total < $threshold ? $this->formatPrice($threshold - $total) : null,
            'm4pgiftproduct_in_cart' => $inCart,
            'm4pgiftproduct_claim_url' => !$inCart && $total >= $threshold ? $this->claimUrl($idGift) : null,
        ]);

        return $this->context->smarty->fetch('module:' . $this->name . '/views/templates/hook/displayShoppingCart.tpl');
    }

    /**
     * Builds the product the way the theme's own listing does, so the block can
     * reuse the miniature template instead of imitating it.
     *
     * @return array|null
     */
    private function presentGift(int $idGift)
    {
        $assembler = new ProductAssembler($this->context);
        $presenter = new PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter(
            new PrestaShop\PrestaShop\Adapter\Image\ImageRetriever($this->context->link),
            $this->context->link,
            new PrestaShop\PrestaShop\Adapter\Product\PriceFormatter(),
            new PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        try {
            $presented = $presenter->present(
                (new ProductPresenterFactory($this->context))->getPresentationSettings(),
                $assembler->assembleProduct(['id_product' => $idGift]),
                $this->context->language
            );

            return $this->markAsGift($presented instanceof JsonSerializable ? $presented->jsonSerialize() : (array) $presented, $idGift);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Shows the gift price the way the theme shows any promotion: the catalogue
     * price struck through, the gift price next to it and a badge.
     */
    private function markAsGift(array $product, int $idGift): array
    {
        $regular = (float) ($product['price_amount'] ?? 0);
        if ($regular <= 0) {
            return $product;
        }

        $price = (float) Configuration::get(self::PRICE);
        if (Product::getTaxCalculationMethod((int) $this->context->customer->id) == PS_TAX_INC) {
            $price *= 1 + ((float) Tax::getProductTaxRate($idGift) / 100);
        }

        if ($price >= $regular) {
            return $product;
        }

        $product['price'] = $this->formatPrice($price);
        $product['price_amount'] = $price;
        $product['regular_price'] = $this->formatPrice($regular);
        $product['regular_price_amount'] = $regular;
        $product['has_discount'] = true;
        $product['discount_type'] = 'amount';
        $product['discount_amount'] = $this->formatPrice($regular - $price);
        $product['discount_to_display'] = $product['discount_amount'];
        // Below half a percent the badge would read "-0%", so the amount speaks alone.
        $percent = (int) round(100 * ($regular - $price) / $regular);
        if ($percent >= 1) {
            $product['discount_percentage'] = '-' . $percent . '%';
            $product['discount_percentage_absolute'] = $percent . '%';
        }
        $product['flags']['m4pgiftproduct'] = [
            'type' => 'discount',
            'label' => $this->trans('Gift', [], 'Modules.M4pgiftproduct.Shop'),
        ];

        return $product;
    }

    private function claimUrl(int $idGift): string
    {
        return $this->context->link->getPageLink('cart', true, null, [
            'add' => 1,
            'id_product' => $idGift,
            'token' => Tools::getToken(false),
        ]);
    }

    public function getContent()
    {
        if (Tools::getValue('ajax') && Tools::getValue('action') === 'searchGiftProduct') {
            $this->renderProductSearch();
        }

        $output = '';

        if (Tools::isSubmit('submitM4pGiftProduct')) {
            $errors = $this->saveSettings();
            $output .= $errors
                ? $this->displayError(implode('<br>', $errors))
                : $this->displayConfirmation($this->trans('Settings updated.', [], 'Modules.M4pgiftproduct.Admin'));
        }

        return $output . $this->renderForm();
    }

    private function saveSettings(): array
    {
        $errors = [];

        $idProduct = (int) Tools::getValue(self::PRODUCT_ID);
        if ($idProduct && !Validate::isLoadedObject(new Product($idProduct))) {
            $errors[] = $this->trans('There is no product with this ID.', [], 'Modules.M4pgiftproduct.Admin');
        }

        $expiryDate = (string) Tools::getValue(self::EXPIRY_DATE);
        if ($expiryDate !== '' && !Validate::isDate($expiryDate) && !Validate::isDateFormat($expiryDate)) {
            $errors[] = $this->trans('The expiry date is not a valid date.', [], 'Modules.M4pgiftproduct.Admin');
        }

        if ($errors) {
            return $errors;
        }

        Configuration::updateValue(self::PRODUCT_ID, $idProduct);
        Configuration::updateValue(self::THRESHOLD, (float) str_replace(',', '.', (string) Tools::getValue(self::THRESHOLD)));
        Configuration::updateValue(self::PRICE, (float) str_replace(',', '.', (string) Tools::getValue(self::PRICE)));
        Configuration::updateValue(self::EXPIRY_DATE, $expiryDate);
        Configuration::updateValue(self::STOCK_DEPENDENT, (int) Tools::getValue(self::STOCK_DEPENDENT));
        Configuration::updateValue(self::ACTIVE, (int) Tools::getValue(self::ACTIVE));

        return [];
    }

    private function renderForm(): string
    {
        $switch = function (string $label, string $name): array {
            return [
                'type' => 'switch',
                'label' => $label,
                'name' => $name,
                'is_bool' => true,
                'values' => [
                    ['id' => $name . '_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Modules.M4pgiftproduct.Admin')],
                    ['id' => $name . '_off', 'value' => 0, 'label' => $this->trans('No', [], 'Modules.M4pgiftproduct.Admin')],
                ],
            ];
        };

        $form = [
            'form' => [
                'legend' => ['title' => $this->trans('Gift product settings', [], 'Modules.M4pgiftproduct.Admin')],
                'input' => [
                    $switch($this->trans('Enabled', [], 'Modules.M4pgiftproduct.Admin'), self::ACTIVE),
                    [
                        'type' => 'html',
                        'label' => $this->trans('Gift product', [], 'Modules.M4pgiftproduct.Admin'),
                        'name' => self::PRODUCT_ID,
                        'html_content' => $this->renderProductPicker(),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Order total that unlocks the gift', [], 'Modules.M4pgiftproduct.Admin'),
                        'desc' => $this->trans('Tax included, shipping excluded, without the gift itself.', [], 'Modules.M4pgiftproduct.Admin'),
                        'name' => self::THRESHOLD,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Gift price', [], 'Modules.M4pgiftproduct.Admin'),
                        'desc' => $this->trans('Tax excluded. Leave 0 to give the product away.', [], 'Modules.M4pgiftproduct.Admin'),
                        'name' => self::PRICE,
                        'required' => true,
                    ],
                    [
                        'type' => 'date',
                        'label' => $this->trans('Runs until', [], 'Modules.M4pgiftproduct.Admin'),
                        'desc' => $this->trans('Leave empty to run with no end date.', [], 'Modules.M4pgiftproduct.Admin'),
                        'name' => self::EXPIRY_DATE,
                    ],
                    $switch($this->trans('Only while in stock', [], 'Modules.M4pgiftproduct.Admin'), self::STOCK_DEPENDENT),
                ],
                'submit' => ['title' => $this->trans('Save', [], 'Modules.M4pgiftproduct.Admin')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submitM4pGiftProduct';
        $helper->fields_value = array_combine(
            $this->settingNames(),
            array_map(function ($name) { return Configuration::get($name); }, $this->settingNames())
        );

        return $helper->generateForm([$form]);
    }

    /**
     * Answers the picker's lookup and ends the request — the module configuration
     * page runs behind the back office token, so no extra check is needed here.
     */
    private function renderProductSearch(): void
    {
        $query = trim((string) Tools::getValue('q'));
        $products = [];

        if (Tools::strlen($query) >= 2) {
            foreach (Product::searchByName((int) $this->context->language->id, $query, null, 20) ?: [] as $product) {
                $products[] = [
                    'id' => (int) $product['id_product'],
                    'name' => $product['name'],
                    'reference' => $product['reference'],
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['products' => $products]);
        exit;
    }

    private function renderProductPicker(): string
    {
        $idProduct = (int) Configuration::get(self::PRODUCT_ID);
        $product = $idProduct ? new Product($idProduct, false, $this->context->language->id) : null;
        $chosen = $product && Validate::isLoadedObject($product)
            ? $product->name . ' (ID ' . $idProduct . ')'
            : $this->trans('No product chosen yet.', [], 'Modules.M4pgiftproduct.Admin');

        return '<div class="m4pgiftproduct-picker js-m4pgiftproduct-picker"'
            . ' data-empty-label="' . Tools::safeOutput($this->trans('No product has been found.', [], 'Modules.M4pgiftproduct.Admin')) . '">'
            . '<input type="hidden" name="' . self::PRODUCT_ID . '" value="' . $idProduct . '" class="js-m4pgiftproduct-id">'
            . '<input type="text" class="form-control js-m4pgiftproduct-search" autocomplete="off"'
            . ' placeholder="' . Tools::safeOutput($this->trans('Type a name or a reference', [], 'Modules.M4pgiftproduct.Admin')) . '">'
            . '<ul class="m4pgiftproduct-results js-m4pgiftproduct-results" hidden></ul>'
            . '<p class="m4pgiftproduct-chosen js-m4pgiftproduct-chosen">' . Tools::safeOutput($chosen) . '</p>'
            . '</div>'
            . '<link rel="stylesheet" href="' . $this->_path . 'views/css/admin-gift-picker.css">'
            . '<script src="' . $this->_path . 'views/js/admin-gift-picker.js"></script>';
    }

    private function settingNames(): array
    {
        return [self::ACTIVE, self::PRODUCT_ID, self::THRESHOLD, self::PRICE, self::EXPIRY_DATE, self::STOCK_DEPENDENT];
    }

    private function giftIsAvailable(int $idGift): bool
    {
        $product = new Product($idGift);
        if (!Validate::isLoadedObject($product) || !$product->active) {
            return false;
        }

        $expiryDate = (string) Configuration::get(self::EXPIRY_DATE);
        if ($expiryDate !== '' && strtotime($expiryDate) < strtotime(date('Y-m-d'))) {
            return false;
        }

        if (Configuration::get(self::STOCK_DEPENDENT) && StockAvailable::getQuantityAvailableByProduct($idGift) <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Total of every line except the gift, tax included and without shipping.
     */
    private function cartTotalWithoutGift(Cart $cart, int $idGift): float
    {
        $products = array_filter($cart->getProducts(true), function ($product) use ($idGift) {
            return (int) $product['id_product'] !== $idGift;
        });

        if (!$products) {
            return 0.0;
        }

        return (float) $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, $products);
    }

    private function giftQuantityInCart(Cart $cart, int $idGift): int
    {
        foreach ($cart->getProducts(true) as $product) {
            if ((int) $product['id_product'] === $idGift) {
                return (int) $product['cart_quantity'];
            }
        }

        return 0;
    }

    /**
     * @return bool false when PrestaShop refused the change, for instance
     *              because the gift ran out of stock
     */
    private function changeGiftQuantity(Cart $cart, int $idGift, int $delta): bool
    {
        if ($delta === 0) {
            return true;
        }

        self::$updatingCart = true;
        $done = $cart->updateQty(abs($delta), $idGift, null, false, $delta > 0 ? 'up' : 'down');
        self::$updatingCart = false;

        Product::flushPriceCache();

        return $done === true;
    }

    private function cartState(int $idCart): array
    {
        $row = Db::getInstance()->getRow(
            'SELECT `gift_added`, `dismissed` FROM `' . _DB_PREFIX_ . 'm4pgiftproduct_cart` WHERE `id_cart` = ' . $idCart
        );

        return [
            'gift_added' => (bool) ($row['gift_added'] ?? false),
            'dismissed' => (bool) ($row['dismissed'] ?? false),
        ];
    }

    private function saveCartState(int $idCart, bool $giftAdded, bool $dismissed): void
    {
        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'm4pgiftproduct_cart` (`id_cart`, `gift_added`, `dismissed`)
            VALUES (' . $idCart . ', ' . (int) $giftAdded . ', ' . (int) $dismissed . ')
            ON DUPLICATE KEY UPDATE `gift_added` = VALUES(`gift_added`), `dismissed` = VALUES(`dismissed`)'
        );
    }

    private function giftRowExists(int $idCart, int $idGift): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'cart_product`
            WHERE `id_cart` = ' . $idCart . ' AND `id_product` = ' . $idGift
        );
    }

    private function addressId(array $params): ?int
    {
        return isset($params['address']) && $params['address'] instanceof Address && $params['address']->id
            ? (int) $params['address']->id
            : null;
    }

    private function formatPrice(float $price): string
    {
        return $this->context->currentLocale->formatPrice($price, $this->context->currency->iso_code);
    }

    private function coverImageUrl(Product $product): string
    {
        $cover = Product::getCover($product->id);
        if (!$cover) {
            return $this->context->link->getImageLink($product->link_rewrite, $this->context->language->iso_code . '-default', 'home_default');
        }

        return $this->context->link->getImageLink($product->link_rewrite, (int) $cover['id_image'], 'home_default');
    }
}

<?php

class Product extends ProductCore
{
        /**
     * Price calculation / Get product price.
     *
     * @param int $id_shop Shop identifier
     * @param int $id_product Product identifier
     * @param int|null $id_product_attribute Attribute identifier
     * @param int $id_country Country identifier
     * @param int $id_state State identifier
     * @param string $zipcode
     * @param int $id_currency Currency identifier
     * @param int $id_group Group identifier
     * @param int $quantity Quantity Required for Specific prices : quantity discount application
     * @param bool $use_tax with (1) or without (0) tax
     * @param int $decimals Number of decimals returned
     * @param bool $only_reduc Returns only the reduction amount
     * @param bool $use_reduc Set if the returned amount will include reduction
     * @param bool $with_ecotax insert ecotax in price output
     * @param array|null $specific_price If a specific price applies regarding the previous parameters,
     *                                   this variable is filled with the corresponding SpecificPrice data
     * @param bool $use_group_reduction
     * @param int $id_customer Customer identifier
     * @param bool $use_customer_price
     * @param int $id_cart Cart identifier
     * @param int $real_quantity
     * @param int $id_customization Customization identifier
     *
     * @return float|null Product price, void if not found in cache $_pricesLevel2
     */
    public static function priceCalculation(
        $id_shop,
        $id_product,
        $id_product_attribute,
        $id_country,
        $id_state,
        $zipcode,
        $id_currency,
        $id_group,
        $quantity,
        $use_tax,
        $decimals,
        $only_reduc,
        $use_reduc,
        $with_ecotax,
        &$specific_price,
        $use_group_reduction,
        $id_customer = 0,
        $use_customer_price = true,
        $id_cart = 0,
        $real_quantity = 0,
        $id_customization = 0
    ) {
        static $address = null;
        static $context = null;

        if ($context == null) {
            $context = Context::getContext()->cloneContext();
        }

        if ($address === null) {
            if (is_object($context->cart) && $context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')} != null) {
                $id_address = $context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
                $address = new Address($id_address);
            } else {
                $address = new Address();
            }
        }

        if ($id_shop !== null && $context->shop->id != (int) $id_shop) {
            $context->shop = new Shop((int) $id_shop);
        }

        if (!$use_customer_price) {
            $id_customer = 0;
        }

        if ($id_product_attribute === null) {
            $id_product_attribute = Product::getDefaultAttribute($id_product);
        }

        $cache_id = (int) $id_product . '-' . (int) $id_shop . '-' . (int) $id_currency . '-' . (int) $id_country . '-' . $id_state . '-' . $zipcode . '-' . (int) $id_group .
            '-' . (int) $quantity . '-' . (int) $id_product_attribute . '-' . (int) $id_customization .
            '-' . (int) $with_ecotax . '-' . (int) $id_customer . '-' . (int) $use_group_reduction . '-' . (int) $id_cart . '-' . (int) $real_quantity .
            '-' . ($only_reduc ? '1' : '0') . '-' . ($use_reduc ? '1' : '0') . '-' . ($use_tax ? '1' : '0') . '-' . (int) $decimals;

        // reference parameter is filled before any returns
        $specific_price = SpecificPrice::getSpecificPrice(
            (int) $id_product,
            $id_shop,
            $id_currency,
            $id_country,
            $id_group,
            $quantity,
            $id_product_attribute,
            $id_customer,
            $id_cart,
            $real_quantity
        );

        if (isset(self::$_prices[$cache_id])) {
            return self::$_prices[$cache_id];
        }

        // fetch price & attribute price
        $cache_id_2 = $id_product . '-' . $id_shop;
        // We need to check the cache for this price AND attribute, if absent the whole product cache needs update
        // This can happen if the cache was filled before the combination was created for example
        if (!isset(self::$_pricesLevel2[$cache_id_2][(int) $id_product_attribute])) {
            $sql = new DbQuery();
            $sql->select('product_shop.`price`, product_shop.`ecotax`');
            $sql->from('product', 'p');
            $sql->innerJoin('product_shop', 'product_shop', '(product_shop.id_product=p.id_product AND product_shop.id_shop = ' . (int) $id_shop . ')');
            $sql->where('p.`id_product` = ' . (int) $id_product);
            if (Combination::isFeatureActive()) {
                $sql->select('IFNULL(product_attribute_shop.id_product_attribute,0) id_product_attribute, product_attribute_shop.`price` AS attribute_price, product_attribute_shop.default_on, product_attribute_shop.`ecotax` AS attribute_ecotax');
                $sql->leftJoin('product_attribute_shop', 'product_attribute_shop', '(product_attribute_shop.id_product = p.id_product AND product_attribute_shop.id_shop = ' . (int) $id_shop . ')');
            } else {
                $sql->select('0 as id_product_attribute');
            }

            $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            if (is_array($res) && count($res)) {
                foreach ($res as $row) {
                    $array_tmp = [
                        'price' => $row['price'],
                        'ecotax' => $row['ecotax'],
                        'attribute_price' => $row['attribute_price'] ?? null,
                        'attribute_ecotax' => $row['attribute_ecotax'] ?? null,
                    ];
                    self::$_pricesLevel2[$cache_id_2][(int) $row['id_product_attribute']] = $array_tmp;

                    if (isset($row['default_on']) && $row['default_on'] == 1) {
                        self::$_pricesLevel2[$cache_id_2][0] = $array_tmp;
                    }
                }
            }
        }

        if (!isset(self::$_pricesLevel2[$cache_id_2][(int) $id_product_attribute])) {
            return null;
        }

        $result = self::$_pricesLevel2[$cache_id_2][(int) $id_product_attribute];

        $startPrice = $result['price'];
        if (!$specific_price || $specific_price['price'] < 0) {
            $price = (float) $result['price'];
        } else {
            $price = (float) $specific_price['price'];
        }
        // convert only if the specific price currency is different from the default currency
        if (
            !$specific_price ||
            !(
                $specific_price['price'] >= 0 &&
                $specific_price['id_currency'] &&
                $id_currency === $specific_price['id_currency']
            )
        ) {
            $price = Tools::convertPrice($price, $id_currency);

            if (isset($specific_price['price']) && $specific_price['price'] >= 0) {
                $specific_price['price'] = $price;
            }
        }
        
        // Attribute price
        if (is_array($result) && (!$specific_price || !$specific_price['id_product_attribute'] || $specific_price['price'] < 0)) {
            $attribute_price = Tools::convertPrice($result['attribute_price'] !== null ? (float) $result['attribute_price'] : 0, $id_currency);
            // If you want the default combination, please use NULL value instead
            if ($id_product_attribute !== false) {
                $price += $attribute_price;
            }
        }

        // Customization price
        if ((int) $id_customization) {
            $price += Tools::convertPrice(Customization::getCustomizationPrice($id_customization), $id_currency);
        }

        // Tax
        $address->id_country = $id_country;
        $address->id_state = $id_state;
        $address->postcode = $zipcode;

        $tax_manager = TaxManagerFactory::getManager($address, Product::getIdTaxRulesGroupByIdProduct((int) $id_product, $context));
        $product_tax_calculator = $tax_manager->getTaxCalculator();

        // Add Tax
        if ($use_tax) {
            $price = $product_tax_calculator->addTaxes($price);
        }

        // Eco Tax
        if (($result['ecotax'] || isset($result['attribute_ecotax'])) && $with_ecotax) {
            $ecotax = $result['ecotax'];
            if (isset($result['attribute_ecotax']) && $result['attribute_ecotax'] > 0) {
                $ecotax = $result['attribute_ecotax'];
            }

            if ($id_currency) {
                $ecotax = Tools::convertPrice($ecotax, $id_currency);
            }
            if ($use_tax) {
                if (self::$psEcotaxTaxRulesGroupId === null) {
                    self::$psEcotaxTaxRulesGroupId = (int) Configuration::get('PS_ECOTAX_TAX_RULES_GROUP_ID');
                }
                // reinit the tax manager for ecotax handling
                $tax_manager = TaxManagerFactory::getManager(
                    $address,
                    self::$psEcotaxTaxRulesGroupId
                );
                $ecotax_tax_calculator = $tax_manager->getTaxCalculator();
                $price += $ecotax_tax_calculator->addTaxes($ecotax);
            } else {
                $price += $ecotax;
            }
        }

        // Reduction
        $specific_price_reduction = 0;
        if (($only_reduc || $use_reduc) && $specific_price) {
            if ($specific_price['reduction_type'] == 'amount') {
                $reduction_amount = $specific_price['reduction'];

                if (!$specific_price['id_currency']) {
                    $reduction_amount = Tools::convertPrice($reduction_amount, $id_currency);
                }

                $specific_price_reduction = $reduction_amount;

                // Adjust taxes if required

                if (!$use_tax && $specific_price['reduction_tax']) {
                    $specific_price_reduction = $product_tax_calculator->removeTaxes($specific_price_reduction);
                }
                if ($use_tax && !$specific_price['reduction_tax']) {
                    $specific_price_reduction = $product_tax_calculator->addTaxes($specific_price_reduction);
                }
            } else {
                $specific_price_reduction = $price * $specific_price['reduction'];
            }
        }

        if ($use_reduc) {
            $price -= $specific_price_reduction;
        }

        // Group reduction
        if ($use_group_reduction) {
            $reduction_from_category = GroupReduction::getValueForProduct($id_product, $id_group);
            if ($reduction_from_category !== false) {
                $group_reduction = $price * (float) $reduction_from_category;
            } else { // apply group reduction if there is no group reduction for this category
                $group_reduction = (($reduc = Group::getReductionByIdGroup($id_group)) != 0) ? ($price * $reduc / 100) : 0;
            }

            $price -= $group_reduction;
        }

        if ($id_product == Configuration::get('GIFT_PRODUCT_ID')) {
            $sql = "SELECT SUM(p.price * cp.quantity) as sum_price FROM " . _DB_PREFIX_ . "product p
                INNER JOIN " . _DB_PREFIX_ . "cart_product cp USING(id_product)
                WHERE cp.id_cart = " . $context->cart->id . "
                    AND cp.id_product <> " . $id_product;
            $currentCartTotalPrice = $product_tax_calculator->addTaxes(Db::getInstance()->getValue($sql));

            $expiryDateGift = Configuration::get('GIFT_EXPIRY_DATE');
            if (
                Configuration::get('GIFT_ACTIVE')
                && $currentCartTotalPrice >= Configuration::get('GIFT_THRESHOLD')
                && (empty($expiryDateGift) || (!empty($expiryDateGift) && strtotime($expiryDateGift) >= strtotime(date('Y-m-d'))))
            ) {
                $price = Configuration::get('GIFT_PRICE');
            }
        }

        Hook::exec('actionProductPriceCalculation', [
            'id_shop' => $id_shop,
            'id_product' => $id_product,
            'id_product_attribute' => $id_product_attribute,
            'id_customization' => $id_customization,
            'id_country' => $id_country,
            'id_state' => $id_state,
            'zip_code' => $zipcode,
            'id_currency' => $id_currency,
            'id_group' => $id_group,
            'id_cart' => $id_cart,
            'id_customer' => $id_customer,
            'use_customer_price' => $use_customer_price,
            'quantity' => $quantity,
            'real_quantity' => $real_quantity,
            'use_tax' => $use_tax,
            'decimals' => $decimals,
            'only_reduc' => $only_reduc,
            'use_reduc' => $use_reduc,
            'with_ecotax' => $with_ecotax,
            'specific_price' => &$specific_price,
            'use_group_reduction' => $use_group_reduction,
            'address' => $address,
            'context' => $context,
            'specific_price_reduction' => &$specific_price_reduction,
            'price' => &$price,
        ]);
        if ($only_reduc) {
            return Tools::ps_round($specific_price_reduction, $decimals);
        }

        $price = Tools::ps_round($price, $decimals);

        if ($price < 0) {
            $price = 0;
        }

        self::$_prices[$cache_id] = $price;

        return self::$_prices[$cache_id];
    }
}

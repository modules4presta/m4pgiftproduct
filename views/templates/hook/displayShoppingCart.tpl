{**
 * m4pgiftproduct
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 *}
<div class="m4pgiftproduct-block card card-block">
    <p class="h4 m4pgiftproduct-title">
        {if $m4pgiftproduct_in_cart}
            {l s='Your gift is in the cart' d='Modules.M4pgiftproduct.Shop'}
        {elseif $m4pgiftproduct_missing}
            {l s='Add %amount% more to your order and the gift is yours' d='Modules.M4pgiftproduct.Shop' sprintf=['%amount%' => $m4pgiftproduct_missing]}
        {else}
            {l s='Your order qualifies for a gift' d='Modules.M4pgiftproduct.Shop'}
        {/if}
    </p>

    <div class="m4pgiftproduct-product">
        {if $m4pgiftproduct_miniature}
            {include file='catalog/_partials/miniatures/product.tpl' product=$m4pgiftproduct_product}
        {else}
            <a href="{$m4pgiftproduct_product.url}">
                <img src="{$m4pgiftproduct_product.cover.bySize.home_default.url}" alt="{$m4pgiftproduct_product.name|escape:'html':'UTF-8'}" loading="lazy">
                {$m4pgiftproduct_product.name|escape:'html':'UTF-8'}
            </a>
        {/if}
    </div>

    {if !$m4pgiftproduct_miniature}
        <p class="m4pgiftproduct-price">
            {if $m4pgiftproduct_free}
                {l s='Yours for free' d='Modules.M4pgiftproduct.Shop'}
            {else}
                {l s='Yours for %price%' d='Modules.M4pgiftproduct.Shop' sprintf=['%price%' => $m4pgiftproduct_price]}
            {/if}
        </p>
    {/if}

    {if $m4pgiftproduct_claim_url}
        <a class="btn btn-primary m4pgiftproduct-claim" href="{$m4pgiftproduct_claim_url}">
            {l s='Add the gift to my cart' d='Modules.M4pgiftproduct.Shop'}
        </a>
    {/if}
</div>

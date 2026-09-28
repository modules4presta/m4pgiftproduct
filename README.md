# M4P Gift on Price Threshold for PrestaShop 8 & 9

**Give a product away once the cart passes an amount you choose — the gift appears by itself, and disappears when the customer removes the items that earned it.**

> **Meta description (150 chars):** Add a gift product to the PrestaShop cart once the order passes a set amount. The gift is added and removed automatically. Free MIT module for B2B shops.

---

## Why a gift beats a discount

A percentage off the basket is invisible; a product the customer can hold is not. A gift at a
threshold moves the average order up without touching your price list:

- **Bigger baskets** — a customer three items short of the threshold has a reason to add a fourth
- **Margin stays intact** — the cost of one gift is fixed, unlike a percentage of every order
- **No codes to hand out** — the gift applies itself, so nobody has to remember a coupon
- **Stock you want to move** — pick the product, change it whenever you like

## What the module does

You choose a product, an amount and a price for that product. As soon as the rest of the cart
reaches the amount, the module adds the product to the cart at your price. When the cart falls back
below the amount, the product is removed again. A block on the cart page shows the gift, rendered
with your theme's own product miniature, and tells the customer where they stand:

| Cart | What the block says |
|---|---|
| Below the amount | How much is still missing |
| Above the amount, gift in the cart | That the gift is already there |
| Above the amount, gift deleted | A button to put it back |

### Key features

- **Added and removed automatically** — on every change to the cart, not only at checkout
- **The customer can say no** — delete the gift line and it stays deleted, with a button to change their mind
- **Looks like the rest of the shop** — the block reuses the theme's product miniature, with the gift
  price shown as a promotion: catalogue price struck through and a badge
- **Any price, not only zero** — sell the gift for 1 or 10 instead of giving it away
- **End date** — the promotion stops by itself on the day you set
- **Optional stock check** — no gift once the product runs out
- **One switch** — turning the module off removes the gift from every cart

### How the threshold is counted

The amount is compared against the total of the cart **excluding the gift itself**, tax included and
without shipping, after every discount PrestaShop applies. A cart of 884 with a threshold of 500
gets the gift; removing the item that brought it there removes the gift too.

## Compatibility

| | |
|---|---|
| PrestaShop | 1.7.6 – 9.x |
| PHP | 7.2.5+ |
| Requirements | none |
| Multistore | The gift and the threshold are shared across shops |
| Themes | The cart block needs a theme that renders `displayShoppingCart` (all standard themes do). It reuses the theme's `catalog/_partials/miniatures/product.tpl` and falls back to plain markup when a theme has none |

The module performs no core overrides. It stores six settings and creates one small table,
`m4pgiftproduct_cart`, which remembers per cart whether the gift was added and whether the customer
deleted it. The table is dropped on uninstall.

## Installation

1. Upload and install the module from **Modules → Module Manager**.
2. Open the module configuration.
3. Find the gift product by name or reference, then set the order total that unlocks it and the
   price it is sold at.
4. Switch the module on and save.
5. Fill a cart past the threshold — the gift appears with the rest of the products.

## Configuration options

| Setting | Description |
|---|---|
| **Enabled** | Turns the promotion on. Switching it off removes the gift from every cart. |
| **Gift product** | The product given away, picked with a search on name or reference. |
| **Order total that unlocks the gift** | Tax included, shipping excluded, without the gift itself. |
| **Gift price** | Tax excluded. Leave 0 to give the product away. |
| **Runs until** | The last day of the promotion. Empty means no end date. |
| **Only while in stock** | Stops the promotion when the gift product runs out. |

## Frequently asked questions

**Can the customer refuse the gift?**
Yes. Deleting the gift line removes it, and the module does not put it back by itself. The block on
the cart page then shows a button, so a customer who changes their mind can take the gift again.

**Can the customer order more than one gift?**
No. The module keeps exactly one in the cart while the threshold is met, so pick a product you do
not sell separately — a sample or a gadget rather than a regular item.

**What happens when I change the gift product?**
New carts get the new product. A gift already sitting in an old cart stays there and is charged at
its normal price, because nothing marks it as a gift once the setting has changed. Switch the module
off and on again if you want those carts cleared.

**Is the gift price visible before the threshold is reached?**
Yes. The block shows the product from the start, priced the way a promotion is priced in your theme:
the catalogue price struck through, the gift price next to it and a badge. Nothing is written to the
catalogue, so the product keeps its normal price everywhere else in the shop.

**Does it work with vouchers and catalogue price rules?**
Yes. The threshold is measured on the cart total after every rule PrestaShop applies, and the gift
price replaces whatever the catalogue says for that product.

**What happens when the gift runs out of stock mid-promotion?**
PrestaShop refuses to put it in the cart and the module leaves the cart alone. Switch on **Only
while in stock** to have the offer stop by itself instead.

**What happens to the settings when I uninstall the module?**
All six are deleted along with the table. Carts keep whatever they held, at normal prices.

---

**Keywords:** PrestaShop gift product, free gift threshold, cart promotion, minimum order gift, B2B
promotion, gift with purchase.

## License

MIT — see [LICENSE](LICENSE). Free to use commercially, fork and modify; keep the copyright notice.

## Contributing

Bug reports and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). For security
issues, follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

---

Built by [Nice Code](https://nice-code.com/pl/oferta/moduly-prestashop) — we build and maintain PrestaShop stores.

© Nice Code sp. z o.o. (Modules4Presta) — released under the MIT license.

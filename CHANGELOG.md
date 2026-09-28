# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-09-28

### Removed

- The `Product` core override. It was a copy of `Product::priceCalculation` from PrestaShop 1.7 with
  sixteen lines added, so it replaced the whole method with an outdated one and clashed with every
  other module overriding the same class.

### Changed

- The gift price now comes from the `actionProductPriceCalculation` hook.
- The cart is updated on `actionCartSave` instead of a display hook that ran once per cart line.
- Settings are prefixed with `M4PGIFTPRODUCT_` so they cannot clash with another module.
- Released under the MIT license, with English and Polish catalogues and the standard documentation.

### Added

- The gift is removed when the cart drops below the threshold, when the promotion ends, when the
  product runs out of stock and when the module is switched off.
- A customer who deletes the gift is not offered it again while that cart stays above the threshold.
  Before, the line was put straight back and could not be removed at all.
- One table, `m4pgiftproduct_cart`, holding that decision per cart.
- A search field for the gift product, so the ID no longer has to be typed by hand.
- The cart block is rendered with the theme's own product miniature and states how much is missing,
  that the gift is in the cart, or offers a button to take it.
- The gift price is presented as a promotion — catalogue price struck through, gift price and a
  badge — without writing a specific price to the catalogue.
- The gift product and the end date are validated before they are saved.

### Fixed

- `Tools::displayPrice()`, removed in PrestaShop 9, made the cart page fail.
- The threshold was counted twice in two different ways, so the gift could be added to the cart
  without being sold at the gift price.
- Product images were built from a hardcoded path instead of the link builder.

# ScandiPWA CompareGraphQl

Fork of [scandipwa/compare-graphql](https://github.com/scandipwa/compare-graphql) 1.0.9, maintained by Selveq for Magento 2.4.9 and PHP 8.3. Module name and namespace are unchanged, and the package replaces `scandipwa/compare-graphql` at every version, so it installs as a drop-in replacement. Selveq is not affiliated with or endorsed by Scandiweb.

## What it does

- Gives every compared product a `thumbnail`, a `small_image` and an `image`, each a `path` and a storefront-sized `url` built under storefront emulation so the theme's own image sizes apply.
- Answers `stock_status` for every compared product, so the comparison table can mark what is out of stock.
- Drops from the comparison table every attribute that no compared product has a value for.
- Shows a dash for a value a product does not have, so the rows that remain line up.

## Install

```sh
composer require selveq/compare-graphql
bin/magento setup:upgrade
```

## License

[OSL-3.0](LICENSE), the license of the original work. Scandiweb's copyright notices are kept in every file, and each file Selveq changed carries a `Modifications © Selveq` notice.

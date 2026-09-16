# Changelog

## 2.0.0

Forked from `scandipwa/compare-graphql` 1.0.9. Module name and namespace are unchanged, and the package replaces `scandipwa/compare-graphql` at every version, so it installs as a drop-in replacement.

- Magento 2.4.9 and PHP 8.3 support.
- A failure while building an image URL no longer leaves the request running under storefront emulation.
- Stock status is read from the product's own stock item, and a product without one raises an error naming it instead of a PHP warning.
- `thumbnail`, `small_image` and `image` each decide between their own file and the placeholder, instead of all three following the thumbnail.
- A compare list loads its comparable attributes once and emulates the storefront once per product, instead of once per attribute lookup and once per image.
- The attribute filter applies core's own has-a-value rule rather than matching the text `N/A`, so a wording change in core cannot silently switch it off.

<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.
<!--- END HEADER -->

## [2.0.9](https://github.com/liquiddesign/pages/compare/v2.0.7...v2.0.9) (2026-08-27)

### Bug Fixes

* Scope Router page cache by selected shop and mutation

  `Router::constructUrl()` cached the resolved page under a key built only from the page type and its
  serialized parameters, while the cached value depends on the selected shop and the mutation as well —
  every mutation has its own `url_<mutation>` and every shop its own set of pages. On a multi-shop
  installation (one application, several domains, one `tempDir`) whichever shop warmed the cache first
  decided the URLs served to all the others, for up to a day. The persistent cache key now includes the
  selected shop and the language; the in-request prefetch map keeps its own index. `Router::match()`
  likewise keys its per-request cache by shop.


---

## [2.0.7](https://github.com/liquiddesign/pages/compare/v2.0.6...v2.0.7) (2026-01-12)


---

## [2.0.6](https://github.com/liquiddesign/pages/compare/v2.0.5...v2.0.6) (2025-10-20)

### Bug Fixes

* Handle null page in Router.php ([88a75f](https://github.com/liquiddesign/pages/commit/88a75f7891febf26e6e610810aff7e138668958f))


---

## [2.0.5](https://github.com/liquiddesign/pages/compare/v2.0.4...v2.0.5) (2025-02-13)

### Features

* Add cache toggle and improve code style ([bc6d5e](https://github.com/liquiddesign/pages/commit/bc6d5e04e661612ad57e52e51b6c2532baee714d))
* Cache ([2a004d](https://github.com/liquiddesign/pages/commit/2a004d050f765301196c31a418c42a3ae03c2a69))


---


[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate/?business=SAT23GPU7F6AS&no_recurring=1&currency_code=EUR)
# CB module & content

One Joomla package (`pkg_cbmodulecontent`) for Community Builder with:

| Extension | Element | What it does |
|---|---|---|
| Community Builder list module | `mod_cblistmodule` | Shows the users of a CB list in a module, with your own template |
| Content - cbprofile | `plg_content_cbprofile` | Shows CB profiles in articles with `{cbprofile ...}` |
| CB list helper | `lib_cblisthelper` | Shared code: builds the user query of a CB list the same way CB does |

**Documentation:** settings, tags, examples and upgrading are in the [wiki](https://github.com/Tazzios/cbmodulecontent/wiki).

Both the module and the plugin load the shared helper from
`libraries/cblisthelper/cblisthelper.php`, so there is one copy of that code.

## Upgrading from the separate extensions
The module and plugin keep their element names, so installing the package over
`mod_cblistmodule` 3.6 and `plg_content_cbprofile` 1.6 upgrades them in place.
Existing module instances, plugin settings and own `tmpl` layouts are kept.

## Requirements
Joomla 5 or 6 and Community Builder.

## Development
Build the package (needs Node.js 18+, no npm packages):
```
node tools/release.js build
```
creates `dist/pkg_cbmodulecontent-<version>.zip`, ready to install in Joomla.

Run the tests (needs PHP 8.1+ with pdo_sqlite, Composer and Node.js):
```
composer update
vendor/bin/phpunit
node --test tests/js/*.test.js
```
The PHP tests run the library, the module and the plugin on a small test site in SQLite, with
stand-ins for the Joomla CMS classes (`tests/Stubs`). GitHub Actions runs them, plus a PHP syntax
check and a manifest check, on every push to main and every pull request.

## Release
The package, library, module and plugin share one version number. Write every change under
`Unreleased` in changelog.md. If there is no `Unreleased` section, add one on top. Releasing takes
two steps, both started by hand from the Actions tab:

1. **Release** (Actions, Release, Run workflow) with a version, for example 5.0.0. It sets the version
   in the four manifests, turns the Unreleased section into the release notes, runs the tests, commits
   this to main, tags `v<version>`, builds the package and publishes a GitHub release with it. Commit
   and tag are public, also with the option draft. Existing installs are not offered the update yet.
2. **Offer update to existing installs**, after you have tested the zip from the release. It adds the
   version to `updates.xml`, its changes to `changelog.xml` (the Changelog button in Joomla's update
   list) and commits that to main. Running it is the moment every site with the package sees the
   update, so there is no approval step: test the zip first.

Both workflows must be allowed to push to main.

The same steps work locally:
```
node tools/release.js prepare 5.0.0
node tools/release.js build
node tools/release.js notes 5.0.0
node tools/release.js update-xml 5.0.0 --sha256 <hash of the zip>
```

### Separate installs
Sites with the separate module (up to 3.6.0) or plugin (up to 1.6.0) look for updates in the old
repositories, and Joomla keeps those update sites after the package is installed. To offer them the
package, once, by hand, after the first release is published and tested:

- [cblistmodule](https://github.com/Tazzios/cblistmodule) `updates.xml`: set `<version>` to the version
  of that release and `<downloadurl>` to its `pkg_cbmodulecontent-<version>.zip`
- [cbprofilecontent](https://github.com/Tazzios/cbprofilecontent) `updates.xml`: the same

The version must be exactly the version the module and plugin have in that package. With a higher
number Joomla keeps offering the update. Later releases do not need this: once a site has the package,
it gets updates from here. Do not rename or remove these two files, and keep that release.

## Repository layout
```
pkg_cbmodulecontent.xml   package manifest
updates.xml               update server for the package
changelog.xml             changes per version, shown by Joomla
tools/release.js          build and release script
tests/                    PHPUnit and Node.js tests
src/lib_cblisthelper/     shared library
src/mod_cblistmodule/     module
src/plg_content_cbprofile/ content plugin
docs/                     changelogs of the former repositories and images used by the wiki
```
The git history of [cblistmodule](https://github.com/Tazzios/cblistmodule) and
[cbprofilecontent](https://github.com/Tazzios/cbprofilecontent) is kept in this repository.

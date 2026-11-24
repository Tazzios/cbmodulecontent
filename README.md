[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate/?business=SAT23GPU7F6AS&no_recurring=1&currency_code=EUR)
# CB module & content

One Joomla package (`pkg_cbmodulecontent`) for Community Builder with:

| Extension | Element | What it does | Docs |
|---|---|---|---|
| Community Builder list module | `mod_cblistmodule` | Shows the users of a CB list in a module, with your own template | [docs/mod_cblistmodule.md](docs/mod_cblistmodule.md) |
| Content - cbprofile | `plg_content_cbprofile` | Shows CB profiles in articles with `{cbprofile ...}` | [docs/plg_content_cbprofile.md](docs/plg_content_cbprofile.md) |
| CB list helper | `lib_cblisthelper` | Shared code: builds the user query of a CB list the same way CB does | |

Both the module and the plugin load the shared helper from
`libraries/cblisthelper/cblisthelper.php`, so there is one copy of that code.

## Upgrading from the separate extensions
The module and plugin keep their element names, so installing the package over
`mod_cblistmodule` 3.6 and `plg_content_cbprofile` 1.6 upgrades them in place.
Existing module instances, plugin settings and own `tmpl` layouts are kept.

## Requirements
Joomla 5 or 6 and Community Builder.

## Building
```
./build.sh
```
creates `build/pkg_cbmodulecontent-<version>.zip`, ready to install in Joomla.

## Repository layout
```
pkg_cbmodulecontent.xml   package manifest
updates.xml               update server for the package
src/lib_cblisthelper/     shared library
src/mod_cblistmodule/     module
src/plg_content_cbprofile/ content plugin
docs/                     documentation and changelogs of the former repositories
```
The git history of [cblistmodule](https://github.com/Tazzios/cblistmodule) and
[cbprofilecontent](https://github.com/Tazzios/cbprofilecontent) is kept in this repository.

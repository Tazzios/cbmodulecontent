Unreleased  
New: package "CB module & content" (pkg_cbmodulecontent) with the module, the content plugin and the library they share, all with the same version number  
New: module output is processed by content plugins, so tags like {cbprofile} work in the module  
Fixed: CB list filter "contains" (LIKE) removed the other filters of the list  
Fixed: CB list filter "IN" with text values found no users  
Improved: cblisthelper.php is shared as a library instead of a copy in the module and the plugin  
Changed: updates come from the package update server, the module and plugin no longer have their own  

Older history: [module](docs/changelog_mod_cblistmodule.md), [plugin](docs/changelog_plg_content_cbprofile.md)

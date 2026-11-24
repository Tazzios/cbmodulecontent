Unreleased  
New: package "CB module & content" (pkg_cbmodulecontent) with the module, the content plugin and the library they share, all with the same version number  
New: module output is processed by content plugins, so tags like {cbprofile} work in the module  
Security: user data in the module and plugin output can not contain tags for content plugins  
Security: the values of a {cbprofile} tag are escaped in the database query, orderby only accepts existing columns and order only asc, desc or random  
Security: imagewidth, imageheight, float and layout of a {cbprofile} tag are checked before they are used  
Fixed: CB list filter "contains" (LIKE) removed the other filters of the list  
Fixed: CB list filter "IN" with text values found no users  
Fixed: {cbprofile} with a CB list without users gave a database error, or showed the users of the tag before it  
Fixed: avatars that are not approved were shown in all layouts  
Fixed: layout text-default had a double slash in the avatar url, and a broken image for users without an avatar  
Fixed: layout div-avatar used a variable that does not exist (PHP warning)  
Fixed: a closing div too much after "No userprofiles found"  
Fixed: special characters in a {cbprofile} tag (like + in an email address) could make the whole article text empty  
Improved: cblisthelper.php is shared as a library instead of a copy in the module and the plugin  
Changed: updates come from the package update server, the module and plugin no longer have their own  
Older history: [module](docs/changelog_mod_cblistmodule.md), [plugin](docs/changelog_plg_content_cbprofile.md)

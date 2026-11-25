Unreleased  
New: English and Dutch translations of the module, the plugin, the library and the package  
New: the module has layouts (tmpl), also as override in the template  
Changed: module, plugin and library use the structure of Joomla 5 and 6 (namespaces, service providers, events) and no longer need the Backward Compatibility plugin  
Changed: the library has classes (Tazzios\Library\CbListHelper) instead of global functions  
Improved: fewer database queries in the module  
Changed: the package checks for Joomla 5.0 and PHP 8.1 and removes the files of older versions  

v5.0.1 November 2026  
New: package "CB module & content" (pkg_cbmodulecontent) with the module, the content plugin and the library they share, all with the same version number  
New: module output is processed by content plugins, so tags like {cbprofile} work in the module  
New: tags [profile_url] and [site_url] in the module template and rules  
New: number of users 0 shows all users of the list  
Security: user data is escaped in the module and the plugin, HTML in user data is shown as text. Only a CB editor field keeps its HTML, without scripts and dangerous links (based on fixes by [Magnus Hasselquist](https://github.com/magnushasselquist/))  
Security: [tags] in user data are no longer replaced, only in the template and the rules (based on fixes by Magnus Hasselquist)  
Security: the debug query is only shown to Super Users (based on fixes by Magnus Hasselquist)  
Security: user data in the module and plugin output can not contain tags for content plugins  
Security: the values of a {cbprofile} tag are escaped in the database query, orderby only accepts existing columns and order only asc, desc or random  
Security: imagewidth, imageheight, float and layout of a {cbprofile} tag are checked before they are used  
Fixed: CB list filter "contains" (LIKE) removed the other filters of the list  
Fixed: CB list filter "IN" with text values found no users  
Fixed: an apostrophe in the value of a CB list filter gave a database error (based on fixes by Magnus Hasselquist)  
Fixed: a CB list without user groups, or a filter or order on the field id, gave a database error (based on fixes by Magnus Hasselquist)  
Fixed: the filter "Is not one of" gave a database error (based on fixes by Magnus Hasselquist)  
Fixed: the filters "Is empty" and "Is not empty" gave a database error  
Fixed: % and _ in the value of a "contains" filter are normal characters now (based on fixes by Magnus Hasselquist)  
Fixed: a select field could show the label of an other field with the same value (based on fixes by Magnus Hasselquist)  
Changed: a CB list with an unknown field or operator in a filter shows no users  
Fixed: {cbprofile} with a CB list without users gave a database error, or showed the users of the tag before it  
Fixed: avatars that are not approved were shown in all layouts  
Fixed: layout text-default had a double slash in the avatar url, and a broken image for users without an avatar  
Fixed: layout div-avatar used a variable that does not exist (PHP warning)  
Fixed: a closing div too much after "No userprofiles found"  
Fixed: a username with a space did not work, use quotes: {cbprofile username="Jan Jansen"}  
Fixed: special characters in a {cbprofile} tag (like + in an email address) could make the whole article text empty  
Improved: cblisthelper.php is shared as a library instead of a copy in the module and the plugin  
Changed: updates come from the package update server, the module and plugin no longer have their own  

Older history: [module](docs/changelog_mod_cblistmodule.md), [plugin](docs/changelog_plg_content_cbprofile.md)

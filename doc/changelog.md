# Changelog

## v2.0.0
Ibexa 4.6 compatibility. Include all features of v1.1.4 except netgen tags support.

## v1.1.4
Added possibility to use _sortField and _sortOrder when creating content to order subitems.

## v1.1.3
Added possibility to use json in ezurl to add a text.

## v1.1.2
Fix validation error, when using fixtures in ezbinaryfile.

## v1.1.1
Make image urls more predictable. 
This is done by incrementing the attribute id by 100 after each content creation.
So adding field to a previously created content will not shift the id of the image.

## v1.1.0
* Resolved dependencies by introducing a State Service with lastContent reference.
* Added TestFilePathNormalizer for image testing
* Added AdminContext for admin ui login
* Added TrashContext
* Added ObjectstateContext
* Added SolrContext

## v1.0.4
* Bugfix for `the page contains a(n) :blockType block in zone :zoneName`

## v1.0.3
* Fixed file upload paths
* Allow specifying IDs for  `the content object :id is hidden` and `the location :id is hidden`

## v1.0.2
* Fix error in `there must not be a(n) :contentType content object`

## v1.0.1
* Let overriden ContentContext work together with LandingpageContext. 

## v1.0.0
* Initial release
# Introduction

NOTE: this is currently alpha, use at your own risk... this has the potential to delete every template from your system if there is a bug (I did that in testing a while ago)

XenForo Developer Tools is a set of tools I created for myself to remove a lot of the tedious things that come with developing with XenForo. It is designed to work in conjuction with XenForo-CLI (github.com/Naatan/XenForo-CLI/). 

# Project Stats

Current features are subject to heavy change and could be plain old broke. These tools are designed to be used by developers on a development enviroment. When things are needed they will be added and pull requests will be considered.

# Dependencies

* Pear xattr package (http://pecl.php.net/package/xattr)
* XenForo-CLI (github.com/Naatan/XenForo-CLI/) - not currently required but I expect it will be in the near future

# Installation

You have 2 options here. If you use XenForo-CLI simply go to your root directory and run "xf addon import https://github.com/Robbo-/XenForo-Developer-Tools.git" and it will clone, symlink, install and rebuild caches for you. If you don't use XenForo-CLI or it doesn't work for you (currently it is linux only and also in alpha stages) then you can simply clone the repository and install like any other add-on. 

Note: you need to do the changes that are in admin.new and index.new to your admin.php and index.php files. 

# Current Features

Currently the only feature of these tools is templates on the file system. Other will be created over time and are outlined below and in the TODO file.

## File System Templates

Supports everything you would expect. Creating, deleting, updating contents, renaming and even changing the addon_id. After installation all templates will be written into rootDir/templates. Currently only admin and master templates are supported. So you will see files like templates/admin/XenForo/public.css. Each addon has it's own folder. So templates for this add-on will be located in templates/admin/devTools and templates/master/devTools. If you move a file from one add-on folder to another you will be changing the addon that template is with (like changing the dropbox from the ACP).

How it works? Every page load it will scan the templates for new templates, renamed template or deleted templates. Then whenever a template is requested (usually each page load will request only a few) the last modified date is checked against a last updated time and the database is updated accordingly. From my tests this is all done very fast, especially compared to webdav. 

Some things don't work or could corrupt the whole system, I recommend you reading the source to understand how everything works at least a little. One thing to note is that when a file is created on the file system it is updated with extended attributes to add the template_id. What this means is if you delete a file on the file system but then save it again (from having it open elsewhere for example) it most likely won't have the template_id stored anymore and if no requests have been to a webpage in that time it will cause that file on the file system to simply not work with anything.

# Upcoming Features

These tools are planned to do a lot more than what it does now... see the TODO file for a little insight into what I plan to add.

Updated the disabled link manager functionality in WordPress, originally designed as a blogroll, to act as a bookmarking system.

## Description

This plugin recreates the old link manager in WordPress as an extensible booking system. 

The link manager functionality in WordPress was disabled in WordPress 3.5 in 2012 and has seen no changes since. This plugin adds a metadata table to 
expand the fields, as well as some of the improvements made to other objects in the WordPress system. This includes an object class, customizable 
list table, tags, a Query class, a REST API endpoint, etc.

You can now set the default visibility if you want things private by default.

## Why not just create something new?

I wanted to self-host my bookmarks, instead of using a third-party service, but delayed for a long time because nothing out there quite worked for me.
Then one day the paid service I was using went down all day. It eventually came back up, but nothing from the maintainer...not even a post on the site about it.

So I decided to write my own. Other than that challenge, this turns a long abandoned WordPress feature into something useful. You can create a blogroll using a link category.
You can store your bookmarks. You can use the added Read Later property to make it work like Read Later services.

## What additional properties are added?

With Link Metadata added, you can add whatever new properties you want. Built into the plugin, in addition

## Backward Compatibility Breaks

The updated field in the link object was previously used for a different purpose, which was deprecated in 2010. This plugin repurposes it as a simple
last modified field. While this is technically a backward compatibility breakage, the field was entirely unused.

In the traditional link manager, the rss field is used to reflect the rss url of the link. However, as this is not strictly used as a blogroll any longer, there is a slight 
change here. If the bookmark is a feed, then this field should not be empty or it will be assumed to reflect a single post. However, if the URL is identical to the URL of the bookmark, it will be assumed to be a microformats h-feed, otherwise a separate feed file.

The image address field is renamed as the Featured Image field. The original recommendation was for a small favicon, but this allows for any size featured image to account for single and feed posts.

The Notes field is now rendered in the UI as a rich text field.


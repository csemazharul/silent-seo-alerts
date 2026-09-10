WordPress.org listing assets
============================

These files do NOT go in the plugin zip. After the plugin is approved you get
an SVN repository with three top-level folders - trunk/, tags/ and assets/ -
and everything here belongs in assets/.

Screenshots
-----------
Save the five captures as PNG, in this order. The numbers must match the
captions in readme.txt, because that is the only thing tying an image to its
description.

  screenshot-1.png   Dashboard
  screenshot-2.png   Monitored Pages
  screenshot-3.png   Flight Log
  screenshot-4.png   Site-wide
  screenshot-5.png   Settings

Roughly 1200x900 or wider; they are shown scaled down, so legible text matters
more than exact size.

Optional, but they make the listing look finished
-------------------------------------------------
  banner-772x250.png    top of the plugin page
  banner-1544x500.png   same, for high-DPI screens
  icon-128x128.png      search results and the plugin card
  icon-256x256.png      same, for high-DPI screens

Committing them
---------------
  svn co https://plugins.svn.wordpress.org/silent-seo-alerts
  cd silent-seo-alerts
  cp /path/to/screenshot-*.png assets/
  svn add assets/*
  svn ci -m "Add listing screenshots"

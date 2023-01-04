#!/bin/bash
#
# @package   buildfiles
# @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
# @license   GNU General Public License version 3, or later
#

# Adds index.html files to every directory.
#
# That was a bad requirement of the Joomla! Extensions Directory back in 2010-2011

for d in `find component -type d | grep -v '.svn'`; do cp -f index.html $d; done
for d in `find modules -type d | grep -v '.svn'`; do cp -f index.html $d; done
for d in `find plugins -type d | grep -v '.svn'`; do cp -f index.html $d; done

for d in `find koowa/ -type d | grep -v '.svn'`; do cp -f index.html $d; done

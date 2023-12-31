#!/bin/bash
#
# @package   buildfiles
# @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
# @license   GNU General Public License version 3, or later
#

# The opposite of add-index-html.sh. It removes all those ugly index.html files from the repository folders.
#
# This was necessary after the Joomla Extensions Directory retracted its previous, ill-thought rule.

find component -type f -name index.html -delete
find modules -type f -name index.html -delete
find plugins -type f -name index.html -delete
find koowa -type f -name index.html -delete
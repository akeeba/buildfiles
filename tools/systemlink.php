<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Akeeba Build Tools - System linker
 */

$options = getopt('s:d:t:', array('source:', 'destination:', 'type:'));

$source = $options['s'] ? $options['s'] : $options['source'];
$dest   = $options['d'] ? $options['d'] : $options['destination'];
$type   = $options['t'] ? $options['t'] : $options['type'];

// Sanity checks
if(!$source || !$dest || !$type)
{
    echo 'You must supply the source, destination and type arguments';
    return;
}

if(!in_array($type, array('link', 'symlink')))
{
    echo 'Unknown link type: '.$type;
    return;
}

\Akeeba\LinkLibrary\LinkHelper::makeLink($source, $dest, $type);
<?php

require_once 'includes/common.inc.php';
$keyPath=$_GET['key'];


// Get keys from Redis according to server-config.
$keys = $redis->keys($keyPath."*");

sort($keys);
$namespaces = array(); // Array to hold our top namespaces.

// Build an array of nested arrays containing all our namespaces and containing keys.
foreach ($keys as $key) {
  // Ignore keys that are to long (Redis supports keys that can be way to long to put in an url).
  if (strlen($key) > $config['maxkeylen']) {
    continue;
  }

  $key = explode($config['seperator'], $key);

  // $d will be a reference to the current namespace.
  $d = &$namespaces;

  // We loop though all the namespaces for this key creating the array for each.
  // Each time updating $d to be a reference to the last namespace so we can create the next one in it.
  for ($i = 0; $i < (count($key) - 1); ++$i) {
    if (!isset($d[$key[$i]])) {
      $d[$key[$i]] = array();
    }

    $d = &$d[$key[$i]];
  }

  // Nodes containing an item named __phpredisadmin__ are also a key, not just a directory.
  // This means that creating an actual key named __phpredisadmin__ will make this bug.
  $d[$key[count($key) - 1]] = array('__phpredisadmin__' => true);

  // Unset $d so we don't accidentally overwrite it somewhere else.
  unset($d);
}


// Recursive function used to print the namespaces.
function print_namespace($item, $name, $fullkey, $islast) {
  global $config, $server, $redis,$depth,$maxDepth,$keyPath;
	$depth=count(explode($config['seperator'], $fullkey));
	
  // Is this also a key and not just a namespace?
  if (isset($item['__phpredisadmin__'])) {
    // Unset it so we won't loop over it when printing this namespace.
    unset($item['__phpredisadmin__']);

    $type  = $redis->type($fullkey);
    $class = array();
    $len   = false;

    if (isset($_GET['key']) && ($fullkey == $_GET['key'])) {
      $class[] = 'current';
    }
    if ($islast) {
      $class[] = 'last';
    }

    // Get the number of items in the key.
    if (!isset($config['faster']) || !$config['faster']) {
      switch ($type) {
        case 'hash':
          $len = $redis->hLen($fullkey);
          break;

        case 'list':
          $len = $redis->lLen($fullkey);
          break;

        case 'set':
          // This is currently the only way to do this, this can be slow since we need to retrieve all keys
          $len = count($redis->sMembers($fullkey));
          break;

        case 'zset':
          // This is currently the only way to do this, this can be slow since we need to retrieve all keys
          $len = count($redis->zRange($fullkey, 0, -1));
          break;
      }
    }


    ?>
    <li<?php echo empty($class) ? '' : ' class="'.implode(' ', $class).'"'?>>
    <a href="?view&amp;s=<?php echo $server['id']?>&amp;key=<?php echo urlencode($fullkey)?>"><?php echo format_html($name)?><?php if ($len !== false) { ?><span class="info">(<?php echo $len?>)</span><?php } ?></a>
    </li>
    <?php
  }

  // Does this namespace also contain subkeys?
  if (count($item) > 0) {
    if($fullkey!=$keyPath){
    ?>
    <li class="folder<?php echo empty($fullkey) ? '' : ' collapsed'?><?php echo $islast ? ' last' : ''?>" title="<?php echo urlencode($fullkey)?>">
    <?php
  	}
    ?>
    <div class="icon"><?php echo format_html($name)?>&nbsp;<span class="info">(<?php echo count($item)?>)</span>
    <?php if (!empty($fullkey)) { ?><a href="delete.php?s=<?php echo $server['id']?>&amp;tree=<?php echo urlencode($fullkey)?>:" class="deltree"><img src="images/delete.png" width="10" height="10" title="Delete tree" alt="[X]"></a><?php } ?>
    </div><ul>
    <?php
		if($depth<$maxDepth){
    $l = count($item);

    foreach ($item as $childname => $childitem) {
      // $fullkey will be empty on the first call.
      if (empty($fullkey)) {
        $childfullkey = $childname;
      } else {
        $childfullkey = $fullkey.$config['seperator'].$childname;
      }

      print_namespace($childitem, $childname, $childfullkey, (--$l == 0));
    }
  }
    ?>
    </ul><?php
    if($fullkey!=$keyPath){
    ?>
    </li>
    <?php
  	}
  }
}

$ks = explode($config['seperator'], $keyPath);
$keyName="";
foreach($ks as $k){
	$keyName=$k;
	$namespaces=$namespaces[$keyName];
}
$maxDepth=count($ks)+1;
$depth=1;
print_namespace($namespaces, $keyName, $keyPath, empty($namespaces));
?>

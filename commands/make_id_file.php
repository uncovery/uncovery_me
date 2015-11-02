<?php
require_once('/home/minecraft/server/bin/core_include.php');

// this here creates a new items array file
$search_arr = umc_item_data_get_namelist();
if (($handle = fopen("/home/minecraft/server/bukkit/plugins/Essentials/items.csv", "r")) !== FALSE) {
    while (($items = fgetcsv($handle, 10000, ",")) !== FALSE) {
        $firstletter = substr($items[0], 0, 1);
        if (count($items) == 3 && $firstletter !== '#' && !isset($search_arr[$items[0]])) {
            $item = umc_goods_get_text($items[1], $items[2]);
            $search_arr[$items[0]] = array('item_name' => $item['item_name'], 'type' => $item['type']);
        }
    }
    umc_array2file($search_arr, 'ITEM_SEARCH', '/home/minecraft/server/bin/includes/item_search.inc.php');
} else {
    die("Could not read items file!");
}

 // umc_get_id_icon();

$add_array = array(
    'spawn_egg' => array('id' => 383, 'type' => 0),
    'dry_shrub' => array('id' => 31, 'type' => 0),
    'green_shrub' => array('id' => 31, 'type' => 3),
    'Double_Stone_Slab' => array('id' => 43, 'type' => 0),
    'Double Sandstone Slab' => array('id' => 43, 'type' => 1),
    'Double (Stone) Wooden Slab' => array('id' => 43, 'type' => 2),
    'Double Cobblestone Slab' => array('id' => 43, 'type' => 3),
    'Double Bricks Slab' => array('id' => 43, 'type' => 4),
    'Double Stone Brick Slab' => array('id' => 43, 'type' => 5),
    'Double Nether Brick Slab' => array('id' => 43, 'type' => 6),
    'Double Quartz Slab' => array('id' => 43, 'type' => 7),
    'Full Stone Slab' => array('id' => 43, 'type' => 8),
    'Full Sandstone Slab' => array('id' => 43, 'type' => 9),
    'Pillar Quartz Block (north-south)' => array('id' => 155, 'type' => 4),
    'Pillar Quartz Block (east-west)' => array('id' => 155, 'type' => 5),
);


// TODO: Check if this is deprecated
function umc_get_id_icon() {
    global $UMC_DOMAIN;
    require_once('/home/minecraft/server/bin/items.php');
    $items = array();
    foreach ($ITEM_SEARCH as $name => $data) {
        $items[$data['id']][$data['type']] = $name;
    }
    ksort($items);

    // these are the items that are in items.php but NOT in the image file
    $invalids = array(
        '97-3', '97-4', '97-5',
        '125-4', '125-5',
        '126-4', '126-5',
        '163-0',
        '164-0',
        '170-0',
        '358-1','358-2','358-3','358-4','358-5','358-6','358-7','358-8','358-9','358-10','358-11','358-12','358-13','358-14','358-15',
        '373-6','373-7','373-11','373-13','373-14','373-15','373-16','373-22','373-23','373-27','373-29','373-30','373-31','373-32','373-38',
        '373-39','373-43','373-45','373-46','373-47','373-48','373-54','373-55','373-59','373-61','373-62','373-63','373-64','373-8192','373-8193',
        '373-8194','373-8195','373-8196','373-8197','373-8198','373-8200','373-8201','373-8202','373-8204','373-8205','373-8206','373-8225','373-8226',
        '373-8228','373-8229','373-8233','373-8236','373-8257','373-8258','373-8259','373-8260','373-8262','373-8264','373-8265','373-8266','373-8269',
        '373-8270','373-8289','373-8290','373-8292','373-8297','373-16384','373-16385','373-16386','373-16387','373-16388','373-16389','373-16390',
        '373-16391','373-16392','373-16393','373-16394','373-16395','373-16396','373-16397','373-16398','373-16399','373-16400','373-16406','373-16407',
        '373-16411','373-16413','373-16414','373-16415','373-16416','373-16417','373-16418','373-16420','373-16421','373-16422','373-16423','373-16425',
        '373-16427','373-16428','373-16429','373-16430','373-16431','373-16432','373-16438','373-16439','373-16443','373-16445','373-16446','373-16447',
        '373-16448','373-16449','373-16450','373-16451','373-16452','373-16454','373-16456','373-16457','373-16458','373-16461','373-16462','373-16481',
        '373-16482','373-16484','373-16489',
        '383-0','383-53','383-97','383-99',
    );

    $div = 1.5;
    $frame_top = 44 / $div;
    $frame_left = 24 / $div;
    $i = 0;
    $col = 1;
    $row = 0;
    $top = $frame_top;
    $width = 30 / $div;
    $height = 32 / $div;
    $size = 800 / $div;

    $text = ".item {
    display: block;
    cursor: default;
    position:relative;
    float:left;
    background-repeat:no-repeat;
    width: {$width}px;
    height: {$height}px;
    background: url('$UMC_DOMAIN/websend/icons_transparent.png');
    background-size: {$size}px;
}
";

    foreach ($items as $id => $data) {
        ksort($data);
        if ($id == 256) {
            $frame_top = 44 / $div;
            $top = $frame_top;
            $frame_left = 384 / $div;
            $col = 1;
            $row = 0;
        }
        foreach ($data as $dmg => $name) {
            if (in_array("$id-$dmg", $invalids)) {
                continue;
            }
            $i++;
            if ($col == 10) {
                $col = 1;
                $i = 1;
                $row ++;
                $top = ((51 / $div) * $row) + $frame_top;
            }
            $side = $frame_left + ($col * (36 / $div));

            $text .= ".item-$id-$dmg {background-position: -{$side}px -{$top}px;}\n";
            $col++;
        }
    }
    file_put_contents('/home/minecraft/public_html/websend/items.css', $text);
}
?>
<?php
/*
 * This file is part of Uncovery Minecraft.
 * Copyright (C) 2015 uncovery.me
 *
 * Uncovery Minecraft is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * This file attempts to create a central config file. There are still MANY
 * parts of the code where information is hard-coded such as paths, userlevels etc.
 */
global $UMC_SETTING, $UMC_PATH_MC;

$UMC_PATH_MC = "/home/minecraft";
$UMC_DOMAIN = "https://uncovery.me";

ini_set('display_errors', 1);

$UMC_SETTING = array(
    'path' => array(
        'server' => $UMC_PATH_MC . '/server',
        'bin' => $UMC_PATH_MC . "/server/bin",
        'data' => $UMC_PATH_MC . "/server/data",
        'html' => $UMC_PATH_MC . "/public_html",
        'wordpress' => $UMC_PATH_MC . "/public_html",
        'bukkit' => $UMC_PATH_MC . "/server/bukkit",
        'url' =>  $UMC_DOMAIN,
        'worlds_mint' => $UMC_PATH_MC . '/server/worlds_mint',
        'worlds_save' => $UMC_PATH_MC . '/server/worlds_save',
    ),
    'url' => "$UMC_DOMAIN/admin/index.php",
    // this is needed for unc_serial_curl
    'ssl_cert' => "/home/includes/certificates/cacert.pem",
    'whitelist_file' => $UMC_PATH_MC . '/server/data/whitelist.json',
    'markers_file' => $UMC_PATH_MC . '/server/data/markers.json',
    'world_folder' => $UMC_PATH_MC . '/server/bukkit/city/',
    'banned_players_file' => $UMC_PATH_MC . '/server/bin/data/banned-players.json',
    'map_css_file'=> $UMC_PATH_MC . '/server/bin/data/map.css',
    'admins' => array('uncovery', '@console', '@Console'),
    'restart_time' => '16:00',
    'donation_users' => array( // these users want to be identified as donators
        '03a1544d-cd1f-4d39-8be4-8b4f71e4827c' => 'pagreifer',
        '03e0b740-4f53-47b2-ba8c-7b0432c95ca4' => 'Psychodrea',
        '68eb07ad-ff8a-4e7f-bdf1-7542fe06e211' => 'zekurom',
    ),
    'world_img_dim' => array(
        'aether' => array('max_coord' => 1536, 'chunkborder' => 512),
        'empire' => array('max_coord' => 2048, 'chunkborder' => 512),
        'empire_new' => array('max_coord' => 2048, 'chunkborder' => 512),
        'flatlands' => array('max_coord' => 1280, 'chunkborder' => 256),
        'skyblock' => array('max_coord' => 1280, 'chunkborder' => 256),
        'city' => array('max_coord' => 1100, 'chunkborder' => 512, 'top_offset' => 450, 'left_offset' => -600),
        'kingdom' => array('max_coord' => 3264, 'chunkborder' => 320),
        'draftlands' => array('max_coord' => 3264, 'chunkborder' => 320),
        'hunger' => array('max_coord' => 125, 'chunkborder' => 256),
    ),
    'world_data' => array(
        'empire'    => array('lot_size' => 128, 'lot_number' => 32, 'prefix' => 'emp',   'spawn' => 'emp_q17'),
        'flatlands' => array('lot_size' => 128, 'lot_number' => 20, 'prefix' => 'flat',  'spawn' => 'flat_k11'),
        'aether'    => array('lot_size' => 192, 'lot_number' => 16, 'prefix' => 'aet',   'spawn' => 'aet_h8'),
        'kingdom'   => array('lot_size' => 272, 'lot_number' => 24, 'prefix' => 'king',  'spawn' => 'king_m12_b'),
        'draftlands'=> array('lot_size' => 272, 'lot_number' => 24, 'prefix' => 'draft', 'spawn' => 'draft_m12_b'),
        'skyblock'  => array('lot_size' => 128, 'lot_number' => 20, 'prefix' => 'block', 'spawn' => 'block_k11'),
        'city'      => array('prefix' => 'city', 'spawn' => 'city_spawn'),
    ),
    'lot_limits' => array(
        'Guest'                 => array('empire' => 1, 'aether' => 0, 'kingdom' =>  0, 'skyblock' => 0, 'draftlands' =>  0),
        'Settler'               => array('empire' => 1, 'aether' => 0, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'SettlerDonator'        => array('empire' => 1, 'aether' => 0, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'Citizen'               => array('empire' => 1, 'aether' => 1, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'CitizenDonator'        => array('empire' => 1, 'aether' => 1, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'Architect'             => array('empire' => 2, 'aether' => 1, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'ArchitectDonator'      => array('empire' => 2, 'aether' => 1, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'Designer'              => array('empire' => 3, 'aether' => 1, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'DesignerDonator'       => array('empire' => 3, 'aether' => 1, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'Master'                => array('empire' => 4, 'aether' => 1, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'MasterDonator'         => array('empire' => 4, 'aether' => 1, 'kingdom' => 99, 'skyblock' => 1, 'draftlands' =>  0),
        'Elder'                 => array('empire' => 4, 'aether' => 2, 'kingdom' => 199, 'skyblock' => 1, 'draftlands' =>  199),
        'ElderDonator'          => array('empire' => 4, 'aether' => 2, 'kingdom' => 199, 'skyblock' => 1, 'draftlands' =>  199),
        'Owner'                 => array('empire' => 91, 'aether' => 92, 'kingdom' => 193, 'skyblock' => 94, 'draftlands' =>  195),
    ),
    'lot_costs' => array(
        '^king_[a-zA-Z]+\d*$' => array('base' => 10000, 'power' => 2),// main kingdom lot
        '^king_[a-zA-Z]+\d*_b$' => 40, // kingdom corner lot B
        '^king_[a-zA-Z]+\d*_a$' => 650, // vertical street lot A
        '^king_[a-zA-Z]+\d*_c$' => 650, // horizontal street lot
        '^draft_[a-zA-Z]+\d*$' => 50000, // main draftlands lot
        '^draft_[a-zA-Z]+\d*_b$' => 1000, // draftlands corner lot B
        '^draft_[a-zA-Z]+\d*_a$' => 5000, // vertical street lot A
        '^draft_[a-zA-Z]+\d*_c$' => 5000, // horizontal street lot
        '^block_[a-zA-Z]+\d*$' => 5000, // skyblock lot
    ),
    'lot_flags' => array('snow-fall', 'ice-form'),
    'lot_worlds_sql' => "('aet_', 'emp_', 'king', 'bloc', 'flat', 'draf')",
    'mint_lots' => array(
        'flatlands' => array(
            'reset' => 'Reset to Normal flatlands lot',
            'flat_d20' => 'Reset to lonely island lot (see flat_d20)',
            'flat_c20' => 'Reset to tech-lot (hollow, 5-block markers, see flat_c20)',
        ),
        'skyblock' => array(

        ),
        'draftlands' => array(
        ),
    ),
    'longterm' => array('SettlerDonator', 'CitizenDonator',
        'Architect', 'ArchitectDonator',
        'Designer', 'DesignerDonator',
        'Master', 'MasterDonator',
        'Elder', 'ElderDonator',
    ),
    // these are the legacy groups with donators. to be phased out
    'ranks' => array('Guest',
        'Settler','SettlerDonator',
        'Citizen','CitizenDonator',
        'Architect','ArchitectDonator',
        'Designer','DesignerDonator',
        'Master','MasterDonator',
        'Elder','ElderDonator',
        'Owner'
    ),
    // "Normal" userlevels (without special ranks such as donator
    'usergroups' => array(
        'Guest',
        'Settler',
        'Citizen',
        'Architect',
        'Designer',
        'Master',
        'Elder',
        'Owner'
    ),
    'list_length' => 100, // how long should web tables be maximal (default?)
);
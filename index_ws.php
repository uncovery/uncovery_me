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

global $UMC_USER, $UMC_ENV, $UMC_SETTING;
require_once(__DIR__ . '/core_include.php');

// we are coming from websend, so include wordpress
require_once($UMC_SETTING['path']['wordpress'] . '/wp-load.php');

if (!$UMC_ENV) {
    umc_set_environment();
}
umc_websend_main();
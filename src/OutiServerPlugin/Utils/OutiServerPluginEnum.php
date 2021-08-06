<?php

declare(strict_types=1);

namespace OutiServerPlugin\Utils;

interface OutiServerPluginEnum
{
    /* @var int 購入・売却 */
    const ADMINSHOP_ALL = 0;
    /* @var int 購入のみ */
    const ADMINSHOP_BUY_ONLY = 1;
    /* @var int 売却のみ */
    const ADMINSHOP_SELL_ONLY = 2;

    /* @var int スロットモード: 通常 */
    const SLOT_NORMAL = 0;
    /* @var int スロットモード: VIP */
    const SLOT_VIP = 1;
    /* @var int スロットモード: カイジ */
    const SLOT_KAIJI = 2;
}
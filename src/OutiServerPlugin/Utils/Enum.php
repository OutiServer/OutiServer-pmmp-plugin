<?php

declare(strict_types=1);

namespace OutiServerPlugin\Utils;

interface Enum
{
    // AdminShop modes
    const ADMINSHOP_ALL = 0;
    const ADMINSHOP_BUY_ONLY = 1;
    const ADMINSHOP_SELL_ONLY = 2;

    // AdminShop Category
    const ADMINSHOP_CATEGORY_PARENT = 0;
    const ADMINSHOP_CATEGORY_CHILD = 1;
}